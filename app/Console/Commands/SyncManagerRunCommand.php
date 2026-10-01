<?php

namespace App\Console\Commands;

use App\Services\SyncManagerService;
use Illuminate\Console\Command;
use Throwable;

class SyncManagerRunCommand extends Command
{
    protected $signature = 'sync-manager:run {run_id} {phase}';

    protected $description = 'Run one persisted Sync Manager phase.';

    /**
     * Execute a detached Sync Manager worker phase.
     *
     * @param SyncManagerService $sync_Manager_Service Handles persisted run state and script execution.
     * @return int Process exit code.
     */
    public function handle(SyncManagerService $sync_Manager_Service): int
    {
        $run_ID = (string) $this->argument('run_id');

        try {
            return $sync_Manager_Service->executeWorker(
                $run_ID,
                (string) $this->argument('phase')
            );
        } catch (Throwable $exception) {
            $sync_Manager_Service->reportWorkerFailure($run_ID, $exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
