<?php
use App\Console\Commands\GenerateRecurringInvoices;
use App\Console\Commands\NetworkHealthCheck;
use App\Console\Commands\ReconcileAsaasInvoices;
use App\Console\Commands\SuspendOverdueContracts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
Artisan::command('about:platform', function(){ $this->info(config('app.name').' '.config('app.version')); });
Schedule::command(GenerateRecurringInvoices::class)->dailyAt('00:20')->withoutOverlapping();
Schedule::command(SuspendOverdueContracts::class)->hourly()->withoutOverlapping();
Schedule::command(NetworkHealthCheck::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(ReconcileAsaasInvoices::class)->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
