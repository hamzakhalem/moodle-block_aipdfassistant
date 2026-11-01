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

use block_aipdfassistant\local\config;
use block_aipdfassistant\local\exception;
use block_aipdfassistant\local\http\client_interface;

/**
 * Catalogue of supported text providers and factory for provider objects.
 *
 * Model names change often; the values below are only suggestions. Admins can
 * type any model id in the plugin settings.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** Supported text providers. */
    const TEXT = [
        'openai' => [
            'label' => 'OpenAI', 'type' => 'openai', 'base' => 'https://api.openai.com/v1',
            'model' => 'gpt-5-mini', 'tokenparam' => 'max_completion_tokens', 'temp' => false, 'needskey' => true,
        ],
        'anthropic' => [
            'label' => 'Anthropic Claude', 'type' => 'anthropic', 'base' => 'https://api.anthropic.com',
            'model' => 'claude-sonnet-5-5', 'tokenparam' => 'max_tokens', 'temp' => true, 'needskey' => true,
        ],
        'gemini' => [
            'label' => 'Google Gemini', 'type' => 'openai',
            'base' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'model' => 'gemini-flash-latest', 'tokenparam' => 'max_tokens', 'temp' => true, 'needskey' => true,
        ],
        'deepseek' => [
            'label' => 'DeepSeek', 'type' => 'openai', 'base' => 'https://api.deepseek.com',
            'model' => 'deepseek-chat', 'tokenparam' => 'max_tokens', 'temp' => true, 'needskey' => true,
        ],
        'ollama' => [
            'label' => 'Ollama (local)', 'type' => 'openai', 'base' => 'http://localhost:11434/v1',
            'model' => 'llama3.1', 'tokenparam' => 'max_tokens', 'temp' => true, 'needskey' => false,
        ],
        'custom' => [
            'label' => 'Custom (OpenAI compatible)', 'type' => 'openai', 'base' => '',
            'model' => '', 'tokenparam' => 'max_tokens', 'temp' => true, 'needskey' => false,
        ],
    ];

    /**
     * Provider id => label pairs for the settings page.
     *
     * @return string[]
     */
    public static function text_choices(): array {
        $choices = [];
        foreach (self::TEXT as $id => $info) {
            $choices[$id] = $info['label'];
        }
        return $choices;
    }

    /**
     * Create the configured text provider.
     *
     * @param config $config Plugin configuration.
     * @param client_interface $http HTTP client.
     * @return text_provider_interface
     * @throws exception When the provider is not configured.
     */
    public static function create_text_provider(config $config, client_interface $http): text_provider_interface {
        $settings = $config->text_settings();
        if ($settings === null || !$config->is_configured()) {
            throw new exception('notconfigured');
        }
        if ($settings['type'] === 'anthropic') {
            return new anthropic_provider($http, $settings);
        }
        return new openai_compatible_provider($http, $settings);
    }
}
