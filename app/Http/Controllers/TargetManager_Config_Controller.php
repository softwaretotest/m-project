<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TargetManager_Config_Controller extends Controller
{
    private const CONFIG_PATH = 'Constant/3_TargetManager_Config.json';

    /**
     * @return string Absolute path to 3_TargetManager_Config.json.
     */
    private function get_Config_Path(): string
    {
        return app_path(self::CONFIG_PATH);
    }

    /**
     * create or update activeTarget App.
     */
    public function updateTargetConfig(Request $request): JsonResponse
    {
        $inputPath = $request->input('path', '');

        $config = ['activeTarget' => '', 'targets' => []];
        if (file_exists($this->get_Config_Path())) {
            $old = json_decode(file_get_contents($this->get_Config_Path()), true);
            if (is_array($old)) {
                $config['targets'] = $old['targets'] ?? [];
                $config['activeTarget'] = $old['activeTarget'] ?? '';
            }
        }

        if (trim((string) $inputPath) === '') {
            $config['activeTarget'] = '';
        } else {
            // Save the selected Laravel target.
            $path = str_replace('\\', '/', trim((string) $inputPath));
            $real = realpath($path);

            if ($real === false) {
                return response()->json(['success' => false, 'message' => "Path does not exist: {$path}"], 422);
            }

            $real = str_replace('\\', '/', $real);
            if (!file_exists($real . '/artisan')) {
                return response()->json(['success' => false, 'message' => 'Not a Laravel project (artisan not found)'], 422);
            }

            $name = basename($real);
            $config['targets'][$name] = ['root_path' => $real];
            $config['activeTarget'] = $name;
        }

        $written = file_put_contents(
            $this->get_Config_Path(),
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($written === false) {
            return response()->json(['success' => false, 'message' => 'Cannot write config file'], 500);
        }

        return response()->json([
            'success' => true,
            'activeTarget' => $config['activeTarget']
        ]);
    }

    /**
     * 1. Scan a parent directory for Laravel projects, then merge saved targets from config.
     * 2. Save the merged list back to config
     * @param  \Illuminate\Http\Request  $request  Optional input "base", e.g. "C:/Users/o/.vscode/react"
     * @return \Illuminate\Http\JsonResponse  e.g.
     * * {
     * *    "success":true,
     * *    "base":"C:/Users/o/.vscode/react",
     * *    "projects":
     * *    [
     * *        {
     * *            "name":"ecommerce",
     * *            "root_path":"C:/Users/o/.vscode/react/ecommerce"
     * *        },
     * *        {
     * *            "name":"m-project",
     * *            "root_path":"C:/Users/o/.vscode/react/m-project"
     * *         }
     * *    ]
     * * }
     */
    public function scanTargets(Request $request)
    {
        $parent_Path = $this->normalize_Path($request->input('base') ?: dirname(base_path()));

        if (!is_dir($parent_Path)) {
            return response()->json(['success' => false, 'message' => "Base not found: {$parent_Path}"], 404);
        }

        $scanned_Projects = $this->scan_Laravel_Projects($parent_Path);
        $merged_Projects  = $this->merge_Saved_Targets($scanned_Projects);

        $config_Data = ['activeTarget' => '', 'targets' => []];

        if (file_exists($this->get_Config_Path())) {
            $config_Content = file_get_contents($this->get_Config_Path());
            $saved_Config   = json_decode((string) $config_Content, true);

            if (!is_array($saved_Config) || !is_array($saved_Config['targets'] ?? null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot save scan results because the target config is invalid',
                ], 500);
            }

            $config_Data['activeTarget'] = $saved_Config['activeTarget'] ?? '';
            $config_Data['targets']      = $saved_Config['targets'];
        }

        foreach ($merged_Projects as $project) {
            $target_Name = $project['name'];
            $target_Info = $config_Data['targets'][$target_Name] ?? [];

            $config_Data['targets'][$target_Name] = array_merge(
                is_array($target_Info) ? $target_Info : [],
                ['root_path' => $project['root_path']]
            );
        }

        $written = file_put_contents(
            $this->get_Config_Path(),
            json_encode($config_Data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        if ($written === false) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot save scanned targets to config file',
            ], 500);
        }

        return response()->json([
            'success'  => true,
            'base'     => $parent_Path,
            'projects' => $merged_Projects,
        ]);
    }

    /**
     * Convert Windows backslashes into forward slashes.
     *
     * @param  string  $raw_Path  e.g. "C:\Users\o\.vscode\react"
     * @return string  e.g. "C:/Users/o/.vscode/react"
     */
    private function normalize_Path(string $raw_Path): string
    {
        return str_replace('\\', '/', $raw_Path);
    }

    /**
     * Check that a folder is a real Laravel project (must own artisan + composer.json).
     *
     * @param  string  $folder_Path  e.g. "C:/Users/o/.vscode/react/ecommerce"
     * @return bool  true when both marker files exist, otherwise false
     */
    private function is_Laravel_Project(string $folder_Path): bool
    {
        if (!is_dir($folder_Path)) {
            return false;
        }
        if (!file_exists($folder_Path . '/artisan')) {
            return false;
        }
        if (!file_exists($folder_Path . '/composer.json')) {
            return false;
        }

        return true;
    }

    /**
     * List every direct child folder that validates as a Laravel project.
     *
     * @param string $parent_Path Parent directory to scan.
     * @return array<int, array{name: string, root_path: string}> e.g.
     * ```php
     * [
     *     [
     *         'name' => 'ecommerce',
     *         'root_path' => 'C:/Users/o/.vscode/react/ecommerce',
     *     ],
     *     [
     *         'name' => 'm-project',
     *         'root_path' => 'C:/Users/o/.vscode/react/m-project',
     *     ],
     * ]
     * ```
     */
    private function scan_Laravel_Projects(string $parent_Path): array
    {
        $projects = [];

        foreach (scandir($parent_Path) as $entry_Name) {
            if ($entry_Name === '.' || $entry_Name === '..') {
                continue;
            }

            $entry_Path = $parent_Path . '/' . $entry_Name;
            if (!$this->is_Laravel_Project($entry_Path)) {
                continue;
            }

            $projects[] = [
                'name'      => $entry_Name,
                'root_path' => $this->normalize_Path(realpath($entry_Path)),
            ];
        }
        return $projects;
    }

    /**
     * Append targets stored in 3_TargetManager_Config.json so a scan never drops history.
     *
     * @param array<int, array{name: string, root_path: string}> $scanned_Projects Projects found on disk, e.g.
     * ```php
     * [
     *     [
     *         'name' => 'ecommerce',
     *         'root_path' => 'C:/Users/o/.vscode/react/ecommerce',
     *     ],
     * ]
     * ```
     * @return array<int, array{name: string, root_path: string}> Scanned projects merged with saved targets, e.g.
     * ```php
     * [
     *     [
     *         'name' => 'ecommerce',
     *         'root_path' => 'C:/Users/o/.vscode/react/ecommerce',
     *     ],
     *     [
     *         'name' => 'learn_backend',
     *         'root_path' => 'C:/Users/o/.vscode/react/learn/learn_backend',
     *     ],
     * ]
     * ```
     */
    private function merge_Saved_Targets(array $scanned_Projects): array
    {
        if (!file_exists($this->get_Config_Path())) {
            return $scanned_Projects;
        }

        $config_Data    = json_decode(file_get_contents($this->get_Config_Path()), true);
        $saved_Targets  = $config_Data['targets'] ?? null;
        if (!is_array($saved_Targets)) {
            return $scanned_Projects;
        }

        $existing_Paths = array_column($scanned_Projects, 'root_path');

        foreach ($saved_Targets as $target_Name => $target_Info) {
            $target_Path = $this->normalize_Path($target_Info['root_path'] ?? '');

            if ($target_Path === '') {
                continue;
            }
            if (in_array($target_Path, $existing_Paths)) {
                continue;
            }
            if (!is_dir($target_Path)) {
                continue;
            }

            $scanned_Projects[] = [
                'name'      => $target_Name,
                'root_path' => $target_Path,
            ];

            $existing_Paths[] = $target_Path;
        }

        return $scanned_Projects;
    }
}
