<?php

namespace Fayne\Page\Filament\Resources\PageResource\Pages;

use Fayne\CMS\Filament\Operations\EditRecord;
use Fayne\CMS\Support\ContentLocale;
use Fayne\Page\Filament\Resources\PageResource;
use Fayne\Page\Filament\Resources\PageResource\Pages\Concerns\BuildsPageOutline;
use Fayne\Page\Models\PageTemplate;
use Fayne\Page\Services\PageContentValidator;
use Fayne\Page\Services\PageRevisionService;
use Fayne\Page\Services\PageTemplateService;
use Fayne\Page\Services\PageTranslationService;
use Fayne\Page\Support\PagePreviewUrl;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EditPage extends EditRecord
{
    use BuildsPageOutline;

    protected static string $resource = PageResource::class;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['locale'] = $this->record->locale;
        if (($data['type'] ?? $this->record->type) !== $this->record->type && ! config('filament-page.page_type_changeable', false)) {
            throw ValidationException::withMessages(['data.type' => __('filament-page::translations.type_locked')]);
        }
        foreach (['keywords', 'meta', 'components', 'fixed_components'] as $field) {
            if (($data[$field] ?? null) === [] && $this->record->{$field} === null) {
                $data[$field] = null;
            }
        }
        try {
            app(PageContentValidator::class)->validate($data, $this->record);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all());
        }

        $candidate = clone $this->record;
        $candidate->fill($data);
        $published = $this->record->currentPublishedRevision;

        return [...$data, 'changed' => ! $published || $candidate->contentSnapshot() !== $published->contentSnapshot()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTranslation')->label(__('filament-page::translations.create'))
                ->visible(fn (): bool => $this->record::isMultilingualEnabled())
                ->authorize(fn (): bool => Gate::allows('view', $this->record) && Gate::allows('create', $this->record::class))
                ->modalDescription(__('filament-page::translations.review'))
                ->schema([
                    Select::make('locale')->label(__('filament-page::translations.locale'))->required()
                        ->options(fn (): array => array_diff_key(ContentLocale::supported(), array_flip($this->record->getAvailableLocales()))),
                    TextInput::make('path')->label(__('filament-page::translations.path'))->default(fn (): string => $this->record->path)->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->assertSavedBeforeLanguageChange();
                    $target = app(PageTranslationService::class)->create($this->record, $data['locale'], $data['path']);
                    $this->redirect(PageResource::getUrl('edit', ['record' => $target]), navigate: false);
                }),
            Action::make('switchLanguage')->label(__('filament-page::translations.switch'))
                ->visible(fn (): bool => $this->record::isMultilingualEnabled())
                ->schema([Select::make('locale')->label(__('filament-page::translations.locale'))->required()
                    ->options(fn (): array => $this->record->translations()->filter(fn ($target): bool => $target->locale !== $this->record->locale && Gate::allows('update', $target))
                        ->mapWithKeys(fn ($target): array => [$target->locale => (ContentLocale::supported()[$target->locale] ?? $target->locale).' · '.($target->is_active ? __('filament-page::translations.active') : __('filament-page::translations.preview'))])->all())])
                ->action(function (array $data): void {
                    $this->assertSavedBeforeLanguageChange();
                    ContentLocale::resolve($data['locale']);
                    $target = $this->record->getTranslation($data['locale']);
                    if (! $target) {
                        throw ValidationException::withMessages(['locale' => __('filament-page::translations.missing')]);
                    }
                    Gate::authorize('view', $target);
                    Gate::authorize('update', $target);
                    $this->redirect(PageResource::getUrl('edit', ['record' => $target]), navigate: false);
                }),
            Action::make('preview')->label(__('filament-page::translations.preview'))
                ->visible(fn (): bool => filled(config('filament-page.page_preview_url')))
                ->authorize(fn () => Gate::allows('view', $this->record))
                ->action(function (): void {
                    Gate::authorize('view', $this->record);
                    $this->redirect(app(PagePreviewUrl::class)->for($this->record, Filament::auth()->user()), navigate: false);
                }),
            Action::make('stash')->label(__('filament-page::translations.stash'))->requiresConfirmation()
                ->authorize(fn () => Gate::allows('update', $this->record))
                ->action(function (): void {
                    Gate::authorize('update', $this->record);
                    app(PageRevisionService::class)->stash($this->record);
                    $this->refreshContent();
                }),
            Action::make('restoreStash')->label(__('filament-page::translations.restore'))->requiresConfirmation()
                ->visible(fn () => $this->record->has_stash)
                ->authorize(fn () => Gate::allows('update', $this->record))
                ->action(function (): void {
                    Gate::authorize('update', $this->record);
                    app(PageRevisionService::class)->restoreStash($this->record);
                    $this->refreshContent();
                }),
            Action::make('saveTemplate')->label(__('filament-page::ui.save_as_template'))
                ->modalDescription(__('filament-page::ui.save_template_help'))
                ->authorize(fn (): bool => Gate::allows('view', $this->record) && Gate::allows('create', PageTemplate::class))
                ->schema([TextInput::make('name')->label(__('filament-page::ui.template_name'))->required()->maxLength(255), Textarea::make('description')->label(__('filament-page::ui.description'))])
                ->action(function (array $data): void {
                    $this->assertSavedBeforeLanguageChange();
                    app(PageTemplateService::class)->save($this->record, $data);
                    Notification::make()->title(__('filament-page::ui.page_template_saved'))->success()->send();
                }),
        ];
    }

    private function refreshContent(): void
    {
        $this->record->refresh();
        $this->fillForm();
        $this->rememberData();
    }

    private function assertSavedBeforeLanguageChange(): void
    {
        if (md5(str_replace('\\', '', json_encode($this->data, JSON_UNESCAPED_UNICODE))) !== $this->savedDataHash) {
            throw ValidationException::withMessages(['locale' => __('filament-page::translations.save_first')]);
        }
    }
}
