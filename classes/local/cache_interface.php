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
 * Key/value cache for generated answers.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface cache_interface {
    /**
     * Fetch a cached value.
     *
     * @param string $key 40 character key.
     * @return string|null Cached value or null.
     */
    public function get(string $key): ?string;

    /**
     * Store a value.
     *
     * @param string $key 40 character key.
     * @param string $value Value.
     * @param string $action Action name (for reporting).
     * @return void
     */
    public function set(string $key, string $value, string $action = ''): void;
}
