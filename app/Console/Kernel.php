<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\PlannifierTransfertJob;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    // protected function schedule(Schedule $schedule)
    // {
    //     $schedule->command('transactions:execute')->everyMinute();
    // }
    protected function schedule(Schedule $schedule)
{
    $schedule->job(new PlannifierTransfertJob())
        ->everyMinute()
        ->onSuccess(function () {
            Log::info("PlannifierTransfertJob exécuté avec succès à " . now());
        })
        ->onFailure(function () {
            Log::error("Échec de l'exécution de PlannifierTransfertJob à " . now());
        });
}


    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}