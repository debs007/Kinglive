<?php

use Illuminate\Support\Facades\Schedule;

// Ban auto-expiry (every 5 minutes)
Schedule::job(new \App\Jobs\AutoExpireBansJob)->everyFiveMinutes();

Schedule::job(new \App\Jobs\CleanupStaleRoomsJob)->everyMinute();
Schedule::job(new \App\Jobs\CreditLiveRewardJob)->everyMinute();
//Schedule::job(new \App\Jobs\NotifyFollowersLiveJob)->everyMinute();
//Schedule::job(new \App\Jobs\ProcessGiftJob)->everyMinute();


// Horizon snapshot
Schedule::command('horizon:snapshot')->everyFiveMinutes();
