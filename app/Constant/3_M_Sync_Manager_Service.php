<?php

namespace App\Constant;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class Sync_Manager_Service
{
    private const STATUS_FILE = 'app/m-sync-manager/sync_status.json';
    private const LEGACY_STATUS_FILE = 'app/m-sync-manager/status.json';
    private const LOCK_FILE = 'app/m-sync-manager/sync_status.lock';
    private const LOG_DIRECTORY = 'app/m-sync-manager/logs';
    private const STATUS_STARTING = 'starting';
    private const STATUS_RUNNING = 'running';
    private const STATUS_AWAITING_REVIEW = 'awaiting_review';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';
    private const STARTING_STALE_AFTER_SECONDS = 30;
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
        $target_Name = $this->get_Active_Target_Name();
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
        $target_Name = $this->get_Active_Target_Name();
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
        $target_Name = $this->get_Active_Target_Name();

        return $this->with_Status_Lock(function () use ($target_Name): array {
            $status_Data = $this->read_Status_Data();
            $run_Record = $status_Data[$target_Name] ?? null;

            if (!is_array($run_Record)) {
                return ['reset' => false, 'run' => null];
            }

            $run_Record = $this->reconcile_Dead_Process($run_Record);
            if ($this->is_Run_Active($run_Record) || $run_Record['status'] !== self::STATUS_FAILED) {
                $status_Data[$target_Name] = $run_Record;
                $this->write_Status_Data($status_Data);

                return ['reset' => false, 'run' => $run_Record];
            }

            $failed_Run_ID = $run_Record['run_id'] ?? null;
            if (is_string($failed_Run_ID) && preg_match('/^[0-9a-f-]{36}$/i', $failed_Run_ID)) {
                $this->delete_Run_Log($failed_Run_ID);
            }

            foreach ([$this->get_Status_File_Path(), storage_path(self::LEGACY_STATUS_FILE)] as $status_Path) {
                if (!file_exists($status_Path)) {
                    continue;
                }

                $stored_Status_Data = $this->read_Status_Data_From_Path($status_Path);
                unset($stored_Status_Data[$target_Name]);
                $this->write_Status_Data_To_Path($status_Path, $stored_Status_Data);
            }

            return ['reset' => true, 'run' => null];
        });
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
        $target_Name = $this->get_Active_Target_Name();
        $run_Record = $this->with_Status_Lock(function () use ($target_Name): ?array {
            $status_Data = $this->read_Status_Data();
            $run_Record = $status_Data[$target_Name] ?? null;

            if (!is_array($run_Record)) {
                return null;
            }

            $run_Record = $this->reconcile_Dead_Process($run_Record);
            $status_Data[$target_Name] = $run_Record;
            $this->write_Status_Data($status_Data);

            return $run_Record;
        });

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

