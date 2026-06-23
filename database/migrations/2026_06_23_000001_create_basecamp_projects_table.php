<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basecamp_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('basecamp_account_id');
            $table->string('basecamp_project_id');
            $table->string('name');
            $table->string('workflow_type', 64);
            $table->string('notion_database_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['basecamp_account_id', 'basecamp_project_id'], 'bc_projects_external_unique');
            $table->index(['workflow_type', 'active'], 'bc_projects_workflow_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basecamp_projects');
    }
};
