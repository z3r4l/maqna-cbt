<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\PermissionPolicy;
use CodeIgniter\HTTP\ResponseInterface;

class QuestionMediaController extends BaseController
{
    private function context(): array|ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'UNAUTHENTICATED', 'message' => 'Silakan masuk.']]);
        if ((! $user->inGroup('teacher') && ! $user->inGroup('school_admin', 'superadmin')) || ! PermissionPolicy::allows($user, 'questions.manage')) {
            return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        }
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'FORBIDDEN', 'message' => 'Akses ditolak.']]);
        return [(int) $profile['school_id'], (int) $user->id];
    }

    public function upload(): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid() || $file->getSize() < 1 || $file->getSize() > 5 * 1024 * 1024) {
            return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => 'Gambar wajib diisi dan maksimal 5 MB.']]);
        }
        $tmp = $file->getTempName();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $image = @getimagesize($tmp);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (! isset($allowed[$mime]) || $image === false || $image['mime'] !== $mime) {
            return $this->response->setStatusCode(422)->setJSON(['error' => ['code' => 'VALIDATION', 'message' => 'Hanya PNG, JPEG, dan WebP yang valid.']]);
        }
        $directory = WRITEPATH . 'uploads/questions';
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) return $this->response->setStatusCode(500)->setJSON(['error' => ['code' => 'STORAGE', 'message' => 'Penyimpanan gagal.']]);
        $stored = bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
        $file->move($directory, $stored);
        $token = bin2hex(random_bytes(24));
        $now = gmdate('Y-m-d H:i:s');
        db_connect()->table('question_media_uploads')->insert([
            'token' => $token, 'school_id' => $context[0], 'actor_user_id' => $context[1], 'stored_name' => $stored,
            'original_name' => mb_substr(basename($file->getClientName()), 0, 180), 'mime_type' => $mime,
            'size_bytes' => $file->getSize(), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400), 'created_at' => $now,
        ]);
        return $this->response->setStatusCode(201)->setJSON(['data' => ['token' => $token, 'src' => '/api/v1/question-media/temp/' . $token], 'meta' => []]);
    }

    public function temporary(string $token): ResponseInterface
    {
        $context = $this->context();
        if ($context instanceof ResponseInterface) return $context;
        $upload = db_connect()->table('question_media_uploads')->where('token', $token)->where('school_id', $context[0])
            ->where('actor_user_id', $context[1])->where('expires_at >', gmdate('Y-m-d H:i:s'))->get()->getRowArray();
        if ($upload === null) return $this->response->setStatusCode(404)->setBody('');
        $path = WRITEPATH . 'uploads/questions/' . $upload['stored_name'];
        if (! is_file($path)) return $this->response->setStatusCode(404)->setBody('');
        return $this->response->setHeader('Content-Type', $upload['mime_type'])->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; sandbox")->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', 'inline')->setBody(file_get_contents($path));
    }
}
