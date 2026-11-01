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


namespace block_aipdfassistant\local;

use block_aipdfassistant\local\office\docx_reader;
use block_aipdfassistant\local\pdf\extraction;
use block_aipdfassistant\local\pdf\pdf_reader;

/**
 * Chooses the right reader for an uploaded file by looking at its content, not its name.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class document_reader {
    /** @var string pdftotext binary for PDF files. */
    private $pdftotext;

    /**
     * Constructor.
     *
     * @param string $pdftotext Path or name of pdftotext (empty to disable).
     */
    public function __construct(string $pdftotext = 'pdftotext') {
        $this->pdftotext = $pdftotext;
    }

    /**
     * Detect the file type from its first bytes.
     *
     * @param string $path Absolute path.
     * @return string "pdf", "docx", or "" when unsupported (including legacy .doc).
     */
    public static function detect(string $path): string {
        if (!is_readable($path)) {
            return '';
        }
        $head = (string) file_get_contents($path, false, null, 0, 1024);
        if (strpos($head, '%PDF-') !== false) {
            return 'pdf';
        }
        if (substr($head, 0, 4) === "PK\x03\x04" && class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $isdocx = $zip->statName('word/document.xml') !== false;
                $zip->close();
                return $isdocx ? 'docx' : '';
            }
        }
        return '';
    }

    /**
     * Extract the text of a PDF or Word file.
     *
     * @param string $path Absolute path.
     * @param int $maxchars Maximum characters kept (0 = unlimited).
     * @return extraction
     * @throws exception unsupportedtype, unreadable, encrypted, noextractabletext, toolarge.
     */
    public function read(string $path, int $maxchars = 0): extraction {
        if (!is_readable($path)) {
            throw new exception('unreadable');
        }
        switch (self::detect($path)) {
            case 'pdf':
                return (new pdf_reader($this->pdftotext))->read($path, $maxchars);
            case 'docx':
                return (new docx_reader())->read($path, $maxchars);
            default:
                throw new exception('unsupportedtype');
        }
    }
}
