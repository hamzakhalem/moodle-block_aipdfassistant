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
 * Translates exceptions into messages safe to show to users.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class errors {
    /**
     * Error code of any throwable.
     *
     * @param \Throwable $e Exception.
     * @return string
     */
    public static function code(\Throwable $e): string {
        if ($e instanceof exception) {
            if ($e->errorcode === 'network' && stripos($e->detail, 'block') !== false) {
                return 'networkblocked';
            }
            return $e->errorcode;
        }
        return 'generic';
    }

    /**
     * Localised message for a throwable. Administrators also see technical detail.
     *
     * @param \Throwable $e Exception.
     * @param bool $isadmin Whether the current user may see technical detail.
     * @return string
     */
    public static function message(\Throwable $e, bool $isadmin = false): string {
        $code = self::code($e);
        $sm = get_string_manager();
        $a = null;
        if ($e instanceof exception && $code === 'ratelimit') {
            $a = max(1, (int) ceil(((int) $e->detail) / 60));
        }
        if (!$sm->string_exists('error_' . $code, 'block_aipdfassistant')) {
            $code = 'generic';
        }
        $message = get_string('error_' . $code, 'block_aipdfassistant', $a);
        if ($isadmin) {
            $detail = $e instanceof exception ? $e->detail : $e->getMessage();
            if ($detail !== '' && $code !== 'ratelimit') {
                $message .= ' [' . \core_text::substr($detail, 0, 300) . ']';
            }
        }
        return $message;
    }
}
