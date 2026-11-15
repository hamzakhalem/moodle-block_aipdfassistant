# Changelog

## 0.1.1 (2026-11-15) - changes requested in the plugin directory review (MMR-553)
- Added the GPL v3 `LICENSE` file (issue 1).
- PDF reading is bounded (issue 2): `pdftotext` runs under a time limit and an output cap and is killed when it exceeds them; the built-in parser inflates streams in slices and refuses files that expand beyond 32 MB per stream or the configured total, with the same time limit; Word files are measured while decompressed. New settings *Time limit for reading a file* and *Largest amount of data a file may expand to*, new error message `error_timeout`.
- Cached AI answers are now declared in the Privacy API (issue 3): metadata, export and deletion. Cache rows belong to the user who requested them (`userid` column, upgrade step) and are no longer shared between users.
- The settings page explains provider accounts and API keys (OpenAI, Claude, Gemini, DeepSeek, Ollama/compatible servers), and the block and the README state what is unavailable until a provider is configured (issue 4).
- The document picker is rendered with the `block_aipdfassistant/picker` Mustache template instead of HTML built in JavaScript (issue 6).
- New tests: decompression bomb, slow and over-producing `pdftotext`, per-user answer cache.

## 0.1.0
- First release.
