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
        $line = str_repeat("=", mb_strlen($short_text) + 2);
        self::write_output($line, $short_text);

    }

    /**
     * With Emoji
     * @param $short_text = one line should be about 20 chars less than screen width
     * $output .=  e.g.
     * ```text
     * ┌===============================================┐
     * │  [ ✅😊😘🩷 ] text text text text text text │
     * └===============================================┘
     * ```
     */
    public static function printBox_Emoji(string $short_text): void
    {
        //calculate width for Emoji
        $width = mb_strwidth($short_text, 'UTF-8');
        $line = str_repeat("=", $width + 3);
        self::write_output($line, $short_text);
    }

    private static function write_output(string $line, string $short_text): string
    {
        $output = '';
        $output .=  "\n";
        $output .=  "┌{$line}┐\n";
        $output .=  "│ {$short_text} │\n";
        $output .=  "└{$line}┘\n";
        $output .=  "\n";

        // echo only if run single script in vscode terminal
        if (php_sapi_name() === 'cli') {
            echo $output;
        }

        return $output;
    }
}
