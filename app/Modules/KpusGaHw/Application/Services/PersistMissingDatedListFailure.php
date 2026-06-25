<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Modules\KpusGaHw\Application\Data\ObjectiveAuditResult;
use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Carbon\CarbonImmutable;

class PersistMissingDatedListFailure
{
    private const AREA_NAME = 'Todo List Harian';

    private const REASON = 'To-do list belum dibuat sebelum jam pengecekan';

    public function __construct(private readonly PersistObjectiveFailure $persistObjectiveFailure) {}

    public function handle(BasecampProject $project, CarbonImmutable $reportDate): DailyAreaAudit
    {
        return $this->persistObjectiveFailure->handle(
            $project,
            $reportDate,
            new ObjectiveAuditResult(
                areaExternalId: 'missing-dated-list:'.$reportDate->toDateString(),
                areaName: self::AREA_NAME,
                todoUrl: $this->projectAppUrl(),
                photoCount: 0,
                firstUploadAt: null,
                passed: false,
                reason: self::REASON,
            ),
        );
    }

    private function projectAppUrl(): string
    {
        return sprintf(
            'https://app.basecamp.com/%s/projects/%s',
            (string) config('basecamp.account_id'),
            (string) config('basecamp.project_id'),
        );
    }
}
