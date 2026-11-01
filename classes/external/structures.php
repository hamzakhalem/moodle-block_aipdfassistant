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


namespace block_aipdfassistant\external;

use core_external\external_single_structure;
use core_external\external_value;

/**
 * Shared return structures of the web services.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class structures {
    /**
     * Description of a stored document.
     *
     * @param int $required VALUE_REQUIRED or VALUE_OPTIONAL.
     * @return external_single_structure
     */
    public static function document(int $required = VALUE_REQUIRED): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Document id'),
            'name' => new external_value(PARAM_TEXT, 'File name'),
            'pages' => new external_value(PARAM_INT, 'Page count'),
            'chars' => new external_value(PARAM_INT, 'Extracted characters'),
            'lowtext' => new external_value(PARAM_BOOL, 'Little text found, maybe scanned pages'),
            'source' => new external_value(PARAM_ALPHA, 'upload or course'),
            'timemodified' => new external_value(PARAM_INT, 'Last used'),
        ], 'Document', $required);
    }
}
