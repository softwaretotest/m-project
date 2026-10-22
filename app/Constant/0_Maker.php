<?php

namespace App\Constant;

//0_Maker.php

/**
 * 1. do validations :
 *      1.1 check if Class $entity exists
 *      1.2 let Class Checker validateConstant check duplication of fieldnames and constraits
 * 2. make a singleton of MakeMigration Class
 * 3. call MakeSchema to make dbSchema
 * 4. send dbSchema to MakeMigration
 */
class Maker
{
    public static string $tableName;

    /**
     * Singleton instance of MakeMigration.
     * * PURPOSE:
     * - Serves as a persistent state coordinator for the migration runner.
     * - Allows MigrationFile::createNew() to access the global Runner::$entityCounter.
     * * WHY SINGLETON:
     * - Without this, the counter state would be lost between cycles.
     * - Enables sequential filename generation (e.g., ..._01_create_shops_table.php).
     *  ? = nullable (> PHP 8.2))
     *  in PHP , static object can be instantiated (not in Java/.NET)
     */
    private static ?MakeMigration $makeMigration = null;

    /**
     * @param string $entity = e.g. App\Constant\UserConstant
     */
    public static function run(string $entity): void
    {
        $schema = [];
        if (!Checker::checkDuplicate()) {
            echo "--- Maker: Aborted due to validation errors! ---\n";
        }

        $tableName = self::checkTableName($entity);
        if (!$tableName) {
            return;
        }

        // need value for MakMigration::get_fieldname_from_migration_file()
        // because this is early initialize than in MigrationFile::set_config()
        self::$tableName = $entity::TABLE_NAME;

        Echo_::printBox($tableName);

        foreach ($entity::fields() as $field) {

            /**
             * array_shift()
             * move the first array element to $name variable
             * and remove it from array
             * the first element is the field name
             * according to M-Project convention
             * a field (business field) is always and array) e.g.
             class ProductConstant
            {
                public const TABLE_NAME = t::PRODUCTS;

                 public static function fields(): array
                {
                    return [
                        f::NAME,
                        f::IMAGE,
                        f::SHOP_ID,
                        f::PRICE,
                        f::STOCK,
                    ];
                }
            .....

             * Example:
            class f
            {
                public const IMAGE = ['image', d::STRING, u::FILE ];
            }
             */
            $name = array_shift($field);

            $schema[$name] = MakeSchema::separate_db_ui($name, $field);
        }
        echo "--------------------------------------\n";

        if (empty($schema)) {
            Logger::error("No fields defined or analysis failed in [{$entity}].");
        }

        /**
         * Initialize the MakeMigration to make Singleton
         */
        if (self::$makeMigration === null) {
            self::$makeMigration = new MakeMigration();
        }

        self::$makeMigration->run($schema);
    }

    private static function checkTableName(string $entity): ?string
    {
        if (!class_exists($entity)) {
            echo "Error: Class {$entity} does not exist.\n";
            return null;
        }

        $reflection = new \ReflectionClass($entity);

        if (!$reflection->hasConstant('TABLE_NAME')) {
            echo "Error: {$entity} is missing 'TABLE_NAME' constant.\n";
            return null;
        }

        $tableName = $entity::TABLE_NAME;
        if (empty($tableName)) {
            echo "Error: 'TABLE_NAME' in {$entity} cannot be empty.\n";
            return null;
        }

        return $tableName;
    }
}
