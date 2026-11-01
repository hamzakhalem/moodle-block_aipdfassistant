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
 * Plugin exception carrying a machine readable error code.
 *
 * The code maps to the lang string "error_<code>" so the UI can show a
 * translated, actionable message without leaking provider internals.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exception extends \Exception {
    /** @var string Machine readable error code. */
    public $errorcode;

    /** @var int HTTP status returned by the remote API (0 when not applicable). */
    public $httpstatus;

    /** @var string Extra technical detail (never shown to students). */
    public $detail;

    /**
     * Constructor.
     *
     * @param string $errorcode Short code such as "auth", "ratelimit" or "network".
     * @param string $detail Technical detail for admins and logs.
     * @param int $httpstatus Remote HTTP status code, if any.
     */
    public function __construct(string $errorcode, string $detail = '', int $httpstatus = 0) {
        parent::__construct($errorcode . ($detail !== '' ? ': ' . $detail : ''));
        $this->errorcode = $errorcode;
        $this->detail = $detail;
        $this->httpstatus = $httpstatus;
    }
}
