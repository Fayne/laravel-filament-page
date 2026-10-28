@php
    $editor = $getLivewire();
    $library = $editor->quickComponentLibrary();
    $items = $editor->data['components'] ?? [];
@endphp
<div x-data="{
    open: false, busy: false, dragging: null, target: null, pointer: null,
    beginPointer(event) {
        if (this.busy || event.button !== 0 || event.target.closest('button, a, input, textarea, select')) return;
        const card = event.target.closest('[data-component-key]');
        if (!card || !this.$refs.outline.contains(card)) return;
        event.preventDefault();
        this.pointer = {id: event.pointerId, key: card.dataset.componentKey, x: event.clientX, y: event.clientY};
    },
    movePointer(event) {
        if (!this.pointer || this.pointer.id !== event.pointerId) return;
        if (!this.dragging && Math.hypot(event.clientX - this.pointer.x, event.clientY - this.pointer.y) < 6) return;
        this.dragging = {kind: 'item', key: this.pointer.key};
        const rect = this.$refs.outline.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) {
            this.target = null;
            return;
        }
        this.aim({currentTarget: this.$refs.outline, clientY: event.clientY});
    },
    async endPointer(event) {
        if (!this.pointer || this.pointer.id !== event.pointerId) return;
        this.movePointer(event);
        this.pointer = null;
        if (this.dragging && this.target !== null) await this.drop();
        else this.cancelPointer();
    },
    cancelPointer(event = null) {
        if (event && (!this.pointer || this.pointer.id !== event.pointerId)) return;
        this.pointer = null;
        this.dragging = null;
        this.target = null;
    },
    startDrag(event, kind, key) {
        if (this.busy) {
            event.preventDefault();
            return;
        }
        this.dragging = {kind, key};
        this.target = null;
        event.dataTransfer.setData('text/plain', 'component');
        event.dataTransfer.effectAllowed = kind === 'library' ? 'copy' : 'move';
    },
    aim(event) {
        if (this.busy || !this.dragging) return;
        const cards = Array.from(event.currentTarget.querySelectorAll('[data-component-key]'));
        const next = cards.find(card => {
            if (this.dragging.kind === 'item' && card.dataset.componentKey === this.dragging.key) return false;
            const rect = card.getBoundingClientRect();
            return event.clientY < rect.top + rect.height / 2;
        });
        this.target = next ? next.dataset.componentKey : 'end';
    },
    async drop() {
        if (this.busy || !this.dragging) return;
        if (this.target === null) return;
        const before = this.target === 'end' ? null : this.target;
        this.busy = true;
        try {
            if (this.dragging.kind === 'library') await $wire.quickAddComponent(this.dragging.key, before);
            else await $wire.quickMoveComponent(this.dragging.key, before);
        } finally { this.busy = false; this.dragging = null; this.target = null; }
    },
    edit(key) {
        this.open = false;
        this.$nextTick(() => {
            const item = Array.from(document.querySelectorAll('[x-sortable-item]')).find(el => el.getAttribute('x-sortable-item') === key);
            if (!item) return;
            let parent = item;
            while (parent) { parent.dispatchEvent(new CustomEvent('expand')); parent = parent.parentElement; }
            item.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
    }
}" @keydown.escape.window="cancelPointer(); open = false" @pointermove.window="movePointer($event)" @pointerup.window="endPointer($event)" @pointercancel.window="cancelPointer($event)">
    <button type="button" class="cms-quick-control" @click="open = true">{{ __('filament-page::ui.quick_builder') }}</button>
    <p style="margin-top: .5rem; font-size: .875rem; color: #6b7280">{{ __('filament-page::ui.quick_builder_help') }}</p>
        <div class="cms-quick-builder" x-show="open" x-cloak>
            <div class="cms-quick-backdrop" @click="open = false"></div>
            <section role="dialog" aria-modal="true" aria-label="{{ __('filament-page::ui.quick_page_builder') }}" class="cms-quick-panel" x-trap.inert.noscroll="open">
                <header><h2>{{ __('filament-page::ui.quick_page_builder') }}</h2><button type="button" class="cms-quick-control" color="gray" @click="open = false">{{ __('filament-page::ui.return_to_content_editor') }}</button></header>
                <div class="cms-quick-columns" :aria-busy="busy">
                    <div class="cms-quick-outline" x-ref="outline" @pointerdown="beginPointer($event)" @dragover.prevent.stop="aim($event)" @drop.prevent.stop="aim($event); drop()" @dragleave="if (!$el.contains($event.relatedTarget)) target = null">
                        <h3>{{ __('filament-page::ui.structure_count', ['count' => count($items)]) }}</h3>
                        <p>{{ __('filament-page::ui.drag_from_the_right_or_reorder_existing_components') }}</p>
                        @foreach ($items as $key => $item)
                            @php($definition = $library[$item['type']] ?? ['name' => $item['type'], 'image' => null])
                            <article class="cms-quick-card" data-component-key="{{ $key }}" wire:key="quick-item-{{ $key }}" draggable="false"
                                :class="{'cms-quick-dragging': dragging?.kind === 'item' && dragging.key === @js((string) $key)}">
                                <div class="cms-quick-guide" x-show="dragging && target === @js((string) $key)" x-cloak aria-hidden="true"><span>{{ __('filament-page::ui.drop_here') }}</span></div>
                                @if ($definition['image'])<img draggable="false" src="{{ $definition['image'] }}" alt="{{ $definition['name'] }}" loading="lazy">@endif
                                <strong>{{ $item['data']['internalTitle'] ?? '' }} {{ $definition['name'] }}</strong>
                                <div class="cms-quick-actions">
                                    <button type="button" class="cms-quick-control" size="sm" color="gray" @click="edit(@js((string) $key))">{{ __('filament-page::ui.edit') }}</button>
                                    <button type="button" class="cms-quick-control" size="sm" color="gray" @click="if (!busy) {busy=true; try {await $wire.quickMoveComponent(@js((string) $key), @js($loop->first ? (string) $key : (string) array_keys($items)[$loop->index - 1]));} finally {busy=false}}" @disabled($loop->first)>{{ __('filament-page::ui.move_up') }}</button>
                                    <button type="button" class="cms-quick-control" size="sm" color="danger" @click="if (!busy) {busy=true; try {await $wire.quickRemoveComponent(@js((string) $key));} finally {busy=false}}">{{ __('filament-page::ui.remove') }}</button>
                                </div>
                            </article>
                        @endforeach
                        <div class="cms-quick-end"><div class="cms-quick-guide" x-show="dragging && target === 'end'" x-cloak aria-hidden="true"><span>{{ __('filament-page::ui.drop_here') }}</span></div>{{ count($items) ? __('filament-page::ui.drop_here_to_append') : __('filament-page::ui.drag_a_component_here_to_start') }}</div>
                        @error('data.components')<p role="alert">{{ $message }}</p>@enderror
                    </div>
                    <aside class="cms-quick-library" aria-label="{{ __('filament-page::ui.component_library') }}">
                        <h3>{{ __('filament-page::ui.component_library') }}</h3><p>{{ __('filament-page::ui.drag_to_a_position_on_the_left_or_click_to_append') }}</p>
                        @forelse ($library as $slug => $definition)
                            <button type="button" draggable="true" :aria-disabled="busy"
                                @dragstart="startDrag($event, 'library', @js($slug))"
                                @dragend="dragging = null; target = null"
                                @click="if (!busy) {busy=true; try {await $wire.quickAddComponent(@js($slug));} finally {busy=false}}">
                                @if ($definition['image'])<img draggable="false" src="{{ $definition['image'] }}" alt="{{ $definition['name'] }}" loading="lazy">@endif
                                <strong>{{ $definition['name'] }}</strong>
                            </button>
                        @empty<p>{{ __('filament-page::ui.create_a_component_definition_first') }}</p>@endforelse
                    </aside>
                </div>
            </section>
        </div>
    <style>
        .cms-quick-control{padding:.5rem .75rem;border:1px solid #9ca3af;border-radius:.5rem;cursor:pointer}.cms-quick-control:disabled{opacity:.4;cursor:default}.cms-quick-builder{position:fixed;inset:0;z-index:60}.cms-quick-backdrop{position:absolute;inset:0;background:#0008}.cms-quick-panel{position:absolute;right:0;top:0;bottom:0;width:min(960px,100vw);background:var(--gray-50,#f9fafb);color:#111827;display:flex;flex-direction:column}.cms-quick-panel header{display:flex;justify-content:space-between;align-items:center;padding:1rem;border-bottom:1px solid #d1d5db}.cms-quick-columns{display:grid;grid-template-columns:minmax(0,1fr) 280px;min-height:0;flex:1}.cms-quick-outline,.cms-quick-library{padding:1rem;overflow-y:auto}.cms-quick-library{border-left:1px solid #d1d5db}.cms-quick-panel h2,.cms-quick-panel h3{font-weight:600}.cms-quick-panel p{font-size:.875rem;margin:.5rem 0 1rem;color:#6b7280}.cms-quick-panel article,.cms-quick-library>button{display:block;width:100%;text-align:left;background:white;border:1px solid #d1d5db;border-radius:.75rem;padding:.75rem;margin-top:.75rem;cursor:grab}.cms-quick-panel img{width:100%;height:120px;object-fit:contain;margin-bottom:.5rem}.cms-quick-actions{display:flex;gap:.5rem;margin-top:.75rem}.cms-quick-end{padding:2rem 1rem;border:2px dashed #9ca3af;border-radius:.75rem;text-align:center;margin-top:1rem}.cms-quick-card{touch-action:none;user-select:none}.cms-quick-card,.cms-quick-end{position:relative}.cms-quick-dragging{opacity:.45}.cms-quick-guide{position:absolute;top:-.5rem;left:0;right:0;height:3px;background:#6366f1;pointer-events:none;z-index:1}.cms-quick-guide span{position:absolute;right:0;top:-.6rem;background:#4f46e5;color:white;padding:.1rem .5rem;border-radius:.25rem;font-size:.75rem;line-height:1.25rem}.dark .cms-quick-panel{background:#111827;color:#f3f4f6}.dark .cms-quick-panel article,.dark .cms-quick-library>button{background:#1f2937;border-color:#4b5563}@media(max-width:640px){.cms-quick-columns{grid-template-columns:minmax(0,1fr) 140px}.cms-quick-outline,.cms-quick-library{padding:.5rem}.cms-quick-panel img{height:80px}.cms-quick-actions{flex-wrap:wrap}}
    </style>
</div>
