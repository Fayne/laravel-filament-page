<?php

namespace Fayne\Page\Filament\Resources\PageResource\Pages;

use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageResource;
use Fayne\Page\Support\ComponentPreview;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ViewPage extends ViewRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageResource::class;

    protected string $view = 'filament-page::filament.page-detail';

    public function infolist(Schema $schema): Schema
    {
        $tabs = [];
        foreach ([
            'basic' => ['name', 'path', 'type', 'locale', 'is_active'],
            'content' => [],
            'seo' => ['title', 'description', 'keywords', 'meta', 'no_index', 'no_follow'],
            'advanced' => ['body_classes', 'main_classes'],
        ] as $tab => $fields) {
            $entries = [];
            foreach ($fields as $field) {
                $entries[] = TextEntry::make($field)
                    ->label(__('filament-page::translations.'.match ($field) {
                        'is_active' => 'active',
                        default => $field,
                    }))
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : (is_bool($state) ? ($state ? '✓' : '—') : $state));
            }
            if ($tab === 'content') {
                $entries[] = View::make('filament-page::filament.detail-content');
            }
            $tabs[] = Tab::make(__('filament-page::translations.'.$tab))->schema($entries);
        }

        return $schema->components([Tabs::make('page-detail')->tabs($tabs)->columnSpanFull()]);
    }

    public function componentPreviews(): array
    {
        $model = config('filament-page.models.page_component');

        return (new $model)->setConnection($this->getRecord()->getConnectionName())->newQuery()->get()
            ->mapWithKeys(fn ($component) => [$component->slug => [
                'name' => $component->name,
                'svg' => ComponentPreview::wireframe($component->mockup),
                'url' => ComponentPreview::url($component->mockup),
            ]])->all();
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
