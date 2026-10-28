<?php

use Fayne\CMS\Support\TranslationGroupMigration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->connections() as $connection) {
            TranslationGroupMigration::up(config('filament-page.tables.pages', 'nova_pages'), $connection);
        }
    }

    public function down(): void
    {
        foreach ($this->connections() as $connection) {
            TranslationGroupMigration::down(config('filament-page.tables.pages', 'nova_pages'), $connection);
        }
    }

    private function connections(): array
    {
        return array_values(array_unique(array_filter(array_column(config('filament-cms.regions', []), 'database')))) ?: [null];
    }
};
