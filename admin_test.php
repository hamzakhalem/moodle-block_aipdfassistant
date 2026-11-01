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
 * Administrator page that tests the AI text provider, the voice provider and PDF reading.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_aipdfassistant\local\config;
use block_aipdfassistant\local\errors;
use block_aipdfassistant\local\factory;
use block_aipdfassistant\local\pdf\pdf_reader;
use block_aipdfassistant\local\pdf\sample;
use block_aipdfassistant\local\tts\registry as tts_registry;
use block_aipdfassistant\local\tts\synthesizer;

require_login();
$systemcontext = context_system::instance();
require_capability('moodle/site:config', $systemcontext);

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/blocks/aipdfassistant/admin_test.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('test_title', 'block_aipdfassistant'));
$PAGE->set_heading(get_string('test_title', 'block_aipdfassistant'));

$config = config::load();
$run = optional_param('run', '', PARAM_ALPHA);
if ($run !== '') {
    require_sesskey();
}
core_php_time_limit::raise(300);

/**
 * Render a success or failure alert.
 *
 * @param bool $ok Whether the test passed.
 * @param string $html Alert content (already safe HTML).
 * @return string
 */
function block_aipdfassistant_alert(bool $ok, string $html): string {
    $label = get_string($ok ? 'test_ok' : 'test_failed', 'block_aipdfassistant');
    return html_writer::div(html_writer::tag('strong', $label) . ': ' . $html, 'alert ' . ($ok ? 'alert-success' : 'alert-danger'));
}

/**
 * Render a form with a single button that triggers a test.
 *
 * @param string $run Test id.
 * @param string $label Button label.
 * @return string
 */
function block_aipdfassistant_run_form(string $run, string $label): string {
    global $PAGE;
    return html_writer::tag('form', html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',
            'value' => sesskey()]) . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'run', 'value' => $run])
        . html_writer::tag('button', $label, ['type' => 'submit', 'class' => 'btn btn-primary']),
        ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mb-3']);
}

echo $OUTPUT->header();

// PDF reading.
echo $OUTPUT->heading(get_string('test_pdf_heading', 'block_aipdfassistant'), 3);
$tmp = make_request_directory() . '/sample.pdf';
file_put_contents($tmp, sample::minimal(['Test page one: the quick brown fox.', 'Test page two: jumps over the lazy dog.']));
try {
    $extraction = (new pdf_reader($config->string('pdftotextpath')))->read($tmp);
    $key = $extraction->extractor === 'pdftotext' ? 'test_pdftotext_ok' : 'test_pdftotext_missing';
    echo block_aipdfassistant_alert($extraction->extractor === 'pdftotext',
        s(get_string($key, 'block_aipdfassistant')) . ' ' . html_writer::tag('code', s(substr($extraction->text, 0, 80))));
} catch (Throwable $e) {
    echo block_aipdfassistant_alert(false, s(errors::message($e, true)));
}

// Text provider.
echo $OUTPUT->heading(get_string('test_text_heading', 'block_aipdfassistant'), 3);
if (!$config->is_configured()) {
    echo $OUTPUT->notification(get_string('test_notconfigured', 'block_aipdfassistant'), 'warning');
} else {
    $settings = $config->text_settings();
    echo html_writer::div(s(get_string('test_provider', 'block_aipdfassistant', $settings['label'] . ' / ' . $settings['model'])),
        'mb-2');
    echo block_aipdfassistant_run_form('text', get_string('test_run_text', 'block_aipdfassistant'));
    if ($run === 'text') {
        $start = microtime(true);
        try {
            $provider = factory::text_provider($config);
            $completion = $provider->complete(
                'You are a connection tester.',
                [['role' => 'user', 'content' => get_string('test_prompt', 'block_aipdfassistant')]],
                ['max_tokens' => max(300, $config->int('maxtokens')), 'temperature' => 0.2]
            );
            $seconds = round(microtime(true) - $start, 2);
            echo block_aipdfassistant_alert(true, html_writer::tag('span', s(get_string('test_latency', 'block_aipdfassistant',
                    $seconds))) . html_writer::tag('pre', s($completion->text), ['class' => 'mt-2 mb-0']));
        } catch (Throwable $e) {
            echo block_aipdfassistant_alert(false, s(errors::message($e, true)));
        }
    }
}

// Voice provider.
echo $OUTPUT->heading(get_string('test_voice_heading', 'block_aipdfassistant'), 3);
if (!$config->tts_enabled()) {
    echo $OUTPUT->notification(get_string('test_voice_disabled', 'block_aipdfassistant'), 'info');
} else if ($config->tts_is_browser()) {
    echo $OUTPUT->notification(get_string('test_voice_browser', 'block_aipdfassistant'), 'info');
} else {
    echo block_aipdfassistant_run_form('voice', get_string('test_run_voice', 'block_aipdfassistant'));
    if ($run === 'voice') {
        $start = microtime(true);
        try {
            $provider = tts_registry::create($config, factory::http());
            $settings = $config->tts_settings();
            $audio = (new synthesizer($provider, $settings['voice'], 600))->synthesize(
                get_string('test_voice_sample', 'block_aipdfassistant'));
            $seconds = round(microtime(true) - $start, 2);
            $source = 'data:' . $audio->mime . ';base64,' . base64_encode($audio->bytes);
            echo block_aipdfassistant_alert(true, html_writer::tag('span', s(get_string('test_latency',
                    'block_aipdfassistant', $seconds))) . html_writer::tag('audio', '', ['controls' => 'controls',
                    'src' => $source, 'class' => 'd-block mt-2']));
        } catch (Throwable $e) {
            echo block_aipdfassistant_alert(false, s(errors::message($e, true)));
        }
    }
}

echo $OUTPUT->footer();
