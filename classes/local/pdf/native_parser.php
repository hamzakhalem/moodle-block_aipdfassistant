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


namespace block_aipdfassistant\local\pdf;

use block_aipdfassistant\local\exception;

/**
 * Dependency free PDF text extractor used when the pdftotext binary is not available.
 *
 * It scans the objects of the file (including compressed object streams), walks the
 * page tree, decodes Flate/ASCIIHex/ASCII85 streams, reads ToUnicode CMaps and
 * interprets the text operators of the page content. It does not do OCR and does
 * not open encrypted files.
 *
 * @package    block_aipdfassistant
 * @copyright  2026 AI PDF Assistant contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class native_parser {
    /** Whitespace characters in PDF syntax. */
    private const WS = " \t\r\n\f\0";

    /** Delimiter characters in PDF syntax. */
    private const DELIM = '()<>[]{}/%';

    /** @var string Raw file bytes. */
    private $data = '';

    /** @var array Object id => [dictionary string, raw stream bytes or null]. */
    private $objects = [];

    /** @var array Font object id => font info. */
    private $fontcache = [];

    /** @var array Object id => decoded stream or null. */
    private $decoded = [];

    /**
     * Extract text from PDF bytes.
     *
     * @param string $data File contents.
     * @return array [string $text, int $pages]
     * @throws exception With code "encrypted" for password protected files.
     */
    public function extract(string $data): array {
        $this->data = $data;
        $this->objects = [];
        $this->fontcache = [];
        $this->decoded = [];
        $this->scan();
        $pages = $this->pages();
        $texts = [];
        foreach ($pages as $page) {
            $texts[] = $this->run_content($this->page_contents($page[1]), $page[2], 0);
        }
        return [implode("\n\n", $texts), count($pages)];
    }

    /**
     * Index every object of the file and expand object streams.
     *
     * @return void
     */
    private function scan(): void {
        if (preg_match('~/Encrypt\s*(<<|\d+\s+\d+\s+R)~', $this->data)) {
            throw new exception('encrypted');
        }
        $found = preg_match_all('~(?<![0-9])(\d{1,7})\s+(\d{1,5})\s+obj\b~', $this->data, $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if (!$found) {
            return;
        }
        $length = strlen($this->data);
        foreach ($matches as $match) {
            $id = (int) $match[1][0];
            $start = $match[0][1] + strlen($match[0][0]);
            $end = strpos($this->data, 'endobj', $start);
            if ($end === false) {
                $end = $length;
            }
            $this->objects[$id] = $this->split_body(substr($this->data, $start, $end - $start));
        }
        foreach ($this->objects as $id => $object) {
            if ($object[1] !== null && preg_match('~/Type\s*/ObjStm~', $object[0])) {
                $this->expand_objstm($object[0], $object[1]);
            }
        }
    }

    /**
     * Split an object body in dictionary and raw stream.
     *
     * @param string $body Object body.
     * @return array [dictionary, raw stream or null]
     */
    private function split_body(string $body): array {
        if (!preg_match('~>>\s*stream(\r\n|\n|\r)~', $body, $m, PREG_OFFSET_CAPTURE)) {
            return [$body, null];
        }
        $dict = substr($body, 0, $m[0][1] + 2);
        $start = $m[0][1] + strlen($m[0][0]);
        $end = strrpos($body, 'endstream');
        if ($end === false || $end < $start) {
            $end = strlen($body);
        }
        $raw = (string) substr($body, $start, $end - $start);
        if (substr($raw, -2) === "\r\n") {
            $raw = substr($raw, 0, -2);
        } else if (substr($raw, -1) === "\n" || substr($raw, -1) === "\r") {
            $raw = substr($raw, 0, -1);
        }
        return [$dict, $raw];
    }

    /**
     * Register the objects stored inside an object stream.
     *
     * @param string $dict Stream dictionary.
     * @param string $raw Raw stream bytes.
     * @return void
     */
    private function expand_objstm(string $dict, string $raw): void {
        if (!preg_match('~/N\s+(\d+)~', $dict, $n) || !preg_match('~/First\s+(\d+)~', $dict, $f)) {
            return;
        }
        $count = (int) $n[1];
        $first = (int) $f[1];
        $data = $this->decode_stream($dict, $raw);
        if ($data === null) {
            return;
        }
        preg_match_all('~\d+~', (string) substr($data, 0, $first), $header);
        $numbers = $header[0];
        $pairs = [];
        for ($i = 0; $i + 1 < count($numbers) && count($pairs) < $count; $i += 2) {
            $pairs[] = [(int) $numbers[$i], (int) $numbers[$i + 1]];
        }
        foreach ($pairs as $k => $pair) {
            $next = isset($pairs[$k + 1]) ? $pairs[$k + 1][1] : strlen($data) - $first;
            $body = (string) substr($data, $first + $pair[1], max(0, $next - $pair[1]));
            if (!isset($this->objects[$pair[0]])) {
                $this->objects[$pair[0]] = [$body, null];
            }
        }
    }

    /**
     * Apply the stream filters. Returns null when a filter is unsupported.
     *
     * @param string $dict Stream dictionary.
     * @param string $raw Raw bytes.
     * @return string|null
     */
    private function decode_stream(string $dict, string $raw): ?string {
        $filters = [];
        if (preg_match('~/Filter\s*(\[[^\]]*\]|/[A-Za-z0-9]+)~', $dict, $fm)) {
            preg_match_all('~/([A-Za-z0-9]+)~', $fm[1], $names);
            $filters = $names[1];
        }
        $out = $raw;
        foreach ($filters as $filter) {
            if ($filter === 'FlateDecode' || $filter === 'Fl') {
                $out = $this->inflate($out);
            } else if ($filter === 'ASCIIHexDecode' || $filter === 'AHx') {
                $parts = explode('>', $out);
                $hex = (string) preg_replace('~[^0-9A-Fa-f]~', '', $parts[0]);
                if (strlen($hex) % 2) {
                    $hex .= '0';
                }
                $out = $hex === '' ? '' : hex2bin($hex);
            } else if ($filter === 'ASCII85Decode' || $filter === 'A85') {
                $out = $this->ascii85($out);
            } else {
                return null;
            }
            if ($out === null || $out === false) {
                return null;
            }
        }
        return $out;
    }

    /**
     * Inflate zlib or raw deflate data.
     *
     * @param string $raw Compressed bytes.
     * @return string|null
     */
    private function inflate(string $raw): ?string {
        $out = @gzuncompress($raw);
        if ($out === false) {
            $out = @gzinflate($raw);
        }
        if ($out === false && strlen($raw) > 2) {
            $out = @gzinflate(substr($raw, 2));
        }
        if ($out === false && function_exists('inflate_init')) {
            $context = @inflate_init(ZLIB_ENCODING_DEFLATE);
            if ($context !== false) {
                $out = @inflate_add($context, $raw, ZLIB_FINISH);
            }
        }
        return $out === false ? null : $out;
    }

    /**
     * Decode ASCII base-85 data.
     *
     * @param string $input Encoded text.
     * @return string|null
     */
    private function ascii85(string $input): ?string {
        $s = trim($input);
        if (strpos($s, '<~') === 0) {
            $s = substr($s, 2);
        }
        $end = strpos($s, '~>');
        if ($end !== false) {
            $s = substr($s, 0, $end);
        }
        $s = (string) preg_replace('~\s+~', '', $s);
        $out = '';
        $tuple = 0;
        $count = 0;
        $length = strlen($s);
        for ($i = 0; $i < $length; $i++) {
            $c = $s[$i];
            if ($c === 'z' && $count === 0) {
                $out .= "\0\0\0\0";
                continue;
            }
            $v = ord($c) - 33;
            if ($v < 0 || $v > 84) {
                return null;
            }
            $tuple = $tuple * 85 + $v;
            $count++;
            if ($count === 5) {
                $out .= pack('N', $tuple);
                $tuple = 0;
                $count = 0;
            }
        }
        if ($count > 1) {
            for ($k = $count; $k < 5; $k++) {
                $tuple = $tuple * 85 + 84;
            }
            $out .= substr(pack('N', $tuple), 0, $count - 1);
        }
        return $out;
    }

    /**
     * Decoded stream of an object (cached).
     *
     * @param int $id Object id.
     * @return string|null
     */
    private function stream_of(int $id): ?string {
        if (array_key_exists($id, $this->decoded)) {
            return $this->decoded[$id];
        }
        $result = null;
        if (isset($this->objects[$id]) && $this->objects[$id][1] !== null) {
            $result = $this->decode_stream($this->objects[$id][0], $this->objects[$id][1]);
        }
        $this->decoded[$id] = $result;
        return $result;
    }

    /**
     * Return the balanced << ... >> dictionary that starts at $pos.
     *
     * @param string $s Source.
     * @param int $pos Offset of the opening "<<".
     * @return string
     */
    private function balanced_dict(string $s, int $pos): string {
        $depth = 0;
        $i = $pos;
        $n = strlen($s);
        while ($i < $n - 1) {
            $two = substr($s, $i, 2);
            if ($two === '<<') {
                $depth++;
                $i += 2;
                continue;
            }
            if ($two === '>>') {
                $depth--;
                $i += 2;
                if ($depth === 0) {
                    return substr($s, $pos, $i - $pos);
                }
                continue;
            }
            if ($s[$i] === '(') {
                $level = 1;
                $i++;
                while ($i < $n && $level > 0) {
                    $ch = $s[$i];
                    if ($ch === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($ch === '(') {
                        $level++;
                    } else if ($ch === ')') {
                        $level--;
                    }
                    $i++;
                }
                continue;
            }
            $i++;
        }
        return (string) substr($s, $pos);
    }

    /**
     * Raw value of a top level dictionary key: a dictionary, an array, a reference or a token.
     *
     * @param string $dict Dictionary source.
     * @param string $key Key without slash.
     * @return string|null
     */
    private function dict_entry_raw(string $dict, string $key): ?string {
        if (!preg_match('~/' . preg_quote($key, '~') . '(?![A-Za-z0-9_.\-])\s*~', $dict, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $p = $m[0][1] + strlen($m[0][0]);
        if ($p >= strlen($dict)) {
            return null;
        }
        if (substr($dict, $p, 2) === '<<') {
            return $this->balanced_dict($dict, $p);
        }
        if ($dict[$p] === '[') {
            $e = strpos($dict, ']', $p);
            return $e !== false ? substr($dict, $p, $e - $p + 1) : (string) substr($dict, $p);
        }
        if (preg_match('~^(\d+)\s+(\d+)\s+R~', substr($dict, $p, 40), $r)) {
            return $r[0];
        }
        if (preg_match('~^/?[^\s/<>\[\]()]+~', substr($dict, $p, 200), $r)) {
            return $r[0];
        }
        return null;
    }

    /**
     * Object id of an indirect reference value ("12 0 R"), or null.
     *
     * @param string|null $raw Raw value.
     * @return int|null
     */
    private function as_ref(?string $raw): ?int {
        if ($raw !== null && preg_match('~^(\d+)\s+\d+\s+R$~', trim($raw), $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Dictionary source of a Resources entry (inline or referenced).
     *
     * @param string $dict Page or form dictionary.
     * @return string|null
     */
    private function resources_of(string $dict): ?string {
        $raw = $this->dict_entry_raw($dict, 'Resources');
        if ($raw === null) {
            return null;
        }
        $ref = $this->as_ref($raw);
        if ($ref !== null) {
            return isset($this->objects[$ref]) ? $this->objects[$ref][0] : null;
        }
        return strpos($raw, '<<') === 0 ? $raw : null;
    }

    /**
     * Collect leaf pages in document order.
     *
     * @return array List of [object id, dictionary, resources dictionary|null].
     */
    private function pages(): array {
        $root = null;
        foreach ($this->objects as $object) {
            if (preg_match('~/Type\s*/Catalog~', $object[0])) {
                $root = $this->as_ref($this->dict_entry_raw($object[0], 'Pages'));
                break;
            }
        }
        $result = [];
        $seen = [];
        if ($root !== null) {
            $this->walk($root, 0, null, $seen, $result);
        }
        if (!$result) {
            $ids = array_keys($this->objects);
            sort($ids);
            foreach ($ids as $id) {
                $dict = $this->objects[$id][0];
                if (preg_match('~/Type\s*/Page(?![A-Za-z])~', $dict)) {
                    $result[] = [$id, $dict, $this->resources_of($dict)];
                }
            }
        }
        return $result;
    }

    /**
     * Depth first walk over the page tree.
     *
     * @param int $id Node object id.
     * @param int $depth Recursion depth.
     * @param string|null $inherited Inherited resources dictionary.
     * @param array $seen Visited ids.
     * @param array $result Collected pages.
     * @return void
     */
    private function walk(int $id, int $depth, ?string $inherited, array &$seen, array &$result): void {
        if (isset($seen[$id]) || $depth > 40 || !isset($this->objects[$id])) {
            return;
        }
        $seen[$id] = true;
        $dict = $this->objects[$id][0];
        $resources = $this->resources_of($dict);
        if ($resources === null) {
            $resources = $inherited;
        }
        $kids = $this->dict_entry_raw($dict, 'Kids');
        if ($kids !== null && !preg_match('~/Type\s*/Page(?![A-Za-z])~', $dict)) {
            if (preg_match_all('~(\d+)\s+\d+\s+R~', $kids, $refs)) {
                foreach ($refs[1] as $kid) {
                    $this->walk((int) $kid, $depth + 1, $resources, $seen, $result);
                }
            }
            return;
        }
        $result[] = [$id, $dict, $resources];
    }

    /**
     * Concatenated decoded content streams of a page.
     *
     * @param string $dict Page dictionary.
     * @return string
     */
    private function page_contents(string $dict): string {
        $raw = $this->dict_entry_raw($dict, 'Contents');
        if ($raw === null) {
            return '';
        }
        $parts = [];
        if (preg_match_all('~(\d+)\s+\d+\s+R~', $raw, $refs)) {
            foreach ($refs[1] as $ref) {
                $parts[] = (string) $this->stream_of((int) $ref);
            }
        }
        return implode("\n", $parts);
    }

    /**
     * Map of font resource names to font object ids.
     *
     * @param string|null $resources Resources dictionary.
     * @return array
     */
    private function fonts_of(?string $resources): array {
        $fonts = [];
        if ($resources === null) {
            return $fonts;
        }
        $raw = $this->dict_entry_raw($resources, 'Font');
        if ($raw === null) {
            return $fonts;
        }
        $ref = $this->as_ref($raw);
        $fontdict = $ref !== null ? ($this->objects[$ref][0] ?? '') : $raw;
        if (preg_match_all('~/([^\s/<>\[\]()]+)\s+(\d+)\s+\d+\s+R~', $fontdict, $m, PREG_SET_ORDER)) {
            foreach ($m as $entry) {
                $fonts[$entry[1]] = (int) $entry[2];
            }
        }
        return $fonts;
    }

    /**
     * Font information (ToUnicode map and type).
     *
     * @param int $id Font object id.
     * @return array Keys: cmap (cmap|null), type0 (bool).
     */
    private function font_info(int $id): array {
        if (isset($this->fontcache[$id])) {
            return $this->fontcache[$id];
        }
        $info = ['cmap' => null, 'type0' => false];
        if (isset($this->objects[$id])) {
            $dict = $this->objects[$id][0];
            $info['type0'] = (bool) preg_match('~/Subtype\s*/Type0~', $dict);
            $tounicode = $this->as_ref($this->dict_entry_raw($dict, 'ToUnicode'));
            if ($tounicode !== null) {
                $stream = $this->stream_of($tounicode);
                if ($stream !== null && $stream !== '') {
                    $cmap = cmap::parse($stream);
                    if ($cmap->nbytes === 0) {
                        $cmap->nbytes = $info['type0'] ? 2 : 1;
                    }
                    $info['cmap'] = $cmap;
                }
            }
        }
        $this->fontcache[$id] = $info;
        return $info;
    }

    /**
     * Content of a form XObject referenced by name.
     *
     * @param string|null $resources Resources dictionary.
     * @param string $name XObject name.
     * @return array|null [decoded content, resources dictionary|null]
     */
    private function xobject(?string $resources, string $name): ?array {
        if ($resources === null) {
            return null;
        }
        $raw = $this->dict_entry_raw($resources, 'XObject');
        if ($raw === null) {
            return null;
        }
        $ref = $this->as_ref($raw);
        $dict = $ref !== null ? ($this->objects[$ref][0] ?? '') : $raw;
        if (!preg_match('~/' . preg_quote($name, '~') . '\s+(\d+)\s+\d+\s+R~', $dict, $m)) {
            return null;
        }
        $id = (int) $m[1];
        if (!isset($this->objects[$id]) || !preg_match('~/Subtype\s*/Form~', $this->objects[$id][0])) {
            return null;
        }
        $stream = $this->stream_of($id);
        if ($stream === null) {
            return null;
        }
        return [$stream, $this->resources_of($this->objects[$id][0])];
    }

    /**
     * Interpret a content stream and return its text.
     *
     * @param string $content Decoded content stream.
     * @param string|null $resources Resources dictionary.
     * @param int $depth Form XObject nesting depth.
     * @return string
     */
    private function run_content(string $content, ?string $resources, int $depth): string {
        $fonts = $this->fonts_of($resources);
        $out = [];
        $lastx = null;
        $lasty = null;
        $font = null;
        $newline = function () use (&$out) {
            if ($out && end($out) !== "\n") {
                $out[] = "\n";
            }
        };
        $space = function () use (&$out) {
            if ($out && end($out) !== ' ' && end($out) !== "\n") {
                $out[] = ' ';
            }
        };
        $operands = [];
        foreach ($this->tokenize($content) as $token) {
            if ($token['t'] !== 'op') {
                $operands[] = $token;
                continue;
            }
            $op = $token['v'];
            $count = count($operands);
            $last = $count ? $operands[$count - 1] : null;
            if ($op === 'Tf' && $count >= 2 && $operands[$count - 2]['t'] === 'name') {
                $font = $fonts[$operands[$count - 2]['v']] ?? null;
            } else if ($op === 'Tj' && $last !== null && $last['t'] === 'str') {
                $out[] = $this->decode($last['v'], $font);
            } else if ($op === 'TJ' && $last !== null && $last['t'] === 'arr') {
                foreach ($last['v'] as $item) {
                    if ($item['t'] === 'str') {
                        $out[] = $this->decode($item['v'], $font);
                    } else if ($item['t'] === 'num' && $item['v'] < -180) {
                        $space();
                    }
                }
            } else if (($op === "'" || $op === '"') && $last !== null && $last['t'] === 'str') {
                $newline();
                $out[] = $this->decode($last['v'], $font);
            } else if (($op === 'Td' || $op === 'TD') && $count >= 2) {
                $tx = $operands[$count - 2]['t'] === 'num' ? $operands[$count - 2]['v'] : null;
                $ty = $operands[$count - 1]['t'] === 'num' ? $operands[$count - 1]['v'] : null;
                if ($ty !== null && abs($ty) > 0.1) {
                    $newline();
                } else if ($tx !== null && $tx > 0.5) {
                    $space();
                }
            } else if ($op === 'T*') {
                $newline();
            } else if ($op === 'Tm' && $count >= 6) {
                $x = $operands[$count - 2]['t'] === 'num' ? $operands[$count - 2]['v'] : null;
                $y = $operands[$count - 1]['t'] === 'num' ? $operands[$count - 1]['v'] : null;
                if ($x !== null && $y !== null) {
                    if ($lasty !== null && abs($y - $lasty) > 1) {
                        $newline();
                    } else if ($lastx !== null && $x > $lastx + 0.5) {
                        $space();
                    }
                    $lastx = $x;
                    $lasty = $y;
                }
            } else if ($op === 'Do' && $last !== null && $last['t'] === 'name' && $depth < 3) {
                $form = $this->xobject($resources, $last['v']);
                if ($form !== null) {
                    $newline();
                    $out[] = $this->run_content($form[0], $form[1] !== null ? $form[1] : $resources, $depth + 1);
                    $newline();
                }
            }
            $operands = [];
        }
        return implode('', $out);
    }

    /**
     * Decode the bytes of a shown string to UTF-8 using the current font.
     *
     * @param string $s Raw string bytes.
     * @param int|null $fontid Current font object id.
     * @return string
     */
    private function decode(string $s, ?int $fontid): string {
        $info = $fontid !== null ? $this->font_info($fontid) : null;
        if ($info !== null && $info['cmap'] !== null) {
            $cmap = $info['cmap'];
            $n = $cmap->nbytes ?: 1;
            $result = '';
            $length = strlen($s);
            for ($i = 0; $i + $n <= $length; $i += $n) {
                $code = 0;
                for ($k = 0; $k < $n; $k++) {
                    $code = ($code << 8) | ord($s[$i + $k]);
                }
                if (isset($cmap->map[$code])) {
                    $result .= $cmap->map[$code];
                } else if ($n === 1) {
                    $result .= (string) mb_convert_encoding($s[$i], 'UTF-8', 'Windows-1252');
                }
            }
            return $result;
        }
        if ($info !== null && $info['type0']) {
            return '';
        }
        return (string) mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }

    /**
     * Split a content stream in tokens.
     *
     * @param string $s Content stream.
     * @return array List of ['t' => type, 'v' => value]; types: str, name, num, arr, op.
     */
    private function tokenize(string $s): array {
        $i = 0;
        $n = strlen($s);
        $out = [];
        $stack = [];
        $emit = function (array $token) use (&$out, &$stack) {
            if ($stack) {
                $stack[count($stack) - 1][] = $token;
            } else {
                $out[] = $token;
            }
        };
        while ($i < $n) {
            $ch = $s[$i];
            if (strpos(self::WS, $ch) !== false) {
                $i++;
                continue;
            }
            if ($ch === '%') {
                while ($i < $n && $s[$i] !== "\r" && $s[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($ch === '(') {
                $emit(['t' => 'str', 'v' => $this->read_literal($s, $i)]);
                continue;
            }
            if ($ch === '<') {
                if (substr($s, $i, 2) === '<<') {
                    $i += 2;
                    continue;
                }
                $e = strpos($s, '>', $i);
                if ($e === false) {
                    break;
                }
                $hex = (string) preg_replace('~[^0-9A-Fa-f]~', '', substr($s, $i + 1, $e - $i - 1));
                if (strlen($hex) % 2) {
                    $hex .= '0';
                }
                $emit(['t' => 'str', 'v' => $hex === '' ? '' : (string) hex2bin($hex)]);
                $i = $e + 1;
                continue;
            }
            if ($ch === '>') {
                $i += substr($s, $i, 2) === '>>' ? 2 : 1;
                continue;
            }
            if ($ch === '[') {
                $stack[] = [];
                $i++;
                continue;
            }
            if ($ch === ']') {
                $i++;
                if ($stack) {
                    $array = array_pop($stack);
                    $emit(['t' => 'arr', 'v' => $array]);
                }
                continue;
            }
            if ($ch === '/') {
                $j = $i + 1;
                while ($j < $n && strpos(self::WS . self::DELIM, $s[$j]) === false) {
                    $j++;
                }
                $emit(['t' => 'name', 'v' => substr($s, $i + 1, $j - $i - 1)]);
                $i = $j;
                continue;
            }
            if (preg_match('~\G[+-]?(\d+\.?\d*|\.\d+)~', $s, $m, 0, $i)) {
                $end = $i + strlen($m[0]);
                if ($end >= $n || strpos(self::WS . self::DELIM, $s[$end]) !== false) {
                    $emit(['t' => 'num', 'v' => (float) $m[0]]);
                    $i = $end;
                    continue;
                }
            }
            $j = $i;
            while ($j < $n && strpos(self::WS . self::DELIM, $s[$j]) === false) {
                $j++;
            }
            if ($j === $i) {
                $j = $i + 1;
            }
            $word = substr($s, $i, $j - $i);
            $i = $j;
            if ($word === 'BI') {
                if (preg_match('~\sEI(?=\s|$)~', $s, $mm, PREG_OFFSET_CAPTURE, $i)) {
                    $i = $mm[0][1] + strlen($mm[0][0]);
                } else {
                    $i = $n;
                }
                continue;
            }
            $emit(['t' => 'op', 'v' => $word]);
        }
        return $out;
    }

    /**
     * Read a literal string starting at "(" and advance the offset past the closing ")".
     *
     * @param string $s Source.
     * @param int $i Offset of the opening parenthesis (updated).
     * @return string Unescaped bytes.
     */
    private function read_literal(string $s, int &$i): string {
        $n = strlen($s);
        $level = 1;
        $i++;
        $buf = '';
        $escapes = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f"];
        while ($i < $n && $level > 0) {
            $c = $s[$i];
            if ($c === '\\') {
                $i++;
                if ($i >= $n) {
                    break;
                }
                $e = $s[$i];
                if (isset($escapes[$e])) {
                    $buf .= $escapes[$e];
                    $i++;
                } else if ($e >= '0' && $e <= '7') {
                    $octal = $e;
                    $i++;
                    for ($k = 0; $k < 2; $k++) {
                        if ($i < $n && $s[$i] >= '0' && $s[$i] <= '7') {
                            $octal .= $s[$i];
                            $i++;
                        } else {
                            break;
                        }
                    }
                    $buf .= chr(octdec($octal) & 255);
                } else if ($e === "\r") {
                    $i++;
                    if ($i < $n && $s[$i] === "\n") {
                        $i++;
                    }
                } else if ($e === "\n") {
                    $i++;
                } else {
                    $buf .= $e;
                    $i++;
                }
                continue;
            }
            if ($c === '(') {
                $level++;
            } else if ($c === ')') {
                $level--;
                if ($level === 0) {
                    $i++;
                    break;
                }
            }
            $buf .= $c;
            $i++;
        }
        return $buf;
    }
}
