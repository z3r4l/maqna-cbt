<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AcademicCatalog;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\ResponseInterface;

class AcademicController extends BaseController
{
    private function context(string $resource): array|ResponseInterface
    {
        $spec = AcademicCatalog::spec($resource);
        if ($spec === null) return $this->error('NOT_FOUND', 'Data tidak ditemukan.', 404);
        $user = auth('session')->user();
        if ($user === null) return $this->error('UNAUTHENTICATED', 'Silakan masuk.', 401);
        if (! $user->inGroup('school_admin', 'superadmin') || ! \App\Services\PermissionPolicy::allows($user, $spec['permission'])) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        $profile = db_connect()->table('user_profiles')->where('user_id', $user->id)->where('active', 1)->get()->getRowArray();
        if ($profile === null) return $this->error('FORBIDDEN', 'Akses ditolak.', 403);
        return [$spec, (int) $profile['school_id']];
    }

    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    public function index(string $resource): ResponseInterface
    {
        $context = $this->context($resource);
        if ($context instanceof ResponseInterface) return $context;
        [, $schoolId] = $context;
        $result = (new AcademicCatalog(db_connect()))->list($resource, $schoolId, (int) $this->request->getGet('page'), (int) ($this->request->getGet('perPage') ?? 20), trim((string) $this->request->getGet('search')));
        return $this->response->setJSON(['data' => $result['rows'], 'meta' => ['page' => $result['page'], 'perPage' => $result['perPage'], 'total' => $result['total']]]);
    }

    public function show(string $resource, string $id): ResponseInterface
    {
        $context = $this->context($resource);
        if ($context instanceof ResponseInterface) return $context;
        [, $schoolId] = $context;
        $row = (new AcademicCatalog(db_connect()))->find($resource, $schoolId, (int) $id);
        if ($row === null) return $this->error('NOT_FOUND', 'Data tidak ditemukan.', 404);
        return $this->response->setJSON(['data' => $row, 'meta' => []]);
    }

    public function create(string $resource): ResponseInterface
    {
        return $this->save($resource, null);
    }

    public function update(string $resource, string $id): ResponseInterface
    {
        return $this->save($resource, (int) $id);
    }

    private function save(string $resource, ?int $id): ResponseInterface
    {
        $context = $this->context($resource);
        if ($context instanceof ResponseInterface) return $context;
        [, $schoolId] = $context;
        try {
            $row = (new AcademicCatalog(db_connect()))->save($resource, $schoolId, $this->request->getJSON(true) ?? [], $id);
            return $this->response->setStatusCode($id === null ? 201 : 200)->setJSON(['data' => $row, 'meta' => []]);
        } catch (\DomainException $error) {
            return $this->error('VALIDATION', $error->getMessage(), 422);
        } catch (\OutOfBoundsException $error) {
            return $this->error('NOT_FOUND', 'Data tidak ditemukan.', 404);
        } catch (\LogicException $error) {
            return $this->error('CONFLICT', $error->getMessage(), 409);
        } catch (DatabaseException $error) {
            return $this->error('CONFLICT', 'Data sudah ada atau masih digunakan.', 409);
        }
    }
}
