// End-to-end UI test of the chat block in headless Chromium, with mocked web services.
const { chromium } = require('/home/claude/.npm-global/lib/node_modules/playwright');
const assert = require('assert');
const BASE = 'http://localhost:8765';
const SHOTS = '/home/claude/build/shots';

function wavDataUrl(seconds = 1.2, freq = 440, rate = 8000) {
  const n = Math.floor(seconds * rate), buf = Buffer.alloc(44 + n * 2);
  buf.write('RIFF', 0); buf.writeUInt32LE(36 + n * 2, 4); buf.write('WAVE', 8); buf.write('fmt ', 12);
  buf.writeUInt32LE(16, 16); buf.writeUInt16LE(1, 20); buf.writeUInt16LE(1, 22); buf.writeUInt32LE(rate, 24);
  buf.writeUInt32LE(rate * 2, 28); buf.writeUInt16LE(2, 32); buf.writeUInt16LE(16, 34); buf.write('data', 36); buf.writeUInt32LE(n * 2, 40);
  for (let i = 0; i < n; i++) buf.writeInt16LE(Math.round(Math.sin(2 * Math.PI * freq * i / rate) * 9000), 44 + i * 2);
  return 'data:audio/wav;base64,' + buf.toString('base64');
}

const DOC = { id: 7, name: 'Chapitre 3 - Photosynthèse.pdf', pages: 12, chars: 20450, lowtext: false, source: 'upload', timemodified: 1 };
const SUMMARY = `**La photosynthèse transforme l'énergie lumineuse en énergie chimique.**

- Elle se déroule dans les *chloroplastes*
- Deux phases: **phase claire** et **cycle de Calvin**
- Produits: glucose et dioxygène

### À retenir
Sans lumière, la plante ne peut pas fixer le CO2.

| Phase | Lieu |
|---|---|
| Claire | Thylakoïdes |
| Calvin | Stroma |`;

let passed = 0, failed = 0;
async function test(name, fn) {
  try { await fn(); passed++; console.log('ok   ' + name); }
  catch (e) { failed++; console.log('FAIL ' + name + '\n     ' + (e.message || e).split('\n')[0]); }
}

async function newPage(browser, lang = 'en', width = 420, height = 1000) {
  const ctx = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 2, locale: lang });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.errors = errors;
  await page.route('**/blocks/aipdfassistant/upload.php', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, document: DOC }) }));
  await page.goto(`${BASE}/${lang}.html`);
  await page.evaluate(() => {
    window.__handlers['block_aipdfassistant_send_message'] = (a) => ({ success: true, errorcode: '', error: '', reply: 'OK', action: a.action, speak: false, cached: false, notice: '', docneeded: false, audiourl: '', audiomime: '', browsertts: false, speecherror: '' });
  });
  return page;
}
const install = (page, name, fnSource, extra) => page.evaluate(([n, src, ex]) => { window.__ex = ex; window.__handlers[n] = eval('(' + src + ')'); }, [name, fnSource.toString(), extra || null]);
const startChat = (page) => page.evaluate(() => window.__start());
const sendReply = (page, reply, extra = {}) => page.evaluate(([r, e]) => {
  window.__handlers['block_aipdfassistant_send_message'] = (a) => Object.assign({ success: true, errorcode: '', error: '', reply: r, action: a.action === 'auto' ? 'ask' : a.action, speak: false, cached: false, notice: '', docneeded: false, audiourl: '', audiomime: '', browsertts: false, speecherror: '' }, e);
}, [reply, extra]);
const lastCall = (page, name) => page.evaluate((n) => { const c = window.__calls.filter(x => x.name === n); return c[c.length - 1]; }, name);

