<?php

namespace Fayne\Page\Filament\Resources\PageRevisionResource\Pages;

use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageRevisionResource;
use Filament\Resources\Pages\ListRecords;

class ListPageRevisions extends ListRecords
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageRevisionResource::class;
}
