<?php

namespace App\Core\Shared\Scheduling\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $fillable = [
        'holiday_date',
        'name',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }

    protected static function newFactory(): HolidayFactory
    {
        return HolidayFactory::new();
    }
}
