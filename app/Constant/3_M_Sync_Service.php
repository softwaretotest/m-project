<?php

namespace App\Constant;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class M_Sync_Service
{
    private const STATUS_STARTING = 'starting';
    private const STATUS_RUNNING = 'running';
    private const STATUS_AWAITING_REVIEW = 'awaiting_review';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';
    private const PHASE_INITIAL = 'initial';
    private const PHASE_CONTINUE = 'continue';
    public const SCRIPT_JSON_TO_PHP = 'json_to_php';
    public const SCRIPT_PHP_TO_JSON = 'php_to_json';
    public const SCRIPT_MIGRATION = 'migration';
    public const SCRIPT_GENERATORS = 'generators';
    private const SCRIPT_STATUS_PENDING = 'pending';
    private const SCRIPT_STATUS_WARNING = 'warning';
    private const MISSING_ENTITIES_JSON_WARNING = 'JSON file not found: Entities.json';

    public const SCRIPT_IDS = [
        self::SCRIPT_JSON_TO_PHP,
        self::SCRIPT_PHP_TO_JSON,
        self::SCRIPT_MIGRATION,
        self::SCRIPT_GENERATORS,
    ];

    private const SCRIPT_ORDER = self::SCRIPT_IDS;
    private const SCRIPT_FILES = [
        self::SCRIPT_JSON_TO_PHP => 'app/Constant/2_M_Sync_JSON.php',
        self::SCRIPT_PHP_TO_JSON => 'app/Constant/1_M_Sync.php',
        self::SCRIPT_MIGRATION => 'app/Constant/0_Runner_run.php',
        self::SCRIPT_GENERATORS => 'app/Constant/3_EntityGenerator.php',
    ];

    /**
     * Reserve a run for the active target and start its detached worker process.
     *
     * @param array<int, string> $selected_Scripts Script identifiers selected in the UI.
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    public function startRun(array $selected_Scripts): array
    {
        $target_Name = TargetManager::get_activeTarget();
        $ordered_Scripts = $this->order_Selected_Scripts($selected_Scripts);
        $run_Record = $this->reserve_Run($target_Name, $ordered_Scripts);

        if (!$run_Record['accepted']) {
            return $run_Record;
        }

        return $this->launch_Reserved_Run($target_Name, $run_Record['run'], self::PHASE_INITIAL);
    }

    /**
     * Continue a run after its selected sync scripts finish and the user reviews Entities.json.
     *
     * @param string $run_ID Identifier of the run awaiting review.
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    public function continueRun(string $run_ID): array
    {
        $target_Name = TargetManager::get_activeTarget();
        $run_Record = $this->reserve_Continuation($target_Name, $run_ID);

        if (!$run_Record['accepted']) {
            return $run_Record;
        }

        return $this->launch_Reserved_Run($target_Name, $run_Record['run'], self::PHASE_CONTINUE);
    }

    /**
     * Clear the target's failed run record and its run log.
     *
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

        $failed_Run_ID = $run_Record['run_id'] ?? null;
        if (is_string($failed_Run_ID) && preg_match('/^[0-9a-f]{36}$/i', $failed_Run_ID)) {
            M_Sync_Service_Status_Log::delete_Run_Log($failed_Run_ID);
        }

        unset($status_Data[$target_Name]);
        M_Sync_Service_Status_Log::write_Status_Data($status_Data);

        return ['reset' => true, 'run' => null];
    }

    /**
     * Return the current run state and complete log lines after the requested byte cursor.
     *
     * @param string|null $run_ID Optional run identifier to verify against the active run.
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
    public function getCurrentStatus(?string $run_ID = null, int $cursor = 0): array
    {
        $target_Name = TargetManager::get_activeTarget();
        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

        if (!is_array($run_Record)) {
            $run_Record = null;
        }

        // $run_Record = $this->reconcile_Dead_Process($run_Record);
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

        if ($run_ID !== null && $run_ID !== $run_Record['run_id']) {
            return [
                'target' => $target_Name,
                'status' => 'not_found',
                'run' => null,
                'logs' => '',
                'cursor' => 0,
            ];
        }

        $log_Chunk = M_Sync_Service_Status_Log::read_Log_Chunk(
            $run_Record['run_id'],
            max(0, $cursor),
            in_array($run_Record['status'], [self::STATUS_COMPLETED, self::STATUS_FAILED], true)
        );

        return [
            'target' => $target_Name,
            'status' => $run_Record['status'],
            'run' => $run_Record,
            'logs' => $log_Chunk['logs'],
            'cursor' => $log_Chunk['cursor'],
        ];
    }

    /**
     * Persist an unexpected worker-level failure in the run status and its UI log.
     *
     * @param string $run_ID Identifier of the failed run.
     * @param string $message Worker exception details.
     * @return void
     */
    public function reportWorkerFailure(string $run_ID, string $message): void
    {
        $run_Record = $this->find_Run($run_ID);
        if ($run_Record === null) {
            return;
        }

        $failure_Message = "Sync worker failed: {$message}" . PHP_EOL;
        M_Sync_Service_Status_Log::append_Run_Output($run_ID, $failure_Message);
        M_Sync_Service_Status_Log::fail_Run($run_Record['target'], $run_ID, $message);
    }

