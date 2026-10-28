<?php

namespace Fayne\Page\Http\Controllers;

use Fayne\CMS\Support\ContentLocale;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['locale' => ['nullable', 'string']]);

        return config('filament-page.models.page')::query()->published()->locale($request->query('locale') ?: ContentLocale::default())->get()
            ->map(fn ($page) => collect($page->publishedProjection())->only(['name', 'path'])->all());
    }

    public function show(Request $request, ?string $path = null)
    {
        $request->validate(['locale' => ['nullable', 'string']]);
        $path = $path ? '/'.ltrim($path, '/') : '/';
        $page = config('filament-page.models.page')::query()->published()->locale($request->query('locale') ?: ContentLocale::default())->publicPath($path)->firstOrFail();

        return response()->json($page->publishedProjection());
    }
}
