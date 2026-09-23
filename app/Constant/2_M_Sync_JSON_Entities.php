<?php

namespace App\Constant;

class M_Sync_JSON_Entities
{
    public static function generate(): void
    {
        $jsonFilePath = dirname(__DIR__, 3) . '/' . TargetManager::$activeTarget . '/app/Constant/M_JSON/Entities.json';

        if (!file_exists($jsonFilePath)) {
            Logger::error("File not found : $jsonFilePath");
        }

        $json = json_decode(file_get_contents($jsonFilePath), true);
        if (!isset($json['entities']) || !is_array($json['entities'])) {
            Logger::error("Invalid entities JSON structure.");
        }

        // Loop Entity in JSON (e.g. ORDERS, PRODUCTS, etc.)
        foreach ($json['entities'] as $entityName => $fields) {
            // change ENTITIES to Singular and make Capitalization (e.g. ORDERS -> Order)
            $singularName = rtrim($entityName, 'S');
            $className = ucfirst(strtolower($singularName)) . 'Constant';
            $fileName = $className . '.php';

            $code = "<?php\n\nnamespace App\Constant;\n\n";
            $code .= "class {$className}\n{\n";
            $code .= "    public const TABLE_NAME = t::{$entityName};\n\n";
            $code .= "    public static function fields(): array\n    {\n";
            $code .= "        return [\n";

            foreach ($fields as $field) {
                // if in JSON written in string e.g "f::ORDER_NR" , then comma at the end if it
                $code .= "            {$field},\n";
            }

            $code .= "        ];\n";
            $code .= "    }\n";
            $code .= "}\n";

            // save Entity 
            $result = file_put_contents(M_Sync_JSON::$target_Constant_Path . '/' . $fileName, $code);
            if ($result)
                Logger::success("{$fileName} generated successfully.");
            else
                Logger::error("Could not generate : {$fileName}");
        }
    }
}
