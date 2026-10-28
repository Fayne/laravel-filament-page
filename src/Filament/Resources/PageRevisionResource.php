<?php

namespace Fayne\Page\Filament\Resources;

use Fayne\Page\Models\PageRevision;
use Fayne\Page\Services\PageRevisionService;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PageRevisionResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('filament-page::navigation.page_revisions');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = PageRevision::class;

    public static function getModelLabel(): string
    {
        return __('filament-page::ui.field_model_pagerevision');
    }

    public static function getModel(): string
    {
        return config('filament-page.models.page_revision', static::$model);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('page_id')->label(__('filament-page::ui.field_page_id')), TextColumn::make('name')->label(__('filament-page::ui.field_name')), TextColumn::make('released_at')->label(__('filament-page::ui.field_released_at'))])->recordActions([
            Action::make('restore')->label(__('filament-page::translations.restore_revision'))->requiresConfirmation()
                ->authorize(fn ($record) => Gate::allows('update', $record->page))
                ->action(function ($record): void {
                    Gate::authorize('update', $record->page);
                    app(PageRevisionService::class)->restore($record->page, $record);
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => PageRevisionResource\Pages\ListPageRevisions::route('/')];
    }
}
