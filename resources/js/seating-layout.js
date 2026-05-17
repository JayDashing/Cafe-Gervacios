import axios from 'axios';

function setCsrfHeader() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    }
}

function statusToDotClass(status) {
    if (status === 'free') {
        return 'available';
    }

    return status;
}

function hardCloseSeatModal() {
    const modal = document.getElementById('seat-modal');
    if (!modal) {
        return;
    }

    modal.classList.remove('open');
    modal.style.display = 'none';
    modal.removeAttribute('data-selected-seat-id');
    modal.querySelector('#seat-modal-delete-row details')?.removeAttribute('open');
}

function showSeatModal(modal) {
    if (!modal) {
        return;
    }

    modal.style.display = 'flex';
    modal.classList.add('open');
}

function requestSeatModalClose() {
    if (typeof window.__tcSeatModalClose === 'function') {
        window.__tcSeatModalClose();
        return;
    }

    hardCloseSeatModal();
}

document.addEventListener('click', (event) => {
    const modal = document.getElementById('seat-modal');
    if (!modal?.classList.contains('open')) {
        return;
    }

    if (event.target.closest('[data-seat-modal-close]') || event.target === modal) {
        event.preventDefault();
        event.stopPropagation();
        requestSeatModalClose();
    }
}, true);

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.getElementById('seat-modal')?.classList.contains('open')) {
        requestSeatModalClose();
    }
});

/** Worst status wins for a table’s badge (occupied > reserved > free). */
function tableAggregateStatusFromDom(tableId) {
    const dots = document.querySelectorAll(`.seating-seat-dot[data-table-id="${tableId}"]`);
    let worst = 'free';
    dots.forEach((el) => {
        const st = el.getAttribute('data-status') ?? 'free';
        if (st === 'occupied') {
            worst = 'occupied';
        } else if (st === 'cleaning' && worst !== 'occupied') {
            worst = 'cleaning';
        } else if (st === 'reserved' && worst !== 'occupied' && worst !== 'cleaning') {
            worst = 'reserved';
        }
    });
    return worst;
}

const TABLE_BADGE_STATUS_LABEL = {
    free: 'Free',
    reserved: 'Reserved',
    occupied: 'Occupied',
    cleaning: 'Cleaning',
};

function applyTableBadgeVisual(tableId) {
    const status = tableAggregateStatusFromDom(tableId);
    document.querySelectorAll(`[data-seating-group][data-table-id="${tableId}"] .seating-badge-card`).forEach((badge) => {
        badge.classList.remove('seating-badge--free', 'seating-badge--reserved', 'seating-badge--occupied', 'seating-badge--cleaning');
        badge.classList.add(`seating-badge--${status}`);
    });
    document.querySelectorAll(`[data-seating-group][data-table-id="${tableId}"] .seating-badge-status`).forEach((el) => {
        el.textContent = TABLE_BADGE_STATUS_LABEL[status] ?? TABLE_BADGE_STATUS_LABEL.free;
    });
}

function applySeatStatus(seatId, status) {
    const el = document.querySelector(`[data-seat-id="${seatId}"]`);
    if (!el) {
        return;
    }
    el.setAttribute('data-status', status);
    el.classList.remove('available', 'reserved', 'occupied', 'cleaning');
    el.classList.add(statusToDotClass(status));
    const label = el.getAttribute('data-label') ?? `Seat ${seatId}`;
    el.setAttribute('aria-label', `${label}, ${status}`);
    const tid = el.getAttribute('data-table-id');
    if (tid) {
        applyTableBadgeVisual(tid);
    }
}

/** After table label/capacity/type change from API — keep dots and badge in sync. */
function applyTableMetaToDom(tableId, table) {
    const label = table.label;
    const capacity = String(table.capacity);
    const furnitureType = table.furniture_type ?? 'standard';

    document.querySelectorAll(`.seating-seat-dot[data-table-id="${tableId}"]`).forEach((el) => {
        const idx = el.getAttribute('data-seat-index') ?? '1';
        el.setAttribute('data-table-label', label);
        el.setAttribute('data-capacity', capacity);
        el.setAttribute('data-furniture-type', furnitureType);
        el.setAttribute('data-label', `${label} — seat ${idx}`);
        const st = el.getAttribute('data-status') ?? 'free';
        el.setAttribute('aria-label', `${label} — seat ${idx}, ${st}`);
    });

    document.querySelectorAll(`[data-seating-group][data-table-id="${tableId}"]`).forEach((group) => {
        const isShell = group.classList.contains('seating-group-shell');
        const labelEl = group.querySelector('.seating-badge-label');
        const partyVal = group.querySelector('.seating-badge-party-value');
        if (labelEl) {
            labelEl.textContent = label;
        }
        if (partyVal) {
            partyVal.textContent = capacity;
        }
        const labelText = group.querySelector('.seating-badge-label')?.textContent ?? label;
        const statusText = group.querySelector('.seating-badge-status')?.textContent ?? '';
        const guestText = group.querySelector('.seating-badge-guest')?.textContent ?? '—';
        const partyText = group.querySelector('.seating-badge-party-value')?.textContent ?? capacity;
        const tip = `${labelText}, ${statusText}, ${guestText}, ${partyText}`;
        group.setAttribute('title', isShell ? `${tip} (merged group)` : tip);
    });
    applyTableBadgeVisual(tableId);
}

