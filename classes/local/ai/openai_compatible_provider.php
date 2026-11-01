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
 * Provider for every API that speaks the OpenAI "chat/completions" dialect:
 * OpenAI, Google Gemini (OpenAI compatibility endpoint), DeepSeek, Ollama, Groq,
 * OpenRouter, Mistral, vLLM, LM Studio and others.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class openai_compatible_provider implements text_provider_interface {
    /** @var client_interface HTTP client. */
    private $http;

    /** @var array Resolved provider settings. */
    private $settings;

    /**
     * Constructor.
     *
     * @param client_interface $http HTTP client.
     * @param array $settings Keys: id, base, model, key, tokenparam, temp, timeout.
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
     * Run one chat completion.
     *
     * @param string $system System prompt.
     * @param array $messages Conversation turns.
     * @param array $options max_tokens, temperature.
     * @return completion
     */
    public function complete(string $system, array $messages, array $options = []): completion {
        $maxtokens = (int) ($options['max_tokens'] ?? 2000);
        $payload = [
            'model' => $this->settings['model'],
            'messages' => array_merge(
                [['role' => 'system', 'content' => $system]],
                helper::normalize_messages($messages)
            ),
        ];
        $payload[$this->settings['tokenparam']] = $maxtokens;
        if (!empty($this->settings['temp'])) {
            $payload['temperature'] = (float) ($options['temperature'] ?? 0.3);
        }

        $attempts = 0;
        do {
            $response = $this->http->post(
                $this->settings['base'] . '/chat/completions',
                $this->headers(),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (int) $this->settings['timeout']
            );
            $retry = $response->status === 400 && $attempts < 2 && self::adjust_payload($payload, $response->body);
            $attempts++;
        } while ($retry);

        return $this->parse($response);
    }

    /**
     * Request headers.
     *
     * @return string[]
     */
    private function headers(): array {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($this->settings['key'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->settings['key'];
        }
        return $headers;
    }

    /**
     * Adapt the payload to a model that rejected a parameter (reasoning models do not
     * accept max_tokens or a custom temperature).
     *
     * @param array $payload Payload, modified in place.
     * @param string $body Error response body.
     * @return bool True when something changed and the request should be retried.
     */
    public static function adjust_payload(array &$payload, string $body): bool {
        $lower = strtolower($body);
        if (isset($payload['max_tokens']) && strpos($lower, 'max_completion_tokens') !== false) {
            $payload['max_completion_tokens'] = $payload['max_tokens'];
            unset($payload['max_tokens']);
            return true;
        }
        if (isset($payload['max_completion_tokens']) && strpos($lower, 'max_completion_tokens') === false
                && strpos($lower, 'max_tokens') !== false) {
            $payload['max_tokens'] = $payload['max_completion_tokens'];
            unset($payload['max_completion_tokens']);
            return true;
        }
        if (isset($payload['temperature']) && strpos($lower, 'temperature') !== false) {
            unset($payload['temperature']);
            return true;
        }
        return false;
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
        if (!is_array($data) || empty($data['choices'][0])) {
            throw new exception('badresponse', mb_substr($response->body, 0, 300, 'UTF-8'), $response->status);
        }
        $choice = $data['choices'][0];
        $content = $choice['message']['content'] ?? '';
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $parts[] = (string) $part['text'];
                } else if (is_string($part)) {
                    $parts[] = $part;
                }
            }
            $content = implode('', $parts);
        }
        $text = helper::strip_thinking((string) $content);
        $finish = (string) ($choice['finish_reason'] ?? '');
        if ($text === '') {
            throw new exception($finish === 'length' ? 'emptylength' : 'emptyresponse', $finish, $response->status);
        }
        return new completion(
            $text,
            (int) ($data['usage']['prompt_tokens'] ?? 0),
            (int) ($data['usage']['completion_tokens'] ?? 0),
            $finish
        );
    }
}
