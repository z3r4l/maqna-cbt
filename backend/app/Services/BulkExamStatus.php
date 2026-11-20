<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class BulkExamStatus
{
    public function __construct(private BaseConnection $db) {}

    private function inspect(int $school, array $ids, string $target): array
    {
        if (! in_array($target, ['paused', 'published'], true)) throw new \DomainException('Status tujuan tidak valid.');
        if ($ids === [] || ! array_is_list($ids) || count($ids) > 100 || count($ids) !== count(array_unique($ids))) throw new \DomainException('Pilih 1-100 ujian unik.');
        foreach ($ids as $id) if (! is_int($id) || $id < 1) throw new \DomainException('ID ujian tidak valid.');
        $rows = $this->db->table('exams')->select('id, title, status')->where('school_id', $school)->whereIn('id', $ids)->get()->getResultArray();
        if (count($rows) !== count($ids)) throw new \DomainException('Ujian di luar sekolah atau tidak ditemukan.');
        $blocked = [];
        foreach ($rows as $row) {
            $from = $target === 'paused' ? 'published' : 'paused';
            if ($row['status'] !== $from) $blocked[] = ['id' => (int) $row['id'], 'reason' => 'Status awal tidak sesuai'];
            $attempts = $this->db->table('attempts')->where('school_id', $school)->where('exam_id', $row['id'])->countAllResults();
            if ($attempts > 0) $blocked[] = ['id' => (int) $row['id'], 'reason' => 'Sudah ada ' . $attempts . ' percobaan; status tidak boleh diubah'];
        }
        return ['rows' => $rows, 'blocked' => $blocked];
    }

    public function preview(int $school, int $actor, array $ids, string $target): array
    {
        $inspected = $this->inspect($school, $ids, $target);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('bulk_action_previews')->insert(['school_id' => $school, 'actor_user_id' => $actor, 'kind' => $target === 'paused' ? 'exam_pause' : 'exam_resume',
            'ids_json' => json_encode($ids, JSON_THROW_ON_ERROR), 'status' => 'preview', 'created_at' => $now, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 900)]);
        return ['id' => (int) $this->db->insertID(), 'target' => $target, 'rows' => $inspected['rows'], 'blocked' => $inspected['blocked']];
    }

    public function commit(int $school, int $actor, int $previewId, string $target, array $input): array
    {
        if (! in_array($target, ['paused', 'published'], true) || ($input['confirmed'] ?? false) !== true) throw new \DomainException('Status tujuan dan konfirmasi wajib diisi.');
        $this->db->transBegin();
        try {
            $preview = $this->db->query('SELECT * FROM bulk_action_previews WHERE id=? AND school_id=? AND actor_user_id=? AND kind=? FOR UPDATE', [$previewId, $school, $actor, $target === 'paused' ? 'exam_pause' : 'exam_resume'])->getRowArray();
            if ($preview === null) throw new \OutOfBoundsException('Pratinjau tidak ditemukan.');
            if ($preview['status'] !== 'preview' || $preview['expires_at'] <= gmdate('Y-m-d H:i:s')) throw new \DomainException('Pratinjau kedaluwarsa atau sudah digunakan.');
            $ids = json_decode($preview['ids_json'], true, 512, JSON_THROW_ON_ERROR);
            $inspected = $this->inspect($school, $ids, $target);
            if ($inspected['blocked'] !== []) throw new \DomainException('Status atau aktivitas ujian berubah. Buat pratinjau baru.');
            $from = $target === 'paused' ? 'published' : 'paused';
            $updated = $this->db->table('exams')->where('school_id', $school)->where('status', $from)->whereIn('id', $ids)->update(['status' => $target, 'updated_at' => gmdate('Y-m-d H:i:s')]);
            if (! $updated || $this->db->affectedRows() !== count($ids)) throw new \DomainException('Status ujian berubah. Buat pratinjau baru.');
            $this->db->table('bulk_action_previews')->where('id', $previewId)->update(['status' => 'committed', 'committed_at' => gmdate('Y-m-d H:i:s')]);
            (new AuditTrail($this->db))->record($school, $actor, 'exams.bulk_status', 'exam', $previewId, ['ids' => $ids, 'target' => $target]);
            if (! $this->db->transStatus()) throw new \RuntimeException('Perubahan status gagal.');
            $this->db->transCommit();
            return ['updated' => count($ids), 'target' => $target];
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
