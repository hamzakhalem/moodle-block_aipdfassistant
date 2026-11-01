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

use block_aipdfassistant\local\ai\completion;
use block_aipdfassistant\local\ai\text_provider_interface;
use block_aipdfassistant\local\http\client_interface;
use block_aipdfassistant\local\http\response;

/**
 * Test doubles: a scripted HTTP client and a counting text provider.
 *
 * @package    block_aipdfassistant
 * @category   test
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_http_client implements client_interface {
    /** @var response[] Queue of responses. */
    public $queue = [];

    /** @var array Recorded requests: url, headers, body, timeout. */
    public $requests = [];

    /**
     * Queue a response.
     *
     * @param int $status HTTP status.
     * @param string $body Body.
     * @param string $error Transport error.
     * @return self
     */
    public function push(int $status, string $body, string $error = ''): self {
        $this->queue[] = new response($status, $body, $error);
        return $this;
    }

    /**
     * Record the request and return the next queued response.
     *
     * @param string $url URL.
     * @param array $headers Headers.
     * @param string $body Body.
     * @param int $timeout Timeout.
     * @return response
     */
    public function post(string $url, array $headers, string $body, int $timeout): response {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $body, 'timeout' => $timeout];
        if (!$this->queue) {
            return new response(500, '{"error":{"message":"no response queued"}}');
        }
        return array_shift($this->queue);
    }
}

/**
 * Text provider returning "T1", "T2", ... and recording what it received.
 */
class counting_provider implements text_provider_interface {
    /** @var int Calls made. */
    public $calls = 0;

    /** @var string[] System prompts received. */
    public $systems = [];

    /** @var array[] Message lists received. */
    public $messages = [];

    /**
     * Complete.
     *
     * @param string $system System prompt.
     * @param array $messages Turns.
     * @param array $options Options.
     * @return completion
     */
    public function complete(string $system, array $messages, array $options = []): completion {
        $this->calls++;
        $this->systems[] = $system;
        $this->messages[] = $messages;
        return new completion('T' . $this->calls, 10, 5, 'stop');
    }

    /**
     * Name.
     *
     * @return string
     */
    public function name(): string {
        return 'fake:model';
    }
}
