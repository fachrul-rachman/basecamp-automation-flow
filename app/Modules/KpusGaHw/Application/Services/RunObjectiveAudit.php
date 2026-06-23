<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\Basecamp\Models\BasecampProject;
use Carbon\CarbonImmutable;

class RunObjectiveAudit
{
    public function __construct(
        private readonly BuildReadOnlyAuditInput $inputBuilder,
        private readonly EvaluateObjectiveRules $evaluator,
        private readonly PersistObjectiveFailure $persistObjectiveFailure,
    ) {}

    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $reportDate): array
    {
        $input = $this->inputBuilder->handle($reportDate);
        $project = $this->project();
        $failed = 0;
        $passed = 0;
        $persisted = 0;

        foreach ($input['areas'] as $area) {
            $result = $this->evaluator->handle($area, $reportDate);

            if ($result->passed) {
                $passed++;

                continue;
            }

            $failed++;
            $audit = $this->persistObjectiveFailure->handle($project, $reportDate, $result);

            if ($audit->wasRecentlyCreated) {
                $persisted++;
            }
        }

        return [
            'report_date' => $reportDate->toDateString(),
            'areas_checked' => count($input['areas']),
            'objective_passed' => $passed,
            'objective_failed' => $failed,
            'failures_persisted' => $persisted,
        ];
    }

    private function project(): BasecampProject
    {
        return BasecampProject::query()
            ->where('basecamp_account_id', (string) config('basecamp.account_id'))
            ->where('basecamp_project_id', (string) config('basecamp.project_id'))
            ->firstOrFail();
    }
}
