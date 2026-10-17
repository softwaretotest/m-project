<?php

namespace App\Constant;

class Echo_
{
    /**
     * @param $short_text = one line should be about 20 chars less than screen width
     * echo e.g.
     * ```text
     *╔==========╗
     *║  orders  ║
     *╚==========╝
     * ```
     */
    public static function printBox(string $short_text): void
    {
        $line = str_repeat("=", mb_strlen($short_text) + 4);

        echo "\n";
        echo "╔{$line}╗\n";
        echo "║  {$short_text}  ║\n";
        echo "╚{$line}╝\n";
        echo "\n";
    }
}
