<?php

namespace App\Http\Controllers;

use App\Constant\TargetManager;
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

    public function get_M_Config_json(Request $request): JsonResponse
    {
        // 1. สั่งให้โหลด Config (ถ้าไม่มีไฟล์ มันจะสร้างให้ตามที่เราเขียนไว้)
        TargetManager::loadConfig();

        // 2. ดึงค่าจาก TargetManager ที่เป็น Array อยู่แล้ว ส่งให้ response()->json() จัดการ
        return response()->json([
            'activeTarget' => TargetManager::$activeTarget,
            'targets'      => TargetManager::$targets
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
