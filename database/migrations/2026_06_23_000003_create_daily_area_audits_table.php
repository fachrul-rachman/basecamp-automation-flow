<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_area_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('basecamp_projects')->cascadeOnDelete();
            $table->date('report_date');
            $table->string('area_identity');
            $table->string('area_name');
            $table->string('basecamp_todo_id')->nullable();
            $table->text('basecamp_todo_url')->nullable();
            $table->unsignedSmallInteger('photo_count')->default(0);
            $table->timestampTz('first_upload_at')->nullable();
            $table->boolean('system_check_passed')->default(false);
            $table->string('ai_result', 32)->nullable();
            $table->json('ai_reasons')->nullable();
            $table->string('status', 32);
            $table->string('reason');
            $table->timestampTz('finalized_at');
            $table->string('notion_delivery_status', 32)->default('pending');
            $table->string('notion_page_id')->nullable();
            $table->unsignedSmallInteger('notion_attempts')->default(0);
            $table->text('last_notion_error')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'report_date', 'area_identity'], 'daily_audits_project_date_area_unique');
            $table->index(['notion_delivery_status', 'notion_attempts'], 'daily_audits_notion_retry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_area_audits');
    }
};
