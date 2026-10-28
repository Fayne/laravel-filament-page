<?php

namespace Fayne\Page\Filament\Resources\PageComponentFieldResource\Pages;

use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageComponentFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPageComponentFields extends ListRecords
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageComponentFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
