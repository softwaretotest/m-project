<?php

namespace App\Constant;

class M_Sync_Service_EXE_Worker
{
    public function execute_Worker(string $phase): int
    {
        $is_continue = ($phase === M_Sync_Service::PHASE_CONTINUE);

        $EXE_Script = new M_Sync_Service_EXE_Script();

        $run_Record = M_Sync_Service_Status_Log::read_run_Record();

        if (!is_array($run_Record)) {
            $this->fail_Run("Active run record not found.");
            return 1;
        }

        $script_IDs = $run_Record['selected_scripts'] ?? [];

        $this->update_Run(M_Sync_Service::STATUS_RUNNING);

        foreach ($script_IDs as $script_ID) {

            // 1. for PHASE_CONTINUE: skip if this script is finished
            if ($is_continue) {
                $current_status = $run_Record['script_statuses'][$script_ID] ?? 'pending';
                if ($current_status === M_Sync_Service::STATUS_COMPLETED) {
                    continue;
                }
            }

            // 2. run script + write Log
            $this->update_Script_Status($script_ID, M_Sync_Service::SCRIPT_STATUS_WARNING);
            M_Sync_Service_Status_Log::write_Log(
                "---------- START script: {$script_ID} ----------" . PHP_EOL
            );

            $result = $EXE_Script->execute_EXE_Script($run_Record, $script_ID);

            // 3. check Error
            if (!($result['success'] ?? false) && ($result['exit_code'] ?? 0) !== 0) {
                $this->update_Script_Status($script_ID, M_Sync_Service::STATUS_FAILED);
                $this->fail_Run("Script {$script_ID} execution failed.");
                return 1;
            }

            // 4. for PHASE_INITIAL: stop for review Entities.json , if not exist
            if (false === $is_continue && !empty($result['has_missing_entities_json'])) {
                $this->update_Script_Status($script_ID, M_Sync_Service::STATUS_COMPLETED);
                $this->update_Run(M_Sync_Service::STATUS_AWAITING_REVIEW);
                return 0;
            }

            $this->update_Script_Status($script_ID, M_Sync_Service::STATUS_COMPLETED);
        }

        $this->update_Run(M_Sync_Service::STATUS_COMPLETED);
        return 0;
    }

    /**
     * Updates the status of a specific script for the active target.
     *
     * @param string $script_ID Script identifier.
     * @param string $script_Status New status value.
     * @return void
     */
    private function update_Script_Status(string $script_ID, string $script_Status): void
    {
        $run_Record = M_Sync_Service_Status_Log::read_run_Record();

        if (isset($run_Record['script_statuses'])) {
            $run_Record['script_statuses'][$script_ID] = $script_Status;
            M_Sync_Service_Status_Log::write_M_Sync_Status_json($run_Record);
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
        $run_Record = M_Sync_Service_Status_Log::read_run_Record();
        if (isset($run_Record)) {
            $run_Record['status'] = 'failed';
            $run_Record['message'] = $message;
            M_Sync_Service_Status_Log::write_M_Sync_Status_json($run_Record);
        }
        Logger::collect_error('Error-Text : '.$message . PHP_EOL);
    }

    /**
    * Updates a persisted run record using a callback function.
    *
    * @param callable $status Function to modify the run record.
    * @return void
    */
    private function update_Run(string $status): void
    {
        $run_Record = M_Sync_Service_Status_Log::read_run_Record();
        $run_Record = $run_Record ?? null;
        if ($run_Record) {
            $run_Record['status'] = $status;
            M_Sync_Service_Status_Log::write_M_Sync_Status_json($run_Record);
        }
    }
}
