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
use block_aipdfassistant\local\course_files;
use block_aipdfassistant\local\document_repository;
use block_aipdfassistant\local\document_service;
use block_aipdfassistant\local\errors;
use block_aipdfassistant\local\exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: use a PDF resource of the course as the working document.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attach_course_pdf extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Block context id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id of the File resource'),
        ]);
    }

    /**
     * Attach a course PDF.
     *
     * @param int $contextid Block context id.
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $contextid, int $cmid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['contextid' => $contextid, 'cmid' => $cmid]);
        $context = access::require_block($params['contextid']);
        $courseid = access::course_id($context);
        $isadmin = has_capability('moodle/site:config', \context_system::instance());
        $out = ['success' => false, 'error' => '', 'document' => null];
        try {
            $config = config::load();
            if (!$config->int('allowcoursepdfs')) {
                throw new exception('docmissing');
            }
            [, $file] = course_files::resolve($courseid, (int) $params['cmid']);
            if ($file->get_filesize() > $config->max_file_bytes()) {
                throw new exception('toolarge');
            }
            $path = $file->copy_content_to_temp();
            if (!$path) {
                throw new exception('unreadable');
            }
            try {
                $doc = (new document_service($config))->ingest($path, $file->get_filename(), (int) $USER->id, $courseid,
                    'course', $file->get_contenthash());
            } finally {
                @unlink($path);
            }
            $out = ['success' => true, 'error' => '', 'document' => document_repository::summary($doc)];
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
            'success' => new external_value(PARAM_BOOL, 'Whether the file was attached'),
            'error' => new external_value(PARAM_RAW, 'Localised error message'),
            'document' => structures::document(VALUE_OPTIONAL),
        ]);
    }
}
