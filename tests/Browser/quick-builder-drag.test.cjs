const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');

const view = readFileSync(resolve(__dirname, '../../resources/views/filament/quick-builder.blade.php'), 'utf8');
const expression = view.match(/<div x-data="([\s\S]*?)" @keydown/)[1];
function panel(kind = 'item', key = 'a') {
    const calls = [];
    const data = { components: { a: {data: {title: 'A'}}, b: {data: {title: 'B'}}, c: {data: {title: 'C'}}, 'new-card': {data: {title: 'New'}} } };
    const state = new Function('$wire', `return (${expression})`)({
        $get: path => path === 'data.components' ? data.components : undefined,
        $set: (path, value, live) => { assert.equal(live, false); if (path === 'data.components') data.components = value; },
        quickMoveComponent: async (...args) => calls.push(['move', ...args]),
        quickAddComponent: async (...args) => calls.push(['add', ...args]),
    });
    state.dragging = { kind, key };
    state.$refs = { outline: { querySelectorAll: () => [], querySelector: () => null } };
    state.$root = { closest: () => ({querySelectorAll: () => []}) };
    state.$nextTick = callback => callback();
    return { state, calls, data };
}
function pointer(clientY) {
    return { clientY, currentTarget: { querySelectorAll: () => ['a', 'b', 'c'].map((key, index) => ({
        dataset: { componentKey: key },
        getBoundingClientRect: () => ({ top: index * 120, height: 100 }),
    })) } };
}

test('dropping the first card in the lower half of the second inserts it between second and third', async () => {
    const { state, calls } = panel();
    state.aim(pointer(195));
    assert.equal(state.target, 'c');
    await state.drop();
    assert.deepEqual(calls, []);
    assert.deepEqual(Object.keys(state.items()), ['b', 'a', 'c', 'new-card']);
});
test('gaps and both halves select the same boundary shown by the guide', () => {
    const { state } = panel();
    for (const y of [175, 219, 230, 245]) {
        state.aim(pointer(y));
        assert.equal(state.target, 'c');
    }
    state.aim(pointer(140));
    assert.equal(state.target, 'b');
});
test('moving upward and moving to the end choose correct insertion targets', async () => {
    const { state, calls } = panel('item', 'c');
    state.aim(pointer(10));
    assert.equal(state.target, 'a');
    await state.drop();
    assert.deepEqual(calls, []);
    assert.deepEqual(Object.keys(state.items()), ['c', 'a', 'b', 'new-card']);
    const last = panel();
    last.state.aim(pointer(360));
    assert.equal(last.state.target, 'end');
    await last.state.drop();
    assert.deepEqual(last.calls, []);
    assert.deepEqual(Object.keys(last.state.items()), ['b', 'c', 'new-card', 'a']);
});
test('library drops use the same position and clear the guide after completion', async () => {
    const { state, calls } = panel('library', 'hero');
    state.aim(pointer(195));
    await state.drop();
    assert.deepEqual(calls, [['add', 'hero', 'c']]);
    assert.equal(state.target, null);
    assert.equal(state.dragging, null);
    assert.equal(state.busy, false);
});

test('library drag keeps its native source enabled during the server round trip', () => {
    const library = view.slice(view.indexOf('<aside class="cms-quick-library"'));
    assert.doesNotMatch(library, /:disabled="busy"/);
});
test('a newly inserted card can start dragging immediately after a library drop resolves', async () => {
    const { state, calls } = panel();
    const event = { preventDefault() { throw new Error('unexpected drag cancellation'); }, dataTransfer: { setData() {} } };
    state.startDrag(event, 'library', 'hero');
    state.aim(pointer(195));
    await state.drop();
    state.startDrag(event, 'item', 'new-card');
    state.aim(pointer(10));
    await state.drop();
    assert.deepEqual(calls, [['add', 'hero', 'c']]);
    assert.deepEqual(Object.keys(state.items()), ['new-card', 'a', 'b', 'c']);
});
test('dragging during an unfinished update is cancelled without corrupting the active operation', () => {
    const { state } = panel('library', 'hero');
    state.busy = true;
    let cancelled = false;
    state.startDrag({ preventDefault() { cancelled = true; } }, 'item', 'new-card');
    assert.equal(cancelled, true);
    assert.deepEqual(state.dragging, { kind: 'library', key: 'hero' });
});

