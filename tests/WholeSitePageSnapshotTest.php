<?php

use Fayne\CMS\Events\PublicationSucceeded;
use Fayne\CMS\Models\DeploymentRecord;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Publishing\WholeSiteSnapshot;
use Fayne\CMS\Services\ReleaseManifestService;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Models\Page;
use Fayne\Page\Support\PromoteReleasedPages;
use Fayne\Page\Tests\TestCase;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WholeSitePageSnapshotTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        foreach (['cn', 'us'] as $region) {
            $app['config']->set('database.connections.' . $region, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        }
        $app['config']->set('filament-cms.regions', [['id' => 'cn', 'database' => 'cn'], ['id' => 'us', 'database' => 'us']]);
        $app['config']->set('filament-cms.multilingual_enabled', true);
        $app['config']->set('filament-cms.supported_locales', ['en' => 'English', 'zh_CN' => 'Chinese']);
        $app['config']->set('filament-cms.publication_routing', ['region_mode' => 'host', 'regions' => [['id' => 'cn', 'host' => 'cn.test'], ['id' => 'us', 'host' => 'us.test']], 'language_prefixes' => ['en' => '/en', 'zh_CN' => '/zh']]);
    }

    private function seedPages(): DeploymentRecord
    {
        $tenant = Tenant::findOrFail(app(TenantContext::class)->requireId());
        foreach (['cn', 'us'] as $region) {
            $tenant->domains()->create(['host' => $region . '.test', 'is_active' => true]);
            $group = (string) Str::uuid();
            foreach (['en', 'zh_CN'] as $locale) {
                Page::on($region)->create(['name' => $region . ' ' . $locale, 'title' => $locale, 'path' => '/about', 'locale' => $locale, 'is_active' => true, 'translation_group_id' => $group]);
            }
        }

        return DeploymentRecord::create(['title' => 'Whole site', 'deployment_type' => 'frontend', 'deployment_contents' => ['publication_mode' => 'whole_site']]);
    }

    public function test_reads_all_real_region_databases_and_freezes_translation_groups_without_id_guesses(): void
    {
        $release = $this->seedPages();
        $this->assertFalse(Schema::hasTable(config('filament-page.tables.pages')));
        $manifest = app(ReleaseManifestService::class)->freeze($release);
        $rows = $manifest->entries['page'];
        $this->assertCount(4, $rows);
        $this->assertSame('1', $rows[0]['entity_id']);
        $this->assertSame('1', $rows[2]['entity_id']);
        $this->assertSame('cn en', $rows[0]['projection']['name']);
        $this->assertSame('us en', $rows[2]['projection']['name']);
        $this->assertSame(['https://cn.test/en/about', 'https://cn.test/zh/about'], array_column($rows[0]['translations'], 'url'));
        $this->assertSame('https://us.test/zh/about', $rows[3]['url']);
        $this->assertArrayNotHasKey('translation_group_id', json_decode($manifest->public_payload, true)['entries']['page'][0]);
    }

    public function test_does_not_guess_missing_translations_from_overlapping_ids(): void
    {
        $release = $this->seedPages();
        Page::on('cn')->where('locale', 'zh_CN')->delete();
        $rows = app(WholeSiteSnapshot::class)->capture($release)['entries']['page'];
        $this->assertCount(1, $rows[0]['translations']);
        $this->assertSame('en', $rows[0]['translations'][0]['locale']);
    }

    public function test_keeps_existing_language_paths_when_no_additional_prefix_is_configured(): void
    {
        $release = $this->seedPages();
        foreach (['cn', 'us'] as $connection) {
            foreach (Page::on($connection)->get() as $page) {
                $page->forceFill(['path' => '/' . $page->locale . '/about'])->save();
            }
        }
        config(['filament-cms.publication_routing.language_prefixes' => []]);
        $rows = app(WholeSiteSnapshot::class)->capture($release)['entries']['page'];
        $this->assertSame('/en/about', $rows[0]['path']);
        $this->assertSame('/zh_CN/about', $rows[1]['path']);
        $this->assertSame('/en/about', Page::on('cn')->findOrFail(1)->path);
    }

    public function test_capture_always_uses_whole_site_provider_metadata_for_comparison_previews(): void
    {
        $release = $this->seedPages();
        $release->deployment_contents = ['site_id' => 'preview'];
        $rows = app(WholeSiteSnapshot::class)->capture($release)['entries']['page'];
        $this->assertCount(2, $rows[0]['translations']);
    }

    public function test_aborts_freeze_when_one_region_database_is_offline(): void
    {
        $release = $this->seedPages();
        config(['database.connections.us.database' => '/missing/path/region.sqlite']);
        DB::purge('us');
        try {
            app(ReleaseManifestService::class)->freeze($release);
            $this->fail('Unavailable regional connection must fail the complete freeze.');
        } catch (SQLiteDatabaseDoesNotExistException $exception) {
            $this->assertSame(0, $release->releaseManifest()->count());
        }
    }

    public function test_promotes_each_raw_source_only_once_when_two_regions_share_a_connection(): void
    {
        $release = $this->seedPages();
        config(['filament-cms.regions' => [['id' => 'cn', 'database' => 'cn'], ['id' => 'us', 'database' => 'cn']]]);
        $manifest = app(ReleaseManifestService::class)->freeze($release);
        $release->setRelation('releaseManifest', $manifest);
        DB::connection('cn')->enableQueryLog();
        app(PromoteReleasedPages::class)->handle(new PublicationSucceeded($release));
        $pageTable = DB::connection('cn')->getQueryGrammar()->wrapTable((new Page)->getTable());
        $updates = collect(DB::connection('cn')->getQueryLog())->filter(fn ($query) => str_starts_with($query['query'], 'select * from ' . $pageTable . ' '));
        $this->assertCount(2, $updates);
        $this->assertSame(2, DB::connection('cn')->table(config('filament-page.tables.page_revisions'))->count());
    }
    public function test_v2_page_api_requires_scope_for_paths_and_filters_frozen_targets(): void
    {
        $release = $this->seedPages();
        $release->forceFill(['tenant_id' => app(TenantContext::class)->requireId()])->save();
        $manifest = app(ReleaseManifestService::class)->freeze($release);
        config(['filament-cms.deployment.build_api_token' => 'build-secret', 'filament-cms.publication_routing' => null]);
        $this->withToken('build-secret');
        $url = '/headless/releases/' . $manifest->release_id . '/pages';
        $this->getJson($url)->assertOk()->assertJsonCount(4, 'data')->assertJsonMissingPath('data.0.content_snapshot')->assertJsonMissingPath('data.0.connection');
        $this->getJson($url . '?source_path=/about')->assertUnprocessable();
        $this->getJson($url . '?path=/en/about')->assertUnprocessable();
        $this->getJson($url . '?scope_id=foreign')->assertUnprocessable();
        $this->getJson($url . '?target_id=foreign')->assertUnprocessable();
        $this->getJson($url . '?target_id=' . $manifest->context['targets'][1]['target_id'])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.projection.name', 'us en');
        $this->getJson($url . '?source_path=/about&scope_id=' . $manifest->context['scopes'][3]['scope_id'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.url', 'https://us.test/zh/about');
    }

    public function test_v2_partial_regional_promotion_failure_retains_lock_and_retries_idempotently(): void
    {
        $this->seedPages();
        config(['filament-cms.deployment.frontend_driver' => 'astro', 'filament-cms.deployment.build_webhook_url' => 'https://build.test', 'filament-cms.deployment.webhook_secret' => 'secret']);
        \Illuminate\Support\Facades\Http::fake(function ($request) {
            $data = str_ends_with($request->url(), '/capabilities') ? ['schema_version' => 2, 'whole_site_v2' => true, 'activation' => 'shared_release_pointer', 'routing_modes' => ['host', 'path']]
                : ['schema_version' => 2, 'accepted' => true, ...array_intersect_key($request->data(), array_flip(['website_id', 'release_id', 'attempt_id', 'manifest_digest']))];
            $body = json_encode($data);
            $timestamp = (string) now()->timestamp;

            return \Illuminate\Support\Facades\Http::response($body, 200, ['X-Nova-CMS-Timestamp' => $timestamp, 'X-Nova-CMS-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, 'secret')]);
        });
        $lifecycle = app(\Fayne\CMS\Publishing\PublicationLifecycle::class);
        $release = $lifecycle->start([]);
        $manifest = $release->releaseManifest;
        $evidence = [...app(\Fayne\CMS\Publishing\WholeSitePublisher::class)->identity($release), 'success' => true, 'activation' => 'activated', 'targets' => array_map(fn ($target) => ['target_id' => $target['target_id'], 'status' => 'activated'], $manifest->context['targets'])];
        $fail = true;
        \Fayne\Page\Models\PageRevision::creating(function ($revision) use (&$fail) {
            if ($fail && $revision->getConnectionName() === 'us') {
                throw new RuntimeException('regional promotion failed');
            }
        });
        try {
            $lifecycle->completeWholeSite($release, $evidence);
            $this->fail('Promotion must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('regional promotion failed', $exception->getMessage());
        }
        $this->assertSame('frozen', $manifest->fresh()->status);
        $this->assertTrue(app(\Fayne\CMS\Publishing\TenantWriteBarrier::class)->isLocked());
        $this->assertSame(2, DB::connection('cn')->table(config('filament-page.tables.page_revisions'))->count());
        $this->assertSame(0, DB::connection('us')->table(config('filament-page.tables.page_revisions'))->count());
        $fail = false;
        $lifecycle->completeWholeSite($release, $evidence);
        $this->assertSame('succeeded', $manifest->fresh()->status);
        $this->assertFalse(app(\Fayne\CMS\Publishing\TenantWriteBarrier::class)->isLocked());
        foreach (['cn', 'us'] as $connection) {
            $this->assertSame(2, DB::connection($connection)->table(config('filament-page.tables.page_revisions'))->count());
        }
    }

}
