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
 * Detects what the user wants (summarise, explain, translate, quiz, file name,
 * read aloud, or a free question) from a message written in Arabic, Algerian
 * Darija, French or English. Pure PHP, no Moodle dependencies.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class intent {
    /** Action: summarise the document. */
    const SUMMARIZE = 'summarize';
    /** Action: explain the document. */
    const EXPLAIN = 'explain';
    /** Action: translate the document. */
    const TRANSLATE = 'translate';
    /** Action: build a short quiz. */
    const QUIZ = 'quiz';
    /** Action: tell the file name. */
    const FILENAME = 'filename';
    /** Action: read the previous answer aloud. */
    const SPEAK = 'speak';
    /** Action: free question about the document. */
    const ASK = 'ask';

    /** Actions that need a document to work on. */
    const NEEDS_DOCUMENT = [self::SUMMARIZE, self::EXPLAIN, self::TRANSLATE, self::QUIZ, self::FILENAME];

    /** Supported target/response languages: code => English name. */
    const LANGUAGES = [
        'ar' => 'Arabic', 'fr' => 'French', 'en' => 'English', 'es' => 'Spanish',
        'de' => 'German', 'it' => 'Italian', 'tr' => 'Turkish', 'ru' => 'Russian',
        'zh' => 'Chinese', 'darija' => 'Algerian Arabic (Darija)',
    ];

    /** @var string[] Language needles (normalised at runtime) per language code. */
    private const LANGUAGE_NEEDLES = [
        'darija' => ['دارجة', 'darija', 'algerien', 'جزائري', 'جزايري', 'عامية', 'بالدارجه'],
        'ar' => ['عربي', 'عربية', 'arabic', 'arabe'],
        'fr' => ['فرنسي', 'فرنسية', 'فرنسا', 'french', 'francais', 'francaise'],
        'en' => ['انجليزي', 'انجليزية', 'انكليزي', 'انقليزي', 'انجليزيه', 'english', 'anglais', 'anglaise'],
        'es' => ['اسباني', 'اسبانية', 'spanish', 'espagnol', 'espanol'],
        'de' => ['الماني', 'المانية', 'german', 'allemand'],
        'it' => ['ايطالي', 'ايطالية', 'italian', 'italien'],
        'tr' => ['تركي', 'تركية', 'turkish', 'turc'],
        'ru' => ['روسي', 'روسية', 'russian', 'russe'],
        'zh' => ['صيني', 'صينية', 'chinese', 'chinois'],
    ];

    /** @var string[] Words that mark the *source* language ("from French"). */
    private const SOURCE_MARKERS = ['من', 'from', 'de', 'depuis', 'du'];

    /** @var string Resolved action. */
    public $action = self::ASK;

    /** @var bool Whether the user also wants the answer read aloud. */
    public $speak = false;

    /** @var string Target/response language code or empty string. */
    public $language = '';

    /**
     * Detect the intent of a message.
     *
     * @param string $message User message (may be empty when a button was used).
     * @param string $explicit Action chosen with a button: "auto" or one of the action constants.
     * @param string $explicitlang Language chosen in the UI (used for translate when the text names none).
     * @return intent Detected intent.
     */
    public static function detect(string $message, string $explicit = 'auto', string $explicitlang = ''): intent {
        $result = new self();
        $norm = text::normalize($message);
        $tokens = text::tokens($norm);

        $result->speak = self::has_any($norm, $tokens, [
            'بالصوت', 'بصوت', 'صوتيا', 'صوتي', 'اسمع', 'سمعني', 'اقراها', 'اقراه', 'اقرا لي', 'اقرا الي',
            'قولهالي', 'قولها لي', 'قوله لي', 'نسمع', 'audio', 'listen', 'read aloud', 'read it to me',
            'out loud', 'aloud', 'mp3', 'voix', 'vocal', 'a haute voix', 'ecout', 'lis moi', 'lis-moi', 'tts',
        ]);

        $language = self::detect_language($norm, $tokens);
        $result->language = $language;

        $valid = array_merge(self::NEEDS_DOCUMENT, [self::SPEAK, self::ASK]);
        if ($explicit !== 'auto' && in_array($explicit, $valid, true)) {
            $result->action = $explicit;
            if ($explicit === self::SPEAK) {
                $result->speak = true;
            }
        } else {
            $result->action = self::detect_action($norm, $tokens, $result->speak);
        }

        if ($result->action === self::TRANSLATE && $result->language === '' && isset(self::LANGUAGES[$explicitlang])) {
            $result->language = $explicitlang;
        }
        return $result;
    }

    /**
     * Whether this action requires a document.
     *
     * @return bool True when a document must be attached.
     */
    public function needs_document(): bool {
        return in_array($this->action, self::NEEDS_DOCUMENT, true);
    }

    /**
     * Pick the main action from the normalised message.
     *
     * @param string $norm Normalised message.
     * @param string[] $tokens Tokens.
     * @param bool $speak Whether a read-aloud wish was found.
     * @return string Action constant.
     */
    private static function detect_action(string $norm, array $tokens, bool $speak): string {
        if ($norm === '') {
            return $speak ? self::SPEAK : self::ASK;
        }
        $strongfile = ['اسم الملف', 'اسم ملف', 'اسم الوثيقة', 'اسم الكتاب', 'اسم ال pdf', 'file name', 'filename',
            'nom du fichier', 'nom de fichier', 'nom du document', 'name of the file', 'name of this file',
            'name of the document', 'titre du fichier'];
        if (self::has_any($norm, $tokens, $strongfile)) {
            return self::FILENAME;
        }
        if (count($tokens) <= 5 && self::has_any($norm, $tokens, ['اسمه', 'اسمو', 'اسمها', 'سميته'])) {
            return self::FILENAME;
        }
        $verbs = ['اعطني', 'اعطيني', 'اعمل', 'ولد', 'حضر', 'انشئ', 'اقترح', 'create', 'generate', 'make', 'give me',
            'genere', 'cree', 'propose', 'donne'];
        $quizwords = ['quiz', 'qcm', 'كويز', 'اختبرني', 'امتحني', 'اختبار', 'test me', 'interroge', 'questionnaire'];
        if (self::has_any($norm, $tokens, $quizwords)
                || (self::has_any($norm, $tokens, ['اسيله', 'questions']) && self::has_any($norm, $tokens, $verbs))) {
            return self::QUIZ;
        }
        if (self::has_any($norm, $tokens, ['ترجم', 'ترجمه', 'translat', 'traduis', 'traduire', 'traduction', 'traduit'])) {
            return self::TRANSLATE;
        }
        if (self::has_any($norm, $tokens, ['لخص', 'تلخيص', 'ملخص', 'اختصر', 'خلاصه', 'summar', 'tl;dr', 'tldr', 'resum',
                'synthese', 'recap', 'in short', 'briefly', 'sum up', 'gist'])) {
            return self::SUMMARIZE;
        }
        if (self::has_any($norm, $tokens, ['اشرح', 'شرح', 'فسر', 'وضح', 'بسط', 'فهمني', 'فهمهالي', 'فهميني', 'explique',
                'expliqu', 'explain', 'eli5', 'clarify', 'break down'])) {
            return self::EXPLAIN;
        }
        if ($speak && self::is_only_speak_request($tokens)) {
            return self::SPEAK;
        }
        return self::ASK;
    }

    /**
     * Whether the message is just a request to read something aloud (few words).
     *
     * @param string[] $tokens Tokens.
     * @return bool True when the message is short.
     */
    private static function is_only_speak_request(array $tokens): bool {
        return count($tokens) <= 6;
    }

    /**
     * Find the target language: the last language named that is not marked as the source.
     *
     * @param string $norm Normalised message.
     * @param string[] $tokens Tokens.
     * @return string Language code or empty string.
     */
    private static function detect_language(string $norm, array $tokens): string {
        $found = '';
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $code = self::language_of_token($tokens[$i]);
            if ($code === '') {
                continue;
            }
            $prev = $i > 0 ? $tokens[$i - 1] : '';
            if ($prev !== '' && in_array($prev, self::SOURCE_MARKERS, true)) {
                continue;
            }
            $found = $code;
        }
        return $found;
    }

    /**
     * Map one token to a language code.
     *
     * @param string $token Normalised token.
     * @return string Language code or empty string.
     */
    private static function language_of_token(string $token): string {
        $prefixes = ['', 'ال', 'بال', 'لل', 'ل', 'ب', 'و', 'وال', 'كال', 'فال', 'وبال', 'ول'];
        foreach (self::LANGUAGE_NEEDLES as $code => $needles) {
            foreach ($needles as $needle) {
                $needle = text::normalize($needle);
                if (preg_match('/\p{Arabic}/u', $needle)) {
                    foreach ($prefixes as $prefix) {
                        if ($token === $prefix . $needle) {
                            return $code;
                        }
                    }
                } else if ($token === $needle) {
                    return $code;
                }
            }
        }
        return '';
    }

    /**
     * Whether the message contains any of the needles.
     *
     * Arabic needles and multi-word phrases are matched as substrings; single Latin
     * words are matched as token prefixes (so "summar" matches "summarize").
     *
     * @param string $norm Normalised message.
     * @param string[] $tokens Tokens of the message.
     * @param string[] $needles Needles in natural spelling (normalised here).
     * @return bool True when one needle matches.
     */
    private static function has_any(string $norm, array $tokens, array $needles): bool {
        foreach ($needles as $needle) {
            $needle = text::normalize($needle);
            if ($needle === '') {
                continue;
            }
            $isarabic = (bool) preg_match('/\p{Arabic}/u', $needle);
            if ($isarabic || strpos($needle, ' ') !== false || strpos($needle, ';') !== false || strpos($needle, '-') !== false) {
                if (strpos($norm, $needle) !== false) {
                    return true;
                }
                continue;
            }
            foreach ($tokens as $token) {
                if (strpos($token, $needle) === 0) {
                    return true;
                }
            }
        }
        return false;
    }
}
