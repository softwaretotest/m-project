<?php

namespace App\Http\Controllers;

use App\Constant\TargetManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class M_Controller extends Controller
{
    /**
     * @return e.g 
     * * C:\Users\o\.vscode\react\ecommerce\app\Constant\M_JSON\Entities.json
     * * C:\Users\o\.vscode\react\ecommerce\app\Constant\M_JSON\App-Data.json
     * * C:\Users\o\.vscode\react\ecommerce\app\Constant\M_JSON\M-Data.json
     */
    private function getAll_JSON_FilesPath(): array
    {
        return [
            'app_data' => TargetManager::gen_path('Constant/M_JSON/App-Data.json', 'app'),
            'm_data'   => TargetManager::gen_path('Constant/M_JSON/M-Data.json', 'app'),
            'entities' => TargetManager::gen_path('Constant/M_JSON/Entities.json', 'app'),
        ];
    }


    /**
     * get path by key
     * @param $key = e.g. app_data , m_data , entities
     * @return e.g. c:\Users\o\.vscode\react\ecommerce\app\Constant\M_JSON\Entities.json
     */
    private function getPath(string $key): string
    {
        $files = $this->getAll_JSON_FilesPath();
        return $files[$key] ?? '';
    }

    /**
     * * get activeTarget and Array data from TargetManager 
     * * and send to response()->json()
     */
    public function get_M_Config_json(Request $request): JsonResponse
    {
        return response()->json([
            'activeTarget' => TargetManager::get_activeTarget(),
            'targets'      => TargetManager::$targets
        ]);
    }

    /**
     * create or update activeTarget App.
     */
    public function updateTargetConfig(Request $request)
    {
        $inputPath = $request->input('path', '');
        $configPath = base_path('app/Constant/3_M-Config.json');

        // read old config
        $config = ['activeTarget' => '', 'targets' => []];
        if (file_exists($configPath)) {
            $old = json_decode(file_get_contents($configPath), true);
            if (is_array($old)) {
                $config['targets'] = $old['targets'] ?? [];
                $config['activeTarget'] = $old['activeTarget'] ?? '';
            }
        }

        // CASE clear activeTarget (send empty path to backend)
        if (trim((string) $inputPath) === '') {
            $config['activeTarget'] = '';
        } else {
            // CASE save new Target 
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

        // write new data to JSON
        $written = file_put_contents(
            $configPath,
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
     * Scan a parent directory for Laravel projects, then merge saved targets from config.
     *
     * @param  \Illuminate\Http\Request  $request  Optional input "base", e.g. "C:/Users/o/.vscode/react"
     * @return \Illuminate\Http\JsonResponse  e.g. 
     * * {
     * * * "success":true,
     * * * "base":"C:/Users/o/.vscode/react",
     * * * "projects":
     * * * [
     * * * * {
     * * * * "name":"ecommerce",
     * * * * "root_path":"C:/Users/o/.vscode/react/ecommerce"
     * * * * }
     * * * ]
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
        if (!is_dir($folder_Path))                            return false;
        if (!file_exists($folder_Path . '/artisan'))          return false;
        if (!file_exists($folder_Path . '/composer.json'))    return false;

        return true;
    }

    /**
     * List every direct child folder that validates as a Laravel project.
     *
     * @param  string  $parent_Path  e.g. "C:/Users/o/.vscode/react"
     * @return array  e.g. [["name" => "ecommerce", "root_path" => "C:/Users/o/.vscode/react/ecommerce"]]
     */
    private function scan_Laravel_Projects(string $parent_Path): array
    {
        $projects = [];

        foreach (scandir($parent_Path) as $entry_Name) {
            if ($entry_Name === '.' || $entry_Name === '..') continue;

            $entry_Path = $parent_Path . '/' . $entry_Name;
            if (!$this->is_Laravel_Project($entry_Path)) continue;

            $projects[] = [
                'name'      => $entry_Name,
                'root_path' => $this->normalize_Path(realpath($entry_Path)),
            ];
        }

        return $projects;
    }

    /**
     * Append targets stored in 3_M-Config.json so a scan never drops history.
     *
     * @param  array  $scanned_Projects  e.g. 
     * * [
     * * * [
     * *        "name" => "ecommerce", 
     * *        "root_path" => "C:/Users/o/.vscode/react/ecommerce"
     * * * ],
     * * * [
     * *        "name" => "m-project", 
     * *        "root_path" => "C:/Users/o/.vscode/react/m-project"
     * * * ],
     * * ]
     * @return array  Same shape plus saved ones, e.g. 
     * * [
     * * * [
     * *        "name" => "ecommerce", 
     * *        "root_path" => "C:/Users/o/.vscode/react/ecommerce"
     * * * ],
     * * * [
     * *        "name" => "m-project", 
     * *        "root_path" => "C:/Users/o/.vscode/react/m-project"
     * * * ],
     * * * [
     * *        "name" => "learn_backend", 
     * *        "root_path" => "C:/Users/o/.vscode/react/learn/learn_backend"
     * * * ],
     * * ]
     */
    private function merge_Saved_Targets(array $scanned_Projects): array
    {
        $config_Path = app_path('Constant/3_M-Config.json');
        if (!file_exists($config_Path)) return $scanned_Projects;

        $config_Data    = json_decode(file_get_contents($config_Path), true);
        $saved_Targets  = $config_Data['targets'] ?? null;
        if (!is_array($saved_Targets)) return $scanned_Projects;

        $existing_Paths = array_column($scanned_Projects, 'root_path');

        foreach ($saved_Targets as $target_Name => $target_Info) {
            $target_Path = $this->normalize_Path($target_Info['root_path'] ?? '');

            if ($target_Path === '')                        continue;
            if (in_array($target_Path, $existing_Paths))    continue;
            if (!is_dir($target_Path))                      continue;

            $scanned_Projects[] = [
                'name'      => $target_Name,
                'root_path' => $target_Path,
            ];

            $existing_Paths[] = $target_Path;
        }

        return $scanned_Projects;
    }

    /**
     * * SAVE M_value from frontend to JSON
     */
    public function save(Request $request): JsonResponse
    {
        // validate new_M_value from POST
        $request->validate([
            'tab' => 'required|string',
            'subTab' => 'required|string',
            'data' => 'present|array',      // present = acept empty data
        ]);

        $tab = $request->input('tab');
        $subTab = $request->input('subTab');
        $newData = $request->input('data');

        /**
         * Gemini said : Laravel Middleware "ConvertEmptyStringsToNull"
         * make empty string to null automatically
         * but, we don't want any null in JSON files
         * so we, need to revers null to empty string
         */
        array_walk_recursive($newData, function (&$value) {
            if ($value === null) {
                $value = "";
            }
        });

        $path = (string)$this->getPath($tab);
        $content = file_get_contents($path);
        $jsonData = json_decode($content, true);

        $jsonData[$subTab] = $newData;

        if (File::put($path, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            // answer success to Frontend 
            return response()->json(['message' => 'Metadata updated successfully ✅', 'status' => 'success']);
        }

        return response()->json(['error' => 'Failed to write file'], 500);
    }

    /**
     * * get Metadata from MSync in   app/Constant/M_JSON
     * * if files not exists,get from resources/js/Components/M_JSON
     * * ----------------------------------------------
     * * DICTIONARY:
     * * app_data: Content of App-Data.json
     * * m_data:   Content of M-Data.json
     * * entities: Content of Entities.json
     */
    public function getMetadata(): JsonResponse
    {
        $combinedMetadata = [];

        foreach ($this->getAll_JSON_FilesPath() as $key => $fullPath) {

            // if JSON files not exist here app/Constant/M_JSON
            if (!file_exists($fullPath)) {
                $dir = dirname($fullPath);
                if (!file_exists($dir)) {
                    mkdir($dir, 0755, true);
                }

                // filename from resources/js/Components/M_JSON
                $templateFilename = '';
                if ($key === 'm_data') {
                    $templateFilename = 'M-Data.json';
                } elseif ($key === 'app_data') {
                    $templateFilename = 'App-Data.json';
                } elseif ($key === 'entities') {
                    $templateFilename = 'Entities.json';
                }

                $templatePath = base_path("resources/js/Components/M_JSON/{$templateFilename}");

                if (file_exists($templatePath)) {
                    copy($templatePath, $fullPath);
                } else {
                    // Fallback case file not found
                    $defaultContent = [];
                    File::put($fullPath, json_encode($defaultContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }

            $content = file_get_contents($fullPath);
            $jsonData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => "Invalid JSON in {$key}: " . json_last_error_msg()], 500);
            }

            $combinedMetadata[$key] = $jsonData;
        }

        return response()->json($combinedMetadata);
    }
}
