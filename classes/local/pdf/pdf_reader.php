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


namespace block_aipdfassistant\local\pdf;

use block_aipdfassistant\local\exception;

/**
 * Reads text from a PDF file: tries the pdftotext binary first (best quality) and
 * falls back to the built-in PHP parser.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_reader {
    /** @var string Path or name of the pdftotext binary ('' disables it). */
    private $pdftotext;

    /** @var bool Whether the PHP parser may be used. */
    private $allownative;

    /**
     * Constructor.
     *
     * @param string $pdftotext Binary path or name; empty to disable.
     * @param bool $allownative Allow the PHP fallback parser.
     */
    public function __construct(string $pdftotext = 'pdftotext', bool $allownative = true) {
        $this->pdftotext = trim($pdftotext);
        $this->allownative = $allownative;
    }

    /**
     * Extract the text of a PDF file.
     *
     * @param string $path Absolute path.
     * @param int $maxchars Maximum characters kept (0 = unlimited).
     * @return extraction
     * @throws exception notpdf, unreadable, encrypted or noextractabletext.
     */
    public function read(string $path, int $maxchars = 0): extraction {
        if (!is_readable($path)) {
            throw new exception('unreadable');
        }
        $head = (string) file_get_contents($path, false, null, 0, 1024);
        if (strpos($head, '%PDF-') === false) {
            throw new exception('notpdf');
        }

        $best = null;
        $encrypted = false;
        if ($this->pdftotext !== '') {
            try {
                $best = $this->run_pdftotext($path);
            } catch (exception $e) {
                if ($e->errorcode === 'encrypted') {
                    $encrypted = true;
                }
            }
        }
        if (($best === null || self::nonspace($best->text) < 20) && $this->allownative) {
            try {
                $data = (string) file_get_contents($path);
                $native = new native_parser();
                [$text, $pages] = $native->extract($data);
                $candidate = new extraction(self::clean($text), $pages, 'native');
                if ($best === null || self::nonspace($candidate->text) > self::nonspace($best->text)) {
                    $best = $candidate;
                }
            } catch (exception $e) {
                if ($e->errorcode === 'encrypted') {
                    $encrypted = true;
                }
            }
        }
        if ($best === null || self::nonspace($best->text) < 1) {
            throw new exception($encrypted ? 'encrypted' : 'noextractabletext');
        }
        if ($maxchars > 0 && mb_strlen($best->text, 'UTF-8') > $maxchars) {
            $best = new extraction(mb_substr($best->text, 0, $maxchars, 'UTF-8'), $best->pages, $best->extractor);
        }
        return $best;
    }

    /**
     * Count non-whitespace characters.
     *
     * @param string $text Text.
     * @return int
     */
    private static function nonspace(string $text): int {
        return strlen((string) preg_replace('/\s+/u', '', $text));
    }

    /**
     * Normalise extracted text: valid UTF-8, compatibility forms, tidy whitespace.
     *
     * @param string $text Raw text.
     * @return string
     */
    public static function clean(string $text): string {
        $text = (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        if (class_exists('Normalizer')) {
            $normalised = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            if (is_string($normalised)) {
                $text = $normalised;
            }
        }
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0E-\x1F\x{FFFE}\x{FFFF}]/u', '', $text);
        $text = str_replace(["\r\n", "\r", "\u{00A0}", "\f"], ["\n", "\n", ' ', "\n\n"], $text);
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $lines[$i] = trim((string) preg_replace('/[ \t]+/u', ' ', $line));
        }
        $text = implode("\n", $lines);
        return trim((string) preg_replace('/\n{3,}/', "\n\n", $text));
    }

    /**
     * Run the pdftotext binary.
     *
     * @param string $path PDF path.
     * @return extraction|null Null when the binary is unavailable or failed.
     * @throws exception With code "encrypted" for password protected files.
     */
    private function run_pdftotext(string $path): ?extraction {
        if (!function_exists('proc_open')) {
            return null;
        }
        $command = [$this->pdftotext, '-enc', 'UTF-8', '-eol', 'unix', $path, '-'];
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            if (stripos($stderr, 'password') !== false || stripos($stderr, 'encrypt') !== false) {
                throw new exception('encrypted');
            }
            return null;
        }
        $pages = substr_count($stdout, "\f");
        return new extraction(self::clean($stdout), $pages > 0 ? $pages : 1, 'pdftotext');
    }
}
