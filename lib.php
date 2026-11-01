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
 * Library functions of block_aipdfassistant.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve generated audio files. Every user can only fetch their own audio.
 *
 * @param stdClass $course Course (or site) the block is in.
 * @param stdClass|cm_info $birecordorcm Block instance record.
 * @param context $context Block context.
 * @param string $filearea File area.
 * @param array $args Remaining path arguments: itemid, [path], filename.
 * @param bool $forcedownload Whether to force a download.
 * @param array $options Additional options.
 * @return void Sends the file or a 404 and exits.
 */
function block_aipdfassistant_pluginfile($course, $birecordorcm, $context, $filearea, $args, $forcedownload,
        array $options = []) {
    global $USER;
    if ($context->contextlevel != CONTEXT_BLOCK || $filearea !== 'audio') {
        send_file_not_found();
    }
    require_login($course);
    require_capability('block/aipdfassistant:use', $context);

    $itemid = (int) array_shift($args);
    if ($itemid !== (int) $USER->id) {
        send_file_not_found();
    }
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'block_aipdfassistant', 'audio', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }
    send_stored_file($file, DAYSECS, 0, false, $options);
}
