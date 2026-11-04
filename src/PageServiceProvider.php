<?php

namespace Fayne\Page;

use Fayne\CMS\Events\PublicationSucceeded;
use Fayne\CMS\Http\Middleware\ResolvePublicTenant;
use Fayne\CMS\Http\Middleware\SelectContentRegion;
use Fayne\CMS\Support\Authorization\PermissionRegistry;
use Fayne\CMS\Support\ReleaseSnapshotRegistry;
use Fayne\Page\Console\InstallCommand;
use Fayne\Page\Http\Controllers\ComponentMockupController;
use Fayne\Page\Policies\PageComponentFieldPolicy;
use Fayne\Page\Policies\PageComponentPolicy;
use Fayne\Page\Policies\PagePolicy;
use Fayne\Page\Policies\PageRevisionPolicy;
use Fayne\Page\Policies\PageWidgetFieldPolicy;
use Fayne\Page\Policies\PageWidgetPolicy;
use Fayne\Page\Support\PageDefinitionsReleaseSnapshotProvider;
use Fayne\Page\Support\PageReleaseSnapshotProvider;
use Fayne\Page\Support\PromoteReleasedPages;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(PermissionRegistry::class, function ($registry): void {
            $registry->register('page', require __DIR__.'/../resources/permissions.php');
        });
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-page');
        $this->app->afterResolving(ReleaseSnapshotRegistry::class, function ($registry): void {
            if (! isset($registry->all()['global_page_definitions'])) {
                $registry->register(new PageDefinitionsReleaseSnapshotProvider);
            }
            if (! isset($registry->all()['page'])) {
                $registry->register(new PageReleaseSnapshotProvider);
            }
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'filament-page-migrations');
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/filament-page.php', 'filament-page');

        if (! config()->has('filesystems.disks.page-component-mockups')) {
            config(['filesystems.disks.page-component-mockups' => [
                'driver' => 'local',
                'root' => __DIR__.'/../resources/images/mockup',
                'url' => url('/filament-page/mockups'),
                'visibility' => 'public',
                'throw' => false,
            ]]);
        }

        Event::listen(PublicationSucceeded::class, PromoteReleasedPages::class);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-page');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        if (! $this->app->routesAreCached()) {
            Route::get('filament-page/mockups/{filename}', ComponentMockupController::class)
                ->where('filename', '[a-z0-9-]+-wireframe\.svg')
                ->name('filament-page.mockups');

            Route::prefix(config('filament-cms.headless.prefix', 'headless'))->middleware(['api', ResolvePublicTenant::class])->group(__DIR__.'/../routes/headless.php');
        }
        $this->commands([InstallCommand::class]);
        if (! $this->app->routesAreCached()) {
            if (config('filament-page.use_default_frontend_routes')) {
                Route::prefix('modules/pages')->middleware(['api', ResolvePublicTenant::class, SelectContentRegion::class])->name('modules.pages.')->group(__DIR__.'/../routes/frontend.php');
            }
            $this->loadRoutesFrom(__DIR__.'/../routes/preview.php');
        }
        Gate::policy(config('filament-page.models.page_template'), PageComponentPolicy::class);
        Gate::policy(config('filament-page.models.page_revision'), PageRevisionPolicy::class);
        Gate::policy(config('filament-page.models.page_widget_field'), PageWidgetFieldPolicy::class);
        Gate::policy(config('filament-page.models.page_widget'), PageWidgetPolicy::class);
        Gate::policy(config('filament-page.models.page_component_field'), PageComponentFieldPolicy::class);
        Gate::policy(config('filament-page.models.page_component'), PageComponentPolicy::class);
        Gate::policy(config('filament-page.models.page'), PagePolicy::class);
        $this->publishes([__DIR__.'/../config/filament-page.php' => config_path('filament-page.php')], 'filament-page-config');
    }
}
