<?php

namespace Fayne\Page\Support;

enum PageType: string
{
    case Basic = 'basic';
    case Home = 'homepage';
    case NotFound = '404_page';

    public static function getSelectOptions(): array
    {
        return ['basic' => __('filament-page::ui.basic_page'), 'homepage' => __('filament-page::ui.homepage'), '404_page' => __('filament-page::ui.not_found_page')];
    }
}
