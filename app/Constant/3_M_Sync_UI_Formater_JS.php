<?php

namespace App\Constant;

require __DIR__ . '/../../vendor/autoload.php';

class M_Sync_UI_Formater_JS
{
    private static string  $target_base = '';

    /**
     * @return void
     */
    public static function copy_JS_files(): void
    {
        self::$target_base = dirname(__DIR__, 3).'/'. TargetManager::get_activeTarget();

        if (!file_exists(self::$target_base)) {
            Logger::error("Target App found : ".self::$target_base);
            return;
        }

        $json_path = self::$target_base . '/app/Constant/M_JSON/App-Data.json';

        if (!file_exists($json_path)) {
            Logger::error("App-Data.json not found at: {$json_path}");
            return;
        }

        $json_content = file_get_contents($json_path);
        preg_match_all('/uf::([A-Z_]+)/', $json_content, $matches);

        if (empty($matches[1])) {
            Logger::warning("Utility JS sync skipped (no utility fields found).");
        } else {
            self::execute_copy_JS($matches);
        }

        Logger::finish();
    }

    private static function execute_copy_JS(array $matches): void
    {
        $found_uf = array_unique($matches[1]);
        $source_dir = __DIR__ . '/JS/';
        DataHelper::ensureDir($source_dir);

        $target_dir = rtrim(self::$target_base, '/\\') . '/resources/js/utils/';
        DataHelper::ensureDir($target_dir);

        $copied_count = 0;

        foreach ($found_uf as $uf_name) {
            $filename = $uf_name . '.js';
            $source_file = $source_dir . $filename;
            $target_file = $target_dir . $filename;

            if (!file_exists($source_file)) {
                Logger::error("Utility JS file missing in source: {$filename}");
                continue;
            }

            DataHelper::ensureDir($target_dir);

            if (file_exists($target_file)) {
                M_Historizer::move_old_file_to_history($target_file);
                Logger::warning("Existing utility JS overwritten: {$filename}");
            } else {
                Logger::success("New utility JS copied: {$filename}");
            }

            copy($source_file, $target_file);
            $copied_count++;
        }

        if ($copied_count > 0) {
            Logger::success("Total utility JS synchronized: {$copied_count} file(s).");
        }
    }
}

\App\Constant\M_Sync_UI_Formater_JS::copy_JS_files();
