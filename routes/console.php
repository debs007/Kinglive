<?php

use Illuminate\Support\Facades\Schedule;

// Ban auto-expiry (every 5 minutes)
Schedule::job(new \App\Jobs\AutoExpireBansJob)->everyFiveMinutes();

Schedule::job(new \App\Jobs\CleanupStaleRoomsJob)->everyMinute();
// CreditLiveRewardJob disabled — rewards are now collected manually by users
// Schedule::job(new \App\Jobs\CreditLiveRewardJob)->everyMinute();

// Monthly reset — 1st of every month at midnight
// Snapshots all host stats then resets monthly counters
Schedule::job(new \App\Jobs\MonthlyResetJob)->monthlyOn(1, '00:00');
//Schedule::job(new \App\Jobs\NotifyFollowersLiveJob)->everyMinute();
//Schedule::job(new \App\Jobs\ProcessGiftJob)->everyMinute();


// Horizon snapshot
Schedule::command('horizon:snapshot')->everyFiveMinutes();