<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Policies\SchoolScopePolicy;
use App\Services\AuditTrail;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserModel;

class PasswordController extends BaseController
{
    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    private function ok(array $value): ResponseInterface
    {
        return $this->response->setJSON(['data' => $value, 'meta' => []]);
    }

    private function target(): array|ResponseInterface
    {
        $actor = auth('session')->user();
        if ($actor === null) {
            return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        }
        $input = $this->request->getJSON(true) ?? [];
        $targetId = (int) ($input['userId'] ?? 0);
        if ($targetId === 0 && is_string($input['username'] ?? null)) {
            $lookup = (new UserModel())->where('username', mb_strtolower(trim($input['username'])))->first();
            $targetId = (int) ($lookup?->id ?? 0);
        }
        $db = db_connect();
        $actorProfile = $db->table('user_profiles')->where('user_id', $actor->id)->get()->getRowArray();
        $targetProfile = $db->table('user_profiles')->where('user_id', $targetId)->get()->getRowArray();
        if ($targetProfile === null || $actorProfile === null) {
            return $this->error('NOT_FOUND', 'Akun tidak ditemukan.', 404);
        }
        $target = (new UserModel())->find($targetId);
        if ($target === null) {
            return $this->error('NOT_FOUND', 'Akun tidak ditemukan.', 404);
        }
        if (! SchoolScopePolicy::canResetStudent($actor->getGroups() ?? [], $actor->can('users.manage'), (int) $actorProfile['school_id'], (int) $targetProfile['school_id'], $target->inGroup('student'))) {
            return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        }
        return [$target, $targetProfile];
    }

    public function adminReset(): ResponseInterface
    {
        $result = $this->target();
        if ($result instanceof ResponseInterface) return $result;
        [$target] = $result;
        $temporary = bin2hex(random_bytes(12));
        $target->password = $temporary;
        (new UserModel())->save($target);
        db_connect()->table('user_profiles')->where('user_id', $target->id)->update(['must_change_password' => 1]);
        (new AuditTrail(db_connect()))->record((int) $result[1]['school_id'], (int) auth('session')->user()->id, 'auth.admin_reset', 'user', (int) $target->id);
        return $this->ok(['temporaryPassword' => $temporary, 'mustChangePassword' => true]);
    }

    public function issueToken(): ResponseInterface
    {
        $result = $this->target();
        if ($result instanceof ResponseInterface) return $result;
        [$target] = $result;
        $raw = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        db_connect()->table('password_reset_tokens')->insert([
            'user_id' => $target->id,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 900),
            'used_at' => null,
            'created_at' => $now,
        ]);
        (new AuditTrail(db_connect()))->record((int) $result[1]['school_id'], (int) auth('session')->user()->id, 'auth.issue_reset_token', 'user', (int) $target->id);
        return $this->ok(['token' => $raw, 'expiresInSeconds' => 900]);
    }

    public function reset(): ResponseInterface
    {
        $input = $this->request->getJSON(true) ?? [];
        $raw = (string) ($input['token'] ?? '');
        $password = (string) ($input['newPassword'] ?? '');
        if (! preg_match('/\A[a-f0-9]{64}\z/', $raw) || strlen($password) < 12 || strlen($password) > 200) {
            return $this->error('VALIDATION', 'Token atau kata sandi tidak valid.', 422);
        }
        $db = db_connect();
        $row = $db->table('password_reset_tokens')->where('token_hash', hash('sha256', $raw))
            ->where('used_at', null)->where('expires_at >', gmdate('Y-m-d H:i:s'))->get()->getRowArray();
        if ($row === null) {
            return $this->error('INVALID_TOKEN', 'Token tidak valid atau telah kedaluwarsa.', 422);
        }
        $db->transBegin();
        $db->table('password_reset_tokens')->where('id', $row['id'])->where('used_at', null)->update(['used_at' => gmdate('Y-m-d H:i:s')]);
        if ($db->affectedRows() !== 1) {
            $db->transRollback();
            return $this->error('INVALID_TOKEN', 'Token tidak valid atau telah kedaluwarsa.', 422);
        }
        $user = (new UserModel())->find($row['user_id']);
        if ($user === null) {
            $db->transRollback();
            return $this->error('INVALID_TOKEN', 'Token tidak valid atau telah kedaluwarsa.', 422);
        }
        $user->password = $password;
        (new UserModel())->save($user);
        $db->table('user_profiles')->where('user_id', $user->id)->update(['must_change_password' => 0]);
        $db->transCommit();
        $profile = $db->table('user_profiles')->where('user_id', $user->id)->get()->getRowArray();
        if ($profile !== null) (new AuditTrail($db))->record((int) $profile['school_id'], (int) $user->id, 'auth.password_reset', 'user', (int) $user->id);
        return $this->ok(['changed' => true]);
    }
}
