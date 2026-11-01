# AI PDF Assistant (block_aipdfassistant)

A Moodle **block** where students and teachers attach a **PDF or Word (.docx)** file and then chat with it: **summarize, explain, translate, quiz, get the file name, or listen to the answer**. Everything goes through the API of the AI provider the administrator chooses.

## Features
- **Any provider through its API:** OpenAI, Anthropic Claude, Google Gemini, DeepSeek, Ollama, or any OpenAI-compatible server (Groq, OpenRouter, vLLM, LM Studio...).
- **Voice:** OpenAI, Gemini or ElevenLabs voices (the admin picks *male* or *female* and can override the voice ids), or the free browser voice.
- **Understands requests in Arabic, Algerian Darija, French and English** ("لخصلي هذا بالصوت", "traduis en arabe", "explain like I'm five"). Also works with buttons.
- **Chat UI:** a one-row **scrollable action strip** (swipe, drag, mouse wheel, arrow keys, fade + arrow buttons only when something is hidden, RTL aware), drag and drop, pick a PDF already in the course, dictation (mic), copy, waveform audio player, expanded view, full RTL support.
- **Long PDFs:** map-reduce summaries, chunked translation, passage retrieval for questions.
- **Cost and safety:** per-user hourly/daily limits, answer cache, size limits, prompt-injection hardening, API keys never reach the browser, usage report, connection test page, privacy API, scheduled cleanup.
- **Word (.docx) reading:** built in (PHP zip + DOM, no library): headings, lists, tables, text boxes; tracked deletions are skipped. Old binary `.doc` files must be saved as `.docx`.
- **PDF reading:** `pdftotext` (Poppler) when installed, otherwise a built-in PHP parser (text PDFs, Flate/ASCII filters, ToUnicode fonts, object streams). **No OCR**: scanned PDFs are reported as unreadable.

## Requirements
Moodle **4.2+**, PHP 7.4+ (mbstring, zlib, zip; intl recommended). Poppler `pdftotext` is optional but gives the best text.

## Install
Copy this folder to `blocks/aipdfassistant` (Moodle 5.1+: `public/blocks/aipdfassistant`) or upload the zip in *Site administration > Plugins > Install plugins*, then run the upgrade.

## Configure (Site administration > Plugins > Blocks > AI PDF Assistant)
1. Choose the **provider**, paste the **API key**, optionally set the **model** (defaults are only suggestions; model names change often).
2. *Voice*: choose a provider (Claude and DeepSeek have no voice API: pair them with OpenAI/Gemini/ElevenLabs, or use the browser voice) and **male/female**.
3. Click **Test the connection** (top of the settings page) to check PDF reading, text and voice.
4. Add the block to a course page or the Dashboard.

Ollama or any server on `localhost`/a private IP must first be allowed in *Site administration > General > Security > HTTP security*, otherwise Moodle blocks the request.

## Tests
- **PHPUnit** (`tests/*_test.php`): intent detection, text/chunker, providers (scripted HTTP, no network), TTS, PDF parser, assistant orchestration, web services, rate limits, repository.
  `php admin/tool/phpunit/cli/init.php && vendor/bin/phpunit --filter block_aipdfassistant` (or run the plugin testsuite).
- **Behat:** `tests/behat/use_block.feature`.
- **JS:** `node tests/js/markdown.test.js` (Markdown sanitiser) and the browser E2E in `tests/js/e2e/` (Playwright, mocked web services).
- `amd/build/*.min.js` was produced with esbuild. Run `grunt amd` in your Moodle checkout to regenerate it the official way.

## Known limitations
- No OCR for scanned PDFs. Not exposed to the Moodle mobile app.
- Default model names (OpenAI `gpt-5-mini`, Claude `claude-sonnet-5-5`, Gemini `gemini-flash-latest`, Gemini voice `gemini-3.8-flash-tts`, OpenAI voice `gpt-4o-mini-tts`) may change: edit them in the settings.
- **The PHP code was written without a PHP runtime available and has not been executed against a live Moodle.** Run the PHPUnit suite and the connection test before production use.

## Privacy
PDF text and chat messages are sent to the provider you configure; answers read aloud are sent to the voice provider. Tell your users. Documents and audio are deleted after the retention period (default 30 days).

License: GNU GPL v3 or later.
