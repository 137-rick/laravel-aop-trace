<?php
/*
 * Go! AOP framework configuration for Laravel.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

$traceEnable = getenv("LEAP_TRACE_ENABLE");
$traceEnable = $traceEnable !== false ? $traceEnable == 1 : env("TRACE_ENABLE", false);

return [

    /*
     |--------------------------------------------------------------------------
     | AOP Debug Mode
     |--------------------------------------------------------------------------
     |
     | When AOP is in debug mode, then breakpoints in the original source
     | code will work. Also engine will refresh cache files if the original
     | files were changed.
     |
     | For production mode, no extra filemtime checks and better
     | integration with opcache.
     |
     */

    'debug' => env('APP_DEBUG', true),

    /*
    |--------------------------------------------------------------------------
    | Application Root Directory
    |--------------------------------------------------------------------------
    |
    | AOP will be applied only to the files in this directory, change it
    | to app_path() if needed.
    |
    */

    'appDir' => base_path(),

    /*
    |--------------------------------------------------------------------------
    | AOP Cache Directory
    |--------------------------------------------------------------------------
    |
    | AOP engine will put all transformed files and caches in that directory.
    |
    */

    'cacheDir' => env('GOAOP_CACHE_DIR', storage_path('app/aspect')),

    /*
    |--------------------------------------------------------------------------
    | Cache File Mode
    |--------------------------------------------------------------------------
    |
    | If configured then will be used as cache file mode for chmod.
    |
    */

    'cacheFileMode' => env('GOAOP_CACHE_PERMISSIONS', 0777),

    /*
    |--------------------------------------------------------------------------
    | Miscellaneous AOP Engine Features
    |--------------------------------------------------------------------------
    |
    | This option should contain a bitmask of values defined in
    | \Go\Aop\Features enumeration:
    |
    | Support Options:
    |   1  - enables interception of system function.
    |   2  - enables interception of "new" operator in the source code.
    |   4  - enables interception of "include"/"require" operations
    |       in legacy code.
    |   64 - do not check the cache presence and assume that cache
    |       is already prepared
    |
    | <code>
    |   //
    |   // bitmask of 1 + 2 + 4 options.
    |   //
    |   'features' => 1 | 2 | 4,
    |
    | </code>
    |
    */

    'features' => env('GOAOP_FEATURES', 1 | 2 | 4),

    /*
    |--------------------------------------------------------------------------
    | Directories White List
    |--------------------------------------------------------------------------
    |
    | AOP will check this list to apply an AOP to selected directories only,
    | leave it empty if you want AOP to be applied to all files in the appDir.
    |
    */

    'includePaths' => [
        base_path() . "/vendor/guzzlehttp/guzzle",
        base_path() ."/vendor/php-curl-class/php-curl-class/src/Curl",
        //app()->path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Directories Black List
    |--------------------------------------------------------------------------
    |
    | AOP will check this list to disable AOP for selected directories.
    |
    | Note: The App\Exceptions\Handler SHOULD NOT be handled by the
    | GO! AOP framework, since in case of fatal errors, it will not be
    | possible to correctly process an Exception.
    |
    */

    'excludePaths' => [
        app()->path() . '/Exceptions',
        "src/HandlerStack.php",
        base_path() . "/vendor/guzzlehttp/guzzle/src/HandlerStack.php",
        base_path() . '/vendor/guzzlehttp/guzzle/src/Exception',
    ],

    /*
    |--------------------------------------------------------------------------
    | AOP Container
    |--------------------------------------------------------------------------
    |
    | This option can be useful for extension and fine-tuning of services.
    |
    */

    'containerClass' => \Go\Core\GoAspectContainer::class,

    // 是否开启
    'traceEnable' => $traceEnable,

    // 是否记录请求结果 response、不记录会减少大量日志大小，但是排查问题不便
    'traceRecordResponse' => env("TRACE_RECORD_RESPONSE", true),

    // 记录trace日志路径
    'traceLogPath' => env("TRACE_LOG_PATH", storage_path() . '/logs'),

    //日志文件名包含pid，用于减少写乱情况
    'traceLogFileWithPid' => env("TRACE_LOG_FILE_WITH_PID", false),

];
