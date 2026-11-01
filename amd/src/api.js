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
 * Client for the web services and the upload endpoint of the AI PDF Assistant block.
 *
 * Arguments are always sent in the order the PHP functions declare them.
 *
 * @module     block_aipdfassistant/api
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    'use strict';

    const call = (methodname, args) => Ajax.call([{methodname: methodname, args: args}])[0];

    return {
        /**
         * Send a chat message.
         *
         * @param {Object} a contextid, docid, message, action, targetlang, history.
         * @return {Promise}
         */
        sendMessage: (a) => call('block_aipdfassistant_send_message', {
            contextid: a.contextid,
            docid: a.docid || 0,
            message: a.message || '',
            action: a.action || 'auto',
            targetlang: a.targetlang || '',
            history: a.history || []
        }),

        /**
         * Generate speech for a message.
         *
         * @param {Number} contextid Block context id.
         * @param {String} text Markdown text.
         * @return {Promise}
         */
        synthesize: (contextid, text) => call('block_aipdfassistant_synthesize', {contextid: contextid, text: text}),

        /**
         * Recent documents and course PDFs.
         *
         * @param {Number} contextid Block context id.
         * @return {Promise}
         */
        listDocuments: (contextid) => call('block_aipdfassistant_list_documents', {contextid: contextid}),

        /**
         * Use a course PDF.
         *
         * @param {Number} contextid Block context id.
         * @param {Number} cmid Course module id.
         * @return {Promise}
         */
        attachCoursePdf: (contextid, cmid) => call('block_aipdfassistant_attach_course_pdf', {contextid: contextid, cmid: cmid}),

        /**
         * Delete a stored document.
         *
         * @param {Number} contextid Block context id.
         * @param {Number} docid Document id.
         * @return {Promise}
         */
        deleteDocument: (contextid, docid) => call('block_aipdfassistant_delete_document', {contextid: contextid, docid: docid}),

        /**
         * Upload a PDF.
         *
         * @param {String} url Upload endpoint.
         * @param {Object} fields contextid, sesskey.
         * @param {File} file The file.
         * @param {Function} onProgress Receives a ratio between 0 and 1.
         * @return {Promise} Resolves with the parsed JSON response.
         */
        upload: function(url, fields, file, onProgress) {
            return new Promise(function(resolve, reject) {
                const data = new FormData();
                data.append('contextid', fields.contextid);
                data.append('sesskey', fields.sesskey);
                data.append('file', file, file.name);
                const xhr = new XMLHttpRequest();
                xhr.open('POST', url);
                xhr.upload.onprogress = function(event) {
                    if (event.lengthComputable && onProgress) {
                        onProgress(event.loaded / event.total);
                    }
                };
                xhr.onload = function() {
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch (e) {
                        reject(new Error('Invalid response'));
                    }
                };
                xhr.onerror = () => reject(new Error('Network error'));
                xhr.send(data);
            });
        }
    };
});
