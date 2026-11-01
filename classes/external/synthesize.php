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


namespace block_aipdfassistant\external;

use block_aipdfassistant\local\access;
use block_aipdfassistant\local\config;
use block_aipdfassistant\local\errors;
use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\rate_limiter;
use block_aipdfassistant\local\speech_service;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: read a message aloud.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class synthesize extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Block context id'),
            'text' => new external_value(PARAM_RAW, 'Markdown text to read'),
        ]);
    }

    /**
     * Generate speech.
     *
     * @param int $contextid Block context id.
     * @param string $text Text.
     * @return array
     */
    public static function execute(int $contextid, string $text): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['contextid' => $contextid, 'text' => $text]);
        $context = access::require_block($params['contextid']);
        $isadmin = has_capability('moodle/site:config', \context_system::instance());
        $out = ['success' => false, 'error' => '', 'audiourl' => '', 'audiomime' => '', 'browsertts' => false];
        $limiter = new rate_limiter();
        try {
            require_capability('block/aipdfassistant:usespeech', $context);
            $config = config::load();
            $wait = $limiter->check((int) $USER->id, $config->int('limit_hour'), $config->int('limit_day'));
            if ($wait !== null) {
                throw new exception('ratelimit', (string) $wait);
            }
            $speech = (new speech_service($config))->render(\core_text::substr((string) $params['text'], 0, 20000),
                (int) $context->id, (int) $USER->id);
            if (!$speech['cached'] && !$speech['browsertts']) {
                $limiter->log((int) $USER->id, access::course_id($context), 0, 'tts', $speech['provider'], 1, 0, 0, 'ok');
            }
            $out = ['success' => true, 'error' => '', 'audiourl' => $speech['audiourl'], 'audiomime' => $speech['audiomime'],
                'browsertts' => $speech['browsertts']];
        } catch (\Throwable $e) {
            $out['error'] = errors::message($e, $isadmin);
        }
        return $out;
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether audio is available'),
            'error' => new external_value(PARAM_RAW, 'Localised error message'),
            'audiourl' => new external_value(PARAM_RAW, 'Audio URL'),
            'audiomime' => new external_value(PARAM_RAW, 'Audio MIME type'),
            'browsertts' => new external_value(PARAM_BOOL, 'Use the browser voice'),
        ]);
    }
}
