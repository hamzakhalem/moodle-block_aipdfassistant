// Node test for block_aipdfassistant/markdown (no browser needed):  node tests/js/markdown.test.js
'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert');

let mod;
global.define = function(deps, factory) {
    mod = factory();
};
eval(fs.readFileSync(path.join(__dirname, '../../amd/src/markdown.js'), 'utf8'));
const md = mod.render;

let passed = 0;
const test = (name, fn) => {
    try {
        fn();
        passed++;
        console.log('ok   ' + name);
    } catch (e) {
        console.log('FAIL ' + name + '\n     ' + e.message);
        process.exitCode = 1;
    }
};

test('escapes raw HTML and script tags', () => {
    const html = md('<script>alert(1)</script> <img src=x onerror=alert(1)>');
    assert(!/<script/i.test(html) && !/<img/i.test(html), html);
    assert(html.indexOf('&lt;script&gt;') !== -1);
});
test('bold, italic, code', () => {
    assert.strictEqual(md('**a** and *b* and `c<d`'),
        '<p dir="auto"><strong>a</strong> and <em>b</em> and <code>c&lt;d</code></p>');
});
test('code spans are not formatted', () => {
    assert.strictEqual(md('`**x**`'), '<p dir="auto"><code>**x**</code></p>');
});
test('headings are shifted below h2', () => {
    assert.strictEqual(md('# Title'), '<h3 dir="auto">Title</h3>');
    assert.strictEqual(md('#### Deep'), '<h6 dir="auto">Deep</h6>');
});
test('unordered list', () => {
    assert.strictEqual(md('- one\n- two'), '<ul dir="auto"><li>one</li><li>two</li></ul>');
});
test('ordered list keeps start number', () => {
    assert.strictEqual(md('3. c\n4. d'), '<ol start="3" dir="auto"><li>c</li><li>d</li></ol>');
});
test('nested list', () => {
    const html = md('- a\n  - b\n- c');
    assert.strictEqual(html, '<ul dir="auto"><li>a<ul dir="auto"><li>b</li></ul></li><li>c</li></ul>');
});
test('links allow only http, https, mailto', () => {
    assert(md('[x](https://moodle.org)').indexOf('href="https://moodle.org"') !== -1);
    assert(md('[x](https://moodle.org)').indexOf('rel="noopener noreferrer"') !== -1);
    const bad = md('[x](javascript:alert(1))');
    assert(bad.indexOf('<a') === -1 && bad.indexOf('href') === -1, bad);
});
test('fenced code block escapes content', () => {
    assert.strictEqual(md('```js\n<b>x</b>\n```'), '<pre><code>&lt;b&gt;x&lt;/b&gt;</code></pre>');
});
test('table with alignment', () => {
    const html = md('| A | B |\n|:--|--:|\n| 1 | 2 |');
    assert(html.indexOf('<table') !== -1 && html.indexOf('text-align:left') !== -1 && html.indexOf('text-align:right') !== -1, html);
    assert(html.indexOf('<td style="text-align:left">1</td>') !== -1, html);
});
test('blockquote and rule', () => {
    assert.strictEqual(md('> quote'), '<blockquote dir="auto"><p dir="auto">quote</p></blockquote>');
    assert.strictEqual(md('---'), '<hr>');
});
test('Arabic text is kept intact and marked dir=auto', () => {
    assert.strictEqual(md('**مرحبا** بالعالم'), '<p dir="auto"><strong>مرحبا</strong> بالعالم</p>');
});
test('single newlines inside a paragraph become <br>', () => {
    assert.strictEqual(md('a\nb'), '<p dir="auto">a<br>b</p>');
});
test('snake_case words are not italicised', () => {
    assert.strictEqual(md('use my_var_name here'), '<p dir="auto">use my_var_name here</p>');
});
test('empty input', () => {
    assert.strictEqual(md(''), '');
    assert.strictEqual(md(null), '');
});
console.log('\n' + passed + ' tests passed' + (process.exitCode ? ' (with failures)' : ''));
