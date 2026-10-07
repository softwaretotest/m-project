<?php

namespace App\Constant;

class M_Sync_Service_Status_Log
{
    public const STATUS_FILE = 'app/Constant/3_M_Sync_Status.json';

    /**
     * We use our own log for frontend polling full content of all script
     * @var string
     */
    public const LOG_FILE = 'app/Constant/3_M_Sync.log';

    /**
     * * Symfony Process(sf_proc_*.out) will create a temporary log file
     * * in the system's temp directory by default.
     * * 'app/tmp/sf_proc_00.out';
     * * Synfony truncate its files after run each script
     */

    private const STATUS_FAILED = 'failed';

    /**
     * Update one script's status in the persisted run record.
     *
     * @param  string  $target_Name  Configured target name.
     * @param  string  $script_ID  Allowlisted script identifier.
     * @param  string  $script_Status  Current script state.
     */
    public static function update_Script_Status(
        string $target_Name,
        string $script_ID,
        string $script_Status
    ): void {
        self::update_Run($target_Name, function (array $run_Record) use ($script_ID, $script_Status): array {
            $run_Record['script_statuses'][$script_ID] = $script_Status;
            $run_Record['updated_at'] = date(DATE_ATOM);
            return $run_Record;
        });
    }

    /**
     * Mark a run failed and persist the failure reason for the UI and developer.
     *
     * @param  string  $target_Name  Configured target name.
     * @param  string  $message  Failure details.
     */
    public static function fail_Run(string $target_Name, string $message): void
    {
        self::update_Run($target_Name, function (array $run_Record) use ($message): array {
            $run_Record['status'] = self::STATUS_FAILED;
            $run_Record['pid'] = null;
            $run_Record['child_pid'] = null;
            $run_Record['message'] = $message;
            $run_Record['finished_at'] = date(DATE_ATOM);
            $run_Record['updated_at'] = date(DATE_ATOM);
            return $run_Record;
        });
    }

    /**
     * Read a chunk of the shared run log starting from the given cursor position.
     *
     * @param  int  $cursor  Byte offset in the log file to start reading from.
     * @return array{content: string, cursor: int}  Log content and new cursor position.
     */
    public static function read_Log_Chunk(int $cursor = 0): array
    {
        $log_Path  = self::get_Log_File_Path();
        $file_Size = filesize($log_Path) ?: 0;

        //  cursor at 0 , after reset_Log
        if ($cursor > $file_Size) {
            $cursor = 0;
        }

        $fh = @fopen($log_Path, 'r');
        if ($fh === false) {
            return ['content' => '', 'cursor' => $cursor];
        }

        fseek($fh, $cursor);
        $content    = stream_get_contents($fh);
        $new_Cursor = ftell($fh);
        fclose($fh);

        return ['content' => $content, 'cursor' => $new_Cursor];
    }

    /**
     * Clear the shared run log and reset the cursor for polling.
     * @return int 0 = clear log , because all processes are complete
     * @return int 1 = do not clear log
     */
    public static function reset_Log(): int
    {
        $target_Name = TargetManager::get_activeTarget();
        $log_Path    = self::get_Log_File_Path();

        // clean remaining file of worker
        $fp = @fopen($log_Path, 'w');
        if ($fp === false) {
            Logger::collect_error("Could not clear log : {$log_Path}");
            return 1;
        }
        @flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        @flock($fp, LOCK_UN);
        fclose($fp);

        // IMPORTAINT : reset old cursor , otherwise polling will fseek wrong far positon than End of File
        $status_Data = self::read_Status_Data();
        if (isset($status_Data[$target_Name])) {
            $status_Data[$target_Name]['log_cursor'] = 0;
            self::write_Status_Data($status_Data);
        }
        return 0;
    }

    /**
     * * read 3_M_Sync_Status.json¨
     * @return array<string, array<string, mixed>>  e.g.
     * *{
     * *  "ecommerce": {
     * *       "target": "ecommerce",
     * *        "status": "completed",
     * *        "selected_scripts": [
     * *           "json_to_php",
     * *           "php_to_json",
     * *           "migration",
     * *           "generators"
     * *       ],
     * *       "script_statuses": {
     * *          "json_to_php": "completed",
     * *          "php_to_json": "completed",
     * *          "migration": "completed",
     * *          "generators": "completed"
     * *      },
     * *      "message": null
     * *  }
     * *}
     */
    public static function read_Status_Data(): array
    {
        $status_Path = base_path(self::STATUS_FILE);
        if (!file_exists($status_Path)) {
            return [];
        }
        return json_decode((string) file_get_contents($status_Path), true) ?? [];
    }

    /**
     * Persist the target-name keyed status map as formatted JSON.
     * @param  array<string, array<string, mixed>>  $status_Data  Persisted runs keyed by target name.
     */
    public static function write_Status_Data(array $status_Data): void
    {
        DataHelper::ensureDir(base_path(self::STATUS_FILE));
        file_put_contents(
            base_path(self::STATUS_FILE),
            json_encode($status_Data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Update a run record while holding the status file lock.
     * @param  string  $target_Name  Configured target name.
     * @param  callable(array<string, mixed>): array<string, mixed>  $update_Run_Record  Run record transformation.
     */
    public static function update_Run(string $target_Name, callable $update_Run_Record): void
    {
        // 1. read all actuell status_Data to run_Record
        $status_Data = self::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

        // 2. validate
        if (!is_array($run_Record)) {
            Logger::collect_error("Sync run changed before update");
        }

        // 3. send $run_Record to get new change , and write status
        $status_Data[$target_Name] = $update_Run_Record($run_Record);
        self::write_Status_Data($status_Data);
    }

    public static function write_Log(string $content, bool $append = true): void
    {
        $valid_content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        if ($content === '') {
            return;
        }
        $log_Path = self::get_Log_File_Path();

        $flags = $append ? (FILE_APPEND | LOCK_EX) : LOCK_EX;
        /**
         * @ = stop showing error
         */
        @file_put_contents($log_Path, $valid_content, $flags);
    }

    /**
     * Log file path , that managed by Synfony
     * @return string
     */
    public static function get_Log_File_Path(): string
    {
        $log_Path = storage_path(self::LOG_FILE);
        $dir      = dirname($log_Path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!file_exists($log_Path)) {
            file_put_contents($log_Path, '');
        }

        return $log_Path;
    }
}
