<?php

namespace App\Modules\KpusGaHw\Application\Data;

use App\Modules\KpusGaHw\Domain\Enums\AiReviewResult;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use Carbon\CarbonImmutable;

final readonly class AiAuditResult
{
    /**
     * @param  list<string>|null  $aiReasons
     */
    public function __construct(
        public string $areaExternalId,
        public string $areaName,
        public string $todoUrl,
        public int $photoCount,
        public ?CarbonImmutable $firstUploadAt,
        public bool $systemCheckPassed,
        public ?AiReviewResult $aiResult,
        public ?array $aiReasons,
        public AuditStatus $status,
        public string $reason,
    ) {}
}
