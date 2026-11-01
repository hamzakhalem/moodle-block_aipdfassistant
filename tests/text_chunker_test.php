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

use block_aipdfassistant\local\chunker;
use block_aipdfassistant\local\text;

/**
 * Tests for the pure text helpers and the chunker.
 *
 * @package    block_aipdfassistant
 * @category   test
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_aipdfassistant\local\text
 * @covers     \block_aipdfassistant\local\chunker
 */
final class text_chunker_test extends \basic_testcase {
    /**
     * Normalisation unifies Arabic and French spellings.
     */
    public function test_normalize(): void {
        $this->assertSame('اشرح', text::normalize('أَشْرَح'));
        $this->assertSame('ترجمه', text::normalize('ترجمة'));
        $this->assertSame('resume en francais', text::normalize('Résumé en français'));
        $this->assertSame('123', text::normalize('١٢٣'));
    }

    /**
     * Markdown is stripped before speech.
     */
    public function test_markdown_to_plain(): void {
        $plain = text::markdown_to_plain("# Title\n\n**Bold** and [link](https://x.org)\n\n- one\n- two\n\n`code`");
        $this->assertStringNotContainsString('#', $plain);
        $this->assertStringNotContainsString('**', $plain);
        $this->assertStringNotContainsString('https://x.org', $plain);
        $this->assertStringContainsString('Bold and link', $plain);
        $this->assertStringContainsString('one', $plain);
    }

    /**
     * Sentence splitting honours the maximum length and loses no words.
     */
    public function test_split_sentences(): void {
        $source = str_repeat('Une phrase assez courte. ', 40) . str_repeat('x', 350);
        $chunks = text::split_sentences($source, 200);
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(200, mb_strlen($chunk));
        }
        $this->assertSame(40, substr_count(implode(' ', $chunks), 'Une phrase assez courte.'));
        $this->assertSame([], text::split_sentences('   ', 200));
    }

    /**
     * Truncation prefers a sentence boundary.
     */
    public function test_truncate(): void {
        $this->assertSame('Hello world, again.', text::truncate('Hello world, again. And more text here', 25));
        $this->assertSame('short', text::truncate('short', 20));
    }

    /**
     * Arabic ratio.
     */
    public function test_arabic_ratio(): void {
        $this->assertGreaterThan(0.9, text::arabic_ratio('مرحبا بالعالم'));
        $this->assertSame(0.0, text::arabic_ratio('hello'));
    }

    /**
     * Chunks respect the size limit and keep document order.
     */
    public function test_split(): void {
        $paragraphs = [];
        for ($i = 1; $i <= 30; $i++) {
            $paragraphs[] = "Paragraph $i " . str_repeat('word ', 30);
        }
        $chunks = chunker::split(implode("\n\n", $paragraphs), 600);
        $this->assertGreaterThan(3, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(600, mb_strlen($chunk));
        }
        $this->assertStringStartsWith('Paragraph 1 ', $chunks[0]);
    }

    /**
     * Retrieval picks the passage that talks about the question.
     */
    public function test_top_chunks_finds_relevant_passage(): void {
        $filler = str_repeat("Le texte general parle de sujets divers sans importance. ", 25);
        $text = $filler . "\n\n" . "La mitochondrie produit de l'ATP dans la cellule. " . str_repeat('Autre. ', 20) . "\n\n" . $filler
            . "\n\n" . $filler;
        $selected = chunker::top_chunks($text, 'Que produit la mitochondrie ?', 1500, 600);
        $this->assertStringContainsString('mitochondrie produit', $selected);
        $this->assertLessThan(mb_strlen($text), mb_strlen($selected));
        $this->assertSame('short text', chunker::top_chunks('short text', 'anything', 1500));
    }

    /**
     * Sampling covers the whole span of a long text.
     */
    public function test_sample_covers_start_and_end(): void {
        $parts = [];
        for ($i = 1; $i <= 40; $i++) {
            $parts[] = "SECTION-$i " . str_repeat('lorem ', 40);
        }
        $sample = chunker::sample(implode("\n\n", $parts), 3000, 5);
        $this->assertStringContainsString('SECTION-1 ', $sample);
        $this->assertStringContainsString('SECTION-40 ', $sample);
        $this->assertLessThanOrEqual(3600, mb_strlen($sample));
    }

    /**
     * Query terms drop stop words and short tokens, and stem Arabic prefixes.
     */
    public function test_query_terms(): void {
        $terms = chunker::query_terms('Que fait la mitochondrie ATP ?');
        $this->assertContains('mitochondrie', $terms);
        $this->assertContains('atp', $terms);
        $this->assertNotContains('que', $terms);
        $this->assertNotContains('la', $terms);
        $this->assertContains('خلايا', chunker::query_terms('ما هي الخلايا'));
    }
}
