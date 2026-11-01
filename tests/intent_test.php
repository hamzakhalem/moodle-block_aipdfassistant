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

use block_aipdfassistant\local\intent;

/**
 * Tests for the multilingual intent detector.
 *
 * @package    block_aipdfassistant
 * @category   test
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_aipdfassistant\local\intent
 */
final class intent_test extends \basic_testcase {
    /**
     * Detect action, speech flag and language.
     *
     * @dataProvider cases
     * @param string $message Message.
     * @param string $explicit Button action.
     * @param string $explicitlang UI language.
     * @param string $action Expected action.
     * @param bool $speak Expected speech flag.
     * @param string $language Expected language.
     */
    public function test_detect(string $message, string $explicit, string $explicitlang, string $action, bool $speak,
            string $language): void {
        $intent = intent::detect($message, $explicit, $explicitlang);
        $this->assertSame($action, $intent->action);
        $this->assertSame($speak, $intent->speak);
        $this->assertSame($language, $intent->language);
    }

    /**
     * Cases verified against a line-for-line Python mirror of the detector.
     *
     * @return array
     */
    public static function cases(): array {
        return [
            ['لخص لي هذا الملف', 'auto', '', 'summarize', false, ''],
            ['لخصلي هذا بالصوت', 'auto', '', 'summarize', true, ''],
            ['اشرح لي هذا الدرس', 'auto', '', 'explain', false, ''],
            ['اشرحلي بالدارجة', 'auto', '', 'explain', false, 'darija'],
            ['ترجم الملف للفرنسية', 'auto', '', 'translate', false, 'fr'],
            ['ترجم من الفرنسية الى العربية', 'auto', '', 'translate', false, 'ar'],
            ['ترجم من العربية للفرنسية بالصوت', 'auto', '', 'translate', true, 'fr'],
            ['translate this to English', 'auto', '', 'translate', false, 'en'],
            ['translate to French from Arabic', 'auto', '', 'translate', false, 'fr'],
            ['traduis en arabe', 'auto', '', 'translate', false, 'ar'],
            ['résume ce document', 'auto', '', 'summarize', false, ''],
            ['Résumé en français s\'il te plaît', 'auto', '', 'summarize', false, 'fr'],
            ['explique-moi le chapitre 2', 'auto', '', 'explain', false, ''],
            ['Summarize this please', 'auto', '', 'summarize', false, ''],
            ['explain like I\'m five', 'auto', '', 'explain', false, ''],
            ['قولهالي بالصوت', 'auto', '', 'speak', true, ''],
            ['عطيني اسمو', 'auto', '', 'filename', false, ''],
            ['ما هو اسم الملف؟', 'auto', '', 'filename', false, ''],
            ['what is the file name', 'auto', '', 'filename', false, ''],
            ['quel est le nom du fichier ?', 'auto', '', 'filename', false, ''],
            ['اعطني اسئلة حول الدرس', 'auto', '', 'quiz', false, ''],
            ['quiz me', 'auto', '', 'quiz', false, ''],
            ['génère un QCM', 'auto', '', 'quiz', false, ''],
            ['اشرح تركيب الجزيء', 'auto', '', 'explain', false, ''],
            ['اشرح تركيز المحلول', 'auto', '', 'explain', false, ''],
            ['ما هي عاصمة الجزائر؟', 'auto', '', 'ask', false, ''],
            ['who wrote chapter two?', 'auto', '', 'ask', false, ''],
            ['ما اسم المؤلف الذي ذكر في النص حسب رأيك وبالتفصيل', 'auto', '', 'ask', false, ''],
            ['', 'summarize', '', 'summarize', false, ''],
            ['', 'translate', 'fr', 'translate', false, 'fr'],
            ['ترجم', 'auto', 'en', 'translate', false, 'en'],
            ['', 'speak', '', 'speak', true, ''],
            ['listen', 'auto', '', 'speak', true, ''],
            ['read it aloud to me', 'auto', '', 'speak', true, ''],
            ['lire à haute voix', 'auto', '', 'speak', true, ''],
            ['هل يمكنك تلخيص الفصل الثالث باللغة الانجليزية', 'auto', '', 'summarize', false, 'en'],
        ];
    }

    /**
     * Which actions need a document.
     */
    public function test_needs_document(): void {
        $this->assertTrue(intent::detect('لخص')->needs_document());
        $this->assertFalse(intent::detect('who wrote this?')->needs_document());
        $this->assertFalse(intent::detect('', 'speak')->needs_document());
    }
}
