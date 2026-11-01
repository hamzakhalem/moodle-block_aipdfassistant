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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * HTTP client built on Moodle's curl wrapper, so the site's proxy settings and
 * the "cURL blocked hosts" security rules apply to every outgoing request.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class moodle_client implements client_interface {
    /**
     * POST a body to a URL.
     *
     * @param string $url Absolute URL.
     * @param string[] $headers Header lines.
     * @param string $body Request body.
     * @param int $timeout Total timeout in seconds.
     * @return response
     */
    public function post(string $url, array $headers, string $body, int $timeout): response {
        $curl = new \curl();
        $curl->setHeader($headers);
        $options = [
            'CURLOPT_TIMEOUT' => max(5, $timeout),
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_RETURNTRANSFER' => true,
        ];
        $result = $curl->post($url, $body, $options);
        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);
        $error = '';
        if ($curl->get_errno() || $result === false) {
            $error = (string) ($curl->error ?? '');
            if ($error === '') {
                $error = 'curl error ' . (int) $curl->get_errno();
            }
        }
        return new response($status, is_string($result) ? $result : '', $error);
    }
}
