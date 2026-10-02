<?php

namespace App\Constant;

use Closure;
use Symfony\Component\Process\Process;

class M_Sync_Manager_Service_Windows_Script
{
    /**
     * @param  Closure(string): string  $get_Log_File_Path  Returns the log file for a run.
     * @param  Closure(string, string, callable): void  $update_Run  Updates one persisted run record.
     */
    public function __construct(
        private readonly Closure $get_Log_File_Path,
        private readonly Closure $update_Run
    ) {}

    /**
     * Execute a Windows child script with output redirected to its run log.
     *
     * @param  array<string, mixed>  $run_Record  Persisted run record containing target information.
     * @param  string  $script_Path  Absolute path to the allowlisted PHP script.
     * @return int Child process exit code.
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
                'SYNC_RUN_LOG' => ($this->get_Log_File_Path)($run_Record['run_id']),
            ]
        );
        $script_Process->disableOutput();
        $script_Process->setTimeout(null);
        $script_Process->start();
        $child_Process_ID = $script_Process->getPid();

        if ($child_Process_ID !== null) {
            ($this->update_Run)(
                $run_Record['target'],
                $run_Record['run_id'],
                function (array $current_Run) use ($child_Process_ID): array {
                    $current_Run['child_pid'] = $child_Process_ID;
                    $current_Run['updated_at'] = date(DATE_ATOM);

                    return $current_Run;
                }
            );
        }

        $script_Process->wait();
        $exit_Code = $script_Process->getExitCode() ?? 1;
        ($this->update_Run)(
            $run_Record['target'],
            $run_Record['run_id'],
            function (array $current_Run): array {
                $current_Run['child_pid'] = null;
                $current_Run['updated_at'] = date(DATE_ATOM);

                return $current_Run;
            }
        );

        return $exit_Code;
    }
}
