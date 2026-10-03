<?php

namespace App\Constant;

use RuntimeException;

class M_Sync_Service_Status_Log
{
    public const STATUS_FILE = 'app/Constant/3_M_Sync_Status.json';

    public const LOG_DIRECTORY = 'app/m-sync/logs';

    private const STATUS_FAILED = 'failed';

    /**
     * Update a run record while holding the status file lock.
     *
     * @param  string  $target_Name  Configured target name.
     * @param  string  $run_ID  Identifier of the run to update.
     * @param  callable(array<string, mixed>): array<string, mixed>  $update_Callback  Run record transformation.
     */
    public static function update_Run(string $target_Name, string $run_ID, callable $update_Callback): void
    {
        $status_Data = self::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

        if (! is_array($run_Record) || $run_Record['run_id'] !== $run_ID) {
            throw new RuntimeException("Sync run changed before update: {$run_ID}");
        }

        $status_Data[$target_Name] = $update_Callback($run_Record);
        self::write_Status_Data($status_Data);
    }

    /**
     * Update one script's status in the persisted run record.
     *
     * @param  string  $target_Name  Configured target name.
     * @param  string  $run_ID  Identifier of the run containing the script.
     * @param  string  $script_ID  Allowlisted script identifier.
     * @param  string  $script_Status  Current script state.
     */
    public static function update_Script_Status(
        string $target_Name,
        string $run_ID,
        string $script_ID,
        string $script_Status
    ): void {
        self::update_Run($target_Name, $run_ID, function (array $run_Record) use ($script_ID, $script_Status): array {
            $run_Record['script_statuses'][$script_ID] = $script_Status;
            $run_Record['updated_at'] = date(DATE_ATOM);

            return $run_Record;
        });
    }

