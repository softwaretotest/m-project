<?php

namespace App\Constant;

use RuntimeException;
use Symfony\Component\Process\Process;

class M_Sync_Service_EXE_Script
{
    private const SCRIPT_FILES = [
        M_Sync_Service::SCRIPT_JSON_TO_PHP => 'app/Constant/2_M_Sync_JSON.php',
        M_Sync_Service::SCRIPT_PHP_TO_JSON => 'app/Constant/1_M_Sync.php',
        M_Sync_Service::SCRIPT_MIGRATION => 'app/Constant/0_Runner_run.php',
        M_Sync_Service::SCRIPT_GENERATORS => 'app/Constant/3_EntityGenerator.php',
    ];

    /**
     * * Execute one allowlisted PHP script
     * * and return its exit code and Entities.json warning state.
     * @param  array<string, mixed>  $run_Record  Persisted run record containing target information.
     * @param  string  $script_ID  Allowlisted script identifier.
    */
    public function execute_EXE_Script(array $run_Record, string $script_ID): array
    {
        $target_Name = (string) ($run_Record['target'] ?? '');
        $script_Path = base_path(self::SCRIPT_FILES[$script_ID]);
        $log_Path = M_Sync_Service_Status_Log::get_Log_File_Path();

        clearstatcache(true, $log_Path);
        $log_Start_Offset = filesize($log_Path);
        if ($log_Start_Offset === false) {
            Logger::collect_error("Could not inspect Sync log for target {$target_Name}");
        }

        // force change Temp Dir to storage to prevent Permission denied on C:\WINDOWS
        $temp_Dir = storage_path('app/tmp');
        if (!is_dir($temp_Dir)) {
            mkdir($temp_Dir, 0775, true);
        }
        /**
         * * putenv = put (Environment Variables)
         * * determines the environment variables for the current process and its child processes.
         */
        putenv('TMP=' . $temp_Dir);
        putenv('TEMP=' . $temp_Dir);

        /**
         * * Symfony will truncate it own files , not our log
         * * during the synfony write it own log and truncate after finish a script
         * * we write apend content(buffer) to logs of all script at the end
         * * IMPORTANT : Do not remove param string $type , because Symfony Process use it ,
         * *             to make UI show Log message, insteat of 'out'
         */
        $script_Process = new Process(
            [PHP_BINARY, $script_Path],
            base_path(),
            [TargetManager::SYNC_TARGET_ENV => $run_Record['target']]
        );
        $script_Process->setTimeout(null);
        // get stream realtime and append in log

        // START MarK-Script-Color
        M_Sync_Service_Status_Log::write_Log("[[M_SYNC_SCRIPT_START:{$script_ID}]]\n", true);

        // Do not remove param string $type , because Symfony Process use it ,
        // to make UI show Log message, insteat of 'out'
        $script_Process->start(function (string $type, string $buffer): void {
            M_Sync_Service_Status_Log::write_Log($buffer, true);
        });

        $script_Process->wait();

        // END MarK-Script-Color
        M_Sync_Service_Status_Log::write_Log("[[M_SYNC_SCRIPT_END:{$script_ID}]]\n", true);

        $exit_Code = $script_Process->getExitCode() ?? 1;

        clearstatcache(true, $log_Path);
        $log_Content = file_get_contents($log_Path);
        if ($log_Content === false) {
            throw new RuntimeException("Could not read Sync log for target {$target_Name}");
        }

        $script_Output = substr($log_Content, $log_Start_Offset);

        return [
            'exit_code' => $exit_Code ?? 0,
            'has_missing_entities_json' => str_contains(
                $script_Output,
                M_Sync_Service::MISSING_ENTITIES_JSON_WARNING
            ),
        ];
    }
}
