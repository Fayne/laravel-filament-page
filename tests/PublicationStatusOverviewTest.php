<?php

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Models\Page;
use Fayne\Page\Tests\TestCase;
use Fayne\PostReleaseStatus\Filament\Pages\PublishStatus;
use Fayne\PostReleaseStatus\PostReleaseStatusServiceProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

spl_autoload_register(function ($class) {
    $prefix = 'Fayne\\PostReleaseStatus\\';
    if (str_starts_with($class, $prefix)) {
        require_once dirname(__DIR__, 2) . '/laravel-filament-post-release-status/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

class PublicationStatusOverviewTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PostReleaseStatusServiceProvider::class];
    }

    public function test_enabled_homepage_variants_both_appear_and_details_load_only_on_request(): void
    {
        config(['filament-cms.multilingual_enabled' => true, 'filament-cms.supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文']]);
        Page::create(['name' => 'Demo store homepage', 'path' => '/', 'locale' => 'en', 'is_active' => true, 'description' => 'English detail only']);
        Page::create(['name' => '示例商店首页', 'path' => '/', 'locale' => 'zh_CN', 'is_active' => true, 'description' => 'Chinese detail only']);
        Page::create(['name' => 'Inactive draft', 'path' => '/draft', 'locale' => 'zh_CN', 'is_active' => false]);
        $admin = Admin::factory()->create();
        $this->grantPermissions($admin, ['publication.view']);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $component = Livewire::test(PublishStatus::class)->assertOk()->assertSee('Demo store homepage')->assertSee('示例商店首页')
            ->assertDontSee('Inactive draft')->assertDontSee('English detail only')->assertDontSee('Chinese detail only');
        $pages = array_values(array_filter($component->viewData('changes')['added'], fn ($change) => $change['after']['entity_type'] === 'page'));
        $this->assertCount(2, $pages);
        $chinese = collect($pages)->firstWhere('after.locale', 'zh_CN');
        $this->getJson($chinese['details_url'])->assertOk()->assertSee('Chinese detail only')->assertDontSee('English detail only');
        Livewire::withQueryParams(['locale' => 'zh_CN'])->test(PublishStatus::class)->assertOk()->assertSee('示例商店首页')->assertDontSee('Demo store homepage');

        $other = Tenant::create(['name' => 'Other tenant', 'slug' => 'other-publication-tenant', 'is_active' => true]);
        parse_str(parse_url($chinese['details_url'], PHP_URL_QUERY), $query);
        $foreign = URL::temporarySignedRoute('post-release-status.change', now()->addMinutes(5), [...Arr::except($query, ['signature', 'expires']), 'publicationTenant' => $other->id]);
        $this->getJson($foreign)->assertNotFound();
        $this->assertNotSame($other->id, app(TenantContext::class)->requireId());
    }
}
