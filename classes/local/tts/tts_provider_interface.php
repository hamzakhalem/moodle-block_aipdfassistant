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


namespace block_aipdfassistant\local\tts;

/**
 * Contract implemented by every text-to-speech provider.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface tts_provider_interface {
    /**
     * Convert a short text into speech.
     *
     * @param string $text Plain text, not longer than {@see max_chars()}.
     * @param string $voice Provider specific voice id.
     * @return audio
     * @throws \block_aipdfassistant\local\exception On failure.
     */
    public function synthesize(string $text, string $voice): audio;

    /**
     * Maximum characters accepted per request.
     *
     * @return int
     */
    public function max_chars(): int;

    /**
     * Identifier for logs and cache keys.
     *
     * @return string
     */
    public function name(): string;
}
