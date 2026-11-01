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

/**
 * Contract implemented by every text (LLM) provider.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface text_provider_interface {
    /**
     * Run one chat completion.
     *
     * @param string $system System prompt.
     * @param array $messages List of ['role' => 'user'|'assistant', 'content' => string].
     * @param array $options Optional: max_tokens (int), temperature (float).
     * @return completion
     * @throws \block_aipdfassistant\local\exception On any API or transport failure.
     */
    public function complete(string $system, array $messages, array $options = []): completion;

    /**
     * Human readable identifier such as "anthropic:claude-sonnet-5-5" (used in logs and cache keys).
     *
     * @return string
     */
    public function name(): string;
}
