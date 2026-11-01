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
 * Builds WAV containers from raw PCM.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wav {
    /**
     * Wrap signed 16-bit little-endian mono PCM in a RIFF/WAVE header.
     *
     * @param string $pcm Raw PCM bytes.
     * @param int $rate Sample rate in Hz.
     * @param int $channels Number of channels.
     * @return string WAV file bytes.
     */
    public static function from_pcm(string $pcm, int $rate = 24000, int $channels = 1): string {
        $bits = 16;
        $byterate = $rate * $channels * $bits / 8;
        $blockalign = $channels * $bits / 8;
        $datalen = strlen($pcm);
        return 'RIFF' . pack('V', 36 + $datalen) . 'WAVE'
            . 'fmt ' . pack('VvvVVvv', 16, 1, $channels, $rate, $byterate, $blockalign, $bits)
            . 'data' . pack('V', $datalen) . $pcm;
    }
}
