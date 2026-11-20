<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class QuestionMedia
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function bind(int $school, int $actor, int $questionId, array $contents, ?int $questionVersionId = null): array
    {
        $tokens = [];
        foreach ($contents as $content) {
            if (preg_match_all('~src=["\'](/api/v1/question-media/temp/([A-Za-z0-9_-]{32,80}))["\']~i', $content, $matches)) {
                foreach ($matches[2] as $token) $tokens[$token] = true;
            }
        }
        if (count($tokens) > 10) throw new \DomainException('Satu soal maksimal memuat 10 gambar.');
        $replace = [];
        foreach (array_keys($tokens) as $token) {
            $upload = $this->db->table('question_media_uploads')->where('token', $token)->where('school_id', $school)
                ->where('actor_user_id', $actor)->where('expires_at >', gmdate('Y-m-d H:i:s'))->get()->getRowArray();
            if ($upload === null) throw new \DomainException('Gambar sementara tidak ditemukan atau sudah kedaluwarsa.');
            $now = gmdate('Y-m-d H:i:s');
            $this->db->table('question_assets')->insert([
                'school_id' => $school, 'question_id' => $questionId, 'question_version_id' => $questionVersionId, 'stored_name' => $upload['stored_name'],
                'original_name' => $upload['original_name'], 'mime_type' => $upload['mime_type'],
                'size_bytes' => $upload['size_bytes'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            $assetId = (int) $this->db->insertID();
            $replace['/api/v1/question-media/temp/' . $token] = '/api/v1/question-assets/' . $assetId;
            $this->db->table('question_media_uploads')->where('token', $token)->delete();
        }
        return $replace;
    }

    public static function replace(array $contents, array $references): array
    {
        foreach ($contents as &$content) $content = strtr($content, $references);
        unset($content);
        return $contents;
    }
}
