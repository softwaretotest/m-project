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
    public const PHASE_INITIAL = 'initial';
    public const PHASE_CONTINUE = 'continue';
    public const SCRIPT_JSON_TO_PHP = 'json_to_php';
    public const SCRIPT_PHP_TO_JSON = 'php_to_json';
    public const SCRIPT_MIGRATION = 'migration';
    public const SCRIPT_GENERATORS = 'generators';
    public const SCRIPT_STATUS_PENDING = 'pending';
    public const SCRIPT_STATUS_WARNING = 'warning';
    public const MISSING_ENTITIES_JSON_WARNING = 'JSON file not found: Entities.json';

    public const SCRIPT_IDS = [
        self::SCRIPT_JSON_TO_PHP,
        self::SCRIPT_PHP_TO_JSON,
        self::SCRIPT_MIGRATION,
        self::SCRIPT_GENERATORS,
    ];

    public function startRun(array $selected_Scripts): array
    {
        M_Sync_Service_Status_Log::reset_Log();

        $allowed_Scripts = [
            'json_to_php',
            'php_to_json',
            'migration',
            'generators',
        ];

        $validated_Scripts = [];

        foreach ($selected_Scripts as $script_ID) {
            $normalized_ID = strtolower(trim($script_ID));
            if (!in_array($normalized_ID, $allowed_Scripts, true)) {
                return [
                    'success' => false,
                    'message' => 'The selected script list contains an unsupported script.',
                ];
            }
            $validated_Scripts[] = $normalized_ID;
        }

        $script_Statuses = [];
        foreach ($allowed_Scripts as $script_ID) {
            $script_Statuses[$script_ID] = in_array($script_ID, $validated_Scripts, true) ? 'pending' : 'skipped';
        }

        $run_Record = M_Sync_Service_Status_Log::read_run_Record();
        $run_Record = [
            'target' => TargetManager::get_activeTarget(),
            'status' => 'running',
            'selected_scripts' => $validated_Scripts,
            'script_statuses' => $script_Statuses,
            'message' => null,
        ];

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
     * Continue a run after its selected sync scripts finish and the user reviews Entities.json.
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    public function continueRun(): array
    {
        $run_Record = $this->reserve_Continuation();

        if (!$run_Record['accepted']) {
            return $run_Record;
        }

        $process_ID = $this->launch_Worker_Process(self::PHASE_CONTINUE);

        M_Sync_Service_Status_Log::update_Run(function (array $run_Record) use ($process_ID): array {
            // $process_ID is only for Linux to sync current_Run
            if (
                $process_ID !== null
                && in_array($run_Record['status'], [self::STATUS_STARTING, self::STATUS_RUNNING], true)
            ) {
                $run_Record['pid'] = $process_ID;
            }

            $run_Record['updated_at'] = date(DATE_ATOM);

            return $run_Record;
        });

        return [
            'accepted' => true,
            'run' => M_Sync_Service_Status_Log::read_run_Record()
        ];
    }

    /**
     * Clear the target's failed run record and its run log.
     * @return array{reset: bool, run: array<string, mixed>|null}
     */
    public function resetFailedRun(): array
    {
        $run_Record = M_Sync_Service_Status_Log::read_run_Record();
        $run_Record = $run_Record ?? null;

        if (!is_array($run_Record)) {
            return ['reset' => false, 'run' => null];
        }

        // $run_Record = $this->reconcile_Dead_Process($run_Record);
        if ($this->is_Run_Active($run_Record) || $run_Record['status'] !== self::STATUS_FAILED) {
            M_Sync_Service_Status_Log::write_M_Sync_Status_json($run_Record);

            return ['reset' => false, 'run' => $run_Record];
        }

        M_Sync_Service_Status_Log::write_M_Sync_Status_json($run_Record);

        return ['reset' => true, 'run' => null];
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

    /**
     * Reserve the generation phase only when the run is waiting for user review.
     *
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    private function reserve_Continuation(): array
    {
        $run_Record = M_Sync_Service_Status_Log::read_run_Record();

        if (!is_array($run_Record)) {
            return [
                'accepted' => false,
                'run' => ['status' => 'not_found'],
            ];
        }

        if ($run_Record['status'] !== self::STATUS_AWAITING_REVIEW) {
            return ['accepted' => false, 'run' => $run_Record];
        }

        $run_Record['status'] = self::STATUS_STARTING;
        $run_Record['pid'] = null;
        $run_Record['message'] = null;
        $run_Record['updated_at'] = date(DATE_ATOM);
        M_Sync_Service_Status_Log::write_M_Sync_Status_json($run_Record);

        return ['accepted' => true, 'run' => $run_Record];
    }

    /**
     * Determine whether a persisted run blocks a new run for the same target.
     *
     * @param array<string, mixed> $run_Record Persisted run record.
     * @return bool True when the run is active or waiting for user review.
     */
    private function is_Run_Active(array $run_Record): bool
    {
        return in_array($run_Record['status'], [
            self::STATUS_STARTING,
            self::STATUS_RUNNING,
            self::STATUS_AWAITING_REVIEW,
        ], true);
    }
}
