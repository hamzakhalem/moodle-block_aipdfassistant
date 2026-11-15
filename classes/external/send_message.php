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
use block_aipdfassistant\local\document_repository;
use block_aipdfassistant\local\errors;
use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\factory;
use block_aipdfassistant\local\intent;
use block_aipdfassistant\local\rate_limiter;
use block_aipdfassistant\local\request;
use block_aipdfassistant\local\speech_service;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: send a chat message (optionally about a document) to the assistant.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message extends external_api {
    /** Maximum characters of one user message. */
    const MAX_MESSAGE = 4000;

    /** Maximum turns of history accepted. */
    const MAX_HISTORY = 10;

    /** Maximum characters kept per history turn. */
    const MAX_TURN = 6000;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Block context id'),
            'docid' => new external_value(PARAM_INT, 'Document id, 0 for none', VALUE_DEFAULT, 0),
            'message' => new external_value(PARAM_RAW, 'User message', VALUE_DEFAULT, ''),
            'action' => new external_value(PARAM_ALPHA, 'auto, summarize, explain, translate, quiz, filename, speak or ask',
                VALUE_DEFAULT, 'auto'),
            'targetlang' => new external_value(PARAM_ALPHA, 'Target language code for translation', VALUE_DEFAULT, ''),
            'history' => new external_multiple_structure(
                new external_single_structure([
                    'role' => new external_value(PARAM_ALPHA, 'user or assistant'),
                    'content' => new external_value(PARAM_RAW, 'Turn text'),
                ]),
                'Previous turns',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Run the request.
     *
     * @param int $contextid Block context id.
     * @param int $docid Document id.
     * @param string $message Message.
     * @param string $action Action chosen with a button, or "auto".
     * @param string $targetlang Language chosen in the UI.
     * @param array $history Previous turns.
     * @return array
     */
    public static function execute(int $contextid, int $docid = 0, string $message = '', string $action = 'auto',
            string $targetlang = '', array $history = []): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid, 'docid' => $docid, 'message' => $message, 'action' => $action,
            'targetlang' => $targetlang, 'history' => $history,
        ]);
        $context = access::require_block($params['contextid']);
        $userid = (int) $USER->id;
        $courseid = access::course_id($context);
        $isadmin = has_capability('moodle/site:config', \context_system::instance());
        $config = config::load();
        $limiter = new rate_limiter();
        $out = self::blank();

        $text = trim(\core_text::substr((string) $params['message'], 0, self::MAX_MESSAGE));
        $intent = intent::detect($text, (string) $params['action'], (string) $params['targetlang']);
        $out['action'] = $intent->action;
        $out['speak'] = $intent->speak;
        $docidused = (int) $params['docid'];

        try {
            if (!$config->is_configured()) {
                throw new exception('notconfigured');
            }
            $repository = new document_repository();
            $doc = null;
            if ($docidused > 0) {
                $doc = $repository->get_for_user($docidused, $userid);
                if (!$doc) {
                    throw new exception('docmissing');
                }
            }
            $cleanhistory = self::clean_history($params['history']);
            $speaktext = $intent->action === intent::SPEAK ? self::last_assistant($cleanhistory) : '';

            if (($intent->needs_document() || ($intent->action === intent::SPEAK && $speaktext === '')) && !$doc) {
                $out['success'] = true;
                $out['docneeded'] = true;
                $out['reply'] = get_string('needdoc', 'block_aipdfassistant',
                    get_string('action_' . $intent->action, 'block_aipdfassistant'));
                return $out;
            }
            if ($intent->action === intent::FILENAME) {
                $out['success'] = true;
                $out['reply'] = get_string('filenamereply', 'block_aipdfassistant',
                    (object) ['name' => $doc->filename, 'pages' => (int) $doc->pages]);
                $limiter->log($userid, $courseid, (int) $doc->id, 'filename', 'local', 0, 0, 0, 'ok', '', true);
                return $out;
            }

            $wait = $limiter->check($userid, $config->int('limit_hour'), $config->int('limit_day'));
            if ($wait !== null) {
                throw new exception('ratelimit', (string) $wait);
            }

            $reply = '';
            $provider = '';
            if ($speaktext !== '') {
                $reply = get_string('speaklast', 'block_aipdfassistant');
                $out['action'] = intent::SPEAK;
            } else {
                $req = new request($intent, $text);
                $req->history = $cleanhistory;
                $req->uilang = self::ui_lang();
                $req->userid = $userid;
                if ($doc) {
                    $req->docname = (string) $doc->filename;
                    $req->doctext = (string) $doc->textcontent;
                    $req->docpages = (int) $doc->pages;
                    $req->dochash = (string) $doc->contenthash;
                    $repository->touch((int) $doc->id);
                }
                $result = factory::assistant($config)->handle($req);
                $reply = $result->text;
                $provider = $result->provider;
                $out['action'] = $result->action;
                $out['cached'] = $result->cached;
                $out['notice'] = $result->notice;
                $limiter->log($userid, $courseid, $doc ? (int) $doc->id : 0, $result->action, $provider, $result->calls,
                    $result->inputtokens, $result->outputtokens, $result->cached ? 'cached' : 'ok', '', $result->cached);
            }
            $out['reply'] = $reply;
            $out['success'] = true;

            if ($intent->speak) {
                self::attach_speech($out, $config, $context, $userid, $courseid, $doc ? (int) $doc->id : 0, $limiter,
                    $speaktext !== '' ? $speaktext : $reply, $isadmin);
            }
        } catch (\Throwable $e) {
            $out['success'] = false;
            $out['errorcode'] = errors::code($e);
            $out['error'] = errors::message($e, $isadmin);
            if (!($e instanceof exception)) {
                debugging($e->getMessage() . "\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
            }
            if (!in_array($out['errorcode'], ['notconfigured', 'ratelimit', 'docmissing'], true)) {
                $limiter->log($userid, $courseid, $docidused, $intent->action, $config->string('provider'), 0, 0, 0, 'error',
                    $out['errorcode']);
            }
        }
        return $out;
    }

    /**
     * Add speech data to the response; failures only add a message, the text stays.
     *
     * @param array $out Response (modified).
     * @param config $config Configuration.
     * @param \context $context Block context.
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param int $docid Document id.
     * @param rate_limiter $limiter Rate limiter.
     * @param string $markdown Text to read.
     * @param bool $isadmin Whether technical detail may be shown.
     * @return void
     */
    private static function attach_speech(array &$out, config $config, \context $context, int $userid, int $courseid,
            int $docid, rate_limiter $limiter, string $markdown, bool $isadmin): void {
        try {
            if (!has_capability('block/aipdfassistant:usespeech', $context)) {
                throw new exception('ttsdisabled');
            }
            $speech = (new speech_service($config))->render($markdown, (int) $context->id, $userid);
            $out['audiourl'] = $speech['audiourl'];
            $out['audiomime'] = $speech['audiomime'];
            $out['browsertts'] = $speech['browsertts'];
            if (!$speech['cached'] && !$speech['browsertts']) {
                $limiter->log($userid, $courseid, $docid, 'tts', $speech['provider'], 1, 0, 0, 'ok');
            }
        } catch (\Throwable $e) {
            $out['speecherror'] = errors::message($e, $isadmin);
        }
    }

    /**
     * Keep valid turns only, cap their number and size.
     *
     * @param array $history Raw history.
     * @return array
     */
    public static function clean_history(array $history): array {
        $clean = [];
        foreach ($history as $turn) {
            $role = $turn['role'] ?? '';
            $content = trim((string) ($turn['content'] ?? ''));
            if (($role === 'user' || $role === 'assistant') && $content !== '') {
                $clean[] = ['role' => $role, 'content' => \core_text::substr($content, 0, self::MAX_TURN)];
            }
        }
        return array_slice($clean, -self::MAX_HISTORY);
    }

    /**
     * Last assistant turn of a history.
     *
     * @param array $history Clean history.
     * @return string
     */
    public static function last_assistant(array $history): string {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($history[$i]['role'] === 'assistant') {
                return $history[$i]['content'];
            }
        }
        return '';
    }

    /**
     * Interface language mapped to a supported code.
     *
     * @return string
     */
    private static function ui_lang(): string {
        $code = strtolower(explode('_', (string) current_language())[0]);
        return isset(intent::LANGUAGES[$code]) ? $code : 'en';
    }

    /**
     * Empty response.
     *
     * @return array
     */
    private static function blank(): array {
        return [
            'success' => false, 'errorcode' => '', 'error' => '', 'reply' => '', 'action' => '', 'speak' => false,
            'cached' => false, 'notice' => '', 'docneeded' => false, 'audiourl' => '', 'audiomime' => '',
            'browsertts' => false, 'speecherror' => '',
        ];
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether an answer was produced'),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT, 'Error code'),
            'error' => new external_value(PARAM_RAW, 'Localised error message'),
            'reply' => new external_value(PARAM_RAW, 'Answer in Markdown'),
            'action' => new external_value(PARAM_ALPHA, 'Action performed'),
            'speak' => new external_value(PARAM_BOOL, 'The user asked for voice output'),
            'cached' => new external_value(PARAM_BOOL, 'Answer came from the cache'),
            'notice' => new external_value(PARAM_ALPHA, 'UI notice code'),
            'docneeded' => new external_value(PARAM_BOOL, 'A document must be attached first'),
            'audiourl' => new external_value(PARAM_RAW, 'URL of generated audio'),
            'audiomime' => new external_value(PARAM_RAW, 'MIME type of the audio'),
            'browsertts' => new external_value(PARAM_BOOL, 'Read the answer with the browser voice'),
            'speecherror' => new external_value(PARAM_RAW, 'Why speech could not be produced'),
        ]);
    }
}
