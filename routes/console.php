<?php

use App\Console\Commands\AiTrainModels;
use App\Console\Commands\KalkulasiStokMinimumCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Training model AI — setiap Minggu 02:00
Schedule::command(AiTrainModels::class)
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->environments(['production', 'local']);

// Kalkulasi stok minimum — tanggal 1, 03:00
Schedule::command(KalkulasiStokMinimumCommand::class)
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->environments(['production', 'local']);

// Backup harian (butuh spatie/laravel-backup)
Schedule::command('backup:clean')
    ->daily()
    ->at('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->environments(['production', 'local']);

Schedule::command('backup:run --only-db')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->environments(['production', 'local']);

// Pengingat & auto tutup buku
Schedule::command('tutup-buku:ingatkan')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->environments(['production', 'local']);

Schedule::command('tutup-buku:auto')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->environments(['production', 'local']);
