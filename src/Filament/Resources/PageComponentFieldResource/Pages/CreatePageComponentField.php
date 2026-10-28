<?php

namespace Fayne\Page\Filament\Resources\PageComponentFieldResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Page\Filament\Resources\PageComponentFieldResource;

class CreatePageComponentField extends CreateRecord
{
    protected static string $resource = PageComponentFieldResource::class;

    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return ['page_component_id' => $data['page_component_id']];
    }
}
