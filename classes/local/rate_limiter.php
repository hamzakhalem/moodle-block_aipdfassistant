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
 * Usage log and per-user rate limits (table block_aipdfassistant_log).
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {
    /** Table name. */
    const TABLE = 'block_aipdfassistant_log';

    /**
     * Check the user's limits.
     *
     * @param int $userid User id.
     * @param int $limithour Maximum billable requests per hour (0 = unlimited).
     * @param int $limitday Maximum billable requests per day (0 = unlimited).
     * @param int|null $now Current time (for tests).
     * @return int|null Seconds to wait, or null when the request may proceed.
     */
    public function check(int $userid, int $limithour, int $limitday, ?int $now = null): ?int {
        $now = $now ?? time();
        $wait = 0;
        foreach ([[$limithour, HOURSECS], [$limitday, DAYSECS]] as [$limit, $window]) {
            if ($limit <= 0) {
                continue;
            }
            $since = $now - $window;
            $count = $this->count($userid, $since);
            if ($count >= $limit) {
                $oldest = $this->oldest($userid, $since);
                $wait = max($wait, max(1, ($oldest + $window) - $now));
            }
        }
        return $wait > 0 ? $wait : null;
    }

    /**
     * Number of billable (non cached) requests since a time.
     *
     * @param int $userid User id.
     * @param int $since Unix time.
     * @return int
     */
    public function count(int $userid, int $since): int {
        global $DB;
        return $DB->count_records_select(self::TABLE, 'userid = ? AND timecreated > ? AND cached = 0',
            [$userid, $since]);
    }

    /**
     * Time of the oldest billable request since a time.
     *
     * @param int $userid User id.
     * @param int $since Unix time.
     * @return int
     */
    private function oldest(int $userid, int $since): int {
        global $DB;
        $rows = $DB->get_records_select(self::TABLE, 'userid = ? AND timecreated > ? AND cached = 0', [$userid, $since],
            'timecreated ASC', 'id, timecreated', 0, 1);
        $row = $rows ? reset($rows) : null;
        return $row ? (int) $row->timecreated : $since;
    }

    /**
     * Record one request.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param int $docid Document id (0 if none).
     * @param string $action Action name ("tts" for speech).
     * @param string $provider Provider identifier.
     * @param int $calls Provider calls made.
     * @param int $inputtokens Input tokens.
     * @param int $outputtokens Output tokens.
     * @param string $status "ok", "error" or "cached".
     * @param string $errorcode Error code for failures.
     * @param bool $cached Whether the answer came from cache.
     * @return void
     */
    public function log(int $userid, int $courseid, int $docid, string $action, string $provider, int $calls,
            int $inputtokens, int $outputtokens, string $status, string $errorcode = '', bool $cached = false): void {
        global $DB;
        $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'docid' => $docid,
            'action' => substr($action, 0, 20),
            'provider' => substr($provider, 0, 100),
            'calls' => $calls,
            'inputtokens' => $inputtokens,
            'outputtokens' => $outputtokens,
            'status' => substr($status, 0, 12),
            'errorcode' => substr($errorcode, 0, 30),
            'cached' => $cached ? 1 : 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * Delete log rows older than a time.
     *
     * @param int $time Unix time.
     * @return void
     */
    public function purge_older_than(int $time): void {
        global $DB;
        $DB->delete_records_select(self::TABLE, 'timecreated < ?', [$time]);
    }
}
