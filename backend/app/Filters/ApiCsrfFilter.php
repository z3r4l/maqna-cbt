<?php

namespace App\Filters;

use CodeIgniter\Filters\CSRF;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

final class ApiCsrfFilter extends CSRF
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            return parent::before($request, $arguments);
        } catch (SecurityException $exception) {
            if ($request instanceof IncomingRequest && str_starts_with(trim($request->getPath(), '/'), 'api/v1/')) {
                return service('response')->setStatusCode(403)->setJSON([
                    'error' => [
                        'code' => 'CSRF_INVALID',
                        'message' => 'Sesi keamanan berubah. Permintaan perlu dicoba kembali.',
                    ],
                ]);
            }

            throw $exception;
        }
    }
}
