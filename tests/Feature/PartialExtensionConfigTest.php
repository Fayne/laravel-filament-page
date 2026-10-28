<?php

namespace Fayne\Page\Tests\Feature;

use Fayne\Page\Filament\Resources\PageComponentResource;
use Fayne\Page\Models\PageComponent;
use Fayne\Page\Tests\Fixtures\HostPage;
use Fayne\Page\Tests\Fixtures\HostPageResource;
use Fayne\Page\Tests\TestCase;
use Filament\Facades\Filament;

require_once __DIR__.'/../Fixtures/ResourceExtensions.php';

class PartialExtensionConfigTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-page.models', ['page' => HostPage::class]);
        $app['config']->set('filament-page.resources', ['page' => HostPageResource::class]);
    }

    public function test_partial_maps_preserve_default_models_and_resources(): void
    {
        $this->assertSame(HostPage::class, config('filament-page.models.page'));
        $this->assertSame(PageComponent::class, config('filament-page.models.page_component'));
        $this->assertSame(PageComponentResource::class, config('filament-page.resources.page_component'));
        $this->assertContains(HostPageResource::class, Filament::getPanel('admin')->getResources());
    }
}
