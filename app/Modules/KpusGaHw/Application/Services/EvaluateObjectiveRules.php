<?php

namespace App\Modules\KpusGaHw\Application\Services;

use App\Modules\KpusGaHw\Application\Data\ObjectiveAuditResult;
use Carbon\CarbonImmutable;

class EvaluateObjectiveRules
{
    /** @param array<string, mixed> $area */
    public function handle(array $area, CarbonImmutable $reportDate): ObjectiveAuditResult
    {
        $photoCount = (int) ($area['image_count'] ?? 0);
        $firstUploadAt = $this->firstUploadAt($area);
        $minPhotos = (int) config('kpus-ga-hw.min_photos', 2);

        if ($photoCount < $minPhotos) {
            return $this->failed($area, $photoCount, $firstUploadAt, "Foto kurang: {$photoCount} dari minimum {$minPhotos}");
        }

        if ($firstUploadAt === null) {
            return $this->failed($area, $photoCount, null, 'Foto tidak memiliki waktu upload');
        }

        $businessTime = $firstUploadAt->setTimezone((string) config('kpus-ga-hw.timezone'));

        if ($businessTime->toDateString() !== $reportDate->toDateString()) {
            return $this->failed($area, $photoCount, $firstUploadAt, 'Tanggal upload pertama tidak sesuai tanggal laporan');
        }

        $runTime = (string) config('kpus-ga-hw.run_time', '09:00');
        $deadline = CarbonImmutable::parse($reportDate->toDateString().' '.$runTime, (string) config('kpus-ga-hw.timezone'));

        if ($businessTime->greaterThan($deadline)) {
            return $this->failed($area, $photoCount, $firstUploadAt, 'Upload pertama melewati 09:00 WIB');
        }

        return new ObjectiveAuditResult(
            areaExternalId: (string) $area['area_external_id'],
            areaName: (string) $area['area_name'],
            todoUrl: (string) $area['todo_url'],
            photoCount: $photoCount,
            firstUploadAt: $firstUploadAt,
            passed: true,
            reason: 'Objective check passed',
        );
    }

    /** @param array<string, mixed> $area */
    private function failed(array $area, int $photoCount, ?CarbonImmutable $firstUploadAt, string $reason): ObjectiveAuditResult
    {
        return new ObjectiveAuditResult(
            areaExternalId: (string) $area['area_external_id'],
            areaName: (string) $area['area_name'],
            todoUrl: (string) $area['todo_url'],
            photoCount: $photoCount,
            firstUploadAt: $firstUploadAt,
            passed: false,
            reason: $reason,
        );
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