    /**
     * Execute the run phase requested by the detached Artisan worker.
     *
     * @param string $run_ID Identifier of the persisted run.
     * @param string $phase Worker phase: initial or continue.
     * @return int Zero on success, non-zero when the run fails.
     */
    public function executeWorker(string $run_ID, string $phase): int
    {
        $worker = new M_Sync_Service_EXE_Worker(
            fn (string $requested_Run_ID): ?array => $this->find_Run($requested_Run_ID),
            function (string $target_Name, string $requested_Run_ID, callable $update_Callback): void {
                M_Sync_Service_Status_Log::update_Run($target_Name, $requested_Run_ID, $update_Callback);
            },
            function (
                string $target_Name,
                string $requested_Run_ID,
                string $script_ID,
                string $script_Status
            ): void {
                M_Sync_Service_Status_Log::update_Script_Status(
                    $target_Name,
                    $requested_Run_ID,
                    $script_ID,
                    $script_Status
                );
            },
            function (string $requested_Run_ID, string $output): void {
                M_Sync_Service_Status_Log::append_Run_Output($requested_Run_ID, $output);
            },
            fn (array $run_Record, string $script_ID): array => $this->execute_Script($run_Record, $script_ID),
            function (string $target_Name, string $requested_Run_ID, string $message): void {
                M_Sync_Service_Status_Log::fail_Run($target_Name, $requested_Run_ID, $message);
            },
            [
                'phase_initial' => self::PHASE_INITIAL,
                'phase_continue' => self::PHASE_CONTINUE,
                'status_running' => self::STATUS_RUNNING,
                'status_awaiting_review' => self::STATUS_AWAITING_REVIEW,
                'status_completed' => self::STATUS_COMPLETED,
                'status_failed' => self::STATUS_FAILED,
                'script_json_to_php' => self::SCRIPT_JSON_TO_PHP,
                'script_status_warning' => self::SCRIPT_STATUS_WARNING,
            ]
        );

        return $worker->execute_Worker($run_ID, $phase);
    }

