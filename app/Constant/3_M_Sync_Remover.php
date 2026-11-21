<?php

namespace App\Constant;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class M_Sync_Remover
{
    public function remove_entity(Request $request)
    {
        $oldKey = $request->input('old_key');
        $archiveHistory = (bool) $request->input('archive_history', true);
        $targets = $request->input('targets', [
            'constant',
            'dto',
            'controller',
            'model',
            'migrations',
        ]);

        if (!is_string($oldKey) || trim($oldKey) === '') {
            $message = 'Invalid or missing old_key for entity removal.';
            Logger::error($message);

            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 422);
        }

        $key = trim($oldKey);
        $targetBasePath = __DIR__ . '/../../../' . TargetManager::get_activeTarget();
        $files = $this->resolveTargetFiles($targetBasePath, $key, is_array($targets) ? $targets : []);

        if ($archiveHistory && count($files) > 0) {
            $this->archiveFiles($files);
        }

        $removed = [];
        foreach ($files as $filePath) {
            if (File::exists($filePath) && File::isFile($filePath)) {
                if (File::delete($filePath)) {
                    $removed[] = $filePath;
                } else {
                    $message = "Failed to delete file: {$filePath}";
                    Logger::error($message);

                    return response()->json([
                        'ok' => false,
                        'message' => $message,
                        'removed' => $removed,
                    ], 500);
                }
            }
        }

        return response()->json([
            'ok' => true,
            'old_key' => $key,
            'removed' => $removed,
        ]);
    }

    private function resolveTargetFiles(string $targetBasePath, string $oldKey, array $targets): array
    {
        $lower = strtolower($oldKey);
        $studly = Str::studly($lower);
        $files = [];

        if (in_array('constant', $targets, true)) {
            $files[] = $targetBasePath . "/app/Constant/{$studly}Constant.php";
        }

        if (in_array('dto', $targets, true)) {
            $files[] = $targetBasePath . "/app/DTOs/{$studly}DTO.php";
        }

        if (in_array('controller', $targets, true)) {
            $files[] = $targetBasePath . "/app/Http/Controllers/{$studly}Controller.php";
        }

        if (in_array('model', $targets, true)) {
            $files[] = $targetBasePath . "/app/Models/{$studly}.php";
        }

        if (in_array('migrations', $targets, true)) {
            $migrationDir = $targetBasePath . '/database/migrations';
            if (File::isDirectory($migrationDir)) {
                $pattern = "*_create_{$lower}_table.php";
                foreach (File::glob($migrationDir . DIRECTORY_SEPARATOR . $pattern) as $matched) {
                    $files[] = $matched;
                }
            }
        }

        return array_values(array_unique($files));
    }

    private function archiveFiles(array $files): void
    {
        if (!class_exists(M_Historizer::class)) {
            return;
        }

        foreach ($files as $filePath) {
            if (File::exists($filePath) && File::isFile($filePath)) {
                M_Historizer::move_old_file_to_history($filePath, true);
            }
        }
    }
}
