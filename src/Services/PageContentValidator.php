<?php

namespace Fayne\Page\Services;

use Fayne\CMS\Support\ContentLocale;
use Fayne\Page\Models\Page;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PageContentValidator
{
    public function validate(array $data, ?Page $record = null): void
    {
        Validator::make($data, [
            'path' => ['required', 'string', 'max:255', 'regex:~^/[^?#]*$~'],
            'type' => ['required', Rule::in(array_keys(config('filament-page.page_types_class')::getSelectOptions()))],
            'locale' => ['required', 'string', 'max:32'], 'components' => ['nullable', 'array'], 'fixed_components' => ['nullable', 'array'],
        ])->validate();
        ContentLocale::resolve($data['locale']);
        $model = config('filament-page.models.page');
        $query = $record ? $record->newQuery() : $model::query();
        $query->where('locale', $data['locale'])->when($record, fn ($query) => $query->whereKeyNot($record->getKey()));
        if ((clone $query)->where(fn ($paths) => $paths->where('path', $data['path'])
            ->orWhereHas('currentPublishedRevision', fn ($revision) => $revision->where('path', $data['path'])))->exists()) {
            throw ValidationException::withMessages(['path' => __('filament-page::translations.path_taken')]);
        }
        $limit = config('filament-page.creation_limited_page_types', [])[$data['type']] ?? null;
        if ($limit !== null && (clone $query)->where('type', $data['type'])->count() >= $limit) {
            throw ValidationException::withMessages(['type' => __('filament-page::translations.type_limit')]);
        }
        $expected = [];
        foreach (config('filament-page.fixed_components', [])[$data['type']] ?? [] as $layout => $count) {
            $expected = [...$expected, ...array_fill(0, $count, $layout)];
        }
        if (array_column($data['fixed_components'] ?? [], 'layout') !== $expected) {
            throw ValidationException::withMessages(['fixed_components' => __('filament-page::translations.fixed_invalid')]);
        }
        $excluded = config('filament-page.excluded_components', []);
        if (in_array($data['type'], $excluded, true) && ($data['components'] ?? []) !== []) {
            throw ValidationException::withMessages(['components' => __('filament-page::translations.components_excluded')]);
        }
    }

    public function initialFixed(string $type): array
    {
        $content = [];
        foreach (config('filament-page.fixed_components', [])[$type] ?? [] as $layout => $count) {
            for ($i = 0; $i < $count; $i++) {
                $content[] = ['layout' => $layout, 'key' => (string) Str::uuid(), 'attributes' => []];
            }
        }

        return $content;
    }
}
