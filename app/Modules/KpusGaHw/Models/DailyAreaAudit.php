<?php

namespace App\Modules\KpusGaHw\Models;

use App\Core\Shared\Basecamp\Models\BasecampProject;
use App\Modules\KpusGaHw\Domain\Enums\AiReviewResult;
use App\Modules\KpusGaHw\Domain\Enums\AuditStatus;
use App\Modules\KpusGaHw\Domain\Enums\NotionDeliveryStatus;
use Database\Factories\DailyAreaAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyAreaAudit extends Model
{
    /** @use HasFactory<DailyAreaAuditFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'report_date',
        'area_identity',
        'area_name',
        'basecamp_todo_id',
        'basecamp_todo_url',
        'photo_count',
        'first_upload_at',
        'system_check_passed',
        'ai_result',
        'ai_reasons',
        'status',
        'reason',
        'finalized_at',
        'notion_delivery_status',
        'notion_page_id',
        'notion_delivered_at',
        'notion_attempts',
        'last_notion_error',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'photo_count' => 'integer',
            'first_upload_at' => 'datetime',
            'system_check_passed' => 'boolean',
            'ai_result' => AiReviewResult::class,
            'ai_reasons' => 'array',
            'status' => AuditStatus::class,
            'finalized_at' => 'datetime',
            'notion_delivery_status' => NotionDeliveryStatus::class,
            'notion_delivered_at' => 'datetime',
            'notion_attempts' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(BasecampProject::class, 'project_id');
    }

    protected static function newFactory(): DailyAreaAuditFactory
    {
        return DailyAreaAuditFactory::new();
    }
}
