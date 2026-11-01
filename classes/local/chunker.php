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
 * Splits long document text and picks the passages most relevant to a question.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class chunker {
    /** Words ignored when scoring passages (very small multilingual stop list). */
    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'that', 'this', 'what', 'which', 'from', 'are', 'was', 'how', 'why', 'who',
        'les', 'des', 'une', 'est', 'que', 'qui', 'quoi', 'dans', 'pour', 'par', 'sur', 'avec', 'comment', 'pourquoi',
        'في', 'من', 'على', 'الى', 'عن', 'ما', 'ماذا', 'هل', 'كيف', 'لماذا', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي',
        'واش', 'وش', 'كيفاش', 'علاش', 'هاد', 'هاذ', 'تاع',
    ];

    /**
     * Split text into chunks no longer than $size characters, respecting paragraphs.
     *
     * @param string $text Document text.
     * @param int $size Maximum characters per chunk (minimum 200).
     * @return string[] Chunks in document order.
     */
    public static function split(string $text, int $size): array {
        $size = max(200, $size);
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $paragraphs = preg_split('/\n\s*\n/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $chunks = [];
        $current = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (mb_strlen($paragraph, 'UTF-8') > $size) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                foreach (text::split_sentences($paragraph, $size) as $piece) {
                    $chunks[] = $piece;
                }
                continue;
            }
            if ($current === '') {
                $current = $paragraph;
            } else if (mb_strlen($current . "\n\n" . $paragraph, 'UTF-8') <= $size) {
                $current .= "\n\n" . $paragraph;
            } else {
                $chunks[] = $current;
                $current = $paragraph;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }
        return $chunks;
    }

    /**
     * Take evenly spaced pieces of a long text so the model sees the whole document's span.
     *
     * @param string $text Document text.
     * @param int $budget Maximum total characters.
     * @param int $pieces Number of pieces to sample.
     * @return string Sampled text (the original when it already fits).
     */
    public static function sample(string $text, int $budget, int $pieces = 6): string {
        if (mb_strlen($text, 'UTF-8') <= $budget) {
            return $text;
        }
        $pieces = max(1, $pieces);
        $chunks = self::split($text, (int) floor($budget / $pieces));
        $count = count($chunks);
        if ($count <= $pieces) {
            return implode("\n\n", $chunks);
        }
        $picked = [];
        for ($i = 0; $i < $pieces; $i++) {
            $index = (int) floor($i * ($count - 1) / max(1, $pieces - 1));
            $picked[$index] = $chunks[$index];
        }
        ksort($picked);
        return implode("\n\n[…]\n\n", $picked);
    }

    /**
     * Select the passages most relevant to a query (TF-IDF like scoring).
     *
     * @param string $text Document text.
     * @param string $query User question.
     * @param int $budget Maximum total characters returned.
     * @param int $chunksize Size of candidate passages.
     * @return string Selected passages in document order, or the whole text if it fits.
     */
    public static function top_chunks(string $text, string $query, int $budget, int $chunksize = 1800): string {
        if (mb_strlen($text, 'UTF-8') <= $budget) {
            return $text;
        }
        $chunks = self::split($text, $chunksize);
        $terms = self::query_terms($query);
        if (!$terms) {
            return self::sample($text, $budget);
        }
        $total = count($chunks);
        $docfreq = array_fill_keys($terms, 0);
        $tokenized = [];
        foreach ($chunks as $i => $chunk) {
            $counts = [];
            foreach (text::tokens(text::normalize($chunk)) as $token) {
                $stem = text::stem($token);
                $counts[$stem] = ($counts[$stem] ?? 0) + 1;
            }
            $tokenized[$i] = $counts;
            foreach ($terms as $term) {
                if (isset($counts[$term])) {
                    $docfreq[$term]++;
                }
            }
        }
        $scores = [];
        foreach ($tokenized as $i => $counts) {
            $score = 0.0;
            foreach ($terms as $term) {
                if (!empty($counts[$term])) {
                    $idf = log(1 + $total / (1 + $docfreq[$term]));
                    $score += (1 + log($counts[$term])) * $idf;
                }
            }
            $scores[$i] = $score;
        }
        arsort($scores);
        if (reset($scores) <= 0) {
            return self::sample($text, $budget);
        }
        $selected = [];
        $used = 0;
        foreach ($scores as $i => $score) {
            if ($score <= 0) {
                break;
            }
            $len = mb_strlen($chunks[$i], 'UTF-8') + 6;
            if ($used + $len > $budget && $selected) {
                continue;
            }
            $selected[$i] = $chunks[$i];
            $used += $len;
            if ($used >= $budget) {
                break;
            }
        }
        ksort($selected);
        return implode("\n\n[…]\n\n", $selected);
    }

    /**
     * Extract searchable terms from a question.
     *
     * @param string $query Question text.
     * @return string[] Unique stemmed terms.
     */
    public static function query_terms(string $query): array {
        $terms = [];
        foreach (text::tokens(text::normalize($query)) as $token) {
            if (mb_strlen($token, 'UTF-8') < 3 || in_array($token, self::STOPWORDS, true)) {
                continue;
            }
            $terms[text::stem($token)] = true;
        }
        return array_keys($terms);
    }
}
