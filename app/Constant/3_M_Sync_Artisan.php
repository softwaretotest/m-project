<?php

namespace App\Constant;

use Illuminate\Console\Command;
use Throwable;

/**
 * Artisan entry point for the detached Sync command.
 * It passes the run ID and phase to the service.
 */
class M_Sync_Artisan extends Command
{
    protected $signature = 'sync:run {run_id} {phase}';

    protected $description = 'Run one persisted Sync phase.';

    /**
     * Execute a detached Sync worker phase.
     *
     * @param M_Sync_Service $sync_Service Handles persisted run state and script execution.
     * @return int Process exit code.
     */
    public function handle(M_Sync_Service $sync_Service): int
    {
        $run_ID = (string) $this->argument('run_id');

        try {
            return $sync_Service->executeWorker(
                $run_ID,
                (string) $this->argument('phase')
            );
        } catch (Throwable $exception) {
            $sync_Service->reportWorkerFailure($run_ID, $exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
