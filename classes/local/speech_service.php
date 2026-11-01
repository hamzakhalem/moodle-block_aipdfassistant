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

use block_aipdfassistant\local\tts\registry as tts_registry;
use block_aipdfassistant\local\tts\synthesizer;

/**
 * Turns an answer into speech: API audio stored as a file, or a browser voice hint.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class speech_service {
    /** @var config Configuration. */
    private $config;

    /** @var audio_store Audio storage. */
    private $store;

    /**
     * Constructor.
     *
     * @param config $config Configuration.
     * @param audio_store|null $store Audio storage.
     */
    public function __construct(config $config, ?audio_store $store = null) {
        $this->config = $config;
        $this->store = $store ?? new audio_store();
    }

    /**
     * Whether voice output is enabled at all.
     *
     * @return bool
     */
    public function enabled(): bool {
        return $this->config->tts_enabled();
    }

    /**
     * Prepare speech for a Markdown message.
     *
     * @param string $markdown Message.
     * @param int $contextid Block context id.
     * @param int $userid User id.
     * @return array Keys: audiourl, audiomime, browsertts, provider, cached.
     * @throws exception ttsdisabled, ttsnotconfigured, nothingtoread or provider errors.
     */
    public function render(string $markdown, int $contextid, int $userid): array {
        if (!$this->config->tts_enabled()) {
            throw new exception('ttsdisabled');
        }
        if ($this->config->tts_is_browser()) {
            return ['audiourl' => '', 'audiomime' => '', 'browsertts' => true, 'provider' => 'browser', 'cached' => false];
        }
        $provider = tts_registry::create($this->config, factory::http());
        $settings = $this->config->tts_settings();
        $synth = new synthesizer($provider, $settings['voice'], $this->config->int('tts_maxchars'));
        $plain = $synth->prepare($markdown);
        if ($plain === '') {
            throw new exception('nothingtoread');
        }
        $key = sha1($provider->name() . '|' . $settings['voice'] . '|' . $plain);
        foreach (['mp3', 'wav'] as $ext) {
            $file = $this->store->find($contextid, $userid, $key, $ext);
            if ($file) {
                return ['audiourl' => audio_store::url($file), 'audiomime' => $file->get_mimetype(),
                    'browsertts' => false, 'provider' => $provider->name(), 'cached' => true];
            }
        }
        $audio = $synth->synthesize($markdown);
        $file = $this->store->save($contextid, $userid, $key, $audio);
        return ['audiourl' => audio_store::url($file), 'audiomime' => $audio->mime, 'browsertts' => false,
            'provider' => $provider->name(), 'cached' => false];
    }
}