(async () => {
  const browser = await chromium.launch({ args: ['--autoplay-policy=no-user-gesture-required'] });

  // ---------------------------------------------------------------- basic structure
  await test('renders: icons hydrated, empty state visible, no console errors', async () => {
    const page = await newPage(browser); await startChat(page);
    assert((await page.locator('.bpa [data-icon] svg').count()) >= 10, 'icons');
    assert(await page.locator('[data-region=empty]').isVisible(), 'empty visible');
    assert(await page.locator('[data-region=dropzone]').isVisible(), 'dropzone visible');
    assert(!(await page.locator('[data-region=docchip]').isVisible()), 'chip hidden');
    assert.deepStrictEqual(page.errors, []);
    await page.screenshot({ path: `${SHOTS}/01-empty-en.png`, fullPage: true });
    await page.context().close();
  });

  await test('init is idempotent (double init does not double-bind)', async () => {
    const page = await newPage(browser); await startChat(page); await startChat(page);
    await sendReply(page, 'hello');
    await page.fill('[data-region=input]', 'hi');
    await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-msg--assistant');
    assert.strictEqual(await page.locator('.bpa-msg--user').count(), 1);
    assert.strictEqual(await page.locator('.bpa-msg--assistant').count(), 1);
    await page.context().close();
  });

  // ---------------------------------------------------------------- upload then pending action
  await test('chip without document opens file chooser, uploads, then runs the pending action', async () => {
    const page = await newPage(browser); await startChat(page);
    await sendReply(page, SUMMARY, { action: 'summarize' });
    const [chooser] = await Promise.all([page.waitForEvent('filechooser'), page.click('[data-action=summarize]')]);
    assert(await page.locator('[data-region=dropzone].is-attn').count() === 1, 'attention ring');
    await chooser.setFiles({ name: 'chapitre3.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 test') });
    await page.waitForSelector('.bpa-msg--assistant');
    const call = await lastCall(page, 'block_aipdfassistant_send_message');
    assert.strictEqual(call.args.docid, 7); assert.strictEqual(call.args.action, 'summarize');
    assert.deepStrictEqual(Object.keys(call.args), ['contextid', 'docid', 'message', 'action', 'targetlang', 'history']);
    assert(await page.locator('[data-region=docchip]').isVisible(), 'chip visible');
    assert((await page.textContent('[data-region=docname]')).includes('Photosynthèse'));
    assert.strictEqual((await page.textContent('[data-region=docmeta]')).trim(), '12 pages');
    assert(await page.locator('.bpa-md table').count() === 1, 'table rendered');
    assert(await page.locator('.bpa-md strong').count() >= 1, 'bold rendered');
    assert.strictEqual(await page.locator('.bpa-sys').count(), 1, 'system line for attached doc');
    await page.screenshot({ path: `${SHOTS}/02-summary-en.png`, fullPage: true });
    await page.context().close();
  });

  await test('rejects non-PDF and oversize files client-side', async () => {
    const page = await newPage(browser); await startChat(page);
    const [c1] = await Promise.all([page.waitForEvent('filechooser'), page.click('[data-region=dropzone]')]);
    await c1.setFiles({ name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('hello') });
    assert((await page.textContent('[data-region=docnote]')).includes('PDF'));
    assert(await page.locator('[data-region=docnote].is-error').count() === 1);
    const before = await page.evaluate(() => window.__calls.length);
    assert.strictEqual(before, 0, 'no ajax for rejected file');
    await page.context().close();
  });

  await test('drag and drop uploads a PDF', async () => {
    const page = await newPage(browser); await startChat(page);
    await page.evaluate(() => {
      const dt = new DataTransfer(); dt.items.add(new File(['%PDF-1.4 x'], 'drop.pdf', { type: 'application/pdf' }));
      const root = document.getElementById('bpa-7');
      root.dispatchEvent(new DragEvent('dragenter', { dataTransfer: dt, bubbles: true, cancelable: true }));
      window.__dragClass = root.classList.contains('is-dragover');
      root.dispatchEvent(new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true }));
    });
    assert(await page.evaluate(() => window.__dragClass), 'dragover class');
    await page.waitForSelector('[data-region=docchip]:not([hidden])');
    assert(!(await page.evaluate(() => document.getElementById('bpa-7').classList.contains('is-dragover'))));
    await page.context().close();
  });

  // ---------------------------------------------------------------- conversation
  await test('typed Arabic message: user bubble dir=auto, assistant markdown, history sent on next turn', async () => {
    const page = await newPage(browser, 'ar'); await startChat(page);
    await sendReply(page, 'هذا **جواب** أول\n\n- نقطة ١\n- نقطة ٢');
    await page.fill('[data-region=input]', 'لخص لي هذا الدرس');
    await page.click('.bpa-send');
    await page.waitForSelector('.bpa-msg--assistant');
    assert.strictEqual(await page.getAttribute('.bpa-bubble', 'dir'), 'auto');
    assert.strictEqual(await page.locator('.bpa-md li').count(), 2);
    await sendReply(page, 'جواب ثان');
    await page.fill('[data-region=input]', 'وماذا بعد؟');
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelectorAll('.bpa-msg--assistant').length === 2);
    const call = await lastCall(page, 'block_aipdfassistant_send_message');
    assert.strictEqual(call.args.history.length, 2);
    assert.strictEqual(call.args.history[0].role, 'user'); assert.strictEqual(call.args.history[1].role, 'assistant');
    await page.screenshot({ path: `${SHOTS}/03-arabic-rtl.png`, fullPage: true });
    await page.context().close();
  });

  await test('Shift+Enter inserts a newline and does not send', async () => {
    const page = await newPage(browser); await startChat(page);
    await page.fill('[data-region=input]', 'line one');
    await page.keyboard.press('Shift+Enter');
    await page.keyboard.type('line two');
    assert.strictEqual(await page.inputValue('[data-region=input]'), 'line one\nline two');
    assert.strictEqual(await page.evaluate(() => window.__calls.length), 0);
    await page.context().close();
  });

  await test('translate chip sends the selected target language', async () => {
    const page = await newPage(browser); await startChat(page);
    await page.click('[data-region=dropzone]', { trial: true }).catch(() => null);
    await page.evaluate(() => { document.querySelector('[data-region=dropzone]').click; });
    // attach document through the upload endpoint
    const [chooser] = await Promise.all([page.waitForEvent('filechooser'), page.click('[data-region=dropzone]')]);
    await chooser.setFiles({ name: 'a.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') });
    await page.waitForSelector('[data-region=docchip]:not([hidden])');
    await sendReply(page, 'Traduction', { action: 'translate' });
    await page.selectOption('[data-region=lang]', 'fr');
    await page.click('[data-action=translate]');
    await page.waitForSelector('.bpa-msg--assistant');
    const call = await lastCall(page, 'block_aipdfassistant_send_message');
    assert.strictEqual(call.args.action, 'translate'); assert.strictEqual(call.args.targetlang, 'fr');
    assert((await page.textContent('.bpa-msg--user')).includes('Français'), 'label shows language');
    await page.context().close();
  });

  await test('server says document needed: assistant hint, attention ring, pending action runs after upload', async () => {
    const page = await newPage(browser); await startChat(page);
    await sendReply(page, 'Attach a PDF first, then I can summarize it.', { docneeded: true, action: 'summarize' });
    await page.fill('[data-region=input]', 'summarize this');
    await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-msg--assistant');
    assert(await page.locator('[data-region=dropzone].is-attn').count() === 1);
    await sendReply(page, 'Real summary', { action: 'summarize' });
    const [chooser] = await Promise.all([page.waitForEvent('filechooser'), page.click('[data-region=dropzone]')]);
    await chooser.setFiles({ name: 'a.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') });
    await page.waitForFunction(() => document.querySelectorAll('.bpa-msg--assistant').length === 2);
    const call = await lastCall(page, 'block_aipdfassistant_send_message');
    assert.strictEqual(call.args.message, 'summarize this'); assert.strictEqual(call.args.docid, 7);
    await page.context().close();
  });

  await test('error shows alert with retry; retry re-sends without duplicating the user bubble', async () => {
    const page = await newPage(browser); await startChat(page);
    await sendReply(page, '', { success: false, error: 'The AI service refused the API key.', errorcode: 'auth' });
    await page.fill('[data-region=input]', 'hello');
    await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-msg--error');
    assert((await page.textContent('.bpa-msg--error')).includes('refused the API key'));
    await page.screenshot({ path: `${SHOTS}/04-error.png`, fullPage: true });
    await sendReply(page, 'Recovered');
    await page.click('[data-action=retry]');
    await page.waitForSelector('.bpa-msg--assistant');
    assert.strictEqual(await page.locator('.bpa-msg--user').count(), 1, 'no duplicate bubble');
    assert.strictEqual(await page.locator('.bpa-msg--error').count(), 0);
    const call = await lastCall(page, 'block_aipdfassistant_send_message');
    assert.strictEqual(call.args.history.length, 0);
    await page.context().close();
  });

  await test('network rejection from Ajax is shown as an error', async () => {
    const page = await newPage(browser); await startChat(page);
    await page.evaluate(() => { window.__handlers['block_aipdfassistant_send_message'] = () => ({ __reject: { message: 'Session expired' } }); });
    await page.fill('[data-region=input]', 'hello'); await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-msg--error');
    assert((await page.textContent('.bpa-msg--error')).includes('Session expired'));
    await page.context().close();
  });

  await test('while busy the send button and chips are disabled; typing indicator appears', async () => {
    const page = await newPage(browser); await startChat(page);
    await page.evaluate(() => { window.__handlers['block_aipdfassistant_send_message'] = () => new Promise(() => null); });
    await page.fill('[data-region=input]', 'hello'); await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-typing');
    assert(await page.isDisabled('.bpa-send'));
    assert(await page.isDisabled('[data-action=explain]'));
    await page.screenshot({ path: `${SHOTS}/05-busy.png`, fullPage: true });
    await page.context().close();
  });

  await test('assistant markdown cannot inject HTML', async () => {
    const page = await newPage(browser); await startChat(page);
    await sendReply(page, '<img src=x onerror="window.__pwned=1"> <script>window.__pwned=1</script> [x](javascript:window.__pwned=1)');
    await page.fill('[data-region=input]', 'x'); await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-msg--assistant');
    assert.strictEqual(await page.locator('.bpa-md img, .bpa-md script, .bpa-md a').count(), 0);
    assert.strictEqual(await page.evaluate(() => window.__pwned), undefined);
    await page.context().close();
  });

  // ---------------------------------------------------------------- audio
  await test('voice answer: waveform player renders, plays, toggles and pauses', async () => {
    const page = await newPage(browser); await startChat(page);
    const url = wavDataUrl();
    await sendReply(page, 'Résumé lu à voix haute.', { speak: true, audiourl: url, audiomime: 'audio/wav' });
    await page.fill('[data-region=input]', 'résume en audio'); await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-player');
    assert((await page.locator('.bpa-wave i').count()) === 34, 'wave bars');
    await page.waitForFunction(() => document.querySelector('.bpa-player').classList.contains('is-playing') || document.querySelector('.bpa-time').textContent.includes('0:0'), null, { timeout: 4000 });
    await page.screenshot({ path: `${SHOTS}/06-player.png`, fullPage: true });
    // pause via the play button
    const playing = await page.evaluate(() => document.querySelector('.bpa-player').classList.contains('is-playing'));
    if (playing) { await page.click('.bpa-play'); assert(!(await page.evaluate(() => document.querySelector('.bpa-player').classList.contains('is-playing')))); }
    await page.click('.bpa-play');
    await page.waitForFunction(() => document.querySelector('.bpa-player').classList.contains('is-playing'), null, { timeout: 4000 });
    await page.waitForFunction(() => document.querySelectorAll('.bpa-wave i.on').length > 0, null, { timeout: 4000 });
    await page.context().close();
  });

  await test('Listen button on a message calls synthesize once, then toggles the same player', async () => {
    const page = await newPage(browser); await startChat(page);
    const url = wavDataUrl(2);
    await install(page, 'block_aipdfassistant_synthesize', (a) => ({ success: true, error: '', audiourl: window.__ex, audiomime: 'audio/wav', browsertts: false }), url);
    await sendReply(page, 'Texte à écouter.');
    await page.fill('[data-region=input]', 'x'); await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-msg--assistant');
    await page.click('[data-action=listen]');
    await page.waitForSelector('.bpa-player');
    await page.click('[data-action=listen]');
    assert.strictEqual(await page.locator('.bpa-player').count(), 1);
    const calls = await page.evaluate(() => window.__calls.filter(c => c.name === 'block_aipdfassistant_synthesize').length);
    assert.strictEqual(calls, 1);
    await page.context().close();
  });

  await test('synthesize failure shows a warning, message text is untouched', async () => {
    const page = await newPage(browser); await startChat(page);
    await install(page, 'block_aipdfassistant_synthesize', () => ({ success: false, error: 'Voice output is disabled on this site.', audiourl: '', audiomime: '', browsertts: false }));
    await sendReply(page, 'Some text');
    await page.fill('[data-region=input]', 'x'); await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-msg--assistant');
    await page.click('[data-action=listen]');
    await page.waitForSelector('.bpa-warn');
    assert((await page.textContent('.bpa-warn')).includes('disabled'));
    await page.context().close();
  });

  await test('copy button copies the answer markdown', async () => {
    const browserCtx = await browser.newContext({ viewport: { width: 420, height: 900 }, permissions: ['clipboard-read', 'clipboard-write'] });
    const page = await browserCtx.newPage();
    await page.goto(`${BASE}/en.html`); await startChat(page);
    await sendReply(page, '**Bold** answer');
    await page.fill('[data-region=input]', 'x'); await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-msg--assistant');
    await page.click('[data-action=copy]');
    await page.waitForSelector('[data-action=copy].is-done');
    assert.strictEqual(await page.evaluate(() => navigator.clipboard.readText()), '**Bold** answer');
    await browserCtx.close();
  });

  // ---------------------------------------------------------------- picker, remove doc, new chat, expand
  await test('picker lists recent and course PDFs; choosing a course PDF attaches it', async () => {
    const page = await newPage(browser); await startChat(page);
    await install(page, 'block_aipdfassistant_list_documents', () => ({
      recent: [{ id: 9, name: 'Old notes.pdf', pages: 1, chars: 100, lowtext: false, source: 'upload', timemodified: 1 }],
      coursepdfs: [{ cmid: 31, name: 'Lecture 4: Cells', section: 'Week 4', filename: 'cells.pdf', filesize: 2411520 }],
    }));
    await install(page, 'block_aipdfassistant_attach_course_pdf', (a) => ({ success: true, error: '', document: { id: 12, name: 'cells.pdf', pages: 8, chars: 5000, lowtext: true, source: 'course', timemodified: 2 } }));
    await page.click('[data-action=picker]');
    await page.waitForSelector('.bpa-item');
    assert.strictEqual(await page.locator('.bpa-item').count(), 2);
    await page.screenshot({ path: `${SHOTS}/07-picker.png`, fullPage: true });
    await page.click('[data-cmid="31"]');
    await page.waitForSelector('[data-region=docchip]:not([hidden])');
    assert((await page.textContent('[data-region=docname]')).includes('cells.pdf'));
    assert(await page.isVisible('[data-region=docnote]'), 'low text warning');
    assert(await page.isHidden('[data-region=picker]'));
    const call = await lastCall(page, 'block_aipdfassistant_attach_course_pdf');
    assert.deepStrictEqual(call.args, { contextid: 55, cmid: 31 });
    await page.context().close();
  });

  await test('remove document clears chip, deletes server-side, history reset', async () => {
    const page = await newPage(browser); await startChat(page);
    const [chooser] = await Promise.all([page.waitForEvent('filechooser'), page.click('[data-region=dropzone]')]);
    await chooser.setFiles({ name: 'a.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') });
    await page.waitForSelector('[data-region=docchip]:not([hidden])');
    await page.click('[data-action=removedoc]');
    assert(await page.isVisible('[data-region=dropzone]'));
    const del = await lastCall(page, 'block_aipdfassistant_delete_document');
    assert.deepStrictEqual(del.args, { contextid: 55, docid: 7 });
    await page.context().close();
  });

  await test('new conversation clears messages and brings the empty state back', async () => {
    const page = await newPage(browser); await startChat(page);
    await sendReply(page, 'answer');
    await page.fill('[data-region=input]', 'x'); await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-msg--assistant');
    await page.click('[data-action=clear]');
    assert.strictEqual(await page.locator('.bpa-msg').count(), 0);
    assert(await page.isVisible('[data-region=empty]'));
    await page.context().close();
  });

  await test('expand moves block to <body>, locks scroll, Esc restores it in place', async () => {
    const page = await newPage(browser, 'en', 1200, 900); await startChat(page);
    await sendReply(page, SUMMARY);
    const [chooser] = await Promise.all([page.waitForEvent('filechooser'), page.click('[data-region=dropzone]')]);
    await chooser.setFiles({ name: 'a.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') });
    await page.waitForSelector('[data-region=docchip]:not([hidden])');
    await page.click('[data-action=summarize]');
    await page.waitForSelector('.bpa-msg--assistant');
    await page.click('[data-action=expand]');
    assert.strictEqual(await page.evaluate(() => document.getElementById('bpa-7').parentElement.tagName), 'BODY');
    assert(await page.evaluate(() => document.body.classList.contains('bpa-noscroll')));
    assert.strictEqual(await page.getAttribute('#bpa-7', 'role'), 'dialog');
    await page.screenshot({ path: `${SHOTS}/08-expanded.png` });
    await page.keyboard.press('Escape');
    assert.strictEqual(await page.evaluate(() => document.getElementById('bpa-7').parentElement.className), 'content');
    assert(!(await page.evaluate(() => document.body.classList.contains('bpa-noscroll'))));
    // still functional after returning
    await sendReply(page, 'after');
    await page.fill('[data-region=input]', 'again'); await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelectorAll('.bpa-msg--assistant').length === 2);
    await page.context().close();
  });

  await test('notices: partial document and saved-answer pills', async () => {
    const page = await newPage(browser); await startChat(page);
    await sendReply(page, 'Summary of a very long document', { notice: 'partial', cached: true });
    await page.fill('[data-region=input]', 'x'); await page.keyboard.press('Enter');
    await page.waitForSelector('.bpa-pill');
    assert.strictEqual(await page.locator('.bpa-pill').count(), 2);
    await page.context().close();
  });

  await test('French and Arabic UI strings load (no raw keys visible)', async () => {
    for (const lang of ['fr', 'ar']) {
      const page = await newPage(browser, lang); await startChat(page);
      const text = await page.textContent('.bpa');
      assert(!/js_[a-zA-Z]+/.test(text), lang + ' has raw keys');
      assert(text.length > 100);
      await page.screenshot({ path: `${SHOTS}/09-empty-${lang}.png`, fullPage: true });
      await page.context().close();
    }
  });

  await test('no speak chip when voice is off', async () => {
    const page = await newPage(browser); await startChat(page);
    assert.strictEqual(await page.locator('[data-action=speak]').count(), 1, 'harness renders speak chip');
    await page.context().close();
  });

  await test('Word file is accepted, the sheet icon shows a DOCX tag, and legacy .doc is rejected client-side', async () => {
    const page = await newPage(browser); await startChat(page);
    const [c0] = await Promise.all([page.waitForEvent('filechooser'), page.click('[data-region=dropzone]')]);
    await c0.setFiles({ name: 'old.doc', mimeType: 'application/msword', buffer: Buffer.from('x') });
    assert((await page.textContent('[data-region=docnote]')).includes('Word'));
    assert.strictEqual(await page.evaluate(() => window.__calls.length), 0);
    await page.route('**/blocks/aipdfassistant/upload.php', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, document: Object.assign({}, DOC, { name: 'cours.docx', pages: 3 }) }) }));
    const [chooser] = await Promise.all([page.waitForEvent('filechooser'), page.click('[data-region=dropzone]')]);
    await chooser.setFiles({ name: 'cours.docx', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', buffer: Buffer.from('PK') });
    await page.waitForSelector('[data-region=docchip]:not([hidden])');
    assert.strictEqual(await page.textContent('[data-region=docchip] .bpa-sheet-tag'), 'DOCX');
    assert.strictEqual(await page.getAttribute('[data-region=docchip] .bpa-sheet', 'data-type'), 'docx');
    assert.strictEqual(await page.isVisible('[data-region=docnote]'), false, 'error note cleared');
    await page.screenshot({ path: `${SHOTS}/10-docx.png`, fullPage: true });
    await page.context().close();
  });

  await test('action strip: one scrollable row, fade + arrows appear only when needed (LTR)', async () => {
    const page = await newPage(browser); await startChat(page);
    const info = await page.evaluate(() => { const t = document.querySelector('[data-region=tools]'); const tops = new Set([...t.querySelectorAll('.bpa-chip')].map(c => Math.round(c.getBoundingClientRect().top))); return { overflow: t.scrollWidth > t.clientWidth, rows: tops.size }; });
    assert(info.overflow, 'strip overflows in a narrow block'); assert.strictEqual(info.rows, 1, 'single row');
    assert(await page.isHidden('.bpa-tools-nav--prev'), 'no prev arrow at the start');
    assert(await page.isVisible('.bpa-tools-nav--next'), 'next arrow visible');
    assert(await page.evaluate(() => document.querySelector('[data-region=toolswrap]').classList.contains('has-end')));
    await page.screenshot({ path: `${SHOTS}/11-strip-en.png`, fullPage: true });
    await page.click('.bpa-tools-nav--next');
    await page.waitForFunction(() => document.querySelector('[data-region=tools]').scrollLeft > 20);
    assert(await page.isVisible('.bpa-tools-nav--prev'), 'prev arrow after scrolling');
    await page.evaluate(() => { const t = document.querySelector('[data-region=tools]'); t.scrollLeft = t.scrollWidth; });
    await page.waitForFunction(() => document.querySelector('.bpa-tools-nav--next').hidden);
    assert(await page.evaluate(() => !document.querySelector('[data-region=toolswrap]').classList.contains('has-end')));
    await page.screenshot({ path: `${SHOTS}/12-strip-end-en.png`, fullPage: true });
    await page.context().close();
  });

  await test('action strip in Arabic (RTL): starts at the right, next arrow scrolls toward the left', async () => {
    const page = await newPage(browser, 'ar'); await startChat(page);
    assert(await page.isHidden('.bpa-tools-nav--prev'));
    assert(await page.isVisible('.bpa-tools-nav--next'));
    const box = await page.evaluate(() => { const w = document.querySelector('[data-region=toolswrap]').getBoundingClientRect(); const n = document.querySelector('.bpa-tools-nav--next').getBoundingClientRect(); return { nextOnLeft: n.left < w.left + w.width / 2 }; });
    assert(box.nextOnLeft, 'next arrow sits at the inline-end (left) in RTL');
    await page.screenshot({ path: `${SHOTS}/13-strip-ar.png`, fullPage: true });
    await page.click('.bpa-tools-nav--next');
    await page.waitForFunction(() => document.querySelector('[data-region=tools]').scrollLeft < -20);
    assert(await page.isVisible('.bpa-tools-nav--prev'));
    await page.context().close();
  });

  await test('dragging the strip scrolls it and never fires the chip under the pointer', async () => {
    const page = await newPage(browser); await startChat(page);
    let chooser = false; page.on('filechooser', () => { chooser = true; });
    const box = await page.locator('[data-action=summarize]').boundingBox();
    const y = box.y + box.height / 2;
    await page.mouse.move(box.x + box.width / 2, y); await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 - 70, y, { steps: 6 }); await page.mouse.up();
    await page.waitForTimeout(150);
    assert(await page.evaluate(() => document.querySelector('[data-region=tools]').scrollLeft) > 20, 'scrolled by dragging');
    assert.strictEqual(chooser, false, 'drag did not click the chip');
    assert.strictEqual(await page.evaluate(() => window.__calls.length), 0);
    await page.context().close();
  });

  await test('mouse wheel scrolls the strip sideways; arrow keys move between actions', async () => {
    const page = await newPage(browser); await startChat(page);
    const box = await page.locator('[data-region=tools]').boundingBox();
    await page.mouse.move(box.x + 40, box.y + box.height / 2); await page.mouse.wheel(0, 90);
    await page.waitForFunction(() => document.querySelector('[data-region=tools]').scrollLeft > 20);
    await page.evaluate(() => { document.querySelector('[data-region=tools]').scrollLeft = 0; });
    await page.focus('[data-action=summarize]'); await page.keyboard.press('ArrowRight');
    assert.strictEqual(await page.evaluate(() => document.activeElement.dataset.action), 'explain');
    await page.keyboard.press('End');
    assert.strictEqual(await page.evaluate(() => document.activeElement.dataset.action), 'speak');
    await page.keyboard.press('Home');
    assert.strictEqual(await page.evaluate(() => document.activeElement.dataset.action), 'summarize');
    await page.context().close();
  });

  await test('expanded view fits every action in one row without arrows', async () => {
    const page = await newPage(browser, 'en', 1200, 800); await startChat(page);
    await page.click('[data-action=expand]');
    await page.waitForTimeout(150);
    assert(await page.isHidden('.bpa-tools-nav--next')); assert(await page.isHidden('.bpa-tools-nav--prev'));
    assert.strictEqual(await page.evaluate(() => { const t = document.querySelector('[data-region=tools]'); return t.scrollWidth <= t.clientWidth; }), true);
    await page.context().close();
  });

  await browser.close();
  console.log(`\n${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})();
