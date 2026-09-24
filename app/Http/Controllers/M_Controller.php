<?php

namespace App\Http\Controllers;

use App\Constant\TargetManager;
use App\Constant\Logger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class M_Controller extends Controller
{
    // set same path getMetadata()
    public const FILES_PATH = [
        'app_data' => 'app/Constant/M_JSON/App-Data.json',
        'm_data'   => 'app/Constant/M_JSON/M-Data.json',
        'entities' => 'app/Constant/M_JSON/Entities.json',
        'm_target' => 'app/Constant/3_M-Config.json',
    ];

    private function getPath(string $key): string
    {
        return base_path(self::FILES_PATH[$key]);
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

    public function saveTargetConfig(Request $request)
    {
        $path = str_replace('\\', '/', trim((string) $request->input('path')));

        if ($path === '') {
            return response()->json(['success' => false, 'message' => 'Path is empty'], 400);
        }

        $real = realpath($path);
        if ($real === false) {
            return response()->json(['success' => false, 'message' => "Path does not exist: {$path}"], 422);
        }
        $real = str_replace('\\', '/', $real);

        if (!file_exists($real . '/artisan')) {
            return response()->json(['success' => false, 'message' => 'Not a Laravel project (artisan not found)'], 422);
        }

        $name       = basename($real);
        $configPath = base_path('app/Constant/3_M-Config.json');

        // อ่านของเดิมมาก่อน แล้วค่อย merge (ไม่ทับทิ้ง)
        $config = ['activeTarget' => '', 'targets' => []];
        if (file_exists($configPath)) {
            $old = json_decode(file_get_contents($configPath), true);
            if (is_array($old)) {
                $config['targets']      = $old['targets'] ?? [];
                $config['activeTarget'] = $old['activeTarget'] ?? '';
            }
        }

        $config['targets'][$name] = ['root_path' => $real];
        $config['activeTarget']   = $name;

        $written = file_put_contents(
            $configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($written === false) {
            return response()->json(['success' => false, 'message' => 'Cannot write config file'], 500);
        }

        return response()->json(['success' => true, 'target' => $name, 'root_path' => $real]);
    }

    public function scanTargets(Request $request)
    {
        // default = โฟลเดอร์แม่ของ m-project (เช่น C:/Users/o/.vscode/react)
        $base = $request->input('base') ?: dirname(base_path());
        $base = str_replace('\\', '/', $base);

        if (!is_dir($base)) {
            return response()->json(['success' => false, 'message' => "Base not found: {$base}"], 404);
        }

        $projects = [];
        foreach (scandir($base) as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            $full = $base . '/' . $entry;
            if (!is_dir($full)) continue;
            if (!file_exists($full . '/artisan')) continue;          // ← ตัวชี้ขาดว่าเป็น Laravel
            if (!file_exists($full . '/composer.json')) continue;

            $projects[] = [
                'name'      => $entry,
                'root_path' => str_replace('\\', '/', realpath($full)),
            ];
        }

        return response()->json([
            'success'  => true,
            'base'     => $base,
            'projects' => $projects,
        ]);
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

        $path = $this->getPath($tab);
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

        foreach (self::FILES_PATH as $key => $path) {
            $fullPath = base_path($path);

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
