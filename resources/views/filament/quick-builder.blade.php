@php
    $editor = $getLivewire();
    $library = $editor->quickComponentLibrary();
    $items = $editor->data['components'] ?? [];
@endphp
<div x-data="{
    open: false, busy: false, dragging: null, target: null, pointer: null,
    items() { return $wire.$get('data.components') || {}; },
    moveBy(key, direction) {
        const keys = Object.keys(this.items());
        const index = keys.indexOf(key);
        if (index < 0 || index + direction < 0 || index + direction >= keys.length) return;
        this.move(key, direction < 0 ? keys[index - 1] : (keys[index + 2] || null));
    },
    atEdge(key, direction) {
        const keys = Object.keys(this.items());
        return keys.indexOf(key) === (direction < 0 ? 0 : keys.length - 1);
    },
    move(key, before = null) {
        const items = this.items();
        if (!(key in items) || key === before || (before !== null && !(before in items))) return;
        const ordered = {};
        for (const id of Object.keys(items)) {
            if (id === key) continue;
            if (id === before) ordered[key] = items[key];
            ordered[id] = items[id];
        }
        if (before === null) ordered[key] = items[key];
        $wire.$set('data.components', ordered, false);
        $wire.$set('quickComponentOrder', Object.keys(ordered), false);
        const cards = Array.from(this.$refs.outline.querySelectorAll('[data-component-key]'));
        const card = cards.find(el => el.dataset.componentKey === key);
        const target = cards.find(el => el.dataset.componentKey === before) || this.$refs.outline.querySelector('.cms-quick-end');
        if (card && target) target.before(card);
        const blocks = Array.from(this.$root.closest('[wire\\:id]')?.querySelectorAll('[x-sortable-item]') || []);
        const block = blocks.find(el => el.getAttribute('x-sortable-item') === key);
        if (block) {
            const list = block.parentElement;
            const owner = list.closest('[data-reorder-owner]');
            const rows = Array.from(list.children).filter(el => el.hasAttribute('x-sortable-item'));
            const gaps = new Map(rows.map(row => [row.getAttribute('x-sortable-item'), row.nextElementSibling?.hasAttribute('x-sortable-item') ? null : row.nextElementSibling]));
            const source = rows.find(row => gaps.get(row.getAttribute('x-sortable-item')));
            const keys = Object.keys(ordered);
            for (const [index, id] of keys.entries()) {
                const row = rows.find(el => el.getAttribute('x-sortable-item') === id);
                if (!row) continue;
                list.append(row);
                let gap = gaps.get(id);
                if (!gap && source && index < keys.length - 1) {
                    const sourceKey = source.getAttribute('x-sortable-item');
                    gap = gaps.get(sourceKey).cloneNode(true);
                    for (const el of [gap, ...gap.querySelectorAll('*')]) {
                        for (const attr of Array.from(el.attributes)) {
                            let value = attr.value.replaceAll(sourceKey, id);
                            if (attr.name === 'wire:key') value += '.local-' + id;
                            el.setAttribute(attr.name, value);
                        }
                    }
                }
                if (gap) { gap.style.display = index === keys.length - 1 ? 'none' : ''; list.append(gap); }
                for (const control of row.querySelectorAll('[data-reorder-direction]')) {
                    if (control.closest('[data-reorder-owner]') !== owner) continue;
                    control.disabled = control.dataset.reorderDirection === 'up' ? index === 0 : index === keys.length - 1;
                }
            }
            if (owner) owner.dataset.reorderItems = JSON.stringify(keys);
        }
    },
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
        this.busy = this.dragging.kind === 'library';
        try {
            if (this.dragging.kind === 'library') await $wire.quickAddComponent(this.dragging.key, before);
            else this.move(this.dragging.key, before);
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
    <x-filament::icon-button icon="heroicon-o-squares-2x2" :label="__('filament-page::ui.quick_builder')" :tooltip="__('filament-page::ui.quick_builder')" @click="open = true" />
    <p style="margin-top: .5rem; font-size: .875rem; color: #6b7280">{{ __('filament-page::ui.quick_builder_help') }}</p>
        <div class="cms-quick-builder" x-show="open" x-cloak>
            <div class="cms-quick-backdrop" @click="open = false"></div>
            <section role="dialog" aria-modal="true" aria-label="{{ __('filament-page::ui.quick_page_builder') }}" class="cms-quick-panel" x-trap.inert.noscroll="open">
                <header><h2>{{ __('filament-page::ui.quick_page_builder') }}</h2><x-filament::icon-button icon="heroicon-o-arrow-uturn-left" color="gray" :label="__('filament-page::ui.return_to_content_editor')" :tooltip="__('filament-page::ui.return_to_content_editor')" @click="open = false" /></header>
                <div class="cms-quick-columns" :aria-busy="busy">
                    <div class="cms-quick-outline" x-ref="outline" @pointerdown="beginPointer($event)" @dragover.prevent.stop="aim($event)" @drop.prevent.stop="aim($event); drop()" @dragleave="if (!$el.contains($event.relatedTarget)) target = null">
                        <h3>{{ __('filament-page::ui.structure_count', ['count' => count($items)]) }}</h3>
                        <p>{{ __('filament-page::ui.drag_from_the_right_or_reorder_existing_components') }}</p>
                        @foreach ($items as $key => $item)
                            @php($definition = $library[$item['type']] ?? ['name' => $item['type'], 'image' => null])
                            <article class="cms-quick-card" data-component-key="{{ $key }}" wire:key="quick-item-{{ $key }}" draggable="false"
                                :class="{'cms-quick-dragging': dragging?.kind === 'item' && dragging.key === @js((string) $key)}">
                                <div class="cms-quick-guide" x-show="dragging && target === @js((string) $key)" x-cloak aria-hidden="true"><span>{{ __('filament-page::ui.drop_here') }}</span></div>
                                <div class="cms-quick-preview">
                                    @if ($definition['wireframe'] ?? false)
                                        @include('filament-page::filament.component-wireframe', ['name' => $definition['name'], 'svg' => $definition['wireframe']])
                                    @elseif ($definition['image'])
                                        <img draggable="false" src="{{ $definition['image'] }}" alt="{{ $definition['name'] }}" loading="lazy">
                                    @else
                                        <div class="cms-quick-placeholder"><strong>{{ $item['data']['internalTitle'] ?? '' }} {{ $definition['name'] }}</strong></div>
                                    @endif
                                    <div class="cms-quick-overlay" aria-hidden="true"></div>
                                    <div class="cms-quick-actions">
                                        <button type="button" class="cms-quick-icon-button" :disabled="busy" aria-label="{{ __('filament-page::ui.edit') }}" title="{{ __('filament-page::ui.edit') }}" @click="edit(@js((string) $key))">
                                            <x-filament::icon icon="heroicon-o-pencil-square" class="cms-quick-action-icon" aria-hidden="true" />
                                        </button>
                                        <button type="button" class="cms-quick-icon-button" :disabled="busy || atEdge(@js((string) $key), -1)" aria-label="{{ __('filament-page::ui.move_up') }}" title="{{ __('filament-page::ui.move_up') }}" @click="if (!busy) moveBy(@js((string) $key), -1)">
                                            <x-filament::icon icon="heroicon-o-chevron-up" class="cms-quick-action-icon" aria-hidden="true" />
                                        </button>
                                        <button type="button" class="cms-quick-icon-button" :disabled="busy || atEdge(@js((string) $key), 1)" aria-label="{{ __('filament-page::ui.move_down') }}" title="{{ __('filament-page::ui.move_down') }}" @click="if (!busy) moveBy(@js((string) $key), 1)">
                                            <x-filament::icon icon="heroicon-o-chevron-down" class="cms-quick-action-icon" aria-hidden="true" />
                                        </button>
                                        <button type="button" class="cms-quick-icon-button cms-quick-icon-danger" :disabled="busy" aria-label="{{ __('filament-page::ui.remove') }}" title="{{ __('filament-page::ui.remove') }}" @click="if (!busy) {busy=true; try {await $wire.quickRemoveComponent(@js((string) $key));} finally {busy=false}}">
                                            <x-filament::icon icon="heroicon-o-trash" class="cms-quick-action-icon" aria-hidden="true" />
                                        </button>
                                    </div>
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
                                @if ($definition['wireframe'] ?? false)
                                    @include('filament-page::filament.component-wireframe', ['name' => $definition['name'], 'svg' => $definition['wireframe']])
                                @elseif ($definition['image'])
                                    <img draggable="false" src="{{ $definition['image'] }}" alt="{{ $definition['name'] }}" loading="lazy">
                                @endif
                                <strong>{{ $definition['name'] }}</strong>
                            </button>
                        @empty<p>{{ __('filament-page::ui.create_a_component_definition_first') }}</p>@endforelse
                    </aside>
                </div>
            </section>
        </div>
    <style>
        .cms-component-wireframe{--mockup-primary:var(--primary-600,#475569)}.cms-component-wireframe svg{display:block;width:100%;height:auto}
        .cms-quick-builder{position:fixed;inset:0;z-index:60}.cms-quick-backdrop{position:absolute;inset:0;background:#0008}.cms-quick-panel{position:absolute;right:0;top:0;bottom:0;width:min(960px,100vw);background:var(--gray-50,#f9fafb);color:#111827;display:flex;flex-direction:column}.cms-quick-panel header{display:flex;justify-content:space-between;align-items:center;padding:1rem;border-bottom:1px solid #d1d5db}.cms-quick-columns{display:grid;grid-template-columns:minmax(0,1fr) 280px;min-height:0;flex:1}.cms-quick-outline,.cms-quick-library{padding:1rem;overflow-y:auto}.cms-quick-library{border-left:1px solid #d1d5db}.cms-quick-panel h2,.cms-quick-panel h3{font-weight:600}.cms-quick-panel p{font-size:.875rem;margin:.5rem 0 1rem;color:#6b7280}.cms-quick-panel article,.cms-quick-library>button{display:block;width:100%;text-align:left;background:white;border:1px solid #d1d5db;border-radius:.75rem;padding:.75rem;margin-top:.75rem;cursor:grab}.cms-quick-panel img{width:100%;height:120px;object-fit:contain;margin-bottom:.5rem}.cms-quick-preview{position:relative;min-height:160px;border-radius:inherit;overflow:hidden}
        .cms-quick-panel .cms-quick-card{padding:0}
        .cms-quick-preview img{display:block;width:100%;height:auto;min-height:160px;margin:0;object-fit:contain}
        .cms-quick-placeholder{min-height:160px;display:flex;align-items:center;justify-content:center;padding:1rem;text-align:center}
        .cms-quick-overlay{position:absolute;inset:0;background:rgb(107 114 128 / .35);opacity:0;pointer-events:none;transition:opacity .2s}
        .cms-quick-actions{position:absolute;right:8px;bottom:8px;display:flex;flex-direction:column;gap:6px;opacity:0;pointer-events:none;transform:translateY(6px);transition:opacity .2s,transform .2s}
        .cms-quick-card:hover .cms-quick-overlay,.cms-quick-card:focus-within .cms-quick-overlay{opacity:1}
        .cms-quick-card:hover .cms-quick-actions,.cms-quick-card:focus-within .cms-quick-actions{opacity:1;pointer-events:auto;transform:translateY(0)}
        .cms-quick-icon-button{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border:0;border-radius:9999px;background:var(--primary-600,#4f46e5);color:white;box-shadow:0 1px 3px #0003;cursor:pointer}
        .cms-quick-icon-button:hover{filter:brightness(1.1)}.cms-quick-icon-button:disabled{opacity:.4;cursor:default}.cms-quick-icon-button:focus-visible{outline:2px solid white;outline-offset:2px}
        .cms-quick-icon-danger{background:#dc2626}.cms-quick-action-icon{width:16px;height:16px}
        @media(hover:none){.cms-quick-actions{opacity:1;pointer-events:auto;transform:none}}
        @media(prefers-reduced-motion:reduce){.cms-quick-overlay,.cms-quick-actions{transition:none}}
        .cms-quick-end{padding:2rem 1rem;border:2px dashed #9ca3af;border-radius:.75rem;text-align:center;margin-top:1rem}.cms-quick-card{touch-action:none;user-select:none}.cms-quick-card,.cms-quick-end{position:relative}.cms-quick-dragging{opacity:.45}.cms-quick-guide{position:absolute;top:-.5rem;left:0;right:0;height:3px;background:#6366f1;pointer-events:none;z-index:1}.cms-quick-guide span{position:absolute;right:0;top:-.6rem;background:#4f46e5;color:white;padding:.1rem .5rem;border-radius:.25rem;font-size:.75rem;line-height:1.25rem}.dark .cms-quick-panel{background:#111827;color:#f3f4f6}.dark .cms-quick-panel article,.dark .cms-quick-library>button{background:#1f2937;border-color:#4b5563}@media(max-width:640px){.cms-quick-columns{grid-template-columns:minmax(0,1fr) 140px}.cms-quick-outline,.cms-quick-library{padding:.5rem}.cms-quick-panel img{height:80px}}
    </style>
</div>
