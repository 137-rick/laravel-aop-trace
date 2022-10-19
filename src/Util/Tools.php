<?php

namespace Xes\Logtrace\Util;

/**
 * Class Tools
 *
 * @author  Finch.Lei ChangLong.Xu
 * @package Xes\Logtrace\Util
 */
class Tools
{

    protected static $_server_ip;

    /**
     * 随机生成TraceId
     *
     * @return string
     */
    public static function getTraceId()
    {
        $result = self::getHostName() . "_" . getmypid() . "_"
            . (microtime(true) - 1483200000)
            . "_" . mt_rand(0, 255);

        return $result;

    }

    /**
     * get my pid
     *
     * @return int
     */
    public static function getMyPid()
    {
        //prevent fork for cache
        return getmypid();
    }

    /**
     * get Host Name
     *
     * @return string
     */
    public static function getHostName()
    {
        if (self::$_server_ip == "") {
            self::$_server_ip = gethostname();
        }
        return self::$_server_ip;

    }
}