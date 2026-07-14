<?php

namespace App\Console;

use App\Console\Commands\LeadsEnrich;
use App\Console\Commands\LeadsScore;
use App\Console\Commands\LeadsSearch;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected array $commands = [
        LeadsSearch::class,
        LeadsEnrich::class,
        LeadsScore::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('leads:search --region="Emilia-Romagna" --sector="pulizie"')
            ->twiceDaily(8, 18)
            ->withoutOverlapping();

        $schedule->command('leads:enrich --limit=50')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        $schedule->command('leads:score')
            ->dailyAt('03:00')
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
