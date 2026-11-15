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

use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\pdf\cmap;
use block_aipdfassistant\local\pdf\native_parser;
use block_aipdfassistant\local\pdf\pdf_reader;
use block_aipdfassistant\local\pdf\sample;

/**
 * Tests for PDF reading (the built-in PHP parser and the reader facade).
 *
 * @package    block_aipdfassistant
 * @category   test
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_aipdfassistant\local\pdf\native_parser
 * @covers     \block_aipdfassistant\local\pdf\cmap
 * @covers     \block_aipdfassistant\local\pdf\pdf_reader
 * @covers     \block_aipdfassistant\local\pdf\sample
 */
final class pdf_test extends \basic_testcase {
    /**
     * Assemble a PDF from object bodies keyed by id.
     *
     * @param array $objects id => body.
     * @return string
     */
    private function assemble(array $objects): string {
        $pdf = "%PDF-1.4\n";
        foreach ($objects as $id => $body) {
            $pdf .= "$id 0 obj\n$body\nendobj\n";
        }
        return $pdf . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    /**
     * A plain one page document.
     */
    public function test_native_reads_simple_pdf(): void {
        [$text, $pages] = (new native_parser())->extract(sample::minimal(['Hello PDF']));
        $this->assertSame(1, $pages);
        $this->assertStringContainsString('Hello PDF', $text);
    }

    /**
     * Compressed streams, several pages, escaped parentheses.
     */
    public function test_native_reads_compressed_multipage_pdf(): void {
        $data = sample::minimal(['First (page) text', 'Second page \\ text'], true);
        [$text, $pages] = (new native_parser())->extract($data);
        $this->assertSame(2, $pages);
        $this->assertStringContainsString('First (page) text', $text);
        $this->assertStringContainsString('Second page \\ text', $text);
        $this->assertLessThan(strpos($text, 'Second'), strpos($text, 'First'), 'page order kept');
    }

    /**
     * Type0 fonts decoded through a ToUnicode CMap (Arabic letters).
     */
    public function test_native_decodes_tounicode_cmap(): void {
        $cmap = "/CIDInit /ProcSet findresource begin 12 dict begin begincmap\n1 begincodespacerange <0000> <FFFF> endcodespacerange\n"
            . "2 beginbfchar <0001> <0645> <0002> <0631> endbfchar\n1 beginbfrange <0003> <0005> <0627> endbfrange\nendcmap";
        $content = 'BT /F1 12 Tf <000100020003> Tj ET';
        $pdf = $this->assemble([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => "<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream",
            5 => '<< /Type /Font /Subtype /Type0 /BaseFont /X /Encoding /Identity-H /ToUnicode 6 0 R >>',
            6 => "<< /Length " . strlen($cmap) . " >>\nstream\n$cmap\nendstream",
        ]);
        [$text] = (new native_parser())->extract($pdf);
        $this->assertSame('مرا', $text);
    }

    /**
     * CMap ranges.
     */
    public function test_cmap_parse(): void {
        $map = cmap::parse("1 begincodespacerange <00> <FF> endcodespacerange\n1 beginbfrange <41> <43> <0061> endbfrange\n"
            . "1 beginbfrange <50> <51> [<0058> <0059>] endbfrange");
        $this->assertSame(1, $map->nbytes);
        $this->assertSame('a', $map->map[0x41]);
        $this->assertSame('c', $map->map[0x43]);
        $this->assertSame('X', $map->map[0x50]);
        $this->assertSame('Y', $map->map[0x51]);
    }

    /**
     * Encrypted files are reported, not mis-read.
     */
    public function test_native_detects_encryption(): void {
        $this->expectException(exception::class);
        $this->expectExceptionMessage('encrypted');
        (new native_parser())->extract("%PDF-1.4\n1 0 obj\n<< /Filter /Standard >>\nendobj\ntrailer\n<< /Encrypt 1 0 R /Root 2 0 R >>\n");
    }

    /**
     * Reader facade with the binary disabled uses the native parser.
     */
    public function test_reader_uses_native_fallback(): void {
        $path = tempnam(sys_get_temp_dir(), 'bpa');
        file_put_contents($path, sample::minimal(['Reader test text here, long enough to count as real content.']));
        try {
            $extraction = (new pdf_reader(''))->read($path);
            $this->assertSame('native', $extraction->extractor);
            $this->assertStringContainsString('Reader test text', $extraction->text);
            $this->assertSame(1, $extraction->pages);
        } finally {
            unlink($path);
        }
    }

    /**
     * Files that are not PDFs, and PDFs without text, give clear error codes.
     */
    public function test_reader_error_codes(): void {
        $path = tempnam(sys_get_temp_dir(), 'bpa');
        try {
            file_put_contents($path, 'just some text');
            $this->assertSame('notpdf', $this->code_of($path));
            file_put_contents($path, sample::minimal(['']));
            $this->assertSame('noextractabletext', $this->code_of($path));
            $this->assertSame('unreadable', $this->code_of($path . '.missing'));
        } finally {
            unlink($path);
        }
    }

    /**
     * A tiny compressed stream that expands to far more than the limit is refused ("decompression bomb").
     */
    public function test_native_refuses_decompression_bomb(): void {
        $bomb = gzcompress(str_repeat('BT (x) Tj ET ', 400000)); // About 5 MB once inflated.
        $data = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Filter /FlateDecode /Length " . strlen($bomb) . " >>\nstream\n" . $bomb . "\nendstream\nendobj\n"
            . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";
        try {
            (new native_parser(30, 1048576))->extract($data); // Limit: 1 MB in total.
            $this->fail('A stream expanding beyond the limit must be refused.');
        } catch (exception $e) {
            $this->assertSame('toolarge', $e->errorcode);
        }
        // The same file is fine with a generous limit.
        [, $pages] = (new native_parser(30, 64 * 1048576))->extract($data);
        $this->assertSame(1, $pages);
    }

    /**
     * An extractor that does not finish in time is stopped and reported as "timeout".
     */
    public function test_reader_stops_slow_pdftotext(): void {
        if (!function_exists('proc_open') || DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Needs proc_open and a POSIX shell.');
        }
        $script = tempnam(sys_get_temp_dir(), 'bpa');
        file_put_contents($script, "#!/bin/sh\nsleep 30\n");
        chmod($script, 0755);
        $path = tempnam(sys_get_temp_dir(), 'bpa');
        file_put_contents($path, sample::minimal(['Some text']));
        try {
            $started = microtime(true);
            try {
                (new pdf_reader($script, true, 1))->read($path);
                $this->fail('The slow extractor must be stopped.');
            } catch (exception $e) {
                $this->assertSame('timeout', $e->errorcode);
            }
            $this->assertLessThan(10, microtime(true) - $started);
        } finally {
            unlink($script);
            unlink($path);
        }
    }

    /**
     * An extractor that prints more than the allowed amount is stopped and reported as "toolarge".
     */
    public function test_reader_caps_pdftotext_output(): void {
        if (!function_exists('proc_open') || DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Needs proc_open and a POSIX shell.');
        }
        $script = tempnam(sys_get_temp_dir(), 'bpa');
        file_put_contents($script, "#!/bin/sh\nyes 'endless text' | head -c 20000000\n");
        chmod($script, 0755);
        $path = tempnam(sys_get_temp_dir(), 'bpa');
        file_put_contents($path, sample::minimal(['Some text']));
        try {
            try {
                (new pdf_reader($script, true, 20, 1048576))->read($path);
                $this->fail('Output beyond the cap must be refused.');
            } catch (exception $e) {
                $this->assertSame('toolarge', $e->errorcode);
            }
        } finally {
            unlink($script);
            unlink($path);
        }
    }

    /**
     * Error code raised when reading a path.
     *
     * @param string $path Path.
     * @return string
     */
    private function code_of(string $path): string {
        try {
            (new pdf_reader(''))->read($path);
        } catch (exception $e) {
            return $e->errorcode;
        }
        return 'none';
    }

    /**
     * Text cleaning normalises whitespace and Arabic presentation forms.
     */
    public function test_clean(): void {
        $this->assertSame("a b\n\nc", pdf_reader::clean("a   b\r\n\r\n\r\n\r\nc"));
        if (class_exists('Normalizer')) {
            $this->assertSame('مرحبا', pdf_reader::clean("\u{FEE1}\u{FEAE}\u{FEA3}\u{FE92}\u{FE8E}"));
        }
    }
}
