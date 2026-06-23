<?php

namespace App\Core\Shared\Basecamp\Models;

use App\Modules\KpusGaHw\Models\DailyAreaAudit;
use Database\Factories\BasecampProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BasecampProject extends Model
{
    /** @use HasFactory<BasecampProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'basecamp_account_id',
        'basecamp_project_id',
        'name',
        'workflow_type',
        'notion_database_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function dailyAreaAudits(): HasMany
    {
        return $this->hasMany(DailyAreaAudit::class, 'project_id');
    }

    protected static function newFactory(): BasecampProjectFactory
    {
        return BasecampProjectFactory::new();
    }
}
