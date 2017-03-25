<?php

namespace App\Console;

use App\Transaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
            error_log('cron');
            $apiRequest = new \ApiRequest();
            $transactions = Transaction::where('status', 'processing')->get();
            foreach ($transactions as $transaction) {
                $messageData = $apiRequest->getMessage($transaction['validation_status']);
                if($transaction['validation_status'] != 'processing') {
                    $transaction->validation_status = $messageData['data']['attributes']['status'];
                    $transaction->validation_message = @$messageData['data']['attributes']['error'];
                    $transaction->save();
                }
            }
        })->cron('*/5 * * * * *');
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