function previewPointer(key, y, id = 1) {
    const card = { dataset: { componentKey: key } };
    return { pointerId: id, button: 0, clientX: 10, clientY: y,
        target: { closest: selector => selector === '[data-component-key]' ? card : null },
        preventDefault() {},
    };
}
test('container pointer sorting immediately moves a new card without a prior button request', async () => {
    const { state, calls } = panel();
    state.dragging = null;
    state.$refs = { outline: { ...pointer(0).currentTarget, contains: () => true, getBoundingClientRect: () => ({left: 0, right: 500, top: 0, bottom: 500}) } };
    state.beginPointer(previewPointer('new-card', 350));
    state.movePointer(previewPointer('new-card', 10));
    assert.deepEqual(state.dragging, { kind: 'item', key: 'new-card' });
    assert.equal(state.target, 'a');
    await state.endPointer(previewPointer('new-card', 10));
    assert.deepEqual(calls, []);
    assert.deepEqual(Object.keys(state.items()), ['new-card', 'a', 'b', 'c']);
    assert.equal(state.pointer, null);
});
test('clicks and cancelled pointer gestures do not reorder cards', async () => {
    const { state, calls } = panel();
    state.dragging = null;
    state.$refs = { outline: { ...pointer(0).currentTarget, contains: () => true, getBoundingClientRect: () => ({left: 0, right: 500, top: 0, bottom: 500}) } };
    state.beginPointer(previewPointer('a', 10));
    state.movePointer(previewPointer('a', 12));
    await state.endPointer(previewPointer('a', 12));
    assert.deepEqual(calls, []);
    state.beginPointer(previewPointer('a', 10));
    state.movePointer(previewPointer('a', 195));
    state.cancelPointer();
    assert.equal(state.dragging, null);
    assert.equal(state.target, null);
    assert.deepEqual(calls, []);
});

test('the pointercancel emitted when native library dragging starts must retain the library drag', () => {
    const { state } = panel('library', 'hero');
    state.cancelPointer({ pointerId: 1 });
    assert.deepEqual(state.dragging, { kind: 'library', key: 'hero' });
});


test('consecutive local moves preserve all field content without a server call', () => {
    const { state, calls, data } = panel();
    const original = data.components.a;
    state.move('a', 'c');
    state.moveBy('a', 1);
    state.moveBy('a', -1);
    assert.deepEqual(Object.keys(data.components), ['b', 'a', 'c', 'new-card']);
    assert.equal(data.components.a, original);
    assert.deepEqual(calls, []);
    assert.equal(state.busy, false);
});

test('edge buttons follow the current local order after repeated moves', () => {
    const { state } = panel();
    assert.equal(state.atEdge('a', -1), true);
    state.moveBy('a', 1);
    assert.equal(state.atEdge('a', -1), false);
    assert.equal(state.atEdge('b', -1), true);
    state.moveBy('new-card', -1);
    assert.equal(state.atEdge('c', 1), true);
});

test('local sorting also moves the ordinary editor rows and preserves add-between controls', () => {
    const { state, data } = panel();
    const keys = ['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333', '44444444-4444-4444-8444-444444444444'];
    data.components = Object.fromEntries(keys.map((key, index) => [key, Object.values(data.components)[index]]));
    class Element {
        constructor(attrs = {}) { this.attrs = {...attrs}; this.children = []; this.style = {}; this.dataset = {}; }
        get attributes() { return Object.entries(this.attrs).map(([name, value]) => ({name, value})); }
        hasAttribute(name) { return name in this.attrs; }
        getAttribute(name) { return this.attrs[name] ?? null; }
        setAttribute(name, value) { this.attrs[name] = value; }
        get nextElementSibling() { return this.parentElement?.children[this.parentElement.children.indexOf(this) + 1] ?? null; }
        append(child) {
            if (child.parentElement) child.parentElement.children.splice(child.parentElement.children.indexOf(child), 1);
            this.children.push(child); child.parentElement = this;
        }
        closest() { return owner; }
        querySelectorAll(selector) { return selector === '*' ? this.children : []; }
        cloneNode() { const copy = new Element(this.attrs); this.children.forEach(child => copy.append(child.cloneNode())); return copy; }
    }
    const owner = new Element();
    const list = new Element();
    const rows = keys.map(key => new Element({'x-sortable-item': key}));
    for (const row of rows) {
        list.append(row);
        if (row === rows.at(-1)) continue;
        const gap = new Element();
        gap.append(new Element({'wire:click': `add after ${row.getAttribute('x-sortable-item')}`, 'wire:key': 'picker'}));
        list.append(gap);
    }
    state.$root = { closest: () => ({querySelectorAll: () => rows}) };
    state.move(keys[3], keys[0]);
    assert.deepEqual(list.children.filter(el => el.hasAttribute('x-sortable-item')).map(el => el.getAttribute('x-sortable-item')), [keys[3], keys[0], keys[1], keys[2]]);
    assert.equal(rows[3].nextElementSibling.children[0].getAttribute('wire:click'), `add after ${keys[3]}`);
    assert.equal(rows[2].nextElementSibling.style.display, 'none');
    assert.deepEqual(JSON.parse(owner.dataset.reorderItems), [keys[3], keys[0], keys[1], keys[2]]);
    state.move(keys[3], null);
    assert.equal(rows[3].nextElementSibling.style.display, 'none');
    assert.equal(rows[2].nextElementSibling.style.display, '');
});
