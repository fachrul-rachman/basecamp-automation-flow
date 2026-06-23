<?php

namespace App\Providers;

use App\Core\Shared\Basecamp\Contracts\AttachmentDownloader;
use App\Core\Shared\Basecamp\Contracts\BasecampClient;
use App\Core\Shared\Basecamp\Services\HttpAttachmentDownloader;
use App\Core\Shared\Basecamp\Services\HttpBasecampClient;
use App\Core\Shared\Notion\Contracts\NotionClient;
use App\Core\Shared\Notion\Services\HttpNotionClient;
use App\Core\Shared\OpenAI\Contracts\VisionReviewClient;
use App\Core\Shared\OpenAI\Services\OpenAiVisionReviewClient;
use App\Core\Shared\Scheduling\Contracts\Clock;
use App\Core\Shared\Scheduling\Contracts\HolidayProvider;
use App\Core\Shared\Scheduling\Services\DatabaseHolidayProvider;
use App\Core\Shared\Scheduling\Services\SystemClock;
use App\Core\Shared\Support\Contracts\Sleeper;
use App\Core\Shared\Support\Services\NativeSleeper;
use App\Modules\KpusGaHw\Console\PrintBasecampAuditInputCommand;
use App\Modules\KpusGaHw\Console\PublishNotionResultsCommand;
use App\Modules\KpusGaHw\Console\RunAiReviewAuditCommand;
use App\Modules\KpusGaHw\Console\RunDailyAuditCommand;
use App\Modules\KpusGaHw\Console\RunObjectiveAuditCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(HolidayProvider::class, DatabaseHolidayProvider::class);
        $this->app->bind(BasecampClient::class, HttpBasecampClient::class);
        $this->app->bind(AttachmentDownloader::class, HttpAttachmentDownloader::class);
        $this->app->bind(VisionReviewClient::class, OpenAiVisionReviewClient::class);
        $this->app->bind(NotionClient::class, HttpNotionClient::class);
        $this->app->bind(Sleeper::class, NativeSleeper::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PrintBasecampAuditInputCommand::class,
                RunObjectiveAuditCommand::class,
                RunAiReviewAuditCommand::class,
                PublishNotionResultsCommand::class,
                RunDailyAuditCommand::class,
            ]);
        }
    }
}
