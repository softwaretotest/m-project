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
    public static function printBox(string $short_text): string
    {
        $line = str_repeat("=", mb_strlen($short_text) + 2);

        $output = '';
        $output .= "\n";
        $output .= "╔{$line}╗\n";
        $output .= "║ {$short_text} ║\n";
        $output .= "╚{$line}╝\n";
        $output .= "\n";

        // ให้ echo เฉพาะตอนที่รันบน CLI เท่านั้น
        if (php_sapi_name() === 'cli') {
            echo $output;
        }

        return $output;
    }

    /**
     * With Emoji
     * @param $short_text = one line should be about 20 chars less than screen width
     * $output .=  e.g.
     * ```text
     * ┌===============================================┐
     * │  [ ✅😊😘🩷 ] NO_old_migration_file = FALSE │
     * └===============================================┘
     * ```
     */
    public static function printBox_Emoji(string $short_text): string
    {
        //calculate width for Emoji
        $width = mb_strwidth($short_text, 'UTF-8');
        $line = str_repeat("=", $width + 3);

        $output = '';
        $output .=  "\n";
        $output .=  "┌{$line}┐\n";
        $output .=  "│ {$short_text} │\n";
        $output .=  "└{$line}┘\n";
        $output .=  "\n";

        if (php_sapi_name() === 'cli') {
            echo $output;
        }

        return $output;
    }
}
