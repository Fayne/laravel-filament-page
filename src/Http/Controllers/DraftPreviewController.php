<?php

namespace Fayne\Page\Http\Controllers;

use Fayne\Page\Models\Page;
use Fayne\Page\Support\PagePreviewSession;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DraftPreviewController extends Controller
{
    public function __invoke(Request $request, PagePreviewSession $sessions)
    {
        $token = $request->bearerToken();
        $path = $request->query('page', '/');
        $response = is_string($token) && is_string($path)
            ? $sessions->run($token, $path, fn (Page $page) => response()->json(['data' => ['entity_type' => 'page', 'entity_id' => (string) $page->getKey(), 'revision_id' => null, 'path' => $page->path, 'locale' => $page->locale, 'projection' => $page->publicProjection()]], 200, ['Cache-Control' => 'private, no-store']))
            : null;

        return $response ?? response()->json(['message' => 'Invalid or expired preview token'], 401, ['Cache-Control' => 'private, no-store']);
    }
}