        $log_Chunk = $this->read_Log_Chunk(
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

        $failure_Message = "Sync Manager worker failed: {$message}" . PHP_EOL;
        $this->append_Run_Output($run_ID, $failure_Message);
        $this->fail_Run($run_Record['target'], $run_ID, $message);
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
        $worker = new M_Sync_Manager_Service_EXE_Worker(
            fn (string $requested_Run_ID): ?array => $this->find_Run($requested_Run_ID),
            function (string $target_Name, string $requested_Run_ID, callable $update_Callback): void {
                $this->update_Run($target_Name, $requested_Run_ID, $update_Callback);
            },
            function (
                string $target_Name,
                string $requested_Run_ID,
                string $script_ID,
                string $script_Status
            ): void {
                $this->update_Script_Status($target_Name, $requested_Run_ID, $script_ID, $script_Status);
            },
            function (string $requested_Run_ID, string $output): void {
                $this->append_Run_Output($requested_Run_ID, $output);
            },
            fn (array $run_Record, string $script_ID): array => $this->execute_Script($run_Record, $script_ID),
            function (string $target_Name, string $requested_Run_ID, string $message): void {
                $this->fail_Run($target_Name, $requested_Run_ID, $message);
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
        return $this->with_Status_Lock(function () use ($target_Name, $ordered_Scripts): array {
            $status_Data = $this->read_Status_Data();
            $existing_Run = $status_Data[$target_Name] ?? null;

            if (is_array($existing_Run)) {
                $existing_Run = $this->reconcile_Dead_Process($existing_Run);
                $status_Data[$target_Name] = $existing_Run;

                if ($this->is_Run_Active($existing_Run)) {
                    $this->write_Status_Data($status_Data);

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

            $this->ensure_Storage_Directories();
            $this->delete_Unused_Run_Logs($status_Data);
            $log_Initialized = file_put_contents($this->get_Log_File_Path($run_ID), '', LOCK_EX);
            if ($log_Initialized === false) {
                throw new RuntimeException("Could not initialize Sync Manager log for run {$run_ID}");
            }

            $status_Data[$target_Name] = $run_Record;
            $this->write_Status_Data($status_Data);

            return ['accepted' => true, 'run' => $run_Record];
        });
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
        return $this->with_Status_Lock(function () use ($target_Name, $run_ID): array {
            $status_Data = $this->read_Status_Data();
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
            $this->write_Status_Data($status_Data);

            return ['accepted' => true, 'run' => $run_Record];
        });
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
            $this->append_Run_Output(
                $run_Record['run_id'],
                "Worker launch failed: {$exception->getMessage()}" . PHP_EOL
            );
            $this->fail_Run($target_Name, $run_Record['run_id'], $exception->getMessage());

            return [
                'accepted' => false,
                'run' => $this->get_Target_Run($target_Name),
            ];
        }

        $this->update_Run($target_Name, $run_Record['run_id'], function (array $current_Run) use ($process_ID): array {
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
                . escapeshellarg($artisan_Path) . ' sync-manager:run '
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
                . escapeshellarg($artisan_Path) . ' sync-manager:run '
                . escapeshellarg($run_ID) . ' '
                . escapeshellarg($phase)
                . ' > /dev/null 2>&1 & echo $!';

            $launcher = Process::fromShellCommandline($command, base_path());
            $launcher->setTimeout(15);
            $launcher->mustRun();
            $process_ID = (int) trim($launcher->getOutput());
        }

        if ($process_ID <= 0) {
            throw new RuntimeException('Could not start the Sync Manager worker process.');
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
        $windows_Script = new M_Sync_Manager_Service_Windows_Script(
            fn (string $run_ID): string => $this->get_Log_File_Path($run_ID),
            function (string $target_Name, string $run_ID, callable $update_Callback): void {
                $this->update_Run($target_Name, $run_ID, $update_Callback);
            }
        );
        $script_Executor = new M_Sync_Manager_Service_EXE_Script(
            fn (string $run_ID): string => $this->get_Log_File_Path($run_ID),
            function (string $target_Name, string $run_ID, callable $update_Callback): void {
                $this->update_Run($target_Name, $run_ID, $update_Callback);
            },
            function (string $run_ID, string $output): void {
                $this->append_Run_Output($run_ID, $output);
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
        $windows_Script = new M_Sync_Manager_Service_Windows_Script(
            fn (string $run_ID): string => $this->get_Log_File_Path($run_ID),
            function (string $target_Name, string $run_ID, callable $update_Callback): void {
                $this->update_Run($target_Name, $run_ID, $update_Callback);
            }
        );

        return $windows_Script->execute_Windows_Script($run_Record, $script_Path);
    }

    /**
     * Order and validate script identifiers against the supported Sync Manager actions.
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
            throw new RuntimeException('Select at least one script to start Sync Manager.');
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
        return $this->with_Status_Lock(function () use ($run_ID): ?array {
            foreach ($this->read_Status_Data() as $run_Record) {
                if (is_array($run_Record) && ($run_Record['run_id'] ?? null) === $run_ID) {
                    return $run_Record;
                }
            }

            return null;
        });
    }

    /**
     * Read one target's current run record from persisted status.
     *
     * @param string $target_Name Configured target name.
     * @return array<string, mixed> Current run record, or an empty array when none exists.
     */
    private function get_Target_Run(string $target_Name): array
    {
        return $this->with_Status_Lock(function () use ($target_Name): array {
            $run_Record = $this->read_Status_Data()[$target_Name] ?? [];

            return is_array($run_Record) ? $run_Record : [];
        });
    }

    /**
     * Update a run record while holding the status file lock.
     *
     * @param string $target_Name Configured target name.
     * @param string $run_ID Identifier of the run to update.
     * @param callable(array<string, mixed>): array<string, mixed> $update_Callback Run record transformation.
     * @return void
     */
    private function update_Run(string $target_Name, string $run_ID, callable $update_Callback): void
    {
        $this->with_Status_Lock(function () use ($target_Name, $run_ID, $update_Callback): void {
            $status_Data = $this->read_Status_Data();
            $run_Record = $status_Data[$target_Name] ?? null;

            if (!is_array($run_Record) || $run_Record['run_id'] !== $run_ID) {
                throw new RuntimeException("Sync Manager run changed before update: {$run_ID}");
            }

            $status_Data[$target_Name] = $update_Callback($run_Record);
            $this->write_Status_Data($status_Data);
        });
    }

    /**
     * Update one script's status in the persisted run record.
     *
     * @param string $target_Name Configured target name.
     * @param string $run_ID Identifier of the run containing the script.
     * @param string $script_ID Allowlisted script identifier.
     * @param string $script_Status Current script state.
     * @return void
     */
    private function update_Script_Status(
        string $target_Name,
        string $run_ID,
        string $script_ID,
        string $script_Status
    ): void {
        $this->update_Run($target_Name, $run_ID, function (array $run_Record) use ($script_ID, $script_Status): array {
            $run_Record['script_statuses'][$script_ID] = $script_Status;
            $run_Record['updated_at'] = date(DATE_ATOM);

            return $run_Record;
        });
    }

    /**
     * Mark a run failed and persist the failure reason for the UI and developer.
     *
     * @param string $target_Name Configured target name.
     * @param string $run_ID Identifier of the failed run.
     * @param string $message Failure details.
     * @return void
     */
    private function fail_Run(string $target_Name, string $run_ID, string $message): void
    {
        $this->update_Run($target_Name, $run_ID, function (array $run_Record) use ($message): array {
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
     * Change a dead worker's running status to failed so a later run is not blocked forever.
     *
     * @param array<string, mixed> $run_Record Persisted run record.
     * @return array<string, mixed> Updated or unchanged run record.
     */
    private function reconcile_Dead_Process(array $run_Record): array
    {
        if (false === in_array(
            $run_Record['status'],
            [self::STATUS_STARTING, self::STATUS_RUNNING],
            true
        )) {
            return $run_Record;
        }

        $process_IDs = array_filter([
            $run_Record['pid'] ?? null,
            $run_Record['child_pid'] ?? null,
        ], static fn ($process_ID): bool => $process_ID !== null);

        if ($process_IDs !== []) {
            foreach ($process_IDs as $process_ID) {
                if ($this->is_Process_Running((int) $process_ID)) {
                    return $run_Record;
                }
            }
        } elseif (
            $run_Record['status'] === self::STATUS_STARTING
            && time() - (strtotime($run_Record['updated_at']) ?: time()) < self::STARTING_STALE_AFTER_SECONDS
        ) {
            return $run_Record;
        }

        $run_Record['status'] = self::STATUS_FAILED;
        $run_Record['pid'] = null;
        $run_Record['child_pid'] = null;
        $run_Record['message'] = 'Worker process exited before recording a completed status.';
        $run_Record['finished_at'] = date(DATE_ATOM);
        $run_Record['updated_at'] = date(DATE_ATOM);

        return $run_Record;
    }

    /**
     * Check whether an operating-system process identifier is still active.
     *
     * @param int $process_ID Operating-system process identifier.
     * @return bool True when the process exists.
     */
    private function is_Process_Running(int $process_ID): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $process_Check = new Process([
                'powershell.exe',
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                "(Get-Process -Id {$process_ID} -ErrorAction SilentlyContinue) -ne \$null",
            ]);
            $process_Check->setTimeout(10);
            $process_Check->mustRun();

            return strtolower(trim($process_Check->getOutput())) === 'true';
        }

        if (function_exists('posix_kill')) {
            return posix_kill($process_ID, 0);
        }

        return is_dir("/proc/{$process_ID}");
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

    /**
     * Append worker output to the current run's private log file.
     *
     * @param string $run_ID Identifier of the run receiving output.
     * @param string $output Raw output received from the child script.
     * @return void
     */
    private function append_Run_Output(string $run_ID, string $output): void
    {
        if ($output === '') {
            return;
        }

        $valid_Output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
        $written = file_put_contents(
            $this->get_Log_File_Path($run_ID),
            $valid_Output,
            FILE_APPEND | LOCK_EX
        );

        if ($written === false) {
            throw new RuntimeException("Could not append output to Sync Manager log for run {$run_ID}");
        }
    }

    /**
     * Read only complete log lines after the byte cursor, except after the run has ended.
     *
     * @param string $run_ID Identifier of the log to read.
     * @param int $cursor Byte offset already consumed by the client.
     * @param bool $include_Final_Line Include a final line without a newline for completed runs.
     * @return array{logs: string, cursor: int} Appended log text and the next byte cursor.
     */
    private function read_Log_Chunk(string $run_ID, int $cursor, bool $include_Final_Line): array
    {
        $log_Path = $this->get_Log_File_Path($run_ID);
        if (!file_exists($log_Path)) {
            return ['logs' => '', 'cursor' => 0];
        }

        $log_Content = file_get_contents($log_Path);
        if ($log_Content === false) {
            throw new RuntimeException("Could not read Sync Manager log for run {$run_ID}");
        }

        $cursor = min($cursor, strlen($log_Content));
        $new_Content = substr($log_Content, $cursor);
        $last_Newline = strrpos($new_Content, "\n");

        if ($last_Newline === false) {
            if (!$include_Final_Line) {
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
     * Run a callback while holding the cross-request status lock.
     *
     * @param callable(): mixed $callback Operation that reads or writes shared status.
     * @return mixed Callback result.
     */
    private function with_Status_Lock(callable $callback): mixed
    {
        $this->ensure_Storage_Directories();
        $lock_Handle = fopen($this->get_Lock_File_Path(), 'c+');

        if ($lock_Handle === false) {
            throw new RuntimeException('Could not open the Sync Manager status lock file.');
        }

        if (!flock($lock_Handle, LOCK_EX)) {
            fclose($lock_Handle);
            throw new RuntimeException('Could not lock the Sync Manager status file.');
        }

        try {
            return $callback();
        } finally {
            flock($lock_Handle, LOCK_UN);
            fclose($lock_Handle);
        }
    }

    /**
     * Read the status file into a target-name keyed map.
     *
     * @return array<string, array<string, mixed>> Persisted runs keyed by target name.
     */
    private function read_Status_Data(): array
    {
        $status_Path = $this->get_Status_File_Path();
        if (!file_exists($status_Path) && file_exists(storage_path(self::LEGACY_STATUS_FILE))) {
            $status_Path = storage_path(self::LEGACY_STATUS_FILE);
        }

        return $this->read_Status_Data_From_Path($status_Path);
    }

    /**
     * Read one status JSON file into a target-name keyed map.
     *
     * @param string $status_Path Absolute path to a status file.
     * @return array<string, array<string, mixed>> Persisted runs keyed by target name.
     */
    private function read_Status_Data_From_Path(string $status_Path): array
    {
        if (!file_exists($status_Path)) {
            return [];
        }

        $status_Content = file_get_contents($status_Path);
        $status_Data = json_decode((string) $status_Content, true);

        if (!is_array($status_Data)) {
            throw new RuntimeException('Sync Manager status file contains invalid JSON.');
        }

        return $status_Data;
    }

    /**
     * Persist the target-name keyed status map as formatted JSON.
     *
     * @param array<string, array<string, mixed>> $status_Data Persisted runs keyed by target name.
     * @return void
     */
    private function write_Status_Data(array $status_Data): void
    {
        $this->write_Status_Data_To_Path($this->get_Status_File_Path(), $status_Data);
    }

    /**
     * Persist the target-name keyed status map as formatted JSON at the given path.
     *
     * @param string $status_Path Absolute path to a status file.
     * @param array<string, array<string, mixed>> $status_Data Persisted runs keyed by target name.
     * @return void
     */
    private function write_Status_Data_To_Path(string $status_Path, array $status_Data): void
    {
        $status_JSON = json_encode($status_Data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($status_JSON === false) {
            throw new RuntimeException('Could not encode Sync Manager status as JSON.');
        }

        $written = file_put_contents($status_Path, $status_JSON, LOCK_EX);
        if ($written === false) {
            throw new RuntimeException("Could not write the Sync Manager status file: {$status_Path}");
        }
    }

    /**
     * Create private storage directories used for status and per-run logs.
     *
     * @return void
     */
    private function ensure_Storage_Directories(): void
    {
        foreach ([dirname($this->get_Status_File_Path()), storage_path(self::LOG_DIRECTORY)] as $directory_Path) {
            if (!is_dir($directory_Path) && !mkdir($directory_Path, 0775, true) && !is_dir($directory_Path)) {
                throw new RuntimeException("Could not create Sync Manager storage directory: {$directory_Path}");
            }
        }
    }

    /**
     * Return the absolute path to the persisted status file.
     *
     * @return string Absolute status JSON path.
     */
    private function get_Status_File_Path(): string
    {
        return storage_path(self::STATUS_FILE);
    }

    /**
     * Return the absolute path to the status lock file.
     *
     * @return string Absolute lock-file path.
     */
    private function get_Lock_File_Path(): string
    {
        return storage_path(self::LOCK_FILE);
    }

    /**
     * Return the absolute path to one run's private output log.
     *
     * @param string $run_ID Identifier of the run.
     * @return string Absolute run-log path.
     */
    private function get_Log_File_Path(string $run_ID): string
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $run_ID)) {
            throw new RuntimeException('Invalid Sync Manager run identifier.');
        }

        return storage_path(self::LOG_DIRECTORY . "/{$run_ID}.log");
    }

    /**
     * Delete run logs that are no longer needed, preserving logs for active or review-pending runs.
     *
     * @param array<string, array<string, mixed>> $status_Data Persisted runs keyed by target name.
     * @return void
     */
    private function delete_Unused_Run_Logs(array $status_Data): void
    {
        $active_Run_IDs = [];
        foreach ($status_Data as $run_Record) {
            if (!is_array($run_Record)) {
                continue;
            }

            $run_ID = $run_Record['run_id'] ?? null;
            if (
                is_string($run_ID)
                && preg_match('/^[0-9a-f-]{36}$/i', $run_ID)
                && isset($run_Record['status'])
                && $this->is_Run_Active($run_Record)
            ) {
                $active_Run_IDs[] = strtolower($run_ID);
            }
        }

        $log_Paths = glob(storage_path(self::LOG_DIRECTORY . '/*.log'));
        if ($log_Paths === false) {
            throw new RuntimeException('Could not list Sync Manager run logs for cleanup.');
        }

        foreach ($log_Paths as $log_Path) {
            $run_ID = basename($log_Path, '.log');
            if (
                !preg_match('/^[0-9a-f-]{36}$/i', $run_ID)
                || in_array(strtolower($run_ID), $active_Run_IDs, true)
            ) {
                continue;
            }

            if (!unlink($log_Path)) {
                throw new RuntimeException("Could not delete unused Sync Manager log: {$log_Path}");
            }
        }
    }

    /**
     * Delete one run's log when its failed status is reset.
     *
     * @param string $run_ID Identifier of the run.
     * @return void
     */
    private function delete_Run_Log(string $run_ID): void
    {
        $log_Path = $this->get_Log_File_Path($run_ID);
        if (file_exists($log_Path) && !unlink($log_Path)) {
            throw new RuntimeException("Could not delete Sync Manager log for run {$run_ID}");
        }
    }

    /**
     * Return the configured active target name required for Sync Manager runs.
     *
     * @return string Active target name.
     */
    private function get_Active_Target_Name(): string
    {
        $target_Name = TargetManager::get_activeTarget();
        if ($target_Name === '') {
            throw new RuntimeException('Select an active target before starting Sync Manager.');
        }

        return $target_Name;
    }

}
