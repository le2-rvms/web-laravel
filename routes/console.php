<?php

use App\Console\Commands\App\DeliveryLogMake;
use App\Console\Commands\App\One\OneHtmlFetch;
use App\Console\Commands\App\One\OneRefreshCookie;
use App\Console\Commands\App\One\OneVehiclesImport;
use App\Console\Commands\App\One\OneViolationsImport;
use App\Console\Commands\App\VehicleViolationUsagesIdUpdate;
use App\Console\Commands\Sys\SmtpSelfTest;
use Illuminate\Support\Facades\Schedule;

// Schedule::call(function () {
//    echo 'schedule tick: '.date('c').PHP_EOL;
// })->everyMinute()->description('test every minute');

// Schedule::command(DeliveryLogMake::class)->cron('* 8-20 * * *')->withoutOverlapping();

Schedule::command(OneRefreshCookie::class)->everyFifteenMinutes()->withoutOverlapping();

// Schedule::command(VehicleViolationUsagesIdUpdate::class)->everyTenMinutes();

Schedule::command(SmtpSelfTest::class)->dailyAt('09:00')->description('SMTP 每日自检邮件');

Schedule::call(function () {
    $commands = [OneRefreshCookie::class, OneHtmlFetch::class, OneVehiclesImport::class, OneViolationsImport::class];

    foreach ($commands as $command) {
        $exitCode = $this->call($command);
        $output   = $this->output();

        if (0 !== $exitCode) {
            break;
        }
    }
})->cron('0 8 * * *')->description('每日早8点1轮122');
