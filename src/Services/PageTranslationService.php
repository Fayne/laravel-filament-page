<?php

namespace Fayne\Page\Services;

use Fayne\CMS\Support\ContentLocale;
use Fayne\CMS\Support\Operations\Operations;
use Fayne\Page\Models\Page;
use Fayne\PageBuilder\Support\ContentStateAdapter;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;
use Fayne\PageBuilder\Support\SensitiveContent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PageTranslationService
{
    public function create(Page $source, string $locale, string $path): Page
    {
        return app(Operations::class)->run('page.translation.create', function () use ($source, $locale, $path): Page {
            $gate = Gate::forUser(auth(config('filament-cms.guard', 'admin'))->user());
            $gate->authorize('view', $source);
            $gate->authorize('create', $source::class);
            $locale = ContentLocale::resolve($locale);
            if (! Page::isMultilingualEnabled()) {
                throw ValidationException::withMessages(['locale' => __('filament-page::translations.disabled')]);
            }

            try {
                return $source->getConnection()->transaction(function () use ($source, $locale, $path, $gate): Page {
                    $source = $source->newQuery()->lockForUpdate()->findOrFail($source->getKey());
                    $gate->authorize('view', $source);
                    if ($source->getTranslation($locale)) {
                        throw ValidationException::withMessages(['locale' => __('filament-page::translations.exists')]);
                    }
                    $definitions = new EloquentContentDefinitions(config('filament-page.models.page_component'), config('filament-page.models.page_widget'), $source->getConnectionName());
                    $adapter = new ContentStateAdapter($definitions);
                    $data = $source->only(['type', 'name', 'title', 'description', 'keywords', 'meta', 'extra_data', 'no_index', 'no_follow', 'body_classes', 'main_classes']);
                    foreach (['components', 'fixed_components'] as $field) {
                        $content = $source->{$field};
                        if (app(SensitiveContent::class)->contains($content ?? [], $definitions)) {
                            $gate->authorize('viewSensitiveContent', $source);
                        }
                        $data[$field] = $content === null ? null : $adapter->fromBuilder($adapter->duplicate($adapter->toBuilder($content)));
                    }
                    $data = [...$data, 'path' => $path, 'locale' => $locale, 'is_active' => false, 'changed' => true, 'has_stash' => false,
                        'translation_group_id' => $source->translation_group_id, 'fallback_id' => $source->fallback_id ?: $source->getKey()];
                    $target = $source->newInstance($data);
                    app(PageContentValidator::class)->validate($data, $target);
                    $target->save();
                    $gate->authorize('update', $target);

                    return $target;
                });
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['locale' => __('filament-page::translations.conflict')]);
            }
        }, ['locale' => $locale, 'path' => $path], context: ['source_id' => (string) $source->getKey()], connection: $source->getConnectionName());
    }
}
