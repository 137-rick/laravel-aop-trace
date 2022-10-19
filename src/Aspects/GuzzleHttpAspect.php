<?php

namespace Xes\Logtrace\Aspects;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Around;
use GuzzleHttp\TransferStats;
use Xes\Logtrace\Trace;
use Xes\Logtrace\Util\Tools;

/**
 * GuzzleHttp aspect
 *
 * @author Finch.Lei ChangLong.Xu
 */
class GuzzleHttpAspect implements Aspect
{

    protected $_stat = null;

    /**
     * guzzle http aspect hook
     *
     * @param MethodInvocation $invocation Invocation
     *
     * @Around("execution(public GuzzleHttp\Client->request(*))")
     *
     * @return bool|string
     * @throws \Throwable
     */

    public function guzzleCurlHandle(MethodInvocation $invocation)
    {
        $argv = $invocation->getArguments();

        //hook the on stats event
        $those = $this;

        //clean old data
        $this->_stat = null;

        $oldStatsHook = null;
        if (isset($argv[2]["on_stats"]) && is_callable($argv[2]["on_stats"])) {
            $oldStatsHook = $argv[2]["on_stats"];
        }

        $argv[2]["on_stats"] = function (TransferStats $stats) use ($those) {
            $those->setStats($stats);
        };

        $headers = $argv[2]["header"] ?? [];

        $headers["traceid"] = Trace::getTraceId();
        $headers["rpcid"] = Trace::getNextRpcId();

        $argv[2]["header"] = $headers;

        //change arguments
        $invocation->setArguments($argv);

        $method = $argv[0];
        $url = $argv[1];
        $body = isset($argv[2]["form_params"]) ? $argv[2]["form_params"] :
            (isset($argv[2]["json"]) ? $argv[2]["json"] : []);

        $startTime = microtime(true);

        //run the process
        try {
            $result = $invocation->proceed();
        } catch (\Throwable $e) {
            $cost = round(microtime(true) - $startTime, 4);

            Trace::curlRequestLog($method, $url, $body, "", $this->getCurlInfo()
                , $cost, $e->getMessage(), -1, $e->getTraceAsString());

            throw $e;
        }

        $cost = round(microtime(true) - $startTime, 4);

        //passed old handle for stat
        if ($oldStatsHook != null) {
            call_user_func_array($oldStatsHook, [$this->_stat]);
        }

        //result
        $response = (string)$result->getBody();

        Trace::curlRequestLog($method, $url, $body, $response, $this->getCurlInfo(), $cost);

        //make some version review the stream
        try{
            $result->getBody()->rewind();
        } catch (\Throwable $e) {

        }

        //after
        return $result;
    }

    /**
     * store the curl info state
     *
     * @param TransferStats $stats
     */
    private function setStats(TransferStats $stats)
    {
        $this->_stat = $stats;
    }

    /**
     * fetch the curl info
     *
     * @return array
     */
    private function getCurlInfo()
    {
        if (empty($this->_stat)) {
            return [];
        }

        return $this->_stat->getHandlerStats();
    }


    /**
     * 这个函数不要删除，否则会导致注解失败
     * 预防use 被编辑器整理误删
     */
    private function foo()
    {
        return [
            Around::class,
        ];
    }

}