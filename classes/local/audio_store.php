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

use block_aipdfassistant\local\tts\audio;

/**
 * Stores generated speech in the Moodle file area of the block, one folder per user.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audio_store {
    /** File area name. */
    const AREA = 'audio';

    /**
     * Find a previously generated file.
     *
     * @param int $contextid Block context id.
     * @param int $userid Owner (used as item id).
     * @param string $key Cache key (sha1).
     * @param string $ext File extension.
     * @return \stored_file|null
     */
    public function find(int $contextid, int $userid, string $key, string $ext): ?\stored_file {
        $file = get_file_storage()->get_file($contextid, 'block_aipdfassistant', self::AREA, $userid, '/', $key . '.' . $ext);
        return $file ? $file : null;
    }

    /**
     * Save audio.
     *
     * @param int $contextid Block context id.
     * @param int $userid Owner.
     * @param string $key Cache key.
     * @param audio $audio Audio.
     * @return \stored_file
     */
    public function save(int $contextid, int $userid, string $key, audio $audio): \stored_file {
        $record = [
            'contextid' => $contextid,
            'component' => 'block_aipdfassistant',
            'filearea' => self::AREA,
            'itemid' => $userid,
            'filepath' => '/',
            'filename' => $key . '.' . $audio->ext,
            'userid' => $userid,
        ];
        return get_file_storage()->create_file_from_string($record, $audio->bytes);
    }

    /**
     * URL of a stored file.
     *
     * @param \stored_file $file File.
     * @return string
     */
    public static function url(\stored_file $file): string {
        return \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out(false);
    }

    /**
     * Delete audio files created before a time.
     *
     * @param int $time Unix time.
     * @return int Number of files deleted.
     */
    public function purge_older_than(int $time): int {
        global $DB;
        $ids = $DB->get_fieldset_select('files', 'id',
            "component = ? AND filearea = ? AND filename <> '.' AND timecreated < ?",
            ['block_aipdfassistant', self::AREA, $time]);
        $fs = get_file_storage();
        $deleted = 0;
        foreach ($ids as $id) {
            $file = $fs->get_file_by_id($id);
            if ($file) {
                $file->delete();
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Delete every audio file of a user.
     *
     * @param int $userid Owner.
     * @return void
     */
    public function delete_all_for_user(int $userid): void {
        global $DB;
        $ids = $DB->get_fieldset_select('files', 'id',
            "component = ? AND filearea = ? AND itemid = ? AND filename <> '.'",
            ['block_aipdfassistant', self::AREA, $userid]);
        $fs = get_file_storage();
        foreach ($ids as $id) {
            $file = $fs->get_file_by_id($id);
            if ($file) {
                $file->delete();
            }
        }
    }
}
