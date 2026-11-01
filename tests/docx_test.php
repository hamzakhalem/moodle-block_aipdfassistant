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


namespace block_aipdfassistant;

use block_aipdfassistant\local\document_reader;
use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\office\docx_reader;
use block_aipdfassistant\local\pdf\sample;

/**
 * Tests for Word (.docx) reading and file type detection.
 *
 * @package    block_aipdfassistant
 * @category   test
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_aipdfassistant\local\office\docx_reader
 * @covers     \block_aipdfassistant\local\document_reader
 */
final class docx_test extends \basic_testcase {
    /**
     * Paragraph XML.
     *
     * @param string $text Text.
     * @param string|null $style Paragraph style id.
     * @return string
     */
    private function p(string $text, ?string $style = null): string {
        $ppr = $style ? '<w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr>' : '';
        return '<w:p>' . $ppr . '<w:r><w:t xml:space="preserve">' . $text . '</w:t></w:r></w:p>';
    }

    /**
     * Write bytes to a temporary file.
     *
     * @param string $bytes Content.
     * @return string Path.
     */
    private function file(string $bytes): string {
        $path = tempnam(sys_get_temp_dir(), 'bpa');
        file_put_contents($path, $bytes);
        return $path;
    }

    /**
     * Read the text of a document body.
     *
     * @param string $body Body XML.
     * @param int $max Maximum characters.
     * @return \block_aipdfassistant\local\pdf\extraction
     */
    private function read(string $body, int $max = 0) {
        $path = $this->file(sample::docx_from_body($body));
        try {
            return (new docx_reader())->read($path, $max);
        } finally {
            unlink($path);
        }
    }

    /**
     * Error code raised when reading bytes through the facade.
     *
     * @param string $bytes File content.
     * @return string
     */
    private function code_of(string $bytes): string {
        $path = $this->file($bytes);
        try {
            (new document_reader(''))->read($path);
        } catch (exception $e) {
            return $e->errorcode;
        } finally {
            unlink($path);
        }
        return 'none';
    }

    /**
     * Headings, plain paragraphs and list items.
     */
    public function test_headings_paragraphs_lists(): void {
        $result = $this->read($this->p('Titre', 'Heading1') . $this->p('Un paragraphe.') . $this->p('un', 'ListBullet')
            . $this->p('deux', 'ListBullet'));
        $this->assertSame("# Titre\n\nUn paragraphe.\n\n- un\n- deux", $result->text);
        $this->assertSame('docx', $result->extractor);
        $this->assertSame(1, $result->pages);
        $this->assertFalse($result->lowtext, 'a short Word file is not "scanned"');
    }

    /**
     * Paragraphs with numbering properties are list items too.
     */
    public function test_numbered_paragraph(): void {
        $item = '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>point</w:t></w:r></w:p>';
        $this->assertSame('- point', $this->read($item)->text);
    }

    /**
     * Tables become pipe separated rows.
     */
    public function test_table(): void {
        $table = '<w:tbl><w:tr><w:tc>' . $this->p('A') . '</w:tc><w:tc>' . $this->p('B') . '</w:tc></w:tr><w:tr><w:tc>'
            . $this->p('1') . '</w:tc><w:tc>' . $this->p('2') . '</w:tc></w:tr></w:tbl>';
        $this->assertSame("A | B\n1 | 2", $this->read($table)->text);
    }

    /**
     * Tracked deletions are skipped; insertions, hyperlinks and tabs are kept.
     */
    public function test_tracked_changes_hyperlinks_tabs(): void {
        $body = '<w:p><w:r><w:t>Garder </w:t></w:r><w:del><w:r><w:delText>SUPPRIME</w:delText></w:r></w:del>'
            . '<w:ins><w:r><w:t>ajouté</w:t></w:r></w:ins><w:r><w:tab/><w:t>fin</w:t></w:r>'
            . '<w:hyperlink><w:r><w:t> lien</w:t></w:r></w:hyperlink></w:p>';
        $this->assertSame('Garder ajouté fin lien', $this->read($body)->text);
    }

    /**
     * Arabic text and Arabic-friendly heading detection.
     */
    public function test_arabic(): void {
        $result = $this->read($this->p('مرحبا بالعالم', 'Heading2') . $this->p('هذا اختبار'));
        $this->assertSame("## مرحبا بالعالم\n\nهذا اختبار", $result->text);
    }

    /**
     * A text box exists twice in the file (Choice and Fallback) but is read once.
     */
    public function test_text_box_is_read_once(): void {
        $body = '<w:p><w:r><mc:AlternateContent><mc:Choice><w:drawing><w:txbxContent>' . $this->p('Boxed')
            . '</w:txbxContent></w:drawing></mc:Choice><mc:Fallback><w:pict><w:txbxContent>' . $this->p('Boxed')
            . '</w:txbxContent></w:pict></mc:Fallback></mc:AlternateContent></w:r></w:p>';
        $this->assertSame('Boxed', $this->read($body)->text);
    }

    /**
     * Line breaks inside a run.
     */
    public function test_line_break(): void {
        $this->assertSame("one\ntwo", $this->read('<w:p><w:r><w:t>one</w:t><w:br/><w:t>two</w:t></w:r></w:p>')->text);
    }

    /**
     * The maximum length is applied.
     */
    public function test_max_chars(): void {
        $this->assertSame('abcd', $this->read($this->p('abcdefghij'), 4)->text);
    }

    /**
     * A document without text, and a document with a DTD, are refused.
     */
    public function test_empty_and_dtd(): void {
        $this->assertSame('noextractabletext', $this->code_of(sample::docx(['', ' '])));
        $this->assertSame('unreadable', $this->code_of(sample::docx_from_body($this->p('x'),
            '<!DOCTYPE foo [<!ENTITY xxe "boom">]>')));
    }

    /**
     * Type detection looks at the content and not at the name.
     */
    public function test_detect(): void {
        $docx = $this->file(sample::docx(['Hello']));
        $pdf = $this->file(sample::minimal(['Hello']));
        $text = $this->file('just text');
        $legacy = $this->file("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 100));
        try {
            $this->assertSame('docx', document_reader::detect($docx));
            $this->assertSame('pdf', document_reader::detect($pdf));
            $this->assertSame('', document_reader::detect($text));
            $this->assertSame('', document_reader::detect($legacy));
            $this->assertSame('', document_reader::detect($docx . '.missing'));
        } finally {
            array_map('unlink', [$docx, $pdf, $text, $legacy]);
        }
    }

    /**
     * The facade reads both formats and refuses everything else.
     */
    public function test_facade(): void {
        $docx = $this->file(sample::docx(['Bonjour le monde']));
        $pdf = $this->file(sample::minimal(['Hello PDF facade test text']));
        try {
            $this->assertStringContainsString('Bonjour le monde', (new document_reader(''))->read($docx)->text);
            $this->assertStringContainsString('Hello PDF', (new document_reader(''))->read($pdf)->text);
        } finally {
            array_map('unlink', [$docx, $pdf]);
        }
        $this->assertSame('unsupportedtype', $this->code_of('plain text'));
        $this->assertSame('unsupportedtype', $this->code_of("\xD0\xCF\x11\xE0" . str_repeat("\0", 64)));
        $zip = tempnam(sys_get_temp_dir(), 'bpa');
        $archive = new \ZipArchive();
        $archive->open($zip, \ZipArchive::OVERWRITE);
        $archive->addFromString('hello.txt', 'not a word file');
        $archive->close();
        $this->assertSame('unsupportedtype', $this->code_of((string) file_get_contents($zip)));
        unlink($zip);
    }
}
