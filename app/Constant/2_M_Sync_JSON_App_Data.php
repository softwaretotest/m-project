<?php

namespace App\Constant;

class M_Sync_JSON_App_Data
{
    public static function generate(): void
    {
        //get JSON Content from target app
        $filePath = dirname(__DIR__, 3) .'/'. TargetManager::get_activeTarget() . '/app/Constant/M_JSON/App-Data.json';
        DataHelper::ensureDir($filePath);

        $content = file_get_contents($filePath);
        if (false === $content) {
            Logger::error("Could not get content of file : ".$filePath);
        }

        $json = json_decode($content, true);
        if (false === $json || null === $json) {
            Logger::error('EMPTY ARRAY : '. print_r($json, true));
        }

        $code = "<?php\n\nnamespace App\Constant;\n\nclass f\n{\n";
        foreach ($json['f'] as $key => $value) {
            $formattedVal = self::format_json_value($value);
            $code .= "    public const {$key} = {$formattedVal};\n";
        }

        $code .= "}\n\nclass t\n{\n";
        foreach ($json['t'] as $key => $value) {
            $code .= "    public const {$key} = " . var_export($value, true) . ";\n";
        }
        $code .= "}\n";

        $result = file_put_contents(M_Sync_JSON::$target_Constant_Path . '/0_Constant_APP.php', $code);
        if ($result) {
            Logger::success("0_Constant_APP.php generated successfully.");
        } else {
            Logger::error("Could not generate 0_Constant_APP.php.");
        }
    }

    private static function format_json_value(mixed $json_value): string
    {
        // 1. Guard for Boolean to return true or false directly
        if (is_bool($json_value)) {
            return $json_value ? 'true' : 'false';
        }

        if (!is_array($json_value)) {
            // if constant e.g. 'd::STRING', 'u::FILE' remove '
            if (is_string($json_value) && preg_match('/^[a-z]+::[A-Z_]+$/', $json_value)) {
                return $json_value;
            }
            return var_export($json_value, true);
        }
        $items = [];
        foreach ($json_value as $val) {
            $items[] = self::format_json_value($val);
        }
        return '[' . implode(', ', $items) . ']';
    }
}
