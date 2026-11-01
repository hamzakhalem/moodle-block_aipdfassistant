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
 * Storage of extracted document text (table block_aipdfassistant_docs).
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_repository {
    /** Table name. */
    const TABLE = 'block_aipdfassistant_docs';

    /**
     * Store a new document.
     *
     * @param int $userid Owner.
     * @param int $courseid Course the block lives in (SITEID on the dashboard).
     * @param string $filename File name.
     * @param int $filesize Size in bytes.
     * @param string $contenthash SHA-1 of the file content.
     * @param string $text Extracted text.
     * @param int $pages Page count.
     * @param string $source "upload" or "course".
     * @param string $extractor Extractor id.
     * @param bool $lowtext Whether little text was found per page.
     * @return int New id.
     */
    public function create(int $userid, int $courseid, string $filename, int $filesize, string $contenthash, string $text,
            int $pages, string $source, string $extractor, bool $lowtext = false): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'filename' => \core_text::substr($filename, 0, 255),
            'filesize' => $filesize,
            'contenthash' => $contenthash,
            'pages' => $pages,
            'charcount' => \core_text::strlen($text),
            'lowtext' => $lowtext ? 1 : 0,
            'source' => $source,
            'extractor' => $extractor,
            'textcontent' => $text,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Fetch a document the user owns.
     *
     * @param int $id Document id.
     * @param int $userid Owner.
     * @return \stdClass|null
     */
    public function get_for_user(int $id, int $userid): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id, 'userid' => $userid]);
        return $record ? $record : null;
    }

    /**
     * Find a document of the user with the same content.
     *
     * @param int $userid Owner.
     * @param string $hash Content hash.
     * @return \stdClass|null
     */
    public function find_by_hash(int $userid, string $hash): ?\stdClass {
        global $DB;
        $records = $DB->get_records(self::TABLE, ['userid' => $userid, 'contenthash' => $hash], 'id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Find any previously extracted copy of a course file (saves re-reading the PDF).
     *
     * @param string $hash Content hash.
     * @return \stdClass|null
     */
    public function find_course_copy(string $hash): ?\stdClass {
        global $DB;
        $records = $DB->get_records(self::TABLE, ['contenthash' => $hash, 'source' => 'course'], 'id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Mark a document as recently used.
     *
     * @param int $id Document id.
     * @return void
     */
    public function touch(int $id): void {
        global $DB;
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Recent documents of a user in a course (without the text).
     *
     * @param int $userid Owner.
     * @param int $courseid Course id.
     * @param int $limit Maximum rows.
     * @return \stdClass[]
     */
    public function list_recent(int $userid, int $courseid, int $limit = 8): array {
        global $DB;
        $fields = 'id, filename, filesize, pages, charcount, lowtext, source, timemodified';
        return array_values($DB->get_records(self::TABLE, ['userid' => $userid, 'courseid' => $courseid],
            'timemodified DESC', $fields, 0, $limit));
    }

    /**
     * Delete one document of a user.
     *
     * @param int $id Document id.
     * @param int $userid Owner.
     * @return bool Whether a row was deleted.
     */
    public function delete(int $id, int $userid): bool {
        global $DB;
        if (!$DB->record_exists(self::TABLE, ['id' => $id, 'userid' => $userid])) {
            return false;
        }
        $DB->delete_records(self::TABLE, ['id' => $id, 'userid' => $userid]);
        return true;
    }

    /**
     * Delete every document of a user.
     *
     * @param int $userid Owner.
     * @return void
     */
    public function delete_all_for_user(int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Delete documents not used since a point in time.
     *
     * @param int $time Unix time.
     * @return int Number of rows deleted.
     */
    public function purge_older_than(int $time): int {
        global $DB;
        $count = $DB->count_records_select(self::TABLE, 'timemodified < ?', [$time]);
        $DB->delete_records_select(self::TABLE, 'timemodified < ?', [$time]);
        return $count;
    }

    /**
     * Light description for the UI.
     *
     * @param \stdClass $doc Database record.
     * @return array
     */
    public static function summary(\stdClass $doc): array {
        return [
            'id' => (int) $doc->id,
            'name' => (string) $doc->filename,
            'pages' => (int) $doc->pages,
            'chars' => (int) $doc->charcount,
            'lowtext' => !empty($doc->lowtext),
            'source' => (string) $doc->source,
            'timemodified' => (int) $doc->timemodified,
        ];
    }
}
