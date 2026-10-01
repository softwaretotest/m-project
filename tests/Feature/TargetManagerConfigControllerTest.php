<?php

namespace Tests\Feature;

use App\Http\Controllers\TargetManager_Config_Controller;
use Tests\TestCase;

class TargetManagerConfigControllerTest extends TestCase
{
    /**
     * Do not expose this M-project installation as a selectable target.
     *
     * @return void
     */
    public function test_scan_excludes_m_project_from_scanned_and_saved_targets(): void
    {
        $controller = new TargetManager_Config_Controller();
        $scan_Projects = new \ReflectionMethod($controller, 'scan_Laravel_Projects');
        $merge_Saved_Targets = new \ReflectionMethod($controller, 'merge_Saved_Targets');
        $parent_Path = dirname(base_path());

        $projects = $merge_Saved_Targets->invoke(
            $controller,
            $scan_Projects->invoke($controller, str_replace('\\', '/', $parent_Path))
        );

        $m_Project_Path = rtrim(str_replace('\\', '/', realpath(base_path())), '/');
        $project_Paths = array_map(
            static fn (array $project): string => rtrim($project['root_path'], '/'),
            $projects
        );

        $this->assertNotContains(
            PHP_OS_FAMILY === 'Windows' ? strtolower($m_Project_Path) : $m_Project_Path,
            array_map(
                static fn (string $path): string => PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path,
                $project_Paths
            )
        );
    }

    /**
     * Reject selecting M-project directly without changing the saved target config.
     *
     * @return void
     */
    public function test_m_project_cannot_be_saved_as_its_own_target(): void
    {
        $config_Path = app_path('Constant/3_TargetManager_Config.json');
        $original_Config = file_get_contents($config_Path);

        $this->postJson('/api/target/save_Target_App', [
            'path' => base_path(),
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'M-project is the management app and cannot be selected as a target.');

        $this->assertSame($original_Config, file_get_contents($config_Path));
    }
}
