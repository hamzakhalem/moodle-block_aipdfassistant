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


namespace block_aipdfassistant\local\ai;

use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\http\response;

/**
 * Shared helpers for chat style providers: message clean-up and HTTP error mapping.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class helper {
    /**
     * Keep only valid user/assistant turns, merge consecutive turns of the same role
     * and make sure the list starts with a user turn.
     *
     * @param array $messages Raw turns.
     * @return array Clean turns.
     */
    public static function normalize_messages(array $messages): array {
        $clean = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? '';
            $content = trim((string) ($message['content'] ?? ''));
            if (($role !== 'user' && $role !== 'assistant') || $content === '') {
                continue;
            }
            $last = count($clean) - 1;
            if ($last >= 0 && $clean[$last]['role'] === $role) {
                $clean[$last]['content'] .= "\n\n" . $content;
            } else {
                $clean[] = ['role' => $role, 'content' => $content];
            }
        }
        while ($clean && $clean[0]['role'] !== 'user') {
            array_shift($clean);
        }
        return $clean;
    }

    /**
     * Pull a human readable error message out of an API error body.
     *
     * @param string $body Raw body.
     * @return string Message or empty string.
     */
    public static function error_message(string $body): string {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return trim(mb_substr(strip_tags($body), 0, 300, 'UTF-8'));
        }
        if (isset($data['error'])) {
            if (is_string($data['error'])) {
                return $data['error'];
            }
            if (is_array($data['error']) && isset($data['error']['message'])) {
                return (string) $data['error']['message'];
            }
        }
        if (isset($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }
        if (isset($data['detail'])) {
            return is_string($data['detail']) ? $data['detail'] : json_encode($data['detail']);
        }
        return '';
    }

    /**
     * Convert a failed HTTP response in a plugin exception.
     *
     * @param response $response Failed response.
     * @return exception
     */
    public static function to_exception(response $response): exception {
        $message = self::error_message($response->body);
        $lower = mb_strtolower($message, 'UTF-8');
        $status = $response->status;
        if ($status === 0) {
            return new exception('network', $response->error, 0);
        }
        if ($status === 401 || $status === 403) {
            return new exception('auth', $message, $status);
        }
        if ($status === 429) {
            return new exception('providerlimit', $message, $status);
        }
        if ($status === 404 && strpos($lower, 'model') !== false) {
            return new exception('badmodel', $message, $status);
        }
        if (($status === 400 || $status === 413) && (strpos($lower, 'context') !== false
                || strpos($lower, 'too long') !== false || strpos($lower, 'too large') !== false
                || strpos($lower, 'maximum') !== false)) {
            return new exception('toolong', $message, $status);
        }
        if ($status >= 500) {
            return new exception('unavailable', $message, $status);
        }
        return new exception('badrequest', $message, $status);
    }

    /**
     * Remove hidden reasoning blocks some local models print.
     *
     * @param string $text Model output.
     * @return string Cleaned output.
     */
    public static function strip_thinking(string $text): string {
        $text = (string) preg_replace('~<think>.*?</think>~s', '', $text);
        return trim($text);
    }
}
