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


namespace block_aipdfassistant;

use block_aipdfassistant\local\ai\anthropic_provider;
use block_aipdfassistant\local\ai\helper;
use block_aipdfassistant\local\ai\openai_compatible_provider;
use block_aipdfassistant\local\ai\registry;
use block_aipdfassistant\local\config;
use block_aipdfassistant\local\exception;

require_once(__DIR__ . '/fixtures/fake_http_client.php');

/**
 * Tests for the text providers (no network: a scripted HTTP client is used).
 *
 * @package    block_aipdfassistant
 * @category   test
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_aipdfassistant\local\ai\openai_compatible_provider
 * @covers     \block_aipdfassistant\local\ai\anthropic_provider
 * @covers     \block_aipdfassistant\local\ai\helper
 * @covers     \block_aipdfassistant\local\ai\registry
 */
final class providers_test extends \basic_testcase {
    /**
     * Build an OpenAI style provider.
     *
     * @param fake_http_client $http Client.
     * @param array $override Settings override.
     * @return openai_compatible_provider
     */
    private function openai(fake_http_client $http, array $override = []): openai_compatible_provider {
        return new openai_compatible_provider($http, $override + [
            'id' => 'openai', 'base' => 'https://api.test/v1', 'model' => 'm1', 'key' => 'sk-test',
            'tokenparam' => 'max_tokens', 'temp' => true, 'timeout' => 30,
        ]);
    }

