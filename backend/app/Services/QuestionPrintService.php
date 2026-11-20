<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Dompdf\Dompdf;
use Dompdf\Options;
use Phar;
use PharData;

final class QuestionPrintService
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function collect(int $school, int $actor, bool $admin, ?int $subjectId): array
    {
        $query = $this->db->table('questions q')->select('q.id, q.body, q.question_type, q.weight, q.rubric, s.name AS subject_name')
            ->join('subjects s', 's.id=q.subject_id')->where('q.school_id', $school)->where('q.status', 'published')->where('q.deleted_at', null);
        if (! $admin) $query->where('q.author_user_id', $actor);
        if ($subjectId !== null) $query->where('q.subject_id', $subjectId);
        if ($query->countAllResults(false) > 500) throw new \DomainException('Maksimal 500 soal per unduhan. Saring mata pelajaran.');
        $rows = $query->orderBy('q.id')->get()->getResultArray();
        if ($rows === []) throw new \OutOfBoundsException('Soal terbit tidak ditemukan.');
        foreach ($rows as &$row) {
            $row['options'] = $this->db->table('question_options')->select('id, body, match_body, match_choice_id, is_correct')->where('question_id', $row['id'])->orderBy('sort_order')->get()->getResultArray();
            $row['matching_choices'] = $this->db->table('question_matching_choices')->select('id, body')->where('question_id', $row['id'])->orderBy('sort_order')->get()->getResultArray();
        }
        return $rows;
    }

    private function lines(array $questions, bool $keys): array
    {
        $lines = [];
        foreach ($questions as $index => $question) {
            $lines[] = ($index + 1) . '. [' . $question['subject_name'] . '] ' . trim(strip_tags($question['body'])) . ' (' . $question['weight'] . ' poin)';
            if ($keys) {
                if ($question['question_type'] === 'essay') $lines[] = 'Rubrik: ' . $question['rubric'];
                foreach ($question['options'] as $position => $option) {
                    if ($question['question_type'] === 'matching') {
                        $choiceIds = array_column($question['matching_choices'], 'id');
                        $choiceIndex = array_search((int) ($option['match_choice_id'] ?? $option['id']), array_map('intval', $choiceIds), true);
                        $lines[] = chr(65 + $position) . '. ' . trim(strip_tags($option['body'])) . ' = ' . chr(65 + (int) $choiceIndex);
                    }
                    elseif ($question['question_type'] === 'true_false') $lines[] = chr(65 + $position) . '. ' . ($option['is_correct'] ? 'Benar' : 'Salah');
                    elseif ($option['is_correct']) $lines[] = 'Kunci: ' . chr(65 + $position);
                }
            } else {
                if ($question['question_type'] === 'matching') {
                    foreach ($question['options'] as $position => $option) $lines[] = chr(65 + $position) . '. ' . trim(strip_tags($option['body']));
                    $lines[] = 'Pilihan pasangan:';
                    foreach ($question['matching_choices'] as $position => $choice) $lines[] = chr(65 + $position) . '. ' . trim(strip_tags($choice['body']));
                } else foreach ($question['options'] as $position => $option) $lines[] = chr(65 + $position) . '. ' . trim(strip_tags($option['body']));
            }
            $lines[] = '';
        }
        return $lines;
    }

    public function pdf(array $questions, bool $keys): string
    {
        $escape = static fn ($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#183b38}h1{font-size:16pt}.line{margin:0 0 7pt;white-space:pre-wrap}</style></head><body><h1>maqna-cbt — ' . ($keys ? 'Kunci soal' : 'Naskah soal') . '</h1>';
        foreach ($this->lines($questions, $keys) as $line) $html .= '<p class="line">' . $escape($line) . '</p>';
        $options = new Options(); $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options); $pdf->loadHtml($html . '</body></html>'); $pdf->setPaper('A4'); $pdf->render();
        return $pdf->output();
    }

    public function docx(array $questions, bool $keys): string
    {
        $escape = static fn ($value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = '';
        foreach (array_merge(['maqna-cbt — ' . ($keys ? 'Kunci soal' : 'Naskah soal')], $this->lines($questions, $keys)) as $line) $body .= '<w:p><w:r><w:t xml:space="preserve">' . $escape($line) . '</w:t></w:r></w:p>';
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '</w:body></w:document>',
        ];
        $base = tempnam(WRITEPATH . 'cache', 'maqna-docx-');
        if ($base === false) throw new \RuntimeException('Berkas sementara tidak tersedia.');
        unlink($base); $path = $base . '.zip';
        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            foreach ($files as $name => $content) $archive->addFromString($name, $content);
            unset($archive);
            $content = file_get_contents($path);
            if ($content === false) throw new \RuntimeException('DOCX gagal dibuat.');
            return $content;
        } finally { if (is_file($path)) unlink($path); }
    }
}