    /**
     * Reserve a new run while ensuring one active run per target.
     *
     * @param string $target_Name Configured active target name.
     * @param array<int, string> $ordered_Scripts Validated scripts in execution order.
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    private function reserve_Run(string $target_Name, array $ordered_Scripts): array
    {
        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        $existing_Run = $status_Data[$target_Name] ?? null;

        if (is_array($existing_Run)) {
            $status_Data[$target_Name] = $existing_Run;

            if ($this->is_Run_Active($existing_Run)) {
                M_Sync_Service_Status_Log::write_Status_Data($status_Data);

                return ['accepted' => false, 'run' => $existing_Run];
            }
        }

        $run_ID = (string) Str::uuid();
        $sync_Scripts = [];
        $generation_Scripts = [];
        $script_Statuses = [];

        foreach ($ordered_Scripts as $script_ID) {
            $script_Statuses[$script_ID] = self::SCRIPT_STATUS_PENDING;
            if (in_array($script_ID, [self::SCRIPT_JSON_TO_PHP, self::SCRIPT_PHP_TO_JSON], true)) {
                $sync_Scripts[] = $script_ID;
            } else {
                $generation_Scripts[] = $script_ID;
            }
        }

        $requires_Review = $sync_Scripts !== [] && $generation_Scripts !== [];
        $initial_Scripts = $requires_Review ? $sync_Scripts : $ordered_Scripts;
        $final_Scripts = $requires_Review ? $generation_Scripts : [];
        $run_Record = [
            'run_id' => $run_ID,
            'target' => $target_Name,
            'status' => self::STATUS_STARTING,
            'pid' => null,
            'child_pid' => null,
            'scripts' => $ordered_Scripts,
            'initial_scripts' => $initial_Scripts,
            'final_scripts' => $final_Scripts,
            'script_statuses' => $script_Statuses,
            'requires_review' => $requires_Review,
            'started_at' => date(DATE_ATOM),
            'finished_at' => null,
            'updated_at' => date(DATE_ATOM),
            'message' => null,
        ];

        M_Sync_Service_Status_Log::ensureDir();
        M_Sync_Service_Status_Log::delete_Unused_Run_Logs(
            $status_Data,
            fn (array $run_Record): bool => $this->is_Run_Active($run_Record)
        );
        $log_Initialized = file_put_contents(
            M_Sync_Service_Status_Log::get_Log_File_Path($run_ID),
            '',
            LOCK_EX
        );
        if ($log_Initialized === false) {
            throw new RuntimeException("Could not initialize Sync log for run {$run_ID}");
        }

        $status_Data[$target_Name] = $run_Record;
        M_Sync_Service_Status_Log::write_Status_Data($status_Data);

        return ['accepted' => true, 'run' => $run_Record];
    }

    /**
     * Reserve the generation phase only when the run is waiting for user review.
     *
     * @param string $target_Name Configured active target name.
     * @param string $run_ID Identifier of the run to continue.
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    private function reserve_Continuation(string $target_Name, string $run_ID): array
    {
        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        $run_Record = $status_Data[$target_Name] ?? null;

        if (!is_array($run_Record) || $run_Record['run_id'] !== $run_ID) {
            return [
                'accepted' => false,
                'run' => ['status' => 'not_found', 'run_id' => $run_ID],
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
     * Launch a reserved run phase and persist the worker process identifier.
     *
     * @param string $target_Name Configured active target name.
     * @param array<string, mixed> $run_Record Persisted run record.
     * @param string $phase Worker phase to launch.
     * @return array{accepted: bool, run: array<string, mixed>}
     */
    private function launch_Reserved_Run(string $target_Name, array $run_Record, string $phase): array
    {
        try {
            $process_ID = $this->launch_Worker_Process($run_Record['run_id'], $phase);
        } catch (Throwable $exception) {
            M_Sync_Service_Status_Log::append_Run_Output(
                $run_Record['run_id'],
                "Worker launch failed: {$exception->getMessage()}" . PHP_EOL
            );
            M_Sync_Service_Status_Log::fail_Run(
                $target_Name,
                $run_Record['run_id'],
                $exception->getMessage()
            );

            return [
                'accepted' => false,
                'run' => $this->get_Target_Run($target_Name),
            ];
        }

        M_Sync_Service_Status_Log::update_Run($target_Name, $run_Record['run_id'], function (array $current_Run) use ($process_ID): array {
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
     * Start the Artisan worker detached from the current HTTP request.
     *
     * @param string $run_ID Identifier of the persisted run.
     * @param string $phase Worker phase to execute.
     * @return int Operating-system process identifier.
     */
    private function launch_Worker_Process(string $run_ID, string $phase): ?int
    {
        $artisan_Path = base_path('artisan');

        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'start "" /B '
                . escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($artisan_Path) . ' sync:run '
                . escapeshellarg($run_ID) . ' '
                . escapeshellarg($phase)
                . ' >NUL 2>&1';

            $launcher = Process::fromShellCommandline($command, base_path());
            $launcher->setTimeout(15);
            $launcher->mustRun();

            return null;
        } else {
            $command = 'nohup '
                . escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($artisan_Path) . ' sync:run '
                . escapeshellarg($run_ID) . ' '
                . escapeshellarg($phase)
                . ' > /dev/null 2>&1 & echo $!';

            $launcher = Process::fromShellCommandline($command, base_path());
            $launcher->setTimeout(15);
            $launcher->mustRun();
            $process_ID = (int) trim($launcher->getOutput());
        }

        if ($process_ID <= 0) {
            throw new RuntimeException('Could not start the Sync worker process.');
        }

        return $process_ID;
    }

    /**
     * Execute one allowlisted PHP script and append its output to the run log as it arrives.
     *
     * @param array<string, mixed> $run_Record Persisted run record containing target information.
     * @param string $script_ID Allowlisted script identifier.
     * @return array{exit_code: int, has_missing_entities_json: bool} Child result and missing source warning.
     */
    private function execute_Script(array $run_Record, string $script_ID): array
    {
        $windows_Script = new M_Sync_Service_Windows_Script(
            fn (string $run_ID): string => M_Sync_Service_Status_Log::get_Log_File_Path($run_ID),
            function (string $target_Name, string $run_ID, callable $update_Callback): void {
                M_Sync_Service_Status_Log::update_Run($target_Name, $run_ID, $update_Callback);
            }
        );
        $script_Executor = new M_Sync_Service_EXE_Script(
            fn (string $run_ID): string => M_Sync_Service_Status_Log::get_Log_File_Path($run_ID),
            function (string $target_Name, string $run_ID, callable $update_Callback): void {
                M_Sync_Service_Status_Log::update_Run($target_Name, $run_ID, $update_Callback);
            },
            function (string $run_ID, string $output): void {
                M_Sync_Service_Status_Log::append_Run_Output($run_ID, $output);
            },
            fn (array $record, string $script_Path): int => $windows_Script->execute_Windows_Script(
                $record,
                $script_Path
            ),
            self::SCRIPT_FILES,
            self::MISSING_ENTITIES_JSON_WARNING
        );

        return $script_Executor->execute_EXE_Script($run_Record, $script_ID);
    }

    /**
     * Execute a Windows child script with output redirected to its run log.
     *
     * @param array<string, mixed> $run_Record Persisted run record containing target information.
     * @param string $script_Path Absolute path to the allowlisted PHP script.
     * @return int Child process exit code.
     */
    private function execute_Windows_Script(array $run_Record, string $script_Path): int
    {
        $windows_Script = new M_Sync_Service_Windows_Script(
            fn (string $run_ID): string => M_Sync_Service_Status_Log::get_Log_File_Path($run_ID),
            function (string $target_Name, string $run_ID, callable $update_Callback): void {
                M_Sync_Service_Status_Log::update_Run($target_Name, $run_ID, $update_Callback);
            }
        );

        return $windows_Script->execute_Windows_Script($run_Record, $script_Path);
    }

    /**
     * Order and validate script identifiers against the supported Sync actions.
     *
     * @param array<int, string> $selected_Scripts Script identifiers received from the UI.
     * @return array<int, string> Unique selected scripts in the fixed UI execution order.
     */
    private function order_Selected_Scripts(array $selected_Scripts): array
    {
        foreach ($selected_Scripts as $script_ID) {
            if (!is_string($script_ID) || !in_array($script_ID, self::SCRIPT_ORDER, true)) {
                throw new RuntimeException('The selected script list contains an unsupported script.');
            }
        }

        $ordered_Scripts = [];
        foreach (self::SCRIPT_ORDER as $script_ID) {
            if (in_array($script_ID, $selected_Scripts, true)) {
                $ordered_Scripts[] = $script_ID;
            }
        }

        if ($ordered_Scripts === []) {
            throw new RuntimeException('Select at least one script to start Sync.');
        }

        return $ordered_Scripts;
    }

    /**
     * Find a persisted run across configured targets by run identifier.
     *
     * @param string $run_ID Identifier of the run to find.
     * @return array<string, mixed>|null Persisted run record, or null when it no longer exists.
     */
    private function find_Run(string $run_ID): ?array
    {
        foreach (M_Sync_Service_Status_Log::read_Status_Data() as $run_Record) {
            if (is_array($run_Record) && ($run_Record['run_id'] ?? null) === $run_ID) {
                return $run_Record;
            }
        }
        return null;
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
