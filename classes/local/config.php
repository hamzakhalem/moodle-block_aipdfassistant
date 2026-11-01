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


namespace block_aipdfassistant\local;

/**
 * Typed access to the plugin settings with sane defaults.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config {
    /** Default values for every admin setting. */
    const DEFAULTS = [
        'provider' => 'openai',
        'apikey' => '',
        'model' => '',
        'baseurl' => '',
        'temperature' => 0.3,
        'maxtokens' => 2000,
        'timeout' => 120,
        'maxcontextchars' => 48000,
        'maxchunks' => 10,
        'tts_provider' => 'none',
        'tts_apikey' => '',
        'tts_model' => '',
        'tts_baseurl' => '',
        'tts_gender' => 'female',
        'tts_voice_male' => '',
        'tts_voice_female' => '',
        'tts_maxchars' => 9000,
        'maxfilesize' => 20,
        'maxdocchars' => 600000,
        'pdftotextpath' => 'pdftotext',
        'retentiondays' => 30,
        'responselang' => 'auto',
        'systemextra' => '',
        'limit_hour' => 30,
        'limit_day' => 150,
        'cachedays' => 30,
        'allowcoursepdfs' => 1,
    ];

    /** @var array Effective values. */
    private $values;

    /**
     * Constructor.
     *
     * @param array $values Overrides for the defaults.
     */
    public function __construct(array $values = []) {
        $this->values = array_merge(self::DEFAULTS, $values);
    }

    /**
     * Load the stored plugin configuration.
     *
     * @return config
     */
    public static function load(): config {
        $stored = get_config('block_aipdfassistant');
        $values = [];
        foreach ((array) $stored as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS) && $value !== '' && $value !== null) {
                $values[$key] = $value;
            } else if (array_key_exists($key, self::DEFAULTS) && is_string(self::DEFAULTS[$key])) {
                $values[$key] = (string) $value;
            }
        }
        return new self($values);
    }

    /**
     * Build a configuration from an array (handy for tests).
     *
     * @param array $values Values.
     * @return config
     */
    public static function from_array(array $values): config {
        return new self($values);
    }

    /**
     * Get a raw value.
     *
     * @param string $key Setting name.
     * @return mixed
     */
    public function get(string $key) {
        return $this->values[$key] ?? null;
    }

    /**
     * Integer setting.
     *
     * @param string $key Setting name.
     * @return int
     */
    public function int(string $key): int {
        return (int) $this->get($key);
    }

    /**
     * String setting.
     *
     * @param string $key Setting name.
     * @return string
     */
    public function string(string $key): string {
        return trim((string) $this->get($key));
    }

    /**
     * Resolve the settings for the text provider.
     *
     * @return array|null Keys: id, label, type, base, model, key, tokenparam, temp. Null when the provider id is unknown.
     */
    public function text_settings(): ?array {
        $id = $this->string('provider');
        $info = ai\registry::TEXT[$id] ?? null;
        if ($info === null) {
            return null;
        }
        $base = $this->string('baseurl');
        $model = $this->string('model');
        return [
            'id' => $id,
            'label' => $info['label'],
            'type' => $info['type'],
            'base' => $base !== '' ? rtrim($base, '/') : $info['base'],
            'model' => $model !== '' ? $model : $info['model'],
            'key' => $this->string('apikey'),
            'tokenparam' => $info['tokenparam'],
            'temp' => $info['temp'],
            'needskey' => $info['needskey'],
            'temperature' => (float) $this->get('temperature'),
            'maxtokens' => max(200, $this->int('maxtokens')),
            'timeout' => max(10, $this->int('timeout')),
        ];
    }

    /**
     * Whether the text provider has everything it needs.
     *
     * @return bool
     */
    public function is_configured(): bool {
        $s = $this->text_settings();
        if ($s === null || $s['base'] === '' || $s['model'] === '') {
            return false;
        }
        return !$s['needskey'] || $s['key'] !== '';
    }

    /**
     * Whether any voice output is enabled (API provider or browser).
     *
     * @return bool
     */
    public function tts_enabled(): bool {
        return $this->string('tts_provider') !== 'none' && $this->string('tts_provider') !== '';
    }

    /**
     * Whether speech is produced in the browser instead of by an API.
     *
     * @return bool
     */
    public function tts_is_browser(): bool {
        return $this->string('tts_provider') === 'browser';
    }

    /**
     * Resolve the settings for the text-to-speech provider.
     *
     * @return array|null Null when voice output is disabled, browser based, or unknown.
     */
    public function tts_settings(): ?array {
        $id = $this->string('tts_provider');
        $info = tts\registry::PROVIDERS[$id] ?? null;
        if ($info === null) {
            return null;
        }
        $base = $this->string('tts_baseurl');
        $model = $this->string('tts_model');
        $key = $this->string('tts_apikey');
        // Reuse the text API key when the vendor is the same and no dedicated key was given.
        if ($key === '' && $this->string('provider') === $id) {
            $key = $this->string('apikey');
        }
        $gender = $this->string('tts_gender') === 'male' ? 'male' : 'female';
        $voice = $this->string('tts_voice_' . $gender);
        return [
            'id' => $id,
            'type' => $info['type'],
            'base' => $base !== '' ? rtrim($base, '/') : $info['base'],
            'model' => $model !== '' ? $model : $info['model'],
            'key' => $key,
            'voice' => $voice !== '' ? $voice : $info['voice_' . $gender],
            'gender' => $gender,
            'chunk' => $info['chunk'],
            'timeout' => max(10, $this->int('timeout')),
            'needskey' => $info['needskey'],
        ];
    }

    /**
     * Maximum upload size in bytes.
     *
     * @return int
     */
    public function max_file_bytes(): int {
        return max(1, $this->int('maxfilesize')) * 1024 * 1024;
    }
}
