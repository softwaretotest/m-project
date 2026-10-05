<?php

namespace App\Constant;

use Symfony\Component\Process\Process;

class M_Sync_Service_Windows_Script
{
    /**
     * Execute a Windows child script with output redirected to its run log.
     *
     * @param  array<string, mixed>  $run_Record  Persisted run record containing target information.
     * @param  string  $script_Path  Absolute path to the allowlisted PHP script.
     * @return int $exit_Code process exit code.
     */
    public function execute_Windows_Script(array $run_Record, string $script_Path): int
    {
        $script_Process = Process::fromShellCommandline(
            '"${:SYNC_PHP_BINARY}" "${:SYNC_SCRIPT_PATH}" >> "${:SYNC_RUN_LOG}" 2>&1',
            base_path(),
            [
                TargetManager::SYNC_TARGET_ENV => $run_Record['target'],
                'SYNC_PHP_BINARY' => PHP_BINARY,
                'SYNC_SCRIPT_PATH' => $script_Path,
                'SYNC_RUN_LOG' => M_Sync_Service_Status_log::get_Log_File_Path(),
            ]
        );
        $script_Process->disableOutput();
        $script_Process->setTimeout(null);
        $script_Process->start();

        $script_Process->wait();
        $exit_Code = $script_Process->getExitCode() ?? 1;

        return $exit_Code;
    }
}
