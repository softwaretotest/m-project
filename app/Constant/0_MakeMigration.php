<?php

namespace App\Constant;

class MakeMigration
{
    public static array $dbSchema = [];

    /**
     * Entry point to trigger migration analysis or creation.
     */
    public static function run(string $tableName, array $schema): void
    {
        echo "--- Maker: Get DB schema for {$tableName} ---\n\n";

        self::$dbSchema = [];
        foreach ($schema as $fieldName => $data) {
            self::$dbSchema[$fieldName] = $data['db'];
        }

        Echo_::printBox("--- Maker: Starting Migration Generation for [{$tableName}] ---");

        // Search for the migration file dynamically based on tableName
        $pattern = (string) TargetManager::gen_path(
            "migrations/*_{$tableName}_table.php",
            'database'
        );

        $files = glob($pattern);

        /**
         * * * SPECIAL CASE users table in Laravel :
         * * mode DEV :
         * * xxxxxxxxxx
         * * we must remove old *_create_{$tableName}_table.php  , before continue
         * * xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
         * * mode PROD :
         * * xxxxxxxxxx
         * * we do not update existing field , but
         * 1. use stub for PROD
         * * 0_add_new_fields_to_orders_table.php
         * 2. checkDuplicate fields first
         * * if duplicate field -> Logger::error
         * * else add only new field in 0_add_new_fields_to_orders_table.php
         */
        if (!empty($files)
            && Runner::$mode === 'DEV'
        ) {
            // delete *_{$tableName}_table.php
            // delete file from system like rm of CMD
            @unlink($files[0]);
            echo "--- Maker: Removed old migration [" . basename($files[0]) . "] ---\n\n";
        }

        if (Runner::$mode === 'DEV') {
            Echo_::printBox(" ----------- [ DEV ] mode selected ----------- ");
            echo "--- Maker: Preparing to CREATE NEW TABLE [{$tableName}]. ---\n\n";
            MigrationFile::createNew($tableName);
        }

        if (Runner::$mode === 'PROD') {
            Echo_::printBox(" ----------- [ PROD ] mode selected ----------- ");
            echo "--- Maker: Preparing to ADD NEW FIELDS for [{$tableName}]. ---\n\n";
            MigrationFile::add_new_Field($tableName);
        }
    }
}
