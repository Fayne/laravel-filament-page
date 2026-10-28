<?php

namespace Fayne\Page\Filament\Resources\PageTemplateResource\Pages;

use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageTemplateResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePageTemplates extends ManageRecords
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageTemplateResource::class;
}
