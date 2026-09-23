<?php

namespace App\Constant;

class M_Sync_JSON_M_Data
{
    public static function generate(): void
    {
        $json = json_decode(file_get_contents(__DIR__ . '/M_JSON/M-Data.json'), true);
        $code = "<?php\n\nnamespace App\Constant;\n\n";

        foreach ($json as $section => $values) {
            // script KEY e.g. "_comment": "\/M_JSON\/*.json",
            if (str_starts_with($section, '_')) {
                continue;
            }

            $code .= "class {$section}\n{\n";
            foreach ($values as $k => $v) {
                $formattedVal = self::formatArray($v);
                $code .= "    public const {$k} = {$formattedVal};\n";
            }
            $code .= "}\n\n";
        }

        $result = file_put_contents(M_Sync_JSON::$target_Constant_Path . '/0_Constant_M.php', $code);
        if ($result)
            Logger::success("0_Constant_M.php generated successfully.");
        else
            Logger::error("Could not generate 0_Constant_M.php.");
    }

    private static function formatArray($arr): string
    {
        if (!is_array($arr)) {
            if (is_string($arr) && preg_match('/^[a-z]+::[A-Z_]+$/', $arr)) {
                return $arr;
            }
            return var_export($arr, true);
        }
        $items = [];
        foreach ($arr as $val) {
            $items[] = self::formatArray($val);
        }
        return '[' . implode(', ', $items) . ']';
    }
}
