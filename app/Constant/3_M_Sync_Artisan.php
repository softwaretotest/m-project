<?php

namespace App\Constant;

use Illuminate\Console\Command;
use Throwable;

class M_Sync_Artisan extends Command
{
    protected $signature = 'sync:run {phase}';

    protected $description = 'Run a detached Sync worker phase using static log and status.';

    public function handle(M_Sync_Service $sync_Service): int
    {
        // \Illuminate\Support\Facades\Log::info('[ -2 ] ARTISAN COMMAND HANDLE CALLED');

        $phase = (string) $this->argument('phase');

        try {
            // \Illuminate\Support\Facades\Log::info('[ -1 ]  ARTISAN COMMAND TRYING TO EXECUTE WORKER: phase = ' . $phase);
            $EXE_Worker = new M_Sync_Service_EXE_Worker();
            return $EXE_Worker->execute_Worker($phase);
        } catch (Throwable $exception) {
            \Illuminate\Support\Facades\Log::info('[🚫] ARTISAN COMMAND FAILED: phase = ' . $phase);
            $sync_Service->reportWorkerFailure($exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
