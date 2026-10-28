<?php

namespace Fayne\Page\Services;

use Fayne\CMS\Support\Operations\Operations;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageRevision;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class PageRevisionService
{
    public function stash(Page $page): ?PageRevision
    {
        return app(Operations::class)->run('page.stash', function () use ($page): ?PageRevision {
            return $page->getConnection()->transaction(function () use ($page): ?PageRevision {
                $page = $page->newQuery()->lockForUpdate()->findOrFail($page->getKey());
                $published = $page->currentPublishedRevision;
                if ($published && $page->contentSnapshot() === $published->contentSnapshot()) {
                    return null;
                }
                $stash = $page->revisions()->updateOrCreate(['is_stash' => true], [...$page->contentSnapshot(), 'released_at' => null]);
                $values = Arr::except($published?->contentSnapshot() ?? [], ['locale', 'fallback_id', 'translation_group_id']);
                $page->forceFill([...$values, 'has_stash' => true, 'changed' => ! $published])->save();

                return $stash;
            });
        }, [], $page, [], successful: fn ($revision) => $revision !== null);
    }

    public function restoreStash(Page $page): void
    {
        app(Operations::class)->run('page.stash.restore', function () use ($page): bool {
            return $page->getConnection()->transaction(function () use ($page): bool {
                $page = $page->newQuery()->lockForUpdate()->findOrFail($page->getKey());
                $stash = $page->stashRevision;
                if (! $stash) {
                    return false;
                }
                $page->forceFill([...Arr::except($stash->contentSnapshot(), ['locale', 'fallback_id', 'translation_group_id']), 'has_stash' => false, 'changed' => true])->save();
                $stash->delete();

                return true;
            });
        }, [], $page, [], successful: fn ($changed) => $changed);
    }

    public function restore(Page $page, PageRevision $revision): void
    {
        app(Operations::class)->run('page.revision.restore', function () use ($page, $revision): void {
            if ((string) $revision->page_id !== (string) $page->getKey() || $revision->getConnectionName() !== $page->getConnectionName()) {
                throw ValidationException::withMessages(['revision' => __('filament-page::ui.revision_page_mismatch')]);
            }
            $page->getConnection()->transaction(function () use ($page, $revision): void {
                $locked = $page->newQuery()->lockForUpdate()->findOrFail($page->getKey());
                $locked->forceFill([...Arr::except($revision->contentSnapshot(), ['locale', 'fallback_id', 'translation_group_id']), 'changed' => true])->save();
            });
        }, ['revision_id' => $revision->getKey()], $page, []);
    }
}
