<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AccountSessionGuard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth('session')->user();
        if ($user === null) return null;
        $session = service('session');
        $key = $session->get('maqna_account_session_key');
        if (! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key)) {
            $key = bin2hex(random_bytes(32));
            $session->set('maqna_account_session_key', $key);
            $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
            if ($profile !== null) {
                try { (new \App\Services\SessionPolicy(db_connect()))->register((int) $profile['school_id'], (int) $user->id, (new \App\Services\SessionPolicy(db_connect()))->role($user), $key); }
                catch (\DomainException) { auth('session')->logout(); return service('response')->setStatusCode(409)->setJSON(['error' => ['code' => 'SESSION_LIMIT', 'message' => 'Batas sesi aktif tercapai.']]); }
            }
        }
        $db = db_connect();
        $record = $db->table('account_sessions')->where('session_key_hash', hash('sha256', $key))->get()->getRowArray();
        if ($record !== null && ($record['revoked_at'] !== null || (int) $record['user_id'] !== (int) $user->id)) {
            auth('session')->logout();
            return service('response')->setStatusCode(401)->setJSON(['error' => ['code' => 'SESSION_REVOKED', 'message' => 'Sesi berakhir. Silakan masuk kembali.']]);
        }
        if ($record !== null) $db->table('account_sessions')->where('id', $record['id'])->update(['last_seen_at' => gmdate('Y-m-d H:i:s')]);
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) { return null; }
}
