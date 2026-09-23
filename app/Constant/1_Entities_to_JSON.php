<?php

namespace App\Constant;
// Entities_to_JSON.php

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Array_;

class Entities_to_JSON extends NodeVisitorAbstract
{
    public $entities = [];

    /**
     * Traverses each node to extract metadata.
     * 1. Extracts TABLE_NAME (e.g., const TABLE_NAME = 'users';)
     * 2. Extracts fields 
     * e.g., 
            f::NAME,
            f::IMAGE,
     * 3. Save found value to $entities , e.g. 
     * *    $tableName , 
     * *    $fields of the table , 
     * * by calling function $fields

    !!!! $node->stmts = Statement !!!!

    class UserConstant {

            const TABLE_NAME = 'users'; // [Statement 1]
        
            public static function fields() { // [Statement 2]
        
            return [
                f::NAME,
                f::IMAGE,
            ];
        }
    }
     *@param $node
     *xxxxxx top part of $node
     ["name"]=>
      object(PhpParser\Node\Identifier)#267 (2) {
        ["attributes":protected]=>
        array(6) {
          ["startLine"]=>
          int(5)
          ["startTokenPos"]=>
          int(9)
          ["startFilePos"]=>
          int(38)
          ["endLine"]=>
          int(5)
          ["endTokenPos"]=>
          int(9)
          ["endFilePos"]=>
          int(50)
        }
        ["name"]=>
        string(13) "OrderConstant"
      }
     *xxxxxx bottom part of $node
     * $node has very long content
     */
    public function enterNode($node)
    {
        if ($node instanceof Class_ && str_ends_with($node->name->toString(), 'Constant')) {
            $tableName = null;
            $fields = [];

            foreach ($node->stmts as $stmt) {
                // Extract TABLE_NAME
                if ($stmt instanceof ClassConst) {
                    foreach ($stmt->consts as $const) {
                        if ($const->name->toString() === 'TABLE_NAME') {
                            $tableName = $this->resolveValue($const->value);
                        }
                    }
                }

                // Extract fields from function fields() method
                if ($stmt instanceof ClassMethod && $stmt->name->toString() === 'fields') {
                    foreach ($stmt->stmts as $subStmt) {
                        if ($subStmt instanceof \PhpParser\Node\Stmt\Return_) {
                            $fields = $this->resolveValue($subStmt->expr);
                        }
                    }
                }
            }

            if ($tableName) {
                $cleanTableName = str_replace('t::', '', $tableName);
                $this->entities[$cleanTableName] = $fields;
            }
        }
    }

    /**
     * * DICTIONARY:
     * * AST  = Code structure from *Constant.php files parsed by PhpParser
     * * NODE = A specific element within the AST
     *@param $node
     * *object(PhpParser\Node\Expr\ClassConstFetch)#270 (3) {
     * *  ["attributes":protected]=>
     * *  array(6) {
     * *    ["startLine"]=>
     * *    int(7)
     * *    ["startTokenPos"]=>
     * *    int(21)
     * *    ["startFilePos"]=>
     * *    int(84)
     * *    ["endLine"]=>
     * *    int(7)
     * *    ["endTokenPos"]=>
     * *    int(23)
     * *    ["endFilePos"]=>
     * *    int(92)
     * *  }
     * *  ["class"]=>
     * *  object(PhpParser\Node\Name)#268 (2) {
     * *    ["attributes":protected]=>
     * *    array(6) {
     * *      ["startLine"]=>
     * *      int(7)
     * *      ["startTokenPos"]=>
     * *      int(21)
     * *      ["startFilePos"]=>
     * *      int(84)
     * *      ["endLine"]=>
     * *      int(7)
     * *      ["endTokenPos"]=>
     * *      int(21)
     * *      ["endFilePos"]=>
     * *      int(84)
     * *    }
     * *    ["name"]=>
     * *    string(1) "t"
     * *  }
     * *  ["name"]=>
     * *  object(PhpParser\Node\Identifier)#269 (2) {
     * *    ["attributes":protected]=>
     * *    array(6) {
     * *      ["startLine"]=>
     * *      int(7)
     * *      ["startTokenPos"]=>
     * *      int(23)
     * *      ["startFilePos"]=>
     * *      int(87)
     * *      ["endLine"]=>
     * *      int(7)
     * *      ["endTokenPos"]=>
     * *      int(23)
     * *      ["endFilePos"]=>
     * *      int(92)
     * *    }
     * *    ["name"]=>
     * *    string(6) "ORDERS"
     * *  }
     * *}
     * @return t::ORDER
     */
    private function resolveValue($node)
    {
        if ($node instanceof Array_) {
            $arr = [];
            foreach ($node->items as $item) {
                $arr[] = $this->resolveValue($item->value);
            }
            return $arr;
        }

        if ($node instanceof ClassConstFetch) {
            // e.g. t::USER_ID -> "t::USER_ID"
            $className = $node->class->toString();
            $constName = $node->name->toString();
            return "{$className}::{$constName}";
        }

        return null;
    }
}
