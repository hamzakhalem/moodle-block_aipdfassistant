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
 * Database backed answer cache (table block_aipdfassistant_cache).
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class db_cache implements cache_interface {
    /** @var int Time to live in seconds (0 = never expires). */
    private $ttl;

    /**
     * Constructor.
     *
     * @param int $ttldays Days an entry stays valid.
     */
    public function __construct(int $ttldays = 30) {
        $this->ttl = max(0, $ttldays) * DAYSECS;
    }

    /**
     * Fetch a cached value.
     *
     * @param string $key Key.
     * @return string|null
     */
    public function get(string $key): ?string {
        global $DB;
        $record = $DB->get_record('block_aipdfassistant_cache', ['cachekey' => $key], 'id, response, timecreated');
        if (!$record) {
            return null;
        }
        if ($this->ttl > 0 && $record->timecreated < time() - $this->ttl) {
            return null;
        }
        return (string) $record->response;
    }

    /**
     * Store a value (replacing any previous one).
     *
     * @param string $key Key.
     * @param string $value Value.
     * @param string $action Action name.
     * @return void
     */
    public function set(string $key, string $value, string $action = ''): void {
        global $DB;
        $existing = $DB->get_record('block_aipdfassistant_cache', ['cachekey' => $key], 'id');
        $record = (object) [
            'cachekey' => $key,
            'action' => substr($action, 0, 20),
            'response' => $value,
            'timecreated' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('block_aipdfassistant_cache', $record);
        } else {
            $DB->insert_record('block_aipdfassistant_cache', $record);
        }
    }

    /**
     * Delete entries older than the time to live.
     *
     * @return void
     */
    public function purge_expired(): void {
        global $DB;
        if ($this->ttl > 0) {
            $DB->delete_records_select('block_aipdfassistant_cache', 'timecreated < ?', [time() - $this->ttl]);
        }
    }
}
