<?php

namespace Xes\Logtrace\Provider;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Xes\Logtrace\Aspects\CurlAspect;
use Xes\Logtrace\Aspects\GuzzleHttpAspect;
use Xes\Logtrace\Console\Commands\CreateAspectCache;
use Xes\Logtrace\Trace;

/**
 * Class AopServiceProvider for old laravel version
 *
 * @author  Finch.Lei ChangLong.Xu
 */
class AopServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

        //department
        Trace::setDepartment(config("app.name", "firstleap"));

        //Trace 开关
        if (!config('go_aop.traceEnable', false)) {
            return;
        }

        umask(0);

        $this->app->singleton(CurlAspect::class, function (Application $app) {
            return new CurlAspect();
        });
        $this->app->singleton(GuzzleHttpAspect::class, function (Application $app) {
            return new GuzzleHttpAspect();
        });
        $this->app->tag([CurlAspect::class, GuzzleHttpAspect::class], 'goaop.aspect');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

        //Trace 开关
        if (!config('go_aop.traceEnable', false)) {
            return;
        }

        umask(0);

        //set config to publish
        $this->publishes([
            __DIR__ . '/../config/go_aop.php' => config_path('go_aop.php'),
        ], "config");

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAspectCache::class,
            ]);
        }

        //Db sql
        \DB::listen(
        /**
         * @var Illuminate\Database\Events\QueryExecuted $sql
         */
            function ($sql) {
                $basePath = base_path();
                $basePathPrefixSize = strlen($basePath) + 1;
                $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

                $sourceFile = null;
                $sourceLine = null;

                foreach ($stack as $oneStack) {
                    if(!isset($oneStack["class"])) {
                        continue;
                    }

                    $startPrefix = substr($oneStack["class"], 0, 3);
                    if (isset($oneStack["file"]) &&$startPrefix == "App") {
                        $sourceFile = substr($oneStack["file"], $basePathPrefixSize);
                        $sourceLine = $oneStack["line"];
                        break;
                    }
                }

                Trace::mysqlQueryLog(
                    $sourceFile,
                    $sourceLine,
                    $sql->connectionName,
                    $sql->sql,
                    $sql->bindings,
                    $sql->time);
            }
        );
    }
}
