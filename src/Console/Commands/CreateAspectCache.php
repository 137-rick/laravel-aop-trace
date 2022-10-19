<?php

namespace Xes\Logtrace\Console\Commands;


use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CacheWarmer;
use Illuminate\Console\Command;


/**
 * CreateAspectCache refresh cache of aop
 *
 * @author Finch.Lei
 */

class CreateAspectCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aop:cache:warmup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'warm up aop code cache';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $kernel =  app(AspectKernel::class);
        $kernel->init(config('go_aop'));
        $warmer = new CacheWarmer($kernel);
        $warmer->warmUp();
    }
}