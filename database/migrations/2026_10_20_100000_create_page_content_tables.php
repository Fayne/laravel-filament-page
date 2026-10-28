<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->connections() as $connection) {
            $this->createTables($connection);
        }
    }

    public function down(): void
    {
        foreach ($this->connections() as $connection) {
            $this->dropTables($connection);
        }
    }

    private function connections(): array
    {
        return array_values(array_unique(array_filter(array_column(config('filament-cms.regions', []), 'database')))) ?: [null];
    }

    protected function createTables(?string $connection): void
    {
        foreach (['pages', 'page_revisions'] as $name) {
            Schema::connection($connection)->create(config('filament-page.tables.'.$name), function (Blueprint $table) use ($name) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                if ($name === 'page_revisions') {
                    $table->foreignId('page_id')->constrained(config('filament-page.tables.pages'))->cascadeOnDelete();
                    $table->boolean('is_stash')->default(false);
                    $table->timestamp('released_at')->nullable();
                } else {
                    $table->boolean('changed')->default(false);
                    $table->boolean('has_stash')->default(false);
                    $table->unsignedInteger('weight')->default(0);
                }
                $table->string('path')->nullable();
                $table->string('type')->default('basic');
                $table->string('name')->nullable();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                foreach (['keywords', 'meta', 'components', 'fixed_components', 'extra_data'] as $column) {
                    $table->json($column)->nullable();
                }
                foreach (['no_index', 'no_follow', 'is_active'] as $column) {
                    $table->boolean($column)->default(false);
                }
                $table->string('body_classes')->nullable();
                $table->string('main_classes')->nullable();
                $table->string('locale')->default('en');
                $table->unsignedBigInteger('fallback_id')->nullable();
                if ($name === 'pages') {
                    $table->unique(['tenant_id', 'locale', 'path']);
                }
                $table->timestamps();
            });
        }
        foreach (['component', 'widget'] as $kind) {
            Schema::connection($connection)->create(config("filament-page.tables.page_{$kind}s"), function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name');
                $table->string('slug');
                $table->unique(['tenant_id', 'slug']);
                $table->text('description')->nullable();
                $table->unsignedInteger('weight')->default(0);
                $table->json('mockup')->nullable();
                $table->json('specifications')->nullable();
                $table->unsignedInteger('usage')->default(0);
                $table->timestamps();
            });
            Schema::connection($connection)->create(config("filament-page.tables.page_{$kind}_fields"), function (Blueprint $table) use ($kind) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId($kind === 'widget' ? 'nova_page_widget_id' : 'page_component_id')->constrained(config("filament-page.tables.page_{$kind}s"))->cascadeOnDelete();
                $table->string('title');
                $table->string('slug')->nullable();
                $table->string('resolver');
                $table->json('options')->nullable();
                $table->json('rules')->nullable();
                $table->unsignedInteger('weight')->default(99);
                $table->timestamps();
            });
        }
    }

    protected function dropTables(?string $connection): void
    {
        foreach (['page_widget_fields', 'page_widgets', 'page_component_fields', 'page_components', 'page_revisions', 'pages'] as $name) {
            Schema::connection($connection)->dropIfExists(config('filament-page.tables.'.$name));
        }
    }
};
