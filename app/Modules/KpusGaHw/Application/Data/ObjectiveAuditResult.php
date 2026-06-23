<?php

namespace App\Modules\KpusGaHw\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ObjectiveAuditResult
{
    public function __construct(
        public string $areaExternalId,
        public string $areaName,
        public string $todoUrl,
        public int $photoCount,
        public ?CarbonImmutable $firstUploadAt,
        public bool $passed,
        public string $reason,
    ) {}
}
