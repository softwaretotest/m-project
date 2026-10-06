<?php

namespace App\Constant;

use Illuminate\Console\Command;
use Throwable;

/**
 * execute worker process
 * @return int failure code to artisan
 */
class M_Sync_Artisan extends Command
{
    protected $signature = 'sync:run {phase}';

    protected $description = 'Run a detached Sync worker phase using static log and status.';

    public function handle(M_Sync_Service $sync_Service): int
    {
        $phase = (string) $this->argument('phase');

        try {
            $EXE_Worker = new M_Sync_Service_EXE_Worker();
            return $EXE_Worker->execute_Worker($phase);
        } catch (Throwable $exception) {
            $error_text = $exception->getMessage();
            Logger::collect_error(
                'ARTISAN COMMAND FAILED: phase = ' . $phase . PHP_EOL .
                ' ERROR = '. $error_text
            );
            M_Sync_Service_Status_Log::write_Log(Logger::$collected_message);
            $sync_Service->reportWorkerFailure($error_text);

            parent::error($error_text);

            // to return failure code to artisan
            return parent::FAILURE;
        }
    }
}