async function syncSeatsFromApi(url) {
    const { data } = await axios.get(url, { headers: { Accept: 'application/json' } });
    for (const s of data.seats ?? []) {
        applySeatStatus(s.id, s.status);
    }
}

async function refreshSeatMapData(apiSeats) {
    if (apiSeats) {
        await syncSeatsFromApi(apiSeats);
    }
    if (typeof window.Livewire !== 'undefined' && typeof window.Livewire.dispatch === 'function') {
        window.Livewire.dispatch('tables-refresh');
    }
}

function confirmAsync(message) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-[1000] flex items-center justify-center bg-black/50 p-4';

        const panel = document.createElement('div');
        panel.className = 'w-full max-w-sm rounded-xl border border-slate-200 bg-white p-4 shadow-2xl';

        const text = document.createElement('p');
        text.className = 'text-sm font-medium text-slate-800';
        text.textContent = message;

        const row = document.createElement('div');
        row.className = 'mt-4 flex justify-end gap-2';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className =
            'rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50';
        cancelBtn.textContent = 'Cancel';

        const okBtn = document.createElement('button');
        okBtn.type = 'button';
        okBtn.className =
            'rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-700';
        okBtn.textContent = 'Confirm';

        function cleanup(val) {
            overlay.remove();
            resolve(val);
        }

        cancelBtn.addEventListener('click', () => cleanup(false));
        okBtn.addEventListener('click', () => cleanup(true));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                cleanup(false);
            }
        });

        row.appendChild(cancelBtn);
        row.appendChild(okBtn);
        panel.appendChild(text);
        panel.appendChild(row);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);
    });
}

function setActiveSeatOptions(status) {
    document.querySelectorAll('#seat-modal .seating-s-opt').forEach((el) => {
        const isSel = el.getAttribute('data-seat-status') === status;
        el.classList.toggle('active', isSel);
        el.setAttribute('aria-checked', isSel ? 'true' : 'false');
    });
}

function initCopySnippet(root, apiSeats) {
    const btn = document.getElementById('copy-seating-snippet');
    if (!btn || !apiSeats) {
        return;
    }
    btn.addEventListener('click', async () => {
        try {
            const { data } = await axios.get(apiSeats, { headers: { Accept: 'application/json' } });
            const lines = (data.seats ?? []).map(
                (s) =>
                    `    ['id' => ${s.id}, 'table' => '${String(s.table_label).replace(/'/g, "\\'")}', 'seat' => ${s.seat_index}, 'pos_x' => ${s.pos_x}, 'pos_y' => ${s.pos_y}, 'status' => '${s.status}'],`,
            );
            const text = '[\n' + lines.join('\n') + '\n]';
            await navigator.clipboard.writeText(text);
            window.showToast?.('success', 'Snippet copied');
        } catch (e) {
            console.error(e);
            window.showToast?.('error', 'Could not copy');
        }
    });
}

function rectsIntersect(a, b) {
    return !(a.right <= b.left || a.left >= b.right || a.bottom <= b.top || a.top >= b.bottom);
}

/** Coordinate surface for % positions: same box as the floor plan image (see #seating-map-stage). */
function initPlacementMode(stage, root, onPlaceTap) {
    const toggle = document.getElementById('seating-placement-toggle');
    const hint = document.getElementById('seating-placement-hint');

    if (!toggle || !stage) {
        return;
    }

    const TAP_MAX_PX = 8;

    /** @type {{ px: number; py: number; cx: number; cy: number; pointerId: number } | null} */
    let placePending = null;

    function setPlacementActive(on) {
        root.classList.toggle('placement-mode', on);
        stage.classList.toggle('is-placement-mode', on);
        hint?.classList.toggle('hidden', !on);
        const label = toggle.querySelector('.seating-tool-btn__text');
        if (label) {
            label.textContent = on ? 'Done adding seats' : 'Add seats';
        } else {
            toggle.textContent = on ? 'Done adding seats' : 'Add seats';
        }
        toggle.classList.toggle('seating-tool-btn--active-placement', on);
        if (!on) {
            placePending = null;
        }
    }

    toggle.addEventListener('click', () => {
        setPlacementActive(!root.classList.contains('placement-mode'));
    });

    function clientToPercent(clientX, clientY) {
        const r = stage.getBoundingClientRect();
        const px = ((clientX - r.left) / r.width) * 100;
        const py = ((clientY - r.top) / r.height) * 100;

        return {
            px: Math.max(0, Math.min(100, px)),
            py: Math.max(0, Math.min(100, py)),
        };
    }

    stage.addEventListener('pointerdown', (e) => {
        if (!root.classList.contains('placement-mode')) {
            return;
        }
        if (e.pointerType === 'mouse' && e.button !== 0) {
            return;
        }
        if (e.target.closest('.seating-seat-dot') || e.target.closest('.seating-tbl-label')) {
            return;
        }
        const { px, py } = clientToPercent(e.clientX, e.clientY);
        placePending = {
            px,
            py,
            cx: e.clientX,
            cy: e.clientY,
            pointerId: e.pointerId,
        };
        try {
            stage.setPointerCapture(e.pointerId);
        } catch {
            /* ignore */
        }
    });

    stage.addEventListener('pointerup', async (e) => {
        if (!root.classList.contains('placement-mode') || !placePending) {
            return;
        }
        if (e.pointerId !== placePending.pointerId) {
            return;
        }
        const dist = Math.hypot(e.clientX - placePending.cx, e.clientY - placePending.cy);
        const { px, py } = placePending;
        placePending = null;
        try {
            stage.releasePointerCapture(e.pointerId);
        } catch {
            /* ignore */
        }
        if (dist > TAP_MAX_PX) {
            return;
        }
        if (typeof onPlaceTap === 'function') {
            onPlaceTap(px, py);
        }
    });

    stage.addEventListener('pointercancel', (e) => {
        if (placePending && e.pointerId === placePending.pointerId) {
            placePending = null;
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && root.classList.contains('placement-mode')) {
            setPlacementActive(false);
        }
    });
}

