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
 * Strings for component 'block_aipdfassistant', language 'en'.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_ask'] = 'answer questions about it';
$string['action_explain'] = 'explain it';
$string['action_filename'] = 'tell you its name';
$string['action_quiz'] = 'quiz you on it';
$string['action_speak'] = 'read it aloud';
$string['action_summarize'] = 'summarize it';
$string['action_translate'] = 'translate it';
$string['aipdfassistant:addinstance'] = 'Add a new AI PDF Assistant block';
$string['aipdfassistant:myaddinstance'] = 'Add a new AI PDF Assistant block to the Dashboard';
$string['aipdfassistant:use'] = 'Use the AI PDF Assistant';
$string['aipdfassistant:usespeech'] = 'Listen to answers read aloud';
$string['aipdfassistant:viewreport'] = 'View the AI PDF Assistant usage report';
$string['blocktitle_default'] = 'Study assistant';
$string['config_title'] = 'Block title';
$string['error_auth'] = 'The AI service refused the API key. The administrator must check it.';
$string['error_badmodel'] = 'The configured AI model was not found. The administrator must check the model name.';
$string['error_badrequest'] = 'The AI service rejected the request.';
$string['error_badresponse'] = 'The AI service sent an unexpected answer.';
$string['error_docmissing'] = 'That document is no longer available. Attach it again.';
$string['error_emptyaudio'] = 'The voice service returned no audio.';
$string['error_emptylength'] = 'The answer was cut off before it started. The administrator should raise the maximum answer length.';
$string['error_emptyresponse'] = 'The AI service returned an empty answer. Try again.';
$string['error_encrypted'] = 'This PDF is password protected and cannot be read.';
$string['error_generic'] = 'Something went wrong. Please try again.';
$string['error_network'] = 'Could not reach the AI service. Check the server\'s internet access.';
$string['error_networkblocked'] = 'Moodle blocked the connection (HTTP security settings). The administrator must allow this host.';
$string['error_noextractabletext'] = 'No readable text found. The PDF may be scanned images; OCR is not supported yet.';
$string['error_nofile'] = 'No file was received.';
$string['error_notconfigured'] = 'The assistant is not configured. Please contact your administrator.';
$string['error_nothingtoread'] = 'There is nothing to read aloud.';
$string['error_notpdf'] = 'This file is not a PDF.';
$string['error_providerlimit'] = 'The AI service is busy or its quota is used up. Try again later.';
$string['error_ratelimit'] = 'You have reached the usage limit. Try again in about {$a} minute(s).';
$string['error_timeout'] = 'Reading this file took too long and was stopped. Try a smaller or simpler file.';
$string['error_toolarge'] = 'The file is larger than the allowed size, or it expands to too much data when it is read.';
$string['error_toolong'] = 'This document is too long for the configured model.';
$string['error_ttsdisabled'] = 'Voice output is disabled on this site.';
$string['error_ttsnotconfigured'] = 'Voice output is not configured.';
$string['error_unavailable'] = 'The AI service is temporarily unavailable. Try again in a moment.';
$string['error_unreadable'] = 'The file could not be read.';
$string['error_unsupportedtype'] = 'This file type is not supported. Use a PDF or a Word (.docx) file; old .doc files must be saved as .docx first.';
$string['filenamereply'] = 'The file is **{$a->name}** ({$a->pages} pages).';
$string['gender_female'] = 'Female';
$string['gender_male'] = 'Male';
$string['js_assistant'] = 'Assistant';
$string['js_attach'] = 'Attach a PDF or Word file';
$string['js_audioFailed'] = 'The audio could not be played.';
$string['js_browserVoice'] = 'Browser voice';
$string['js_cached'] = 'Saved answer';
$string['js_clear'] = 'New conversation';
$string['js_collapse'] = 'Close expanded view';
$string['js_copied'] = 'Copied';
$string['js_copy'] = 'Copy';
$string['js_courseFiles'] = 'Files in this course';
$string['js_docAttached'] = 'Document attached: {$a}';
$string['js_dropHint'] = 'or click to choose a file · up to {$a} MB';
$string['js_dropTitle'] = 'Drop a PDF or Word file here';
$string['js_emptyHint'] = 'Add a PDF or Word file to summarize, explain, translate or hear it. You can write or dictate in Arabic, French or English.';
$string['js_emptyTitle'] = 'Your study desk';
$string['js_errorGeneric'] = 'Something went wrong. Please try again.';
$string['js_errorSize'] = 'This file is larger than {$a} MB.';
$string['js_errorType'] = 'Please choose a PDF or Word (.docx) file.';
$string['js_expand'] = 'Expand';
$string['js_explain'] = 'Explain';
$string['js_filename'] = 'File name';
$string['js_listen'] = 'Listen';
$string['js_loading'] = 'Loading…';
$string['js_lowtext'] = 'Little text was found in this file. Scanned pages cannot be read.';
$string['js_mic'] = 'Dictate';
$string['js_micStop'] = 'Stop dictation';
$string['js_noDocs'] = 'Nothing here yet.';
$string['js_noticePartial'] = 'The document is long, so this answer is based on evenly spaced parts of it.';
$string['js_noticeTranslate'] = 'Only the first part of the document was translated.';
$string['js_page'] = '1 page';
$string['js_pages'] = '{$a} pages';
$string['js_pickDocument'] = 'Recent & course files';
$string['js_placeholder'] = 'Ask about the document…';
$string['js_placeholderNoDoc'] = 'Attach a PDF or Word file, or ask anything…';
$string['js_quiz'] = 'Quiz me';
$string['js_reading'] = 'Reading the PDF…';
$string['js_ready'] = 'Ready. Choose an action below or ask a question.';
$string['js_recent'] = 'Recent files';
$string['js_removeDoc'] = 'Remove document';
$string['js_retry'] = 'Try again';
$string['js_send'] = 'Send';
$string['js_speak'] = 'Read aloud';
$string['js_speaking'] = 'Reading aloud…';
$string['js_stop'] = 'Stop';
$string['js_summarize'] = 'Summarize';
$string['js_thinking'] = 'Thinking…';
$string['js_translate'] = 'Translate';
$string['js_translateTo'] = 'Translate to';
$string['js_uploadFailed'] = 'Upload failed';
$string['js_uploading'] = 'Uploading…';
$string['js_you'] = 'You';
$string['lang_auto'] = 'Automatic';
$string['link_report'] = 'Usage report';
$string['link_test'] = 'Test the connection';
$string['link_tools_desc'] = '<a href="{$a->test}">Test the connection</a> · <a href="{$a->report}">Usage report</a>';
$string['needdoc'] = 'Attach a PDF or Word file first, then I can {$a}.';
$string['notconfigured_admin'] = 'The assistant is not set up yet, so users only see this notice instead of the chat: summaries, explanations, translations, quizzes, questions and audio are all unavailable until an AI provider is configured. Choose a provider and enter the API key from your account with it (or the address of your own Ollama server).';
$string['notconfigured_user'] = 'The assistant is not available yet. Please ask your administrator.';
$string['opensettings'] = 'Open settings';
$string['pluginname'] = 'AI PDF Assistant';
$string['privacy:metadata:audio'] = 'Generated audio files of answers read aloud';
$string['privacy:metadata:cache'] = 'Cached AI answers (summaries, explanations, translations, quizzes) generated from a user\'s document';
$string['privacy:metadata:cache:action'] = 'The action that produced the answer';
$string['privacy:metadata:cache:response'] = 'The generated answer text';
$string['privacy:metadata:cache:timecreated'] = 'When the answer was generated';
$string['privacy:metadata:cache:userid'] = 'The user who requested the answer';
$string['privacy:metadata:docs'] = 'Text extracted from the PDF or Word files a user attached';
$string['privacy:metadata:docs:courseid'] = 'The course where the block was used';
$string['privacy:metadata:docs:filename'] = 'The file name';
$string['privacy:metadata:docs:textcontent'] = 'The text extracted from the PDF';
$string['privacy:metadata:docs:timecreated'] = 'When the document was added';
$string['privacy:metadata:docs:userid'] = 'The owner of the document';
$string['privacy:metadata:externalai'] = 'Text of the PDF and the user\'s messages are sent to the AI provider chosen by the administrator to produce answers';
$string['privacy:metadata:externalai:text'] = 'The document text and the conversation';
$string['privacy:metadata:externalvoice'] = 'Answer text is sent to the voice provider chosen by the administrator to produce audio';
$string['privacy:metadata:externalvoice:text'] = 'The text to read aloud';
$string['privacy:metadata:log'] = 'Usage log used for rate limiting and reports';
$string['privacy:metadata:log:action'] = 'The action requested';
$string['privacy:metadata:log:provider'] = 'The AI provider used';
$string['privacy:metadata:log:timecreated'] = 'When the request was made';
$string['privacy:metadata:log:userid'] = 'The user who made the request';
$string['privacy:path:activity'] = 'Activity';
$string['privacy:path:cache'] = 'Cached answers';
$string['privacy:path:documents'] = 'Documents';
$string['report_action'] = 'Action';
$string['report_byaction'] = 'By action';
$string['report_cached'] = 'Saved answers';
$string['report_errors'] = 'Errors';
$string['report_last30'] = 'Last 30 days';
$string['report_none'] = 'No activity yet.';
$string['report_provider'] = 'Provider';
$string['report_recent'] = 'Latest events';
$string['report_requests'] = 'Requests';
$string['report_status'] = 'Status';
$string['report_time'] = 'Time';
$string['report_title'] = 'AI PDF Assistant: usage report';
$string['report_tokens'] = 'Tokens (in / out)';
$string['report_topusers'] = 'Most active users';
$string['report_user'] = 'User';
$string['setting_allowcoursepdfs'] = 'Allow choosing PDF and Word files from the course';
$string['setting_allowcoursepdfs_desc'] = 'Users only see files they can already open.';
$string['setting_apikey'] = 'API key';
$string['setting_apikey_desc'] = 'Stored on the server and never sent to browsers. Leave empty for Ollama or servers without authentication.';
$string['setting_baseurl'] = 'API base URL';
$string['setting_baseurl_desc'] = 'Optional. Required for "Custom". Example for Ollama: http://localhost:11434/v1';
$string['setting_cachedays'] = 'Keep saved answers for (days)';
$string['setting_cachedays_desc'] = 'Identical summaries, explanations and translations of the same PDF are served from the saved copy.';
$string['setting_extracttimeout'] = 'Time limit for reading a file (seconds)';
$string['setting_extracttimeout_desc'] = 'Reading a document is stopped after this time, so that a damaged or hostile file cannot keep the server busy. It covers the pdftotext program and the built-in reader.';
$string['setting_limit_day'] = 'Requests per user per day';
$string['setting_limit_day_desc'] = '0 = unlimited.';
$string['setting_limit_hour'] = 'Requests per user per hour';
$string['setting_limit_hour_desc'] = '0 = unlimited. Saved answers do not count.';
$string['setting_maxchunks'] = 'Maximum parts per request';
$string['setting_maxchunks_desc'] = 'Each part is one API call, so this caps the cost of one long document.';
$string['setting_maxcontextchars'] = 'Document characters per request';
$string['setting_maxcontextchars_desc'] = 'Longer documents are summarized in parts and merged. About 4 characters equal one token.';
$string['setting_maxdecompressedmb'] = 'Largest amount of data a file may expand to (MB)';
$string['setting_maxdecompressedmb_desc'] = 'Protects against decompression bombs: a PDF is refused when its compressed streams would expand beyond this total (built-in reader), or when pdftotext produces more text than this. Word files are limited in the same way.';
$string['setting_maxdocchars'] = 'Maximum characters kept per document';
$string['setting_maxdocchars_desc'] = '';
$string['setting_maxfilesize'] = 'Maximum file size (MB)';
$string['setting_maxfilesize_desc'] = 'Also limited by PHP and Moodle upload limits.';
$string['setting_maxtokens'] = 'Maximum answer length (tokens)';
$string['setting_maxtokens_desc'] = 'Reasoning models spend part of this budget on thinking; use at least 2000.';
$string['setting_model'] = 'Model';
$string['setting_model_desc'] = 'Model id exactly as the provider names it. Leave empty to use the suggested default (OpenAI: gpt-5-mini, Claude: claude-sonnet-5-5, Gemini: gemini-flash-latest, DeepSeek: deepseek-chat). Model names change often, check your provider\'s documentation.';
$string['setting_pdftotextpath'] = 'pdftotext path';
$string['setting_pdftotextpath_desc'] = 'Poppler\'s pdftotext gives the best text. If it is not installed the built-in PHP reader is used automatically. Empty disables pdftotext.';
$string['setting_provider'] = 'Provider';
$string['setting_provider_desc'] = 'OpenAI, Claude, Gemini and DeepSeek work with just an API key. Ollama and "Custom" accept any OpenAI-compatible server. Servers on localhost or private networks must first be allowed in Site administration > General > Security > HTTP security.';
$string['setting_responselang'] = 'Answer language';
$string['setting_responselang_desc'] = '"Automatic" answers in the language of the question.';
$string['setting_retentiondays'] = 'Keep documents and audio for (days)';
$string['setting_retentiondays_desc'] = 'Older items are deleted by a scheduled task.';
$string['setting_systemextra'] = 'Extra instructions';
$string['setting_systemextra_desc'] = 'Added to every request, e.g. "Use the vocabulary of first-year biology students."';
$string['setting_temperature'] = 'Temperature';
$string['setting_temperature_desc'] = 'Lower is more factual. Ignored by models that do not allow changing it.';
$string['setting_timeout'] = 'Request timeout (seconds)';
$string['setting_timeout_desc'] = '';
$string['setting_tts_apikey'] = 'Voice API key';
$string['setting_tts_apikey_desc'] = 'Leave empty to reuse the text provider key when both use the same vendor.';
$string['setting_tts_baseurl'] = 'Voice API base URL';
$string['setting_tts_baseurl_desc'] = 'Optional. Required for "Custom".';
$string['setting_tts_gender'] = 'Voice';
$string['setting_tts_gender_desc'] = 'The administrator chooses whether answers are read by a male or a female voice.';
$string['setting_tts_maxchars'] = 'Maximum characters read aloud';
$string['setting_tts_maxchars_desc'] = 'Longer answers are read up to this limit to control cost.';
$string['setting_tts_model'] = 'Voice model';
$string['setting_tts_model_desc'] = 'Empty = default (OpenAI: gpt-4o-mini-tts, Gemini: gemini-3.8-flash-tts, ElevenLabs: eleven_multilingual_v2).';
$string['setting_tts_provider'] = 'Voice provider';
$string['setting_tts_provider_desc'] = 'Claude and DeepSeek have no voice API, so pair them with OpenAI, Gemini or ElevenLabs here. The browser voice costs nothing but quality and the male/female choice depend on the user\'s device.';
$string['setting_tts_voice_female'] = 'Female voice id';
$string['setting_tts_voice_female_desc'] = 'Empty = default (OpenAI: nova, Gemini: Kore, ElevenLabs: Rachel).';
$string['setting_tts_voice_male'] = 'Male voice id';
$string['setting_tts_voice_male_desc'] = 'Empty = default (OpenAI: onyx, Gemini: Charon, ElevenLabs: Adam).';
$string['settings_behaviour'] = 'Behaviour and limits';
$string['settings_docs'] = 'Documents';
$string['settings_gettingstarted'] = 'Before you start: AI provider accounts';
$string['settings_gettingstarted_desc'] = '<p>This plugin does not include any AI. It sends the text of the document a user attaches, and the user\'s questions, to an AI service that <strong>you</strong> choose and pay for. You need an account with that provider and an API key from it:</p><ul><li><strong>OpenAI</strong>: create an account at platform.openai.com, add billing credit, then create an API key. A ChatGPT subscription does not include API access.</li><li><strong>Claude (Anthropic)</strong>: create an account at console.anthropic.com, add credit and create an API key.</li><li><strong>Google Gemini</strong>: create a key at aistudio.google.com/apikey. A limited free tier exists, paid usage is billed by Google.</li><li><strong>DeepSeek</strong>: create an account at platform.deepseek.com, add credit and create an API key.</li><li><strong>Ollama or any OpenAI-compatible server</strong>: no account is needed. Run the server yourself (see ollama.com), enter its address as the base URL, and allow that host in Site administration &gt; General &gt; Security &gt; HTTP security if it is on localhost or a private network.</li></ul><p><strong>Until a provider is configured</strong> the block shows a notice instead of the chat, so nothing can be summarized, explained, translated, quizzed or answered, and nothing is sent to any provider. The Listen button only appears once a voice is chosen below (the free browser voice needs no account). Providers bill by usage: use the hourly and daily limits in the Behaviour section to control cost, and make sure your privacy policy mentions the provider you pick, because document text is sent to it.</p>';
$string['settings_text'] = 'AI text provider';
$string['settings_text_desc'] = 'Choose which AI service writes summaries, explanations, translations and answers. Everything goes through its API, nothing runs inside Moodle.';
$string['settings_tools'] = 'Tools';
$string['settings_voice'] = 'Voice (text to speech)';
$string['settings_voice_desc'] = 'Optional. Lets users hear answers read aloud. Without a voice the Listen button is hidden. Pick a voice API for studio quality (it needs an account and API key with that provider), or the free browser voice, which needs no account.';
$string['speaklast'] = 'Here is the audio version of my last answer.';
$string['task_cleanup'] = 'Clean up AI PDF Assistant data';
$string['test_failed'] = 'Failed';
$string['test_latency'] = 'Response time: {$a} s';
$string['test_notconfigured'] = 'The text provider is not configured yet.';
$string['test_ok'] = 'Success';
$string['test_pdf_heading'] = 'PDF and Word reading';
$string['test_pdftotext_missing'] = 'pdftotext was not found; the built-in PHP reader will be used (text-based PDFs only).';
$string['test_pdftotext_ok'] = 'pdftotext is available.';
$string['test_prompt'] = 'Reply with one short sentence in Arabic, French and English saying the connection works.';
$string['test_provider'] = 'Provider: {$a}';
$string['test_run_text'] = 'Run the text test';
$string['test_run_voice'] = 'Run the voice test';
$string['test_text_heading'] = 'Text provider';
$string['test_title'] = 'AI PDF Assistant: connection test';
$string['test_voice_browser'] = 'Voice uses the browser, nothing to test on the server.';
$string['test_voice_disabled'] = 'Voice is disabled.';
$string['test_voice_heading'] = 'Voice provider';
$string['test_voice_sample'] = 'Hello! This is a test of the study assistant voice. مرحبا، هذا اختبار صوت مساعد الدراسة.';
$string['tts_browser'] = 'Browser voice (free)';
$string['tts_custom'] = 'Custom (OpenAI-compatible)';
$string['tts_elevenlabs'] = 'ElevenLabs';
$string['tts_gemini'] = 'Google Gemini';
$string['tts_none'] = 'Disabled';
$string['tts_openai'] = 'OpenAI';
