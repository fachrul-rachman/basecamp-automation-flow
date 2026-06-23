<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Core\Shared\OpenAI\Data\VisionReviewResponse;
use App\Modules\KpusGaHw\Application\Data\AiAuditResult;
use App\Modules\KpusGaHw\Domain\Enums\AiReviewResult;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use Carbon\CarbonImmutable;

class ResolveAiAuditResult
{
    /**
     * @param  array<string, mixed>  $area
     */
    public function handle(array $area, VisionReviewResponse $response): AiAuditResult
    {
        if (! $response->successful || $response->json === null) {
            return $this->needReview($area, null, [$response->failureReason ?? 'AI gagal membaca foto']);
        }

        $aiResult = $this->aiResult($response->json['result'] ?? null);
        $reasons = $this->reasons($response->json['reasons'] ?? null);

        if ($aiResult === null || $reasons === []) {
            return $this->needReview($area, null, ['AI gagal membaca foto']);
        }

        return match ($aiResult) {
            AiReviewResult::Ok => new AiAuditResult(
                areaExternalId: (string) $area['area_external_id'],
                areaName: (string) $area['area_name'],
                todoUrl: (string) $area['todo_url'],
                photoCount: (int) $area['image_count'],
                firstUploadAt: $this->firstUploadAt($area),
                systemCheckPassed: true,
                aiResult: $aiResult,
                aiReasons: $reasons,
                status: AuditStatus::Baik,
                reason: 'Sesuai ketentuan',
            ),
            AiReviewResult::Anomaly, AiReviewResult::Uncertain => $this->needReview($area, $aiResult, $reasons),
        };
    }

    /**
     * @param  array<string, mixed>  $area
     * @param  list<string>  $reasons
     */
    private function needReview(array $area, ?AiReviewResult $aiResult, array $reasons): AiAuditResult
    {
        $reason = $reasons[0] ?? 'AI gagal membaca foto';

        return new AiAuditResult(
            areaExternalId: (string) $area['area_external_id'],
            areaName: (string) $area['area_name'],
            todoUrl: (string) $area['todo_url'],
            photoCount: (int) $area['image_count'],
            firstUploadAt: $this->firstUploadAt($area),
            systemCheckPassed: true,
            aiResult: $aiResult,
            aiReasons: $reasons,
            status: AuditStatus::NeedReview,
            reason: $reason,
        );
    }

    private function aiResult(mixed $value): ?AiReviewResult
    {
        return is_string($value) ? AiReviewResult::tryFrom($value) : null;
    }

    /** @return list<string> */
    private function reasons(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            fn (mixed $reason): bool => is_string($reason) && trim($reason) !== '',
        ));
    }

    /** @param array<string, mixed> $area */
    private function firstUploadAt(array $area): ?CarbonImmutable
    {
        $value = $area['first_upload_at'] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
