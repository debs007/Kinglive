<?php

namespace App\Providers;

use App\Services\AgoraService;
use App\Services\BanService;
use App\Services\GameService;
use App\Services\GiftService;
use App\Services\LiveRoomService;
use App\Services\NotificationService;
use App\Services\OtpService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AgoraService::class);
        $this->app->singleton(BanService::class);
        $this->app->singleton(GiftService::class);
        $this->app->singleton(GameService::class);
        $this->app->singleton(LiveRoomService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(OtpService::class);
        $this->app->singleton(WalletService::class);
    }

    public function boot(): void
    {
        // Global Blade helper: setting('key', 'default')
        Blade::directive('setting', function ($expression) {
            return "<?php echo \App\Models\Setting::get({$expression}); ?>";
        });
    }
}
