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
 * Web service functions of block_aipdfassistant (AJAX only, not exposed to the mobile app).
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_aipdfassistant_send_message' => [
        'classname' => 'block_aipdfassistant\external\send_message',
        'description' => 'Send a message (optionally about a PDF) to the study assistant.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/aipdfassistant:use',
        'services' => [],
    ],
    'block_aipdfassistant_synthesize' => [
        'classname' => 'block_aipdfassistant\external\synthesize',
        'description' => 'Read an answer aloud.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/aipdfassistant:usespeech',
        'services' => [],
    ],
    'block_aipdfassistant_list_documents' => [
        'classname' => 'block_aipdfassistant\external\list_documents',
        'description' => 'List recent documents and course PDFs the user can attach.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/aipdfassistant:use',
        'services' => [],
    ],
    'block_aipdfassistant_attach_course_pdf' => [
        'classname' => 'block_aipdfassistant\external\attach_course_pdf',
        'description' => 'Use a PDF resource of the course as the working document.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/aipdfassistant:use',
        'services' => [],
    ],
    'block_aipdfassistant_delete_document' => [
        'classname' => 'block_aipdfassistant\external\delete_document',
        'description' => 'Remove one of the user\'s stored documents.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/aipdfassistant:use',
        'services' => [],
    ],
];
