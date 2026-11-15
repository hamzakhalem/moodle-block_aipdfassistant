// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Chat controller of the AI PDF Assistant block.
 *
 * @module     block_aipdfassistant/chat
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['block_aipdfassistant/api', 'block_aipdfassistant/markdown', 'block_aipdfassistant/icons', 'core/templates'],
        function(Api, Markdown, Icons, Templates) {
    'use strict';

    const NEEDS_DOC = ['summarize', 'explain', 'translate', 'quiz', 'filename'];
    const MAX_HISTORY = 10;
    const WAVE_BARS = 34;
    const reduceMotion = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const esc = Markdown.escapeHtml;

    const formatTime = function(seconds) {
        if (!isFinite(seconds) || seconds < 0) {
            return '0:00';
        }
        const s = Math.floor(seconds);
        return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
    };

    const formatSize = function(bytes) {
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    };

    // Small deterministic PRNG so a given audio always draws the same waveform.
    const seeded = function(text) {
        let h = 2166136261;
        for (let i = 0; i < text.length; i++) {
            h ^= text.charCodeAt(i);
            h = Math.imul(h, 16777619);
        }
        return function() {
            h += 0x6D2B79F5;
            let t = h;
            t = Math.imul(t ^ t >>> 15, t | 1);
            t ^= t + Math.imul(t ^ t >>> 7, t | 61);
            return ((t ^ t >>> 14) >>> 0) / 4294967296;
        };
    };

    const detectSpeechLang = function(text, uilang) {
        const letters = (text.match(/\p{L}/gu) || []).length || 1;
        const arabic = (text.match(/[\u0600-\u06FF]/g) || []).length;
        if (arabic / letters > 0.3) {
            return 'ar-SA';
        }
        if (/[àâçéèêëîïôûùüÿœ]|\b(le|la|les|des|une|est|et|pour|dans|que)\b/i.test(text)) {
            return 'fr-FR';
        }
        return uilang === 'fr' ? 'fr-FR' : 'en-US';
    };

    const pickVoice = function(lang, gender) {
        const synth = window.speechSynthesis;
        const voices = synth && synth.getVoices ? synth.getVoices() : [];
        const prefix = lang.split('-')[0].toLowerCase();
        const pool = voices.filter((v) => (v.lang || '').toLowerCase().indexOf(prefix) === 0);
        if (!pool.length) {
            return null;
        }
        const female = /(\bfemale\b|woman|hoda|salma|zariyah|amina|zira|aria|jenny|samantha|julie|hortense|denise|eloise|amelie|paulina|karen|fiona)/i;
        const male = /(\bmale\b|naayf|hamed|shakir|maged|david|\bmark\b|guy|daniel|thomas|paul|henri|claude|antoine|alex|fred|jorge)/i;
        const match = pool.filter((v) => (gender === 'male' ? male : female).test(v.name));
        return match.length ? match[0] : pool[0];
    };

    class Chat {
        constructor(root, cfg) {
            this.root = root;
            this.cfg = cfg;
            this.strings = cfg.strings || {};
            this.doc = null;
            this.history = [];
            this.busy = false;
            this.pending = null;
            this.player = null;
            this.recognition = null;
            this.placeholderNode = null;
            this.msgData = new WeakMap();
            this.onKeydown = (e) => {
                if (e.key === 'Escape' && this.root.classList.contains('is-expanded')) {
                    this.toggleExpand(false);
                }
            };

            this.hydrateIcons(root);
            this.log = this.region('log');
            this.empty = this.region('empty');
            this.input = this.region('input');
            this.fileInput = this.region('file');
            this.dropzone = this.region('dropzone');
            this.langSelect = this.region('lang');
            this.setupMic();
            this.setupTools();
            this.bind();
            this.setDoc(null, true);
            this.autosize();
        }

        t(key, a) {
            const value = this.strings[key] !== undefined ? this.strings[key] : key;
            return a !== undefined ? value.replace('%a%', a) : value;
        }

        region(name, scope) {
            return (scope || this.root).querySelector('[data-region="' + name + '"]');
        }

        hydrateIcons(scope) {
            scope.querySelectorAll('[data-icon]').forEach(function(node) {
                node.innerHTML = Icons[node.dataset.icon] || '';
            });
        }

        // ------------------------------------------------------------------ action strip

        setupTools() {
            this.toolsWrap = this.region('toolswrap');
            this.tools = this.region('tools');
            this.toolsPrev = this.toolsWrap.querySelector('.bpa-tools-nav--prev');
            this.toolsNext = this.toolsWrap.querySelector('.bpa-tools-nav--next');
            const tools = this.tools;
            let queued = false;
            const refresh = () => {
                if (queued) {
                    return;
                }
                queued = true;
                window.requestAnimationFrame(() => {
                    queued = false;
                    this.updateTools();
                });
            };
            this.refreshTools = refresh;
            tools.addEventListener('scroll', refresh, {passive: true});
            window.addEventListener('resize', refresh);
            if (window.ResizeObserver) {
                new window.ResizeObserver(refresh).observe(tools);
            }
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(refresh);
            }
            refresh();

            // Vertical mouse wheel scrolls the strip sideways, but only while it can still move that way.
            tools.addEventListener('wheel', (e) => {
                if (Math.abs(e.deltaX) >= Math.abs(e.deltaY) || tools.scrollWidth <= tools.clientWidth) {
                    return;
                }
                const rtl = window.getComputedStyle(tools).direction === 'rtl';
                const step = (rtl ? -1 : 1) * e.deltaY;
                const max = tools.scrollWidth - tools.clientWidth;
                const next = tools.scrollLeft + step;
                if ((rtl ? -next : next) < 0 || (rtl ? -next : next) > max) {
                    return;
                }
                e.preventDefault();
                tools.scrollLeft = next;
            }, {passive: false});

            // Drag with the mouse; a drag never triggers the chip under the pointer.
            let startX = 0;
            let startScroll = 0;
            let pressed = false;
            let dragged = false;
            tools.addEventListener('pointerdown', (e) => {
                if (e.pointerType !== 'mouse' || e.button !== 0 || e.target.closest('select')) {
                    return;
                }
                pressed = true;
                dragged = false;
                startX = e.clientX;
                startScroll = tools.scrollLeft;
            });
            tools.addEventListener('pointermove', (e) => {
                if (!pressed) {
                    return;
                }
                const delta = e.clientX - startX;
                if (!dragged && Math.abs(delta) > 5) {
                    dragged = true;
                    tools.classList.add('is-dragging');
                    tools.setPointerCapture(e.pointerId);
                }
                if (dragged) {
                    tools.scrollLeft = startScroll - delta;
                }
            });
            const release = () => {
                if (!pressed) {
                    return;
                }
                pressed = false;
                tools.classList.remove('is-dragging');
                if (dragged) {
                    const stop = (event) => {
                        event.stopPropagation();
                        event.preventDefault();
                    };
                    tools.addEventListener('click', stop, {capture: true, once: true});
                    setTimeout(() => tools.removeEventListener('click', stop, true), 0);
                }
            };
            tools.addEventListener('pointerup', release);
            tools.addEventListener('pointercancel', release);

            // Arrow keys move between the actions (toolbar pattern).
            tools.addEventListener('keydown', (e) => {
                const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
                if (keys.indexOf(e.key) === -1 || e.target.tagName === 'SELECT') {
                    return;
                }
                const items = Array.prototype.slice.call(tools.querySelectorAll('button:not(:disabled)'));
                const index = items.indexOf(document.activeElement);
                if (index === -1) {
                    return;
                }
                const rtl = window.getComputedStyle(tools).direction === 'rtl';
                let target = index;
                if (e.key === 'Home') {
                    target = 0;
                } else if (e.key === 'End') {
                    target = items.length - 1;
                } else {
                    const forward = (e.key === 'ArrowRight') !== rtl;
                    target = Math.max(0, Math.min(items.length - 1, index + (forward ? 1 : -1)));
                }
                e.preventDefault();
                items[target].focus();
            });
        }

        updateTools() {
            const tools = this.tools;
            const max = tools.scrollWidth - tools.clientWidth;
            const position = Math.abs(tools.scrollLeft);
            const hasStart = max > 2 && position > 2;
            const hasEnd = max > 2 && position < max - 2;
            this.toolsWrap.classList.toggle('has-start', hasStart);
            this.toolsWrap.classList.toggle('has-end', hasEnd);
            this.toolsPrev.hidden = !hasStart;
            this.toolsNext.hidden = !hasEnd;
        }

        scrollTools(direction) {
            const tools = this.tools;
            const rtl = window.getComputedStyle(tools).direction === 'rtl';
            const amount = Math.max(120, tools.clientWidth * 0.7);
            const left = (rtl ? -1 : 1) * direction * amount;
            if (tools.scrollBy) {
                tools.scrollBy({left: left, behavior: reduceMotion() ? 'auto' : 'smooth'});
            } else {
                tools.scrollLeft += left;
            }
        }

        // ------------------------------------------------------------------ events

        bind() {
            const root = this.root;
            root.addEventListener('click', (e) => {
                const button = e.target.closest('[data-action]');
                if (button && root.contains(button)) {
                    this.onAction(button.dataset.action, button);
                }
            });
            this.region('composer').addEventListener('submit', (e) => {
                e.preventDefault();
                this.submit();
            });
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                    e.preventDefault();
                    this.submit();
                }
            });
            this.input.addEventListener('input', () => this.autosize());
            this.fileInput.addEventListener('change', () => {
                const file = this.fileInput.files && this.fileInput.files[0];
                this.fileInput.value = '';
                if (file) {
                    this.upload(file);
                }
            });
            this.dropzone.addEventListener('click', () => this.fileInput.click());
            this.dropzone.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.fileInput.click();
                }
            });
            let depth = 0;
            root.addEventListener('dragenter', (e) => {
                e.preventDefault();
                depth++;
                root.classList.add('is-dragover');
            });
            root.addEventListener('dragover', (e) => e.preventDefault());
            root.addEventListener('dragleave', () => {
                depth = Math.max(0, depth - 1);
                if (!depth) {
                    root.classList.remove('is-dragover');
                }
            });
            root.addEventListener('drop', (e) => {
                e.preventDefault();
                depth = 0;
                root.classList.remove('is-dragover');
                const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                if (file) {
                    this.upload(file);
                }
            });
            this.region('picker').addEventListener('click', (e) => {
                const item = e.target.closest('[data-docid],[data-cmid]');
                if (!item) {
                    return;
                }
                if (item.dataset.docid) {
                    this.useRecent(Number(item.dataset.docid));
                } else {
                    this.useCourse(Number(item.dataset.cmid), item);
                }
            });
        }

        onAction(action, button) {
            switch (action) {
                case 'clear':
                    this.reset();
                    break;
                case 'expand':
                    this.toggleExpand();
                    break;
                case 'removedoc':
                    this.removeDoc();
                    break;
                case 'picker':
                    this.togglePicker();
                    break;
                case 'attach':
                    this.fileInput.click();
                    break;
                case 'toolsprev':
                    this.scrollTools(-1);
                    break;
                case 'toolsnext':
                    this.scrollTools(1);
                    break;
                case 'mic':
                    this.toggleMic();
                    break;
                case 'copy':
                    this.copy(button);
                    break;
                case 'listen':
                    this.listen(button);
                    break;
                case 'retry':
                    this.retry(button);
                    break;
                default:
                    this.quick(action);
            }
        }

        // ------------------------------------------------------------------ documents

        setDoc(doc, silent) {
            const changed = !this.doc || !doc || this.doc.id !== doc.id;
            this.doc = doc;
            this.region('dropzone').hidden = !!doc;
            const chip = this.region('docchip');
            chip.hidden = !doc;
            this.root.dataset.state = doc ? 'ready' : 'empty';
            this.input.placeholder = this.t(doc ? 'placeholder' : 'placeholderNoDoc');
            if (doc) {
                const ext = ((doc.name.match(/\.(\w+)$/) || [])[1] || 'pdf').toLowerCase();
                const sheet = chip.querySelector('.bpa-sheet');
                sheet.dataset.type = ext;
                sheet.querySelector('.bpa-sheet-tag').textContent = ext.toUpperCase().slice(0, 4);
                this.renderDocName(this.region('docname'), doc.name);
                const pages = doc.pages === 1 ? this.t('page') : this.t('pages', doc.pages);
                this.region('docmeta').textContent = pages;
                this.note(doc.lowtext ? this.t('lowtext') : '', false);
                this.hidePicker();
                if (!silent && changed) {
                    this.history = [];
                    this.addSystem(this.t('docAttached', doc.name));
                }
            } else {
                this.note('');
            }
            this.refreshTools();
            if (doc && this.pending) {
                const pending = this.pending;
                this.pending = null;
                this.send(pending.text, pending.action);
            }
        }

        renderDocName(node, name) {
            // Keep the extension in its own left-to-right run so Arabic names do not show ".pdf" in the wrong place.
            node.textContent = '';
            const match = /^(.*?)(\.[A-Za-z0-9]{1,5})$/.exec(name);
            if (!match) {
                node.textContent = name;
                return;
            }
            node.appendChild(document.createTextNode(match[1]));
            const ext = document.createElement('bdi');
            ext.dir = 'ltr';
            ext.textContent = match[2];
            node.appendChild(ext);
        }

        removeDoc() {
            const doc = this.doc;
            this.pending = null;
            this.setDoc(null, true);
            this.history = [];
            if (doc) {
                Api.deleteDocument(this.cfg.contextid, doc.id).catch(() => null);
            }
        }

        note(message, isError) {
            const node = this.region('docnote');
            node.textContent = message || '';
            node.hidden = !message;
            node.classList.toggle('is-error', !!isError);
        }

        attention() {
            this.dropzone.classList.remove('is-attn');
            // Restart the CSS animation.
            void this.dropzone.offsetWidth;
            this.dropzone.classList.add('is-attn');
            setTimeout(() => this.dropzone.classList.remove('is-attn'), 1800);
        }

        upload(file) {
            if (!/\.(pdf|docx)$/i.test(file.name) && file.type !== 'application/pdf') {
                this.note(this.t('errorType'), true);
                return;
            }
            if (file.size > this.cfg.maxbytes) {
                this.note(this.t('errorSize', this.cfg.maxmb), true);
                return;
            }
            this.note('');
            this.setUploading(true, file.name);
            Api.upload(this.cfg.uploadurl, {contextid: this.cfg.contextid, sesskey: this.cfg.sesskey}, file,
                (ratio) => this.setProgress(ratio))
                .then((res) => {
                    this.setUploading(false);
                    if (res && res.success) {
                        this.setDoc(res.document);
                    } else {
                        this.pending = null;
                        this.note((res && res.error) || this.t('uploadFailed'), true);
                    }
                })
                .catch(() => {
                    this.setUploading(false);
                    this.pending = null;
                    this.note(this.t('uploadFailed'), true);
                });
        }

        setUploading(on, name) {
            const zone = this.dropzone;
            zone.classList.toggle('is-busy', on);
            const progress = this.region('progress');
            progress.hidden = !on;
            const title = zone.querySelector('strong');
            const hint = zone.querySelector('small');
            if (on) {
                zone.dataset.title = title.textContent;
                zone.dataset.hint = hint.textContent;
                title.textContent = name;
                hint.textContent = this.t('uploading');
                this.setProgress(0);
            } else if (zone.dataset.title) {
                title.textContent = zone.dataset.title;
                hint.textContent = zone.dataset.hint;
            }
        }

        setProgress(ratio) {
            this.region('progressbar').style.width = Math.round(ratio * 100) + '%';
            if (ratio >= 1) {
                this.dropzone.querySelector('small').textContent = this.t('reading');
            }
        }

        togglePicker() {
            const picker = this.region('picker');
            const open = picker.hidden;
            if (!open) {
                this.hidePicker();
                return;
            }
            picker.hidden = false;
            this.root.querySelector('[data-action="picker"]').setAttribute('aria-expanded', 'true');
            this.renderPicker({loading: true});
            Api.listDocuments(this.cfg.contextid).then((data) => this.renderPicker(this.pickerContext(data)))
                .catch(() => this.renderPicker({error: true}));
        }

        hidePicker() {
            this.region('picker').hidden = true;
            this.root.querySelector('[data-action="picker"]').setAttribute('aria-expanded', 'false');
        }

        /**
         * Template context of the document picker.
         *
         * @param {Object} data Response of the list_documents service.
         * @return {Object}
         */
        pickerContext(data) {
            const recent = (data.recent || []).map((doc) => ({
                id: doc.id, name: doc.name, pages: doc.pages, single: doc.pages === 1
            }));
            const course = (this.cfg.allowcourse ? (data.coursepdfs || []) : []).map((file) => ({
                cmid: file.cmid, name: file.name, meta: file.section + ' \u00b7 ' + formatSize(file.filesize)
            }));
            return {
                empty: !recent.length && !course.length,
                hasrecent: recent.length > 0,
                recent: recent,
                hascourse: course.length > 0,
                course: course
            };
        }

        /**
         * Render the picker with the block_aipdfassistant/picker Mustache template.
         *
         * @param {Object} context Template context.
         * @return {Promise}
         */
        renderPicker(context) {
            const picker = this.region('picker');
            return Templates.renderForPromise('block_aipdfassistant/picker', context).then(({html, js}) => {
                Templates.replaceNodeContents(picker, html, js);
                this.hydrateIcons(picker);
                return picker;
            }).catch(() => {
                picker.textContent = this.t('errorGeneric');
            });
        }

        useRecent(id) {
            Api.listDocuments(this.cfg.contextid).then((data) => {
                const doc = (data.recent || []).find((d) => d.id === id);
                if (doc) {
                    this.setDoc(doc);
                }
            });
        }

        useCourse(cmid, item) {
            item.classList.add('is-busy');
            Api.attachCoursePdf(this.cfg.contextid, cmid).then((res) => {
                item.classList.remove('is-busy');
                if (res.success && res.document) {
                    this.setDoc(res.document);
                } else {
                    this.note(res.error || this.t('errorGeneric'), true);
                    this.hidePicker();
                }
            }).catch(() => {
                item.classList.remove('is-busy');
                this.note(this.t('errorGeneric'), true);
            });
        }

        // ------------------------------------------------------------------ conversation

        actionLabel(action) {
            if (action === 'translate') {
                const option = this.langSelect.options[this.langSelect.selectedIndex];
                return this.t('translate') + ' → ' + (option ? option.textContent : '');
            }
            return this.t(action);
        }

        quick(action) {
            if (this.busy) {
                return;
            }
            if (NEEDS_DOC.indexOf(action) !== -1 && !this.doc) {
                this.pending = {text: '', action: action};
                this.attention();
                this.fileInput.click();
                return;
            }
            this.send('', action);
        }

        submit() {
            const text = this.input.value.trim();
            if (!text || this.busy) {
                return;
            }
            this.input.value = '';
            this.autosize();
            this.send(text, 'auto');
        }

        send(text, action, retry) {
            if (this.busy) {
                return;
            }
            const shown = text || this.actionLabel(action);
            const history = this.history.slice(-MAX_HISTORY);
            if (!retry) {
                this.hideEmpty();
                this.addUser(shown);
            }
            this.history.push({role: 'user', content: shown});
            this.setBusy(true);
            this.typing = this.addTyping();
            Api.sendMessage({
                contextid: this.cfg.contextid,
                docid: this.doc ? this.doc.id : 0,
                message: text,
                action: action,
                targetlang: this.langSelect.value,
                history: history
            }).then((res) => this.onReply(res, text, action))
                .catch((err) => {
                    this.history.pop();
                    this.removeTyping();
                    this.addError((err && err.message) || this.t('errorGeneric'), {text: text, action: action});
                })
                .then(() => this.setBusy(false));
        }

        onReply(res, text, action) {
            this.removeTyping();
            if (!res.success) {
                this.history.pop();
                this.addError(res.error || this.t('errorGeneric'), {text: text, action: action});
                return;
            }
            if (res.docneeded) {
                this.history.pop();
                this.pending = {text: text, action: action};
                this.attention();
                this.addAssistant(res.reply, {});
                return;
            }
            this.history.push({role: 'assistant', content: res.reply});
            this.addAssistant(res.reply, {
                notice: res.notice,
                cached: res.cached,
                audio: res.audiourl ? {url: res.audiourl} : null,
                browsertts: res.browsertts,
                speecherror: res.speecherror,
                autoplay: res.speak
            });
        }

        retry(button) {
            const article = button.closest('.bpa-msg');
            const ctx = this.msgData.get(article);
            if (!ctx || this.busy) {
                return;
            }
            article.remove();
            this.send(ctx.text, ctx.action, true);
        }

        setBusy(on) {
            this.busy = on;
            this.root.classList.toggle('is-busy', on);
            this.log.setAttribute('aria-busy', on ? 'true' : 'false');
            this.root.querySelectorAll('.bpa-chip, .bpa-chip-main, .bpa-send').forEach(function(node) {
                node.disabled = on;
            });
        }

        reset() {
            this.stopPlayer();
            this.history = [];
            this.pending = null;
            this.log.querySelectorAll('.bpa-msg, .bpa-sys, .bpa-typing').forEach((node) => node.remove());
            this.empty.hidden = false;
        }

        hideEmpty() {
            this.empty.hidden = true;
        }

        scrollToEnd() {
            const top = this.log.scrollHeight;
            if (this.log.scrollTo) {
                this.log.scrollTo({top: top, behavior: reduceMotion() ? 'auto' : 'smooth'});
            } else {
                this.log.scrollTop = top;
            }
        }

        scrollToMessage(node) {
            const top = Math.max(0, node.offsetTop - 10);
            if (this.log.scrollTo) {
                this.log.scrollTo({top: top, behavior: reduceMotion() ? 'auto' : 'smooth'});
            } else {
                this.log.scrollTop = top;
            }
        }

        // ------------------------------------------------------------------ message rendering

        addUser(text) {
            const article = document.createElement('article');
            article.className = 'bpa-msg bpa-msg--user';
            const bubble = document.createElement('div');
            bubble.className = 'bpa-bubble';
            bubble.dir = 'auto';
            bubble.textContent = text;
            article.appendChild(bubble);
            this.log.appendChild(article);
            this.scrollToEnd();
        }

        addSystem(text) {
            this.hideEmpty();
            const line = document.createElement('div');
            line.className = 'bpa-sys';
            line.innerHTML = '<span class="bpa-sys-icon">' + Icons.file + '</span><span dir="auto"></span>';
            line.lastChild.textContent = text;
            this.log.appendChild(line);
            this.scrollToEnd();
        }

        addTyping() {
            const node = document.createElement('div');
            node.className = 'bpa-typing';
            node.innerHTML = '<span class="bpa-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="bpa-typing-text"></span>';
            node.lastChild.textContent = this.t('thinking');
            this.log.appendChild(node);
            this.scrollToEnd();
            return node;
        }

        removeTyping() {
            if (this.typing) {
                this.typing.remove();
                this.typing = null;
            }
        }

        addAssistant(markdown, opts) {
            this.hideEmpty();
            const article = document.createElement('article');
            article.className = 'bpa-msg bpa-msg--assistant';
            article.innerHTML = '<div class="bpa-msg-who"></div><div class="bpa-md"></div><div class="bpa-msg-extra"></div>' +
                '<div class="bpa-msg-actions"></div>';
            article.querySelector('.bpa-msg-who').textContent = this.t('assistant');
            article.querySelector('.bpa-md').innerHTML = Markdown.render(markdown);
            const extra = article.querySelector('.bpa-msg-extra');
            const notices = [];
            if (opts.notice === 'partial') {
                notices.push(this.t('noticePartial'));
            } else if (opts.notice === 'translatetruncated') {
                notices.push(this.t('noticeTranslate'));
            }
            if (opts.cached) {
                notices.push(this.t('cached'));
            }
            notices.forEach(function(text) {
                const pill = document.createElement('span');
                pill.className = 'bpa-pill';
                pill.textContent = text;
                extra.appendChild(pill);
            });
            if (opts.speecherror) {
                const warn = document.createElement('p');
                warn.className = 'bpa-warn';
                warn.textContent = opts.speecherror;
                extra.appendChild(warn);
            }
            const actions = article.querySelector('.bpa-msg-actions');
            actions.innerHTML = '<button type="button" class="bpa-mini" data-action="copy">' + Icons.copy + '<span>' +
                esc(this.t('copy')) + '</span></button>' +
                (this.cfg.canspeak ? '<button type="button" class="bpa-mini" data-action="listen">' + Icons.volume +
                    '<span>' + esc(this.t('listen')) + '</span></button>' : '');
            this.msgData.set(article, {markdown: markdown});
            this.log.appendChild(article);
            if (opts.audio) {
                this.attachPlayer(article, {url: opts.audio.url}, opts.autoplay);
            } else if (opts.browsertts) {
                this.attachPlayer(article, {text: markdown}, opts.autoplay);
            }
            this.scrollToMessage(article);
            return article;
        }

        addError(message, ctx) {
            this.hideEmpty();
            const article = document.createElement('article');
            article.className = 'bpa-msg bpa-msg--error';
            article.setAttribute('role', 'alert');
            article.innerHTML = '<span class="bpa-error-icon">' + Icons.alert + '</span><div class="bpa-error-body"><p dir="auto"></p>' +
                (ctx ? '<button type="button" class="bpa-mini" data-action="retry">' + Icons.retry + '<span>' +
                    esc(this.t('retry')) + '</span></button>' : '') + '</div>';
            article.querySelector('p').textContent = message;
            if (ctx) {
                this.msgData.set(article, ctx);
            }
            this.log.appendChild(article);
            this.scrollToEnd();
        }

        // ------------------------------------------------------------------ actions on messages

        copy(button) {
            const article = button.closest('.bpa-msg');
            const data = this.msgData.get(article);
            if (!data) {
                return;
            }
            const done = () => {
                const label = button.querySelector('span');
                const old = label.textContent;
                button.classList.add('is-done');
                label.textContent = this.t('copied');
                setTimeout(() => {
                    button.classList.remove('is-done');
                    label.textContent = old;
                }, 1400);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(data.markdown).then(done).catch(() => null);
                return;
            }
            const area = document.createElement('textarea');
            area.value = data.markdown;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy');
                done();
            } catch (e) {
                // Nothing else to try.
            }
            area.remove();
        }

        listen(button) {
            const article = button.closest('.bpa-msg');
            const data = this.msgData.get(article);
            if (!data) {
                return;
            }
            if (data.player) {
                data.player.toggle();
                return;
            }
            if (this.cfg.browsertts) {
                this.attachPlayer(article, {text: data.markdown}, true);
                return;
            }
            button.disabled = true;
            button.classList.add('is-busy');
            Api.synthesize(this.cfg.contextid, data.markdown).then((res) => {
                if (res.success && res.audiourl) {
                    this.attachPlayer(article, {url: res.audiourl}, true);
                } else if (res.success && res.browsertts) {
                    this.attachPlayer(article, {text: data.markdown}, true);
                } else {
                    this.showMessageWarning(article, res.error || this.t('errorGeneric'));
                }
            }).catch((err) => this.showMessageWarning(article, (err && err.message) || this.t('errorGeneric')))
                .then(() => {
                    button.disabled = false;
                    button.classList.remove('is-busy');
                });
        }

        showMessageWarning(article, message) {
            const extra = article.querySelector('.bpa-msg-extra');
            const warn = document.createElement('p');
            warn.className = 'bpa-warn';
            warn.textContent = message;
            extra.appendChild(warn);
        }

        // ------------------------------------------------------------------ audio

        stopPlayer() {
            if (this.player) {
                this.player.pause();
                this.player = null;
            }
            if (window.speechSynthesis) {
                window.speechSynthesis.cancel();
            }
        }

        attachPlayer(article, source, autoplay) {
            const data = this.msgData.get(article) || {};
            const extra = article.querySelector('.bpa-msg-extra');
            const built = this.buildPlayer(source);
            extra.appendChild(built.element);
            data.player = built.controller;
            this.msgData.set(article, data);
            if (autoplay) {
                built.controller.play();
            }
        }

        buildPlayer(source) {
            const wrap = document.createElement('div');
            wrap.className = 'bpa-player';
            const rand = seeded(source.url || source.text || 'x');
            let bars = '';
            for (let i = 0; i < WAVE_BARS; i++) {
                const height = 22 + Math.round(rand() * 78);
                bars += '<i style="height:' + height + '%"></i>';
            }
            wrap.innerHTML = '<button type="button" class="bpa-play" aria-label="' + esc(this.t('listen')) + '">' + Icons.play +
                '</button><div class="bpa-wave" dir="ltr" role="slider" tabindex="0" aria-label="' + esc(this.t('listen')) +
                '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">' + bars + '</div><span class="bpa-time">' +
                (source.text ? esc(this.t('browserVoice')) : '0:00') + '</span>';
            const playBtn = wrap.querySelector('.bpa-play');
            const wave = wrap.querySelector('.bpa-wave');
            const time = wrap.querySelector('.bpa-time');
            const barNodes = Array.prototype.slice.call(wave.children);
            const self = this;
            const controller = {playing: false};

            const paint = function(ratio) {
                const on = Math.round(ratio * barNodes.length);
                barNodes.forEach((node, i) => node.classList.toggle('on', i < on));
                wave.setAttribute('aria-valuenow', String(Math.round(ratio * 100)));
            };
            const setPlaying = function(playing) {
                controller.playing = playing;
                wrap.classList.toggle('is-playing', playing);
                playBtn.innerHTML = playing ? Icons.pause : Icons.play;
            };

            if (source.url) {
                const audio = new Audio();
                audio.preload = 'metadata';
                audio.src = source.url;
                audio.addEventListener('loadedmetadata', () => {
                    time.textContent = formatTime(audio.duration);
                });
                audio.addEventListener('timeupdate', () => {
                    const ratio = audio.duration ? audio.currentTime / audio.duration : 0;
                    paint(ratio);
                    time.textContent = formatTime(audio.currentTime) + ' / ' + formatTime(audio.duration);
                });
                audio.addEventListener('ended', () => {
                    setPlaying(false);
                    paint(0);
                    time.textContent = formatTime(audio.duration);
                });
                audio.addEventListener('error', () => {
                    setPlaying(false);
                    wrap.classList.add('is-error');
                    time.textContent = self.t('audioFailed');
                });
                controller.play = function() {
                    if (self.player && self.player !== controller) {
                        self.player.pause();
                    }
                    self.player = controller;
                    const promise = audio.play();
                    setPlaying(true);
                    if (promise && promise.catch) {
                        promise.catch(() => setPlaying(false));
                    }
                };
                controller.pause = function() {
                    audio.pause();
                    setPlaying(false);
                };
                controller.seek = function(ratio) {
                    if (audio.duration) {
                        audio.currentTime = ratio * audio.duration;
                    }
                };
            } else {
                const synth = window.speechSynthesis;
                controller.play = function() {
                    if (!synth || !window.SpeechSynthesisUtterance) {
                        wrap.classList.add('is-error');
                        time.textContent = self.t('audioFailed');
                        return;
                    }
                    if (self.player && self.player !== controller) {
                        self.player.pause();
                    }
                    self.player = controller;
                    synth.cancel();
                    const plain = source.text.replace(/[#*_`>|~-]+/g, ' ').replace(/\s+/g, ' ').trim();
                    const utterance = new window.SpeechSynthesisUtterance(plain);
                    utterance.lang = detectSpeechLang(plain, self.cfg.lang);
                    const voice = pickVoice(utterance.lang, self.cfg.gender);
                    if (voice) {
                        utterance.voice = voice;
                    }
                    utterance.onboundary = (event) => paint(event.charIndex / Math.max(1, plain.length));
                    utterance.onend = () => {
                        setPlaying(false);
                        paint(0);
                    };
                    utterance.onerror = () => setPlaying(false);
                    synth.speak(utterance);
                    setPlaying(true);
                };
                controller.pause = function() {
                    if (synth) {
                        synth.cancel();
                    }
                    setPlaying(false);
                };
                controller.seek = function() {
                    return null;
                };
            }
            controller.toggle = function() {
                if (controller.playing) {
                    controller.pause();
                } else {
                    controller.play();
                }
            };
            playBtn.addEventListener('click', controller.toggle);
            wave.addEventListener('click', (e) => {
                const rect = wave.getBoundingClientRect();
                controller.seek((e.clientX - rect.left) / rect.width);
            });
            wave.addEventListener('keydown', (e) => {
                if (e.key === ' ' || e.key === 'Enter') {
                    e.preventDefault();
                    controller.toggle();
                }
            });
            return {element: wrap, controller: controller};
        }

        // ------------------------------------------------------------------ dictation

        setupMic() {
            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!Recognition) {
                return;
            }
            const map = {ar: 'ar-DZ', fr: 'fr-FR', en: 'en-US'};
            this.recognition = new Recognition();
            this.recognition.lang = map[this.cfg.lang] || 'en-US';
            this.recognition.interimResults = true;
            this.recognition.continuous = false;
            this.region('mic').hidden = false;
            let base = '';
            this.recognition.onresult = (event) => {
                let transcript = '';
                for (let i = 0; i < event.results.length; i++) {
                    transcript += event.results[i][0].transcript;
                }
                this.input.value = (base ? base + ' ' : '') + transcript;
                this.autosize();
            };
            const finish = () => this.setMic(false);
            this.recognition.onend = finish;
            this.recognition.onerror = finish;
            this.micStart = function() {
                base = this.input.value.trim();
                this.recognition.start();
                this.setMic(true);
            };
        }

        toggleMic() {
            if (!this.recognition) {
                return;
            }
            if (this.region('mic').classList.contains('is-listening')) {
                this.recognition.stop();
            } else {
                try {
                    this.micStart();
                } catch (e) {
                    this.setMic(false);
                }
            }
        }

        setMic(on) {
            const button = this.region('mic');
            button.classList.toggle('is-listening', on);
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            const label = this.t(on ? 'micStop' : 'mic');
            button.title = label;
            button.setAttribute('aria-label', label);
        }

        // ------------------------------------------------------------------ layout

        autosize() {
            this.input.style.height = 'auto';
            this.input.style.height = Math.min(this.input.scrollHeight, 140) + 'px';
        }

        toggleExpand(force) {
            const root = this.root;
            const on = typeof force === 'boolean' ? force : !root.classList.contains('is-expanded');
            if (on === root.classList.contains('is-expanded')) {
                return;
            }
            if (on) {
                // Move to <body> so position: fixed is not trapped by transformed ancestors (Moodle drawers).
                this.placeholderNode = document.createComment('bpa');
                root.parentNode.insertBefore(this.placeholderNode, root);
                document.body.appendChild(root);
                document.addEventListener('keydown', this.onKeydown);
            } else if (this.placeholderNode && this.placeholderNode.parentNode) {
                this.placeholderNode.parentNode.insertBefore(root, this.placeholderNode);
                this.placeholderNode.remove();
                this.placeholderNode = null;
                document.removeEventListener('keydown', this.onKeydown);
            }
            root.classList.toggle('is-expanded', on);
            document.body.classList.toggle('bpa-noscroll', on);
            const button = root.querySelector('[data-action="expand"]');
            const label = this.t(on ? 'collapse' : 'expand');
            button.title = label;
            button.setAttribute('aria-label', label);
            button.setAttribute('aria-expanded', on ? 'true' : 'false');
            this.region('expandicon', button).innerHTML = on ? Icons.collapse : Icons.expand;
            if (on) {
                root.setAttribute('role', 'dialog');
                root.setAttribute('aria-modal', 'true');
                this.input.focus();
            } else {
                root.removeAttribute('role');
                root.removeAttribute('aria-modal');
                button.focus();
            }
            this.autosize();
            this.scrollToEnd();
            this.refreshTools();
        }
    }

    return {
        /**
         * Start the chat on a rendered block.
         *
         * @param {Object} cfg Configuration prepared by the block class.
         */
        init: function(cfg) {
            const root = document.getElementById(cfg.uniqid);
            if (!root || root.dataset.bound) {
                return;
            }
            root.dataset.bound = '1';
            new Chat(root, cfg);
        },
        Chat: Chat
    };
});
