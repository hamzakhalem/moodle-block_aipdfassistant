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


/**
 * Block class for block_aipdfassistant.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_aipdfassistant\local\config;
use block_aipdfassistant\local\intent;

/**
 * Chat style study assistant: attach a PDF, then summarise, explain, translate or listen to it.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_aipdfassistant extends block_base {
    /** Names of the js_* language strings handed to the browser. */
    const JS_STRINGS = ['assistant', 'you', 'dropTitle', 'dropHint', 'pickDocument', 'recent', 'courseFiles', 'noDocs',
        'placeholder', 'placeholderNoDoc', 'send', 'attach', 'mic', 'micStop', 'expand', 'collapse', 'clear', 'removeDoc',
        'copy', 'copied', 'listen', 'stop', 'retry', 'summarize', 'explain', 'translate', 'quiz', 'filename', 'speak',
        'translateTo', 'uploading', 'reading', 'thinking', 'pages', 'page', 'ready', 'emptyTitle', 'emptyHint', 'errorSize',
        'errorType', 'errorGeneric', 'lowtext', 'noticePartial', 'noticeTranslate', 'cached', 'speaking', 'loading',
        'audioFailed', 'browserVoice', 'docAttached', 'uploadFailed'];

    /** js_* strings that take a placeholder value ({$a}). */
    const JS_PLACEHOLDER_STRINGS = ['dropHint', 'pages', 'errorSize', 'docAttached'];

    /**
     * Initialise the block.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('blocktitle_default', 'block_aipdfassistant');
    }

    /**
     * Where the block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * The block has site wide settings.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * One assistant per page is enough.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Apply the per-instance title.
     *
     * @return void
     */
    public function specialization() {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title, true, ['context' => $this->context]);
        } else {
            $this->title = get_string('blocktitle_default', 'block_aipdfassistant');
        }
    }

    /**
     * Build the block content.
     *
     * @return stdClass
     */
    public function get_content() {
        global $OUTPUT;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser() || !has_capability('block/aipdfassistant:use', $this->context)) {
            return $this->content;
        }

        $config = config::load();
        if (!$config->is_configured()) {
            $message = get_string('notconfigured_user', 'block_aipdfassistant');
            if (has_capability('moodle/site:config', context_system::instance())) {
                $link = html_writer::link(
                    new moodle_url('/admin/settings.php', ['section' => 'blocksettingaipdfassistant']),
                    get_string('opensettings', 'block_aipdfassistant')
                );
                $message = get_string('notconfigured_admin', 'block_aipdfassistant') . ' ' . $link;
            }
            $this->content->text = html_writer::div($message, 'alert alert-info mb-0');
            return $this->content;
        }

        $maxbytes = min($config->max_file_bytes(), get_max_upload_file_size($this->page->course->maxbytes ?? 0));
        $maxmb = $maxbytes >= 10 * 1048576 ? (string) round($maxbytes / 1048576) : (string) round($maxbytes / 1048576, 1);
        $courseid = (int) $this->page->course->id;
        $uilang = strtolower(explode('_', (string) current_language())[0]);
        $canspeak = $config->tts_enabled() && has_capability('block/aipdfassistant:usespeech', $this->context);

        $languages = [];
        foreach (intent::LANGUAGES as $code => $name) {
            if ($code !== 'darija') {
                $languages[] = ['code' => $code, 'name' => $this->native_language_name($code)];
            }
        }
        $strings = [];
        foreach (self::JS_STRINGS as $key) {
            $a = in_array($key, self::JS_PLACEHOLDER_STRINGS, true) ? '%a%' : null;
            $strings[$key] = get_string('js_' . $key, 'block_aipdfassistant', $a);
        }

        $uniqid = 'bpa-' . $this->instance->id;
        $templatecontext = [
            'uniqid' => $uniqid,
            'title' => $this->title,
            'maxmb' => $maxmb,
            'canspeak' => $canspeak,
            'languages' => array_map(function ($lang) use ($uilang) {
                return $lang + ['selected' => $lang['code'] === ($uilang === 'ar' ? 'fr' : 'ar')];
            }, $languages),
        ];
        $this->content->text = $OUTPUT->render_from_template('block_aipdfassistant/chat', $templatecontext);

        $this->page->requires->js_call_amd('block_aipdfassistant/chat', 'init', [[
            'uniqid' => $uniqid,
            'contextid' => (int) $this->context->id,
            'courseid' => $courseid,
            'maxbytes' => (int) $maxbytes,
            'maxmb' => $maxmb,
            'uploadurl' => (new moodle_url('/blocks/aipdfassistant/upload.php'))->out(false),
            'canspeak' => $canspeak,
            'browsertts' => $canspeak && $config->tts_is_browser(),
            'gender' => $config->string('tts_gender') === 'male' ? 'male' : 'female',
            'allowcourse' => (bool) $config->int('allowcoursepdfs') && $courseid != SITEID,
            'sesskey' => sesskey(),
            'lang' => $uilang,
            'strings' => $strings,
        ]]);
        return $this->content;
    }

    /**
     * Language name written in the language itself.
     *
     * @param string $code Language code.
     * @return string
     */
    private function native_language_name(string $code): string {
        $names = [
            'ar' => 'العربية', 'fr' => 'Français', 'en' => 'English', 'es' => 'Español', 'de' => 'Deutsch',
            'it' => 'Italiano', 'tr' => 'Türkçe', 'ru' => 'Русский', 'zh' => '中文',
        ];
        return $names[$code] ?? $code;
    }
}
