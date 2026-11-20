<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserModel;

class AuthController extends BaseController
{
    private function data(array $value, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['data' => $value, 'meta' => []]);
    }

    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    public function csrf(): ResponseInterface
    {
        return $this->data(['header' => csrf_header(), 'token' => csrf_hash()]);
    }

    public function login(): ResponseInterface
    {
        $input = $this->request->getJSON(true) ?? [];
        $identity = mb_strtolower(trim((string) ($input['identity'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if ($identity === '' || $password === '' || mb_strlen($identity) > 254) {
            return $this->error('VALIDATION', 'Identitas dan kata sandi wajib diisi.', 422);
        }
        if (str_ends_with($identity, '@internal.maqna.invalid')) {
            return $this->error('INVALID_CREDENTIALS', 'Identitas atau kata sandi salah.', 401);
        }

        $throttler = service('throttler');
        $key = 'login_' . hash('sha256', $this->request->getIPAddress() . ':' . $identity);
        if (! $throttler->check($key, 5, MINUTE)) {
            return $this->error('RATE_LIMIT', 'Terlalu banyak percobaan. Coba lagi nanti.', 429);
        }

        $field = str_contains($identity, '@') ? 'email' : 'username';
        $result = auth('session')->attempt([$field => $identity, 'password' => $password]);
        if (! $result->isOK()) {
            return $this->error('INVALID_CREDENTIALS', 'Identitas atau kata sandi salah.', 401);
        }

        $user = auth('session')->user();
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->get()->getRowArray();
        if ($profile === null || (int) $profile['active'] !== 1) {
            auth('session')->logout();
            return $this->error('INVALID_CREDENTIALS', 'Identitas atau kata sandi salah.', 401);
        }

        service('session')->regenerate(true);
        if ($user->inGroup('student')) service('session')->set('maqna_student_session_key', bin2hex(random_bytes(32)));
        $sessionKey = bin2hex(random_bytes(32));
        try { (new \App\Services\SessionPolicy(db_connect()))->register((int) $profile['school_id'], (int) $user->id, (new \App\Services\SessionPolicy(db_connect()))->role($user), $sessionKey); }
        catch (\DomainException $error) { auth('session')->logout(); return $this->error('SESSION_LIMIT', $error->getMessage(), 409); }
        service('session')->set('maqna_account_session_key', $sessionKey);
        return $this->data($this->profile($user, $profile));
    }

    public function logout(): ResponseInterface
    {
        $user = auth('session')->user();
        $accountKey = service('session')->get('maqna_account_session_key');
        if ($user !== null && is_string($accountKey)) db_connect()->table('account_sessions')->where('session_key_hash', hash('sha256', $accountKey))->where('revoked_at', null)->update(['revoked_at' => gmdate('Y-m-d H:i:s'), 'revoke_reason' => 'Pengguna keluar']);
        $key = service('session')->get('maqna_student_session_key');
        if ($user !== null && $user->inGroup('student') && is_string($key)) {
            db_connect()->table('student_sessions')->where('session_key_hash', hash('sha256', $key))->where('revoked_at', null)
                ->update(['revoked_at' => gmdate('Y-m-d H:i:s'), 'revoke_reason' => 'Siswa keluar dari akun']);
        }
        auth('session')->logout();
        return $this->data(['loggedOut' => true]);
    }

    public function me(): ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        }
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->get()->getRowArray();
        if ($profile === null || (int) $profile['active'] !== 1) {
            auth('session')->logout();
            return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        }
        return $this->data($this->profile($user, $profile));
    }

    public function changePassword(): ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        }
        $input = $this->request->getJSON(true) ?? [];
        $current = (string) ($input['currentPassword'] ?? '');
        $next = (string) ($input['newPassword'] ?? '');
        if (strlen($next) < 12 || strlen($next) > 200) {
            return $this->error('VALIDATION', 'Kata sandi baru harus 12–200 karakter.', 422);
        }
        $check = auth('session')->check(['username' => $user->username, 'password' => $current]);
        if (! $check->isOK()) {
            return $this->error('INVALID_CREDENTIALS', 'Kata sandi saat ini salah.', 403);
        }
        $user->password = $next;
        (new UserModel())->save($user);
        db_connect()->table('user_profiles')->where('user_id', $user->id)->update(['must_change_password' => 0]);
        return $this->data(['changed' => true]);
    }

    public function updateProfile(): ResponseInterface
    {
        $user = auth('session')->user();
        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        }
        $input = $this->request->getJSON(true) ?? [];
        if (array_diff(array_keys($input), ['displayName']) !== []) {
            return $this->error('VALIDATION', 'Hanya nama tampilan yang dapat diubah di profil.', 422);
        }
        $name = trim((string) ($input['displayName'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            return $this->error('VALIDATION', 'Nama harus 2–120 karakter.', 422);
        }
        db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->update(['display_name' => $name]);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->get()->getRowArray();
        return $this->data($this->profile($user, $profile));
    }

    private function profile($user, array $profile): array
    {
        $permissions = array_keys(config(\Config\AuthGroups::class)->permissions);
        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'email' => $user->email && ! str_ends_with($user->email, '@internal.maqna.invalid') ? $user->email : null,
            'name' => $profile['display_name'],
            'schoolId' => (int) $profile['school_id'],
            'roles' => $user->getGroups() ?? [],
            'permissions' => array_values(array_filter($permissions, static fn (string $permission): bool => \App\Services\PermissionPolicy::allows($user, $permission))),
            'mustChangePassword' => (bool) $profile['must_change_password'],
        ];
    }
}
