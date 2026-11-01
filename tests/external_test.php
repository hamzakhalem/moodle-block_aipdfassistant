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

use block_aipdfassistant\external\send_message;
use block_aipdfassistant\local\document_repository;
use block_aipdfassistant\local\factory;
use block_aipdfassistant\local\rate_limiter;

require_once(__DIR__ . '/fixtures/fake_http_client.php');

/**
 * Database and web service tests: rate limits, documents, send_message.
 *
 * @package    block_aipdfassistant
 * @category   test
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_aipdfassistant\external\send_message
 * @covers     \block_aipdfassistant\local\rate_limiter
 * @covers     \block_aipdfassistant\local\document_repository
 */
final class external_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private $course;

    /** @var \stdClass Student. */
    private $user;

    /** @var \context_block Block context. */
    private $context;

    /** @var fake_http_client HTTP double. */
    private $http;

    /**
     * Create a course, a student and a block, and configure a custom provider.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->user = $generator->create_and_enrol($this->course, 'student');
        $block = $generator->create_block('aipdfassistant', ['parentcontextid' => \context_course::instance($this->course->id)->id]);
        $this->context = \context_block::instance($block->id);
        set_config('provider', 'custom', 'block_aipdfassistant');
        set_config('baseurl', 'https://llm.test/v1', 'block_aipdfassistant');
        set_config('model', 'test-model', 'block_aipdfassistant');
        $this->http = new fake_http_client();
        factory::set_http_client($this->http);
        $this->setUser($this->user);
    }

    /**
     * Remove the HTTP double.
     */
    protected function tearDown(): void {
        factory::set_http_client(null);
        parent::tearDown();
    }

    /**
     * Queue a model answer.
     *
     * @param string $text Answer.
     */
    private function model_says(string $text): void {
        $this->http->push(200, json_encode(['choices' => [['message' => ['content' => $text]]], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2]]));
    }

    /**
     * Store a document for the student.
     *
     * @param string $text Text.
     * @return int Id.
     */
    private function document(string $text = 'Cells produce energy in mitochondria.'): int {
        return (new document_repository())->create((int) $this->user->id, (int) $this->course->id, 'cells.pdf', 100,
            sha1($text), $text, 4, 'upload', 'native');
    }

    /**
     * A plain question is answered and logged.
     */
    public function test_free_question(): void {
        $this->model_says('Photosynthesis makes sugar.');
        $out = send_message::execute($this->context->id, 0, 'What is photosynthesis?');
        $this->assertTrue($out['success']);
        $this->assertSame('Photosynthesis makes sugar.', $out['reply']);
        $this->assertSame('ask', $out['action']);
        $this->assertSame(1, $GLOBALS["DB"]->count_records('block_aipdfassistant_log', ['userid' => $this->user->id]));
    }

    /**
     * Document tasks without a document ask for one and do not call the AI.
     */
    public function test_document_needed(): void {
        $out = send_message::execute($this->context->id, 0, 'لخص لي هذا الملف');
        $this->assertTrue($out['success']);
        $this->assertTrue($out['docneeded']);
        $this->assertCount(0, $this->http->requests);
    }

    /**
     * Summaries use the document text, and repeating the request is served from the cache.
     */
    public function test_summary_and_cache(): void {
        $docid = $this->document();
        $this->model_says('Summary text');
        $first = send_message::execute($this->context->id, $docid, '', 'summarize');
        $this->assertTrue($first['success']);
        $this->assertFalse($first['cached']);
        $this->assertStringContainsString('Cells produce energy', $this->http->requests[0]['body']);
        $second = send_message::execute($this->context->id, $docid, '', 'summarize');
        $this->assertTrue($second['cached']);
        $this->assertSame('Summary text', $second['reply']);
        $this->assertCount(1, $this->http->requests);
    }

    /**
     * The file name is answered locally.
     */
    public function test_filename_is_local(): void {
        $docid = $this->document();
        $out = send_message::execute($this->context->id, $docid, 'ما هو اسم الملف؟');
        $this->assertStringContainsString('cells.pdf', $out['reply']);
        $this->assertCount(0, $this->http->requests);
    }

    /**
     * Nobody can use another user's document.
     */
    public function test_other_users_document_is_refused(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $id = (new document_repository())->create((int) $other->id, (int) $this->course->id, 'secret.pdf', 1, sha1('s'), 'secret', 1,
            'upload', 'native');
        $out = send_message::execute($this->context->id, $id, '', 'summarize');
        $this->assertFalse($out['success']);
        $this->assertSame('docmissing', $out['errorcode']);
        $this->assertCount(0, $this->http->requests);
    }

    /**
     * Without credentials the user gets a friendly "not configured" error.
     */
    public function test_not_configured(): void {
        set_config('provider', 'openai', 'block_aipdfassistant');
        set_config('apikey', '', 'block_aipdfassistant');
        $out = send_message::execute($this->context->id, 0, 'hello');
        $this->assertFalse($out['success']);
        $this->assertSame('notconfigured', $out['errorcode']);
    }

    /**
     * Provider failures become translated errors and are logged.
     */
    public function test_provider_error_is_reported(): void {
        $this->http->push(401, '{"error":{"message":"bad key"}}');
        $out = send_message::execute($this->context->id, 0, 'hello');
        $this->assertFalse($out['success']);
        $this->assertSame('auth', $out['errorcode']);
        $this->assertSame(1, $GLOBALS["DB"]->count_records('block_aipdfassistant_log', ['status' => 'error', 'errorcode' => 'auth']));
    }

    /**
     * Hourly limit.
     */
    public function test_rate_limit(): void {
        set_config('limit_hour', '1', 'block_aipdfassistant');
        $this->model_says('one');
        $this->assertTrue(send_message::execute($this->context->id, 0, 'first question')['success']);
        $out = send_message::execute($this->context->id, 0, 'second question');
        $this->assertFalse($out['success']);
        $this->assertSame('ratelimit', $out['errorcode']);
    }

    /**
     * Browser voice: the answer is flagged for client side speech.
     */
    public function test_browser_voice(): void {
        set_config('tts_provider', 'browser', 'block_aipdfassistant');
        $docid = $this->document();
        $this->model_says('Résumé');
        $out = send_message::execute($this->context->id, $docid, 'لخص بالصوت');
        $this->assertTrue($out['success']);
        $this->assertTrue($out['speak']);
        $this->assertTrue($out['browsertts']);
    }

    /**
     * Sliding window limits and waiting time.
     */
    public function test_rate_limiter_window(): void {
        $limiter = new rate_limiter();
        $now = time();
        $this->assertNull($limiter->check((int) $this->user->id, 2, 0, $now));
        $limiter->log((int) $this->user->id, 1, 0, 'ask', 'x', 1, 0, 0, 'ok');
        $limiter->log((int) $this->user->id, 1, 0, 'ask', 'x', 1, 0, 0, 'ok');
        $limiter->log((int) $this->user->id, 1, 0, 'summarize', 'x', 0, 0, 0, 'cached', '', true);
        $wait = $limiter->check((int) $this->user->id, 2, 0, $now);
        $this->assertGreaterThan(0, $wait);
        $this->assertLessThanOrEqual(HOURSECS, $wait);
        $this->assertNull($limiter->check((int) $this->user->id, 3, 0, $now), 'cached rows do not count');
        $this->assertNull($limiter->check((int) $this->user->id, 0, 0, $now), 'zero means unlimited');
        $this->assertNull($limiter->check((int) $this->user->id, 2, 0, $now + 2 * HOURSECS), 'window slides');
    }

    /**
     * Repository ownership and purge.
     */
    public function test_repository(): void {
        $repository = new document_repository();
        $id = $this->document();
        $this->assertNotNull($repository->get_for_user($id, (int) $this->user->id));
        $this->assertNull($repository->get_for_user($id, (int) $this->user->id + 1));
        $this->assertFalse($repository->delete($id, (int) $this->user->id + 1));
        $this->assertSame(0, $repository->purge_older_than(time() - DAYSECS));
        $this->assertSame(1, $repository->purge_older_than(time() + 10));
        $this->assertNull($repository->get_for_user($id, (int) $this->user->id));
    }

    /**
     * History cleaning keeps valid turns only.
     */
    public function test_clean_history(): void {
        $clean = send_message::clean_history([
            ['role' => 'user', 'content' => 'a'], ['role' => 'system', 'content' => 'x'], ['role' => 'assistant', 'content' => ' '],
            ['role' => 'assistant', 'content' => 'b'],
        ]);
        $this->assertSame([['role' => 'user', 'content' => 'a'], ['role' => 'assistant', 'content' => 'b']], $clean);
        $this->assertSame('b', send_message::last_assistant($clean));
        $this->assertSame('', send_message::last_assistant([]));
    }
}
