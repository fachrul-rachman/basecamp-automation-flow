<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_area_audits', function (Blueprint $table): void {
            $table->timestampTz('notion_delivered_at')->nullable()->after('notion_page_id');
        });
    }

    public function down(): void
    {
        Schema::table('daily_area_audits', function (Blueprint $table): void {
            $table->dropColumn('notion_delivered_at');
        });
    }
};
