<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (array_values(array_unique(array_filter(array_column(config('filament-cms.regions', []), 'database')))) ?: [null] as $connection) {
            Schema::connection($connection)->table(config('filament-page.tables.page_revisions', 'nova_page_revisions'), function (Blueprint $table): void {
                $table->string('publication_manifest_key', 100)->nullable();
                $table->unique(['page_id', 'publication_manifest_key'], 'page_revision_publication_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (array_values(array_unique(array_filter(array_column(config('filament-cms.regions', []), 'database')))) ?: [null] as $connection) {
            Schema::connection($connection)->table(config('filament-page.tables.page_revisions', 'nova_page_revisions'), function (Blueprint $table): void {
                $table->dropUnique('page_revision_publication_unique');
                $table->dropColumn('publication_manifest_key');
            });
        }
    }
};
