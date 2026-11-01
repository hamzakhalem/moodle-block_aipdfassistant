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


/**
 * Usage report for administrators.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$systemcontext = context_system::instance();
require_capability('block/aipdfassistant:viewreport', $systemcontext);

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/blocks/aipdfassistant/report.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('report_title', 'block_aipdfassistant'));
$PAGE->set_heading(get_string('report_title', 'block_aipdfassistant'));

$since = time() - 30 * DAYSECS;
$table = '{block_aipdfassistant_log}';

$totals = $DB->get_record_sql(
    "SELECT COUNT(1) AS requests,
            SUM(CASE WHEN cached = 1 THEN 1 ELSE 0 END) AS cachedcount,
            SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS errorcount,
            SUM(inputtokens) AS tokensin,
            SUM(outputtokens) AS tokensout
       FROM $table
      WHERE timecreated > :since",
    ['since' => $since]
);
$byaction = $DB->get_records_sql(
    "SELECT action, COUNT(1) AS total
       FROM $table
      WHERE timecreated > :since
   GROUP BY action
   ORDER BY total DESC",
    ['since' => $since]
);
$topusers = $DB->get_records_sql(
    "SELECT userid, COUNT(1) AS total
       FROM $table
      WHERE timecreated > :since
   GROUP BY userid
   ORDER BY total DESC",
    ['since' => $since],
    0,
    10
);
$recent = $DB->get_records_select('block_aipdfassistant_log', 'timecreated > ?', [$since], 'timecreated DESC', '*', 0, 50);

$userids = array_unique(array_merge(array_keys($topusers), array_map(function ($row) {
    return $row->userid;
}, $recent)));
$users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];
$username = function (int $id) use ($users) {
    return isset($users[$id]) ? fullname($users[$id]) : '#' . $id;
};

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_last30', 'block_aipdfassistant'), 3);

if (!$totals || !(int) $totals->requests) {
    echo $OUTPUT->notification(get_string('report_none', 'block_aipdfassistant'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$summary = new html_table();
$summary->head = [
    get_string('report_requests', 'block_aipdfassistant'),
    get_string('report_cached', 'block_aipdfassistant'),
    get_string('report_errors', 'block_aipdfassistant'),
    get_string('report_tokens', 'block_aipdfassistant'),
];
$summary->data = [[
    (int) $totals->requests,
    (int) $totals->cachedcount,
    (int) $totals->errorcount,
    number_format((int) $totals->tokensin) . ' / ' . number_format((int) $totals->tokensout),
]];
echo html_writer::table($summary);

echo $OUTPUT->heading(get_string('report_byaction', 'block_aipdfassistant'), 4);
$actions = new html_table();
$actions->head = [get_string('report_action', 'block_aipdfassistant'), get_string('report_requests', 'block_aipdfassistant')];
foreach ($byaction as $row) {
    $actions->data[] = [s($row->action), (int) $row->total];
}
echo html_writer::table($actions);

echo $OUTPUT->heading(get_string('report_topusers', 'block_aipdfassistant'), 4);
$usertable = new html_table();
$usertable->head = [get_string('report_user', 'block_aipdfassistant'), get_string('report_requests', 'block_aipdfassistant')];
foreach ($topusers as $row) {
    $usertable->data[] = [s($username((int) $row->userid)), (int) $row->total];
}
echo html_writer::table($usertable);

echo $OUTPUT->heading(get_string('report_recent', 'block_aipdfassistant'), 4);
$events = new html_table();
$events->head = [
    get_string('report_time', 'block_aipdfassistant'),
    get_string('report_user', 'block_aipdfassistant'),
    get_string('report_action', 'block_aipdfassistant'),
    get_string('report_provider', 'block_aipdfassistant'),
    get_string('report_status', 'block_aipdfassistant'),
];
foreach ($recent as $row) {
    $status = $row->status . ($row->errorcode !== '' ? ' (' . $row->errorcode . ')' : '');
    $events->data[] = [userdate($row->timecreated), s($username((int) $row->userid)), s($row->action), s($row->provider),
        s($status)];
}
echo html_writer::table($events);
echo $OUTPUT->footer();
