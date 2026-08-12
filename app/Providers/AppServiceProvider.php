<?php

namespace App\Providers;

use App\Contracts\GoogleCalendar;
use App\Services\GoogleCalendarClient;
use App\Socialite\AppleProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GoogleCalendar::class, GoogleCalendarClient::class);
    }

    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, function (SocialiteWasCalled $event) {
            $event->extendSocialite('apple', AppleProvider::class);
        });
    }
}
