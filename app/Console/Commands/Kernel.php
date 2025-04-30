<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        // Register your commands here
        \App\Console\Commands\ProcessStockOpname::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('stock-opname:process')
                 ->everyMinutes()
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/scheduler.log')); // 🟡 Tambahkan logging
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}