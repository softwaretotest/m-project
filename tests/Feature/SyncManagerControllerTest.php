<?php

namespace Tests\Feature;

use App\Services\SyncManagerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncManagerControllerTest extends TestCase
{
    /**
     * Reject script identifiers that are not in the backend allowlist.
     *
     * @return void
     */
    public function test_start_rejects_unallowlisted_script_ids(): void
    {
        $this->postJson('/api/sync-manager/start', [
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
        $this->postJson('/api/sync-manager/start', [
            'scripts' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('scripts');
    }

    /**
     * Clear a failed run from current and legacy status files while preserving its log.
     *
     * @return void
     */
    public function test_reset_failed_run_clears_backend_status_files_and_keeps_run_log(): void
    {
        $original_Storage_Path = app()->storagePath();
        $test_Storage_Path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sync-manager-reset-' . Str::uuid();
        $status_Directory = $test_Storage_Path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'm-sync-manager';
        $log_Directory = $status_Directory . DIRECTORY_SEPARATOR . 'logs';
        File::ensureDirectoryExists($log_Directory);
        app()->useStoragePath($test_Storage_Path);

        $canonical_Status_Path = $status_Directory . DIRECTORY_SEPARATOR . 'sync_status.json';
        $legacy_Status_Path = $status_Directory . DIRECTORY_SEPARATOR . 'status.json';
        $run_Log_Path = $log_Directory . DIRECTORY_SEPARATOR . 'failed-run.log';
        $failed_Run = ['run_id' => 'failed-run', 'target' => 'ecommerce', 'status' => 'failed'];
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
            $result = app(SyncManagerService::class)->resetFailedRun();

            $this->assertTrue($result['reset']);
            $this->assertSame(['m-project' => $completed_Other_Run], json_decode(
                file_get_contents($canonical_Status_Path),
                true
            ));
            $this->assertSame(['learn_backend' => $completed_Other_Run], json_decode(
                file_get_contents($legacy_Status_Path),
                true
            ));
            $this->assertFileExists($run_Log_Path);
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
        $test_Storage_Path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sync-manager-worker-' . Str::uuid();
        $status_Directory = $test_Storage_Path . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'm-sync-manager';
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
            $sync_Manager_Service = app(SyncManagerService::class);
            $windows_Runner = new \ReflectionMethod(SyncManagerService::class, 'execute_Windows_Script');
            $exit_Code = $windows_Runner->invoke($sync_Manager_Service, $run_Record, $worker_Script_Path);

            $this->assertSame(0, $exit_Code);
            $this->assertStringContainsString('worker probe passed', file_get_contents($run_Log_Path));
        } finally {
            app()->useStoragePath($original_Storage_Path);
            File::deleteDirectory($test_Storage_Path);
        }
    }
}
