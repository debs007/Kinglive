<?php

use Illuminate\Support\Facades\Schedule;

// Ban auto-expiry (every 5 minutes)
Schedule::job(new \App\Jobs\AutoExpireBansJob)->everyFiveMinutes();

// Horizon snapshot
Schedule::command('horizon:snapshot')->everyFiveMinutes();
