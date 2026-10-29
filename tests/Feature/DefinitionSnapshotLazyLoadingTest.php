<?php

use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Services\ReleaseManifestService;
use Illuminate\Database\Eloquent\Model;

it('freezes page definition fields with lazy loading disabled', function (string $kind, bool $regional) {
    $connection = $regional ? 'snapshot_region' : 'sqlite';
    if ($regional) {
        config(['database.connections.snapshot_region' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'filament-cms.regions' => [['id' => 'target', 'database' => $connection]]]);
        (require dirname(__DIR__, 2) . '/database/migrations/2026_10_20_100000_create_page_content_tables.php')->up();
    }
    $model = config("filament-page.models.page_{$kind}");
    foreach ([1, 2] as $index) {
        $definition = $model::on($connection)->create(['name' => "Definition {$index}", 'slug' => "definition-{$index}"]);
        $definition->fields()->create(['title' => 'Late', 'slug' => 'late', 'resolver' => 'text', 'weight' => 9]);
        $definition->fields()->create(['title' => 'Early', 'slug' => 'early', 'resolver' => 'text', 'weight' => 1, 'options' => ['nested' => ['value' => 'preserved']]]);
    }
    if ($regional) {
        $model::on('sqlite')->create(['name' => 'Other database', 'slug' => 'definition-1']);
    }
    $release = DeploymentRecord::create(['deployment_type' => 'full', 'status' => 0, 'deployment_contents' => ['region' => $regional ? 'target' : '']]);
    $previous = Model::preventsLazyLoading();
    Model::preventLazyLoading();
    try {
        $definitions = app(ReleaseManifestService::class)->freeze($release)->entries['global_page_definitions'][0]['projection'][$kind];
        expect($definitions)->toHaveCount(2);
        foreach ($definitions as $definition) {
            expect(array_column($definition['fields'], 'slug'))->toBe(['early', 'late'])
                ->and($definition['fields'][0]['options'])->toBe(['nested' => ['value' => 'preserved']]);
        }
    } finally {
        Model::preventLazyLoading($previous);
    }
})->with(['component', 'widget'])->with([false, true]);
