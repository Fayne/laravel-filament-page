const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');

const view = readFileSync(resolve(__dirname, '../../resources/views/filament/quick-builder.blade.php'), 'utf8');
const expression = view.match(/<div x-data="([\s\S]*?)" @keydown/)[1];
function panel(kind = 'item', key = 'a') {
    const calls = [];
    const state = new Function('$wire', `return (${expression})`)({
        quickMoveComponent: async (...args) => calls.push(['move', ...args]),
        quickAddComponent: async (...args) => calls.push(['add', ...args]),
    });
    state.dragging = { kind, key };
    return { state, calls };
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
    assert.deepEqual(calls, [['move', 'a', 'c']]);
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
    assert.deepEqual(calls, [['move', 'c', 'a']]);
    const last = panel();
    last.state.aim(pointer(360));
    assert.equal(last.state.target, 'end');
    await last.state.drop();
    assert.deepEqual(last.calls, [['move', 'a', null]]);
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
    assert.deepEqual(calls, [['add', 'hero', 'c'], ['move', 'new-card', 'a']]);
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
    assert.deepEqual(calls, [['move', 'new-card', 'a']]);
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
