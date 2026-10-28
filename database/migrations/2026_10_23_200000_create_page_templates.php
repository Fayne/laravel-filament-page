<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connections(): array
    {
        return array_values(array_unique(array_filter(array_column(config('filament-cms.regions', []), 'database')))) ?: [null];
    }

    public function up(): void
    {
        foreach ($this->connections() as $connection) {
            Schema::connection($connection)->create('cms_page_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type');
                $table->boolean('is_active')->default(true);
                $table->json('structure');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->connections() as $connection) {
            Schema::connection($connection)->dropIfExists('cms_page_templates');
        }
    }
};
