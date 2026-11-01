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

/**
 * Reads a PDF or Word file from disk and stores its text for a user, re-using earlier copies.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_service {
    /** @var config Plugin configuration. */
    private $config;

    /** @var document_repository Repository. */
    private $repository;

    /**
     * Constructor.
     *
     * @param config|null $config Configuration (loaded when null).
     * @param document_repository|null $repository Repository.
     */
    public function __construct(?config $config = null, ?document_repository $repository = null) {
        $this->config = $config ?? config::load();
        $this->repository = $repository ?? new document_repository();
    }

    /**
     * Extract and store a PDF or Word (.docx) file for a user.
     *
     * @param string $path Absolute path of the file.
     * @param string $filename Name to show.
     * @param int $userid Owner.
     * @param int $courseid Course id.
     * @param string $source "upload" or "course".
     * @param string $hash Content hash (computed from the file when empty).
     * @return \stdClass The stored document record.
     * @throws exception With codes such as noextractabletext, encrypted, unsupportedtype, toolarge.
     */
    public function ingest(string $path, string $filename, int $userid, int $courseid, string $source = 'upload',
            string $hash = ''): \stdClass {
        $size = (int) filesize($path);
        if ($size > $this->config->max_file_bytes()) {
            throw new exception('toolarge');
        }
        if ($hash === '') {
            $hash = (string) sha1_file($path);
        }
        $existing = $this->repository->find_by_hash($userid, $hash);
        if ($existing) {
            $this->repository->touch((int) $existing->id);
            return $existing;
        }
        if ($source === 'course') {
            $copy = $this->repository->find_course_copy($hash);
            if ($copy) {
                $id = $this->repository->create($userid, $courseid, $filename, $size, $hash, (string) $copy->textcontent,
                    (int) $copy->pages, 'course', (string) $copy->extractor, !empty($copy->lowtext));
                return $this->repository->get_for_user($id, $userid);
            }
        }
        $reader = new document_reader($this->config->string('pdftotextpath'));
        $extraction = $reader->read($path, $this->config->int('maxdocchars'));
        $id = $this->repository->create($userid, $courseid, $filename, $size, $hash, $extraction->text, $extraction->pages,
            $source, $extraction->extractor, $extraction->lowtext);
        return $this->repository->get_for_user($id, $userid);
    }
}
