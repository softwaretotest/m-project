<?php

namespace App\Http\Controllers;

use App\Constant\DataHelper;
use App\Constant\TargetManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class M_Controller extends Controller
{
    /**
     * get path by key
     * @param $key = e.g. app_data , m_data , entities
     * @return e.g. c:\Users\o\.vscode\react\ecommerce\app\Constant\M_JSON\Entities.json
     */
    private function getPath(string $key): string
    {
        $files = $this->get_Target_JSON_FilePath();
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
     * * SAVE M_value from frontend to JSON
     */
    public function save_M_value(Request $request): JsonResponse
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
     * @return array e.g.
     * * C:\Users\o\.vscode\react\ecommerce\app\Constant\M_JSON\Entities.json
     * * C:\Users\o\.vscode\react\ecommerce\app\Constant\M_JSON\App-Data.json
     * * C:\Users\o\.vscode\react\ecommerce\app\Constant\M_JSON\M-Data.json
     */
    private function get_Target_JSON_FilePath(): array
    {
        return [
            'app_data' => TargetManager::gen_path('Constant/M_JSON/App-Data.json', 'app'),
            'm_data'   => TargetManager::gen_path('Constant/M_JSON/M-Data.json', 'app'),
            'entities' => TargetManager::gen_path('Constant/M_JSON/Entities.json', 'app'),
        ];
    }

    private const TEMPLATE_FILES = [
        'm_data'   => 'M-Data.json',
        'app_data' => 'App-Data.json',
        'entities' => 'Entities.json',
    ];

    public function get_M_value(): JsonResponse
    {
        $combinedMetadata = [];

        try {
            foreach ($this->get_Target_JSON_FilePath() as $key => $targetPath) {
                $this->ensureTargetFile($key, $targetPath);
                $combinedMetadata[$key] = $this->readJsonFile($targetPath);
            }
        } catch (\RuntimeException $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json($combinedMetadata);
    }

    /**
     * * make file at target from template , if file at target not exists
     * * if file exists do not overwrite
     */
    private function ensureTargetFile(string $key, string $targetPath): void
    {
        if (file_exists($targetPath)) {
            return;
        }

        DataHelper::ensureDir($targetPath);
        $sourcePath = base_path('app/Constant/M_JSON_Example/' . self::TEMPLATE_FILES[$key]);

        if (!file_exists($sourcePath)) {
            throw new \RuntimeException("Template file does not exist: {$sourcePath}");
        }
        if (!copy($sourcePath, $targetPath)) {
            throw new \RuntimeException("Could not copy from {$sourcePath} to {$targetPath}");
        }
    }

    /**
     * * read and decode JSON from path (
     * * alway read file from target)
     */
    private function readJsonFile(string $path): array
    {
        $jsonData = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in ' . basename($path) . ': ' . json_last_error_msg());
        }

        return $jsonData ?? [];
    }

    public function copyJSON(Request $request): JsonResponse
    {
        $type = $request->input('type'); // get 'app_data' or 'entities'
        $targetPaths = $this->get_Target_JSON_FilePath();

        if (!isset($targetPaths[$type])) {
            return response()->json(['error' => 'Invalid type : '.$type], 400);
        }

        $targetPath = $targetPaths[$type];

        // define name of JSON
        $exampleFileName = $type === 'app_data' ? 'Example_App-Data.json' : 'Example_Entities.json';
        $sourcePath = base_path("app/Constant/M_JSON_Example/{$exampleFileName}");

        if (!file_exists($sourcePath)) {
            return response()->json(['error' => 'Example file not found : '.$sourcePath], 404);
        }

        DataHelper::ensureDir($targetPath);
        copy($sourcePath, $targetPath);

        return response()->json(['success' => true]);
    }

    /**
     * Retrieve example JSON content for frontend validation prior to copying.
     *
     * @param Request $request
     * @return JsonResponse e.g.
     *   {
     *     _comment: String,
     *     f: Array,
     *     s: Array,
     *     entities: Array
     *   }
     */
    public function get_Example_JSON(Request $request): JsonResponse
    {
        $request_type = $request->input('type');
        $target_path_map = $this->get_Target_JSON_FilePath();

        if (!isset($target_path_map[$request_type])) {
            return response()->json(['error' => 'Invalid type specified: ' . $request_type], 400);
        }

        $example_file_name = $request_type === 'app_data' ? 'Example_App-Data.json' : 'Example_Entities.json';
        $source_file_path = base_path("app/Constant/M_JSON_Example/{$example_file_name}");

        if (!file_exists($source_file_path)) {
            return response()->json(['error' => 'Example file not found: ' . $source_file_path], 404);
        }

        $raw_file_content = file_get_contents($source_file_path);
        $parsed_json_payload = json_decode($raw_file_content, true);    // remove same key , keep the latest duplicated - item[key]

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Invalid JSON structure in example file'], 500);
        }
        // Log::info("get_Example_JSON - return : ".print_r($parsed_json_payload, true));
        return response()->json($parsed_json_payload);
    }

    /**
     * Safely merge example JSON data into the existing target JSON file without overwriting.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function mergeAndSaveJSON(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $targetPaths = $this->get_Target_JSON_FilePath();

        if (!isset($targetPaths[$type])) {
            return response()->json(['error' => 'Invalid type specified'], 400);
        }

        $targetPath = $targetPaths[$type];
        $exampleFileName = $type === 'app_data' ? 'Example_App-Data.json' : 'Example_Entities.json';
        $sourcePath = base_path("app/Constant/M_JSON_Example/{$exampleFileName}");

        if (!file_exists($sourcePath)) {
            return response()->json(['error' => 'Example file not found'], 404);
        }

        $existingData = [];
        if (file_exists($targetPath)) {
            $existingData = json_decode(file_get_contents($targetPath), true) ?? [];
        }

        $sourceData = json_decode(file_get_contents($sourcePath), true) ?? [];

        if ($type === 'app_data') {
            $existingData['f'] = array_merge($existingData['f'] ?? [], $sourceData['f'] ?? []);
            $existingData['s'] = array_merge($existingData['s'] ?? [], $sourceData['s'] ?? []);
        } else {
            foreach ($sourceData['entities'] ?? [] as $tableName => $fields) {
                if (!isset($existingData['entities'][$tableName])) {
                    $existingData['entities'][$tableName] = [];
                }
                $existingData['entities'][$tableName] = array_values(array_unique(
                    array_merge($existingData['entities'][$tableName], $fields)
                ));
            }
        }

        DataHelper::ensureDir($targetPath);
        file_put_contents($targetPath, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json(['success' => true]);
    }

}