    /**
     * Request layout and response parsing.
     */
    public function test_openai_request_and_response(): void {
        $http = (new fake_http_client())->push(200, json_encode([
            'choices' => [['message' => ['content' => 'Bonjour'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 3],
        ]));
        $result = $this->openai($http)->complete('SYS', [['role' => 'user', 'content' => 'Salut']], ['max_tokens' => 500, 'temperature' => 0.2]);
        $this->assertSame('Bonjour', $result->text);
        $this->assertSame(11, $result->inputtokens);
        $this->assertSame(3, $result->outputtokens);
        $request = $http->requests[0];
        $this->assertSame('https://api.test/v1/chat/completions', $request['url']);
        $this->assertContains('Authorization: Bearer sk-test', $request['headers']);
        $payload = json_decode($request['body'], true);
        $this->assertSame('m1', $payload['model']);
        $this->assertSame(500, $payload['max_tokens']);
        $this->assertSame(0.2, $payload['temperature']);
        $this->assertSame('system', $payload['messages'][0]['role']);
        $this->assertSame('SYS', $payload['messages'][0]['content']);
        $this->assertSame('Salut', $payload['messages'][1]['content']);
    }

    /**
     * Arabic text must not be escaped in the JSON body.
     */
    public function test_openai_keeps_unicode_unescaped(): void {
        $http = (new fake_http_client())->push(200, '{"choices":[{"message":{"content":"ok"}}]}');
        $this->openai($http)->complete('S', [['role' => 'user', 'content' => 'لخص']]);
        $this->assertStringContainsString('لخص', $http->requests[0]['body']);
    }

    /**
     * Reasoning models reject max_tokens; the provider retries with max_completion_tokens.
     */
    public function test_openai_retries_with_max_completion_tokens(): void {
        $http = (new fake_http_client())
            ->push(400, '{"error":{"message":"Unsupported parameter: \'max_tokens\' is not supported with this model. Use \'max_completion_tokens\' instead."}}')
            ->push(200, '{"choices":[{"message":{"content":"done"}}]}');
        $result = $this->openai($http)->complete('S', [['role' => 'user', 'content' => 'x']], ['max_tokens' => 700]);
        $this->assertSame('done', $result->text);
        $this->assertCount(2, $http->requests);
        $second = json_decode($http->requests[1]['body'], true);
        $this->assertSame(700, $second['max_completion_tokens']);
        $this->assertArrayNotHasKey('max_tokens', $second);
    }

    /**
     * A rejected temperature is dropped and the request retried.
     */
    public function test_openai_retries_without_temperature(): void {
        $http = (new fake_http_client())
            ->push(400, '{"error":{"message":"Unsupported value: temperature does not support 0.3 with this model."}}')
            ->push(200, '{"choices":[{"message":{"content":"done"}}]}');
        $this->openai($http)->complete('S', [['role' => 'user', 'content' => 'x']]);
        $this->assertArrayNotHasKey('temperature', json_decode($http->requests[1]['body'], true));
    }

    /**
     * Content given as parts, and hidden reasoning, are handled.
     */
    public function test_openai_content_parts_and_think_blocks(): void {
        $http = (new fake_http_client())->push(200, json_encode(['choices' => [['message' => ['content' => [
            ['type' => 'text', 'text' => '<think>secret</think>Visible '], ['type' => 'text', 'text' => 'text'],
        ]]]]]));
        $this->assertSame('Visible text', $this->openai($http)->complete('S', [['role' => 'user', 'content' => 'x']])->text);
    }

    /**
     * HTTP failures map to stable error codes.
     *
     * @dataProvider failures
     * @param int $status HTTP status.
     * @param string $body Body.
     * @param string $code Expected code.
     * @param string $error Transport error.
     */
    public function test_openai_error_mapping(int $status, string $body, string $code, string $error = ''): void {
        $http = (new fake_http_client())->push($status, $body, $error);
        try {
            $this->openai($http)->complete('S', [['role' => 'user', 'content' => 'x']]);
            $this->fail('Exception expected');
        } catch (exception $e) {
            $this->assertSame($code, $e->errorcode);
        }
    }

    /**
     * Failure cases.
     *
     * @return array
     */
    public static function failures(): array {
        return [
            'bad key' => [401, '{"error":{"message":"Incorrect API key"}}', 'auth'],
            'forbidden' => [403, '{"error":"no"}', 'auth'],
            'quota' => [429, '{"error":{"message":"Rate limit"}}', 'providerlimit'],
            'unknown model' => [404, '{"error":{"message":"The model `zzz` does not exist"}}', 'badmodel'],
            'too long' => [400, '{"error":{"message":"maximum context length is 8192 tokens"}}', 'toolong'],
            'server' => [503, 'Service Unavailable', 'unavailable'],
            'bad request' => [400, '{"error":{"message":"invalid json"}}', 'badrequest'],
            'network' => [0, '', 'network', 'Could not resolve host'],
            'html page' => [502, '<html>Bad gateway</html>', 'unavailable'],
        ];
    }

    /**
     * Malformed and empty answers.
     */
    public function test_openai_bad_and_empty_responses(): void {
        foreach ([['{"nochoices":true}', 'badresponse'], ['{"choices":[{"message":{"content":""},"finish_reason":"length"}]}', 'emptylength'],
                ['{"choices":[{"message":{"content":""},"finish_reason":"stop"}]}', 'emptyresponse']] as [$body, $code]) {
            $http = (new fake_http_client())->push(200, $body);
            try {
                $this->openai($http)->complete('S', [['role' => 'user', 'content' => 'x']]);
                $this->fail('Exception expected for ' . $code);
            } catch (exception $e) {
                $this->assertSame($code, $e->errorcode);
            }
        }
    }

    /**
     * The Anthropic request uses its own headers and a top-level system prompt.
     */
    public function test_anthropic_request_and_response(): void {
        $http = (new fake_http_client())->push(200, json_encode([
            'content' => [['type' => 'text', 'text' => 'Salam '], ['type' => 'text', 'text' => 'alaykum']],
            'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 20, 'output_tokens' => 4],
        ]));
        $provider = new anthropic_provider($http, ['id' => 'anthropic', 'base' => 'https://api.anthropic.com', 'model' => 'claude-x',
            'key' => 'ak', 'temp' => true, 'timeout' => 30]);
        $result = $provider->complete('SYS', [
            ['role' => 'assistant', 'content' => 'dropped: conversation must start with the user'],
            ['role' => 'user', 'content' => 'one'], ['role' => 'user', 'content' => 'two'],
        ], ['max_tokens' => 900, 'temperature' => 0.1]);
        $this->assertSame('Salam alaykum', $result->text);
        $this->assertSame(20, $result->inputtokens);
        $request = $http->requests[0];
        $this->assertSame('https://api.anthropic.com/v1/messages', $request['url']);
        $this->assertContains('x-api-key: ak', $request['headers']);
        $this->assertContains('anthropic-version: 2023-06-01', $request['headers']);
        $payload = json_decode($request['body'], true);
        $this->assertSame('SYS', $payload['system']);
        $this->assertSame(900, $payload['max_tokens']);
        $this->assertCount(1, $payload['messages']);
        $this->assertSame("one\n\ntwo", $payload['messages'][0]['content']);
    }

    /**
     * Anthropic: a rejected temperature is dropped.
     */
    public function test_anthropic_retries_without_temperature(): void {
        $http = (new fake_http_client())
            ->push(400, '{"type":"error","error":{"type":"invalid_request_error","message":"temperature is deprecated for this model"}}')
            ->push(200, '{"content":[{"type":"text","text":"ok"}]}');
        $provider = new anthropic_provider($http, ['id' => 'anthropic', 'base' => 'https://api.anthropic.com', 'model' => 'm',
            'key' => 'k', 'temp' => true, 'timeout' => 30]);
        $this->assertSame('ok', $provider->complete('S', [['role' => 'user', 'content' => 'x']])->text);
        $this->assertArrayNotHasKey('temperature', json_decode($http->requests[1]['body'], true));
    }

    /**
     * Message normalisation.
     */
    public function test_normalize_messages(): void {
        $clean = helper::normalize_messages([
            ['role' => 'system', 'content' => 'ignored'], ['role' => 'assistant', 'content' => 'a'],
            ['role' => 'user', 'content' => ' u1 '], ['role' => 'user', 'content' => ''], ['role' => 'user', 'content' => 'u2'],
            ['role' => 'assistant', 'content' => 'a2'],
        ]);
        $this->assertSame([['role' => 'user', 'content' => "u1\n\nu2"], ['role' => 'assistant', 'content' => 'a2']], $clean);
    }

    /**
     * The registry builds the right provider from configuration and refuses incomplete setups.
     */
    public function test_registry(): void {
        $http = new fake_http_client();
        $claude = registry::create_text_provider(config::from_array(['provider' => 'anthropic', 'apikey' => 'k']), $http);
        $this->assertInstanceOf(anthropic_provider::class, $claude);
        $this->assertSame('anthropic:claude-sonnet-5-5', $claude->name());
        $deepseek = registry::create_text_provider(config::from_array(['provider' => 'deepseek', 'apikey' => 'k']), $http);
        $this->assertInstanceOf(openai_compatible_provider::class, $deepseek);
        $ollama = registry::create_text_provider(config::from_array(['provider' => 'ollama']), $http);
        $this->assertSame('ollama:llama3.1', $ollama->name());
        $this->expectException(exception::class);
        registry::create_text_provider(config::from_array(['provider' => 'openai', 'apikey' => '']), $http);
    }

    /**
     * Configuration helpers.
     */
    public function test_config(): void {
        $this->assertFalse(config::from_array(['provider' => 'custom'])->is_configured());
        $this->assertTrue(config::from_array(['provider' => 'custom', 'baseurl' => 'http://x/v1', 'model' => 'm'])->is_configured());
        $tts = config::from_array(['provider' => 'openai', 'apikey' => 'k', 'tts_provider' => 'openai', 'tts_gender' => 'male'])->tts_settings();
        $this->assertSame('onyx', $tts['voice']);
        $this->assertSame('k', $tts['key'], 'text key reused for the same vendor');
        $female = config::from_array(['tts_provider' => 'gemini', 'tts_apikey' => 'g', 'tts_gender' => 'female'])->tts_settings();
        $this->assertSame('Kore', $female['voice']);
        $custom = config::from_array(['tts_provider' => 'openai', 'tts_apikey' => 'k', 'tts_voice_female' => 'shimmer'])->tts_settings();
        $this->assertSame('shimmer', $custom['voice']);
        $this->assertFalse(config::from_array(['tts_provider' => 'none'])->tts_enabled());
        $this->assertTrue(config::from_array(['tts_provider' => 'browser'])->tts_is_browser());
    }
}
