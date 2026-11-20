<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class AuditTrail
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function record(int $school, int $actor, string $action, string $type, int $target, array $metadata = []): void
    {
        $request = service('request');
        if ($request instanceof \CodeIgniter\HTTP\IncomingRequest) $metadata['ip'] = $request->getIPAddress();
        $this->db->table('audit_events')->insert([
            'school_id' => $school, 'actor_user_id' => $actor, 'action' => $action,
            'target_type' => $type, 'target_id' => $target,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
