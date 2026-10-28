<?php

namespace Fayne\Page\Http\Controllers;

use Fayne\CMS\Support\ContentLocale;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class LocalizedPageController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['locale' => ['nullable', 'string']]);
        $locale = ContentLocale::resolve($request->query('locale'));

        return config('filament-page.models.page')::query()->published()->locale($locale)->get()
            ->map(fn ($page) => collect($page->publishedProjection())->only(['name', 'path'])->all());
    }

    public function show(Request $request, ?string $path = null)
    {
        $request->validate(['locale' => ['nullable', 'string']]);
        $locale = ContentLocale::resolve($request->query('locale'));
        $path = $path ? '/'.ltrim($path, '/') : '/';
        $page = config('filament-page.models.page')::query()->published()->locale($locale)->publicPath($path)->firstOrFail();
        $translations = $page->newQuery()->published()->where('translation_group_id', $page->translation_group_id)
            ->when(! $page::isMultilingualEnabled(), fn ($query) => $query->locale())->get()
            ->filter(fn ($version) => ContentLocale::normalize($version->locale) !== null)
            ->map(fn ($version): array => ['locale' => $version->locale, 'path' => $version->publishedProjection()['path']])->values();

        return response()->json([...$page->publishedProjection(), 'translations' => $translations,
            'language' => ['requested_locale' => $locale, 'resolved_locale' => $locale, 'is_fallback' => false]]);
    }
}
