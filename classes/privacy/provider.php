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


namespace block_aipdfassistant\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy provider for block_aipdfassistant.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {
    /**
     * Describe the stored data.
     *
     * @param collection $collection Collection to fill.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_aipdfassistant_docs', [
            'userid' => 'privacy:metadata:docs:userid',
            'courseid' => 'privacy:metadata:docs:courseid',
            'filename' => 'privacy:metadata:docs:filename',
            'textcontent' => 'privacy:metadata:docs:textcontent',
            'timecreated' => 'privacy:metadata:docs:timecreated',
        ], 'privacy:metadata:docs');
        $collection->add_database_table('block_aipdfassistant_log', [
            'userid' => 'privacy:metadata:log:userid',
            'action' => 'privacy:metadata:log:action',
            'provider' => 'privacy:metadata:log:provider',
            'timecreated' => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:audio');
        $collection->add_external_location_link('aiprovider', [
            'text' => 'privacy:metadata:externalai:text',
        ], 'privacy:metadata:externalai');
        $collection->add_external_location_link('voiceprovider', [
            'text' => 'privacy:metadata:externalvoice:text',
        ], 'privacy:metadata:externalvoice');
        return $collection;
    }

    /**
     * Contexts holding data of a user: the user context (documents, log) and block contexts (audio).
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
              WHERE ctx.contextlevel = :userlevel
                AND ctx.instanceid = :userid
                AND (EXISTS (SELECT 1 FROM {block_aipdfassistant_docs} d WHERE d.userid = :docuser)
                     OR EXISTS (SELECT 1 FROM {block_aipdfassistant_log} l WHERE l.userid = :loguser))",
            ['userlevel' => CONTEXT_USER, 'userid' => $userid, 'docuser' => $userid, 'loguser' => $userid]
        );
        $contextlist->add_from_sql(
            "SELECT DISTINCT f.contextid AS id
               FROM {files} f
              WHERE f.component = :component AND f.filearea = :area AND f.itemid = :itemid AND f.filename <> '.'",
            ['component' => 'block_aipdfassistant', 'area' => 'audio', 'itemid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist User list to fill.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER) {
            $userid = (int) $context->instanceid;
            if ($DB->record_exists('block_aipdfassistant_docs', ['userid' => $userid])
                    || $DB->record_exists('block_aipdfassistant_log', ['userid' => $userid])) {
                $userlist->add_user($userid);
            }
        } else if ($context->contextlevel == CONTEXT_BLOCK) {
            $userlist->add_from_sql('userid',
                "SELECT DISTINCT f.itemid AS userid
                   FROM {files} f
                  WHERE f.contextid = :contextid AND f.component = :component AND f.filearea = :area AND f.filename <> '.'",
                ['contextid' => $context->id, 'component' => 'block_aipdfassistant', 'area' => 'audio']);
        }
    }

    /**
     * Export the data of a user.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        $plugin = get_string('pluginname', 'block_aipdfassistant');
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === $userid) {
                $docs = [];
                foreach ($DB->get_records('block_aipdfassistant_docs', ['userid' => $userid], 'id ASC') as $doc) {
                    $docs[] = (object) [
                        'filename' => $doc->filename,
                        'pages' => (int) $doc->pages,
                        'source' => $doc->source,
                        'text' => $doc->textcontent,
                        'timecreated' => transform::datetime($doc->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [$plugin, get_string('privacy:path:documents', 'block_aipdfassistant')], (object) ['documents' => $docs]);
                $events = [];
                foreach ($DB->get_records('block_aipdfassistant_log', ['userid' => $userid], 'id ASC') as $row) {
                    $events[] = (object) [
                        'action' => $row->action,
                        'provider' => $row->provider,
                        'status' => $row->status,
                        'timecreated' => transform::datetime($row->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [$plugin, get_string('privacy:path:activity', 'block_aipdfassistant')], (object) ['activity' => $events]);
            } else if ($context->contextlevel == CONTEXT_BLOCK) {
                writer::with_context($context)->export_area_files([$plugin], 'block_aipdfassistant', 'audio', $userid);
            }
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel == CONTEXT_BLOCK) {
            get_file_storage()->delete_area_files($context->id, 'block_aipdfassistant', 'audio');
        } else if ($context->contextlevel == CONTEXT_USER) {
            self::delete_database_rows((int) $context->instanceid);
        }
    }

    /**
     * Delete the data of one user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === $userid) {
                self::delete_database_rows($userid);
            } else if ($context->contextlevel == CONTEXT_BLOCK) {
                get_file_storage()->delete_area_files($context->id, 'block_aipdfassistant', 'audio', $userid);
            }
        }
    }

    /**
     * Delete the data of several users in one context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        foreach ($userlist->get_userids() as $userid) {
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === (int) $userid) {
                self::delete_database_rows((int) $userid);
            } else if ($context->contextlevel == CONTEXT_BLOCK) {
                get_file_storage()->delete_area_files($context->id, 'block_aipdfassistant', 'audio', (int) $userid);
            }
        }
    }

    /**
     * Delete documents and log rows of a user.
     *
     * @param int $userid User id.
     * @return void
     */
    private static function delete_database_rows(int $userid): void {
        global $DB;
        $DB->delete_records('block_aipdfassistant_docs', ['userid' => $userid]);
        $DB->delete_records('block_aipdfassistant_log', ['userid' => $userid]);
    }
}
