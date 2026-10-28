<div style="display:grid;gap:1rem">
@foreach ($structure as $group => $blocks)
<strong>{{ $group === 'components' ? __('filament-page::ui.regular_components') : __('filament-page::ui.fixed_components') }}</strong>
@include('filament-page::template-structure-blocks', ['blocks' => $blocks])
@endforeach
</div>
