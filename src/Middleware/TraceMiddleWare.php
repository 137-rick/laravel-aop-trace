<?php

namespace Xes\Logtrace\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Xes\Logtrace\Trace;

/**
 * Class TraceMiddleware
 *
 * @author  Finch.Lei ChangLong.Xu
 * @package Xes\Logtrace\Middleware
 */
class TraceMiddleware
{

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     * @throws \Throwable
     */
    public function handle(Request $request, Closure $next)
    {
        //Trace 开关
        if (!config('go_aop.traceEnable', true)) {
            return $next($request);
        }

        //fetch header with traceid and rpcid
        $traceId = $request->headers->get('traceid', ""); // generate traceid
        $rpcId = $request->headers->get('rpcid', "");
        $uid = $request->headers->get('parent-id', $request->headers->get('workcode'), "");

        //trace start
        Trace::requestStart($traceId, $rpcId);

        //set uid
        Trace::setUid($uid[0] ?? "");

        //process
        try {
            /**
             * @var Illuminate\Http\Response
             */
            $result = $next($request);
        } catch (\Throwable $e) {

            Trace::Exception($e);
            throw $e;
        }

        //response is obj of response
        if ($result instanceof Response) {
            $result->header("traceid", Trace::getTraceId());
            $result->header("rpcid", Trace::getOrigRpcId());
        }

        Trace::requestFinished($result->getStatusCode(), $result->getContent());

        //end
        return $result;
    }
}
