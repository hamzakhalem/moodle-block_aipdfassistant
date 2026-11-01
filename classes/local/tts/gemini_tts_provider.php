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
 * Google Gemini text-to-speech through generateContent. Returns raw 16-bit PCM
 * that the synthesizer wraps in a WAV container.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gemini_tts_provider implements tts_provider_interface {
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
     * @param string $voice Prebuilt voice name, e.g. "Kore".
     * @return audio
     */
    public function synthesize(string $text, string $voice): audio {
        $url = $this->settings['base'] . '/models/' . rawurlencode($this->settings['model']) . ':generateContent';
        $payload = [
            'contents' => [[
                'parts' => [['text' => "Read the following text aloud exactly as written, in its own language:\n\n" . $text]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]],
                ],
            ],
        ];
        $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $this->settings['key']];
        $response = $this->http->post($url, $headers, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (int) $this->settings['timeout']);
        if (!$response->ok()) {
            throw helper::to_exception($response);
        }
        $data = json_decode($response->body, true);
        $inline = $data['candidates'][0]['content']['parts'][0]['inlineData']
            ?? ($data['candidates'][0]['content']['parts'][0]['inline_data'] ?? null);
        if (!is_array($inline) || empty($inline['data'])) {
            throw new exception('emptyaudio', mb_substr($response->body, 0, 300, 'UTF-8'), $response->status);
        }
        $bytes = base64_decode((string) $inline['data'], true);
        if ($bytes === false || $bytes === '') {
            throw new exception('emptyaudio', 'invalid base64', $response->status);
        }
        $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'audio/L16;rate=24000');
        if (stripos($mime, 'mpeg') !== false || stripos($mime, 'mp3') !== false) {
            return new audio($bytes, 'audio/mpeg', 'mp3');
        }
        if (stripos($mime, 'wav') !== false) {
            return new audio($bytes, 'audio/wav', 'wav');
        }
        $rate = 24000;
        if (preg_match('/rate=(\d{4,6})/i', $mime, $m)) {
            $rate = (int) $m[1];
        }
        return new audio($bytes, 'audio/pcm', 'pcm', $rate);
    }
}
