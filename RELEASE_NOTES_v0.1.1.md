## AI PDF Assistant 0.1.1 (beta)

Now available on the Moodle marketplace: https://marketplace.moodle.com/plugins/block_aipdfassistant

Changes made after the plugin directory review:
- Added the GPL v3 `LICENSE` file.
- Safer file reading: time limit and output cap for `pdftotext`, decompressed-size limits for PDF and Word files (both configurable by admins).
- Privacy API now covers cached AI answers (metadata, export, deletion); cached answers are per user (upgrade step included).
- Clearer setup: the settings page and the README explain provider accounts and what is unavailable before configuration.
- Document picker rendered with a Mustache template.
- New tests: decompression bomb, slow and oversized `pdftotext`, per-user cache.

Upgrading from 0.1.0 runs a database upgrade step (adds `userid` to the cache table and clears old cache rows).
