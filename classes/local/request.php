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
 * Everything the assistant needs to answer one message.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request {
    /** @var string The user's message (empty when a button was used). */
    public $message = '';

    /** @var intent Detected intent. */
    public $intent;

    /** @var string Document file name. */
    public $docname = '';

    /** @var string Document text ('' when no document is attached). */
    public $doctext = '';

    /** @var int Document pages. */
    public $docpages = 0;

    /** @var string Hash identifying the document content (used for caching). */
    public $dochash = '';

    /** @var array Previous turns: [['role' => 'user'|'assistant', 'content' => string], ...]. */
    public $history = [];

    /** @var int Id of the user who made the request (0 when unknown). */
    public $userid = 0;

    /** @var string Interface language code of the user (ar, fr, en, ...). */
    public $uilang = 'en';

    /**
     * Constructor.
     *
     * @param intent $intent Detected intent.
     * @param string $message User message.
     */
    public function __construct(intent $intent, string $message = '') {
        $this->intent = $intent;
        $this->message = $message;
    }

    /**
     * Whether a document is attached.
     *
     * @return bool
     */
    public function has_document(): bool {
        return trim($this->doctext) !== '';
    }
}
