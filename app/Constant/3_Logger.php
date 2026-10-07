<?php

namespace App\Constant;

class Logger
{
    public const SUCCESS = '[ ✅ SUCCESS ] ';
    public const WARNING = '[ ⚠️ WARNING ] ';
    public const ERROR   = '[ 🚫 ERROR   ] ';
    public const FINISH  = '[ 🆗 FINISH  ] ';

    /**
     * Centralized log message formatter.
     */
    private static function formatMessage(string $level, string $message, ?string $location = null): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $location ??= self::getCaller();
        return "[ {$timestamp} ] {$level}{$location} {$message}";
    }

    public static string $collected_message = '';

    /**
     * Centralize error details formatting.
     */
    private static function formatErrorDetails(string $message, ?\Throwable $exception): string
    {
        $exception ??= new \RuntimeException($message);
        return $message . ' | Exception: ' . get_class($exception)
            . ' | Message: ' . $exception->getMessage()
            . ' | File: ' . $exception->getFile()
            . ' | Line: ' . $exception->getLine();
    }

    /**
     * * this function is only for 3_M_Sync_Service
     * * to collect error without throw exception
     * * 3_M_Sync_Service will write $collected_message to UI at the end
     * @param string $message   Custom error message
     * @return void
     */
    public static function collect_error(?string $message): void
    {
        $exception = new \RuntimeException($message);
        $detailedMessage = self::formatErrorDetails($message, $exception);
        $formatted = self::formatMessage(self::ERROR, $detailedMessage);
        self::$collected_message .= $formatted . "\n";
    }

    /**
     * Log an error message, halt execution immediately (Fail-fast),
     * and throw an exception if none is provided.
     *
     * @param string         $message   Custom error message
     * @param \Throwable|null $exception Optional exception object
     * @return string log message to be collected
     * @throws \Throwable
     */
    public static function error(
        string $message,
        ?\Throwable $exception = null
    ): string {
        $exception ??= new \RuntimeException($message);
        $detailedMessage = self::formatErrorDetails($message, $exception);
        self::log(self::ERROR, $detailedMessage . "\n");
        throw $exception;
    }

    /**
     * Save log to target app and print log message in Terminal immediately.
     *
     * @param string $level   Log level (use constants from Logger::SUCCESS, etc.)
     * @param string $message Message to be logged
     * * [ 2026-10-03 05:27:04 ] [ ✅ SUCCESS ] [ M_Sync::syncAll ] [ 🆗 FINISH  ] [message]
     */
    public static function log(string $level, string $message): void
    {
        $formattedMessage = self::formatMessage($level, $message);

        // 1. Print to Terminal immediately
        if (php_sapi_name() === 'cli') { // condition prevent to print on browser(= ouput only for api data)
            echo $formattedMessage . "\n";
        }
        // 2. Define path file log
        $logDir = dirname(__DIR__, 3) . '/m-project_logs/';

        DataHelper::ensureDir($logDir);

        // log file name = app name
        $appName = TargetManager::peek_activeTarget() ?: 'm-project';
        $logFile = $logDir . '/' . $appName . '.log';


        self::checkAndRotateLog($logFile);

        // 3. Write to file (append with exclusive lock)
        $result = file_put_contents($logFile, $formattedMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            echo "======================================================================\n\n";
            echo "\n\n" . self::ERROR . " Logger could not save messages to : $logFile\n\n";
            echo "======================================================================\n\n";
        }
    }


    /**
     * Find the actual external Class and Method that called the Logger.
     */
    private static function getCaller(): string
    {
        // get Backtrace 10 level deeper
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        $class = 'Global';

        $method = 'main';

        // loop to find the first Class / Methode that called Logger
        foreach ($trace as $step) {
            $stepClass = $step['class'] ?? '';

            // skip lines that called inside Logger
            if ($stepClass === self::class) {
                continue;
            }

            // if found break and return
            if (!empty($stepClass)) {
                $class = (new \ReflectionClass($stepClass))->getShortName();
            }
            $method = $step['function'] ?? 'main';
            break;
        }

        return "[ {$class}::{$method} ]";
    }

    /**
     * Check log file size and rotate to history folder if it exceeds the limit.
     *
     * @param string $logFile Path to the current log file
     * @return void
     */
    private static function checkAndRotateLog(string $logFile): void
    {
        // max. file size 5MB: 5 * 1024 * 1024)
        $maxSize = 5 * 1024 * 1024;

        $logDir = dirname($logFile);
        if (file_exists($logFile) && filesize($logFile) >= $maxSize) {
            $logDir = dirname($logFile);
            $historyDir = $logDir . '/history_logs';

            if (!is_dir($historyDir)) {
                mkdir($historyDir, 0777, true);
            }

            $appName = TargetManager::peek_activeTarget() ?: 'm-project';
            $timestamp = date('Y-m-d_H-i-s');
            $historyFile = $historyDir . '/' . $appName . '_' . $timestamp . '.log';

            // 1. move old log to history
            @rename($logFile, $historyFile);

            // 2. make new Log file
            @touch($logFile);
            @chmod($logFile, 0777);
        }
    }

    /**
     * Log a success message.
     *
     * @param string $message
     * @return void
     */
    public static function success(string $message): void
    {
        self::log(self::SUCCESS, $message . "\n");
    }

    public static function finish(): void
    {
        echo "======================================================================\n\n";
        self::success(self::FINISH);
        echo "======================================================================\n\n";
    }

    /**
     * Log a warning message.
     *
     * @param string $message
     * @return void
     */
    public static function warning(string $message): void
    {
        self::log(self::WARNING, $message . "\n");
    }
}
