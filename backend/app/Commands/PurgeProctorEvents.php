<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PurgeProctorEvents extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'proctor:purge';
    protected $description = 'Delete proctor telemetry older than 30 days.';

    public function run(array $params)
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
        db_connect()->table('proctor_events')->where('created_at <', $cutoff)->delete();
        CLI::write('Old proctor events removed.');
    }
}
