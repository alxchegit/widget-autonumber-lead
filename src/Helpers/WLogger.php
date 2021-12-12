<?php


namespace App\Helpers;

//если не нужны логи - просто ставим false
const WDEBUG = true;


class WLogger
{
    protected static $timestampFormat = 'H:i:s';

    public static function log_it($msg, $line)
    {
        if (!WDEBUG)
            return;

        $timestamp = date(self::$timestampFormat);
        $currentDay = date('d.m.Y');

        file_put_contents(dirname(__DIR__, 2). "/logs/$currentDay.txt", "[$timestamp]" . ($line === false ? ' ' : "[Line: $line] ") . print_r($msg, true) . PHP_EOL, FILE_APPEND);
    }
}