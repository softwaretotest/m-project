<?php

namespace App\Constant;
// 1_Constant_M_APP_to_JSON.php

require __DIR__ . '/../../vendor/autoload.php';

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;

class Constant_M_APP_to_JSON extends NodeVisitorAbstract
{
    public $data = [];
    private $currentClassName = '';

    /**
     * 
     */
    public function enterNode($node)
    {
        // 1. Detect Class
        // e.g., class OrderConstant { ... } -> 'OrderConstant'
        if ($node instanceof Class_) {
            $this->currentClassName = $node->name->toString();
            $this->data[$this->currentClassName] = [];
        }

        // 2. Process Constants if inside a class
        // e.g., const TABLE_NAME = 'orders';
        if ($node instanceof ClassConst && !empty($this->currentClassName)) {
            foreach ($node->consts as $const) {
                $name = $const->name->toString();
                $value = $this->resolveValue($const->value);

                // Assign to class data
                $this->data[$this->currentClassName][$name] = $value;
            }
        }
    }

    /**
     * * DICTIONARY:
     * * AST  = Code structure from *Constant.php files (e.g., class f, class d)
     * * NODE = Specific element (e.g., Array_, String_, ClassConstFetch)
     * * EXAMPLES (Mapped from your project):
     * - String_ ('text')         -> "text" (from class u)
     * - Array_  (['image', d::STRING, u::FILE]) 
     * -> ["image", "string", "file"] (from class f)
     * - ClassConstFetch (d::STRING) -> "d::STRING" (from class f)
     * @param $node 
     * 
     * * object(PhpParser\Node\Scalar\String_)#807 (2) {
     * *   ["attributes":protected]=>
     * *   array(8) {
     * *     ["startLine"]=>
     * *     int(7)
     * *     ["startTokenPos"]=>
     * *     int(21)
     * *     ["startFilePos"]=>
     * *     int(69)
     * *     ["endLine"]=>
     * *     int(7)
     * *     ["endTokenPos"]=>
     * *     int(21)
     * *     ["endFilePos"]=>
     * *     int(77)
     * *     ["kind"]=>
     * *     int(1)
     * *     ["rawValue"]=>
     * *     string(9) "'boolean'"
     * *   }
     * *   ["value"]=>
     * *   string(7) "boolean"
     * * }
     * @return 
     * * {$class}::{$const} = e.g. d::STRING 
     * 
     * * $node->value 
     * * CASE String_ = e.g string(6) name
     * * CASE LNumber = e.g string(5)  255
     */
    private function resolveValue($node)
    {
        // String_: e.g. string(7) "boolean"
        if ($node instanceof \PhpParser\Node\Scalar\String_) {
            return $node->value;
        }

        // LNumber: e.g. int(255)
        if ($node instanceof \PhpParser\Node\Scalar\LNumber) {
            return $node->value;
        }

        // Array_: e.g., ['price', [d::DECIMAL, 10, 2], u::NUMBER, ...]
        if ($node instanceof \PhpParser\Node\Expr\Array_) {
            $arr = [];
            foreach ($node->items as $item) {
                $arr[] = $this->resolveValue($item->value);
            }
            return $arr;
        }

        // convert true / false to JSON e.g. [cd::DEFAULT, false]
        if ($node instanceof \PhpParser\Node\Expr\ConstFetch) {
            $name = $node->name->toString();
            if ($name === 'true') {
                return true;
            }
            if ($name === 'false') {
                return false;
            }
        }

        // ClassConstFetch: e.g., d::STRING, u::FILE
        if ($node instanceof \PhpParser\Node\Expr\ClassConstFetch) {
            $class = $node->class->toString();
            $const = $node->name->toString();
            return "{$class}::{$const}";
        }

        return null;
    }
}
