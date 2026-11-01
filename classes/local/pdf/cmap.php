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
 * ToUnicode CMap parser: maps character codes used inside a PDF font to Unicode text.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cmap {
    /** @var string[] Character code => UTF-8 string. */
    public $map = [];

    /** @var int Bytes per character code (0 when the CMap does not say). */
    public $nbytes = 0;

    /**
     * Parse CMap source text.
     *
     * @param string $source Decoded CMap stream.
     * @return cmap
     */
    public static function parse(string $source): cmap {
        $cmap = new self();
        if (preg_match_all('~begincodespacerange(.*?)endcodespacerange~s', $source, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('~<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>~', $block, $pairs, PREG_SET_ORDER)) {
                    foreach ($pairs as $pair) {
                        $cmap->nbytes = max($cmap->nbytes, intdiv(strlen($pair[1]), 2));
                    }
                }
            }
        }
        if (preg_match_all('~beginbfchar(.*?)endbfchar~s', $source, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('~<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]*)>~', $block, $pairs, PREG_SET_ORDER)) {
                    foreach ($pairs as $pair) {
                        $cmap->map[hexdec($pair[1])] = self::hex_to_utf8($pair[2]);
                    }
                }
            }
        }
        if (preg_match_all('~beginbfrange(.*?)endbfrange~s', $source, $blocks)) {
            foreach ($blocks[1] as $block) {
                $found = preg_match_all(
                    '~<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(\[[^\]]*\]|<[0-9A-Fa-f]*>)~',
                    $block,
                    $ranges,
                    PREG_SET_ORDER
                );
                if (!$found) {
                    continue;
                }
                foreach ($ranges as $range) {
                    $lo = hexdec($range[1]);
                    $hi = hexdec($range[2]);
                    if ($hi < $lo || $hi - $lo > 65535) {
                        continue;
                    }
                    $dst = $range[3];
                    if ($dst[0] === '[') {
                        preg_match_all('~<([0-9A-Fa-f]*)>~', $dst, $items);
                        foreach ($items[1] as $i => $item) {
                            if ($lo + $i > $hi) {
                                break;
                            }
                            $cmap->map[$lo + $i] = self::hex_to_utf8($item);
                        }
                    } else {
                        $base = substr($dst, 1, -1);
                        if (strlen($base) < 4) {
                            $base = str_pad($base, 4, '0', STR_PAD_LEFT);
                        }
                        $head = (string) substr($base, 0, -4);
                        $last = hexdec(substr($base, -4));
                        for ($i = 0; $i <= $hi - $lo; $i++) {
                            $cmap->map[$lo + $i] = self::hex_to_utf8($head . sprintf('%04x', ($last + $i) & 0xFFFF));
                        }
                    }
                }
            }
        }
        return $cmap;
    }

    /**
     * Convert a UTF-16BE hex string to UTF-8.
     *
     * @param string $hex Hex digits.
     * @return string UTF-8 text.
     */
    private static function hex_to_utf8(string $hex): string {
        if (strlen($hex) % 2) {
            $hex .= '0';
        }
        $bin = $hex === '' ? '' : hex2bin($hex);
        if ($bin === false || $bin === '') {
            return '';
        }
        $text = mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
        return is_string($text) ? $text : '';
    }
}
