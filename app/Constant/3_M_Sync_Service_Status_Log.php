<?php

namespace App\Constant;

class M_Sync_Service_Status_Log
{
    public const STATUS_FILE = 'app/Constant/3_M_Sync_Status.json';

    /**
     * We use our own log for frontend polling full content of all script
     * @var string
     */
    public const LOG_FILE = 'logs/3_M_Sync.log';

    /**
     * * Symfony Process(sf_proc_*.out) will create a temporary log file
     * * in the system's temp directory by default.
     * * 'app/tmp/sf_proc_00.out';
     * * Synfony truncate its files after run each script
     */
    public const STATUS_FAILED = 'failed';

    public static function reset_Status(): void
    {
        DataHelper::ensureDir(base_path(self::STATUS_FILE));
        $run_Record = self::read_run_Record();
        $run_Record['status'] = M_Sync_Service::PHASE_INITIAL;

        foreach ($run_Record['script_statuses'] as $script_id) {
            $run_Record['script_statuses'][$script_id] = M_Sync_Service::STATUS_PENDING;
        }
        self::write_M_Sync_Status_json($run_Record);
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
        $run_Record = self::read_run_Record();
        if (isset($run_Record)) {
            $run_Record['log_cursor'] = 0;
            self::write_M_Sync_Status_json($run_Record);
        }
        return 0;
    }

    /**
     * * read 3_M_Sync_Status.json¨
     * * create if file if not exist
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
    public static function read_run_Record(): array
    {
        $status_Path = base_path(self::STATUS_FILE);
        if (!file_exists($status_Path)) {
            Logger::collect_error('File was not exists , created one empty : ' . $status_Path);
            DataHelper::ensureDir($status_Path);
            return [];
        }
        return json_decode((string) file_get_contents($status_Path), true) ?? [];
    }

    /**
     * Persist the target-name keyed status map as formatted JSON.
     * @param  array<string, array<string, mixed>>  $run_Record  Persisted runs keyed by target name.
     */
    public static function write_M_Sync_Status_json(array $run_Record): void
    {
        DataHelper::ensureDir(base_path(self::STATUS_FILE));
        file_put_contents(
            base_path(self::STATUS_FILE),
            json_encode($run_Record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Update a run record by merging new attributes.
     * @param array<string, mixed> $new_Data Key-value pairs to update or add.
     */
    public static function update_Run(array $new_Data): void
    {
        $run_Record = self::read_run_Record();

        // this loop find if same key exists = update , else add new item-key
        foreach ($new_Data as $key => $value) {
            $run_Record[$key] = $value;
        }

        self::write_M_Sync_Status_json($run_Record);
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
     * * Log file path 'app/tmp/sf_proc_00.out' is managed by Synfony Process
     * * so, this is a copy of Synfony-log to m-project logs
     * * it must be /storage
     * * otherwise scirpt will not copy log from sf_proc_00.out
     * * and frontend Polling will not work
     * @return string
     */
    public static function get_Log_File_Path(): string
    {
        $log_Path = storage_path(self::LOG_FILE);
        $dir      = dirname($log_Path);
        DataHelper::ensureDir($dir);
        return $log_Path;
    }
}
