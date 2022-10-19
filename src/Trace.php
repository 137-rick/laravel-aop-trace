<?php

namespace Xes\Logtrace;

use Xes\Logtrace\Logger\AdvanceWriter;
use Xes\Logtrace\Util\Tools;

/**
 * Class Trace
 *
 * @author  Finch.Lei ChangLong.Xu
 * @package Xes\Logtrace
 */
class Trace
{
    protected static $traceId = "";

    protected static $rpcId = "";

    protected static $rpcIdCounter = 0;

    protected static $startTime = 0;

    protected static $uid = "";

    protected static $version = "";

    protected static $department = "firstleap";

    /**
     * request start
     *
     * @param string $traceId
     * @param string $rpcId
     */
    public static function requestStart($traceId = "", $rpcId = "")
    {
        if ($traceId == "") {
            self::$traceId = Tools::getTraceId();
        } else {
            self::$traceId = $traceId;
        }

        if ($rpcId == "") {
            self::$rpcId = "1";
        } else {
            self::$rpcId = $rpcId;
        }

        //start cost
        self::$startTime = microtime(true);

        //register dump log
        AdvanceWriter::registerLogDumper();
    }

    /**
     * request end
     *
     * @param $code
     * @param $response
     */
    public static function requestFinished($code, $response)
    {
        //decode response
        $responseTemp = json_decode($response, true);
        if (!empty($responseTemp) && count($responseTemp) > 0) {
            $response = $responseTemp;
        }

        if (php_sapi_name() == "cli") {
            return;
        }

        //record response
        if (!config('go_aop.traceRecordResponse', true)) {
            $response = [];
        }

        //set at first
        $log = array(
            "x_name" => "request.info",
            "x_action" => ($_SERVER["REQUEST_SCHEME"] ?? 'http') . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
            "x_param" => ["get" => $_GET, "post" => $_POST, "body" => file_get_contents("php://input")],
            "x_trace_id" => Trace::getTraceId(),
            "x_rpc_id" => Trace::getOrigRpcId(),
            "x_department" => self::$department,
            "x_server_ip" => Tools::getHostName(),
            "x_timestamp" => (int)self::$startTime,
            "x_duration" => round(microtime(true) - self::$startTime, 4),
            "x_pid" => Tools::getMyPid(),
            "x_uid" => self::$uid,
            "x_version" => self::$version,
            "x_code" => $code,
            "x_response" => $response,
            //"x_extra" => self::$_extra_context
        );

        //dump log
        AdvanceWriter::baseLog($log);
    }

    /**
     * request end exception record
     *
     * @param $exception
     */
    public static function Exception(\Throwable $exception)
    {
        //general log format
        $log = array(
            "x_name" => "log.exception",
            "x_param" => ["get" => $_GET, "post" => $_POST , "body" => file_get_contents("php://input")],
            "x_trace_id" => Trace::getTraceId(),
            "x_rpc_id" => Trace::getOrigRpcId(),
            "x_department" => self::$department,
            "x_server_ip" => Tools::getHostName(),
            "x_timestamp" => (int)self::$startTime,
            "x_duration" => round(microtime(true) - self::$startTime, 4),
            "x_pid" => Tools::getMyPid(),
            "x_uid" => self::$uid,
            "x_version" => self::$version,
            "x_code" => $exception->getCode(),
            "x_msg" => $exception->getMessage(),
            "x_file" => $exception->getFile(),
            "x_line" => $exception->getLine(),
            "x_backtrace" => $exception->getTraceAsString(),
        );

        if (php_sapi_name() != "cli") {
            $log["x_action"] = ($_SERVER["REQUEST_SCHEME"] ?? 'http') . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
        }

            //dump log
        AdvanceWriter::baseLog($log);
    }

