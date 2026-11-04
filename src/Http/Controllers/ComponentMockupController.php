<?php

namespace Fayne\Page\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComponentMockupController
{
    public function __invoke(string $filename): StreamedResponse
    {
        abort_unless(preg_match('/\A[a-z0-9-]+-wireframe\.svg\z/', $filename), 404);
        $disk = Storage::disk('page-component-mockups');
        abort_unless($disk->exists($filename), 404);

        return $disk->response($filename, headers: [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
