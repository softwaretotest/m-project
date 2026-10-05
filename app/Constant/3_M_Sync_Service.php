<?php

namespace App\Constant;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

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

        $target_Name = TargetManager::get_activeTarget();
        if ($target_Name === '') {
            return [
                'success' => false,
                'message' => 'Active target not found.',
            ];
        }

        $allowed_Scripts = [
            'json_to_php',
            'php_to_json',
            'migration',
            'generators',
        ];

        $validated_Scripts = [];

        // \Illuminate\Support\Facades\Log::info(print_r($selected_Scripts));

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

        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        $status_Data[$target_Name] = [
            'target' => $target_Name,
            'status' => 'running',
            'selected_scripts' => $validated_Scripts,
            'script_statuses' => $script_Statuses,
            'message' => null,
        ];

        M_Sync_Service_Status_Log::write_Status_Data($status_Data);

        M_Sync_Service_Status_Log::write_Log("---------- START SYNCHRONIZATION RUN ----------" . PHP_EOL);

        $this->launch_Worker_Process(self::PHASE_INITIAL);

        return [
            'success' => true,
            'target' => $target_Name,
            'status' => 'running',
        ];
    }

    private function launch_Worker_Process(string $phase): ?int
    {
        // \Illuminate\Support\Facades\Log::info('LAUNCH WORKER START: phase = ' . $phase);

        $artisan_Path = base_path('artisan');

        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'start "" /B '
                . escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($artisan_Path) . ' sync:run '
                . escapeshellarg($phase)
                . ' >NUL 2>&1';

            $launcher = Process::fromShellCommandline($command, base_path());
            $launcher->setTimeout(15);

            // \Illuminate\Support\Facades\Log::info('WINDOWS COMMAND: ' . $command);

            $launcher->mustRun();

            // \Illuminate\Support\Facades\Log::info('WINDOWS COMMAND FINISHED');

            return null;
        } else {
            $command = 'nohup '
                . escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($artisan_Path) . ' sync:run '
                . escapeshellarg($phase)
                . ' > /dev/null 2>&1 & echo $!';

            $launcher = Process::fromShellCommandline($command, base_path());
            $launcher->setTimeout(15);
            $launcher->mustRun();
            $process_ID = (int) trim($launcher->getOutput());
        }

        if ($process_ID <= 0) {
            // throw new RuntimeException('Could not start the Sync worker process.');
            \Illuminate\Support\Facades\Log::info('[🚫] Could not start the Sync worker process');
        }

        return $process_ID;
    }

    /**
     * Continue a run after its selected sync scripts finish and the user reviews Entities.json.
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    public function continueRun(): array
    {
        $target_Name = TargetManager::get_activeTarget();
        $run_Record = $this->reserve_Continuation($target_Name);

        if (!$run_Record['accepted']) {
            return $run_Record;
        }

        return $this->launch_Reserved_Run($target_Name, self::PHASE_CONTINUE);
    }

    /**
     * Clear the target's failed run record and its run log.
     * @return array{reset: bool, run: array<string, mixed>|null}
     */
    public function resetFailedRun(): array
    {
        $target_Name = TargetManager::get_activeTarget();

        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

        if (!is_array($run_Record)) {
            return ['reset' => false, 'run' => null];
        }

        // $run_Record = $this->reconcile_Dead_Process($run_Record);
        if ($this->is_Run_Active($run_Record) || $run_Record['status'] !== self::STATUS_FAILED) {
            $status_Data[$target_Name] = $run_Record;
            M_Sync_Service_Status_Log::write_Status_Data($status_Data);

            return ['reset' => false, 'run' => $run_Record];
        }

        M_Sync_Service_Status_Log::write_Status_Data($status_Data);

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
        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

        if (!is_array($run_Record)) {
            $run_Record = null;
        }

        $status_Data[$target_Name] = $run_Record;
        M_Sync_Service_Status_Log::write_Status_Data($status_Data);

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
        $target_Name = TargetManager::get_activeTarget();
        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

        if (!is_array($run_Record)) {
            return;
        }

        $run_Record['status'] = 'failed';
        $run_Record['message'] = $message;
        $status_Data[$target_Name] = $run_Record;

        M_Sync_Service_Status_Log::write_Status_Data($status_Data);

        \Illuminate\Support\Facades\Log::error("[🚫] SYNC WORKER FAILED: " . $message);
    }

    /**
     * Reserve the generation phase only when the run is waiting for user review.
     *
     * @param string $target_Name Configured active target name.
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    private function reserve_Continuation(string $target_Name): array
    {
        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

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
        $status_Data[$target_Name] = $run_Record;
        M_Sync_Service_Status_Log::write_Status_Data($status_Data);

        return ['accepted' => true, 'run' => $run_Record];
    }

    /**
     * Launch a reserved run phase
     *
     * @param string $target_Name Configured active target name.
     * @param string $phase Worker phase to launch.
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    private function launch_Reserved_Run(string $target_Name, string $phase): array
    {
        try {
            $process_ID = $this->launch_Worker_Process($phase);
        } catch (Throwable $exception) {
            M_Sync_Service_Status_Log::fail_Run(
                $target_Name,
                $exception->getMessage()
            );

            return [
                'accepted' => false,
                'run' => $this->get_Target_Run($target_Name),
            ];
        }

        M_Sync_Service_Status_Log::update_Run($target_Name, function (array $current_Run) use ($process_ID): array {
            if (
                $process_ID !== null
                && in_array($current_Run['status'], [self::STATUS_STARTING, self::STATUS_RUNNING], true)
            ) {
                $current_Run['pid'] = $process_ID;
            }
            $current_Run['updated_at'] = date(DATE_ATOM);

            return $current_Run;
        });

        return [
            'accepted' => true,
            'run' => $this->get_Target_Run($target_Name),
        ];
    }

    /**
     * Read one target's current run record from persisted status.
     *
     * @param string $target_Name Configured target name.
     * @return array<string, mixed> Current run record, or an empty array when none exists.
     */
    private function get_Target_Run(string $target_Name): array
    {
        $run_Record = M_Sync_Service_Status_Log::read_Status_Data()[$target_Name] ?? [];
        return is_array($run_Record) ? $run_Record : [];
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
