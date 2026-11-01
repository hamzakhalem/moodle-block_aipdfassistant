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


namespace block_aipdfassistant\task;

use block_aipdfassistant\local\audio_store;
use block_aipdfassistant\local\config;
use block_aipdfassistant\local\db_cache;
use block_aipdfassistant\local\document_repository;
use block_aipdfassistant\local\rate_limiter;

/**
 * Deletes old documents, audio files, cached answers and log rows.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup', 'block_aipdfassistant');
    }

    /**
     * Run the task.
     *
     * @return void
     */
    public function execute() {
        $config = config::load();
        $days = max(1, $config->int('retentiondays'));
        $cutoff = time() - $days * DAYSECS;
        $docs = (new document_repository())->purge_older_than($cutoff);
        $audio = (new audio_store())->purge_older_than($cutoff);
        (new db_cache($config->int('cachedays')))->purge_expired();
        (new rate_limiter())->purge_older_than(time() - 180 * DAYSECS);
        mtrace('block_aipdfassistant: removed ' . $docs . ' documents and ' . $audio . ' audio files older than ' . $days
            . ' days.');
    }
}
