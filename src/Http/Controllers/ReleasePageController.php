<?php

namespace Fayne\Page\Http\Controllers;

use Fayne\CMS\Models\ReleaseManifest;
use Fayne\CMS\Publishing\FrozenReleaseAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;

final class ReleasePageController extends Controller
{
    public function index(Request $request, string $releaseId)
    {
        $manifest = ReleaseManifest::query()
            ->where('release_id', $releaseId)
            ->whereIn('status', ['frozen', 'succeeded'])
            ->firstOrFail();

        app(FrozenReleaseAccess::class)->authorize($request, $manifest);

        return response()->json([
            'data' => collect($manifest->entries['page'] ?? [])
                ->when($request->filled('path'), fn ($pages) => $pages->where('path', $request->string('path')->toString()))
                ->map(fn ($entry) => Arr::except($entry, ['content_snapshot', 'comparison_projection', 'connection']))->values(),
        ]);
    }
}
