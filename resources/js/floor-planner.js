import axios from 'axios';

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const GRID_SIZE = 20;
const MIN_SIZE = 64;
const TABLE_MARGIN = 12;
const ZONE_PADDING = 12;
const ZONE_HEADER_SPACE = 54;
const FOCUS_ZOOM = 1.35;
const DEFAULT_STATUS = 'available';
const DEFAULT_ZONE_KEY = 'dining-a';
const MERGE_STORAGE_KEY = 'cafe-gervacios-floor-plan-merge-groups';

const ZONES = [
    { key: 'counter', name: 'Counter', x: 28, y: 38, width: 252, height: 178 },
    { key: 'dining-a', name: 'Main Dining', x: 312, y: 58, width: 352, height: 286 },
    { key: 'window', name: 'Window Booths', x: 700, y: 58, width: 462, height: 190 },
    { key: 'group', name: 'Group Tables', x: 700, y: 286, width: 306, height: 406 },
    { key: 'kitchen', name: 'Staff / Kitchen Area', x: 1038, y: 286, width: 124, height: 406 },
    { key: 'dining-b', name: 'Main Dining', x: 312, y: 376, width: 352, height: 316 },
    { key: 'waiting', name: 'Waiting Area', x: 28, y: 520, width: 246, height: 198 },
];

function notify(type, message) {
    if (typeof window.showToast === 'function') {
        window.showToast(type, message);
        return;
    }

    if (type === 'error') {
        console.error(message);
        return;
    }

    console.log(message);
}

