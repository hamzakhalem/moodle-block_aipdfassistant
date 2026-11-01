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

use block_aipdfassistant\local\ai\registry as ai_registry;
use block_aipdfassistant\local\ai\text_provider_interface;
use block_aipdfassistant\local\http\client_interface;
use block_aipdfassistant\local\http\moodle_client;

/**
 * Creates service objects; tests can replace the HTTP client.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class factory {
    /** @var client_interface|null Replacement HTTP client for unit tests. */
    private static $httpoverride = null;

    /**
     * Replace the HTTP client (unit tests only).
     *
     * @param client_interface|null $client Client or null to restore the default.
     * @return void
     */
    public static function set_http_client(?client_interface $client): void {
        self::$httpoverride = $client;
    }

    /**
     * HTTP client in use.
     *
     * @return client_interface
     */
    public static function http(): client_interface {
        return self::$httpoverride ?? new moodle_client();
    }

    /**
     * Text provider for the current configuration.
     *
     * @param config $config Configuration.
     * @return text_provider_interface
     */
    public static function text_provider(config $config): text_provider_interface {
        return ai_registry::create_text_provider($config, self::http());
    }

    /**
     * Assistant wired with the configured provider and the database cache.
     *
     * @param config $config Configuration.
     * @return assistant
     */
    public static function assistant(config $config): assistant {
        return new assistant(self::text_provider($config), new db_cache($config->int('cachedays')), $config);
    }
}
