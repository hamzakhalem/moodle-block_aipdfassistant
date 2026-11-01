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
 * Finds PDF and Word files published as File resources in a course.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_files {
    /** Maximum number of files listed. */
    const LIMIT = 100;

    /** MIME types the assistant can read. */
    const MIMETYPES = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    /**
     * PDF or Word file attached to a File resource, if any.
     *
     * @param \cm_info $cm Course module.
     * @return \stored_file|null
     */
    public static function pdf_of_cm(\cm_info $cm): ?\stored_file {
        $files = get_file_storage()->get_area_files($cm->context->id, 'mod_resource', 'content', 0,
            'sortorder DESC, id ASC', false);
        foreach ($files as $file) {
            if (in_array($file->get_mimetype(), self::MIMETYPES, true)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * PDF resources the current user can see in a course.
     *
     * @param int $courseid Course id.
     * @return array List of arrays with cmid, name, section, filename, filesize.
     */
    public static function list_pdfs(int $courseid): array {
        if ($courseid == SITEID) {
            return [];
        }
        $modinfo = get_fast_modinfo($courseid);
        $out = [];
        foreach ($modinfo->get_instances_of('resource') as $cm) {
            if (!$cm->uservisible || !has_capability('mod/resource:view', $cm->context)) {
                continue;
            }
            $file = self::pdf_of_cm($cm);
            if (!$file) {
                continue;
            }
            $out[] = [
                'cmid' => (int) $cm->id,
                'name' => format_string($cm->name, true, ['context' => $cm->context]),
                'section' => (string) get_section_name($courseid, $cm->sectionnum),
                'filename' => $file->get_filename(),
                'filesize' => (int) $file->get_filesize(),
            ];
            if (count($out) >= self::LIMIT) {
                break;
            }
        }
        return $out;
    }

    /**
     * Resolve a visible File resource of a course.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @return array [\cm_info, \stored_file]
     * @throws exception docmissing when the file is not available to the user.
     */
    public static function resolve(int $courseid, int $cmid): array {
        $modinfo = get_fast_modinfo($courseid);
        $cms = $modinfo->get_instances_of('resource');
        if (!isset($cms[$cmid])) {
            throw new exception('docmissing');
        }
        $cm = $cms[$cmid];
        if (!$cm->uservisible || !has_capability('mod/resource:view', $cm->context)) {
            throw new exception('docmissing');
        }
        $file = self::pdf_of_cm($cm);
        if (!$file) {
            throw new exception('docmissing');
        }
        return [$cm, $file];
    }
}
