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

    /**
     * With Emoji
     * @param $short_text = one line should be about 20 chars less than screen width
     * echo e.g.
     * ```text
     * ┌===============================================┐
     * │  [ ✅😊😘🩷 ] NO_old_migration_file = FALSE │
     * └===============================================┘
     * ```
     */
    public static function printBox_Emoji(string $short_text): void
    {
        //calculate width for Emoji
        $width = mb_strwidth($short_text, 'UTF-8');
        $line = str_repeat("=", $width + 4);

        echo "\n";
        echo "┌{$line}┐\n";
        echo "│ {$short_text} │\n";
        echo "└{$line}┘\n";
        echo "\n";
    }
}
