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
 * Builds tiny valid PDF and Word files (used by the connection test and unit tests).
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sample {
    /**
     * Build a PDF with one page per text, each shown with the Helvetica standard font.
     *
     * @param string[] $pages Lines of text, one entry per page.
     * @param bool $compress Whether to Flate-compress the content streams.
     * @return string PDF bytes.
     */
    public static function minimal(array $pages = ['Hello PDF'], bool $compress = false): string {
        $objects = [];
        $kids = [];
        $nextid = 4;
        $fontid = 3;
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[$fontid] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        foreach ($pages as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content = "BT /F1 12 Tf 50 700 Td ($escaped) Tj ET";
            $filter = '';
            if ($compress) {
                $content = gzcompress($content);
                $filter = ' /Filter /FlateDecode';
            }
            $contentid = $nextid++;
            $pageid = $nextid++;
            $objects[$contentid] = '<< /Length ' . strlen($content) . $filter . " >>\nstream\n" . $content . "\nendstream";
            $objects[$pageid] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents ' . $contentid
                . ' 0 R /Resources << /Font << /F1 ' . $fontid . ' 0 R >> >> >>';
            $kids[] = $pageid . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }

    /**
     * Build a .docx file from WordprocessingML body XML.
     *
     * @param string $bodyxml XML placed inside w:body (paragraphs, tables...).
     * @param string $extradeclaration Extra XML written before w:document (for DTD tests).
     * @return string The .docx bytes.
     * @throws \RuntimeException When the zip extension is missing.
     */
    public static function docx_from_body(string $bodyxml, string $extradeclaration = ''): string {
        if (!class_exists('\ZipArchive')) {
            throw new \RuntimeException('The PHP zip extension is required.');
        }
        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            . 'xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"';
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . $extradeclaration
            . '<w:document ' . $ns . '><w:body>' . $bodyxml . '</w:body></w:document>';
        $path = tempnam(sys_get_temp_dir(), 'bpadocx');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types '
            . 'xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" '
            . 'ContentType="application/xml"/></Types>');
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);
        return $bytes;
    }

    /**
     * Build a .docx file from simple paragraphs.
     *
     * @param array $paragraphs Strings, or ['text' => string, 'style' => 'Heading1'] arrays.
     * @return string The .docx bytes.
     */
    public static function docx(array $paragraphs = ['Hello Word']): string {
        $body = '';
        foreach ($paragraphs as $paragraph) {
            $text = is_array($paragraph) ? $paragraph['text'] : $paragraph;
            $style = is_array($paragraph) && !empty($paragraph['style'])
                ? '<w:pPr><w:pStyle w:val="' . $paragraph['style'] . '"/></w:pPr>' : '';
            $body .= '<w:p>' . $style . '<w:r><w:t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1)
                . '</w:t></w:r></w:p>';
        }
        return self::docx_from_body($body);
    }
}
