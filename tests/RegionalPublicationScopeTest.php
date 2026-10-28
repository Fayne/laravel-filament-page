<?php

use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Publishing\PublicationLifecycle;
use Fayne\CMS\Support\ReleaseSnapshotRegistry;
use Fayne\Page\Models\Page;
use Fayne\Page\Tests\TestCase;
use Fayne\PostReleaseStatus\Services\Scope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class RegionalPublicationScopeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.connections.regional', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $app['config']->set('filament-cms.regions', [['id' => 'regional', 'database' => 'regional']]);
        $app['config']->set('filament-cms.multilingual_enabled', false);
        $app['config']->set('filament-cms.deployment.frontend_driver', 'astro');
        $app['config']->set('filament-cms.deployment.build_webhook_url', 'https://build.test');
        $app['config']->set('filament-cms.deployment.webhook_secret', 'secret');
    }

    public function test_fresh_regional_install_uses_one_scope_for_preview_start_and_history(): void
    {
        require_once dirname(__DIR__, 2).'/laravel-filament-post-release-status/src/Services/Scope.php';
        $this->assertFalse(Schema::hasTable('nova_pages'));
        $this->assertTrue(Schema::connection('regional')->hasTable('nova_pages'));
        Page::on('regional')->create(['name' => 'Regional', 'path' => '/', 'is_active' => true]);
        $context = Scope::context([]);
        $this->assertSame('regional', $context['region']);
        $preview = new DeploymentRecord;
        $preview->forceFill(['deployment_contents' => $context]);
        $entry = app(ReleaseSnapshotRegistry::class)->get('page')->snapshot($preview)[0];
        $this->assertSame('Regional', $entry['projection']['name']);
        Http::fake(['*' => Http::response([], 202)]);
        $release = app(PublicationLifecycle::class)->start([]);
        $this->assertSame($context['region'], $release->releaseManifest->context['region']);
        $this->assertSame(Scope::key((int) $release->tenant_id, $context), $release->scope_key);
    }
}
