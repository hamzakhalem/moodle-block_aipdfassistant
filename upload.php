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
 * Receives a PDF from the chat block, extracts its text and stores it for the user.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

use block_aipdfassistant\local\access;
use block_aipdfassistant\local\config;
use block_aipdfassistant\local\document_repository;
use block_aipdfassistant\local\document_service;
use block_aipdfassistant\local\errors;
use block_aipdfassistant\local\exception;

$contextid = required_param('contextid', PARAM_INT);
require_sesskey();
$context = access::require_block($contextid);
$courseid = access::course_id($context);
$isadmin = has_capability('moodle/site:config', context_system::instance());
$config = config::load();

header('Content-Type: application/json; charset=utf-8');
core_php_time_limit::raise(300);

try {
    if (!$config->is_configured()) {
        throw new exception('notconfigured');
    }
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        throw new exception('nofile');
    }
    $upload = $_FILES['file'];
    if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new exception('toolarge');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        throw new exception('nofile');
    }
    $name = clean_param(basename((string) $upload['name']), PARAM_FILE);
    if ($name === '') {
        $name = 'document.pdf';
    }
    $doc = (new document_service($config))->ingest($upload['tmp_name'], $name, (int) $USER->id, $courseid, 'upload');
    echo json_encode(['success' => true, 'document' => document_repository::summary($doc)]);
} catch (Throwable $e) {
    if (!($e instanceof exception)) {
        debugging($e->getMessage() . "\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
    }
    echo json_encode([
        'success' => false,
        'errorcode' => errors::code($e),
        'error' => errors::message($e, $isadmin),
    ]);
}
die();