const LONG_PRESS_MS = 550;
const EMPTY_MOVE_START_DRAG_PX = 10;
const DOT_MOVE_CANCEL_LONG_PRESS_PX = 14;

/**
 * Marquee box-select, Selection mode (toggle + tap), Ctrl/Cmd+click, long-press on markers to enter selection.
 */
function initSeatSelection(stage, root, { openEditModal }) {
    const marquee = document.getElementById('seating-marquee-rect');
    const bar = document.getElementById('seating-selection-bar');
    const countEl = document.getElementById('seating-selection-count');
    const groupBtn = document.getElementById('seating-group-open');
    const clearBtn = document.getElementById('seating-clear-selection');
    const groupModal = document.getElementById('group-table-modal');
    const groupInput = document.getElementById('group-table-label-input');
    const groupSubmit = document.getElementById('group-table-submit');
    const groupCancel = document.getElementById('group-table-cancel');
    const groupClose = document.getElementById('group-table-modal-close');
    const selectionModeToggle = document.getElementById('seating-selection-mode-toggle');
    const selectionModeHint = document.getElementById('seating-selection-mode-hint');
    const placementToggle = document.getElementById('seating-placement-toggle');

    if (!marquee || !stage || !root) {
        return { exitSelectionMode: () => {} };
    }

    const apiGroup = root.dataset.apiGroup;
    const apiSeats = root.dataset.apiSeats;
    const groupingEnabled = Boolean(apiGroup && groupModal && groupBtn);

    /** @type {Set<string>} */
    let selectedIds = new Set();
    let dragActive = false;
    let startX = 0;
    let startY = 0;
    let pointerId = null;
    let selectionMode = false;
    let suppressNextDotClick = false;
    /** @type {ReturnType<typeof setTimeout> | null} */
    let emptyLongPressTimer = null;
    /** @type {{ pointerId: number; startCX: number; startCY: number; longPressDone?: boolean } | null} */
    let emptyGesture = null;

    const MIN_DRAG_PX = 4;

    function clearMarqueeVisual() {
        marquee.classList.remove('is-active');
        marquee.style.width = '0px';
        marquee.style.height = '0px';
        marquee.style.left = '0px';
        marquee.style.top = '0px';
    }

    function clearSelection() {
        selectedIds = new Set();
        document.querySelectorAll('.seating-seat-dot.is-marquee-selected').forEach((el) => {
            el.classList.remove('is-marquee-selected');
        });
        updateSelectionBar();
    }

    function mergedGuestCapacityPreview() {
        const byTable = new Map();
        selectedIds.forEach((id) => {
            const el = document.querySelector(`[data-seat-id="${id}"]`);
            if (!el) {
                return;
            }
            const tid = el.getAttribute('data-table-id');
            if (!tid || byTable.has(tid)) {
                return;
            }
            const cap = parseInt(el.getAttribute('data-capacity') ?? '1', 10);
            byTable.set(tid, Number.isNaN(cap) ? 1 : cap);
        });
        let sum = 0;
        byTable.forEach((c) => {
            sum += c;
        });
        return sum;
    }

    function updateSelectionBar() {
        const n = selectedIds.size;
        if (!bar || !countEl || !groupBtn) {
            return;
        }
        if (n === 0) {
            bar.classList.add('hidden');
        } else {
            bar.classList.remove('hidden');
            const sumCap = mergedGuestCapacityPreview();
            const merged = Math.max(n, sumCap);
            countEl.textContent = `${n} marker${n === 1 ? '' : 's'} selected · merged guests ≈ ${merged}`;
            groupBtn.disabled = false;
        }
    }

    function getSeatIdsForTable(tableId) {
        const ids = [];
        document.querySelectorAll(`.seating-seat-dot[data-table-id="${tableId}"]`).forEach((el) => {
            const sid = el.getAttribute('data-seat-id');
            if (sid) {
                ids.push(sid);
            }
        });
        return ids;
    }

    function applySelectionFromRect(selClient) {
        selectedIds = new Set();
        const touchedTableIds = new Set();
        document.querySelectorAll('.seating-seat-dot').forEach((dot) => {
            const r = dot.getBoundingClientRect();
            if (rectsIntersect(selClient, r)) {
                const tid = dot.getAttribute('data-table-id');
                if (tid) {
                    touchedTableIds.add(tid);
                }
            }
        });

        document.querySelectorAll('.seating-seat-dot').forEach((dot) => {
            dot.classList.remove('is-marquee-selected');
        });

        touchedTableIds.forEach((tid) => {
            getSeatIdsForTable(tid).forEach((id) => {
                selectedIds.add(id);
                document.querySelector(`[data-seat-id="${id}"]`)?.classList.add('is-marquee-selected');
            });
        });
        updateSelectionBar();
    }

    function syncSelectionModeUi() {
        root.classList.toggle('selection-mode', selectionMode);
        stage.classList.toggle('is-selection-mode', selectionMode);
        selectionModeHint?.classList.toggle('hidden', !selectionMode);
        if (selectionModeToggle) {
            selectionModeToggle.setAttribute('aria-pressed', selectionMode ? 'true' : 'false');
            const label = selectionModeToggle.querySelector('.seating-tool-btn__text');
            if (label) {
                label.textContent = selectionMode ? 'Done selecting' : 'Selection';
            } else {
                selectionModeToggle.textContent = selectionMode ? 'Done selecting' : 'Selection';
            }
            selectionModeToggle.classList.toggle('seating-tool-btn--active-selection', selectionMode);
        }
    }

    function enterSelectionMode() {
        if (root.classList.contains('placement-mode')) {
            placementToggle?.click();
        }
        selectionMode = true;
        syncSelectionModeUi();
    }

    function exitSelectionMode() {
        selectionMode = false;
        syncSelectionModeUi();
    }

    function setMarqueeClassForSeatIds(ids, on) {
        ids.forEach((sid) => {
            document.querySelector(`[data-seat-id="${sid}"]`)?.classList.toggle('is-marquee-selected', on);
        });
    }

    function toggleSeatInSelection(id, el) {
        const tableId = el.getAttribute('data-table-id');
        const groupIds = tableId ? getSeatIdsForTable(tableId) : [id];

        if (groupIds.length > 1) {
            const allSelected = groupIds.every((sid) => selectedIds.has(sid));
            if (allSelected) {
                groupIds.forEach((sid) => {
                    selectedIds.delete(sid);
                });
                setMarqueeClassForSeatIds(groupIds, false);
            } else {
                groupIds.forEach((sid) => {
                    selectedIds.add(sid);
                });
                setMarqueeClassForSeatIds(groupIds, true);
            }
        } else if (selectedIds.has(id)) {
            selectedIds.delete(id);
            el.classList.remove('is-marquee-selected');
        } else {
            selectedIds.add(id);
            el.classList.add('is-marquee-selected');
        }
        updateSelectionBar();
    }

    function openGroupModal() {
        if (!groupModal || selectedIds.size === 0) {
            return;
        }
        if (groupInput) {
            groupInput.value = '';
        }
        groupModal.classList.add('open');
        groupInput?.focus();
    }

    function closeGroupModal() {
        groupModal?.classList.remove('open');
    }

    selectionModeToggle?.addEventListener('click', () => {
        if (selectionMode) {
            exitSelectionMode();
        } else {
            enterSelectionMode();
        }
    });

    /**
     * Seat marker clicks: Ctrl/Cmd+toggle, Selection mode tap, else open edit modal.
     * Table badge / group shell clicks use the first seat dot for that table (dots are easy to miss).
     */
    root.addEventListener('click', (e) => {
        let target = e.target.closest('.seating-seat-dot');
        if (!target) {
            const groupEl = e.target.closest('[data-seating-group][data-table-id]');
            if (groupEl) {
                const tableId = groupEl.getAttribute('data-table-id');
                if (tableId) {
                    target = document.querySelector(
                        `.seating-seat-dot[data-table-id="${tableId}"]`,
                    );
                }
            }
        }
        if (!target?.getAttribute('data-seat-id')) {
            return;
        }
        if (suppressNextDotClick) {
            suppressNextDotClick = false;
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        const id = target.getAttribute('data-seat-id');
        if (!id) {
            return;
        }
        if (groupingEnabled && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            e.stopPropagation();
            toggleSeatInSelection(id, target);
            return;
        }
        if (groupingEnabled && selectionMode) {
            e.preventDefault();
            e.stopPropagation();
            toggleSeatInSelection(id, target);
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        openEditModal(target);
    });

    /** Long-press on a marker (touch-friendly): enter selection mode and toggle this marker */
    stage.addEventListener(
        'pointerdown',
        (e) => {
            if (!groupingEnabled) {
                return;
            }
            if (root.classList.contains('placement-mode')) {
                return;
            }
            const dot = e.target.closest('.seating-seat-dot');
            if (!dot) {
                return;
            }
            if (e.pointerType === 'mouse' && e.button !== 0) {
                return;
            }
            const startX = e.clientX;
            const startY = e.clientY;
            let cancelled = false;
            const timer = setTimeout(() => {
                if (cancelled) {
                    return;
                }
                enterSelectionMode();
                const id = dot.getAttribute('data-seat-id');
                if (id) {
                    toggleSeatInSelection(id, dot);
                }
                suppressNextDotClick = true;
                try {
                    navigator.vibrate?.(25);
                } catch {
                    /* ignore */
                }
            }, LONG_PRESS_MS);

            function cancel() {
                cancelled = true;
                clearTimeout(timer);
            }

            function onMove(ev) {
                if (Math.hypot(ev.clientX - startX, ev.clientY - startY) > DOT_MOVE_CANCEL_LONG_PRESS_PX) {
                    cancel();
                    stage.removeEventListener('pointermove', onMove);
                    stage.removeEventListener('pointerup', onUp);
                    stage.removeEventListener('pointercancel', onUp);
                }
            }

            function onUp() {
                cancel();
                stage.removeEventListener('pointermove', onMove);
                stage.removeEventListener('pointerup', onUp);
                stage.removeEventListener('pointercancel', onUp);
            }

            stage.addEventListener('pointermove', onMove);
            stage.addEventListener('pointerup', onUp);
            stage.addEventListener('pointercancel', onUp);
        },
        true,
    );

    /** Empty map: long-press enters selection mode; drag draws marquee (clears selection when drag starts) */
    stage.addEventListener('pointerdown', (e) => {
        if (!groupingEnabled) {
            return;
        }
        if (root.classList.contains('placement-mode')) {
            return;
        }
        if (e.pointerType === 'mouse' && e.button !== 0) {
            return;
        }
        if (e.target.closest('.seating-seat-dot') || e.target.closest('.seating-tbl-label')) {
            return;
        }

        e.preventDefault();

        clearTimeout(emptyLongPressTimer);
        emptyLongPressTimer = null;
        emptyGesture = {
            pointerId: e.pointerId,
            startCX: e.clientX,
            startCY: e.clientY,
        };

        const r = stage.getBoundingClientRect();
        startX = e.clientX - r.left;
        startY = e.clientY - r.top;
        pointerId = e.pointerId;
        dragActive = false;

        emptyLongPressTimer = setTimeout(() => {
            emptyLongPressTimer = null;
            if (emptyGesture && emptyGesture.pointerId === pointerId && !dragActive) {
                emptyGesture.longPressDone = true;
                enterSelectionMode();
                try {
                    navigator.vibrate?.(20);
                } catch {
                    /* ignore */
                }
            }
        }, LONG_PRESS_MS);
    });

    stage.addEventListener('pointermove', (e) => {
        if (!groupingEnabled) {
            return;
        }
        if (root.classList.contains('placement-mode')) {
            return;
        }
        if (emptyGesture && e.pointerId === emptyGesture.pointerId && !dragActive) {
            const dist = Math.hypot(e.clientX - emptyGesture.startCX, e.clientY - emptyGesture.startCY);
            if (dist > EMPTY_MOVE_START_DRAG_PX) {
                clearTimeout(emptyLongPressTimer);
                emptyLongPressTimer = null;
                if (!emptyGesture.longPressDone) {
                    clearSelection();
                    dragActive = true;
                    marquee.classList.add('is-active');
                    marquee.style.left = `${startX}px`;
                    marquee.style.top = `${startY}px`;
                    marquee.style.width = '0px';
                    marquee.style.height = '0px';
                    try {
                        stage.setPointerCapture(e.pointerId);
                    } catch {
                        /* ignore */
                    }
                }
            }
        }
        if (!dragActive || e.pointerId !== pointerId) {
            return;
        }
        const r = stage.getBoundingClientRect();
        const x = e.clientX - r.left;
        const y = e.clientY - r.top;
        const nx = Math.min(startX, x);
        const ny = Math.min(startY, y);
        const nw = Math.abs(x - startX);
        const nh = Math.abs(y - startY);
        marquee.style.left = `${nx}px`;
        marquee.style.top = `${ny}px`;
        marquee.style.width = `${nw}px`;
        marquee.style.height = `${nh}px`;
    });

    function endPointer(e) {
        if (!groupingEnabled) {
            return;
        }
        clearTimeout(emptyLongPressTimer);
        emptyLongPressTimer = null;

        if (!dragActive) {
            if (emptyGesture && e.pointerId === emptyGesture.pointerId) {
                emptyGesture = null;
                pointerId = null;
            }
            return;
        }

        if (e.pointerId !== pointerId) {
            return;
        }

        dragActive = false;
        pointerId = null;
        emptyGesture = null;

        try {
            stage.releasePointerCapture(e.pointerId);
        } catch {
            /* ignore */
        }

        const w = parseFloat(marquee.style.width) || 0;
        const h = parseFloat(marquee.style.height) || 0;

        if (w < MIN_DRAG_PX || h < MIN_DRAG_PX) {
            clearMarqueeVisual();
            return;
        }

        const selClient = marquee.getBoundingClientRect();
        clearMarqueeVisual();
        applySelectionFromRect(selClient);
    }

    stage.addEventListener('pointerup', endPointer);
    stage.addEventListener('pointercancel', endPointer);

    clearBtn?.addEventListener('click', () => {
        clearSelection();
        clearMarqueeVisual();
    });

    groupBtn?.addEventListener('click', () => {
        if (selectedIds.size === 0) {
            return;
        }
        openGroupModal();
    });

    groupCancel?.addEventListener('click', closeGroupModal);
    groupClose?.addEventListener('click', closeGroupModal);
    groupModal?.addEventListener('click', (e) => {
        if (e.target === groupModal) {
            closeGroupModal();
        }
    });

    async function submitGroupMerge() {
        if (!groupingEnabled || selectedIds.size === 0) {
            return;
        }
        const label = groupInput?.value?.trim() ?? '';
        try {
            await axios.post(
                apiGroup,
                {
                    seat_ids: Array.from(selectedIds, (id) => parseInt(id, 10)),
                    label: label || null,
                },
                { headers: { Accept: 'application/json' } },
            );
            closeGroupModal();
            window.showToast?.('success', 'Table merged');
            await refreshSeatMapData(apiSeats);
        } catch (err) {
            console.error(err);
            window.showToast?.('error', err.response?.data?.message ?? 'Could not group seats');
        }
    }

    groupSubmit?.addEventListener('click', () => {
        submitGroupMerge();
    });

    groupInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitGroupMerge();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (document.getElementById('seat-modal')?.classList.contains('open')) {
                return;
            }
            if (groupModal?.classList.contains('open')) {
                closeGroupModal();
            } else if (!root.classList.contains('placement-mode')) {
                exitSelectionMode();
                clearSelection();
                clearMarqueeVisual();
            }
            return;
        }
        if (e.key !== 'g' && e.key !== 'G') {
            return;
        }
        if (!groupingEnabled) {
            return;
        }
        if (e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }
        const el = e.target;
        if (
            el instanceof HTMLElement &&
            (el.closest('input, textarea, select') || el.isContentEditable)
        ) {
            return;
        }
        if (document.getElementById('seat-modal')?.classList.contains('open')) {
            return;
        }
        if (root.classList.contains('placement-mode')) {
            return;
        }
        if (groupModal?.classList.contains('open')) {
            return;
        }
        if (selectedIds.size < 2) {
            return;
        }
        e.preventDefault();
        openGroupModal();
    });

    return {
        exitSelectionMode,
    };
}

