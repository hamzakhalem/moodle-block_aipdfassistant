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
 * Outcome of {@see assistant::handle()}.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class result {
    /** @var string Answer in Markdown. */
    public $text = '';

    /** @var string Action that produced the answer. */
    public $action = '';

    /** @var bool Whether the answer came from the cache. */
    public $cached = false;

    /** @var int Number of provider calls made. */
    public $calls = 0;

    /** @var int Input tokens used. */
    public $inputtokens = 0;

    /** @var int Output tokens used. */
    public $outputtokens = 0;

    /** @var string Notice code for the UI: "", "partial" or "translatetruncated". */
    public $notice = '';

    /** @var string Provider identifier that produced the answer. */
    public $provider = '';
}
