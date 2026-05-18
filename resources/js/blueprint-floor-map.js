import axios from 'axios';

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const STATUS_LABELS = {
    available: 'Free',
    reserved: 'Reserved',
    occupied: 'Occupied',
    cleaning: 'Cleaning',
};

const STATUS_CLASSES = {
    available: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    reserved: 'border-amber-200 bg-amber-50 text-amber-800',
    occupied: 'border-orange-200 bg-orange-50 text-orange-800',
    cleaning: 'border-slate-200 bg-slate-100 text-slate-700',
};

const MERGE_DISTANCE_LIMIT = 18;
const BOUNDARY_ERROR = 'Table marker must stay inside the blueprint area.';
const DEFAULT_MARKER_WIDTH = 58;
const DEFAULT_MARKER_HEIGHT = 42;

function normalizeStatus(status) {
    return status === 'free' ? 'available' : (status || 'available');
}

function statusLabel(status) {
    return STATUS_LABELS[normalizeStatus(status)] || STATUS_LABELS.available;
}

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

function firstError(error, fallback) {
    const data = error?.response?.data;
    if (data?.errors) {
        const first = Object.values(data.errors).flat()[0];
        if (first) return first;
    }

    return data?.message || fallback;
}

function readJson(root, selector, fallback = []) {
    const node = root.querySelector(selector);
    if (!node) return fallback;

    try {
        return JSON.parse(node.textContent || '[]');
    } catch (error) {
        console.warn(`Could not parse ${selector}`, error);
        return fallback;
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function finiteNumber(value, fallback = 0) {
    const number = Number(value);

    return Number.isFinite(number) ? number : fallback;
}

function normalizeGroup(group) {
    const tableIds = Array.isArray(group.table_ids || group.tableIds)
        ? (group.table_ids || group.tableIds).map((id) => Number(id)).filter(Boolean)
        : [];

    return {
        id: String(group.id || `daily-${Date.now()}`),
        table_ids: [...new Set(tableIds)],
        label: String(group.label || ''),
        booking_id: group.booking_id ? Number(group.booking_id) : null,
        booking_ref: String(group.booking_ref || ''),
        guest_name: String(group.guest_name || ''),
        source: String(group.source || 'daily_operation'),
    };
}

class BlueprintFloorMap {
    constructor(root) {
        this.root = root;
        this.stage = root.querySelector('[data-blueprint-stage]');
        this.body = root.querySelector('[data-blueprint-body]');
        this.panel = root.querySelector('[data-blueprint-panel]');
        this.mergeLayer = root.querySelector('[data-blueprint-merge-layer]');
        this.mergeBar = root.querySelector('[data-blueprint-merge-bar]');
        this.placementBar = root.querySelector('[data-blueprint-placement-bar]');
        this.addModal = root.querySelector('[data-blueprint-add-modal]');
        this.addForm = root.querySelector('[data-blueprint-add-form]');
        this.mergeModal = root.querySelector('[data-blueprint-merge-modal]');
        this.mergeForm = root.querySelector('[data-blueprint-merge-form]');
        this.uploadForm = root.querySelector('[data-blueprint-upload-form]');
        this.uploadInput = root.querySelector('[data-blueprint-upload-input]');
        this.apiStatus = root.dataset.apiStatus;
        this.apiMergeGroups = root.dataset.apiMergeGroups;
        this.apiPlace = root.dataset.apiPlace;
        this.apiUpdate = root.dataset.apiUpdate;
        this.apiDelete = root.dataset.apiDelete;
        this.bookingsUrl = root.dataset.bookingsUrl || '#';
        this.editMode = root.dataset.editMode === 'true';
        this.tables = readJson(root, '[data-blueprint-tables-json]', []).map((table) => this.normalizeTable(table));
        this.groups = readJson(root, '[data-blueprint-groups-json]', []).map(normalizeGroup)
            .filter((group) => group.table_ids.length > 1);
        this.bookings = readJson(root, '[data-blueprint-bookings-json]', []);
        this.selectedTableId = null;
        this.selectedGroupId = null;
        this.selectionMode = false;
        this.selectedIds = new Set();
        this.pendingMarker = null;
        this.pendingMoves = new Map();
        this.suppressClickForTable = null;

        this.bind();
        this.render();
    }

    normalizeTable(table) {
        const x = finiteNumber(table.x, 50);
        const y = finiteNumber(table.y, 50);

        return {
            ...table,
            id: Number(table.id),
            seat_id: Number(table.seat_id || 0),
            capacity: Number(table.capacity || 1),
            status: normalizeStatus(table.status),
            x,
            y,
            savedX: x,
            savedY: y,
            furniture_type: table.furniture_type || 'standard',
            merge_group: String(table.merge_group || 'default'),
        };
    }

    bind() {
        this.root.addEventListener('click', (event) => {
            const actionButton = event.target.closest('[data-blueprint-action]');
            if (!actionButton || !this.root.contains(actionButton)) return;

            event.preventDefault();
            this.handleAction(actionButton.dataset.blueprintAction, actionButton);
        });

        this.stage?.addEventListener('click', (event) => {
            if (!this.pendingMarker) return;
            if (event.target.closest('[data-blueprint-marker]')) return;

            const point = this.pointFromEvent(event, null, this.pendingMarker);
            if (!point) {
                notify('error', BOUNDARY_ERROR);
                return;
            }
            if (point.clamped) {
                notify('error', BOUNDARY_ERROR);
            }
            this.placeMarker(point);
        });

        this.addForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            this.beginPlacementFromModal();
        });

        this.mergeForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            this.mergeSelected();
        });

        this.uploadInput?.addEventListener('change', () => {
            if (this.uploadInput.files?.length) {
                this.uploadForm?.submit();
            }
        });
    }

    bindMarker(marker) {
        if (marker.dataset.blueprintBound === 'true') return;

        marker.dataset.blueprintBound = 'true';
        marker.addEventListener('click', (event) => {
            event.stopPropagation();
            const tableId = Number(marker.dataset.tableId);
            if (this.suppressClickForTable === tableId) {
                this.suppressClickForTable = null;
                return;
            }
            this.handleMarkerClick(tableId);
        });

        marker.addEventListener('pointerdown', (event) => this.startMarkerDrag(event, marker));
    }

    handleAction(action) {
        if (action === 'upload-blueprint') {
            this.uploadInput?.click();
            return;
        }

        if (action === 'open-add-marker') {
            this.openAddModal();
            return;
        }

        if (action === 'close-add-marker') {
            this.closeAddModal();
            return;
        }

        if (action === 'cancel-placement') {
            this.cancelPlacement();
            return;
        }

        if (action === 'save-layout') {
            this.saveLayout();
            return;
        }

        if (action === 'start-merge') {
            this.startMergeSelection();
            return;
        }

        if (action === 'cancel-merge-select') {
            this.cancelMergeSelection();
            return;
        }

        if (action === 'open-merge') {
            this.openMergeModal();
            return;
        }

        if (action === 'close-merge') {
            this.closeMergeModal();
        }
    }

    handleMarkerClick(tableId) {
        if (this.pendingMarker) return;

        if (this.selectionMode) {
            this.togglePicked(tableId);
            return;
        }

        this.selectedTableId = tableId;
        this.selectedGroupId = this.groupForTable(tableId)?.id || null;
        this.render();
    }

    nextTableLabel() {
        const max = this.tables.reduce((highest, table) => {
            const match = String(table.label || '').match(/^T(\d+)$/i);
            return match ? Math.max(highest, Number(match[1])) : highest;
        }, 0);

        return `T${max + 1}`;
    }

    markerSize(marker = null, table = null) {
        const liveRect = marker?.getBoundingClientRect?.();
        if (liveRect?.width > 0 && liveRect?.height > 0) {
            return {
                width: liveRect.width,
                height: liveRect.height,
            };
        }

        if (!this.stage) {
            return {
                width: DEFAULT_MARKER_WIDTH,
                height: DEFAULT_MARKER_HEIGHT,
            };
        }

        const probe = document.createElement('button');
        const status = normalizeStatus(table?.status);
        probe.type = 'button';
        probe.className = `bfm-marker bfm-marker--${status === 'available' ? 'free' : status}`;
        probe.style.visibility = 'hidden';
        probe.style.pointerEvents = 'none';
        probe.style.left = '0';
        probe.style.top = '0';
        probe.innerHTML = `
            <span class="bfm-marker__name">${escapeHtml(table?.label || table?.preview_label || this.nextTableLabel())}</span>
            <span class="bfm-marker__meta">
                <span class="bfm-marker__status">${statusLabel(status)}</span>
                <span class="bfm-marker__capacity">${Number(table?.capacity || 1)}</span>
            </span>
        `;

        this.stage.appendChild(probe);
        const rect = probe.getBoundingClientRect();
        probe.remove();

        return {
            width: Math.max(DEFAULT_MARKER_WIDTH, rect.width || 0),
            height: Math.max(DEFAULT_MARKER_HEIGHT, rect.height || 0),
        };
    }

    markerBoundary(marker = null, table = null) {
        const rect = this.stage?.getBoundingClientRect?.();
        if (!rect || rect.width <= 0 || rect.height <= 0) return null;

        const size = this.markerSize(marker, table);
        const halfWidth = Math.min(size.width / 2, rect.width / 2);
        const halfHeight = Math.min(size.height / 2, rect.height / 2);
        const maxX = Math.max(halfWidth, rect.width - halfWidth);
        const maxY = Math.max(halfHeight, rect.height - halfHeight);

        return {
            left: rect.left,
            top: rect.top,
            containerWidth: rect.width,
            containerHeight: rect.height,
            markerWidth: size.width,
            markerHeight: size.height,
            minX: halfWidth,
            minY: halfHeight,
            maxX,
            maxY,
        };
    }

    boundaryPayload(bounds) {
        return {
            container_width: Number(bounds.containerWidth.toFixed(2)),
            container_height: Number(bounds.containerHeight.toFixed(2)),
            marker_width: Number(bounds.markerWidth.toFixed(2)),
            marker_height: Number(bounds.markerHeight.toFixed(2)),
        };
    }

    pointFromPixels(pixelX, pixelY, bounds) {
        const x = clamp(pixelX, bounds.minX, bounds.maxX);
        const y = clamp(pixelY, bounds.minY, bounds.maxY);

        return {
            x: clamp((x / bounds.containerWidth) * 100, 0, 100),
            y: clamp((y / bounds.containerHeight) * 100, 0, 100),
            clamped: Math.abs(x - pixelX) > 0.5 || Math.abs(y - pixelY) > 0.5,
            bounds: this.boundaryPayload(bounds),
        };
    }

    pointFromEvent(event, marker = null, table = null) {
        const bounds = this.markerBoundary(marker, table);
        if (!bounds) return null;

        return this.pointFromPixels(event.clientX - bounds.left, event.clientY - bounds.top, bounds);
    }

    pointFromTable(table, marker = null) {
        const bounds = this.markerBoundary(marker, table);
        if (!bounds) return null;

        return this.pointFromPixels(
            (finiteNumber(table.x, 50) / 100) * bounds.containerWidth,
            (finiteNumber(table.y, 50) / 100) * bounds.containerHeight,
            bounds,
        );
    }

    coordinatePayload(point) {
        return {
            pos_x: point.x,
            pos_y: point.y,
            ...point.bounds,
        };
    }

    restorePendingMoves() {
        for (const tableId of this.pendingMoves.keys()) {
            const table = this.table(tableId);
            if (!table) continue;

            table.x = finiteNumber(table.savedX, table.x);
            table.y = finiteNumber(table.savedY, table.y);
        }

        this.pendingMoves.clear();
        this.render();
    }

    startMarkerDrag(event, marker) {
        if (!this.editMode || this.selectionMode || this.pendingMarker) return;
        if (event.pointerType === 'mouse' && event.button !== 0) return;

        const tableId = Number(marker.dataset.tableId);
        const table = this.table(tableId);
        if (!table) return;

        event.preventDefault();
        marker.setPointerCapture?.(event.pointerId);

        const startX = event.clientX;
        const startY = event.clientY;
        let moved = false;
        let clampedToBoundary = false;

        const move = (moveEvent) => {
            const distance = Math.hypot(moveEvent.clientX - startX, moveEvent.clientY - startY);
            if (distance > 3) {
                moved = true;
                marker.classList.add('is-dragging');
            }
            if (!moved) return;

            const point = this.pointFromEvent(moveEvent, marker, table);
            if (!point) return;

            clampedToBoundary ||= point.clamped;
            table.x = point.x;
            table.y = point.y;
            marker.style.left = `${point.x}%`;
            marker.style.top = `${point.y}%`;
            this.pendingMoves.set(table.id, this.coordinatePayload(point));
            this.renderGroups();
        };

        const up = () => {
            marker.releasePointerCapture?.(event.pointerId);
            marker.classList.remove('is-dragging');
            marker.removeEventListener('pointermove', move);
            marker.removeEventListener('pointerup', up);
            marker.removeEventListener('pointercancel', up);
            if (moved) {
                this.suppressClickForTable = tableId;
                notify(
                    clampedToBoundary ? 'error' : 'success',
                    clampedToBoundary ? BOUNDARY_ERROR : 'Marker moved. Click Save Layout to keep the new position.',
                );
            }
        };

        marker.addEventListener('pointermove', move);
        marker.addEventListener('pointerup', up);
        marker.addEventListener('pointercancel', up);
    }

    table(id) {
        return this.tables.find((table) => Number(table.id) === Number(id)) || null;
    }

    selectedGroup() {
        return this.groups.find((group) => group.id === this.selectedGroupId) || null;
    }

    groupForTable(tableId) {
        return this.groups.find((group) => group.table_ids.includes(Number(tableId))) || null;
    }

    groupTables(group) {
        return (group?.table_ids || []).map((id) => this.table(id)).filter(Boolean);
    }

    tableDistance(a, b) {
        if (!a || !b) return Number.POSITIVE_INFINITY;

        return Math.hypot(Number(a.x) - Number(b.x), Number(a.y) - Number(b.y));
    }

    tablesCanMerge(a, b) {
        if (!a || !b || Number(a.id) === Number(b.id)) return false;

        return a.merge_group === b.merge_group && this.tableDistance(a, b) <= MERGE_DISTANCE_LIMIT;
    }

    mergeSetIsConnected(ids) {
        const uniqueIds = [...new Set(ids.map(Number).filter(Boolean))];
        if (uniqueIds.length < 2) return false;

        const tables = uniqueIds.map((id) => this.table(id)).filter(Boolean);
        if (tables.length !== uniqueIds.length) return false;

        const seen = new Set([tables[0].id]);
        const queue = [tables[0]];

        while (queue.length > 0) {
            const current = queue.shift();
            tables.forEach((candidate) => {
                if (seen.has(candidate.id)) return;
                if (!this.tablesCanMerge(current, candidate)) return;
                seen.add(candidate.id);
                queue.push(candidate);
            });
        }

        return seen.size === tables.length;
    }

    candidateCanJoinSelection(tableId) {
        if (this.selectedIds.has(tableId)) return true;
        if (this.selectedIds.size === 0) return true;

        return this.mergeSetIsConnected([...this.selectedIds, tableId]);
    }

    render() {
        this.renderMarkers();
        this.renderGroups();
        this.renderControls();
        this.renderPanel();
    }

    renderMarkers() {
        this.tables.forEach((table) => {
            const marker = this.ensureMarker(table);
            const status = normalizeStatus(table.status);
            const point = this.pointFromTable(table, marker);

            if (point?.clamped) {
                table.x = point.x;
                table.y = point.y;
                if (this.editMode && table.seat_id) {
                    this.pendingMoves.set(table.id, this.coordinatePayload(point));
                }
            }

            marker.classList.remove(
                'bfm-marker--free',
                'bfm-marker--reserved',
                'bfm-marker--occupied',
                'bfm-marker--cleaning',
                'is-selected',
                'is-picked',
                'is-pick-mode',
                'is-merged',
                'is-merge-compatible',
                'is-merge-blocked',
            );
            marker.classList.add(`bfm-marker--${status === 'available' ? 'free' : status}`);
            marker.classList.toggle('is-selected', Number(table.id) === Number(this.selectedTableId));
            marker.classList.toggle('is-picked', this.selectedIds.has(Number(table.id)));
            marker.classList.toggle('is-pick-mode', this.selectionMode);
            marker.classList.toggle('is-merged', Boolean(this.groupForTable(table.id)));
            const canCompareMergeDistance = this.selectionMode && this.selectedIds.size > 0 && !this.selectedIds.has(Number(table.id));
            marker.classList.toggle('is-merge-compatible', canCompareMergeDistance && this.candidateCanJoinSelection(Number(table.id)));
            marker.classList.toggle('is-merge-blocked', canCompareMergeDistance && !this.candidateCanJoinSelection(Number(table.id)));
            marker.style.left = `${table.x}%`;
            marker.style.top = `${table.y}%`;
            marker.setAttribute('aria-label', `${table.label}, ${statusLabel(status)}, ${table.capacity} seats`);
            marker.querySelector('.bfm-marker__name').textContent = table.label;
            marker.querySelector('.bfm-marker__status').textContent = statusLabel(status);
            marker.querySelector('.bfm-marker__capacity').textContent = String(table.capacity);
        });

        this.root.querySelector('.bfm-empty')?.classList.toggle('hidden', this.tables.length > 0);
    }

    ensureMarker(table) {
        let marker = this.root.querySelector(`[data-blueprint-marker][data-table-id="${table.id}"]`);
        if (!marker) {
            marker = document.createElement('button');
            marker.type = 'button';
            marker.className = 'bfm-marker';
            marker.dataset.blueprintMarker = '';
            marker.dataset.tableId = String(table.id);
            marker.innerHTML = `
                <span class="bfm-marker__name"></span>
                <span class="bfm-marker__meta">
                    <span class="bfm-marker__status"></span>
                    <span class="bfm-marker__capacity"></span>
                </span>
            `;
            this.stage?.appendChild(marker);
        }

        this.bindMarker(marker);

        return marker;
    }

    renderGroups() {
        if (!this.mergeLayer) return;

        this.mergeLayer.innerHTML = '';

        this.groups.forEach((group) => {
            const tables = this.groupTables(group);
            if (tables.length < 2) return;

            const xs = tables.map((table) => Number(table.x));
            const ys = tables.map((table) => Number(table.y));
            const minX = Math.max(0, Math.min(...xs) - 4);
            const maxX = Math.min(100, Math.max(...xs) + 4);
            const minY = Math.max(0, Math.min(...ys) - 5);
            const maxY = Math.min(100, Math.max(...ys) + 5);

            const button = document.createElement('button');
            button.type = 'button';
            button.className = `bfm-group ${group.id === this.selectedGroupId ? 'is-selected' : ''}`;
            button.style.left = `${(minX + maxX) / 2}%`;
            button.style.top = `${(minY + maxY) / 2}%`;
            button.style.width = `${Math.max(10, maxX - minX)}%`;
            button.style.height = `${Math.max(12, maxY - minY)}%`;
            button.dataset.blueprintGroup = group.id;
            button.innerHTML = `
                <span class="bfm-group__label">
                    <i class="fa-solid fa-object-group" aria-hidden="true"></i>
                    ${escapeHtml(this.groupLabel(group))}
                </span>
            `;
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                this.selectionMode = false;
                this.selectedIds.clear();
                this.selectedTableId = null;
                this.selectedGroupId = group.id;
                this.render();
            });
            this.mergeLayer.appendChild(button);
        });
    }

    renderControls() {
        const count = this.selectedIds.size;
        const merge = this.root.querySelector('[data-blueprint-action="open-merge"]');
        const summary = this.root.querySelector('[data-blueprint-selection-summary]');
        const nextLabel = this.root.querySelector('[data-blueprint-next-label]');

        this.mergeBar?.classList.toggle('hidden', !this.selectionMode);
        this.mergeBar?.classList.toggle('flex', this.selectionMode);
        this.placementBar?.classList.toggle('hidden', !this.pendingMarker);
        this.placementBar?.classList.toggle('flex', Boolean(this.pendingMarker));
        this.stage?.classList.toggle('is-placement-mode', Boolean(this.pendingMarker));

        const canMergeSelection = count >= 2 && this.mergeSetIsConnected([...this.selectedIds]);

        if (merge) {
            merge.disabled = !canMergeSelection;
        }

        if (summary) {
            if (!this.selectionMode) {
                summary.textContent = 'Select two or more nearby tables to merge.';
            } else if (count === 0) {
                summary.textContent = 'Select a table. Nearby compatible tables will be highlighted.';
            } else if (count === 1) {
                summary.textContent = 'Nearby compatible tables are highlighted. Distant tables are dimmed.';
            } else {
                summary.textContent = canMergeSelection
                    ? `${count} nearby tables selected - ready to merge`
                    : 'These tables are too far apart to merge.';
            }
        }

        if (nextLabel) {
            nextLabel.textContent = this.nextTableLabel();
        }
    }

    renderPanel() {
        const group = this.selectedGroup() || (this.selectedTableId ? this.groupForTable(this.selectedTableId) : null);
        if (group && !this.editMode) {
            this.selectedGroupId = group.id;
            this.renderGroupPanel(group);
            return;
        }

        const table = this.selectedTableId ? this.table(this.selectedTableId) : null;
        if (!table || !this.panel) {
            if (this.panel) {
                this.panel.hidden = true;
                this.panel.innerHTML = '';
            }
            this.body?.classList.remove('has-panel');
            return;
        }

        this.panel.hidden = false;
        this.body?.classList.add('has-panel');
        this.panel.innerHTML = this.editMode ? this.editPanelHtml(table) : this.tablePanelHtml(table);
        this.bindPanelActions([table.id]);
    }

    renderGroupPanel(group) {
        const tables = this.groupTables(group);
        if (!this.panel || tables.length < 2) return;

        const capacity = tables.reduce((sum, table) => sum + Number(table.capacity || 0), 0);
        const title = this.groupLabel(group);
        const bookingText = group.booking_ref
            ? `${escapeHtml(group.guest_name || 'Guest')} - ${escapeHtml(group.booking_ref)}`
            : 'Walk-in group';

        this.panel.hidden = false;
        this.body?.classList.add('has-panel');
        this.panel.innerHTML = `
            <div class="grid gap-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">${escapeHtml(title)}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Merged - ${capacity} seats</p>
                    </div>
                    <button type="button" class="bfm-close-btn" data-panel-action="close" aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <span class="bfm-field-label">Booking</span>
                    <p class="text-sm font-semibold text-slate-800">${bookingText}</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <span class="bfm-field-label">Tables included</span>
                    <p class="text-sm font-semibold text-slate-800">${tables.map((table) => escapeHtml(table.label)).join(', ')}</p>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <a href="${this.bookingsUrl}" class="bfm-action">Assign Booking</a>
                    ${group.booking_ref ? `<a href="${this.bookingsUrl}?search=${encodeURIComponent(group.booking_ref)}" class="bfm-action">View Booking</a>` : ''}
                    ${this.statusButton('available', 'Mark Free')}
                    ${this.statusButton('reserved', 'Mark Reserved')}
                    ${this.statusButton('occupied', 'Mark Occupied')}
                    ${this.statusButton('cleaning', 'Mark Cleaning')}
                    <button type="button" class="bfm-action border-sky-200 bg-sky-50 text-sky-800" data-panel-action="unmerge">
                        Unmerge
                    </button>
                </div>
            </div>
        `;
        this.bindPanelActions(tables.map((table) => table.id), group.id);
    }

    tablePanelHtml(table) {
        const statusClass = STATUS_CLASSES[normalizeStatus(table.status)] || STATUS_CLASSES.available;
        const booking = table.booking;
        const bookingText = booking
            ? `${escapeHtml(booking.guest)} - ${escapeHtml(booking.ref)} - ${Number(booking.party || 1)} guests`
            : 'No assigned booking';
        const guest = booking?.guest || table.guest?.name || (normalizeStatus(table.status) === 'occupied' ? 'Walk-in / current party' : 'No current guest');

        return `
            <div class="grid gap-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">${escapeHtml(table.label)}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">${Number(table.capacity)} ${Number(table.capacity) === 1 ? 'seat' : 'seats'}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex shrink-0 items-center rounded-full border px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${statusClass}">
                            ${statusLabel(table.status)}
                        </span>
                        <button type="button" class="bfm-close-btn" data-panel-action="close" aria-label="Close">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="grid gap-2">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <span class="bfm-field-label">Assigned booking</span>
                        <p class="text-sm font-semibold text-slate-800">${bookingText}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <span class="bfm-field-label">Current guest</span>
                        <p class="text-sm font-semibold text-slate-800">${escapeHtml(guest)}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <a href="${this.bookingsUrl}" class="bfm-action">Assign Booking</a>
                    ${booking ? `<a href="${this.bookingsUrl}?search=${encodeURIComponent(booking.ref || '')}" class="bfm-action">View Booking</a>` : ''}
                    ${this.statusButton('available', 'Mark Free', table)}
                    ${this.statusButton('reserved', 'Mark Reserved', table)}
                    ${this.statusButton('occupied', 'Mark Occupied', table)}
                    ${this.statusButton('cleaning', 'Mark Cleaning', table)}
                    <button type="button" class="bfm-action border-sky-200 bg-sky-50 text-sky-800" data-blueprint-action="start-merge">
                        Merge Tables
                    </button>
                </div>
            </div>
        `;
    }

    editPanelHtml(table) {
        return `
            <div class="grid gap-3 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">Edit ${escapeHtml(table.label)}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Move the marker by dragging it on the blueprint.</p>
                    </div>
                    <button type="button" class="bfm-close-btn" data-panel-action="close" aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <span class="bfm-field-label mb-1">Table Name</span>
                    <strong class="block text-xl font-black tracking-tight text-slate-950">${escapeHtml(table.label)}</strong>
                    <p class="mt-1 text-xs font-medium leading-snug text-slate-500">System-generated identifier. It cannot be renamed.</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="bfm-field-label">Capacity</label>
                        <input class="bfm-input" type="number" min="1" max="99" data-edit-field="capacity" value="${Number(table.capacity)}">
                    </div>
                    <div>
                        <label class="bfm-field-label">Shape / type</label>
                        <select class="bfm-input" data-edit-field="type">
                            ${this.typeOptions(table.furniture_type)}
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button type="button" class="tc-admin-btn-primary min-h-10 px-4 py-2 text-sm" data-panel-action="save-marker">
                        Save Marker
                    </button>
                    <button type="button" class="bfm-action border-rose-200 bg-rose-50 text-rose-800" data-panel-action="delete-marker">
                        Delete Marker
                    </button>
                </div>
            </div>
        `;
    }

    typeOptions(value) {
        const options = [
            ['standard', 'Standard'],
            ['booth', 'Booth'],
            ['bar', 'Bar / counter'],
            ['high-top', 'High-top'],
            ['outdoor', 'Outdoor'],
            ['bench', 'Bench'],
        ];

        return options.map(([optionValue, label]) => (
            `<option value="${optionValue}" ${optionValue === value ? 'selected' : ''}>${label}</option>`
        )).join('');
    }

    statusButton(status, label, table = null) {
        const disabled = table && normalizeStatus(table.status) === status ? 'disabled' : '';

        return `<button type="button" class="bfm-action" data-panel-status="${status}" ${disabled}>${label}</button>`;
    }

    bindPanelActions(tableIds, groupId = null) {
        this.panel.querySelectorAll('[data-panel-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.dataset.panelAction;
                if (action === 'close') {
                    this.selectedTableId = null;
                    this.selectedGroupId = null;
                    this.render();
                }
                if (action === 'unmerge' && groupId) {
                    this.unmergeGroup(groupId);
                }
                if (action === 'save-marker') {
                    this.saveSelectedMarker();
                }
                if (action === 'delete-marker') {
                    this.deleteSelectedMarker();
                }
            });
        });

        this.panel.querySelectorAll('[data-panel-status]').forEach((button) => {
            button.addEventListener('click', () => this.updateStatus(tableIds, button.dataset.panelStatus));
        });
    }

    groupLabel(group) {
        if (group.label) return group.label;

        const labels = this.groupTables(group).map((table) => table.label);

        return labels.join(' + ') || 'Merged group';
    }

    openAddModal() {
        if (!this.editMode || !this.addModal) return;
        this.addModal.hidden = false;
        this.renderControls();
        this.addModal.querySelector('[data-add-field="capacity"]')?.focus();
    }

    closeAddModal() {
        if (this.addModal) {
            this.addModal.hidden = true;
        }
    }

    beginPlacementFromModal() {
        const capacity = Number(this.addModal?.querySelector('[data-add-field="capacity"]')?.value || 1);
        const furnitureType = String(this.addModal?.querySelector('[data-add-field="type"]')?.value || 'standard');

        if (Number.isNaN(capacity) || capacity < 1) {
            notify('error', 'Enter a valid capacity.');
            return;
        }

        this.pendingMarker = {
            preview_label: this.nextTableLabel(),
            capacity,
            furniture_type: furnitureType,
        };
        this.closeAddModal();
        this.renderControls();
        notify('success', 'Click on the blueprint where this table should appear.');
    }

    cancelPlacement() {
        this.pendingMarker = null;
        this.renderControls();
    }

    async placeMarker(point) {
        if (!this.pendingMarker || !this.apiPlace) return;

        try {
            const { data } = await axios.post(this.apiPlace, {
                ...this.coordinatePayload(point),
                capacity: this.pendingMarker.capacity,
                furniture_type: this.pendingMarker.furniture_type,
                status: 'free',
            }, {
                headers: { Accept: 'application/json' },
            });

            const table = this.normalizeTable({
                id: data.seat.table_id,
                seat_id: data.seat.seat_id,
                label: data.seat.table_label,
                capacity: this.pendingMarker.capacity,
                status: 'available',
                x: data.seat.pos_x ?? point.x,
                y: data.seat.pos_y ?? point.y,
                furniture_type: this.pendingMarker.furniture_type,
                guest: { name: 'No current guest', party: String(this.pendingMarker.capacity), arrival_at: null },
                booking: null,
            });

            this.tables.push(table);
            this.pendingMarker = null;
            this.selectedTableId = table.id;
            this.render();
            notify('success', 'Table marker added.');
        } catch (error) {
            notify('error', firstError(error, 'Could not add table marker'));
        }
    }

    async saveLayout() {
        if (!this.editMode || !this.apiUpdate) return;
        if (this.pendingMoves.size === 0) {
            notify('success', 'Layout is already saved.');
            return;
        }

        try {
            for (const [tableId] of this.pendingMoves.entries()) {
                const table = this.table(tableId);
                if (!table?.seat_id) continue;
                const marker = this.root.querySelector(`[data-blueprint-marker][data-table-id="${table.id}"]`);
                const boundedPoint = this.pointFromTable(table, marker);
                if (!boundedPoint) {
                    this.restorePendingMoves();
                    notify('error', BOUNDARY_ERROR);
                    return;
                }
                if (boundedPoint.clamped) {
                    table.x = boundedPoint.x;
                    table.y = boundedPoint.y;
                }

                await axios.post(this.apiUpdate, {
                    seat_id: table.seat_id,
                    ...this.coordinatePayload(boundedPoint),
                }, {
                    headers: { Accept: 'application/json' },
                });

                table.savedX = table.x;
                table.savedY = table.y;
            }

            this.pendingMoves.clear();
            notify('success', 'Layout saved.');
        } catch (error) {
            this.restorePendingMoves();
            notify('error', firstError(error, 'Could not save layout'));
        }
    }

    async saveSelectedMarker() {
        const table = this.selectedTableId ? this.table(this.selectedTableId) : null;
        if (!table?.seat_id || !this.apiUpdate || !this.panel) return;

        const capacity = Number(this.panel.querySelector('[data-edit-field="capacity"]')?.value || 1);
        const furnitureType = String(this.panel.querySelector('[data-edit-field="type"]')?.value || 'standard');

        if (Number.isNaN(capacity) || capacity < 1) {
            notify('error', 'Enter a valid capacity.');
            return;
        }

        try {
            await axios.post(this.apiUpdate, {
                seat_id: table.seat_id,
                capacity,
                furniture_type: furnitureType,
            }, {
                headers: { Accept: 'application/json' },
            });

            table.capacity = capacity;
            table.furniture_type = furnitureType;
            this.render();
            notify('success', 'Marker saved.');
        } catch (error) {
            notify('error', firstError(error, 'Could not save marker'));
        }
    }

    async deleteSelectedMarker() {
        const table = this.selectedTableId ? this.table(this.selectedTableId) : null;
        if (!table?.seat_id || !this.apiDelete) return;
        if (!window.confirm(`Delete ${table.label} from the floor map?`)) return;

        try {
            await axios.post(this.apiDelete, {
                seat_id: table.seat_id,
                scope: 'table',
            }, {
                headers: { Accept: 'application/json' },
            });

            this.tables = this.tables.filter((item) => item.id !== table.id);
            this.groups = this.groups
                .map((group) => ({
                    ...group,
                    table_ids: group.table_ids.filter((id) => Number(id) !== Number(table.id)),
                }))
                .filter((group) => group.table_ids.length > 1);
            this.root.querySelector(`[data-blueprint-marker][data-table-id="${table.id}"]`)?.remove();
            this.selectedTableId = null;
            this.selectedGroupId = null;
            this.render();
            notify('success', 'Marker deleted.');
        } catch (error) {
            notify('error', firstError(error, 'Could not delete marker'));
        }
    }

    startMergeSelection() {
        this.selectionMode = true;
        this.selectedIds.clear();
        if (this.selectedTableId) {
            this.selectedIds.add(Number(this.selectedTableId));
        }
        this.selectedTableId = null;
        this.selectedGroupId = null;
        this.render();
    }

    cancelMergeSelection() {
        this.selectionMode = false;
        this.selectedIds.clear();
        this.closeMergeModal();
        this.render();
    }

    togglePicked(tableId) {
        if (this.selectedIds.has(tableId)) {
            this.selectedIds.delete(tableId);
        } else {
            if (!this.candidateCanJoinSelection(tableId)) {
                notify('error', 'These tables are too far apart to merge.');
                return;
            }
            this.selectedIds.add(tableId);
        }

        this.render();
    }

    openMergeModal() {
        if (this.selectedIds.size < 2 || !this.mergeModal) return;
        if (!this.mergeSetIsConnected([...this.selectedIds])) {
            notify('error', 'These tables are too far apart to merge.');
            return;
        }

        const tables = [...this.selectedIds].map((id) => this.table(id)).filter(Boolean);
        const labels = tables.map((table) => table.label).join(' + ');
        const capacity = tables.reduce((sum, table) => sum + Number(table.capacity || 0), 0);
        const labelInput = this.mergeModal.querySelector('[data-merge-field="label"]');
        const summary = this.mergeModal.querySelector('[data-blueprint-merge-summary]');

        if (labelInput) labelInput.value = labels;
        if (summary) summary.textContent = `${labels} - ${capacity} total seats`;

        this.mergeModal.hidden = false;
    }

    closeMergeModal() {
        if (this.mergeModal) {
            this.mergeModal.hidden = true;
        }
    }

    async mergeSelected() {
        const ids = [...this.selectedIds].map(Number).filter(Boolean);
        if (ids.length < 2) return;
        if (!this.mergeSetIsConnected(ids)) {
            notify('error', 'These tables are too far apart to merge.');
            return;
        }

        const bookingId = Number(this.mergeModal?.querySelector('[data-merge-field="booking"]')?.value || 0);
        const booking = this.bookings.find((item) => Number(item.id) === bookingId) || null;
        const label = String(this.mergeModal?.querySelector('[data-merge-field="label"]')?.value || '').trim()
            || ids.map((id) => this.table(id)?.label).filter(Boolean).join(' + ');

        const nextGroup = {
            id: `daily-${Date.now()}`,
            table_ids: ids,
            label,
            booking_id: booking?.id || null,
            booking_ref: booking?.ref || '',
            guest_name: booking?.guest || '',
            source: booking ? 'booking' : 'walk_in',
        };

        this.groups = this.groups
            .map((group) => ({
                ...group,
                table_ids: group.table_ids.filter((id) => !ids.includes(Number(id))),
            }))
            .filter((group) => group.table_ids.length > 1);
        this.groups.push(nextGroup);

        if (!await this.persistGroups()) return;

        this.selectedIds.clear();
        this.selectionMode = false;
        this.selectedTableId = null;
        this.selectedGroupId = nextGroup.id;
        this.closeMergeModal();
        this.render();
        notify('success', 'Tables merged for service.');
    }

    async unmergeGroup(groupId) {
        this.groups = this.groups.filter((group) => group.id !== groupId);

        if (!await this.persistGroups()) return;

        this.selectedGroupId = null;
        this.selectedTableId = null;
        this.render();
        notify('success', 'Tables unmerged.');
    }

    async persistGroups() {
        if (!this.apiMergeGroups) return false;

        try {
            const response = await axios.post(this.apiMergeGroups, {
                groups: this.groups,
            }, {
                headers: { Accept: 'application/json' },
            });

            const groups = response?.data?.planner?.mergeGroups;
            if (Array.isArray(groups)) {
                this.groups = groups.map(normalizeGroup).filter((group) => group.table_ids.length > 1);
            }

            return true;
        } catch (error) {
            notify('error', firstError(error, 'Could not save merged table group'));
            return false;
        }
    }

    async updateStatus(tableIds, status) {
        if (!this.apiStatus || !status || tableIds.length === 0) return;

        try {
            for (const id of tableIds) {
                await axios.post(this.apiStatus, {
                    table_id: id,
                    status,
                }, {
                    headers: { Accept: 'application/json' },
                });
                const table = this.table(id);
                if (table) {
                    table.status = normalizeStatus(status);
                    table.status_label = statusLabel(status);
                    if (status === 'available') {
                        table.booking = null;
                    }
                }
            }

            this.render();
            notify('success', tableIds.length > 1 ? 'Merged tables updated.' : `Table marked ${statusLabel(status).toLowerCase()}.`);
        } catch (error) {
            notify('error', firstError(error, 'Could not update table status'));
        }
    }
}

document.querySelectorAll('[data-blueprint-floor-map]').forEach((root) => {
    if (!root.blueprintFloorMap) {
        root.blueprintFloorMap = new BlueprintFloorMap(root);
    }
});
