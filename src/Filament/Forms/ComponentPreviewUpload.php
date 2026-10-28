<?php

namespace Fayne\Page\Filament\Forms;

use Fayne\PageBuilder\Filament\Forms\Components\ContentFileUpload;

class ComponentPreviewUpload extends ContentFileUpload
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->label(__('filament-page::translations.component_preview'))->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp'])->maxSize(5120)
            ->disk(fn () => $this->getRecord()?->mockup['disk'] ?? config('filament-cms.media.disk', 'public'))
            ->directory('component-previews')->visibility('public')->preventFilePathTampering()
            ->helperText(__('filament-page::translations.component_preview_help'))
            ->mutateDehydratedStateUsing(function ($state) {
                if (! $state) {
                    return null;
                }
                $original = $this->getRecord()?->mockup;
                if (($original['path'] ?? null) === $state && ($original['disk'] ?? null) === $this->getDiskName()) {
                    return $original;
                }

                return ['disk' => $this->getDiskName(), 'path' => $state];
            });
    }

    public function getOriginalFilePaths(): array
    {
        $record = $this->getRecord();
        $asset = $record?->exists ? $record->fresh()?->mockup : null;

        return isset($asset['path']) && ($asset['disk'] ?? null) === $this->getDiskName() ? [$asset['path']] : [];
    }
}
