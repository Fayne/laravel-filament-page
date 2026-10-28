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
        $panel->resources([Resources\PageTemplateResource::class, Resources\PageResource::class, Resources\PageComponentResource::class, Resources\PageComponentFieldResource::class, Resources\PageWidgetResource::class, Resources\PageWidgetFieldResource::class, Resources\PageRevisionResource::class]);
    }

    public function boot(Panel $panel): void {}
}
