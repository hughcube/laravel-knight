<?php

namespace HughCube\Laravel\Knight\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\Console\Exception\RuntimeException;

class KRTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'krtest
                            {number=1 : Number of repetitions, one by default }
                            {--class=\Tests\KRTest : The name of the class that needs to be executed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repeat the execution of a class';

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function handle()
    {
        $instance = $this->makeInstance();

        /** @var int $number */
        $number = $this->argument('number');

        $this->line('<info>当前时间:</info><comment>'.Carbon::now()->format('Y-m-d H:i:s.u').'</comment>');
        $this->line("<info>重复次数:</info><comment>{$number}</comment><info>次</info>");

        $startPeakMemory = memory_get_peak_usage();
        $this->info(sprintf('开始的内存峰值:%sM', $this->formatMemory($startPeakMemory)));

        $startDateTime = Carbon::now();

        for ($i = 1; $i <= $number; $i++) {
            $instance->run($i);
        }

        $endDateTime = Carbon::now();
        $endPeakMemory = memory_get_peak_usage();

        $expendTime = $startDateTime->diffInMicroseconds($endDateTime);
        $expendMemory = $endPeakMemory - $startPeakMemory;

        $this->info(
            sprintf(
                '<info>总耗时:</info><comment>%s</comment><info>秒, 平均耗时:</info><comment>%s</comment><info>微秒</info>',
                round($expendTime / 1000000, 2),
                round($expendTime / $number, 2)
            )
        );

        $this->info(
            sprintf('<info>结束的内存峰值:</info><comment>%s</comment><info>M</info>', $this->formatMemory($endPeakMemory))
        );
        $this->info(
            sprintf('<info>内存峰值差:</info><comment>%s</comment><info>M</info>', $this->formatMemory($expendMemory))
        );
    }

    /**
     * @return object
     *
     * @throws BindingResolutionException
     */
    protected function makeInstance()
    {
        $class = $this->option('class');

        if (empty($class)) {
            throw new RuntimeException('The "--class" option does not exist.');
        }

        if (! class_exists($class)) {
            throw new RuntimeException(sprintf('class "%s" is not defined.', $class));
        }

        return $this->laravel->make($class);
    }

    /**
     * 内存单位换算为M.
     *
     * @param  int  $memory
     * @return float
     */
    protected function formatMemory(int $memory): float
    {
        return round($memory / 1024 / 1024, 2);
    }
}
