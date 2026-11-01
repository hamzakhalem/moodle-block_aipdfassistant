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

use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\text;

/**
 * Reads long texts aloud: strips Markdown, splits into provider sized chunks,
 * synthesises each one and joins the audio.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class synthesizer {
    /** @var tts_provider_interface Provider. */
    private $provider;

    /** @var string Voice id. */
    private $voice;

    /** @var int Maximum characters read in total. */
    private $maxtotal;

    /**
     * Constructor.
     *
     * @param tts_provider_interface $provider Provider.
     * @param string $voice Voice id.
     * @param int $maxtotal Maximum characters read aloud per request.
     */
    public function __construct(tts_provider_interface $provider, string $voice, int $maxtotal = 9000) {
        $this->provider = $provider;
        $this->voice = $voice;
        $this->maxtotal = max(200, $maxtotal);
    }

    /**
     * Plain text that will actually be spoken for a Markdown message.
     *
     * @param string $markdown Message in Markdown.
     * @return string Plain text, truncated to the configured limit.
     */
    public function prepare(string $markdown): string {
        return text::truncate(text::markdown_to_plain($markdown), $this->maxtotal);
    }

    /**
     * Synthesise a Markdown message.
     *
     * @param string $markdown Message.
     * @return audio Combined audio (MP3 or WAV).
     * @throws exception When there is nothing to read or the provider fails.
     */
    public function synthesize(string $markdown): audio {
        $plain = $this->prepare($markdown);
        if ($plain === '') {
            throw new exception('nothingtoread');
        }
        $chunks = text::split_sentences($plain, $this->provider->max_chars());
        $parts = [];
        foreach ($chunks as $chunk) {
            $parts[] = $this->provider->synthesize($chunk, $this->voice);
        }
        return self::join($parts);
    }

    /**
     * Join audio fragments produced by the same provider.
     *
     * @param audio[] $parts Fragments.
     * @return audio
     */
    public static function join(array $parts): audio {
        if (!$parts) {
            throw new exception('emptyaudio');
        }
        $first = $parts[0];
        if ($first->mime === 'audio/pcm') {
            $pcm = '';
            foreach ($parts as $part) {
                $pcm .= $part->bytes;
            }
            return new audio(wav::from_pcm($pcm, $first->rate ?: 24000), 'audio/wav', 'wav');
        }
        if (count($parts) === 1) {
            return $first;
        }
        $bytes = '';
        foreach ($parts as $part) {
            $bytes .= $part->bytes;
        }
        return new audio($bytes, $first->mime, $first->ext);
    }
}
