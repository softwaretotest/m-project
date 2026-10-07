<?php

namespace App\Constant;

use Symfony\Component\Process\Process;

class M_Sync_Service
{
    public const STATUS_STARTING = 'starting';
    public const STATUS_RUNNING = 'running';
    public const STATUS_AWAITING_REVIEW = 'awaiting_review';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_WARNING = 'warning';
    public const PHASE_INITIAL = 'initial';
    public const PHASE_CONTINUE = 'continue';
    public const SCRIPT_JSON_TO_PHP = 'json_to_php';
    public const SCRIPT_PHP_TO_JSON = 'php_to_json';
    public const SCRIPT_MIGRATION = 'migration';
    public const SCRIPT_GENERATORS = 'generators';
    public const MISSING_ENTITIES_JSON_WARNING = 'JSON file not found: Entities.json';

    /**
     * * Continue a run after its selected sync scripts finish and the user reviews Entities.json.
     * * simple call startRun to rerun all scripts to test if no any problems
     * @return array{accepted: bool, run: array<string, mixed>} $run_Record
     */
    public function continueRun(array $selected_Scripts): array
    {
        return self::startRun($selected_Scripts);
    }

    /**
     * 1. validate scripts
     * 2. order $validated_Scripts like $allowed_Scripts
     * @param array $selected_Scripts e.g.
     * * [
     * *     'migration',
     * *     'generators',
     * *     'json_to_php',
     * *     'php_to_json',
     * * ]
     * @return array ordered scripts e.g.
     * * [
     * *     'json_to_php',
     * *     'php_to_json',
     * *     'migration',
     * *     'generators',
     * * ]
     */
    public static function get_init_run_Record(array $selected_Scripts): array
    {
        $allowed_Scripts = [
            'json_to_php',
            'php_to_json',
            'migration',
            'generators',
        ];

        // 1. validate scripts
        $selected_Lookup = [];
        foreach ($selected_Scripts as $script_ID) {
            $normalized_ID = strtolower(trim($script_ID));
            if (!in_array($normalized_ID, $allowed_Scripts, true)) {
                return [
                    'success' => false,
                    'message' => 'The selected script list contains an unsupported script.',
                ];
            }
            $selected_Lookup[$normalized_ID] = true;
        }

        // 2. order $validated_Scripts like $allowed_Scripts
        $validated_Scripts = [];
        foreach ($allowed_Scripts as $script_ID) {
            if (isset($selected_Lookup[$script_ID])) {
                $validated_Scripts[] = $script_ID;
            }
        }

        $script_Statuses = [];
        foreach ($allowed_Scripts as $script_ID) {
            $script_Statuses[$script_ID] = isset($selected_Lookup[$script_ID]) ? 'pending' : 'skipped';
        }

        $run_Record = [
            'target' => TargetManager::get_activeTarget(),
            'status' => 'running',
            'selected_scripts' => $validated_Scripts,
            'script_statuses' => $script_Statuses,
            'message' => null,
        ];

        return $run_Record;
    }

    public function startRun(array $selected_Scripts): array
    {
        M_Sync_Service_Status_Log::reset_Log();

        $run_Record = self::get_init_run_Record($selected_Scripts);

        M_Sync_Service_Status_Log::write_M_Sync_Status_json($run_Record);

        M_Sync_Service_Status_Log::write_Log("---------- START SYNCHRONIZATION RUN ----------" . PHP_EOL);

        $this->launch_Worker_Process(self::PHASE_INITIAL);

        M_Sync_Service_Status_Log::write_Log(Logger::$collected_message);

        return [
            'success' => true,
            'target' => TargetManager::get_activeTarget(),
            'status' => 'running',
        ];
    }

    /**
     * * Execute worker process in the background.
     * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * * for Windows : call start "" /B ,
     * *        to run background without cmd windows
     * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * * for Linux   : nohup ... & echo $!
     * *        to continue process , although HTTP Request ended
     * * LINUX need processs_ID to
     * *    1. sync current_Run
     * *    2. check background PID = Ref. for still running process
     * *    3. Kill Process
     * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     * @param string $phase
     *   Worker phase to launch (e.g. initial, continue)
     * @return int|null
     *   Process ID (PID) on Linux, or null on Windows
     */
    private function launch_Worker_Process(string $phase): ?int
    {
        $artisan_Path = base_path('artisan');

        $core_Command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($artisan_Path) . ' sync:run '
            . escapeshellarg($phase);

        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'start "" /B ' . $core_Command . ' >NUL 2>&1';
        } else {
            $command = 'nohup ' . $core_Command . ' > /dev/null 2>&1 & echo $!';
        }

        $launcher = Process::fromShellCommandline($command, base_path());
        $launcher->setTimeout(15);

        /**
         * * tell M_Sync_Artisan to run $core_Command
         * * call Artisan CLI
         */
        $launcher->mustRun();

        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        // CASE : OS = LINUX
        $process_ID = (int) trim($launcher->getOutput());

        if ($process_ID <= 0) {
            Logger::collect_error('Could not start the Sync worker process, $process_ID = ' . $process_ID);
        }

        return $process_ID;
    }

    /**
     * Return the current run state and complete log lines after the requested byte cursor.     *
     * @param int $cursor Byte offset already consumed by the UI.
     * @return array<string, mixed> e.g.
     * * [
     * * * "target" => "ecommerce",
     * * * "status" => "running",
     * * * "run" => [
     * * * * "run_id" => "7a3f...",
     * * * * "scripts" =>
     * * * * [
     * * * * * "json_to_php",
     * * * * * "migration"
     * * * * ],
     * * * * "script_statuses" => [
     * * * * * "json_to_php" => "completed",
     * * * * * "migration" => "pending"
     * * * * ]
     * * * ],
     * * * "logs" => "[ 2026-10-01 12:00:00 ] [ SUCCESS ] ...",
     * * * "cursor" => 94
     * * ]
     */
    public function getCurrentStatus(?int $cursor = 0): array
    {
        $cursor = $cursor ?? 0;

        $target_Name = TargetManager::get_activeTarget();
        $run_Record = M_Sync_Service_Status_Log::read_run_Record();
        $run_Record = $run_Record ?? null;

        if (!is_array($run_Record)) {
            $run_Record = null;
        }

        M_Sync_Service_Status_Log::write_M_Sync_Status_json($run_Record);

        if ($run_Record === null) {
            return [
                'target' => $target_Name,
                'status' => 'idle',
                'run' => null,
                'logs' => '',
                'cursor' => 0,
            ];
        }

        $log_Chunk = M_Sync_Service_Status_Log::read_Log_Chunk(
            max(0, $cursor)
        );

        return [
            'target' => $target_Name,
            'status' => $run_Record['status'],
            'run' => $run_Record,
            'logs' => $log_Chunk['content'] ?? '',
            'cursor' => $log_Chunk['cursor'] ?? $cursor,
        ];
    }

    public function reportWorkerFailure(string $message): void
    {
        $run_Record = M_Sync_Service_Status_Log::read_run_Record();
        $run_Record = $run_Record ?? null;

        if (!is_array($run_Record)) {
            return;
        }

        $run_Record['status'] = 'failed';
        $run_Record['message'] = $message;

        M_Sync_Service_Status_Log::write_M_Sync_Status_json($run_Record);

        Logger::collect_error("SYNC WORKER FAILED : " . $message);
    }
}
