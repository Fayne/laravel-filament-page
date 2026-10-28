<?php

namespace Fayne\Page\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class PagePlugin implements Plugin
{
    public static function make(): static
    {
        return new static;
    }

    public function getId(): string
    {
        return 'filament-page';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([Resources\PageTemplateResource::configuredResource(), Resources\PageResource::configuredResource(), Resources\PageComponentResource::configuredResource(), Resources\PageComponentFieldResource::configuredResource(), Resources\PageWidgetResource::configuredResource(), Resources\PageWidgetFieldResource::configuredResource(), Resources\PageRevisionResource::configuredResource()]);
    }

    public function boot(Panel $panel): void {}
}
