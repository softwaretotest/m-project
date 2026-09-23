<?php

namespace App\Constant;

class Checker
{
    private static array $sameFields = [];

    public static function checkDuplicate(): bool
    {
        $classes = ['f', 's', 'd', 'u', 'cd', 'cu', 'cud', 't'];
        $errors = [];

        foreach ($classes as $className) {
            $fullClassName = 'App\\Constant\\' . $className;
            if (!class_exists($fullClassName)) continue;

            $errors = array_merge($errors, self::validateConstant($fullClassName));
        }

        if (!empty($errors)) {
            self::dieReportErrors($errors);
            return false;
        }

        return true;
    }

    private static function validateConstant(string $fullClassName): array
    {
        $localErrors = [];
        $constants = (new \ReflectionClass($fullClassName))->getConstants();

        foreach ($constants as $varname => $field) {
            $field = is_array($field) ? $field : [$field];
            $source = "{$fullClassName}::{$varname}";

            // 1. check field name
            if (!isset($field[0])) {
                $localErrors[] = "[Field Name] Missing name definition in [{$source}]";
            }

            $name = $field[0] ?? 'UNKNOWN';

            // 2. check duplicate field name
            if (isset($samefields[$name])) {
                $localErrors[] = "[Field Name] Duplicate '{$name}' found in [{$samefields[$name]}] and [{$source}]";
            }
            $samefields[$name] = $source;

            // 3. check duplicate constraints
            $localErrors = array_merge($localErrors, self::checkConstraints(array_slice($field, 1), $source));
        }
        return $localErrors;
    }

    private static function checkConstraints($constraints, $source): array
    {
        if (count($constraints) <= 1) {
            return [];
        }

        $constraintErrors = [];
        $sameConstraints = [];

        foreach ($constraints as $c) {
            /**
             * get constraint name
             * cause some are string,
             * but, some are array e.g. ['default', 0]
             */
            $cName = is_array($c) ? $c[0] : $c;

            // check if we have same $cName 
            if (isset($sameConstraints[$cName])) {
                $constraintErrors[] = "[Constraint] Duplicate '{$cName}' found in field at [{$source}]";
            }

            /**
             * marked as checked , 
             * e.g. Array ( [nullable] => 1  , [unique]   => 1 )
             * to use this array in if-isset next loop
             */
            $sameConstraints[$cName] = true;
        }

        return $constraintErrors;
    }

    private static function dieReportErrors(array $errors): void
    {
        if (!empty($errors)) {
            Logger::error("SYSTEM VALIDATION FAILED!\n" . implode("\n", $errors));
        } else {
            Logger::success("--- Global Validator Passed! ---");
        }
    }
}
