<?php

namespace Xes\Logtrace\Logger;

use Xes\Logtrace\Util\Tools;

/**
 * Class AdvanceWriter
 *
 * @author  Finch.Lei ChangLong.Xu
 * @package Xes\Logtrace\Logger
 */
class AdvanceWriter
{

    protected static $logCache = [];

    /**
     * record Log
     *
     * @param $log
     */
    public static function baseLog($log)
    {
        //cli direct write
        if (php_sapi_name() == "cli") {
            self::$logCache[] = $log;
            AdvanceWriter::dumpLog();
            return;
        }

        self::$logCache[] = $log;

    }

    /**
     * register dump log process
     */
    public static function registerLogDumper()
    {
        register_shutdown_function(function () {
            AdvanceWriter::dumpLog();
        });
    }

    /**
     * getLog path
     *
     * @return mixed
     */
    public static function getLogPath()
    {
        return config('go_aop.traceLogPath', storage_path() . "/logs");
    }

    /**
     * get Log file name
     *
     * @return string
     */
    public static function getFileName()
    {
        if (!config('go_aop.traceLogFileWithPid', true)) {
            return self::getLogPath() . "/" . config("app.name", "firstleap")
                . "-" . date("Y-m-d") . ".log";
        }

        return self::getLogPath() . "/" . config("app.name", "firstleap"). "-"
            . Tools::getMyPid() . "-" . date("Y-m-d") . ".log";

    }

    /**
     * dump the log
     */
    public static function dumpLog()
    {
        if (empty(self::$logCache)) {
            return;
        }

        if (php_sapi_name() != "cli") {
            fastcgi_finish_request();
        }

        $logFileName = self::getFileName();
        try{
            umask(0);
            $fd = fopen($logFileName, "a+");
        } catch (\Throwable $e) {
            try{
                mkdir(self::getLogPath(). "/", 0777, true);
            } catch (\Throwable $e) {}
            try{
                chmod($logFileName, 0666);
            } catch (\Throwable $e) {}

            try{
                $fd = fopen($logFileName, "a+");
            } catch (\Throwable $e) {
                error_log("write message fail " . $logFileName);
                return ;
            }
        }

        $logList = self::$logCache;
        self::$logCache = [];

        foreach ($logList as $log) {
            $log = json_encode($log);
            fwrite($fd, $log . PHP_EOL);
        }

        fclose($fd);

    }
}