    /**
     * curl request format
     *
     * @param string $method
     * @param string $url
     * @param        $body
     * @param        $response
     * @param array  $curlInfo
     * @param        $cost
     * @param string $msg
     * @param string $code
     * @param string $backtrace
     */
    public static function curlRequestLog(string $method, string $url, $body, $response
        , array $curlInfo, $cost, $msg = "", $code = "", $backtrace = "")
    {
        $url = parse_url($url);

        if (empty($method)) {
            $method = "unknown";
        }

        $params = [];

        //get param
        if (isset($url["query"])) {
            parse_str($url["query"], $paramsTemp);

            $params["get"] = count($paramsTemp) > 0 ? $paramsTemp : [];

            unset($paramsTemp);
        }

        //filter query
        if ($url !== false) {
            $url = ($url["scheme"] ?? "http") . "://" . ($url["host"] ?? "unknow") . ($url["path"] ?? "/");
        }

        //request body process
        if (!empty($body)) {
            $params["post"] = $body;
        }

        //file source path and line scan from stack
        $basePath = base_path();
        $basePathPrefixSize = strlen($basePath) + 1;
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        $sourceFile = null;
        $sourceLine = null;

        foreach ($stack as $oneStack) {
            if (!isset($oneStack["class"])) {
                continue;
            }

            $startPrefix = substr($oneStack["class"], 0, 3);
            if ($startPrefix == "App") {
                $sourceFile = substr($oneStack["file"], $basePathPrefixSize);
                $sourceLine = $oneStack["line"];
                break;
            }
        }

        //make the response to array
        $responseLength = strlen($response);
        $responseTemp = json_decode($response, true);
        if (!empty($responseTemp) && count($responseTemp) > 0) {
            $response = $responseTemp;
        }

        //record response
        if (!config('go_aop.traceRecordResponse', true)) {
            $response = [];
        }

        $LogList = array(
            "x_name" => "http." . strtolower($method),
            "x_trace_id" => Trace::getTraceId(),
            "x_rpc_id" => Trace::getCurrentRpcId(),
            "x_timestamp" => time(),

            "x_uid" => self::$uid,
            "x_version" => self::$version,
            "x_department" => self::$department,

            "x_pid" => Tools::getMyPid(),
            "x_server_ip" => Tools::getHostName(),

            "x_module" => "php_curl_aspect",
            "x_duration" => $cost,
            "x_action" => $url,
            "x_param" => $params,
            "x_file" => $sourceFile ?? "",
            "x_line" => $sourceLine ?? 0,
            "x_code" => $curlInfo["http_code"] ?? "",

            "x_response" => $response,
            "x_dns_duration" => round(sprintf("%.f", $curlInfo["namelookup_time"] ?? 0), 4),
            "x_response_length" => $responseLength,
            "x_extra" => [
                "curl_info" => [
                    "url" => $curlInfo["url"] ?? "",
                    "primary_ip" => $curlInfo["primary_ip"] ?? "",
                    "content_type" => $curlInfo["content_type"] ?? "",
                    "http_code" => $curlInfo["http_code"] ?? "",
                    "filetime" => $curlInfo["filetime"] ?? "",
                    "redirect_count" => $curlInfo["redirect_count"] ?? "",
                    "total_time" => round(sprintf("%.f", $curlInfo["total_time"] ?? 0), 4),
                    "namelookup_time" => round(sprintf("%.f", $curlInfo["namelookup_time"] ?? 0), 4),
                    "connect_time" => round(sprintf("%.f", $curlInfo["connect_time"] ?? 0), 4),
                    "pretransfer_time" => round(sprintf("%.f", $curlInfo["pretransfer_time"] ?? 0), 4),
                    "speed_download" => $curlInfo["speed_download"] ?? 0,
                    "speed_upload" => $curlInfo["speed_upload"] ?? 0,
                ],
            ]
        );

        //for error log
        if (!empty($msg) || !empty($code) || !empty($backtrace)) {
            $LogList["x_msg"] = $msg;
            $LogList["x_code"] = $code;
            $LogList["x_backtrace"] = $backtrace;
            $LogList["x_name"] = $LogList["x_name"] . ".error";

        }

        AdvanceWriter::baseLog($LogList);
    }

    /**
     * mysql query log
     *
     * @param $file
     * @param $line
     * @param $db
     * @param $sql
     * @param $params
     * @param $time
     */
    public static function mysqlQueryLog($file, $line, $db, $sql, $params, $time)
    {
        $LogList = array(
            "x_name" => "mysql.query",
            "x_trace_id" => Trace::getTraceId(),
            "x_rpc_id" => Trace::getNextRpcId(),
            "x_timestamp" => time(),

            "x_uid" => self::$uid,
            "x_version" => self::$version,
            "x_department" => self::$department,

            "x_pid" => Tools::getMyPid(),
            "x_server_ip" => Tools::getHostName(),

            "x_module" => "php_db_listener",
            "x_duration" => round($time / 1000, 4),
            "x_db" => $db,
            "x_action" => $sql,
            "x_param" => ["binding" => $params],
            "x_file" => $file ?? "",
            "x_line" => $line ?? 0,
        );

        AdvanceWriter::baseLog($LogList);
    }

    /**
     * getTraceId
     *
     * @return string
     */
    public static function getTraceId()
    {
        if (self::$traceId == "") {
            self::resetTraceId();
        }

        return self::$traceId;
    }

    /**
     * Reset new traceid
     *
     * @return string
     */
    public static function resetTraceId()
    {
        self::$traceId = Tools::getTraceId();
        self::$rpcId = "1";
        self::$rpcIdCounter = 1;

        return self::$traceId;
    }

    /**
     * get orig rpcid without counter
     *
     * @return string
     */
    public static function getOrigRpcId()
    {
        if (self::$rpcId == "") {
            self::resetTraceId();
        }

        return self::$rpcId;
    }

    /**
     * get the rpcId
     *
     * @return string
     */
    public static function getCurrentRpcId()
    {
        if (self::$rpcId == "") {
            self::resetTraceId();
        }

        return self::$rpcId . "." . self::$rpcIdCounter;
    }

    /**
     * get next rpcId
     *
     * @return string
     */
    public static function getNextRpcId()
    {
        if (self::$rpcId == "") {
            self::resetTraceId();
        }

        self::$rpcIdCounter++;
        return self::$rpcId . "." . self::$rpcIdCounter;
    }

    /**
     * 设置当前用户身份，方便汇总用户请求
     *
     * @param $uid
     */
    public static function setUid($uid)
    {
        self::$uid = $uid;
    }

    /**
     * version set
     *
     * @param $version
     */
    public static function setVersion($version)
    {
        self::$version = $version;
    }

    /**
     * department set
     *
     * @param $department
     */
    public static function setDepartment($department)
    {
        self::$department = $department;
    }
}
