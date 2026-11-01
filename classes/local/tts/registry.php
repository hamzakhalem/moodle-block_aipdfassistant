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

use block_aipdfassistant\local\config;
use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\http\client_interface;

/**
 * Catalogue of text-to-speech providers and factory.
 *
 * The male/female voice defaults are suggestions; admins can type any voice id.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** Supported TTS providers. */
    const PROVIDERS = [
        'openai' => [
            'type' => 'openai', 'base' => 'https://api.openai.com/v1', 'model' => 'gpt-4o-mini-tts',
            'voice_male' => 'onyx', 'voice_female' => 'nova', 'chunk' => 1800, 'needskey' => true,
        ],
        'gemini' => [
            'type' => 'gemini', 'base' => 'https://generativelanguage.googleapis.com/v1beta',
            'model' => 'gemini-3.8-flash-tts', 'voice_male' => 'Charon', 'voice_female' => 'Kore',
            'chunk' => 3000, 'needskey' => true,
        ],
        'elevenlabs' => [
            'type' => 'elevenlabs', 'base' => 'https://api.elevenlabs.io/v1', 'model' => 'eleven_multilingual_v2',
            'voice_male' => 'pNInz6obpgDQGcFmaJgB', 'voice_female' => '21m00Tcm4TlvDq8ikWAM',
            'chunk' => 2500, 'needskey' => true,
        ],
        'custom' => [
            'type' => 'openai', 'base' => '', 'model' => 'tts-1',
            'voice_male' => 'onyx', 'voice_female' => 'nova', 'chunk' => 1800, 'needskey' => false,
        ],
    ];

    /**
     * Provider id => label pairs for the settings page (includes "none" and "browser").
     *
     * @param array $labels Labels keyed by id, already translated.
     * @return string[]
     */
    public static function choices(array $labels): array {
        return $labels;
    }

    /**
     * Create the configured TTS provider, or null when the voice is browser based or disabled.
     *
     * @param config $config Plugin configuration.
     * @param client_interface $http HTTP client.
     * @return tts_provider_interface|null
     * @throws exception When the provider lacks credentials.
     */
    public static function create(config $config, client_interface $http): ?tts_provider_interface {
        $settings = $config->tts_settings();
        if ($settings === null) {
            return null;
        }
        if ($settings['needskey'] && $settings['key'] === '') {
            throw new exception('ttsnotconfigured');
        }
        if ($settings['base'] === '') {
            throw new exception('ttsnotconfigured', 'Missing base URL');
        }
        switch ($settings['type']) {
            case 'gemini':
                return new gemini_tts_provider($http, $settings);
            case 'elevenlabs':
                return new elevenlabs_tts_provider($http, $settings);
            default:
                return new openai_tts_provider($http, $settings);
        }
    }
}
