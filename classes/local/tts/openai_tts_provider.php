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

use block_aipdfassistant\local\ai\helper;
use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\http\client_interface;

/**
 * OpenAI compatible text-to-speech ("/audio/speech"). Works with OpenAI and with
 * self-hosted servers that copy the same endpoint.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class openai_tts_provider implements tts_provider_interface {
    /** @var client_interface HTTP client. */
    private $http;

    /** @var array Resolved settings. */
    private $settings;

    /**
     * Constructor.
     *
     * @param client_interface $http HTTP client.
     * @param array $settings Keys: id, base, model, key, chunk, timeout.
     */
    public function __construct(client_interface $http, array $settings) {
        $this->http = $http;
        $this->settings = $settings;
    }

    /**
     * Maximum characters per request.
     *
     * @return int
     */
    public function max_chars(): int {
        return (int) $this->settings['chunk'];
    }

    /**
     * Identifier.
     *
     * @return string
     */
    public function name(): string {
        return $this->settings['id'] . ':' . $this->settings['model'];
    }

    /**
     * Synthesise speech.
     *
     * @param string $text Plain text.
     * @param string $voice Voice id.
     * @return audio
     */
    public function synthesize(string $text, string $voice): audio {
        $headers = ['Content-Type: application/json', 'Accept: audio/mpeg'];
        if ($this->settings['key'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->settings['key'];
        }
        $body = json_encode([
            'model' => $this->settings['model'],
            'input' => $text,
            'voice' => $voice,
            'response_format' => 'mp3',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = $this->http->post($this->settings['base'] . '/audio/speech', $headers, $body,
            (int) $this->settings['timeout']);
        if (!$response->ok()) {
            throw helper::to_exception($response);
        }
        if ($response->body === '') {
            throw new exception('emptyaudio', '', $response->status);
        }
        return new audio($response->body, 'audio/mpeg', 'mp3');
    }
}
