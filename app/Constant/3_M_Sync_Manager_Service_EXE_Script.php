<?php

namespace App\Constant;

use Closure;
use RuntimeException;
use Symfony\Component\Process\Process;

class M_Sync_Manager_Service_EXE_Script
{
    /**
     * @param  Closure(string): string  $get_Log_File_Path  Returns the log file for a run.
     * @param  Closure(string, string, callable): void  $update_Run  Updates one persisted run record.
     * @param  Closure(string, string): void  $append_Run_Output  Appends output to the run log.
     * @param  Closure(array<string, mixed>, string): int  $execute_Windows_Script  Runs one script on Windows.
     * @param  array<string, string>  $script_Files  Allowlisted script IDs mapped to PHP files.
     * @param  string  $missing_Entities_JSON_Warning  Output text indicating Entities.json is missing.
     */
    public function __construct(
        private readonly Closure $get_Log_File_Path,
        private readonly Closure $update_Run,
        private readonly Closure $append_Run_Output,
        private readonly Closure $execute_Windows_Script,
        private readonly array $script_Files,
        private readonly string $missing_Entities_JSON_Warning
    ) {}

    /**
     * Execute one allowlisted PHP script and return its exit code and Entities.json warning state.
     *
     * @param  array<string, mixed>  $run_Record  Persisted run record containing target information.
     * @param  string  $script_ID  Allowlisted script identifier.
     * @return array{exit_code: int, has_missing_entities_json: bool} Child result and missing source warning.
     */
    public function execute_EXE_Script(array $run_Record, string $script_ID): array
    {
        $script_Path = base_path($this->script_Files[$script_ID]);
        $log_Path = ($this->get_Log_File_Path)($run_Record['run_id']);
        clearstatcache(true, $log_Path);
        $log_Start_Offset = filesize($log_Path);
        if ($log_Start_Offset === false) {
            throw new RuntimeException("Could not inspect Sync Manager log for run {$run_Record['run_id']}");
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $exit_Code = ($this->execute_Windows_Script)($run_Record, $script_Path);
        } else {
            $script_Process = new Process(
                [PHP_BINARY, $script_Path],
                base_path(),
                [TargetManager::SYNC_TARGET_ENV => $run_Record['target']]
            );
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

            $script_Process->wait(function (string $type, string $output) use ($run_Record): void {
                ($this->append_Run_Output)($run_Record['run_id'], $output);
            });
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
        }

        clearstatcache(true, $log_Path);
        $log_Content = file_get_contents($log_Path);
        if ($log_Content === false) {
            throw new RuntimeException("Could not read Sync Manager log for run {$run_Record['run_id']}");
        }

        $script_Output = substr($log_Content, $log_Start_Offset);

        return [
            'exit_code' => $exit_Code,
            'has_missing_entities_json' => str_contains(
                $script_Output,
                $this->missing_Entities_JSON_Warning
            ),
        ];
    }
}
