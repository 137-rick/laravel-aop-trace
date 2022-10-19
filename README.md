# Laravel Tracer

## Introduction
laravel aspect log trace

## Laravel 5.5+ Install


### AOP Config

## 发布配置文件到laravel config内

```bash
php artisan vendor:publish --provider="Xes\Logtrace\Provider\AopServiceProvider" 
```

src/config/go\_aop.php 文件会放在 config/go\_aop.php 内

## .env Config
以下为具体.env内配置项，展示的是建议配置项

```ini

# 是否开启
TRACE_ENABLE=true

# 记录trace日志路径
TRACE_LOG_PATH=/data/logs/trace/

# 是否记录请求结果 response、不记录会减少大量日志大小，但是排查问题不便
TRACE_RECORD_RESPONSE=true

# laravel env 中项目名称
APP_NAME=project_name_trace

# 关闭debug模式，否则会有性能问题
# debug 开启后会实时更新缓存

APP_DEBUG=false

# 日志文件名包含pid，用于减少写乱情况
TRACE_LOG_FILE_WITH_PID=true

```

## MiddleWare 中间件注册

vim app/Http/Kernel.php 

```php

$middleware = [ 
     \Xes\Logtrace\Middleware\TraceMiddleware::class,
];

```

## Exception 拦截注册
app/Exceptions/Handler.php

```php
# 文件顶部
use Xes\Logtrace\Trace;

//找到render函数
public function render($request, Exception $exception)
{
        //追加这一行
        Trace::Exception($exception); 

```

## Console
app/Console/Kernel.php

```php
//顶部追加引用
use Xes\Logtrace\Trace;

    protected function commands()
    {
        //追加这一行
        Trace::requestStart(); 

```

## 刷新缓存

```bash
# 预热缓存、注意 这个操作实际和请求生成有差异 需要本地充分测试再使用
./artisan aop:cache:warmup

# 或
rm -rf storage/app/aspect/*
```

## Laravel version < 5.5

## Provider
```bash 
php artisan vendor:publish --provider="Xes\Logtrace\Provider\AopServiceProvider" 
```

## Notice

项目中有语法错误会导致报错，请及时修复代码错误 

并且如果碰到无法识别的php文件，请在config/go\_aop.php白名单内人工屏蔽 

发布时必须清空目标机器storage/app/aspect目录内容 
