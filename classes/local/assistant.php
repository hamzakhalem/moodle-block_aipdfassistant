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

use block_aipdfassistant\local\ai\text_provider_interface;

/**
 * Orchestrates one answer: builds prompts, handles long documents (map-reduce
 * summaries, chunked translation, retrieval for questions), caches results.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assistant {
    /** Longest message (characters) that may be served from the answer cache. */
    const CACHEABLE_MESSAGE = 40;

    /** @var text_provider_interface LLM provider. */
    private $provider;

    /** @var cache_interface Answer cache. */
    private $cache;

    /** @var config Plugin configuration. */
    private $config;

    /** @var result Result being built. */
    private $result;

    /**
     * Constructor.
     *
     * @param text_provider_interface $provider LLM provider.
     * @param cache_interface $cache Answer cache.
     * @param config $config Plugin configuration.
     */
    public function __construct(text_provider_interface $provider, cache_interface $cache, config $config) {
        $this->provider = $provider;
        $this->cache = $cache;
        $this->config = $config;
    }

    /**
     * Produce the answer for a request.
     *
     * @param request $req Request.
     * @return result
     * @throws exception On provider failures.
     */
    public function handle(request $req): result {
        $this->result = new result();
        $this->result->provider = $this->provider->name();
        $action = $req->intent->action;
        if ($action === intent::SPEAK || $action === intent::FILENAME) {
            $action = intent::SUMMARIZE;
        }
        $this->result->action = $action;
        $key = null;

        if ($req->has_document() && in_array($action, [intent::SUMMARIZE, intent::EXPLAIN, intent::TRANSLATE, intent::QUIZ], true)) {
            $key = $this->cache_key($req, $action);
            if ($key !== null) {
                $hit = $this->cache->get($key);
                if ($hit !== null) {
                    $data = json_decode($hit, true);
                    if (is_array($data) && isset($data['text'])) {
                        $this->result->text = (string) $data['text'];
                        $this->result->notice = (string) ($data['notice'] ?? '');
                        $this->result->cached = true;
                        return $this->result;
                    }
                }
            }
        }

        switch ($action) {
            case intent::SUMMARIZE:
            case intent::EXPLAIN:
                $this->summarize_or_explain($req, $action);
                break;
            case intent::TRANSLATE:
                $this->translate($req);
                break;
            case intent::QUIZ:
                $this->quiz($req);
                break;
            default:
                $this->ask($req);
        }

        if ($key !== null && $this->result->text !== '') {
            $this->cache->set($key, json_encode(['text' => $this->result->text, 'notice' => $this->result->notice],
                JSON_UNESCAPED_UNICODE), $action);
        }
        return $this->result;
    }

    /**
     * Cache key for whole-document tasks, or null when the request must not be cached.
     *
     * @param request $req Request.
     * @param string $action Resolved action.
     * @return string|null
     */
    public function cache_key(request $req, string $action): ?string {
        $message = text::normalize($req->message);
        if ($req->dochash === '' || mb_strlen($message, 'UTF-8') > self::CACHEABLE_MESSAGE) {
            return null;
        }
        return sha1(implode('|', [
            prompt_builder::VERSION, $req->dochash, $action, $this->effective_language($req), $message,
            $this->provider->name(), $this->config->string('responselang'), $req->uilang,
            sha1($this->config->string('systemextra')),
        ]));
    }

    /**
     * Language the answer will use ('' when it follows the request).
     *
     * @param request $req Request.
     * @return string
     */
    private function effective_language(request $req): string {
        $lang = $req->intent->language;
        if ($lang === '' && $req->intent->action === intent::TRANSLATE) {
            $lang = isset(intent::LANGUAGES[$req->uilang]) ? $req->uilang : 'en';
        }
        return $lang;
    }

    /**
     * Base system prompt for a request.
     *
     * @param request $req Request.
     * @return string
     */
    private function base_system(request $req): string {
        $line = prompt_builder::language_line($req->intent->language, $this->config->string('responselang'), $req->uilang);
        return prompt_builder::system($line, $this->config->string('systemextra'));
    }

    /**
     * Call the provider and account for usage.
     *
     * @param string $system System prompt.
     * @param array $messages Turns.
     * @return string Answer text.
     */
    private function call(string $system, array $messages): string {
        $completion = $this->provider->complete($system, $messages, [
            'max_tokens' => max(200, $this->config->int('maxtokens')),
            'temperature' => (float) $this->config->get('temperature'),
        ]);
        $this->result->calls++;
        $this->result->inputtokens += $completion->inputtokens;
        $this->result->outputtokens += $completion->outputtokens;
        return trim($completion->text);
    }

    /**
     * Maximum characters of document text per call.
     *
     * @return int
     */
    private function budget(): int {
        return max(4000, $this->config->int('maxcontextchars'));
    }

    /**
     * Summaries and explanations, with map-reduce for long documents.
     *
     * @param request $req Request.
     * @param string $action Action.
     * @return void
     */
    private function summarize_or_explain(request $req, string $action): void {
        $budget = $this->budget();
        $base = $this->base_system($req);
        if (!$req->has_document()) {
            $this->result->text = $this->call($base, [['role' => 'user', 'content' => $req->message]]);
            return;
        }
        $length = mb_strlen($req->doctext, 'UTF-8');
        if ($length <= $budget) {
            $system = $base . "\n\n" . prompt_builder::document_block($req->docname, $req->docpages, $req->doctext);
            $this->result->text = $this->call($system, [
                ['role' => 'user', 'content' => prompt_builder::task($action, $req->message)],
            ]);
            return;
        }
        $chunks = chunker::split($req->doctext, (int) floor($budget * 0.8));
        $maxchunks = max(2, $this->config->int('maxchunks'));
        if (count($chunks) > $maxchunks) {
            $chunks = $this->evenly($chunks, $maxchunks);
            $this->result->notice = 'partial';
        }
        $partials = [];
        $total = count($chunks);
        foreach ($chunks as $i => $chunk) {
            $system = $base . "\n\n" . prompt_builder::document_block($req->docname, $req->docpages, $chunk);
            $partials[] = $this->call($system, [
                ['role' => 'user', 'content' => prompt_builder::partial_task($action, $i + 1, $total)],
            ]);
        }
        $this->result->text = $this->call($base, [
            ['role' => 'user', 'content' => prompt_builder::merge_task($action, $partials, $req->message)],
        ]);
    }

    /**
     * Translation in sequential chunks.
     *
     * @param request $req Request.
     * @return void
     */
    private function translate(request $req): void {
        $lang = $this->effective_language($req);
        $langname = intent::LANGUAGES[$lang] ?? 'English';
        $text = $req->has_document() ? $req->doctext : $req->message;
        $size = max(1500, 2 * max(200, $this->config->int('maxtokens')));
        $chunks = chunker::split($text, $size);
        $maxchunks = max(1, $this->config->int('maxchunks'));
        if (count($chunks) > $maxchunks) {
            $chunks = array_slice($chunks, 0, $maxchunks);
            $this->result->notice = 'translatetruncated';
        }
        $system = prompt_builder::translate_system($langname);
        $total = count($chunks);
        $parts = [];
        foreach ($chunks as $i => $chunk) {
            $parts[] = $this->call($system, [
                ['role' => 'user', 'content' => prompt_builder::translate_user($chunk, $i + 1, $total)],
            ]);
        }
        $this->result->text = implode("\n\n", $parts);
    }

    /**
     * Quiz generation from (a sample of) the document.
     *
     * @param request $req Request.
     * @return void
     */
    private function quiz(request $req): void {
        $excerpt = chunker::sample($req->doctext, $this->budget());
        $system = $this->base_system($req) . "\n\n" . prompt_builder::document_block($req->docname, $req->docpages, $excerpt);
        $this->result->text = $this->call($system, [
            ['role' => 'user', 'content' => prompt_builder::task(intent::QUIZ, $req->message)],
        ]);
    }

    /**
     * Free questions, answered from the most relevant passages.
     *
     * @param request $req Request.
     * @return void
     */
    private function ask(request $req): void {
        $messages = $req->history;
        $messages[] = ['role' => 'user', 'content' => $req->message];
        $system = $this->base_system($req);
        if ($req->has_document()) {
            $excerpt = chunker::top_chunks($req->doctext, $req->message, $this->budget());
            $system .= "\n\n" . prompt_builder::document_block($req->docname, $req->docpages, $excerpt)
                . "\n\n" . prompt_builder::task(intent::ASK, '');
        } else {
            $system .= "\n\nNo document is attached. Answer helpfully and briefly; if the question is about a specific "
                . 'document, invite the user to attach a PDF.';
        }
        $this->result->text = $this->call($system, $messages);
    }

    /**
     * Pick $count items evenly spread over a list.
     *
     * @param array $items Items.
     * @param int $count Number to pick.
     * @return array
     */
    private function evenly(array $items, int $count): array {
        $total = count($items);
        $picked = [];
        for ($i = 0; $i < $count; $i++) {
            $picked[] = $items[(int) floor($i * ($total - 1) / max(1, $count - 1))];
        }
        return $picked;
    }
}
