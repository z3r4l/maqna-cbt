<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class BulkPeopleService
{
    public function __construct(private BaseConnection $db) {}

    private function table(string $kind): string
    {
        return match ($kind) {
            'students' => 'students',
            'teachers' => 'teachers',
            default => throw new \DomainException('Jenis data tidak valid.'),
        };
    }

    private function inspect(int $school, string $kind, array $ids): array
    {
        $table = $this->table($kind);
        $rows = $this->db->table($table . ' p')->select('p.id, p.user_id, p.status, up.display_name')
            ->join('user_profiles up', 'up.user_id=p.user_id')
            ->where('p.school_id', $school)->whereIn('p.id', $ids)->get()->getResultArray();
        if (count($rows) !== count($ids)) throw new \DomainException('Pilihan berisi data di luar sekolah atau tidak ditemukan.');
        $blocked = [];
        foreach ($rows as $row) {
            if ($row['status'] !== 'active') $blocked[] = ['id' => (int) $row['id'], 'reason' => 'Sudah tidak aktif'];
            if ($kind === 'students' && $this->db->table('attempts')->where('student_id', $row['id'])->where('status', 'active')->where('deadline_at >', gmdate('Y-m-d H:i:s'))->countAllResults() > 0) {
                $blocked[] = ['id' => (int) $row['id'], 'reason' => 'Sedang mengerjakan ujian'];
            }
            if ($kind === 'teachers' && $this->db->table('teaching_assignments')->where('teacher_id', $row['id'])->where('active', 1)->countAllResults() > 0) {
                $blocked[] = ['id' => (int) $row['id'], 'reason' => 'Masih memiliki penugasan aktif'];
            }
        }
        return ['rows' => $rows, 'blocked' => $blocked];
    }

    public function preview(int $school, int $actor, string $kind, array $ids): array
    {
        $this->table($kind);
        if ($ids === [] || ! array_is_list($ids) || count($ids) > 100 || count($ids) !== count(array_unique($ids))) throw new \DomainException('Pilih 1-100 ID unik.');
        foreach ($ids as $id) if (! is_int($id) || $id < 1) throw new \DomainException('ID tidak valid.');
        $inspected = $this->inspect($school, $kind, $ids);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('bulk_action_previews')->insert(['school_id' => $school, 'actor_user_id' => $actor, 'kind' => $kind, 'ids_json' => json_encode($ids, JSON_THROW_ON_ERROR), 'status' => 'preview', 'created_at' => $now, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 900)]);
        return ['id' => (int) $this->db->insertID(), 'count' => count($ids), 'rows' => array_map(static fn ($row) => ['id' => (int) $row['id'], 'name' => $row['display_name']], $inspected['rows']), 'blocked' => $inspected['blocked']];
    }

    public function commit(int $school, int $actor, string $kind, int $previewId, array $input): array
    {
        if (array_diff(array_keys($input), ['reason', 'confirmed', 'confirmationText']) !== []) throw new \DomainException('Kolom tidak diizinkan.');
        $reason = trim((string) ($input['reason'] ?? ''));
        if (($input['confirmed'] ?? false) !== true || mb_strlen($reason) < 10 || mb_strlen($reason) > 500) throw new \DomainException('Konfirmasi dan alasan 10-500 karakter wajib diisi.');
        $this->db->transBegin();
        try {
            $preview = $this->db->query('SELECT * FROM bulk_action_previews WHERE id=? AND school_id=? AND actor_user_id=? AND kind=? FOR UPDATE', [$previewId, $school, $actor, $kind])->getRowArray();
            if ($preview === null) throw new \OutOfBoundsException('Pratinjau tidak ditemukan.');
            if ($preview['status'] !== 'preview' || $preview['expires_at'] <= gmdate('Y-m-d H:i:s')) throw new \DomainException('Pratinjau kedaluwarsa atau telah digunakan.');
            $ids = json_decode($preview['ids_json'], true, 512, JSON_THROW_ON_ERROR);
            if (($input['confirmationText'] ?? '') !== 'NONAKTIFKAN ' . count($ids)) throw new \DomainException('Ketik konfirmasi sesuai jumlah pilihan.');
            $inspected = $this->inspect($school, $kind, $ids);
            if ($inspected['blocked'] !== []) throw new \DomainException('Pilihan berubah atau masih memiliki aktivitas aktif. Periksa ulang.');
            $table = $this->table($kind);
            $userIds = array_map('intval', array_column($inspected['rows'], 'user_id'));
            $this->db->table($table)->where('school_id', $school)->whereIn('id', $ids)->update(['status' => 'inactive', 'updated_at' => gmdate('Y-m-d H:i:s')]);
            $this->db->table('user_profiles')->where('school_id', $school)->whereIn('user_id', $userIds)->update(['active' => 0]);
            $this->db->table('bulk_action_previews')->where('id', $previewId)->update(['status' => 'committed', 'committed_at' => gmdate('Y-m-d H:i:s')]);
            (new AuditTrail($this->db))->record($school, $actor, 'people.bulk_deactivate', $kind, $previewId, ['ids' => $ids, 'reason' => $reason]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Aksi massal gagal.');
            $this->db->transCommit();
            return ['deactivated' => count($ids)];
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
