/**
 * Dashboard seat map: dot tap behavior is state-driven.
 * - If waitlist pick-table state is active, tap assigns that table.
 * - Otherwise, tap opens table quick-actions popover.
 * Ctrl/Cmd+click passes through.
 */

function getDashboardSeatMapContext() {
    const dashboard = document.querySelector('[data-dashboard-seat-map]');
    if (!dashboard) {
        return null;
    }
    const seating = dashboard.querySelector('[data-seating-layout]');
    if (!seating || seating.dataset.waitlistTablePick === 'false') {
        return null;
    }
    return { dashboard, seating };
}

function clearWaitlistPickVisual() {
    document.querySelectorAll('.seating-seat-dot.is-waitlist-seat-at').forEach((el) => {
        el.classList.remove('is-waitlist-seat-at');
    });
}

function setWaitlistPickVisual(tableId) {
    clearWaitlistPickVisual();
    if (tableId === null || tableId === undefined) {
        return;
    }
    const id = String(tableId);
    document.querySelectorAll(`.seating-seat-dot[data-table-id="${id}"]`).forEach((el) => {
        el.classList.add('is-waitlist-seat-at');
    });
}

let lastWaitlistPickTableId = null;

function clearTableOpsVisual() {
    document.querySelectorAll('.seating-seat-dot.is-table-ops-selected').forEach((el) => {
        el.classList.remove('is-table-ops-selected');
    });
}

function setTableOpsVisual(tableId) {
    clearTableOpsVisual();
    if (tableId === null || tableId === undefined) {
        return;
    }
    const id = String(tableId);
    document.querySelectorAll(`.seating-seat-dot[data-table-id="${id}"]`).forEach((el) => {
        el.classList.add('is-table-ops-selected');
    });
}

let lastTableOpsTableId = null;

function hasActiveWaitlistPickState() {
    return lastWaitlistPickTableId !== null;
}

function parseTableIdPayload(payload) {
    if (payload == null) {
        return null;
    }
    if (typeof payload === 'object' && 'tableId' in payload) {
        return payload.tableId;
    }

    return null;
}

function bindDashboardSeatDotGestures() {
    document.addEventListener(
        'click',
        (e) => {
            const ctx = getDashboardSeatMapContext();
            if (!ctx) {
                return;
            }
            const { seating } = ctx;
            const dot = e.target.closest('.seating-seat-dot');
            const group = dot ? null : e.target.closest('[data-seating-group][data-table-id]');
            const target = dot ?? group;

            if (!target || !seating.contains(target)) {
                return;
            }

            if (e.ctrlKey || e.metaKey) {
                return;
            }

            const tid = parseInt(target.getAttribute('data-table-id'), 10);
            if (!Number.isFinite(tid)) {
                return;
            }

            if (typeof window.Livewire === 'undefined') {
                return;
            }

            e.preventDefault();
            e.stopImmediatePropagation();

            if (hasActiveWaitlistPickState()) {
                lastWaitlistPickTableId = tid;
                window.Livewire.dispatch('table-selected', { tableId: tid });
                setWaitlistPickVisual(tid);
                return;
            }

            lastTableOpsTableId = tid;
            const rect = target.getBoundingClientRect();
            window.Livewire.dispatch('table-ops-select', {
                tableId: tid,
                left: rect.left + rect.width / 2,
                top: rect.top + rect.height / 2,
            });
            setTableOpsVisual(tid);
        },
        true,
    );
}

document.addEventListener('DOMContentLoaded', () => {
    bindDashboardSeatDotGestures();
});

document.addEventListener(
    'keydown',
    (e) => {
        if (e.key !== 'Escape') {
            return;
        }
        if (!getDashboardSeatMapContext()) {
            return;
        }
        if (lastTableOpsTableId === null) {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        lastTableOpsTableId = null;
        if (typeof window.Livewire === 'undefined') {
            return;
        }
        window.Livewire.dispatch('table-ops-select', { tableId: null });
        setTableOpsVisual(null);
    },
    true,
);

document.addEventListener('livewire:init', () => {
    if (typeof window.Livewire === 'undefined' || typeof window.Livewire.on !== 'function') {
        return;
    }

    window.Livewire.on('tables-refresh', () => {
        window.__tcSeatingRefreshFromApi?.();
    });

    window.Livewire.on('table-selected', (payload) => {
        if (!getDashboardSeatMapContext()) {
            return;
        }
        const id = parseTableIdPayload(payload);
        lastWaitlistPickTableId = id ?? null;
        setWaitlistPickVisual(id);
    });

    window.Livewire.on('table-ops-select', (payload) => {
        if (!getDashboardSeatMapContext()) {
            return;
        }
        const id = parseTableIdPayload(payload);
        lastTableOpsTableId = id ?? null;
        setTableOpsVisual(id);
    });
});
