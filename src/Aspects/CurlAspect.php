<?php

namespace Xes\Logtrace\Aspects;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FunctionInvocation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\Around;
use Xes\Logtrace\Trace;

/**
 * CurlAspect aspect
 *
 * @author Finch.Lei ChangLong.Xu
 */
class CurlAspect implements Aspect
{

    protected static $headers = [];

    protected static $body = "";

    /**
     * curl aspect hook
     *
     * @param FunctionInvocation $invocation Invocation
     *
     * @Around("execution(**\curl_exec(*))")
     *
     * @return bool|string
     */

    public function curlExecAspect(FunctionInvocation $invocation)
    {

        //argv
        $argV = $invocation->getArguments();
        $curlFd = $argV[0] ?? null;

        //curl fd可用 不可用返回false
        if (!is_resource($curlFd)) {
            return FALSE;
        }

        //file source path and line scan from stack
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        foreach ($stack as $oneStack) {
            //guzzle ignore
            if (isset($oneStack["file"]) && stripos($oneStack["file"], "/guzzlehttp/") !== FALSE) {
                self::$headers = [];
                return $invocation->proceed();
            }
        }

        //reset header
        $headers = self::$headers ?? [];
        $headers[] = "traceid: " . Trace::getTraceId();
        $headers[] = "rpcid: " . Trace::getNextRpcId();

        \curl_setopt($curlFd, CURLOPT_HTTPHEADER, $headers);

        //clean header
        self::$headers = [];

        //hack request info
        \curl_setopt($curlFd, CURLINFO_HEADER_OUT, 1);

        $startTime = microtime(true);

        //curl exec
        $response = \curl_exec($curlFd);

        $cost = round(microtime(true) - $startTime, 4);

        //get curl info
        $curlInfo = \curl_getinfo($curlFd);

        //hack request header
        $headers = $curlInfo["request_header"] ?? "";
        $headers = explode("\r\n\r\n", $headers ?? "");
        $headers[0] = explode("\r\n", $headers[0] ?? "");
        $method = substr($headers[0][0] ?? "", 0, 3);

        //get method
        $method = $this->getMethodName($method);

        $body = self::$body;

        //request body process
        if (!empty($body)) {
            $paramsTemp = @json_decode($body, true);
            if (is_array($paramsTemp) && count($paramsTemp) > 0) {
                $body = $paramsTemp;
            } else {
                parse_str($body, $paramsTemp);
                if (count($paramsTemp) > 0) {
                    $body = $paramsTemp;
                }
            }
            unset($paramsTemp);
        }

        //error on curl
        if ($response === FALSE) {
            Trace::curlRequestLog($method, $curlInfo["url"], $body, "", $curlInfo
                , $cost , curl_error($curlFd), curl_errno($curlFd), "");

            return $response;
        }

        Trace::curlRequestLog($method, $curlInfo["url"], $body, $response, $curlInfo, $cost);

        return $response;

    }

    /**
     * curl set opt aspect hook
     *
     * @param FunctionInvocation $invocation Invocation
     *
     * @Around("execution(**\curl_setopt(*))")
     *
     * @return bool|string
     */

    public function curlSetOptAspect(FunctionInvocation $invocation)
    {
        $argv = $invocation->getArguments();

        if (isset($argv[2]) && $argv[1] == CURLOPT_HTTPHEADER) {
            $this->setHeaders($argv[2]);
        }
        if (isset($argv[2]) && $argv[1] == CURLOPT_POSTFIELDS) {
            self::$body = ($argv[2]);
        }

        return $invocation->proceed();
    }

    /**
     * curl set opt array aspect hook
     *
     * @param FunctionInvocation $invocation Invocation
     *
     * @Around("execution(**\curl_setopt_array(*))")
     *
     * @return bool|string
     */

    public function curlSetOptArrayAspect(FunctionInvocation $invocation)
    {
        $argv = $invocation->getArguments();

        if (isset($argv[1][CURLOPT_HTTPHEADER])) {
            $this->setHeaders($argv[1][CURLOPT_HTTPHEADER]);
        }

        if (isset($argv[1][CURLOPT_POSTFIELDS])) {
            self::$body = $argv[1][CURLOPT_POSTFIELDS];
        }

        return $invocation->proceed();
    }

    /**
     * curl set opt aspect hook
     *
     * @param FunctionInvocation $invocation Invocation
     *
     * @After("execution(**\curl_init(*))")
     *
     * @After("execution(**\curl_close(*))")
     *
     * @return bool|string
     */
    public function curlRestAspect(FunctionInvocation $invocation)
    {
        self::$headers = [];

        return $invocation->proceed();
    }

    /**
     * decode header format
     *
     * @param $argv
     */
    private function setHeaders($argv)
    {
        $type = gettype($argv);

        switch ($type) {
            case "array":
                self::$headers = $argv;
                break;
            case "string":
                $decode = @json_decode($argv, true);
                self::$headers = is_array($decode) ? $decode : [];
                break;
            default:
                self::$headers = [];
        }
    }

    /**
     * get Method
     *
     * @param $method
     *
     * @return string
     */
    private function getMethodName($method)
    {
        switch ($method) {
            case "GET":
                $method = "get";
                break;
            case "OPT":
                $method = "opt";
                break;
            case "POS":
                $method = "post";
                break;
            case "PUT":
                $method = "put";
                break;
            case "DEL":
                $method = "delete";
                break;
            case "PAT":
                $method = "patch";
                break;
            default:
                $method = strtolower($method);
                break;
        }
        return $method;
    }

    /**
     * 这个函数不要删除，否则会导致注解失败
     * 预防use 被编辑器整理误删
     */
    private function foo()
    {
        return [
            Around::class,
            After::class
        ];
    }

}