<?php

namespace App\Constant;

use RuntimeException;

class M_Sync_Service_Status_Log
{
    public const STATUS_FILE = 'app/Constant/3_M_Sync_Status.json';

    // public const LOG_FILE = 'app/Constant/3_M_Sync.log';
    public const LOG_FILE = 'app/tmp/sf_proc_00.out';

    private const STATUS_FAILED = 'failed';

    /**
     * Update a run record while holding the status file lock.
     * @param  string  $target_Name  Configured target name.
     * @param  callable(array<string, mixed>): array<string, mixed>  $update_Callback  Run record transformation.
     */
    public static function update_Run(string $target_Name, callable $update_Callback): void
    {
        $status_Data = self::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

        if (!is_array($run_Record)) {
            throw new RuntimeException("Sync run changed before update");
        }

        $status_Data[$target_Name] = $update_Callback($run_Record);
        self::write_Status_Data($status_Data);
    }

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

    public static function read_Log_Chunk(int $cursor = 0): array
    {
        $log_Path = self::get_Log_File_Path();

        if (!file_exists($log_Path)) {
            return ['content' => '', 'cursor' => 0];
        }

        $file_Handle = fopen($log_Path, 'r');
        if (!$file_Handle) {
            return ['content' => '', 'cursor' => $cursor];
        }

        fseek($file_Handle, $cursor);
        $content = stream_get_contents($file_Handle);
        $new_Cursor = ftell($file_Handle);
        fclose($file_Handle);

        return [
            'content' => $content,
            'cursor' => $new_Cursor,
        ];
    }


    /**
     * Truncate the shared run log when a failed status is reset or a new run starts.
     */
    public static function reset_Log(): void
    {
        $target_Name = TargetManager::get_activeTarget();
        $status_Data = self::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

        // ตรวจสอบภายในตัว: หากยังมีสคริปต์ไหนไม่คอมพลีท ห้ามเคลียร์เด็ดขาด
        if (!empty($run_Record) && isset($run_Record['script_statuses'])) {
            foreach ($run_Record['script_statuses'] as $status) {
                if ($status !== 'completed') {
                    return; // ยังไม่จบกระบวนการ ออกจากฟังก์ชันทันทีโดยไม่ทำลาย Log เก่า
                }
            }
        }

        $log_Path = base_path(self::LOG_FILE);
        \Illuminate\Support\Facades\Log::info("Resetting Sync log for target {$target_Name} at path: {$log_Path}");
        if (file_put_contents($log_Path, '', LOCK_EX) === false) {
            throw new RuntimeException('Could not reset the Sync log file');
        } else {
            \Illuminate\Support\Facades\Log::info("[ ✅ SUCCESS ] : Resetting Sync log for target {$target_Name} at path: {$log_Path}");
        }
    }

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
        self::ensureDir(base_path(self::STATUS_FILE));
        file_put_contents(
            base_path(self::STATUS_FILE),
            json_encode($status_Data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public static function write_Log(string $content, bool $append = true): void
    {
        $valid_content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

        $log_Path = self::get_Log_File_Path();
        $dir = dirname($log_Path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $flags = $append ? FILE_APPEND : 0;
        $result = file_put_contents($log_Path, $valid_content, $flags);

        if ($result === false) {
            \Illuminate\Support\Facades\Log::info('[ 🚫 ERROR ] Status_Log - write_Log: content = '.$valid_content);
        } else {
            \Illuminate\Support\Facades\Log::info('[ ✅ SUCCESS ] Status_Log - write_Log: content = '.$valid_content);
        }
    }

    /**
     * Create private storage directories used for status and per-run logs.
     */
    public static function ensureDir(string $filePath): void
    {
        $status_Dir = dirname($filePath);
        if (!is_dir($status_Dir)) {
            mkdir($status_Dir, 0775, true);
        }
    }

    /**
     * Return the absolute path to one run's private output log.
     * @return string Absolute run-log path.
     */
    public static function get_Log_File_Path(): string
    {
        $log_Path = storage_path('app/tmp/sf_proc_00.out');
        $dir = dirname($log_Path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (!file_exists($log_Path)) {
            file_put_contents($log_Path, '');
        }
        return $log_Path;
    }
}
