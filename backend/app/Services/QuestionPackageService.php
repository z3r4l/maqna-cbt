<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Phar;
use PharData;

final class QuestionPackageService
{
    public function __construct(private BaseConnection $db)
    {
    }

    private function inspectZipDirectory(string $path): void
    {
        $raw = file_get_contents($path);
        if ($raw === false || strlen($raw) > 10 * 1024 * 1024) throw new \DomainException('Arsip melebihi batas unggah.');
        $end = strrpos($raw, "PK\x05\x06");
        if ($end === false || strlen($raw) - $end > 65557 || $end + 22 > strlen($raw)) throw new \DomainException('Direktori ZIP tidak valid.');
        $u16 = static fn (int $offset) => unpack('v', substr($raw, $offset, 2))[1];
        $u32 = static fn (int $offset) => unpack('V', substr($raw, $offset, 4))[1];
        $count = $u16($end + 10); $size = $u32($end + 12); $offset = $u32($end + 16);
        if ($count > 1000 || $count === 0xffff || $offset === 0xffffffff || $size === 0xffffffff || $offset + $size !== $end) throw new \DomainException('Direktori ZIP terlalu besar atau rusak.');
        $seen = []; $total = 0;
        for ($index = 0; $index < $count; $index++) {
            if (substr($raw, $offset, 4) !== "PK\x01\x02" || $offset + 46 > $end) throw new \DomainException('Entri ZIP rusak.');
            $flags = $u16($offset + 8); $compressed = $u32($offset + 20); $uncompressed = $u32($offset + 24);
            $nameLength = $u16($offset + 28); $extraLength = $u16($offset + 30); $commentLength = $u16($offset + 32);
            $next = $offset + 46 + $nameLength + $extraLength + $commentLength;
            if ($next > $end || ($flags & 1) !== 0) throw new \DomainException('Entri ZIP terenkripsi atau rusak.');
            $name = substr($raw, $offset + 46, $nameLength);
            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, '\\') || str_contains($name, ':') || str_contains($name, "\0") || isset($seen[$name])) throw new \DomainException('Path arsip tidak aman.');
            $seen[$name] = true; $total += $uncompressed;
            if ($total > 20 * 1024 * 1024 || $uncompressed > 5 * 1024 * 1024 || ($compressed > 0 && $uncompressed / $compressed > 100)) throw new \DomainException('Arsip terlalu besar atau rasio kompresi tidak aman.');
            $offset = $next;
        }
        if ($offset !== $end) throw new \DomainException('Direktori ZIP tidak konsisten.');
    }

    private function archive(string $path): array
    {
        $this->inspectZipDirectory($path);
        try { $zip = new PharData($path); }
        catch (\Throwable $error) { throw new \DomainException('Arsip ZIP/DOCX tidak valid.', 0, $error); }
        $files = []; $size = 0; $count = 0;
        foreach (new \RecursiveIteratorIterator($zip) as $entry) {
            $name = str_replace('\\', '/', substr($entry->getPathName(), strlen('phar://' . $path . '/')));
            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, ':') || str_contains($name, "\0") || $entry->isLink()) throw new \DomainException('Path arsip tidak aman.');
            $size += $entry->getSize(); $count++;
            if ($count > 1000 || $size > 20 * 1024 * 1024 || $entry->getSize() > 5 * 1024 * 1024
                || ($entry->getCompressedSize() > 0 && $entry->getSize() / $entry->getCompressedSize() > 100)) throw new \DomainException('Arsip terlalu besar atau rasio kompresi tidak aman.');
            $files[$name] = $entry->getContent();
        }
        return $files;
    }

    private function image(string $name, string $bytes): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $info = @getimagesizefromstring($bytes);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || $info === false || $info['mime'] !== $mime || strlen($bytes) > 5 * 1024 * 1024) throw new \DomainException('Media bukan gambar PNG/JPEG/WebP yang valid.');
        return ['name' => mb_substr(basename($name), 0, 180), 'mime' => $mime, 'data' => base64_encode($bytes)];
    }

    private function validateQuestions(int $school, int $actor, bool $admin, array $questions, string $mode): array
    {
        if (! in_array($mode, ['append', 'replace'], true) || $questions === [] || count($questions) > 500) throw new \DomainException('Mode atau jumlah soal tidak valid.');
        $clean = [];
        foreach ($questions as $index => $question) {
            if (! is_array($question) || ! is_array($question['assets'] ?? null) || count($question['assets']) > 10) throw new \DomainException('Baris ' . ($index + 1) . ': media tidak valid.');
            $media = $question['assets']; unset($question['assets']);
            $clean[] = $question;
            foreach ($media as $asset) {
                if (! is_array($asset) || array_diff(array_keys($asset), ['name', 'mime', 'data']) !== [] || ! is_string($asset['data'] ?? null)
                    || ! is_string($asset['name'] ?? null) || ! is_string($asset['mime'] ?? null)) throw new \DomainException('Baris ' . ($index + 1) . ': media tidak valid.');
                $bytes = base64_decode($asset['data'], true);
                if ($bytes === false || $this->image($asset['name'], $bytes)['mime'] !== $asset['mime']) throw new \DomainException('Baris ' . ($index + 1) . ': media tidak valid.');
            }
        }
        $rows = (new QuestionTransferService($this->db))->validate($school, $actor, $admin, ['schema' => 'maqna-cbt.questions', 'version' => 1, 'mode' => 'skip', 'questions' => $clean], $mode === 'append');
        if ($mode === 'replace') {
            foreach ($rows as $index => $row) if ($row['status'] === 'skip') {
                $source = $questions[$row['rowNumber'] - 1];
                $subject = $this->db->table('subjects')->where('school_id', $school)->where('code', $source['subjectCode'])->get()->getRowArray();
                $existing = $this->db->table('questions')->where('school_id', $school)->where('subject_id', $subject['id'])->where('body', QuestionContent::sanitize($source['body']))->where('deleted_at', null)->get()->getRowArray();
                if ($existing === null || $existing['status'] !== 'draft' || (! $admin && (int) $existing['author_user_id'] !== $actor)
                    || $this->db->table('exam_items')->where('source_question_id', $existing['id'])->countAllResults() > 0) throw new \DomainException('Mode replace hanya untuk draf sendiri yang belum masuk snapshot ujian.');
                $rows[$index]['status'] = 'replace';
            }
        }
        return $rows;
    }

    public function previewZip(int $school, int $actor, bool $admin, string $path, string $mode): array
    {
        $files = $this->archive($path);
        if (! isset($files['manifest.json'])) throw new \DomainException('Manifest ZIP tidak ditemukan.');
        $manifest = json_decode($files['manifest.json'], true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($manifest) || array_diff(array_keys($manifest), ['schema', 'version', 'questions']) !== []
            || ($manifest['schema'] ?? null) !== 'maqna-cbt.question-package' || ($manifest['version'] ?? null) !== 1 || ! is_array($manifest['questions'] ?? null)) throw new \DomainException('Manifest ZIP tidak didukung.');
        $questions = [];
        foreach ($manifest['questions'] as $index => $question) {
            if (! is_array($question) || ! is_array($question['assetPaths'] ?? null) || ! array_is_list($question['assetPaths'])) throw new \DomainException('Daftar media ZIP tidak valid.');
            $assets = [];
            foreach ($question['assetPaths'] as $assetPath) {
                if (! is_string($assetPath) || ! preg_match('~^media/[A-Za-z0-9_/-]+\.(png|jpg|jpeg|webp)$~i', $assetPath) || ! isset($files[$assetPath])) throw new \DomainException('Media ZIP hilang atau path tidak aman.');
                $assets[] = $this->image($assetPath, $files[$assetPath]);
            }
            unset($question['assetPaths']);
            $questions[] = $question + ['assets' => $assets];
        }
        return $this->preview($school, $actor, $admin, $questions, $mode, 'zip');
    }

    public function preview(int $school, int $actor, bool $admin, array $questions, string $mode, string $kind): array
    {
        $rows = $this->validateQuestions($school, $actor, $admin, $questions, $mode);
        $this->db->table('question_transfer_jobs')->insert(['school_id' => $school, 'actor_user_id' => $actor, 'status' => 'preview',
            'payload_json' => json_encode(['kind' => $kind, 'mode' => $mode, 'questions' => $questions], JSON_THROW_ON_ERROR),
            'row_count' => count($rows), 'created_at' => gmdate('Y-m-d H:i:s')]);
        return ['id' => (int) $this->db->insertID(), 'rows' => $rows, 'count' => count($rows)];
    }

    public function commit(int $school, int $actor, bool $admin, int $id, string $kind): array
    {
        $this->db->transBegin(); $written = [];
        try {
            $job = $this->db->query('SELECT * FROM question_transfer_jobs WHERE id=? AND school_id=? AND actor_user_id=? FOR UPDATE', [$id, $school, $actor])->getRowArray();
            if ($job === null) throw new \OutOfBoundsException('Pratinjau tidak ditemukan.');
            $payload = json_decode($job['payload_json'], true, 512, JSON_THROW_ON_ERROR);
            if (($payload['kind'] ?? null) !== $kind) throw new \OutOfBoundsException('Pratinjau tidak ditemukan.');
            if ($job['status'] === 'committed') { $this->db->transCommit(); return ['id' => $id, 'status' => 'committed']; }
            $rows = $this->validateQuestions($school, $actor, $admin, $payload['questions'], $payload['mode']);
            $subjects = $this->db->table('subjects')->select('id, code')->where('school_id', $school)->get()->getResultArray();
            $subjectIds = array_column($subjects, 'id', 'code');
            $directory = WRITEPATH . 'uploads/questions';
            if (! is_dir($directory) && ! mkdir($directory, 0700, true)) throw new \RuntimeException('Media tidak dapat disimpan.');
            $created = 0; $replaced = 0; $skipped = 0;
            foreach ($rows as $index => $row) {
                $source = $payload['questions'][$index];
                if ($row['status'] === 'skip') { $skipped++; continue; }
                $existing = null;
                if ($row['status'] === 'replace') $existing = $this->db->query('SELECT * FROM questions WHERE school_id=? AND subject_id=? AND body=? AND deleted_at IS NULL FOR UPDATE',
                    [$school, $subjectIds[$source['subjectCode']], QuestionContent::sanitize($source['body'])])->getRowArray();
                if ($row['status'] === 'replace' && $existing === null) throw new \DomainException('Draf berubah sejak pratinjau.');
                $question = (new QuestionBank($this->db))->save($school, $actor, ['subjectId' => (int) $subjectIds[$source['subjectCode']], 'body' => $source['body'],
                    'questionType' => $source['type'], 'weight' => (float) $source['weight'], 'rubric' => $source['rubric'] ?? '',
                    'explanation' => $source['explanation'] ?? '', 'options' => $source['options'], 'status' => 'draft'], $existing === null ? null : (int) $existing['id'], $admin);
                $questionId = (int) $question['id'];
                if ($existing !== null) {
                    $this->db->table('question_assets')->where('question_id', $questionId)->where('archived_at', null)->update(['archived_at' => gmdate('Y-m-d H:i:s')]);
                    $replaced++;
                } else $created++;
                foreach ($source['assets'] as $asset) {
                    $bytes = base64_decode($asset['data'], true);
                    if ($bytes === false) throw new \DomainException('Media rusak.');
                    $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$asset['mime']];
                    $stored = bin2hex(random_bytes(20)) . '.' . $extension;
                    $path = $directory . '/' . $stored;
                    if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) throw new \RuntimeException('Media gagal ditulis.');
                    $written[] = $path;
                    $now = gmdate('Y-m-d H:i:s');
                    $this->db->table('question_assets')->insert(['school_id' => $school, 'question_id' => $questionId, 'stored_name' => $stored,
                        'original_name' => $asset['name'], 'mime_type' => $asset['mime'], 'size_bytes' => strlen($bytes), 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            $this->db->table('question_transfer_jobs')->where('id', $id)->update(['status' => 'committed', 'committed_at' => gmdate('Y-m-d H:i:s')]);
            (new AuditTrail($this->db))->record($school, $actor, 'questions.' . $kind . '_import', 'question_transfer_job', $id, compact('created', 'replaced', 'skipped'));
            if (! $this->db->transStatus()) throw new \RuntimeException('Impor gagal.');
            $this->db->transCommit();
            return ['id' => $id, 'status' => 'committed', 'created' => $created, 'replaced' => $replaced, 'skipped' => $skipped];
        } catch (\Throwable $error) {
            $this->db->transRollback();
            foreach ($written as $path) if (is_file($path)) unlink($path);
            throw $error;
        }
    }

    private function zip(array $files): string
    {
        $base = tempnam(WRITEPATH . 'cache', 'maqna-package-');
        if ($base === false) throw new \RuntimeException('Berkas sementara tidak tersedia.');
        unlink($base); $path = $base . '.zip';
        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            foreach ($files as $name => $content) $archive->addFromString($name, $content);
            unset($archive);
            $body = file_get_contents($path);
            if ($body === false) throw new \RuntimeException('Arsip gagal dibaca.');
            return $body;
        } finally { if (is_file($path)) unlink($path); }
    }

    public function exportZip(int $school, int $actor, bool $admin): string
    {
        $payload = (new QuestionTransferService($this->db))->export($school, $actor, $admin);
        $builder = $this->db->table('questions')->select('id')->where('school_id', $school)->where('deleted_at', null);
        if (! $admin) $builder->where('author_user_id', $actor);
        $ids = array_column($builder->orderBy('id')->limit(500)->get()->getResultArray(), 'id');
        $files = []; $total = 0;
        foreach ($payload['questions'] as $index => &$question) {
            $question['assetPaths'] = [];
            $assets = $this->db->table('question_assets')->where('question_id', $ids[$index])->where('archived_at', null)->get()->getResultArray();
            foreach ($assets as $asset) {
                $path = WRITEPATH . 'uploads/questions/' . $asset['stored_name'];
                if (! is_file($path)) throw new \DomainException('Media sumber hilang; backup dibatalkan.');
                $bytes = file_get_contents($path); $total += strlen($bytes);
                if ($total > 15 * 1024 * 1024) throw new \DomainException('Media backup melebihi 15 MB.');
                $name = 'media/' . $ids[$index] . '/' . basename($asset['stored_name']);
                $files[$name] = $bytes; $question['assetPaths'][] = $name;
            }
        }
        $files['manifest.json'] = json_encode(['schema' => 'maqna-cbt.question-package', 'version' => 1, 'questions' => $payload['questions']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        return $this->zip($files);
    }

    public function previewDocx(int $school, int $actor, bool $admin, string $path): array
    {
        $files = $this->archive($path);
        if (! isset($files['word/document.xml'], $files['word/_rels/document.xml.rels'])) throw new \DomainException('Struktur DOCX tidak lengkap.');
        $document = new \DOMDocument();
        if (! $document->loadXML($files['word/document.xml'], LIBXML_NONET)) throw new \DomainException('Dokumen Word rusak.');
        $relationships = new \DOMDocument();
        if (! $relationships->loadXML($files['word/_rels/document.xml.rels'], LIBXML_NONET)) throw new \DomainException('Relasi Word rusak.');
        $targets = [];
        foreach ($relationships->getElementsByTagName('Relationship') as $relation) {
            $target = $relation->getAttribute('Target');
            if (preg_match('~^media/[A-Za-z0-9_.-]+$~', $target)) $targets[$relation->getAttribute('Id')] = 'word/' . $target;
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $questions = []; $current = null;
        foreach ($xpath->query('//w:body/w:p') as $paragraph) {
            $line = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $node) $line .= $node->textContent;
            $line = trim($line);
            if ($line === 'QUESTION') {
                if ($current !== null) throw new \DomainException('Tutup soal sebelumnya dengan END.');
                $current = ['subjectCode' => '', 'body' => '', 'type' => 'mcq', 'weight' => 1, 'rubric' => '', 'explanation' => '', 'options' => [], 'assets' => [], '_key' => ''];
            } elseif ($line === 'END') {
                if ($current === null) throw new \DomainException('END tanpa QUESTION.');
                $questions[] = $this->finalizeDocx($current);
                $current = null;
            } elseif ($line !== '') {
                if ($current === null) throw new \DomainException('Teks di luar blok QUESTION/END: ' . mb_substr($line, 0, 60));
                if (! preg_match('/^(SUBJECT|TYPE|WEIGHT|BODY|BODY\+|OPTION [A-L]|KEY|RUBRIC|EXPLANATION):\s*(.*)$/ui', $line, $m)) throw new \DomainException('Baris DOCX tidak dikenal: ' . mb_substr($line, 0, 60));
                $field = strtoupper($m[1]); $value = trim($m[2]);
                if ($field === 'SUBJECT') $current['subjectCode'] = $value;
                elseif ($field === 'TYPE') $current['type'] = $value;
                elseif ($field === 'WEIGHT') $current['weight'] = $value;
                elseif ($field === 'BODY') $current['body'] = $value;
                elseif ($field === 'BODY+') $current['body'] .= "\n" . $value;
                elseif ($field === 'KEY') $current['_key'] = strtoupper($value);
                elseif ($field === 'RUBRIC') $current['rubric'] = $value;
                elseif ($field === 'EXPLANATION') $current['explanation'] = $value;
                else $current['_options'][ord(substr($field, -1)) - 65] = $value;
            }
            foreach ($xpath->query('.//a:blip', $paragraph) as $image) {
                if ($current === null) throw new \DomainException('Gambar DOCX di luar soal.');
                $id = $image->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
                $target = $targets[$id] ?? null;
                if ($target === null || ! isset($files[$target])) throw new \DomainException('Gambar DOCX hilang atau relasi tidak aman.');
                $current['assets'][] = $this->image($target, $files[$target]);
            }
        }
        if ($current !== null || $questions === []) throw new \DomainException('Blok DOCX tidak lengkap atau kosong.');
        return $this->preview($school, $actor, $admin, $questions, 'append', 'docx');
    }

    private function finalizeDocx(array $question): array
    {
        $raw = $question['_options'] ?? [];
        ksort($raw);
        if ($question['type'] !== 'essay' && (array_keys($raw) !== range(0, count($raw) - 1) || count($raw) < 2)) throw new \DomainException('Opsi DOCX harus urut mulai A.');
        if ($question['type'] === 'essay' && $raw !== []) throw new \DomainException('Esai tidak memiliki opsi.');
        $key = str_replace(' ', '', $question['_key']);
        $letters = $key === '' ? [] : explode(',', $key);
        foreach ($raw as $index => $value) {
            $match = null;
            if ($question['type'] === 'matching') {
                $pair = explode('=>', $value, 2);
                if (count($pair) !== 2) throw new \DomainException('Pasangan DOCX memakai tanda =>.');
                $value = trim($pair[0]); $match = trim($pair[1]);
            }
            $question['options'][] = ['body' => $value, 'matchBody' => $match,
                'isCorrect' => $question['type'] === 'true_false' ? (($letters[$index] ?? '') === 'T') : in_array(chr(65 + $index), $letters, true)];
        }
        if ($question['type'] === 'true_false' && (count($letters) !== count($raw) || array_diff($letters, ['T', 'F']) !== [])) throw new \DomainException('Kunci benar/salah DOCX harus T,F per baris.');
        unset($question['_options'], $question['_key']);
        return $question;
    }

    public function templateDocx(): string
    {
        $lines = ['QUESTION', 'SUBJECT: MAT', 'TYPE: mcq', 'WEIGHT: 1', 'BODY: Tulis pertanyaan di sini', 'OPTION A: Jawaban benar', 'OPTION B: Pengecoh', 'KEY: A', 'RUBRIC:', 'EXPLANATION:', 'END'];
        $body = '';
        foreach ($lines as $line) $body .= '<w:p><w:r><w:t xml:space="preserve">' . htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r></w:p>';
        return $this->zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '</w:body></w:document>',
        ]);
    }
}
