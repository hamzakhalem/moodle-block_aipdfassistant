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

/**
 * Pure text helpers (Arabic/French/English aware). No Moodle dependencies.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class text {
    /**
     * Normalise text for keyword matching: lower case, strip Arabic diacritics and
     * tatweel, unify alef/yaa/taa-marbuta variants, strip Latin accents.
     *
     * @param string $s Input text.
     * @return string Normalised text, single spaced.
     */
    public static function normalize(string $s): string {
        $s = mb_strtolower($s, 'UTF-8');
        $s = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
        $s = strtr($s, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
            'گ' => 'ك', 'ڤ' => 'ف', 'پ' => 'ب', 'چ' => 'ج',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', 'ñ' => 'n', 'ß' => 'ss',
            '’' => "'", '‘' => "'", '؟' => '?', '،' => ',',
        ]);
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /**
     * Split normalised text into word tokens.
     *
     * @param string $normalized Output of {@see normalize()}.
     * @return string[] Tokens.
     */
    public static function tokens(string $normalized): array {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        return $parts ? $parts : [];
    }

    /**
     * Share of Arabic-script letters among all letters (0..1).
     *
     * @param string $s Text.
     * @return float Ratio.
     */
    public static function arabic_ratio(string $s): float {
        $letters = preg_match_all('/\p{L}/u', $s);
        if (!$letters) {
            return 0.0;
        }
        $arabic = preg_match_all('/\p{Arabic}/u', $s);
        return $arabic / $letters;
    }

    /**
     * Strip Markdown so text can be read aloud by a TTS engine.
     *
     * @param string $md Markdown text.
     * @return string Plain text.
     */
    public static function markdown_to_plain(string $md): string {
        $t = str_replace(["\r\n", "\r"], "\n", $md);
        $t = (string) preg_replace('/```[a-zA-Z0-9_-]*\n?/', '', $t);
        $t = (string) preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $t);
        $t = (string) preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $t);
        $t = (string) preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $t);
        $t = (string) preg_replace('/^\s*>\s?/m', '', $t);
        $t = (string) preg_replace('/^\s*([-*+]|\d+[.)])\s+/m', '', $t);
        $t = (string) preg_replace('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/m', '', $t);
        $t = (string) preg_replace('/\s*\|\s*/', ', ', $t);
        $t = (string) preg_replace('/(\*\*|__|\*|_|`|~~)/', '', $t);
        $t = (string) preg_replace('/<[^>]+>/', '', $t);
        $t = (string) preg_replace('/[ \t]+/', ' ', $t);
        $t = (string) preg_replace('/\n{3,}/', "\n\n", $t);
        return trim($t);
    }

    /**
     * Cut text to a maximum number of characters, preferring a sentence boundary.
     *
     * @param string $s Text.
     * @param int $max Maximum characters.
     * @return string Possibly shortened text.
     */
    public static function truncate(string $s, int $max): string {
        if ($max <= 0 || mb_strlen($s, 'UTF-8') <= $max) {
            return $s;
        }
        $cut = mb_substr($s, 0, $max, 'UTF-8');
        if (preg_match('/^(.*[.!?؟。\n])\s/su', $cut . ' ', $m) && mb_strlen($m[1], 'UTF-8') > $max * 0.6) {
            return rtrim($m[1]);
        }
        $pos = mb_strrpos($cut, ' ', 0, 'UTF-8');
        return $pos !== false && $pos > $max * 0.6 ? mb_substr($cut, 0, $pos, 'UTF-8') : $cut;
    }

    /**
     * Split text in chunks of at most $max characters, breaking on sentence ends.
     *
     * @param string $s Text.
     * @param int $max Maximum characters per chunk (minimum 100).
     * @return string[] Chunks.
     */
    public static function split_sentences(string $s, int $max): array {
        $max = max(100, $max);
        $s = trim($s);
        if ($s === '') {
            return [];
        }
        $pieces = preg_split('/(?<=[.!?؟。:\n])\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $current = '';
        foreach ($pieces as $piece) {
            while (mb_strlen($piece, 'UTF-8') > $max) {
                $head = mb_substr($piece, 0, $max, 'UTF-8');
                $pos = mb_strrpos($head, ' ', 0, 'UTF-8');
                if ($pos === false || $pos < $max * 0.5) {
                    $pos = $max;
                }
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                $chunks[] = trim(mb_substr($piece, 0, $pos, 'UTF-8'));
                $piece = trim(mb_substr($piece, $pos, null, 'UTF-8'));
            }
            if ($piece === '') {
                continue;
            }
            if ($current === '') {
                $current = $piece;
            } else if (mb_strlen($current . ' ' . $piece, 'UTF-8') <= $max) {
                $current .= ' ' . $piece;
            } else {
                $chunks[] = $current;
                $current = $piece;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }
        return $chunks;
    }

    /**
     * Light Arabic/Latin stemmer used by the retriever (prefix stripping only).
     *
     * @param string $token Normalised token.
     * @return string Stemmed token.
     */
    public static function stem(string $token): string {
        if (preg_match('/\p{Arabic}/u', $token) && mb_strlen($token, 'UTF-8') > 4) {
            foreach (['وال', 'بال', 'كال', 'لل', 'ال'] as $prefix) {
                if (strpos($token, $prefix) === 0) {
                    return mb_substr($token, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8');
                }
            }
            return $token;
        }
        if (mb_strlen($token, 'UTF-8') > 5 && substr($token, -1) === 's') {
            return substr($token, 0, -1);
        }
        return $token;
    }
}
