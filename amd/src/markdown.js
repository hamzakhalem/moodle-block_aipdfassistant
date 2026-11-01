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
 * Small, safe Markdown renderer for assistant answers. All input is HTML-escaped first and
 * only a fixed set of tags is produced, so model output can never inject markup or scripts.
 *
 * @module     block_aipdfassistant/markdown
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    const escapeHtml = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const safeUrl = (url) => /^(https?:\/\/|mailto:)/i.test(url) ? url : '';

    /**
     * Inline formatting on text that is NOT yet escaped.
     *
     * @param {String} raw Raw text.
     * @return {String} Safe HTML.
     */
    const inline = function(raw) {
        const codes = [];
        let text = String(raw).replace(/`([^`\n]+)`/g, function(m, code) {
            codes.push('<code>' + escapeHtml(code) + '</code>');
            return '\u0000' + (codes.length - 1) + '\u0000';
        });
        text = escapeHtml(text);
        text = text.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function(m, label, url) {
            const href = safeUrl(url.replace(/&amp;/g, '&'));
            return href ? '<a href="' + escapeHtml(href) + '" target="_blank" rel="noopener noreferrer">' + label + '</a>' : label;
        });
        text = text.replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>')
            .replace(/__([^_\n]+?)__/g, '<strong>$1</strong>')
            .replace(/(^|[^*\w])\*([^*\n]+?)\*(?!\*)/g, '$1<em>$2</em>')
            .replace(/~~([^~\n]+?)~~/g, '<del>$1</del>');
        return text.replace(/\u0000(\d+)\u0000/g, function(m, i) {
            return codes[Number(i)];
        });
    };

    const LIST_RE = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/;

    const renderList = function(items) {
        let html = '';
        const stack = [];
        items.forEach(function(item) {
            const tag = item.ordered ? 'ol' : 'ul';
            while (stack.length && item.indent < stack[stack.length - 1].indent) {
                html += '</li></' + stack.pop().tag + '>';
            }
            if (!stack.length || item.indent > stack[stack.length - 1].indent) {
                const start = item.ordered && item.number !== 1 ? ' start="' + item.number + '"' : '';
                html += '<' + tag + start + ' dir="auto"><li>';
                stack.push({indent: item.indent, tag: tag});
            } else {
                html += '</li><li>';
            }
            html += inline(item.text);
        });
        while (stack.length) {
            html += '</li></' + stack.pop().tag + '>';
        }
        return html;
    };

    const splitRow = function(line) {
        let row = line.trim();
        if (row.charAt(0) === '|') {
            row = row.slice(1);
        }
        if (row.charAt(row.length - 1) === '|') {
            row = row.slice(0, -1);
        }
        return row.split('|').map((cell) => cell.trim());
    };

    const isTableSeparator = (line) => /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(line) && line.indexOf('-') !== -1;

    const renderTable = function(header, separator, rows) {
        const aligns = splitRow(separator).map(function(cell) {
            const left = cell.charAt(0) === ':';
            const right = cell.charAt(cell.length - 1) === ':';
            return left && right ? 'center' : (right ? 'right' : (left ? 'left' : ''));
        });
        const cell = function(tag, text, i) {
            const style = aligns[i] ? ' style="text-align:' + aligns[i] + '"' : '';
            return '<' + tag + style + '>' + inline(text) + '</' + tag + '>';
        };
        let html = '<div class="bpa-tablewrap"><table dir="auto"><thead><tr>';
        splitRow(header).forEach((text, i) => {
            html += cell('th', text, i);
        });
        html += '</tr></thead><tbody>';
        rows.forEach(function(line) {
            html += '<tr>';
            splitRow(line).forEach((text, i) => {
                html += cell('td', text, i);
            });
            html += '</tr>';
        });
        return html + '</tbody></table></div>';
    };

    /**
     * Render Markdown to safe HTML.
     *
     * @param {String} markdown Markdown source.
     * @return {String} HTML.
     */
    const render = function(markdown) {
        const lines = String(markdown || '').replace(/\r\n?/g, '\n').split('\n');
        const out = [];
        let i = 0;
        const isBlockStart = (line) => /^\s{0,3}#{1,6}\s/.test(line) || /^\s*```/.test(line) || /^\s*>/.test(line) ||
            LIST_RE.test(line) || /^\s*([-*_])(\s*\1){2,}\s*$/.test(line);

        while (i < lines.length) {
            const line = lines[i];
            if (/^\s*$/.test(line)) {
                i++;
                continue;
            }
            // Fenced code block.
            if (/^\s*```/.test(line)) {
                const code = [];
                i++;
                while (i < lines.length && !/^\s*```/.test(lines[i])) {
                    code.push(lines[i]);
                    i++;
                }
                i++;
                out.push('<pre><code>' + escapeHtml(code.join('\n')) + '</code></pre>');
                continue;
            }
            // Heading.
            let m = /^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/.exec(line);
            if (m) {
                const level = Math.min(6, m[1].length + 2);
                out.push('<h' + level + ' dir="auto">' + inline(m[2]) + '</h' + level + '>');
                i++;
                continue;
            }
            // Horizontal rule.
            if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
                out.push('<hr>');
                i++;
                continue;
            }
            // Table.
            if (line.indexOf('|') !== -1 && i + 1 < lines.length && isTableSeparator(lines[i + 1])) {
                const header = line;
                const separator = lines[i + 1];
                const rows = [];
                i += 2;
                while (i < lines.length && lines[i].indexOf('|') !== -1 && !/^\s*$/.test(lines[i])) {
                    rows.push(lines[i]);
                    i++;
                }
                out.push(renderTable(header, separator, rows));
                continue;
            }
            // Blockquote.
            if (/^\s*>/.test(line)) {
                const quote = [];
                while (i < lines.length && /^\s*>/.test(lines[i])) {
                    quote.push(lines[i].replace(/^\s*>\s?/, ''));
                    i++;
                }
                out.push('<blockquote dir="auto">' + render(quote.join('\n')) + '</blockquote>');
                continue;
            }
            // List.
            m = LIST_RE.exec(line);
            if (m) {
                const items = [];
                while (i < lines.length) {
                    const li = LIST_RE.exec(lines[i]);
                    if (li) {
                        items.push({
                            indent: li[1].replace(/\t/g, '    ').length,
                            ordered: /\d/.test(li[2]),
                            number: parseInt(li[2], 10),
                            text: li[3]
                        });
                        i++;
                    } else if (/^\s+\S/.test(lines[i]) && items.length) {
                        items[items.length - 1].text += ' ' + lines[i].trim();
                        i++;
                    } else {
                        break;
                    }
                }
                out.push(renderList(items));
                continue;
            }
            // Paragraph.
            const para = [line];
            i++;
            while (i < lines.length && !/^\s*$/.test(lines[i]) && !isBlockStart(lines[i])) {
                para.push(lines[i]);
                i++;
            }
            out.push('<p dir="auto">' + para.map((l) => inline(l.trim())).join('<br>') + '</p>');
        }
        return out.join('');
    };

    return {render: render, escapeHtml: escapeHtml};
});