function firstAxiosErrorMessage(err, fallback) {
    const d = err.response?.data;
    if (typeof d?.message === 'string' && d.message) {
        return d.message;
    }
    const errs = d?.errors;
    if (errs && typeof errs === 'object') {
        const first = Object.values(errs)[0];
        if (Array.isArray(first) && first[0]) {
            return first[0];
        }
    }
    return fallback;
}

function showLayoutError(message) {
    const text = message || 'Action could not be completed.';
    window.showToast?.('error', text);
    const box = document.getElementById('seating-layout-error');
    const msg = document.getElementById('seating-layout-error-message');
    if (!box || !msg) {
        return;
    }
    msg.textContent = text;
    box.classList.remove('hidden');
    box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function clearLayoutError() {
    const box = document.getElementById('seating-layout-error');
    const msg = document.getElementById('seating-layout-error-message');
    if (!box || !msg) {
        return;
    }
    msg.textContent = '';
    box.classList.add('hidden');
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('#seating-layout-error-dismiss')) {
        return;
    }
    e.preventDefault();
    clearLayoutError();
});

/** Remove table group overlays and seat dots for a table (after API deleted the table). */
function removeTableFromDom(tableId) {
    const tid = String(tableId);
    document.querySelectorAll('.seating-seat-dot').forEach((el) => {
        if (el.getAttribute('data-table-id') === tid) {
            el.remove();
        }
    });
    document.querySelectorAll(`[data-seating-group][data-table-id="${tid}"]`).forEach((el) => el.remove());
}