function firstError(error, fallback = 'Something went wrong') {
    const data = error?.response?.data;
    if (data?.errors) {
        const first = Object.values(data.errors).flat()[0];
        if (first) return first;
    }

    return data?.message || fallback;
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function snap(value, enabled) {
    if (!enabled) return Math.round(value);

    return Math.round(value / GRID_SIZE) * GRID_SIZE;
}

function normalizeStatus(status) {
    if (status === 'free') return 'available';

    return status || DEFAULT_STATUS;
}

function statusLabel(status) {
    return normalizeStatus(status) === 'available'
        ? 'FREE'
        : normalizeStatus(status).replace(/_/g, ' ').toUpperCase();
}

function tableStatusText(status) {
    const label = statusLabel(status).toLowerCase();

    return label.charAt(0).toUpperCase() + label.slice(1);
}

function statusClasses(status) {
    const normalized = normalizeStatus(status);

    return {
        available: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        reserved: 'border-blue-200 bg-blue-50 text-blue-800',
        occupied: 'border-orange-200 bg-orange-50 text-orange-800',
        cleaning: 'border-slate-200 bg-slate-100 text-slate-700',
    }[normalized] || 'border-slate-200 bg-slate-50 text-slate-700';
}

function statusActionClasses(status) {
    return {
        available: 'fp-status-action border-emerald-200 bg-emerald-50 text-emerald-800',
        reserved: 'fp-status-action border-blue-200 bg-blue-50 text-blue-800',
        occupied: 'fp-status-action border-orange-200 bg-orange-50 text-orange-800',
        cleaning: 'fp-status-action border-slate-200 bg-slate-100 text-slate-700',
    }[status] || 'fp-status-action';
}

function zoneByKey(key) {
    return ZONES.find((zone) => zone.key === key) || null;
}

function zoneForPoint(x, y) {
    return ZONES.find((zone) => (
        x >= zone.x
        && x <= zone.x + zone.width
        && y >= zone.y
        && y <= zone.y + zone.height
    )) || null;
}

function nearestZoneForPoint(x, y) {
    return ZONES.reduce((best, zone) => {
        const cx = zone.x + (zone.width / 2);
        const cy = zone.y + (zone.height / 2);
        const distance = ((cx - x) ** 2) + ((cy - y) ** 2);

        return !best || distance < best.distance ? { zone, distance } : best;
    }, null)?.zone || zoneByKey(DEFAULT_ZONE_KEY);
}

function tableCenter(table) {
    return {
        x: Number(table.x || 0) + (Number(table.width || 0) / 2),
        y: Number(table.y || 0) + (Number(table.height || 0) / 2),
    };
}

function tableZoneObject(table) {
    const center = tableCenter(table);

    return zoneForPoint(center.x, center.y) || nearestZoneForPoint(center.x, center.y);
}

function tableZone(table) {
    return tableZoneObject(table)?.name || 'Dining Area A';
}

function rectFor(table, x = table.x, y = table.y) {
    const width = Math.max(MIN_SIZE, Number(table.width || MIN_SIZE));
    const height = Math.max(MIN_SIZE, Number(table.height || MIN_SIZE));

    return {
        left: Number(x || 0),
        top: Number(y || 0),
        right: Number(x || 0) + width,
        bottom: Number(y || 0) + height,
    };
}

function rectsOverlap(a, b, margin = TABLE_MARGIN) {
    return a.left < b.right + margin
        && a.right > b.left - margin
        && a.top < b.bottom + margin
        && a.bottom > b.top - margin;
}

function tableDimensions(shape, capacity) {
    if (shape === 'booth') {
        return { width: 168, height: 92 };
    }
    if (shape === 'rectangle' || capacity >= 6) {
        return { width: 182, height: 104 };
    }
    if (shape === 'round') {
        return { width: 120, height: 120 };
    }
    if (shape === 'counter') {
        return { width: 84, height: 70 };
    }

    return capacity <= 2 ? { width: 106, height: 92 } : { width: 124, height: 108 };
}

function zoneDefault(zoneKey) {
    return {
        counter: { shape: 'counter', capacity: 1 },
        group: { shape: 'rectangle', capacity: 6 },
        window: { shape: 'booth', capacity: 4 },
        waiting: { shape: 'square', capacity: 2 },
        kitchen: { shape: 'counter', capacity: 1 },
    }[zoneKey] || { shape: 'square', capacity: 4 };
}

function parseTables(root) {
    const node = root.querySelector('[data-floor-planner-json]');
    if (!node) return [];

    try {
        return JSON.parse(node.textContent || '[]').map((table) => ({
            id: Number(table.id),
            label: String(table.label || `T${table.id}`),
            capacity: Number(table.capacity || 1),
            status: normalizeStatus(table.status),
            shape: table.shape || 'square',
            x: Number(table.x || 0),
            y: Number(table.y || 0),
            width: Number(table.width || 120),
            height: Number(table.height || 90),
            rotation: Number(table.rotation || 0),
            seat_count: Number(table.seat_count || 0),
            booking: table.booking || null,
        }));
    } catch (error) {
        console.warn('Could not parse planner tables', error);
        return [];
    }
}

function payload(table) {
    return {
        id: table.id,
        label: table.label,
        capacity: table.capacity,
        planner_shape: table.shape,
        position_x: table.x,
        position_y: table.y,
        layout_width: table.width,
        layout_height: table.height,
        layout_rotation: table.rotation,
    };
}

function apiPlannerTables(response) {
    return response?.data?.planner?.plannerTables || [];
}

function normalizeMergeGroups(groups) {
    if (!Array.isArray(groups)) return [];

    return groups
        .map((group) => ({
            id: String(group.id || `merge-${Date.now()}`),
            tableIds: Array.isArray(group.table_ids || group.tableIds)
                ? (group.table_ids || group.tableIds).map((id) => Number(id)).filter(Boolean)
                : [],
        }))
        .filter((group) => group.tableIds.length > 1);
}

function loadMergeGroups(root) {
    const embedded = root.querySelector('[data-floor-planner-merge-json]');
    if (embedded) {
        try {
            const groups = normalizeMergeGroups(JSON.parse(embedded.textContent || '[]'));
            if (groups.length) return groups;
        } catch (error) {
            console.warn('Could not parse floor plan merge groups', error);
        }
    }

    try {
        const raw = window.localStorage?.getItem(MERGE_STORAGE_KEY);
        if (!raw) return [];

        return normalizeMergeGroups(JSON.parse(raw));
    } catch (error) {
        console.warn('Could not load floor plan merge groups', error);
        return [];
    }
}

function saveMergeGroups(groups) {
    try {
        window.localStorage?.setItem(MERGE_STORAGE_KEY, JSON.stringify(groups));
    } catch (error) {
        console.warn('Could not save floor plan merge groups', error);
    }
}

class FloorPlanner {
    constructor(root) {
        this.root = root;
        this.canEdit = root.dataset.canEdit === '1';
        this.canvas = root.querySelector('[data-floor-planner-canvas]');
        this.scaleEl = root.querySelector('[data-floor-planner-scale]');
        this.scrollEl = root.querySelector('[data-floor-planner-scroll]');
        this.panel = root.querySelector('[data-floor-planner-panel]');
        this.empty = root.querySelector('[data-floor-planner-empty]');
        this.snapInput = root.querySelector('[data-floor-planner-snap]');
        this.modal = root.querySelector('[data-floor-planner-modal]');
        this.addForm = root.querySelector('[data-floor-planner-add-form]');
        this.modeNote = root.querySelector('[data-floor-planner-mode-note]');
        this.editToolbar = root.querySelector('[data-floor-planner-edit-toolbar]');
        this.bookingsUrl = root.dataset.bookingsUrl || '/admin/bookings';
        this.canvasWidth = Number(root.dataset.canvasWidth || 1200);
        this.canvasHeight = Number(root.dataset.canvasHeight || 760);
        this.tables = parseTables(root);
        this.selectedId = null;
        this.selectedGroupId = null;
        this.selectedZoneKey = null;
        this.focusZoneKey = null;
        this.pendingZoneKey = null;
        this.selectedMergeIds = new Set();
        this.mergeGroups = loadMergeGroups(root);
        this.zoom = 1;
        this.editMode = false;
        this.undoStack = [];
        this.dirty = false;
        this.drag = null;
        this.root.floorPlannerInstance = this;

        this.resolveExistingOverlaps();
        this.bind();
        this.render();
    }

    bind() {
        this.root.querySelectorAll('[data-zone-select]').forEach((button) => {
            button.addEventListener('click', () => this.selectZone(button.dataset.zoneSelect));
        });

        this.root.querySelectorAll('[data-zone-add]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                this.openAddModal(button.dataset.zoneAdd);
            });
        });

        this.root.querySelectorAll('[data-floor-planner-action]').forEach((button) => {
            button.addEventListener('click', () => this.handleToolbar(button.dataset.floorPlannerAction));
        });

        this.root.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', () => this.closeAddModal());
        });

        this.modal?.addEventListener('click', (event) => {
            if (event.target === this.modal) this.closeAddModal();
        });

        this.addForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            this.createTableFromModal();
        });

        this.canvas.addEventListener('pointerdown', (event) => {
            const tableEl = event.target.closest('[data-planner-table-id]');
            if (!tableEl) return;

            const table = this.find(Number(tableEl.dataset.plannerTableId));
            if (!table) return;

            if (event.target.closest('[data-merge-select]')) {
                event.preventDefault();
                event.stopPropagation();
                this.toggleMergeSelection(table.id);
                return;
            }

            this.select(table.id);
            if (!this.canEdit || !this.editMode) return;

            this.startDrag(event, table, tableEl);
        });

        window.addEventListener('pointermove', (event) => this.moveDrag(event));
        window.addEventListener('pointerup', () => this.endDrag());
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeAddModal();
                this.closePanel();
            }
        });
    }

    get snapEnabled() {
        return Boolean(this.snapInput?.checked);
    }

    find(id) {
        return this.tables.find((table) => table.id === id);
    }

    selected() {
        return this.find(this.selectedId);
    }

    selectedGroup() {
        return this.mergeGroups.find((group) => group.id === this.selectedGroupId) || null;
    }

    groupForTable(id) {
        return this.mergeGroups.find((group) => group.tableIds.includes(Number(id))) || null;
    }

    select(id) {
        const table = this.find(id);
        if (!table) return;

        this.selectedId = id;
        this.selectedGroupId = this.groupForTable(id)?.id || null;
        this.selectedZoneKey = tableZoneObject(table)?.key || this.selectedZoneKey;
        this.render();
    }

    selectGroup(id) {
        const group = this.mergeGroups.find((item) => item.id === id);
        if (!group) return;

        this.selectedGroupId = id;
        this.selectedId = null;
        const firstTable = group.tableIds.map((tableId) => this.find(tableId)).find(Boolean);
        if (firstTable) {
            this.selectedZoneKey = tableZoneObject(firstTable)?.key || this.selectedZoneKey;
        }
        this.render();
    }

    selectZone(key) {
        if (!zoneByKey(key)) return;

        this.selectedZoneKey = key;
        this.selectedId = null;
        this.selectedGroupId = null;
        this.render();
    }

    closePanel() {
        this.selectedId = null;
        this.selectedGroupId = null;
        this.render();
    }

    setTables(rows) {
        this.tables = rows.map((table) => ({
            id: Number(table.id),
            label: String(table.label || `T${table.id}`),
            capacity: Number(table.capacity || 1),
            status: normalizeStatus(table.status),
            shape: table.shape || 'square',
            x: Number(table.x || 0),
            y: Number(table.y || 0),
            width: Number(table.width || 120),
            height: Number(table.height || 90),
            rotation: Number(table.rotation || 0),
            seat_count: Number(table.seat_count || 0),
            booking: table.booking || null,
        }));

        this.resolveExistingOverlaps();
        this.pruneMergeGroups();

        if (this.selectedId && !this.find(this.selectedId)) {
            this.selectedId = null;
        }
        if (this.selectedGroupId && !this.selectedGroup()) {
            this.selectedGroupId = null;
        }
        this.render();
    }

    setMergeGroupsFromResponse(response) {
        const groups = response?.data?.planner?.mergeGroups;
        if (!Array.isArray(groups)) return;

        this.mergeGroups = normalizeMergeGroups(groups);
        saveMergeGroups(this.mergeGroups);
    }

    render() {
        this.root.classList.toggle('is-editing', this.editMode && this.canEdit);
        this.root.classList.toggle('has-panel', Boolean(this.selected() || this.selectedGroup()));
        this.renderTablesOnly();
        this.renderPanel();
        this.updateControls();
        this.updateZoneState();
        this.applyZoom();
    }

    renderTablesOnly() {
        this.canvas.querySelectorAll('[data-planner-table-id]').forEach((node) => node.remove());
        this.canvas.querySelectorAll('[data-merge-group-id]').forEach((node) => node.remove());
        this.empty.hidden = this.tables.length > 0;
        this.renderMergeGroups();
        this.tables.forEach((table) => this.canvas.appendChild(this.renderTable(table)));
        this.updateZoneState();
    }

    renderTable(table) {
        const zone = tableZoneObject(table);
        const isOutOfFocus = this.focusZoneKey && zone?.key !== this.focusZoneKey;
        const el = document.createElement('button');
        el.type = 'button';
        el.className = [
            'fp-table',
            `fp-table--${normalizeStatus(table.status)}`,
            `fp-table--${table.shape}`,
            table.id === this.selectedId ? 'is-selected' : '',
            this.selectedMergeIds.has(Number(table.id)) ? 'is-merge-selected' : '',
            isOutOfFocus ? 'is-out-of-focus' : '',
        ].filter(Boolean).join(' ');
        el.dataset.plannerTableId = String(table.id);
        el.dataset.zoneKey = zone?.key || DEFAULT_ZONE_KEY;
        el.style.left = `${table.x}px`;
        el.style.top = `${table.y}px`;
        el.style.width = `${Math.max(MIN_SIZE, table.width)}px`;
        el.style.height = `${Math.max(MIN_SIZE, table.height)}px`;
        el.style.transform = `rotate(${table.rotation || 0}deg)`;
        el.setAttribute('aria-label', `${table.label}, ${statusLabel(table.status)}, ${table.capacity} seats`);

        const piece = document.createElement('span');
        piece.className = 'fp-table-piece';
        piece.innerHTML = `
            ${this.chairMarkup(table)}
            <span class="fp-table-label">
                <strong>${this.escape(table.label)}</strong>
                <em>${tableStatusText(table.status)}</em>
            </span>
        `;
        el.appendChild(piece);

        if (this.canEdit) {
            const mergeToggle = document.createElement('span');
            mergeToggle.className = [
                'fp-merge-toggle',
                this.selectedMergeIds.has(Number(table.id)) ? 'is-active' : '',
            ].filter(Boolean).join(' ');
            mergeToggle.dataset.mergeSelect = String(table.id);
            mergeToggle.setAttribute('aria-hidden', 'true');
            mergeToggle.innerHTML = this.selectedMergeIds.has(Number(table.id))
                ? '<i class="fa-solid fa-check" aria-hidden="true"></i>'
                : '<i class="fa-solid fa-plus" aria-hidden="true"></i>';
            el.appendChild(mergeToggle);
        }

        return el;
    }

    chairMarkup(table) {
        if (table.shape === 'counter') return '';

        const count = Math.min(Number(table.capacity || 1) >= 6 ? 6 : Number(table.capacity || 1), 6);
        return Array.from({ length: count })
            .map((_, index) => `<i class="fp-chair fp-chair--${index + 1}" aria-hidden="true"></i>`)
            .join('');
    }

    renderMergeGroups() {
        this.mergeGroups.forEach((group) => {
            const tables = group.tableIds.map((id) => this.find(id)).filter(Boolean);
            if (tables.length < 2) return;
            if (this.focusZoneKey && !tables.some((table) => tableZoneObject(table)?.key === this.focusZoneKey)) {
                return;
            }

            const left = Math.min(...tables.map((table) => rectFor(table).left)) - 10;
            const top = Math.min(...tables.map((table) => rectFor(table).top)) - 10;
            const right = Math.max(...tables.map((table) => rectFor(table).right)) + 10;
            const bottom = Math.max(...tables.map((table) => rectFor(table).bottom)) + 10;
            const seats = tables.reduce((sum, table) => sum + Number(table.capacity || 0), 0);

            const groupEl = document.createElement('button');
            groupEl.type = 'button';
            groupEl.className = [
                'fp-merge-group',
                group.id === this.selectedGroupId ? 'is-selected' : '',
            ].filter(Boolean).join(' ');
            groupEl.dataset.mergeGroupId = group.id;
            groupEl.style.left = `${Math.max(0, left)}px`;
            groupEl.style.top = `${Math.max(0, top)}px`;
            groupEl.style.width = `${Math.max(80, right - left)}px`;
            groupEl.style.height = `${Math.max(80, bottom - top)}px`;
            groupEl.setAttribute('aria-label', `Merged group, ${tables.length} tables, ${seats} seats`);
            groupEl.innerHTML = `
                <span class="fp-merge-label">
                    <i class="fa-solid fa-object-group" aria-hidden="true"></i>
                    Merged group - ${seats} seats
                </span>
            `;
            groupEl.addEventListener('click', (event) => {
                event.stopPropagation();
                this.selectGroup(group.id);
            });
            this.canvas.appendChild(groupEl);
        });
    }

    renderPanel() {
        const table = this.selected();
        const group = this.selectedGroup();
        if (!table && group) {
            this.renderGroupPanel(group);
            return;
        }
        if (!table) {
            this.panel.hidden = true;
            this.panel.innerHTML = '';
            return;
        }

        this.panel.hidden = false;

        const statusClass = statusClasses(table.status);
        const bookingText = table.booking
            ? `${this.escape(table.booking.guest || 'Guest')} - ${this.escape(table.booking.ref || `#${table.booking.id}`)} - ${Number(table.booking.party || 1)} guests`
            : 'No assigned booking';
        const guestText = table.booking?.guest || (normalizeStatus(table.status) === 'occupied' ? 'Walk-in / current party' : 'No current guest');
        const editReadonly = this.canEdit && this.editMode ? '' : 'disabled';
        const disabledClass = this.canEdit && this.editMode ? '' : 'opacity-60 cursor-not-allowed';
        const zoneName = tableZone(table);

        this.panel.innerHTML = `
            <div class="grid gap-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">${this.escape(table.label)}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">${table.capacity} ${Number(table.capacity) === 1 ? 'seat' : 'seats'} - ${this.escape(zoneName)}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex shrink-0 items-center rounded-full border px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${statusClass}">
                            ${statusLabel(table.status)}
                        </span>
                        <button type="button" class="fp-modal-close" data-panel-action="close-panel" aria-label="Close table details">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="grid gap-2 xl:grid-cols-[1fr_1fr_1fr_auto] xl:items-end">
                    <div class="fp-detail-card">
                        <span class="fp-field-label">Assigned booking</span>
                        <p class="text-sm font-semibold text-slate-800">${bookingText}</p>
                    </div>
                    <div class="fp-detail-card">
                        <span class="fp-field-label">Cafe area</span>
                        <p class="text-sm font-semibold text-slate-800">${this.escape(zoneName)}</p>
                    </div>
                    <div class="fp-detail-card">
                        <span class="fp-field-label">Current guest</span>
                        <p class="text-sm font-semibold text-slate-800">${this.escape(guestText)}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 lg:w-[360px]">
                        ${table.booking ? `<a href="${this.bookingsUrl}?search=${encodeURIComponent(table.booking.ref || '')}" class="fp-status-action inline-flex items-center justify-center text-center">View Booking</a>` : ''}
                        ${this.statusButton('available', 'Mark Free', table)}
                        ${this.statusButton('occupied', 'Mark Occupied', table)}
                        ${this.statusButton('cleaning', 'Mark Cleaning', table)}
                        <button type="button" class="fp-status-action ${disabledClass}" data-panel-action="focus-name" ${editReadonly}>
                            Edit Table
                        </button>
                    </div>
                </div>

                ${this.canEdit && this.editMode ? `
                    <div class="grid gap-3 border-t border-slate-200 pt-3 xl:grid-cols-[1fr_110px_150px_110px_110px_220px] xl:items-end">
                        <div>
                            <label class="fp-field-label" for="fp-table-name-${table.id}">Table name</label>
                            <input id="fp-table-name-${table.id}" class="fp-input" type="text" value="${this.escapeAttr(table.label)}" data-panel-field="label">
                        </div>

                        <div>
                            <label class="fp-field-label" for="fp-table-capacity-${table.id}">Capacity</label>
                            <input id="fp-table-capacity-${table.id}" class="fp-input" type="number" min="1" max="99" value="${table.capacity}" data-panel-field="capacity">
                        </div>

                        <div>
                            <label class="fp-field-label" for="fp-table-shape-${table.id}">Shape</label>
                            <select id="fp-table-shape-${table.id}" class="fp-input" data-panel-field="shape">
                                ${this.shapeOption(table.shape, 'square', 'Square')}
                                ${this.shapeOption(table.shape, 'rectangle', 'Rectangle')}
                                ${this.shapeOption(table.shape, 'round', 'Round')}
                                ${this.shapeOption(table.shape, 'booth', 'Booth Table')}
                                ${this.shapeOption(table.shape, 'counter', 'Counter / Bar')}
                            </select>
                        </div>

                        <div>
                            <label class="fp-field-label" for="fp-table-width-${table.id}">Width</label>
                            <input id="fp-table-width-${table.id}" class="fp-input" type="number" min="${MIN_SIZE}" max="600" value="${Math.round(table.width)}" data-panel-field="width">
                        </div>

                        <div>
                            <label class="fp-field-label" for="fp-table-height-${table.id}">Height</label>
                            <input id="fp-table-height-${table.id}" class="fp-input" type="number" min="${MIN_SIZE}" max="600" value="${Math.round(table.height)}" data-panel-field="height">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="tc-admin-btn-secondary min-h-10" data-panel-action="rotate-right">
                                <i class="fa-solid fa-rotate-right text-xs" aria-hidden="true"></i>
                                Rotate
                            </button>
                            <button type="button" class="min-h-10 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100" data-panel-action="delete">
                                <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                ` : ''}

                ${!this.canEdit ? '<p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">Staff can update table status here. Layout editing is admin-only.</p>' : ''}
            </div>
        `;

        this.panel.querySelectorAll('[data-panel-field]').forEach((field) => {
            field.addEventListener('change', () => this.updateSelectedField(field));
            field.addEventListener('input', () => {
                if (field.dataset.panelField !== 'shape') this.updateSelectedField(field, false);
            });
        });

        this.panel.querySelectorAll('[data-panel-action]').forEach((button) => {
            button.addEventListener('click', () => this.handlePanelAction(button.dataset.panelAction));
        });

        this.panel.querySelectorAll('[data-panel-status]').forEach((button) => {
            button.addEventListener('click', () => this.updateStatus(button.dataset.panelStatus));
        });
    }

    renderGroupPanel(group) {
        const tables = group.tableIds.map((id) => this.find(id)).filter(Boolean);
        if (tables.length < 2) {
            this.panel.hidden = true;
            this.panel.innerHTML = '';
            return;
        }

        const seats = tables.reduce((sum, table) => sum + Number(table.capacity || 0), 0);
        const labels = tables.map((table) => this.escape(table.label)).join(', ');
        const zones = [...new Set(tables.map((table) => tableZone(table)))].join(', ');

        this.panel.hidden = false;
        this.panel.innerHTML = `
            <div class="grid gap-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">Merged table group</h3>
                        <p class="mt-0.5 text-xs text-slate-500">${tables.length} tables - ${seats} total seats</p>
                    </div>
                    <button type="button" class="fp-modal-close" data-panel-action="close-panel" aria-label="Close group details">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="fp-detail-card">
                        <span class="fp-field-label">Tables included</span>
                        <p class="text-sm font-semibold text-slate-800">${labels}</p>
                    </div>
                    <div class="fp-detail-card">
                        <span class="fp-field-label">Cafe area</span>
                        <p class="text-sm font-semibold text-slate-800">${this.escape(zones)}</p>
                    </div>
                </div>

                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                    Visual merge keeps each original table record separate for status, booking, and reporting.
                </p>

                ${this.canEdit && this.editMode ? `
                    <button type="button" class="tc-admin-btn-secondary min-h-10 px-4 py-2 text-sm" data-panel-action="unmerge-group">
                        <i class="fa-solid fa-object-ungroup text-xs" aria-hidden="true"></i>
                        Unmerge
                    </button>
                ` : ''}
            </div>
        `;

        this.panel.querySelectorAll('[data-panel-action]').forEach((button) => {
            button.addEventListener('click', () => this.handlePanelAction(button.dataset.panelAction));
        });
    }

    updateControls() {
        const focusButton = this.root.querySelector('[data-floor-planner-action="focus-area"]');
        const fullFloorButton = this.root.querySelector('[data-floor-planner-action="full-floor"]');
        const toggleButton = this.root.querySelector('[data-floor-planner-action="toggle-edit"]');
        const addButton = this.root.querySelector('[data-floor-planner-action="add-selected"]');
        const mergeButton = this.root.querySelector('[data-floor-planner-action="merge-selected"]');
        const unmergeButton = this.root.querySelector('[data-floor-planner-action="unmerge-selected"]');
        const saveButton = this.root.querySelector('[data-floor-planner-action="save"]');
        const globalToggleButtons = document.querySelectorAll('[data-floor-planner-global-action="toggle-edit"]');
        const hasSelectedZone = Boolean(this.selectedZoneKey);
        const canAddToSelectedZone = hasSelectedZone && this.selectedZoneKey !== 'kitchen';

        if (this.editToolbar) {
            this.editToolbar.classList.toggle('hidden', !this.editMode);
            this.editToolbar.classList.toggle('flex', this.editMode);
        }

        if (focusButton) {
            focusButton.disabled = !hasSelectedZone;
            focusButton.classList.toggle('opacity-50', !hasSelectedZone);
        }

        if (fullFloorButton) {
            fullFloorButton.classList.toggle('hidden', !this.focusZoneKey);
            fullFloorButton.classList.toggle('inline-flex', Boolean(this.focusZoneKey));
        }

        if (toggleButton) {
            toggleButton.setAttribute('aria-pressed', this.editMode ? 'true' : 'false');
            toggleButton.classList.toggle('tc-admin-btn-primary', this.editMode);
            toggleButton.classList.toggle('tc-admin-btn-secondary', !this.editMode);
            toggleButton.innerHTML = this.editMode
                ? '<i class="fa-solid fa-eye text-[11px]" aria-hidden="true"></i> View Mode'
                : '<i class="fa-solid fa-pen-ruler text-[11px]" aria-hidden="true"></i> Edit Layout Mode';
        }

        globalToggleButtons.forEach((button) => {
            button.setAttribute('aria-pressed', this.editMode ? 'true' : 'false');
            button.innerHTML = this.editMode
                ? '<i class="fa-solid fa-check text-xs" aria-hidden="true"></i> Done Editing'
                : '<i class="fa-solid fa-pen-ruler text-xs" aria-hidden="true"></i> Edit Layout';
        });

        if (addButton) {
            addButton.disabled = !this.editMode || !canAddToSelectedZone;
            addButton.classList.toggle('hidden', !this.editMode);
            addButton.classList.toggle('inline-flex', this.editMode);
        }

        if (mergeButton) {
            const canMerge = this.editMode && this.selectedMergeIds.size >= 2;
            mergeButton.disabled = !canMerge;
            mergeButton.classList.toggle('hidden', !this.editMode);
            mergeButton.classList.toggle('inline-flex', this.editMode);
            mergeButton.classList.toggle('opacity-60', !canMerge);
        }

        if (unmergeButton) {
            const canUnmerge = this.editMode && Boolean(this.selectedGroupId || this.groupForTable(this.selectedId));
            unmergeButton.disabled = !canUnmerge;
            unmergeButton.classList.toggle('hidden', !this.editMode);
            unmergeButton.classList.toggle('inline-flex', this.editMode);
            unmergeButton.classList.toggle('opacity-60', !canUnmerge);
        }

        if (saveButton) {
            saveButton.disabled = !this.editMode || !this.dirty;
            saveButton.classList.toggle('hidden', !this.editMode);
            saveButton.classList.toggle('inline-flex', this.editMode);
            saveButton.classList.toggle('opacity-60', !this.dirty);
        }

    }

    updateZoneState() {
        const counts = Object.fromEntries(ZONES.map((zone) => [zone.key, 0]));
        this.tables.forEach((table) => {
            const zone = tableZoneObject(table);
            counts[zone?.key || DEFAULT_ZONE_KEY] = (counts[zone?.key || DEFAULT_ZONE_KEY] || 0) + 1;
        });

        this.root.querySelectorAll('[data-zone-key]').forEach((zoneEl) => {
            const key = zoneEl.dataset.zoneKey;
            const count = counts[key] || 0;

            zoneEl.classList.toggle('is-selected', key === this.selectedZoneKey);
            zoneEl.classList.toggle('is-out-of-focus', Boolean(this.focusZoneKey && key !== this.focusZoneKey));
            zoneEl.classList.toggle('is-drag-boundary', Boolean(this.drag?.zone?.key === key));
        });

        this.root.querySelectorAll('[data-floor-element-zone]').forEach((element) => {
            element.classList.toggle('is-out-of-focus', Boolean(this.focusZoneKey && element.dataset.floorElementZone !== this.focusZoneKey));
        });

        this.root.querySelectorAll('[data-zone-add]').forEach((button) => {
            button.disabled = !this.canEdit || !this.editMode;
        });
    }

    shapeOption(current, value, label) {
        return `<option value="${value}" ${current === value ? 'selected' : ''}>${label}</option>`;
    }

    statusButton(status, label, table) {
        const disabled = normalizeStatus(table.status) === status ? 'disabled' : '';
        return `
            <button type="button" class="${statusActionClasses(status)}" data-panel-status="${status}" ${disabled}>
                ${label}
            </button>
        `;
    }

    updateSelectedField(field, shouldRender = true) {
        if (!this.canEdit || !this.editMode) return;

        const table = this.selected();
        if (!table) return;

        const key = field.dataset.panelField;
        if (key === 'label') {
            table.label = field.value.trim() || table.label;
        } else if (key === 'capacity') {
            table.capacity = clamp(Number(field.value || table.capacity), 1, 99);
            field.value = table.capacity;
            const dimensions = tableDimensions(table.shape, table.capacity);
            table.width = dimensions.width;
            table.height = dimensions.height;
            this.keepTableInsideZone(table);
        } else if (key === 'shape') {
            table.shape = field.value;
            const dimensions = tableDimensions(table.shape, table.capacity);
            table.width = dimensions.width;
            table.height = dimensions.height;
            this.keepTableInsideZone(table);
        } else if (key === 'width') {
            table.width = clamp(Number(field.value || table.width), MIN_SIZE, 600);
            field.value = Math.round(table.width);
            this.keepTableInsideZone(table);
        } else if (key === 'height') {
            table.height = clamp(Number(field.value || table.height), MIN_SIZE, 600);
            field.value = Math.round(table.height);
            this.keepTableInsideZone(table);
        }

        this.dirty = true;
        if (shouldRender) this.render();
        else this.renderTablesOnly();
    }

    handlePanelAction(action) {
        const table = this.selected();
        if (!table && !['close-panel', 'unmerge-group'].includes(action)) return;

        if (action === 'close-panel') {
            this.closePanel();
            return;
        }

        if (action === 'unmerge-group') {
            this.unmergeSelected();
            return;
        }

        if (action === 'focus-name') {
            if (!this.editMode) {
                notify('info', 'Turn on Edit Layout Mode to edit table details.');
                return;
            }
            this.panel.querySelector('[data-panel-field="label"]')?.focus();
            return;
        }

        if (!this.canEdit || !this.editMode) return;

        if (action === 'rotate-right') {
            table.rotation = (table.rotation + 15) % 360;
            this.dirty = true;
            this.render();
            return;
        }

        if (action === 'delete') {
            this.deleteSelected();
        }
    }

    handleToolbar(action) {
        if (action === 'reset-view') {
            this.zoom = this.focusZoneKey ? FOCUS_ZOOM : 1;
            this.applyZoom();
            this.scrollEl.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
            if (this.focusZoneKey) this.scrollToZone(zoneByKey(this.focusZoneKey));
            return;
        }

        if (action === 'focus-area') {
            this.focusArea();
            return;
        }

        if (action === 'full-floor') {
            this.backToFullFloor();
            return;
        }

        if (action === 'toggle-edit') {
            if (!this.canEdit) return;
            this.editMode = !this.editMode;
            if (!this.editMode) this.closeAddModal();
            this.render();
            return;
        }

        if (action === 'add-selected') {
            if (!this.selectedZoneKey) {
                notify('info', 'Select an area first.');
                return;
            }
            this.openAddModal(this.selectedZoneKey);
            return;
        }

        if (action === 'merge-selected') {
            this.mergeSelected();
            return;
        }

        if (action === 'unmerge-selected') {
            this.unmergeSelected();
            return;
        }

        if (action === 'save') {
            this.save(this.tables);
        }
    }

    toggleMergeSelection(id) {
        if (!this.canEdit || !this.editMode) return;

        const numericId = Number(id);
        if (this.selectedMergeIds.has(numericId)) {
            this.selectedMergeIds.delete(numericId);
        } else {
            this.selectedMergeIds.add(numericId);
        }

        this.selectedId = numericId;
        this.selectedGroupId = this.groupForTable(numericId)?.id || null;
        this.render();
    }

    async mergeSelected() {
        if (!this.canEdit || !this.editMode) return;

        const tableIds = [...this.selectedMergeIds]
            .map((id) => Number(id))
            .filter((id) => Boolean(this.find(id)));

        if (tableIds.length < 2) {
            notify('info', 'Select at least two tables to merge.');
            return;
        }

        const zones = new Set(tableIds.map((id) => tableZoneObject(this.find(id))?.key || DEFAULT_ZONE_KEY));
        if (zones.size > 2) {
            notify('info', 'Tables are far apart. Move them closer before merging.');
            return;
        }

        const nextGroup = {
            id: `merge-${Date.now()}`,
            tableIds,
        };

        this.mergeGroups = this.mergeGroups
            .map((group) => ({
                ...group,
                tableIds: group.tableIds.filter((id) => !tableIds.includes(Number(id))),
            }))
            .filter((group) => group.tableIds.length > 1);

        this.mergeGroups.push(nextGroup);
        this.selectedMergeIds.clear();
        this.selectedGroupId = nextGroup.id;
        this.selectedId = null;
        if (!await this.persistMergeGroups()) return;
        this.render();
        notify('success', 'Tables visually merged on the floor plan.');
    }

    async unmergeSelected() {
        if (!this.canEdit || !this.editMode) return;

        const group = this.selectedGroup()
            || this.groupForTable(this.selectedId)
            || [...this.selectedMergeIds].map((id) => this.groupForTable(id)).find(Boolean);

        if (!group) {
            notify('info', 'Select a merged group first.');
            return;
        }

        this.mergeGroups = this.mergeGroups.filter((item) => item.id !== group.id);
        this.selectedGroupId = null;
        this.selectedMergeIds.clear();
        if (!await this.persistMergeGroups()) return;
        this.render();
        notify('success', 'Merged group split back into individual tables.');
    }

    pruneMergeGroups() {
        const existingIds = new Set(this.tables.map((table) => Number(table.id)));
        this.mergeGroups = this.mergeGroups
            .map((group) => ({
                ...group,
                tableIds: group.tableIds.map((id) => Number(id)).filter((id) => existingIds.has(id)),
            }))
            .filter((group) => group.tableIds.length > 1);

        this.selectedMergeIds = new Set([...this.selectedMergeIds].filter((id) => existingIds.has(Number(id))));
        saveMergeGroups(this.mergeGroups);
    }

    async persistMergeGroups() {
        saveMergeGroups(this.mergeGroups);
        if (!this.root.dataset.apiMergeGroups) return true;

        try {
            const response = await axios.post(this.root.dataset.apiMergeGroups, {
                groups: this.mergeGroups.map((group) => ({
                    id: group.id,
                    table_ids: group.tableIds,
                })),
            });
            this.setMergeGroupsFromResponse(response);
            this.renderTablesOnly();
            this.updateControls();
            return true;
        } catch (error) {
            notify('error', firstError(error, 'Could not save merged table group'));
            return false;
        }
    }

    focusArea() {
        if (!this.selectedZoneKey) {
            notify('info', 'Select an area first.');
            return;
        }

        this.focusZoneKey = this.selectedZoneKey;
        this.zoom = FOCUS_ZOOM;
        this.render();
        this.scrollToZone(zoneByKey(this.focusZoneKey));
    }

    backToFullFloor() {
        this.focusZoneKey = null;
        this.zoom = 1;
        this.render();
        this.scrollEl.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
    }

    scrollToZone(zone) {
        if (!zone) return;

        this.scrollEl.scrollTo({
            left: Math.max(0, (zone.x * this.zoom) - 80),
            top: Math.max(0, (zone.y * this.zoom) - 80),
            behavior: 'smooth',
        });
    }

    applyZoom() {
        this.scaleEl.style.transform = `scale(${this.zoom})`;
        this.scaleEl.style.width = `${this.canvasWidth * this.zoom}px`;
        this.scaleEl.style.height = `${this.canvasHeight * this.zoom}px`;
    }

    openAddModal(zoneKey) {
        if (!this.canEdit) return;

        if (!this.editMode) {
            notify('info', 'Turn on Edit Layout Mode before adding tables.');
            return;
        }

        const zone = zoneByKey(zoneKey);
        if (!zone) return;
        if (zone.key === 'kitchen') {
            notify('info', 'Choose a customer seating area first.');
            return;
        }

        this.selectZone(zone.key);
        this.pendingZoneKey = zone.key;

        const defaults = zoneDefault(zone.key);
        const labelField = this.addForm?.querySelector('[data-add-field="label"]');
        const capacityField = this.addForm?.querySelector('[data-add-field="capacity"]');
        const shapeField = this.addForm?.querySelector('[data-add-field="shape"]');
        const zoneField = this.addForm?.querySelector('[data-add-field="zone"]');
        const statusField = this.addForm?.querySelector('[data-add-field="status"]');
        const zoneLabel = this.root.querySelector('[data-add-table-zone-label]');

        if (labelField) labelField.value = '';
        if (capacityField) capacityField.value = defaults.capacity;
        if (shapeField) shapeField.value = defaults.shape;
        if (zoneField) zoneField.value = zone.key;
        if (statusField) statusField.value = DEFAULT_STATUS;
        if (zoneLabel) zoneLabel.textContent = `New table will be placed in ${zone.name}.`;

        this.modal.hidden = false;
        labelField?.focus();
    }

    closeAddModal() {
        if (this.modal) this.modal.hidden = true;
        this.pendingZoneKey = null;
    }

    async createTableFromModal() {
        if (!this.canEdit || !this.editMode) return;

        const requestedZone = this.addForm?.querySelector('[data-add-field="zone"]')?.value;
        const zone = zoneByKey(requestedZone || this.pendingZoneKey || this.selectedZoneKey);
        if (!zone) {
            notify('info', 'Select an area first.');
            return;
        }

        const label = this.addForm?.querySelector('[data-add-field="label"]')?.value.trim() || '';
        const capacity = clamp(Number(this.addForm?.querySelector('[data-add-field="capacity"]')?.value || 4), 1, 99);
        const shape = this.addForm?.querySelector('[data-add-field="shape"]')?.value || 'square';
        const status = normalizeStatus(this.addForm?.querySelector('[data-add-field="status"]')?.value || DEFAULT_STATUS);
        const dimensions = tableDimensions(shape, capacity);
        const position = this.findAvailablePosition(zone, dimensions.width, dimensions.height);

        if (!position) {
            notify('error', 'No available space in this area. Choose another area or move tables.');
            return;
        }

        const submitButton = this.addForm?.querySelector('[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        try {
            let response = await axios.post(this.root.dataset.apiStore, {
                label,
                capacity,
                planner_shape: shape,
                position_x: position.x,
                position_y: position.y,
                layout_width: dimensions.width,
                layout_height: dimensions.height,
                layout_rotation: 0,
            });

            const createdId = Number(response.data.table_id);
            if (status !== DEFAULT_STATUS && createdId) {
                response = await axios.post(this.root.dataset.apiStatus, {
                    table_id: createdId,
                    status,
                });
            }

            this.setTables(apiPlannerTables(response));
            this.selectedId = createdId;
            this.selectedZoneKey = zone.key;
            this.focusZoneKey = this.focusZoneKey || zone.key;
            this.closeAddModal();
            this.render();
            this.scrollToZone(zone);
            notify('success', `Table added to ${zone.name}`);
        } catch (error) {
            notify('error', firstError(error, 'Could not add table'));
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    }

    clampInsideZone(table, rawX, rawY, zone = tableZoneObject(table), topPadding = ZONE_PADDING) {
        const width = Math.max(MIN_SIZE, Number(table.width || MIN_SIZE));
        const height = Math.max(MIN_SIZE, Number(table.height || MIN_SIZE));
        const minX = zone.x + ZONE_PADDING;
        const maxX = zone.x + zone.width - width - ZONE_PADDING;
        const minY = zone.y + topPadding;
        const maxY = zone.y + zone.height - height - ZONE_PADDING;
        const x = maxX < minX
            ? zone.x + Math.max(0, (zone.width - width) / 2)
            : clamp(rawX, minX, maxX);
        const y = maxY < minY
            ? zone.y + Math.max(0, (zone.height - height) / 2)
            : clamp(rawY, minY, maxY);

        return {
            x: snap(x, this.snapEnabled),
            y: snap(y, this.snapEnabled),
        };
    }

    keepTableInsideZone(table) {
        const zone = tableZoneObject(table);
        const position = this.clampInsideZone(table, table.x, table.y, zone);
        table.x = position.x;
        table.y = position.y;
    }

    overlapsAny(candidate, ignoreId = null) {
        const candidateRect = rectFor(candidate);

        return this.tables.some((table) => {
            if (ignoreId !== null && Number(table.id) === Number(ignoreId)) return false;

            return rectsOverlap(candidateRect, rectFor(table));
        });
    }

    findAvailablePosition(zone, width, height, ignoreId = null) {
        const minX = zone.x + ZONE_PADDING;
        const maxX = zone.x + zone.width - width - ZONE_PADDING;
        const minY = zone.y + ZONE_HEADER_SPACE;
        const maxY = zone.y + zone.height - height - ZONE_PADDING;

        if (maxX < minX || maxY < minY) return null;

        for (let y = minY; y <= maxY; y += GRID_SIZE) {
            for (let x = minX; x <= maxX; x += GRID_SIZE) {
                const candidate = {
                    id: ignoreId || 'new',
                    x: snap(x, this.snapEnabled),
                    y: snap(y, this.snapEnabled),
                    width,
                    height,
                };
                const clamped = this.clampInsideZone(candidate, candidate.x, candidate.y, zone, ZONE_HEADER_SPACE);
                candidate.x = clamped.x;
                candidate.y = clamped.y;

                if (!this.overlapsAny(candidate, ignoreId)) {
                    return { x: candidate.x, y: candidate.y };
                }
            }
        }

        return null;
    }

    resolveExistingOverlaps() {
        let changed = false;

        this.tables.forEach((table) => {
            const zone = tableZoneObject(table);
            const clamped = this.clampInsideZone(table, table.x, table.y, zone);

            if (Math.round(table.x) !== Math.round(clamped.x) || Math.round(table.y) !== Math.round(clamped.y)) {
                table.x = clamped.x;
                table.y = clamped.y;
                changed = true;
            }

            if (this.overlapsAny(table, table.id)) {
                const position = this.findAvailablePosition(zone, table.width, table.height, table.id);
                if (position) {
                    table.x = position.x;
                    table.y = position.y;
                    changed = true;
                }
            }
        });

        if (changed && this.canEdit) {
            this.dirty = true;
        }
    }

    startDrag(event, table, tableEl) {
        event.preventDefault();
        tableEl.setPointerCapture?.(event.pointerId);

        const zone = tableZoneObject(table);
        const original = { id: table.id, x: table.x, y: table.y };
        this.drag = {
            table,
            zone,
            startClientX: event.clientX,
            startClientY: event.clientY,
            startX: table.x,
            startY: table.y,
            moved: false,
            blocked: false,
            original,
        };
        this.selectedZoneKey = zone.key;
        this.updateZoneState();
    }

    moveDrag(event) {
        if (!this.drag) return;

        const dx = (event.clientX - this.drag.startClientX) / this.zoom;
        const dy = (event.clientY - this.drag.startClientY) / this.zoom;
        const table = this.drag.table;
        const position = this.clampInsideZone(
            table,
            this.drag.startX + dx,
            this.drag.startY + dy,
            this.drag.zone,
        );
        const candidate = { ...table, x: position.x, y: position.y };

        if (this.overlapsAny(candidate, table.id)) {
            this.drag.blocked = true;
            return;
        }

        table.x = position.x;
        table.y = position.y;
        this.drag.moved = true;
        this.dirty = true;
        this.renderTablesOnly();
    }

    endDrag() {
        if (!this.drag) return;

        if (this.drag.moved) {
            this.undoStack.push(this.drag.original);
            if (this.undoStack.length > 20) this.undoStack.shift();
            this.renderPanel();
        } else if (this.drag.blocked) {
            notify('info', 'Move blocked to avoid overlapping another table.');
        }

        this.drag = null;
        this.updateZoneState();
        this.updateControls();
    }

    async save(tables) {
        if (!this.canEdit || !this.editMode) return;
        if (!tables.length) return;

        try {
            const response = await axios.post(this.root.dataset.apiSave, {
                tables: tables.map(payload),
            });
            this.setTables(apiPlannerTables(response));
            this.dirty = false;
            this.render();
            notify('success', 'Floor planner saved');
        } catch (error) {
            notify('error', firstError(error, 'Could not save floor planner'));
        }
    }

    async updateStatus(status) {
        const table = this.selected();
        if (!table || !status) return;

        try {
            const response = await axios.post(this.root.dataset.apiStatus, {
                table_id: table.id,
                status,
            });
            this.setTables(apiPlannerTables(response));
            this.selectedId = table.id;
            this.render();
            window.Livewire?.dispatch?.('tables-refresh');
            window.dispatchEvent(new CustomEvent('tables-refresh'));
            notify('success', `Table marked ${statusLabel(status).toLowerCase()}`);
        } catch (error) {
            notify('error', firstError(error, 'Could not update table status'));
        }
    }

    async deleteSelected() {
        if (!this.canEdit || !this.editMode) return;

        const table = this.selected();
        if (!table) return;

        if (table.booking) {
            notify('error', 'This table has an active booking and cannot be deleted.');
            return;
        }

        if (!window.confirm(`Delete ${table.label}? This removes the table marker from the floor planner.`)) {
            return;
        }

        try {
            const response = await axios.post(this.root.dataset.apiDelete, {
                table_id: table.id,
            });
            this.setTables(apiPlannerTables(response));
            notify('success', 'Table deleted');
        } catch (error) {
            notify('error', firstError(error, 'Could not delete table'));
        }
    }

    escape(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    escapeAttr(value) {
        return this.escape(value).replace(/`/g, '&#096;');
    }
}

function initFloorPlanners() {
    document.querySelectorAll('[data-floor-planner]').forEach((root) => {
        if (root.dataset.floorPlannerReady === '1') return;
        root.dataset.floorPlannerReady = '1';
        new FloorPlanner(root);
    });
}

if (!window.floorPlannerGlobalActionsReady) {
    window.floorPlannerGlobalActionsReady = true;
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-floor-planner-global-action]');
        if (!button) return;

        const planner = document.querySelector('[data-floor-planner]')?.floorPlannerInstance;
        if (!planner) return;

        planner.handleToolbar(button.dataset.floorPlannerGlobalAction);
    });
}

document.addEventListener('DOMContentLoaded', initFloorPlanners);
document.addEventListener('livewire:navigated', initFloorPlanners);
