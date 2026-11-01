<?php
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


namespace block_aipdfassistant\local\office;

use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\pdf\extraction;
use block_aipdfassistant\local\pdf\pdf_reader;

/**
 * Reads the text of Word (.docx) files with PHP's ZipArchive and DOM, no extra library.
 *
 * Headings become Markdown headings, list paragraphs become "- " items, tables become
 * "a | b" rows, text boxes are included once, tracked deletions are skipped.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class docx_reader {
    /** WordprocessingML namespace. */
    const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Markup compatibility namespace. */
    const MC = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    /** Largest document.xml accepted after decompression (protects against zip bombs). */
    const MAX_XML = 31457280;

    /**
     * Extract the text of a .docx file.
     *
     * @param string $path Absolute path.
     * @param int $maxchars Maximum characters kept (0 = unlimited).
     * @return extraction
     * @throws exception unreadable, unsupportedtype, toolarge.
     */
    public function read(string $path, int $maxchars = 0): extraction {
        if (!is_readable($path)) {
            throw new exception('unreadable');
        }
        if (!class_exists('\ZipArchive')) {
            throw new exception('unreadable', 'The PHP zip extension is missing.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new exception('unsupportedtype');
        }
        try {
            $stat = $zip->statName('word/document.xml');
            if ($stat === false) {
                throw new exception('unsupportedtype');
            }
            if ($stat['size'] > self::MAX_XML) {
                throw new exception('toolarge');
            }
            $xml = $zip->getFromName('word/document.xml');
            $app = $zip->getFromName('docProps/app.xml');
        } finally {
            $zip->close();
        }
        if (!is_string($xml) || $xml === '') {
            throw new exception('unreadable');
        }
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $xml)) {
            throw new exception('unreadable', 'DTD and entities are not allowed.');
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $bodies = $loaded ? $dom->getElementsByTagNameNS(self::W, 'body') : null;
        if (!$bodies || !$bodies->length) {
            throw new exception('unreadable');
        }

        $lines = [];
        $this->walk_blocks($bodies->item(0), $lines);
        $text = pdf_reader::clean($this->join_lines($lines));
        if ($maxchars > 0 && mb_strlen($text, 'UTF-8') > $maxchars) {
            $text = mb_substr($text, 0, $maxchars, 'UTF-8');
        }
        $pages = 0;
        if (is_string($app) && preg_match('~<Pages>(\d+)</Pages>~', $app, $m)) {
            $pages = (int) $m[1];
        }
        if ($pages < 1) {
            $pages = max(1, (int) ceil(mb_strlen($text, 'UTF-8') / 2500));
        }
        if (trim($text) === '') {
            throw new exception('noextractabletext');
        }
        return new extraction($text, $pages, 'docx', false);
    }

    /**
     * Element children of a node.
     *
     * @param \DOMNode $node Node.
     * @return \DOMElement[]
     */
    private function elements(\DOMNode $node): array {
        $result = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $result[] = $child;
            }
        }
        return $result;
    }

    /**
     * Whether a node is a WordprocessingML element with this local name.
     *
     * @param \DOMNode $node Node.
     * @param string $name Local name.
     * @return bool
     */
    private function is_w(\DOMNode $node, string $name): bool {
        return $node->namespaceURI === self::W && $node->localName === $name;
    }

    /**
     * Whether a node is a markup-compatibility element with this local name.
     *
     * @param \DOMNode $node Node.
     * @param string $name Local name.
     * @return bool
     */
    private function is_mc(\DOMNode $node, string $name): bool {
        return $node->namespaceURI === self::MC && $node->localName === $name;
    }

    /**
     * Value of a w: attribute.
     *
     * @param \DOMElement $node Element.
     * @param string $name Attribute name without prefix.
     * @return string
     */
    private function attr(\DOMElement $node, string $name): string {
        $value = $node->getAttributeNS(self::W, $name);
        return $value !== '' ? $value : $node->getAttribute('w:' . $name);
    }

    /**
     * The mc:Choice branch of an mc:AlternateContent element (the Fallback duplicates it).
     *
     * @param \DOMElement $alternate Element.
     * @return \DOMElement|null
     */
    private function choice_of(\DOMElement $alternate): ?\DOMElement {
        foreach ($this->elements($alternate) as $child) {
            if ($this->is_mc($child, 'Choice')) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Descendant w: elements with a name, skipping mc:Fallback branches.
     *
     * @param \DOMNode $node Start node.
     * @param string $name Local name.
     * @return \DOMElement[]
     */
    private function descendants(\DOMNode $node, string $name): array {
        $result = [];
        foreach ($this->elements($node) as $child) {
            if ($this->is_w($child, $name)) {
                $result[] = $child;
            } else if (!$this->is_mc($child, 'Fallback')) {
                $result = array_merge($result, $this->descendants($child, $name));
            }
        }
        return $result;
    }

    /**
     * Text of one run (w:r).
     *
     * @param \DOMElement $run Run.
     * @param array $extra Collects text box lines.
     * @return string
     */
    private function run_text(\DOMElement $run, array &$extra): string {
        $out = '';
        foreach ($this->elements($run) as $child) {
            if ($this->is_w($child, 't')) {
                $out .= $child->textContent;
            } else if ($this->is_w($child, 'tab')) {
                $out .= "\t";
            } else if ($this->is_w($child, 'br') || $this->is_w($child, 'cr')) {
                $out .= $this->attr($child, 'type') === 'page' ? "\n\n" : "\n";
            } else if ($this->is_w($child, 'noBreakHyphen')) {
                $out .= '-';
            } else if ($this->is_mc($child, 'AlternateContent')) {
                $choice = $this->choice_of($child);
                if ($choice !== null) {
                    $this->collect_textboxes($choice, $extra);
                }
            } else if ($this->is_w($child, 'pict') || $this->is_w($child, 'object')) {
                $this->collect_textboxes($child, $extra);
            }
        }
        return $out;
    }

    /**
     * Gather the paragraphs of text boxes below a node.
     *
     * @param \DOMNode $node Node.
     * @param array $extra Collected lines.
     * @return void
     */
    private function collect_textboxes(\DOMNode $node, array &$extra): void {
        foreach ($this->descendants($node, 'txbxContent') as $box) {
            $lines = [];
            $this->walk_blocks($box, $lines);
            foreach ($lines as $line) {
                if (trim($line[1]) !== '') {
                    $extra[] = $line[1];
                }
            }
        }
    }

    /**
     * Inline text of a paragraph or of a wrapper such as a hyperlink.
     *
     * @param \DOMNode $node Node.
     * @param array $extra Collects text box lines.
     * @return string
     */
    private function inline(\DOMNode $node, array &$extra): string {
        $out = '';
        foreach ($this->elements($node) as $child) {
            if ($this->is_w($child, 'r')) {
                $out .= $this->run_text($child, $extra);
            } else if ($this->is_w($child, 'del') || $this->is_w($child, 'moveFrom') || $this->is_w($child, 'pPr')
                    || $this->is_w($child, 'rPr')) {
                continue;
            } else if ($this->is_w($child, 'sdt')) {
                foreach ($this->elements($child) as $content) {
                    if ($this->is_w($content, 'sdtContent')) {
                        $out .= $this->inline($content, $extra);
                    }
                }
            } else if ($this->is_mc($child, 'AlternateContent')) {
                $choice = $this->choice_of($child);
                if ($choice !== null) {
                    $out .= $this->inline($choice, $extra);
                }
            } else if ($child->namespaceURI === self::W) {
                $out .= $this->inline($child, $extra);
            }
        }
        return $out;
    }

    /**
     * Add one paragraph to the output lines.
     *
     * @param \DOMElement $paragraph Paragraph.
     * @param array $lines Output: list of [kind, text].
     * @return void
     */
    private function paragraph(\DOMElement $paragraph, array &$lines): void {
        $style = '';
        $numbered = false;
        foreach ($this->elements($paragraph) as $child) {
            if ($this->is_w($child, 'pPr')) {
                foreach ($this->elements($child) as $property) {
                    if ($this->is_w($property, 'pStyle')) {
                        $style = $this->attr($property, 'val');
                    } else if ($this->is_w($property, 'numPr')) {
                        $numbered = true;
                    }
                }
            }
        }
        $extra = [];
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $this->inline($paragraph, $extra));
        $parts = array_map('trim', explode("\n", $text));
        $text = trim(implode("\n", $parts));
        $kind = 'p';
        if (preg_match('/^(?:heading|titre|عنوان)\s*(\d)$/iu', $style, $m)) {
            $kind = 'h';
            $text = $text === '' ? '' : str_repeat('#', min(6, (int) $m[1])) . ' ' . $text;
        } else if (strtolower($style) === 'title') {
            $kind = 'h';
            $text = $text === '' ? '' : '# ' . $text;
        } else if ($numbered || preg_match('/^list/i', $style)) {
            $kind = 'li';
            $text = $text === '' ? '' : '- ' . $text;
        }
        if ($text !== '') {
            $lines[] = [$kind, $text];
        }
        foreach ($extra as $boxed) {
            $lines[] = ['p', trim((string) preg_replace('/\s+/u', ' ', $boxed))];
        }
    }

    /**
     * Add a table as "a | b" rows.
     *
     * @param \DOMElement $table Table.
     * @param array $lines Output lines.
     * @return void
     */
    private function table(\DOMElement $table, array &$lines): void {
        $rows = [];
        foreach ($this->elements($table) as $row) {
            if (!$this->is_w($row, 'tr')) {
                continue;
            }
            $cells = [];
            foreach ($this->elements($row) as $cell) {
                if (!$this->is_w($cell, 'tc')) {
                    continue;
                }
                $sub = [];
                $this->walk_blocks($cell, $sub);
                $texts = array_map(function ($item) {
                    return $item[1];
                }, $sub);
                $cells[] = trim(str_replace("\n", ' ', implode(' ', $texts)));
            }
            if (array_filter($cells, function ($cell) {
                return $cell !== '';
            })) {
                $rows[] = implode(' | ', $cells);
            }
        }
        if ($rows) {
            $lines[] = ['table', implode("\n", $rows)];
        }
    }

    /**
     * Walk the block level children of a container (body, cell, text box).
     *
     * @param \DOMNode $node Container.
     * @param array $lines Output lines.
     * @return void
     */
    private function walk_blocks(\DOMNode $node, array &$lines): void {
        foreach ($this->elements($node) as $child) {
            if ($this->is_w($child, 'p')) {
                $this->paragraph($child, $lines);
            } else if ($this->is_w($child, 'tbl')) {
                $this->table($child, $lines);
            } else if ($this->is_w($child, 'sdt')) {
                foreach ($this->elements($child) as $content) {
                    if ($this->is_w($content, 'sdtContent')) {
                        $this->walk_blocks($content, $lines);
                    }
                }
            } else if ($this->is_mc($child, 'AlternateContent')) {
                $choice = $this->choice_of($child);
                if ($choice !== null) {
                    $this->walk_blocks($choice, $lines);
                }
            } else if ($this->is_w($child, 'del') || $this->is_w($child, 'moveFrom')) {
                continue;
            } else if ($child->namespaceURI === self::W
                    && in_array($child->localName, ['ins', 'moveTo', 'customXml', 'smartTag'], true)) {
                $this->walk_blocks($child, $lines);
            }
        }
    }

    /**
     * Join blocks: list items stay together, everything else is separated by a blank line.
     *
     * @param array $lines List of [kind, text].
     * @return string
     */
    private function join_lines(array $lines): string {
        $out = '';
        $previous = null;
        foreach ($lines as $line) {
            [$kind, $text] = $line;
            if ($previous === null) {
                $out = $text;
            } else if ($kind === 'li' && $previous === 'li') {
                $out .= "\n" . $text;
            } else {
                $out .= "\n\n" . $text;
            }
            $previous = $kind;
        }
        return trim($out);
    }
}
