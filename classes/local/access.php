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
 * Context and capability checks shared by web services and upload.php.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /**
     * Validate that a context id is an instance of this block and that the current
     * user may use it.
     *
     * @param int $contextid Block context id.
     * @return \context_block
     * @throws \moodle_exception When the context is not a block instance of this plugin.
     */
    public static function require_block(int $contextid): \context_block {
        global $DB;
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context || $context->contextlevel != CONTEXT_BLOCK) {
            throw new \moodle_exception('invalidcontext');
        }
        $blockname = $DB->get_field('block_instances', 'blockname', ['id' => $context->instanceid]);
        if ($blockname !== 'aipdfassistant') {
            throw new \moodle_exception('invalidcontext');
        }
        \core_external\external_api::validate_context($context);
        require_capability('block/aipdfassistant:use', $context);
        return $context;
    }

    /**
     * Course id of a block context (SITEID for dashboard and front page blocks).
     *
     * @param \context $context Block context.
     * @return int
     */
    public static function course_id(\context $context): int {
        $coursecontext = $context->get_course_context(false);
        return $coursecontext ? (int) $coursecontext->instanceid : (int) SITEID;
    }
}