    /**
     * Mark a run failed and persist the failure reason for the UI and developer.
     *
     * @param  string  $target_Name  Configured target name.
     * @param  string  $run_ID  Identifier of the failed run.
     * @param  string  $message  Failure details.
     */
    public static function fail_Run(string $target_Name, string $run_ID, string $message): void
    {
        self::update_Run($target_Name, $run_ID, function (array $run_Record) use ($message): array {
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
     * Append worker output to the current run's private log file.
     *
     * @param  string  $run_ID  Identifier of the run receiving output.
     * @param  string  $output  Raw output received from the child script.
     */
    public static function append_Run_Output(string $run_ID, string $output): void
    {
        if ($output === '') {
            return;
        }

        $valid_Output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
        $written = file_put_contents(
            self::get_Log_File_Path($run_ID),
            $valid_Output,
            FILE_APPEND | LOCK_EX
        );

        if ($written === false) {
            throw new RuntimeException("Could not append output to Sync log for run {$run_ID}");
        }
    }

    /**
     * Read only complete log lines after the byte cursor, except after the run has ended.
     *
     * @param  string  $run_ID  Identifier of the log to read.
     * @param  int  $cursor  Byte offset already consumed by the client.
     * @param  bool  $include_Final_Line  Include a final line without a newline for completed runs.
     * @return array{logs: string, cursor: int} Appended log text and the next byte cursor.
     */
    public static function read_Log_Chunk(string $run_ID, int $cursor, bool $include_Final_Line): array
    {
        $log_Path = self::get_Log_File_Path($run_ID);
        if (! file_exists($log_Path)) {
            return ['logs' => '', 'cursor' => 0];
        }

        $log_Content = file_get_contents($log_Path);
        if ($log_Content === false) {
            throw new RuntimeException("Could not read Sync log for run {$run_ID}");
        }

        $cursor = min($cursor, strlen($log_Content));
        $new_Content = substr($log_Content, $cursor);
        $last_Newline = strrpos($new_Content, "\n");

        if ($last_Newline === false) {
            if (! $include_Final_Line) {
                return ['logs' => '', 'cursor' => $cursor];
            }

            $complete_Content = $new_Content;
        } else {
            $complete_Content = substr($new_Content, 0, $last_Newline + 1);
        }

        return [
            'logs' => mb_convert_encoding($complete_Content, 'UTF-8', 'UTF-8'),
            'cursor' => $cursor + strlen($complete_Content),
        ];
    }

    /**
     * Read one status JSON file into a target-name keyed map.
     *
     * @return array<string, array<string, mixed>> Persisted runs keyed by target name.
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
     *
     * @param  array<string, array<string, mixed>>  $status_Data  Persisted runs keyed by target name.
     */
    public static function write_Status_Data(array $status_Data): void
    {
        self::ensureDir();
        file_put_contents(
            base_path(self::STATUS_FILE),
            json_encode($status_Data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Persist the target-name keyed status map as formatted JSON at the given path.
     *
     * @param  string  $status_Path  Absolute path to a status file.
     * @param  array<string, array<string, mixed>>  $status_Data  Persisted runs keyed by target name.
     */
    public static function write_Status_Data_To_Path(string $status_Path, array $status_Data): void
    {
        $status_JSON = json_encode($status_Data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($status_JSON === false) {
            throw new RuntimeException('Could not encode Sync status as JSON.');
        }

        $written = file_put_contents($status_Path, $status_JSON, LOCK_EX);
        if ($written === false) {
            throw new RuntimeException("Could not write the Sync status file: {$status_Path}");
        }
    }

    /**
     * Create private storage directories used for status and per-run logs.
     */
    public static function ensureDir(): void
    {
        // 1. จัดการโฟลเดอร์ของไฟล์สถานะใหม่ที่พาร์ท app/Constant/
        $status_Dir = dirname(base_path(self::STATUS_FILE));
        if (!is_dir($status_Dir)) {
            mkdir($status_Dir, 0775, true);
        }

        // 2. จัดการโฟลเดอร์เก็บ Log ฝั่ง storage
        $log_Dir = storage_path(self::LOG_DIRECTORY);
        if (!is_dir($log_Dir)) {
            mkdir($log_Dir, 0775, true);
        }
    }

    /**
     * Return the absolute path to one run's private output log.
     *
     * @param  string  $run_ID  Identifier of the run.
     * @return string Absolute run-log path.
     */
    public static function get_Log_File_Path(string $run_ID): string
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $run_ID)) {
            throw new RuntimeException('Invalid Sync run identifier.');
        }

        return storage_path(self::LOG_DIRECTORY."/{$run_ID}.log");
    }

    /**
     * Delete run logs that are no longer needed, preserving active or review-pending runs.
     *
     * @param  array<string, array<string, mixed>>  $status_Data  Persisted runs keyed by target name.
     * @param  callable(array<string, mixed>): bool  $is_Run_Active  Checks whether a run must retain its log.
     */
    public static function delete_Unused_Run_Logs(array $status_Data, callable $is_Run_Active): void
    {
        $active_Run_IDs = [];
        foreach ($status_Data as $run_Record) {
            if (! is_array($run_Record)) {
                continue;
            }

            $run_ID = $run_Record['run_id'] ?? null;
            if (
                is_string($run_ID)
                && preg_match('/^[0-9a-f-]{36}$/i', $run_ID)
                && isset($run_Record['status'])
                && $is_Run_Active($run_Record)
            ) {
                $active_Run_IDs[] = strtolower($run_ID);
            }
        }

        $log_Paths = glob(storage_path(self::LOG_DIRECTORY.'/*.log'));
        if ($log_Paths === false) {
            throw new RuntimeException('Could not list Sync run logs for cleanup.');
        }

        foreach ($log_Paths as $log_Path) {
            $run_ID = basename($log_Path, '.log');
            if (
                ! preg_match('/^[0-9a-f-]{36}$/i', $run_ID)
                || in_array(strtolower($run_ID), $active_Run_IDs, true)
            ) {
                continue;
            }

            if (! unlink($log_Path)) {
                throw new RuntimeException("Could not delete unused Sync log: {$log_Path}");
            }
        }
    }

    /**
     * Delete one run's log when its failed status is reset.
     *
     * @param  string  $run_ID  Identifier of the run.
     */
    public static function delete_Run_Log(string $run_ID): void
    {
        $log_Path = self::get_Log_File_Path($run_ID);
        if (file_exists($log_Path) && ! unlink($log_Path)) {
            throw new RuntimeException("Could not delete Sync log for run {$run_ID}");
        }
    }
}
