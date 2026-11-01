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
 * Immutable HTTP response value object.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class response {
    /** @var int HTTP status code (0 when the request never reached the server). */
    public $status;

    /** @var string Raw response body (may be binary). */
    public $body;

    /** @var string Transport error message, if any. */
    public $error;

    /**
     * Constructor.
     *
     * @param int $status HTTP status.
     * @param string $body Body.
     * @param string $error Transport error.
     */
    public function __construct(int $status, string $body, string $error = '') {
        $this->status = $status;
        $this->body = $body;
        $this->error = $error;
    }

    /**
     * Whether the status is 2xx.
     *
     * @return bool
     */
    public function ok(): bool {
        return $this->status >= 200 && $this->status < 300;
    }
}
