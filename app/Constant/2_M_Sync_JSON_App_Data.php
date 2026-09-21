<?php

namespace App\Constant;

class M_Sync_JSON_App_Data
{
    public static function generate(): void
    {
        $json = json_decode(file_get_contents(__DIR__ . '/M_JSON/App-Data.json'), true);

        $code = "<?php\n\nnamespace App\Constant;\n\nclass f\n{\n";
        foreach ($json['f'] as $k => $v) {
            $formattedVal = self::formatArray($v);
            $code .= "    public const {$k} = {$formattedVal};\n";
        }

        $code .= "}\n\nclass t\n{\n";
        foreach ($json['t'] as $k => $v) {
            $code .= "    public const {$k} = " . var_export($v, true) . ";\n";
        }
        $code .= "}\n";

        file_put_contents(M_Sync_JSON::$target_Constant_Path . '/0_Constant_APP.php', $code);
        echo "[ ✅ ] 0_Constant_APP.php generated successfully.\n";
    }

    private static function formatArray($arr): string
    {
        if (!is_array($arr)) {
            // if constant e.g. 'd::STRING', 'u::FILE' remove ' 
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
