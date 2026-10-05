<?php

namespace App\Constant;

use Throwable;

class M_Sync_Service_EXE_Worker
{
    private string $target_Name;

    public function __construct()
    {
        $this->target_Name = TargetManager::get_activeTarget();
    }

    /**
    * Loads the persisted run record for the active target.
    *
    * @return array<string, mixed>|null The run record array or null if not found.
    */    private function find_Run(): ?array
    {
        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        return $status_Data[$this->target_Name] ?? null;
    }


    /**
     * Executes the requested allowlisted script via the execution runner.
     *
     * @param array<string, mixed> $run_Record Persisted run record.
     * @param string $script_ID Allowlisted script identifier.
     * @return array{exit_code: int, has_missing_entities_json: bool}
     */
    private function execute_Script(array $run_Record, string $script_ID): array
    {
        $EXE_Script = new M_Sync_Service_EXE_Script();

        return $EXE_Script->execute_EXE_Script($run_Record, $script_ID);
    }

    /**
     * Updates the status of a specific script for the active target.
     *
     * @param string $script_ID Script identifier.
     * @param string $status_Data New status value.
     * @return void
     */
    private function update_Script_Status(string $script_ID, string $status_Data): void
    {
        // \Illuminate\Support\Facades\Log::info('[ 7 ] WORKER update_Script_Status : script_ID = ' . $script_ID);
        // \Illuminate\Support\Facades\Log::info('[ 8 ] WORKER update_Script_Status : status_Data = ' . $status_Data);
        $status_Data_All = M_Sync_Service_Status_Log::read_Status_Data();

        if (isset($status_Data_All[$this->target_Name]['script_statuses'])) {
            $status_Data_All[$this->target_Name]['script_statuses'][$script_ID] = $status_Data;
            // \Illuminate\Support\Facades\Log::info('[ 9 ] WORKER update_Script_Status : status_Data = ' . json_encode($status_Data_All, JSON_PRETTY_PRINT));
            M_Sync_Service_Status_Log::write_Status_Data($status_Data_All);
        }
    }

    /**
     * Marks the active run record as failed with the given error message.
     *
     * @param string $message Error message describing the failure.
     * @return void
     */
    private function fail_Run(string $message): void
    {
        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        if (isset($status_Data[$this->target_Name])) {
            $status_Data[$this->target_Name]['status'] = 'failed';
            $status_Data[$this->target_Name]['message'] = $message;
            M_Sync_Service_Status_Log::write_Status_Data($status_Data);
        }

        $log_Path = base_path(M_Sync_Service_Status_Log::LOG_FILE);
        $failure_Message = "[ 🚫 fail_Run ] EXE_Worker FAILED : {$message}" . PHP_EOL;
        file_put_contents($log_Path, $failure_Message, FILE_APPEND);
        \Illuminate\Support\Facades\Log::error($failure_Message);
    }


    /**
    * Updates a persisted run record using a callback function.
    *
    * @param callable $status Function to modify the run record.
    * @return void
    */
    private function update_Run(string $status): void
    {
        $status_Data = M_Sync_Service_Status_Log::read_Status_Data();
        $run_Record = $status_Data[$this->target_Name] ?? null;
        if ($run_Record) {
            $run_Record['status'] = $status;
            $status_Data[$this->target_Name]['status'] = $status;
            M_Sync_Service_Status_Log::write_Status_Data($status_Data);
        }
    }

    /**
     * Execute one persisted phase, update its run/script states, and return its process exit code.
     */
    public function execute_Worker(string $phase): int
    {
        try {
            $run_Record = $this->find_Run();
            if (!is_array($run_Record)) {
                $this->fail_Run("Active run record not found.");
                return 1;
            }

            $script_IDs = $run_Record['selected_scripts'] ?? [];
            // \Illuminate\Support\Facades\Log::info('[ 1 ] WORKER TRY START: script_count = ' . count($script_IDs));
            // \Illuminate\Support\Facades\Log::info('[ 2 ] $phase: ' . $phase . ', $script_IDs: ' . json_encode($script_IDs));
            // \Illuminate\Support\Facades\Log::info('[ 3 ] $this->states[phase_initial]: ' . M_Sync_Service::PHASE_INITIAL . ', $script_IDs: ' . json_encode($script_IDs));

            if ($phase === M_Sync_Service::PHASE_INITIAL) {
                // \Illuminate\Support\Facades\Log::info('[ 4 ] WORKER IS TRYING TO START update_Run : status = ' . M_Sync_Service::STATUS_RUNNING);
                $this->update_Run(M_Sync_Service::STATUS_RUNNING);
                // \Illuminate\Support\Facades\Log::info('[ 5 ] WORKER !!! FINISH !!! update_Run : status = ' . M_Sync_Service::STATUS_RUNNING);

                foreach ($script_IDs as $script_ID) {
                    // \Illuminate\Support\Facades\Log::info('[ 6 ] WORKER START EXECUTING : script_ID = ' . $script_ID);
                    $this->update_Script_Status($script_ID, M_Sync_Service::SCRIPT_STATUS_WARNING);

                    $output = "---------- START script: {$script_ID} ----------" . PHP_EOL;
                    M_Sync_Service_Status_Log::write_Log($output);

                    $result = $this->execute_Script($run_Record, $script_ID);

                    if (!($result['success'] ?? false) && ($result['exit_code'] ?? 0) !== 0) {
                        $this->update_Script_Status($script_ID, M_Sync_Service::STATUS_FAILED);
                        $this->fail_Run("Script {$script_ID} execution failed.");
                        return 1;
                    }

                    if (!empty($result['has_missing_entities_json'])) {
                        $this->update_Script_Status($script_ID, M_Sync_Service::STATUS_COMPLETED);
                        $this->update_Run(M_Sync_Service::STATUS_AWAITING_REVIEW);
                        return 0;
                    }

                    $this->update_Script_Status($script_ID, M_Sync_Service::STATUS_COMPLETED);
                }

                $this->update_Run(M_Sync_Service::STATUS_COMPLETED);
            } elseif ($phase === M_Sync_Service::PHASE_CONTINUE) {
                $this->update_Run(M_Sync_Service::STATUS_RUNNING);

                foreach ($script_IDs as $script_ID) {
                    $current_status = $run_Record['script_statuses'][$script_ID] ?? 'pending';
                    if ($current_status === M_Sync_Service::STATUS_COMPLETED) {
                        continue;
                    }

                    $this->update_Script_Status($script_ID, M_Sync_Service::SCRIPT_STATUS_WARNING);

                    $output = "---------- RESUME script: {$script_ID} ----------" . PHP_EOL;
                    M_Sync_Service_Status_Log::write_Log($output);

                    $result = $this->execute_Script($run_Record, $script_ID);

                    if (!($result['success'] ?? false) && ($result['exit_code'] ?? 0) !== 0) {
                        $this->update_Script_Status($script_ID, M_Sync_Service::STATUS_FAILED);
                        $this->fail_Run("Script {$script_ID} execution failed.");
                        return 1;
                    }

                    $this->update_Script_Status($script_ID, M_Sync_Service::STATUS_COMPLETED);
                }

                $this->update_Run(M_Sync_Service::STATUS_COMPLETED);
            }

            return 0;
        } catch (Throwable $exception) {
            $this->fail_Run($exception->getMessage());
            return 1;
        }
    }
}
