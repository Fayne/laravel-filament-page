<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates revision publication keys and uniqueness in the initial regional schema and rolls back cleanly', function () {
    config(['database.connections.initial_schema' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
        'filament-cms.regions' => [['id' => 'initial', 'database' => 'initial_schema']]]);
    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_20_100000_create_page_content_tables.php';
    $migration->up();
    $pageId = DB::connection('initial_schema')->table(config('filament-page.tables.pages'))->insertGetId(['tenant_id' => 1, 'path' => '/initial']);
    $revisions = DB::connection('initial_schema')->table(config('filament-page.tables.page_revisions'));
    $values = ['tenant_id' => 1, 'page_id' => $pageId];
    $revisions->insert([...$values, 'publication_manifest_key' => 'release-1']);
    expect(fn () => $revisions->insert([...$values, 'publication_manifest_key' => 'release-1']))->toThrow(QueryException::class);
    $revisions->insert([...$values, 'publication_manifest_key' => null]);
    $revisions->insert([...$values, 'publication_manifest_key' => null]);
    expect($revisions->count())->toBe(3);
    $migration->down();
    expect(Schema::connection('initial_schema')->hasTable(config('filament-page.tables.page_revisions')))->toBeFalse();
    $migration->up();
    expect(Schema::connection('initial_schema')->hasColumn(config('filament-page.tables.page_revisions'), 'publication_manifest_key'))->toBeTrue();
});
