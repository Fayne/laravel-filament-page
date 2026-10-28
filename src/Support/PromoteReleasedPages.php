<?php

namespace Fayne\Page\Support;

use Fayne\CMS\Events\PublicationSucceeded;

final class PromoteReleasedPages
{
    public function handle(PublicationSucceeded $event): void
    {
        $manifest = $event->deployment->releaseManifest;
        $sources = [];
        foreach ($manifest->entries['page'] ?? [] as $entry) {
            $key = json_encode([$entry['connection'] ?? config('database.default'), config('filament-page.models.page'), (string) $entry['entity_id']], JSON_THROW_ON_ERROR);
            if (isset($sources[$key]) && $sources[$key]['content_snapshot'] !== $entry['content_snapshot']) {
                throw new \LogicException('A shared page source has contradictory frozen content.');
            }
            $sources[$key] ??= $entry;
        }
        foreach ($sources as $entry) {
            $model = config('filament-page.models.page');
            $page = (new $model)->setConnection($entry['connection'] ?? config('database.default'));
            $page->getConnection()->transaction(function () use ($page, $entry, $manifest): void {
                $page = $page->newQuery()->lockForUpdate()->findOrFail($entry['entity_id']);
                // Regional writes can commit before the control connection: retry by manifest key.
                $page->revisions()->firstOrCreate(['publication_manifest_key' => $manifest->release_id], [
                    ...$entry['content_snapshot'], 'is_stash' => false, 'released_at' => now(),
                ]);
                $page->forceFill(['changed' => $page->contentSnapshot() !== $entry['content_snapshot']])->saveQuietly();
            });
        }
    }
}
