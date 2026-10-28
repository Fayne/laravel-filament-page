<?php

namespace Fayne\Page\Support;

use Fayne\CMS\Models\Admin;
use Fayne\Page\Models\Page;
use InvalidArgumentException;

final class PagePreviewUrl
{
    public function __construct(private PagePreviewSession $sessions) {}

    public function for(Page $page, Admin $admin): string
    {
        $url = config('filament-page.page_preview_url');
        if (! is_string($url) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || ! parse_url($url, PHP_URL_HOST) || parse_url($url, PHP_URL_FRAGMENT) !== null) {
            throw new InvalidArgumentException(__('filament-page::errors.preview_url_invalid'));
        }
        $query = http_build_query(['path' => $page->path, 'preview_token' => $this->sessions->issue($page, $admin)], '', '&', PHP_QUERY_RFC3986);

        return $url.(str_contains($url, '?') ? '&' : '?').$query;
    }
}
