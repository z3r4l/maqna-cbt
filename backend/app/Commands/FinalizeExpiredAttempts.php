<?php

namespace App\Commands;

use App\Services\AttemptService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class FinalizeExpiredAttempts extends BaseCommand
{
    protected $group = 'maqna-cbt';
    protected $name = 'attempts:finalize-expired';
    protected $description = 'Finalize active attempts whose server deadline has passed.';

    public function run(array $params)
    {
        $count = (new AttemptService(db_connect()))->expireDue();
        CLI::write("Finalized {$count} expired attempt(s).");
    }
}
