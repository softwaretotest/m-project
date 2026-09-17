<?php

namespace App\Geners;

/**
 * make dto, model, service, controller
 * according to the entity name of Entities.json
 * we keep this in case, we change SSOT to JSON
 * Now we use SSOT from EntityConstant.php
 */
class EntityGenerator_JSON
{
    public static function runAll()
    {
        // 1. read SSOT
        $jsonPath = app_path('Constant/M_JSON/Entities.json');
        if (!file_exists($jsonPath)) {
            throw new \Exception("file not found - SSOT : {$jsonPath}");
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        $entities = $data['entities'] ?? [];

        // 2. loop through Entity
        foreach ($entities as $tableName => $fields) {
            // change TABLENAME e.g. ORDERS to Order
            $entityName = ucfirst(strtolower(rtrim($tableName, 'S')));
            self::generate($entityName);
        }
    }
    public static function generate(string $entityName)
    {
        $stubs = [
            'dto.stub'        => app_path("DTOs/{$entityName}DTO.php"),
            'model.stub'      => app_path("Models/{$entityName}.php"),
            'service.stub'    => app_path("Services/{$entityName}Service.php"),
            'controller.stub' => app_path("Http/Controllers/{$entityName}Controller.php"),
        ];

        foreach ($stubs as $stubFile => $outputPath) {
            $stubContent = file_get_contents(app_path("Geners/Stub/{$stubFile}"));

            // replace Dummy by Entity
            $content = str_replace('Dummy', $entityName, $stubContent);

            // make Directory
            if (!file_exists(dirname($outputPath))) {
                mkdir(dirname($outputPath), 0755, true);
            }

            file_put_contents($outputPath, $content);
            echo "Generated: {$outputPath}\n";
        }
    }
}
