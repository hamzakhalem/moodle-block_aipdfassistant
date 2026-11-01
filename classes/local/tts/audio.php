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
 * Synthesised audio.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class audio {
    /** @var string Binary audio data. */
    public $bytes;

    /** @var string MIME type, e.g. audio/mpeg. "audio/pcm" means raw 16-bit mono PCM. */
    public $mime;

    /** @var string File extension without dot. */
    public $ext;

    /** @var int Sample rate for raw PCM data. */
    public $rate;

    /**
     * Constructor.
     *
     * @param string $bytes Audio bytes.
     * @param string $mime MIME type.
     * @param string $ext File extension.
     * @param int $rate Sample rate (PCM only).
     */
    public function __construct(string $bytes, string $mime, string $ext, int $rate = 0) {
        $this->bytes = $bytes;
        $this->mime = $mime;
        $this->ext = $ext;
        $this->rate = $rate;
    }
}
