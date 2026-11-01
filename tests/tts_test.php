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

use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\tts\audio;
use block_aipdfassistant\local\tts\elevenlabs_tts_provider;
use block_aipdfassistant\local\tts\gemini_tts_provider;
use block_aipdfassistant\local\tts\openai_tts_provider;
use block_aipdfassistant\local\tts\synthesizer;
use block_aipdfassistant\local\tts\tts_provider_interface;
use block_aipdfassistant\local\tts\wav;

require_once(__DIR__ . '/fixtures/fake_http_client.php');

/**
 * Tests for the text-to-speech providers and the synthesizer.
 *
 * @package    block_aipdfassistant
 * @category   test
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_aipdfassistant\local\tts\openai_tts_provider
 * @covers     \block_aipdfassistant\local\tts\gemini_tts_provider
 * @covers     \block_aipdfassistant\local\tts\elevenlabs_tts_provider
 * @covers     \block_aipdfassistant\local\tts\synthesizer
 * @covers     \block_aipdfassistant\local\tts\wav
 */
final class tts_test extends \basic_testcase {
    /** @var array Base settings for providers. */
    private $settings = ['id' => 'x', 'base' => 'https://tts.test/v1', 'model' => 'tts-m', 'key' => 'k', 'chunk' => 100, 'timeout' => 30];

    /**
     * OpenAI style speech request.
     */
    public function test_openai_tts(): void {
        $http = (new fake_http_client())->push(200, 'MP3BYTES');
        $audio = (new openai_tts_provider($http, $this->settings))->synthesize('Bonjour', 'nova');
        $this->assertSame('MP3BYTES', $audio->bytes);
        $this->assertSame('audio/mpeg', $audio->mime);
        $this->assertSame('https://tts.test/v1/audio/speech', $http->requests[0]['url']);
        $body = json_decode($http->requests[0]['body'], true);
        $this->assertSame(['model' => 'tts-m', 'input' => 'Bonjour', 'voice' => 'nova', 'response_format' => 'mp3'], $body);
    }

    /**
     * Gemini returns base64 PCM that becomes WAV.
     */
    public function test_gemini_tts_and_wav(): void {
        $pcm = str_repeat("\x01\x02", 50);
        $http = (new fake_http_client())->push(200, json_encode(['candidates' => [['content' => ['parts' => [
            ['inlineData' => ['mimeType' => 'audio/L16;codec=pcm;rate=24000', 'data' => base64_encode($pcm)]],
        ]]]]]));
        $provider = new gemini_tts_provider($http, ['base' => 'https://gem.test/v1beta', 'model' => 'g-tts'] + $this->settings);
        $part = $provider->synthesize('مرحبا', 'Kore');
        $this->assertSame('audio/pcm', $part->mime);
        $this->assertSame(24000, $part->rate);
        $this->assertSame('https://gem.test/v1beta/models/g-tts:generateContent', $http->requests[0]['url']);
        $this->assertContains('x-goog-api-key: k', $http->requests[0]['headers']);
        $payload = json_decode($http->requests[0]['body'], true);
        $this->assertSame('Kore', $payload['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
        $this->assertSame(['AUDIO'], $payload['generationConfig']['responseModalities']);
        $joined = synthesizer::join([$part]);
        $this->assertSame('audio/wav', $joined->mime);
        $this->assertSame('RIFF', substr($joined->bytes, 0, 4));
        $this->assertSame('WAVE', substr($joined->bytes, 8, 4));
        $this->assertSame(44 + strlen($pcm), strlen($joined->bytes));
        $this->assertSame(strlen($pcm), unpack('V', substr($joined->bytes, 40, 4))[1]);
        $this->assertSame(24000, unpack('V', substr($joined->bytes, 24, 4))[1]);
    }

    /**
     * ElevenLabs addresses the voice in the URL and authenticates with xi-api-key.
     */
    public function test_elevenlabs_tts(): void {
        $http = (new fake_http_client())->push(200, 'AUDIO');
        $provider = new elevenlabs_tts_provider($http, ['base' => 'https://api.eleven.test/v1', 'model' => 'eleven_multilingual_v2'] + $this->settings);
        $this->assertSame('AUDIO', $provider->synthesize('Hi', 'VOICE123')->bytes);
        $this->assertStringContainsString('/text-to-speech/VOICE123?output_format=mp3_44100_128', $http->requests[0]['url']);
        $this->assertContains('xi-api-key: k', $http->requests[0]['headers']);
    }

    /**
     * Provider failures are mapped.
     */
    public function test_tts_errors(): void {
        $http = (new fake_http_client())->push(401, '{"error":{"message":"bad key"}}')->push(200, '');
        $provider = new openai_tts_provider($http, $this->settings);
        try {
            $provider->synthesize('x', 'nova');
            $this->fail('Exception expected');
        } catch (exception $e) {
            $this->assertSame('auth', $e->errorcode);
        }
        $this->expectException(exception::class);
        $provider->synthesize('x', 'nova');
    }

    /**
     * Long texts are cut in provider sized pieces and the audio is joined in order.
     */
    public function test_synthesizer_chunks_and_joins(): void {
        $provider = new class implements tts_provider_interface {
            /** @var string[] Texts received. */
            public $texts = [];

            /**
             * Fake synthesis.
             *
             * @param string $text Text.
             * @param string $voice Voice.
             * @return audio
             */
            public function synthesize(string $text, string $voice): audio {
                $this->texts[] = $text;
                return new audio('[' . count($this->texts) . ']', 'audio/mpeg', 'mp3');
            }

            /**
             * Max chars.
             *
             * @return int
             */
            public function max_chars(): int {
                return 100;
            }

            /**
             * Name.
             *
             * @return string
             */
            public function name(): string {
                return 'fake';
            }
        };
        $markdown = "# Titre\n\n" . str_repeat('**Une phrase de test** pour la voix. ', 12);
        $audio = (new synthesizer($provider, 'v', 9000))->synthesize($markdown);
        $this->assertGreaterThan(2, count($provider->texts));
        foreach ($provider->texts as $text) {
            $this->assertLessThanOrEqual(100, mb_strlen($text));
            $this->assertStringNotContainsString('**', $text);
            $this->assertStringNotContainsString('#', $text);
        }
        $this->assertSame('audio/mpeg', $audio->mime);
        $this->assertSame('[1][2]', substr($audio->bytes, 0, 6));
        $this->expectException(exception::class);
        (new synthesizer($provider, 'v'))->synthesize("```\n```");
    }

    /**
     * The total read-aloud length is capped.
     */
    public function test_synthesizer_cap(): void {
        $provider = new class implements tts_provider_interface {
            /**
             * Fake.
             *
             * @param string $text Text.
             * @param string $voice Voice.
             * @return audio
             */
            public function synthesize(string $text, string $voice): audio {
                return new audio('a', 'audio/mpeg', 'mp3');
            }

            /**
             * Max.
             *
             * @return int
             */
            public function max_chars(): int {
                return 1000;
            }

            /**
             * Name.
             *
             * @return string
             */
            public function name(): string {
                return 'fake';
            }
        };
        $plain = (new synthesizer($provider, 'v', 500))->prepare(str_repeat('Une phrase. ', 200));
        $this->assertLessThanOrEqual(500, mb_strlen($plain));
        $this->assertSame(wav::from_pcm('', 8000)[0], 'R');
    }
}
