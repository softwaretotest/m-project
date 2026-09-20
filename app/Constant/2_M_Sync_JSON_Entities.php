<?php

namespace App\Constant;

class M_Sync_JSON_Entities
{
    public static function generate(): void
    {
        $jsonFilePath = __DIR__ . '/M_JSON/Entities.json';
        if (!file_exists($jsonFilePath)) {
            echo "[ 🚫 ERROR] Entities.json not found.\n";
            return;
        }

        $json = json_decode(file_get_contents($jsonFilePath), true);
        if (!isset($json['entities']) || !is_array($json['entities'])) {
            echo "[ 🚫 ERROR] Invalid entities JSON structure.\n";
            return;
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


            self::add_folder_Entities();

            // save Entity 
            file_put_contents(__DIR__ . '/' . $fileName, $code);
            echo "[ ✅ ] {$fileName} generated successfully.\n";
        }
    }

    /**
     * add folder app/Constant/Entities if not exist
     */
    private static function add_folder_Entities(): void
    {
        $folderPath = __DIR__ . '/Entities';

        if (!is_dir($folderPath)) {
            /**
             * * save mode 0755 
             * * true = recursive = add mother folder if neccessary
             */
            mkdir($folderPath, 0755, true);
            echo "[ 📁 ] Created directory: Entities\n";
        }
    }
}
