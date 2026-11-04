@php($previews = $this->componentPreviews())
<style>.cms-component-wireframe{--mockup-primary:var(--primary-600,#475569)}.cms-component-wireframe svg{display:block;width:100%;height:auto}</style>
    @foreach (['components', 'fixed_components'] as $field)
        <x-filament::section :heading="__('filament-page::ui.'.($field === 'components' ? 'detail_components' : $field))">
            <div x-data="{ mode: 'view' }">
                <div class="flex gap-2 mb-4">
                    @foreach (['view' => 'heroicon-o-eye', 'pretty' => 'heroicon-o-code-bracket-square', 'compact' => 'heroicon-o-code-bracket'] as $mode => $icon)
                        <x-filament::icon-button :icon="$icon" :label="__('filament-page::ui.detail_'.$mode)" :tooltip="__('filament-page::ui.detail_'.$mode)" x-bind:aria-pressed="mode === '{{ $mode }}'" @click="mode = '{{ $mode }}'" />
                    @endforeach
                </div>
                <div x-show="mode === 'view'" class="flex flex-col gap-2">
                    @foreach ($this->getRecord()->getAttribute($field) ?? [] as $component)
                        @php($preview = $previews[$component['layout'] ?? ''] ?? null)
                        @if ($preview && $preview['svg'])
                            @include('filament-page::filament.component-wireframe', ['name' => $preview['name'], 'svg' => $preview['svg']])
                        @elseif ($preview && $preview['url'])
                            <img src="{{ $preview['url'] }}" alt="{{ $preview['name'] }}" class="w-full block" loading="lazy">
                        @else
                            <div class="p-6 text-center">{{ $preview['name'] ?? $component['layout'] ?? '' }}</div>
                        @endif
                    @endforeach
                </div>
                <pre x-show="mode === 'pretty'" x-cloak class="overflow-auto text-sm">{{ json_encode($this->getRecord()->getAttribute($field) ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                <pre x-show="mode === 'compact'" x-cloak class="overflow-auto text-sm">{{ json_encode($this->getRecord()->getAttribute($field) ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </x-filament::section>
    @endforeach
