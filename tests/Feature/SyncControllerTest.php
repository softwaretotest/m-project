<?php

namespace Tests\Feature;

use App\Constant\M_Sync_Service;
use App\Constant\M_Sync_Service_Status_Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncControllerTest extends TestCase
{
    /**
     * Reject script identifiers that are not in the backend allowlist.
     *
     * @return void
     */
    public function test_start_rejects_unallowlisted_script_ids(): void
    {
        $this->postJson('/api/sync/start', [
            'scripts' => ['../../artisan'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('scripts.0');
    }

    /**
     * Require at least one selected script before a run can start.
     *
     * @return void
     */
    public function test_start_requires_at_least_one_script(): void
    {
        $this->postJson('/api/sync/start', [
            'scripts' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('scripts');
    }

    /**
     * Clear a failed run from current and legacy status files and delete its log.
     *
     * @return void
     */
    public function test_reset_failed_run_clears_backend_status_files_and_deletes_run_log(): void
    {
        $original_Storage_Path = app()->storagePath();
        $test_Storage_Path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sync-reset-' . Str::uuid();
        $status_Directory = $test_Storage_Path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'm-sync';
        $log_Directory = $status_Directory . DIRECTORY_SEPARATOR . 'logs';
        File::ensureDirectoryExists($log_Directory);
        app()->useStoragePath($test_Storage_Path);

        $canonical_Status_Path = $status_Directory . DIRECTORY_SEPARATOR . 'sync_status.json';
        $legacy_Status_Path = $status_Directory . DIRECTORY_SEPARATOR . 'status.json';
        $run_ID = (string) Str::uuid();
        $run_Log_Path = $log_Directory . DIRECTORY_SEPARATOR . $run_ID . '.log';
        $failed_Run = ['run_id' => $run_ID, 'target' => 'ecommerce', 'status' => 'failed'];
        $completed_Other_Run = ['run_id' => 'other-run', 'target' => 'm-project', 'status' => 'completed'];

        file_put_contents($canonical_Status_Path, json_encode([
            'ecommerce' => $failed_Run,
            'm-project' => $completed_Other_Run,
        ]));
        file_put_contents($legacy_Status_Path, json_encode([
            'ecommerce' => $failed_Run,
            'learn_backend' => $completed_Other_Run,
        ]));
        file_put_contents($run_Log_Path, 'Worker launch failed');

        try {
            $result = app(M_Sync_Service::class)->resetFailedRun();

            $this->assertTrue($result['reset']);
            $this->assertSame(['m-project' => $completed_Other_Run], json_decode(
                file_get_contents($canonical_Status_Path),
                true
            ));
            $this->assertSame(['learn_backend' => $completed_Other_Run], json_decode(
                file_get_contents($legacy_Status_Path),
                true
            ));
            $this->assertFileDoesNotExist($run_Log_Path);
        } finally {
            app()->useStoragePath($original_Storage_Path);
            File::deleteDirectory($test_Storage_Path);
        }
    }

    /**
     * Buffer worker output in its run log for UI polling.
     *
     * @return void
     */
    public function test_worker_output_is_read_from_run_log_by_incremental_cursor(): void
    {
        $original_Storage_Path = app()->storagePath();
        $test_Storage_Path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sync-output-' . Str::uuid();
        $log_Directory = $test_Storage_Path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'm-sync' . DIRECTORY_SEPARATOR . 'logs';
        File::ensureDirectoryExists($log_Directory);
        app()->useStoragePath($test_Storage_Path);

        $run_ID = (string) Str::uuid();
        $run_Log_Path = $log_Directory . DIRECTORY_SEPARATOR . $run_ID . '.log';

        try {
            file_put_contents($run_Log_Path, '');

            M_Sync_Service_Status_Log::append_Run_Output(
                $run_ID,
                "First output line\nSecond output"
            );
            $first_Chunk = M_Sync_Service_Status_Log::read_Log_Chunk($run_ID, 0, false);
            $this->assertSame("First output line\n", $first_Chunk['logs']);
            $this->assertSame(strlen("First output line\n"), $first_Chunk['cursor']);

            M_Sync_Service_Status_Log::append_Run_Output($run_ID, " line\n");
            $final_Chunk = M_Sync_Service_Status_Log::read_Log_Chunk(
                $run_ID,
                $first_Chunk['cursor'],
                true
            );
            $this->assertSame("Second output line\n", $final_Chunk['logs']);
            $this->assertFileExists($run_Log_Path);
            $this->assertSame(
                "First output line\nSecond output line\n",
                file_get_contents($run_Log_Path)
            );
        } finally {
            app()->useStoragePath($original_Storage_Path);
            File::deleteDirectory($test_Storage_Path);
        }
    }

    /**
     * Delete stale run logs at the next run while preserving logs for other active runs.
     *
     * @return void
     */
    public function test_cleanup_deletes_unused_run_logs_and_preserves_active_run_logs(): void
    {
        $original_Storage_Path = app()->storagePath();
        $test_Storage_Path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sync-cleanup-' . Str::uuid();
        $log_Directory = $test_Storage_Path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'm-sync' . DIRECTORY_SEPARATOR . 'logs';
        File::ensureDirectoryExists($log_Directory);
        app()->useStoragePath($test_Storage_Path);

        $active_Run_ID = (string) Str::uuid();
        $review_Run_ID = (string) Str::uuid();
        $completed_Run_ID = (string) Str::uuid();
        $orphan_Run_ID = (string) Str::uuid();
        $unrelated_Log_Path = $log_Directory . DIRECTORY_SEPARATOR . 'other.log';

        foreach ([$active_Run_ID, $review_Run_ID, $completed_Run_ID, $orphan_Run_ID] as $run_ID) {
            file_put_contents($log_Directory . DIRECTORY_SEPARATOR . $run_ID . '.log', $run_ID);
        }
        file_put_contents($unrelated_Log_Path, 'unrelated');

        try {
            M_Sync_Service_Status_Log::delete_Unused_Run_Logs([
                'active-target' => ['run_id' => $active_Run_ID, 'status' => 'running'],
                'review-target' => ['run_id' => $review_Run_ID, 'status' => 'awaiting_review'],
                'completed-target' => ['run_id' => $completed_Run_ID, 'status' => 'completed'],
            ], static fn (array $run_Record): bool => in_array(
                $run_Record['status'],
                ['starting', 'running', 'awaiting_review'],
                true
            ));

            $this->assertFileExists($log_Directory . DIRECTORY_SEPARATOR . $active_Run_ID . '.log');
            $this->assertFileExists($log_Directory . DIRECTORY_SEPARATOR . $review_Run_ID . '.log');
            $this->assertFileDoesNotExist($log_Directory . DIRECTORY_SEPARATOR . $completed_Run_ID . '.log');
            $this->assertFileDoesNotExist($log_Directory . DIRECTORY_SEPARATOR . $orphan_Run_ID . '.log');
            $this->assertFileExists($unrelated_Log_Path);
        } finally {
            app()->useStoragePath($original_Storage_Path);
            File::deleteDirectory($test_Storage_Path);
        }
    }

    /**
     * Execute a safe Windows worker probe and verify its integer exit code is accepted.
     *
     * @return void
     */
    public function test_windows_script_runner_returns_the_child_exit_code(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The Windows child-process runner is only available on Windows.');
        }

        $original_Storage_Path = app()->storagePath();
        $test_Storage_Path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sync-worker-' . Str::uuid();
        $status_Directory = $test_Storage_Path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'm-sync';
        $log_Directory = $status_Directory . DIRECTORY_SEPARATOR . 'logs';
        File::ensureDirectoryExists($log_Directory);
        app()->useStoragePath($test_Storage_Path);

        $run_ID = (string) Str::uuid();
        $run_Log_Path = $log_Directory . DIRECTORY_SEPARATOR . $run_ID . '.log';
        $worker_Script_Path = $test_Storage_Path . DIRECTORY_SEPARATOR . 'worker-probe.php';
        $run_Record = [
            'run_id' => $run_ID,
            'target' => 'ecommerce',
        ];
        file_put_contents($run_Log_Path, '');
        file_put_contents($worker_Script_Path, "<?php echo 'worker probe passed';");
        file_put_contents(
            $status_Directory . DIRECTORY_SEPARATOR . 'sync_status.json',
            json_encode(['ecommerce' => $run_Record])
        );

        try {
            $sync_Service = app(M_Sync_Service::class);
            $windows_Runner = new \ReflectionMethod(M_Sync_Service::class, 'execute_Windows_Script');
            $exit_Code = $windows_Runner->invoke($sync_Service, $run_Record, $worker_Script_Path);
            $output_Chunk = M_Sync_Service_Status_Log::read_Log_Chunk($run_ID, 0, true);

            $this->assertSame(0, $exit_Code);
            $this->assertStringContainsString('worker probe passed', $output_Chunk['logs']);
        } finally {
            app()->useStoragePath($original_Storage_Path);
            File::deleteDirectory($test_Storage_Path);
        }
    }
}