/**
 * Delete seat/table from API. Uses document-level delegation target so this still works after Livewire/DOM morphs.
 * Full table removal updates the map without reload; removing one seat from a merged group reloads to refresh layout.
 */
async function postSeatDeleteFromGlobal(scope, seatId, apiDelete) {
    setCsrfHeader();
    const msg =
        scope === 'table'
            ? 'Remove this table and all seat dots from the map?'
            : 'Remove this seat dot from the map?';
    const confirmed = await confirmAsync(msg);
    if (!confirmed) {
        return;
    }
    clearLayoutError();
    try {
        const { data } = await axios.post(
            apiDelete,
            { seat_id: parseInt(seatId, 10), scope },
            { headers: { Accept: 'application/json' } },
        );
        if (data.removed_table_id != null) {
            removeTableFromDom(data.removed_table_id);
            window.__tcSeatModalClose?.();
            window.showToast?.('success', 'Removed');
            return;
        }
        await refreshSeatMapData(document.querySelector('[data-seating-layout]')?.dataset?.apiSeats);
    } catch (err) {
        console.error(err);
        showLayoutError(firstAxiosErrorMessage(err, 'Could not remove'));
    }
}

async function postSeatUnmergeFromGlobal(seatId, apiUnmerge) {
    setCsrfHeader();
    const confirmed = await confirmAsync('Unmerge this table into separate table markers?');
    if (!confirmed) {
        return;
    }
    clearLayoutError();
    try {
        await axios.post(
            apiUnmerge,
            { seat_id: parseInt(seatId, 10) },
            { headers: { Accept: 'application/json' } },
        );
        window.__tcSeatModalClose?.();
        window.showToast?.('success', 'Table unmerged');
        window.location.reload();
    } catch (err) {
        console.error(err);
        showLayoutError(firstAxiosErrorMessage(err, 'Could not unmerge'));
    }
}

