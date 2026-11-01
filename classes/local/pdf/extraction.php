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

/**
 * Result of reading a PDF.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extraction {
    /** @var string Extracted UTF-8 text. */
    public $text;

    /** @var int Page count (best effort). */
    public $pages;

    /** @var string Extractor that produced the text: "pdftotext", "native" or "docx". */
    public $extractor;

    /** @var bool True when very little text was found per page (likely scanned pages). */
    public $lowtext;

    /**
     * Constructor.
     *
     * @param string $text Text.
     * @param int $pages Pages.
     * @param string $extractor Extractor id.
     * @param bool|null $lowtext Force the "little text" flag (null = decide from the amount of text per page).
     */
    public function __construct(string $text, int $pages, string $extractor, ?bool $lowtext = null) {
        $this->text = $text;
        $this->pages = max(1, $pages);
        $this->extractor = $extractor;
        if ($lowtext === null) {
            $nonspace = strlen((string) preg_replace('/\s+/u', '', $text));
            $lowtext = ($nonspace / $this->pages) < 80;
        }
        $this->lowtext = $lowtext;
    }
}
