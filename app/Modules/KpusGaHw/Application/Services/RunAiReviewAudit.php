<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Core\Shared\OpenAI\Contracts\VisionReviewClient;
use App\Modules\KpusGaHw\Application\Exceptions\DatedTodolistNotFoundException;
use Carbon\CarbonImmutable;

class RunAiReviewAudit
{
    public function __construct(
        private readonly BuildReadOnlyAuditInput $inputBuilder,
        private readonly EvaluateObjectiveRules $objectiveRules,
        private readonly PersistObjectiveFailure $persistObjectiveFailure,
        private readonly PersistMissingDatedListFailure $persistMissingDatedListFailure,
        private readonly BuildKpusGaHwVisionReviewRequest $requestBuilder,
        private readonly VisionReviewClient $visionReview,
        private readonly ResolveAiAuditResult $resultResolver,
        private readonly PersistAiAuditResult $persistAiResult,
    ) {}

    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $reportDate): array
    {
        $summary = [
            'report_date' => $reportDate->toDateString(),
            'areas_checked' => 0,
            'objective_failed' => 0,
            'ai_reviewed' => 0,
            'baik' => 0,
            'need_review' => 0,
            'bermasalah' => 0,
            'persisted' => 0,
        ];

        try {
            $input = $this->inputBuilder->handle($reportDate);
            $summary['areas_checked'] = count($input['areas']);
        } catch (DatedTodolistNotFoundException) {
            $audit = $this->persistMissingDatedListFailure->handle($this->project(), $reportDate);
            $summary['objective_failed'] = 1;
            $summary['bermasalah'] = 1;
            $summary['persisted'] = $audit->wasRecentlyCreated ? 1 : 0;
            $summary['missing_dated_todolist'] = true;

            return $summary;
        }

        $project = $this->project();

        foreach ($input['areas'] as $area) {
            $objectiveResult = $this->objectiveRules->handle($area, $reportDate);

            if (! $objectiveResult->passed) {
                $summary['objective_failed']++;
                $summary['bermasalah']++;
                $audit = $this->persistObjectiveFailure->handle($project, $reportDate, $objectiveResult);

                if ($audit->wasRecentlyCreated) {
                    $summary['persisted']++;
                }

                continue;
            }

            if ($this->hasFinalResult($project, $reportDate, (string) $area['area_external_id'])) {
                continue;
            }

            $summary['ai_reviewed']++;
            $aiResponse = $this->visionReview->review($this->requestBuilder->handle($area, $reportDate));
            $aiResult = $this->resultResolver->handle($area, $aiResponse);
            $audit = $this->persistAiResult->handle($project, $reportDate, $aiResult);

            if ($audit->wasRecentlyCreated) {
                $summary['persisted']++;
            }

            match ($audit->status->value) {
                'Baik' => $summary['baik']++,
                'Need Review' => $summary['need_review']++,
                'Bermasalah' => $summary['bermasalah']++,
                default => null,
            };
        }

        return $summary;
    }

    private function project(): BasecampProject
    {
        return BasecampProject::query()
            ->where('basecamp_account_id', (string) config('basecamp.account_id'))
            ->where('basecamp_project_id', (string) config('basecamp.project_id'))
            ->firstOrFail();
    }

    private function hasFinalResult(BasecampProject $project, CarbonImmutable $reportDate, string $areaIdentity): bool
    {
        return $project->dailyAreaAudits()
            ->whereDate('report_date', $reportDate->toDateString())
            ->where('area_identity', $areaIdentity)
            ->exists();
    }
}