document.addEventListener('click', (e) => {
    const t = e.target.closest('#seat-modal-delete-seat, #seat-modal-delete-table, #seat-modal-unmerge-table');
    if (!t) {
        return;
    }
    e.preventDefault();
    const root = document.querySelector('[data-seating-layout]');
    const modal = document.getElementById('seat-modal');
    const sid = modal?.getAttribute('data-selected-seat-id');
    if (!sid) {
        return;
    }
    if (t.id === 'seat-modal-unmerge-table') {
        const apiUnmerge = root?.dataset?.apiUnmerge;
        if (!apiUnmerge) {
            return;
        }
        postSeatUnmergeFromGlobal(sid, apiUnmerge);

        return;
    }

    const scope = t.id === 'seat-modal-delete-table' ? 'table' : 'seat';
    const apiDelete = root?.dataset?.apiDelete;
    if (!apiDelete) {
        return;
    }
    postSeatDeleteFromGlobal(scope, sid, apiDelete);
});

function initSeatingLayout() {
    const root = document.querySelector('[data-seating-layout]');
    if (!root) {
        return;
    }

    setCsrfHeader();

    const apiSeats = root.dataset.apiSeats;
    const apiUpdate = root.dataset.apiUpdate;
    const apiPlace = root.dataset.apiPlace;
    const apiDelete = root.dataset.apiDelete;
    const apiUnmerge = root.dataset.apiUnmerge;
    if (!apiSeats || !apiUpdate) {
        return;
    }

    initCopySnippet(root, apiSeats);

    const mapStage = document.getElementById('seating-map-stage');
    const modal = document.getElementById('seat-modal');
    const title = document.getElementById('seat-modal-title');
    const sub = document.getElementById('seat-modal-sub');
    const nameInput = document.getElementById('seat-modal-table-name');
    const capacityRow = document.getElementById('seat-modal-capacity-row');
    const capacityInput = document.getElementById('seat-modal-capacity');
    const capacityHint = document.getElementById('seat-modal-capacity-hint');
    const furnitureSelect = document.getElementById('seat-modal-furniture-type');
    const saveBtn = document.getElementById('seat-modal-save');
    const closeBtn = document.getElementById('seat-modal-close');
    const doneBtn = document.getElementById('seat-modal-done');
    const unmergeRow = document.getElementById('seat-modal-unmerge-row');
    const deleteRow = document.getElementById('seat-modal-delete-row');

    /** @type {'place' | 'edit' | null} */
    let modalMode = null;
    let selectedSeatId = null;
    /** @type {{ px: number; py: number } | null} */
    let pendingPlace = null;

    function getActiveStatusFromModal() {
        const active = document.querySelector('#seat-modal .seating-s-opt.active');
        return active?.getAttribute('data-seat-status') ?? 'free';
    }

    function openEditModal(dot) {
        modalMode = 'edit';
        pendingPlace = null;
        selectedSeatId = dot.getAttribute('data-seat-id');
        const tableLabel = dot.getAttribute('data-table-label') ?? '';
        const seatIdx = dot.getAttribute('data-seat-index') ?? '1';
        const status = dot.getAttribute('data-status') ?? 'free';
        const seatCount = parseInt(dot.getAttribute('data-table-seat-count') ?? '1', 10);
        if (title) {
            title.textContent = tableLabel ? `Edit ${tableLabel}` : 'Edit table marker';
        }
        if (sub) {
            sub.textContent = `Marker ${seatIdx} - update details and status.`;
        }
        if (nameInput) {
            nameInput.value = tableLabel;
        }
        if (capacityInput) {
            const minSeats = parseInt(dot.getAttribute('data-table-seat-count') ?? '1', 10);
            capacityInput.min = String(Math.max(1, minSeats));
            capacityInput.value = dot.getAttribute('data-capacity') ?? String(minSeats);
        }
        if (capacityHint) {
            capacityHint.textContent =
                'Must be at least the number of dots in this group (guest capacity for bookings).';
        }
        if (furnitureSelect) {
            furnitureSelect.value = dot.getAttribute('data-furniture-type') ?? 'standard';
        }
        capacityRow?.classList.remove('hidden');
        unmergeRow?.classList.toggle('hidden', !(apiUnmerge && seatCount > 1));
        if (deleteRow) {
            const canDelete = Boolean(root.dataset.apiDelete);
            deleteRow.classList.toggle('hidden', !canDelete);
        }
        modal?.querySelector('#seat-modal-delete-row details')?.removeAttribute('open');
        setActiveSeatOptions(status);
        modal?.setAttribute('data-selected-seat-id', selectedSeatId);
        showSeatModal(modal);
    }

    function openPlaceModal(px, py) {
        if (!apiPlace) {
            return;
        }
        modalMode = 'place';
        pendingPlace = { px, py };
        selectedSeatId = null;
        if (title) {
            title.textContent = 'Add table marker';
        }
        if (sub) {
            sub.textContent = 'Place this marker on the blueprint and enter the table details.';
        }
        if (nameInput) {
            nameInput.value = '';
        }
        if (capacityInput) {
            capacityInput.min = '1';
            capacityInput.value = '1';
        }
        if (capacityHint) {
            capacityHint.textContent = 'Guest capacity for bookings and walk-in seating.';
        }
        if (furnitureSelect) {
            furnitureSelect.value = 'standard';
        }
        capacityRow?.classList.remove('hidden');
        unmergeRow?.classList.add('hidden');
        deleteRow?.classList.add('hidden');
        setActiveSeatOptions('free');
        modal?.removeAttribute('data-selected-seat-id');
        showSeatModal(modal);
    }

    function closeModal() {
        selectedSeatId = null;
        pendingPlace = null;
        modalMode = null;
        modal?.removeAttribute('data-selected-seat-id');
        modal?.querySelector('#seat-modal-delete-row details')?.removeAttribute('open');
        hardCloseSeatModal();
    }

    /** @type {{ exitSelectionMode: () => void }} */
    let selectionApi = { exitSelectionMode: () => {} };

    if (mapStage) {
        initPlacementMode(mapStage, root, openPlaceModal);
        selectionApi = initSeatSelection(mapStage, root, { openEditModal });
    }

    document.getElementById('seating-placement-toggle')?.addEventListener('click', () => {
        setTimeout(() => {
            if (root.classList.contains('placement-mode')) {
                selectionApi.exitSelectionMode();
            }
        }, 0);
    });

    closeBtn?.addEventListener('click', closeModal);
    doneBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    modal?.querySelectorAll('.seating-s-opt').forEach((opt) => {
        opt.addEventListener('click', () => {
            const status = opt.getAttribute('data-seat-status');
            if (!status) {
                return;
            }
            setActiveSeatOptions(status);
        });
        opt.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                opt.click();
            }
        });
    });

    saveBtn?.addEventListener('click', async () => {
        if (modalMode === 'place' && pendingPlace && apiPlace) {
            const capPlace = parseInt(capacityInput?.value ?? '1', 10);
            if (Number.isNaN(capPlace) || capPlace < 1) {
                window.showToast?.('error', 'Enter a valid capacity (guests)');
                return;
            }
            try {
                await axios.post(
                    apiPlace,
                    {
                        pos_x: pendingPlace.px,
                        pos_y: pendingPlace.py,
                        label: nameInput?.value?.trim() || undefined,
                        capacity: capPlace,
                        furniture_type: furnitureSelect?.value ?? 'standard',
                        status: getActiveStatusFromModal(),
                    },
                    { headers: { Accept: 'application/json' } },
                );
                closeModal();
                window.showToast?.('success', 'Table marker added');
                window.Livewire.dispatch('tables-refresh');
                setTimeout(() => window.location.reload(), 600);
            } catch (err) {
                console.error(err);
                window.showToast?.('error', firstAxiosErrorMessage(err, 'Could not add seat'));
            }
            return;
        }

        if (modalMode === 'edit' && selectedSeatId) {
            const cap = parseInt(capacityInput?.value ?? '1', 10);
            if (Number.isNaN(cap) || cap < 1) {
                window.showToast?.('error', 'Enter a valid capacity');
                return;
            }
            const labelTrim = nameInput?.value?.trim() ?? '';
            const payload = {
                seat_id: parseInt(selectedSeatId, 10),
                status: getActiveStatusFromModal(),
                capacity: cap,
                furniture_type: furnitureSelect?.value ?? 'standard',
            };
            if (labelTrim !== '') {
                payload.label = labelTrim;
            }
            try {
                const { data } = await axios.post(apiUpdate, payload, {
                    headers: { Accept: 'application/json' } },
                );
                const list = data.seats ?? [];
                for (const s of list) {
                    applySeatStatus(String(s.id), s.status);
                }
                if (data.table) {
                    applyTableMetaToDom(String(data.table.id), data.table);
                }
                closeModal();
                window.showToast?.('success', 'Saved');
            } catch (err) {
                console.error(err);
                window.showToast?.('error', firstAxiosErrorMessage(err, 'Could not save'));
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    syncSeatsFromApi(apiSeats).catch(() => {});

    if (root.dataset.waitlistTablePick === 'true') {
        window.__tcSeatingRefreshFromApi = () => syncSeatsFromApi(apiSeats).catch(() => {});
    }

    window.__tcSeatModalClose = closeModal;
}

document.addEventListener('DOMContentLoaded', initSeatingLayout);
