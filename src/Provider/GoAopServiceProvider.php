<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Xes\Logtrace\Provider;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Illuminate\Support\ServiceProvider;
use mysql_xdevapi\Exception;
use Xes\Logtrace\Aspects\AspectLaravelKernel;

/**
 * Service provider for registration of Go! AOP framework
 */
class GoAopServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application services.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot()
    {

        //Trace 开关
        if (!config('go_aop.traceEnable', false)) {
            return;
        }

        umask(0);

        /** @var AspectContainer $aspectContainer */
        $aspectContainer = $this->app->make(AspectContainer::class);

        // Let's collect all aspects and just register them in the container
        $aspects = $this->app->tagged('goaop.aspect');
        foreach ($aspects as $aspect) {
            $aspectContainer->registerAspect($aspect);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //Trace 开关
        if (!config('go_aop.traceEnable', false)) {
            return;
        }

        umask(0);

        $this->publishes([__DIR__  . '/../config/go_aop.php' => config_path('go_aop.php')]);

        if (file_exists($this->configPath())) {
            $this->mergeConfigFrom($this->configPath(), 'go_aop');
        } else {
            throw new Exception("config/go_aop.php not found ");
        }

        $this->app->singleton(AspectKernel::class, function () {
            $aspectKernel = AspectLaravelKernel::getInstance();
            $aspectKernel->init(config('go_aop'));

            return $aspectKernel;
        });

        $this->app->singleton(AspectContainer::class, function ($app) {
            /** @var AspectKernel $kernel */
            $kernel = $app->make(AspectKernel::class);

            return $kernel->getContainer();
        });
    }

    /**
     * @inheritDoc
     */
    public function provides()
    {
        return [AspectKernel::class, AspectContainer::class];
    }

    /**
     * Returns the path to the configuration
     *
     * @return string
     */
    private function configPath()
    {
        return __DIR__ . '/../../../../../config/go_aop.php';
    }
}
