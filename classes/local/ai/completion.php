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
 * Result of a text completion.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class completion {
    /** @var string Generated text (Markdown). */
    public $text;

    /** @var int Prompt tokens reported by the API (0 if unknown). */
    public $inputtokens;

    /** @var int Completion tokens reported by the API (0 if unknown). */
    public $outputtokens;

    /** @var string Finish/stop reason reported by the API. */
    public $finish;

    /**
     * Constructor.
     *
     * @param string $text Generated text.
     * @param int $inputtokens Input tokens.
     * @param int $outputtokens Output tokens.
     * @param string $finish Finish reason.
     */
    public function __construct(string $text, int $inputtokens = 0, int $outputtokens = 0, string $finish = '') {
        $this->text = $text;
        $this->inputtokens = $inputtokens;
        $this->outputtokens = $outputtokens;
        $this->finish = $finish;
    }
}
