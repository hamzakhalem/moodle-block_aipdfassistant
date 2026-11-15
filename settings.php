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
 * Administration settings of block_aipdfassistant.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $plugin = 'block_aipdfassistant';
    $str = function (string $id, $a = null) use ($plugin) {
        return get_string($id, $plugin, $a);
    };

    $tools = (object) [
        'test' => (new moodle_url('/blocks/aipdfassistant/admin_test.php'))->out(false),
        'report' => (new moodle_url('/blocks/aipdfassistant/report.php'))->out(false),
    ];
    $settings->add(new admin_setting_heading("$plugin/gettingstarted", $str('settings_gettingstarted'),
        $str('settings_gettingstarted_desc')));
    $settings->add(new admin_setting_heading("$plugin/tools", $str('settings_tools'), $str('link_tools_desc', $tools)));

    // Text provider.
    $settings->add(new admin_setting_heading("$plugin/texthead", $str('settings_text'), $str('settings_text_desc')));
    $settings->add(new admin_setting_configselect("$plugin/provider", $str('setting_provider'), $str('setting_provider_desc'),
        'openai', \block_aipdfassistant\local\ai\registry::text_choices()));
    $settings->add(new admin_setting_configpasswordunmask("$plugin/apikey", $str('setting_apikey'),
        $str('setting_apikey_desc'), ''));
    $settings->add(new admin_setting_configtext("$plugin/model", $str('setting_model'), $str('setting_model_desc'), '',
        PARAM_RAW_TRIMMED));
    $settings->add(new admin_setting_configtext("$plugin/baseurl", $str('setting_baseurl'), $str('setting_baseurl_desc'), '',
        PARAM_RAW_TRIMMED));
    $settings->add(new admin_setting_configtext("$plugin/temperature", $str('setting_temperature'),
        $str('setting_temperature_desc'), '0.3', PARAM_FLOAT, 5));
    $settings->add(new admin_setting_configtext("$plugin/maxtokens", $str('setting_maxtokens'),
        $str('setting_maxtokens_desc'), '2000', PARAM_INT, 6));
    $settings->add(new admin_setting_configtext("$plugin/timeout", $str('setting_timeout'),
        $str('setting_timeout_desc'), '120', PARAM_INT, 5));
    $settings->add(new admin_setting_configtext("$plugin/maxcontextchars", $str('setting_maxcontextchars'),
        $str('setting_maxcontextchars_desc'), '48000', PARAM_INT, 8));
    $settings->add(new admin_setting_configtext("$plugin/maxchunks", $str('setting_maxchunks'),
        $str('setting_maxchunks_desc'), '10', PARAM_INT, 4));

    // Voice.
    $settings->add(new admin_setting_heading("$plugin/voicehead", $str('settings_voice'), $str('settings_voice_desc')));
    $settings->add(new admin_setting_configselect("$plugin/tts_provider", $str('setting_tts_provider'),
        $str('setting_tts_provider_desc'), 'none', [
            'none' => $str('tts_none'),
            'browser' => $str('tts_browser'),
            'openai' => $str('tts_openai'),
            'gemini' => $str('tts_gemini'),
            'elevenlabs' => $str('tts_elevenlabs'),
            'custom' => $str('tts_custom'),
        ]));
    $settings->add(new admin_setting_configselect("$plugin/tts_gender", $str('setting_tts_gender'),
        $str('setting_tts_gender_desc'), 'female', ['female' => $str('gender_female'), 'male' => $str('gender_male')]));
    $settings->add(new admin_setting_configpasswordunmask("$plugin/tts_apikey", $str('setting_tts_apikey'),
        $str('setting_tts_apikey_desc'), ''));
    $settings->add(new admin_setting_configtext("$plugin/tts_model", $str('setting_tts_model'),
        $str('setting_tts_model_desc'), '', PARAM_RAW_TRIMMED));
    $settings->add(new admin_setting_configtext("$plugin/tts_baseurl", $str('setting_tts_baseurl'),
        $str('setting_tts_baseurl_desc'), '', PARAM_RAW_TRIMMED));
    $settings->add(new admin_setting_configtext("$plugin/tts_voice_male", $str('setting_tts_voice_male'),
        $str('setting_tts_voice_male_desc'), '', PARAM_RAW_TRIMMED));
    $settings->add(new admin_setting_configtext("$plugin/tts_voice_female", $str('setting_tts_voice_female'),
        $str('setting_tts_voice_female_desc'), '', PARAM_RAW_TRIMMED));
    $settings->add(new admin_setting_configtext("$plugin/tts_maxchars", $str('setting_tts_maxchars'),
        $str('setting_tts_maxchars_desc'), '9000', PARAM_INT, 6));

    // Documents.
    $settings->add(new admin_setting_heading("$plugin/docshead", $str('settings_docs'), ''));
    $settings->add(new admin_setting_configtext("$plugin/maxfilesize", $str('setting_maxfilesize'),
        $str('setting_maxfilesize_desc'), '20', PARAM_INT, 4));
    $settings->add(new admin_setting_configtext("$plugin/maxdocchars", $str('setting_maxdocchars'),
        $str('setting_maxdocchars_desc'), '600000', PARAM_INT, 8));
    $settings->add(new admin_setting_configtext("$plugin/pdftotextpath", $str('setting_pdftotextpath'),
        $str('setting_pdftotextpath_desc'), 'pdftotext', PARAM_RAW_TRIMMED));
    $settings->add(new admin_setting_configtext("$plugin/extracttimeout", $str('setting_extracttimeout'),
        $str('setting_extracttimeout_desc'), '30', PARAM_INT, 4));
    $settings->add(new admin_setting_configtext("$plugin/maxdecompressedmb", $str('setting_maxdecompressedmb'),
        $str('setting_maxdecompressedmb_desc'), '128', PARAM_INT, 5));
    $settings->add(new admin_setting_configtext("$plugin/retentiondays", $str('setting_retentiondays'),
        $str('setting_retentiondays_desc'), '30', PARAM_INT, 4));
    $settings->add(new admin_setting_configcheckbox("$plugin/allowcoursepdfs", $str('setting_allowcoursepdfs'),
        $str('setting_allowcoursepdfs_desc'), 1));

    // Behaviour.
    $settings->add(new admin_setting_heading("$plugin/behaviourhead", $str('settings_behaviour'), ''));
    $languages = ['auto' => $str('lang_auto')];
    foreach (\block_aipdfassistant\local\intent::LANGUAGES as $code => $name) {
        $languages[$code] = $name;
    }
    $settings->add(new admin_setting_configselect("$plugin/responselang", $str('setting_responselang'),
        $str('setting_responselang_desc'), 'auto', $languages));
    $settings->add(new admin_setting_configtextarea("$plugin/systemextra", $str('setting_systemextra'),
        $str('setting_systemextra_desc'), '', PARAM_TEXT, 60, 4));
    $settings->add(new admin_setting_configtext("$plugin/limit_hour", $str('setting_limit_hour'),
        $str('setting_limit_hour_desc'), '30', PARAM_INT, 4));
    $settings->add(new admin_setting_configtext("$plugin/limit_day", $str('setting_limit_day'),
        $str('setting_limit_day_desc'), '150', PARAM_INT, 4));
    $settings->add(new admin_setting_configtext("$plugin/cachedays", $str('setting_cachedays'),
        $str('setting_cachedays_desc'), '30', PARAM_INT, 4));
}
