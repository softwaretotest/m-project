<?php

namespace App\Constant;

use Closure;
use RuntimeException;
use Throwable;

class M_Sync_Manager_Service_EXE_Worker
{
    /**
     * @param  Closure(string): (array<string, mixed>|null)  $find_Run  Loads the persisted run record.
     * @param  Closure(string, string, callable): void  $update_Run  Updates one persisted run record.
     * @param  Closure(string, string, string, string): void  $update_Script_Status  Updates one script state.
     * @param  Closure(string, string): void  $append_Run_Output  Appends text to the run log.
     * @param  Closure(array<string, mixed>, string): array{exit_code: int, has_missing_entities_json: bool}  $execute_Script  Runs one selected script.
     * @param  Closure(string, string, string): void  $fail_Run  Marks a run failed with its message.
     * @param array{
     *     phase_initial: string,
     *     phase_continue: string,
     *     status_running: string,
     *     status_awaiting_review: string,
     *     status_completed: string,
     *     status_failed: string,
     *     script_json_to_php: string,
     *     script_status_warning: string
     * } $states Run and script state values.
     */
    public function __construct(
        private readonly Closure $find_Run,
        private readonly Closure $update_Run,
        private readonly Closure $update_Script_Status,
        private readonly Closure $append_Run_Output,
        private readonly Closure $execute_Script,
        private readonly Closure $fail_Run,
        private readonly array $states
    ) {}

    /**
     * Execute one persisted phase, update its run/script states, and return its process exit code.
     *
     * @param  string  $run_ID  Identifier of the persisted run.
     * @param  string  $phase  Worker phase: initial or continue.
     * @return int Zero on success, non-zero when the run fails.
     */
    public function execute_Worker(string $run_ID, string $phase): int
    {
        $run_Record = ($this->find_Run)($run_ID);

        if ($run_Record === null) {
            throw new RuntimeException("Sync Manager run not found: {$run_ID}");
        }

        $target_Name = $run_Record['target'];
        $script_IDs = $phase === $this->states['phase_continue']
            ? $run_Record['final_scripts']
            : $run_Record['initial_scripts'];

        ($this->update_Run)(
            $target_Name,
            $run_ID,
            function (array $current_Run): array {
                $current_Run['status'] = $this->states['status_running'];
                $current_Run['pid'] = getmypid();
                $current_Run['updated_at'] = date(DATE_ATOM);

                return $current_Run;
            }
        );

        foreach ($script_IDs as $script_ID) {
            ($this->update_Script_Status)(
                $target_Name,
                $run_ID,
                $script_ID,
                $this->states['status_running']
            );
            ($this->append_Run_Output)($run_ID, "[[M_SYNC_SCRIPT_START:{$script_ID}]]".PHP_EOL);

            try {
                $script_Result = ($this->execute_Script)($run_Record, $script_ID);
            } catch (Throwable $exception) {
                ($this->update_Script_Status)(
                    $target_Name,
                    $run_ID,
                    $script_ID,
                    $this->states['status_failed']
                );
                ($this->fail_Run)($target_Name, $run_ID, $exception->getMessage());

                return 1;
            } finally {
                ($this->append_Run_Output)($run_ID, "[[M_SYNC_SCRIPT_END:{$script_ID}]]".PHP_EOL);
            }

            if ($script_Result['exit_code'] !== 0) {
                ($this->update_Script_Status)(
                    $target_Name,
                    $run_ID,
                    $script_ID,
                    $this->states['status_failed']
                );
                ($this->fail_Run)(
                    $target_Name,
                    $run_ID,
                    "Script '{$script_ID}' exited with code {$script_Result['exit_code']}"
                );

                return $script_Result['exit_code'];
            }

            $script_Status = $script_ID === $this->states['script_json_to_php']
                && $script_Result['has_missing_entities_json']
                    ? $this->states['script_status_warning']
                    : $this->states['status_completed'];
            ($this->update_Script_Status)($target_Name, $run_ID, $script_ID, $script_Status);
        }

        if ($phase === $this->states['phase_initial'] && $run_Record['requires_review']) {
            ($this->update_Run)(
                $target_Name,
                $run_ID,
                function (array $current_Run): array {
                    $current_Run['status'] = $this->states['status_awaiting_review'];
                    $current_Run['pid'] = null;
                    $current_Run['message'] = 'Review Entities.json, then continue the selected generation scripts.';
                    $current_Run['updated_at'] = date(DATE_ATOM);

                    return $current_Run;
                }
            );

            return 0;
        }

        ($this->update_Run)(
            $target_Name,
            $run_ID,
            function (array $current_Run): array {
                $current_Run['status'] = $this->states['status_completed'];
                $current_Run['pid'] = null;
                $current_Run['finished_at'] = date(DATE_ATOM);
                $current_Run['updated_at'] = date(DATE_ATOM);

                return $current_Run;
            }
        );

        return 0;
    }
}
