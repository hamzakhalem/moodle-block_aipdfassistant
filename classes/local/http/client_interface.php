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


namespace block_aipdfassistant\local\http;

/**
 * Minimal HTTP client contract so providers can be tested without network access.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface client_interface {
    /**
     * POST a body to a URL.
     *
     * @param string $url Absolute URL.
     * @param string[] $headers Header lines such as "Content-Type: application/json".
     * @param string $body Request body.
     * @param int $timeout Total timeout in seconds.
     * @return response The response (never throws for HTTP errors).
     */
    public function post(string $url, array $headers, string $body, int $timeout): response;
}
