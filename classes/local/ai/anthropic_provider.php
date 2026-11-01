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
use block_aipdfassistant\local\http\client_interface;
use block_aipdfassistant\local\http\response;

/**
 * Provider for the Anthropic Messages API (Claude).
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class anthropic_provider implements text_provider_interface {
    /** API version header value. */
    const API_VERSION = '2023-06-01';

    /** @var client_interface HTTP client. */
    private $http;

    /** @var array Resolved provider settings. */
    private $settings;

    /**
     * Constructor.
     *
     * @param client_interface $http HTTP client.
     * @param array $settings Keys: id, base, model, key, temp, timeout.
     */
    public function __construct(client_interface $http, array $settings) {
        $this->http = $http;
        $this->settings = $settings;
    }

    /**
     * Identifier for logs and cache keys.
     *
     * @return string
     */
    public function name(): string {
        return $this->settings['id'] . ':' . $this->settings['model'];
    }

    /**
     * Run one completion.
     *
     * @param string $system System prompt.
     * @param array $messages Conversation turns.
     * @param array $options max_tokens, temperature.
     * @return completion
     */
    public function complete(string $system, array $messages, array $options = []): completion {
        $clean = helper::normalize_messages($messages);
        if (!$clean) {
            throw new exception('badrequest', 'No user message to send.');
        }
        $payload = [
            'model' => $this->settings['model'],
            'max_tokens' => (int) ($options['max_tokens'] ?? 2000),
            'system' => $system,
            'messages' => $clean,
        ];
        if (!empty($this->settings['temp'])) {
            $payload['temperature'] = (float) ($options['temperature'] ?? 0.3);
        }
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->settings['key'],
            'anthropic-version: ' . self::API_VERSION,
        ];
        $attempts = 0;
        do {
            $response = $this->http->post(
                $this->settings['base'] . '/v1/messages',
                $headers,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (int) $this->settings['timeout']
            );
            $retry = false;
            if ($response->status === 400 && $attempts < 1 && isset($payload['temperature'])
                    && strpos(strtolower($response->body), 'temperature') !== false) {
                unset($payload['temperature']);
                $retry = true;
            }
            $attempts++;
        } while ($retry);

        return $this->parse($response);
    }

    /**
     * Turn the HTTP response into a completion.
     *
     * @param response $response Response.
     * @return completion
     */
    private function parse(response $response): completion {
        if (!$response->ok()) {
            throw helper::to_exception($response);
        }
        $data = json_decode($response->body, true);
        if (!is_array($data) || empty($data['content']) || !is_array($data['content'])) {
            throw new exception('badresponse', mb_substr($response->body, 0, 300, 'UTF-8'), $response->status);
        }
        $parts = [];
        foreach ($data['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $parts[] = (string) $block['text'];
            }
        }
        $text = trim(implode('', $parts));
        $finish = (string) ($data['stop_reason'] ?? '');
        if ($text === '') {
            throw new exception($finish === 'max_tokens' ? 'emptylength' : 'emptyresponse', $finish, $response->status);
        }
        return new completion(
            $text,
            (int) ($data['usage']['input_tokens'] ?? 0),
            (int) ($data['usage']['output_tokens'] ?? 0),
            $finish
        );
    }
}
