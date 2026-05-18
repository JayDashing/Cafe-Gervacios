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
const BOUNDARY_ERROR = 'Table marker must stay inside the blueprint image.';
const DEFAULT_MARKER_WIDTH = 58;
const DEFAULT_MARKER_HEIGHT = 42;
const BLUEPRINT_ZOOM_MIN = 0.6;
const BLUEPRINT_ZOOM_MAX = 2.25;
const BLUEPRINT_ZOOM_STEP = 0.15;

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
        this.blueprint = root.querySelector('[data-blueprint-image], .bfm-blueprint');
        this.mapScroll = root.querySelector('.bfm-map-scroll');
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
        this.zoomLabel = root.querySelector('[data-blueprint-zoom-label]');
        this.apiTables = root.dataset.apiTables;
        this.apiStatus = root.dataset.apiStatus;
        this.apiMergeGroups = root.dataset.apiMergeGroups;
        this.apiGroup = root.dataset.apiGroup;
        this.apiUnmerge = root.dataset.apiUnmerge;
        this.apiPlace = root.dataset.apiPlace;
        this.apiUpdate = root.dataset.apiUpdate;
        this.apiDelete = root.dataset.apiDelete;
        this.bookingsUrl = root.dataset.bookingsUrl || '#';
        this.editMode = root.dataset.editMode === 'true';
        this.operationsMode = root.dataset.operationsMode === 'true';
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
        this.resizeFrame = null;
        this.resizeObserver = null;
        this.lastOperationsFit = null;
        this.blueprintZoom = 1;
        this.blueprintFitScale = 1;
        this.serverRefreshTimer = null;
        this.refreshingTables = false;
        this.suppressClickForTable = null;
        this.operationsHighlightIds = new Set();
        this.operationsLinkedTableId = null;

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
            seat_ids: Array.isArray(table.seat_ids) ? table.seat_ids.map((id) => Number(id)).filter(Boolean) : [],
            capacity: Number(table.capacity || 1),
            min_capacity: Number(table.min_capacity || table.capacity || 1),
            seat_count: Number(table.seat_count || 1),
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

        this.root.addEventListener('click', (event) => {
            if (!this.pendingMarker) return;
            if (event.target.closest('[data-blueprint-marker]')) return;
            if (event.target.closest('[data-blueprint-action], [data-blueprint-panel], .bfm-edit-toolbar')) return;

            const point = this.pointFromEvent(event, null, this.pendingMarker);
            if (!point) {
                notify('error', BOUNDARY_ERROR);
                return;
            }
            if (point.clamped) {
                notify('error', BOUNDARY_ERROR);
                return;
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

        this.blueprint?.addEventListener('load', () => this.scheduleRender());

        if (typeof ResizeObserver !== 'undefined' && this.mapScroll) {
            this.resizeObserver = new ResizeObserver(() => this.scheduleRender());
            this.resizeObserver.observe(this.mapScroll);
            if (this.blueprint) {
                this.resizeObserver.observe(this.blueprint);
            }
        }

        window.addEventListener('resize', () => this.scheduleRender(), { passive: true });
        window.addEventListener('keydown', (event) => {
            if (!this.operationsMode || event.key !== 'Escape') return;
            if (!this.selectedTableId && !this.selectedGroupId) return;

            event.preventDefault();
            this.closePanelSelection();
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

        if (action === 'zoom-out') {
            this.setBlueprintZoom(this.blueprintZoom - BLUEPRINT_ZOOM_STEP);
            return;
        }

        if (action === 'zoom-in') {
            this.setBlueprintZoom(this.blueprintZoom + BLUEPRINT_ZOOM_STEP);
            return;
        }

        if (action === 'zoom-fit') {
            this.setBlueprintZoom(1);
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
        this.operationsLinkedTableId = this.operationsHighlightIds.has(Number(tableId)) ? Number(tableId) : null;
        this.render();
    }

    setOperationsHighlight(payload = {}) {
        const data = Array.isArray(payload) ? (payload[0] || {}) : payload;
        const ids = Array.isArray(data.tableIds) ? data.tableIds : [];

        this.operationsHighlightIds = new Set(ids.map((id) => Number(id)).filter(Boolean));
        this.operationsLinkedTableId = null;
        this.renderMarkers();
    }

    scheduleServerRefresh() {
        if (!this.apiTables) return;

        if (this.serverRefreshTimer) {
            clearTimeout(this.serverRefreshTimer);
        }

        this.serverRefreshTimer = window.setTimeout(() => {
            this.serverRefreshTimer = null;
            this.refreshFromServer();
        }, 80);
    }

    async refreshFromServer() {
        if (!this.apiTables || this.refreshingTables) return;

        this.refreshingTables = true;

        try {
            const { data } = await axios.get(this.apiTables, {
                headers: { Accept: 'application/json' },
            });
            this.applyPlannerPayload(data);
        } catch (error) {
            console.warn('Could not refresh floor map state', error);
        } finally {
            this.refreshingTables = false;
        }
    }

    applyPlannerPayload(payload = {}) {
        const planner = payload?.planner || payload;
        const rows = Array.isArray(planner?.plannerTables) ? planner.plannerTables : [];

        if (rows.length > 0) {
            const canvas = planner?.plannerCanvas || {};
            const canvasWidth = finiteNumber(canvas.width, 100);
            const canvasHeight = finiteNumber(canvas.height, 100);
            const existingById = new Map(this.tables.map((table) => [Number(table.id), table]));

            this.tables = rows.map((row) => {
                const existing = existingById.get(Number(row.id));
                const fallbackX = canvasWidth > 0 ? clamp((finiteNumber(row.x, 0) / canvasWidth) * 100, 0, 100) : 50;
                const fallbackY = canvasHeight > 0 ? clamp((finiteNumber(row.y, 0) / canvasHeight) * 100, 0, 100) : 50;

                return this.normalizeTable({
                    ...(existing || {}),
                    id: Number(row.id),
                    label: String(row.label || existing?.label || ''),
                    capacity: Number(row.capacity || existing?.capacity || 1),
                    min_capacity: Number(row.min_capacity || existing?.min_capacity || row.capacity || existing?.capacity || 1),
                    seat_count: Number(row.seat_count || existing?.seat_count || 1),
                    status: normalizeStatus(row.status || existing?.status),
                    x: existing ? existing.x : fallbackX,
                    y: existing ? existing.y : fallbackY,
                    savedX: existing ? existing.savedX : fallbackX,
                    savedY: existing ? existing.savedY : fallbackY,
                    booking: row.booking || null,
                    guest: existing?.guest || null,
                    furniture_type: existing?.furniture_type || row.shape || 'standard',
                    merge_group: existing?.merge_group || 'default',
                });
            });
        }

        if (Array.isArray(planner?.mergeGroups)) {
            this.groups = planner.mergeGroups.map(normalizeGroup)
                .filter((group) => group.table_ids.length > 1);
        }

        if (this.selectedTableId && !this.table(this.selectedTableId)) {
            this.selectedTableId = null;
            this.selectedGroupId = null;
        }

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

    imageRect() {
        const rect = this.blueprint?.getBoundingClientRect?.();
        if (!rect || rect.width <= 0 || rect.height <= 0) return null;

        return rect;
    }

    markerBoundary(marker = null, table = null) {
        const rect = this.imageRect();
        const stageRect = this.stage?.getBoundingClientRect?.();
        if (!rect || !stageRect || rect.width <= 0 || rect.height <= 0) return null;

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
            imageOffsetX: rect.left - stageRect.left,
            imageOffsetY: rect.top - stageRect.top,
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
            image_width: Number(bounds.containerWidth.toFixed(2)),
            image_height: Number(bounds.containerHeight.toFixed(2)),
            marker_width: Number(bounds.markerWidth.toFixed(2)),
            marker_height: Number(bounds.markerHeight.toFixed(2)),
        };
    }

    pointFromPixels(pixelX, pixelY, bounds, rejectOutside = false) {
        if (
            rejectOutside
            && (pixelX < 0 || pixelY < 0 || pixelX > bounds.containerWidth || pixelY > bounds.containerHeight)
        ) {
            return null;
        }

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

        return this.pointFromPixels(event.clientX - bounds.left, event.clientY - bounds.top, bounds, true);
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

    pointToStagePixels(point, bounds) {
        return {
            left: bounds.imageOffsetX + (finiteNumber(point.x, 0) / 100) * bounds.containerWidth,
            top: bounds.imageOffsetY + (finiteNumber(point.y, 0) / 100) * bounds.containerHeight,
        };
    }

    applyMarkerPosition(marker, table) {
        const bounds = this.markerBoundary(marker, table);
        if (!bounds) {
            marker.style.left = `${table.x}%`;
            marker.style.top = `${table.y}%`;
            return;
        }

        const pixels = this.pointToStagePixels({ x: table.x, y: table.y }, bounds);
        marker.style.left = `${pixels.left}px`;
        marker.style.top = `${pixels.top}px`;
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
        const originalX = table.x;
        const originalY = table.y;
        const originalPendingMove = this.pendingMoves.get(table.id) || null;
        let moved = false;
        let clampedToBoundary = false;
        let outsideImage = false;

        const move = (moveEvent) => {
            const distance = Math.hypot(moveEvent.clientX - startX, moveEvent.clientY - startY);
            if (distance > 3) {
                moved = true;
                marker.classList.add('is-dragging');
            }
            if (!moved) return;

            const point = this.pointFromEvent(moveEvent, marker, table);
            if (!point) {
                outsideImage = true;
                return;
            }

            outsideImage = false;
            clampedToBoundary ||= point.clamped;
            table.x = point.x;
            table.y = point.y;
            this.applyMarkerPosition(marker, table);
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
                if (outsideImage) {
                    table.x = originalX;
                    table.y = originalY;
                    if (originalPendingMove) {
                        this.pendingMoves.set(table.id, originalPendingMove);
                    } else {
                        this.pendingMoves.delete(table.id);
                    }
                    this.applyMarkerPosition(marker, table);
                    this.renderGroups();
                    notify('error', BOUNDARY_ERROR);
                    return;
                }
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
        if (this.editMode && this.apiGroup) return true;
        if (this.selectedIds.has(tableId)) return true;
        if (this.selectedIds.size === 0) return true;

        return this.mergeSetIsConnected([...this.selectedIds, tableId]);
    }

    scheduleRender() {
        if (this.resizeFrame) {
            cancelAnimationFrame(this.resizeFrame);
        }

        this.resizeFrame = requestAnimationFrame(() => {
            this.resizeFrame = null;
            this.render();
        });
    }

    mapAvailableSize() {
        if (!this.mapScroll) return null;

        const styles = window.getComputedStyle(this.mapScroll);
        const availableWidth = this.mapScroll.clientWidth
            - finiteNumber(parseFloat(styles.paddingLeft), 0)
            - finiteNumber(parseFloat(styles.paddingRight), 0);
        const availableHeight = this.mapScroll.clientHeight
            - finiteNumber(parseFloat(styles.paddingTop), 0)
            - finiteNumber(parseFloat(styles.paddingBottom), 0);

        if (availableWidth <= 0 || availableHeight <= 0) return null;

        return {
            width: availableWidth,
            height: availableHeight,
        };
    }

    updateZoomControls() {
        if (!this.editMode) return;

        const renderedPercent = Math.max(1, Math.round(this.blueprintFitScale * this.blueprintZoom * 100));
        if (this.zoomLabel) {
            this.zoomLabel.textContent = `${renderedPercent}%`;
        }

        this.root.querySelectorAll('[data-blueprint-action="zoom-out"]').forEach((button) => {
            button.disabled = this.blueprintZoom <= BLUEPRINT_ZOOM_MIN + 0.001;
        });
        this.root.querySelectorAll('[data-blueprint-action="zoom-in"]').forEach((button) => {
            button.disabled = this.blueprintZoom >= BLUEPRINT_ZOOM_MAX - 0.001;
        });
        this.root.querySelectorAll('[data-blueprint-action="zoom-fit"]').forEach((button) => {
            button.disabled = Math.abs(this.blueprintZoom - 1) < 0.001;
        });
    }

    setBlueprintZoom(zoom) {
        if (!this.editMode) return;

        this.blueprintZoom = clamp(zoom, BLUEPRINT_ZOOM_MIN, BLUEPRINT_ZOOM_MAX);
        this.lastOperationsFit = null;
        this.fitBlueprintStage({ preserveCenter: true });
        this.renderMarkers();
        this.renderGroups();
        this.updateZoomControls();
    }

    fitBlueprintStage(options = {}) {
        if (!this.mapScroll || !this.stage || !this.blueprint) return;

        const naturalWidth = this.blueprint.naturalWidth || 0;
        const naturalHeight = this.blueprint.naturalHeight || 0;
        if (naturalWidth <= 0 || naturalHeight <= 0) return;

        const available = this.mapAvailableSize();
        if (!available) return;

        const fitScale = Math.min(available.width / naturalWidth, available.height / naturalHeight);
        const zoom = this.editMode ? this.blueprintZoom : 1;
        const scale = fitScale * zoom;
        const fittedWidth = Math.max(1, naturalWidth * scale);
        const fittedHeight = Math.max(1, naturalHeight * scale);
        const nextFit = `${this.editMode ? 'edit' : 'ops'}:${fittedWidth.toFixed(2)}x${fittedHeight.toFixed(2)}:${zoom.toFixed(3)}`;

        this.blueprintFitScale = fitScale;
        if (this.lastOperationsFit === nextFit) {
            this.updateZoomControls();
            return;
        }

        const oldWidth = this.stage.offsetWidth || 0;
        const oldHeight = this.stage.offsetHeight || 0;
        const oldCenterX = oldWidth > this.mapScroll.clientWidth
            ? (this.mapScroll.scrollLeft + (this.mapScroll.clientWidth / 2)) / oldWidth
            : 0.5;
        const oldCenterY = oldHeight > this.mapScroll.clientHeight
            ? (this.mapScroll.scrollTop + (this.mapScroll.clientHeight / 2)) / oldHeight
            : 0.5;

        this.lastOperationsFit = nextFit;
        this.stage.style.width = `${fittedWidth}px`;
        this.stage.style.height = `${fittedHeight}px`;
        this.blueprint.style.width = `${fittedWidth}px`;
        this.blueprint.style.height = `${fittedHeight}px`;
        this.blueprint.style.maxWidth = 'none';
        this.blueprint.style.maxHeight = 'none';

        if (options.preserveCenter) {
            this.mapScroll.scrollLeft = Math.max(0, (fittedWidth * oldCenterX) - (this.mapScroll.clientWidth / 2));
            this.mapScroll.scrollTop = Math.max(0, (fittedHeight * oldCenterY) - (this.mapScroll.clientHeight / 2));
        }

        this.updateZoomControls();
    }

    render() {
        this.fitBlueprintStage();
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
                'is-ops-compatible',
                'is-ops-linked',
                'is-ops-dimmed',
            );
            marker.classList.add(`bfm-marker--${status === 'available' ? 'free' : status}`);
            marker.classList.toggle('is-selected', Number(table.id) === Number(this.selectedTableId));
            marker.classList.toggle('is-picked', this.selectedIds.has(Number(table.id)));
            marker.classList.toggle('is-pick-mode', this.selectionMode);
            marker.classList.toggle('is-merged', Boolean(this.groupForTable(table.id)));
            const canCompareMergeDistance = this.selectionMode && this.selectedIds.size > 0 && !this.selectedIds.has(Number(table.id));
            marker.classList.toggle('is-merge-compatible', canCompareMergeDistance && this.candidateCanJoinSelection(Number(table.id)));
            marker.classList.toggle('is-merge-blocked', canCompareMergeDistance && !this.candidateCanJoinSelection(Number(table.id)));
            const hasOperationsHighlight = this.operationsHighlightIds.size > 0;
            const isOperationsCompatible = this.operationsHighlightIds.has(Number(table.id));
            marker.classList.toggle('is-ops-compatible', hasOperationsHighlight && isOperationsCompatible);
            marker.classList.toggle('is-ops-linked', hasOperationsHighlight && Number(table.id) === Number(this.operationsLinkedTableId));
            marker.classList.toggle('is-ops-dimmed', hasOperationsHighlight && !isOperationsCompatible);
            this.applyMarkerPosition(marker, table);
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
            const imageRect = this.imageRect();
            const stageRect = this.stage?.getBoundingClientRect?.();

            const xs = tables.map((table) => Number(table.x));
            const ys = tables.map((table) => Number(table.y));
            const minX = Math.max(0, Math.min(...xs) - 4);
            const maxX = Math.min(100, Math.max(...xs) + 4);
            const minY = Math.max(0, Math.min(...ys) - 5);
            const maxY = Math.min(100, Math.max(...ys) + 5);

            const button = document.createElement('button');
            button.type = 'button';
            button.className = `bfm-group ${group.id === this.selectedGroupId ? 'is-selected' : ''}`;
            if (imageRect && stageRect) {
                const offsetX = imageRect.left - stageRect.left;
                const offsetY = imageRect.top - stageRect.top;
                button.style.left = `${offsetX + (((minX + maxX) / 2) / 100) * imageRect.width}px`;
                button.style.top = `${offsetY + (((minY + maxY) / 2) / 100) * imageRect.height}px`;
                button.style.width = `${Math.max(60, ((maxX - minX) / 100) * imageRect.width)}px`;
                button.style.height = `${Math.max(60, ((maxY - minY) / 100) * imageRect.height)}px`;
            } else {
                button.style.left = `${(minX + maxX) / 2}%`;
                button.style.top = `${(minY + maxY) / 2}%`;
                button.style.width = `${Math.max(10, maxX - minX)}%`;
                button.style.height = `${Math.max(12, maxY - minY)}%`;
            }
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

        const editGrouping = this.editMode && this.apiGroup;
        const canMergeSelection = count >= 2 && (editGrouping || this.mergeSetIsConnected([...this.selectedIds]));

        if (merge) {
            merge.disabled = !canMergeSelection;
        }

        if (summary) {
            if (!this.selectionMode) {
                summary.textContent = editGrouping
                    ? 'Select two or more table markers to group.'
                    : 'Select two or more nearby tables to merge.';
            } else if (count === 0) {
                summary.textContent = editGrouping
                    ? 'Select the table markers to group.'
                    : 'Select a table. Nearby compatible tables will be highlighted.';
            } else if (count === 1) {
                summary.textContent = editGrouping
                    ? 'Select at least one more marker.'
                    : 'Nearby compatible tables are highlighted. Distant tables are dimmed.';
            } else {
                summary.textContent = canMergeSelection
                    ? `${count} tables selected - ready to merge`
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
                this.resetOperationsPopover();
            }
            this.body?.classList.remove('has-panel');
            return;
        }

        this.panel.hidden = false;
        this.body?.classList.toggle('has-panel', !this.operationsMode);
        this.panel.innerHTML = this.editMode ? this.editPanelHtml(table) : this.tablePanelHtml(table);
        this.bindPanelActions([table.id]);
        this.positionOperationsPopover([table.id]);
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
        this.body?.classList.toggle('has-panel', !this.operationsMode);
        const statusSummary = this.operationsMode
            ? `
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <span class="bfm-field-label">Status</span>
                    <p class="text-sm font-semibold text-slate-800">Merged service group</p>
                </div>
            `
            : '';
        const actions = `
            ${this.groupActionsHtml(tables)}
            ${group.booking_ref ? `<a href="${this.bookingsUrl}?search=${encodeURIComponent(group.booking_ref)}" class="bfm-action">View Booking</a>` : ''}
            ${!this.operationsMode ? `
                <button type="button" class="bfm-action border-sky-200 bg-sky-50 text-sky-800" data-panel-action="unmerge">
                    Unmerge
                </button>
            ` : ''}
        `;
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
                ${statusSummary}

                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <span class="bfm-field-label">Tables included</span>
                    <p class="text-sm font-semibold text-slate-800">${tables.map((table) => escapeHtml(table.label)).join(', ')}</p>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    ${actions}
                </div>
            </div>
        `;
        this.bindPanelActions(tables.map((table) => table.id), group.id);
        this.positionOperationsPopover(tables.map((table) => table.id));
    }

    positionOperationsPopover(tableIds = []) {
        if (!this.operationsMode || !this.panel || this.panel.hidden) return;

        const anchorRect = this.anchorRectForTables(tableIds);
        if (!anchorRect) {
            this.resetOperationsPopover();
            return;
        }

        this.panel.classList.add('is-popover');
        this.panel.style.removeProperty('bottom');
        this.panel.style.removeProperty('right');

        const viewport = window.visualViewport || window;
        const viewportWidth = Number(viewport.width || window.innerWidth || document.documentElement.clientWidth || 0);
        const viewportHeight = Number(viewport.height || window.innerHeight || document.documentElement.clientHeight || 0);
        const viewportLeft = Number(viewport.offsetLeft || 0);
        const viewportTop = Number(viewport.offsetTop || 0);
        const fallbackBounds = {
            left: viewportLeft,
            top: viewportTop,
            right: viewportLeft + viewportWidth,
            bottom: viewportTop + viewportHeight,
            width: viewportWidth,
            height: viewportHeight,
        };
        const mapBounds = this.mapScroll?.getBoundingClientRect() || fallbackBounds;
        const margin = 14;
        const gap = 12;
        const bounds = {
            left: Math.max(mapBounds.left, fallbackBounds.left),
            top: Math.max(mapBounds.top, fallbackBounds.top),
            right: Math.min(mapBounds.right, fallbackBounds.right),
            bottom: Math.min(mapBounds.bottom, fallbackBounds.bottom),
        };
        bounds.width = Math.max(0, bounds.right - bounds.left);
        bounds.height = Math.max(0, bounds.bottom - bounds.top);

        this.panel.style.setProperty('--bfm-popover-max-height', `${Math.max(220, bounds.height - margin * 2)}px`);

        const panelRect = this.panel.getBoundingClientRect();
        const panelWidth = Math.min(panelRect.width || 384, Math.max(260, bounds.width - margin * 2));
        const panelHeight = Math.min(panelRect.height || 360, Math.max(220, bounds.height - margin * 2));
        const canFitRight = bounds.right - anchorRect.right >= panelWidth + gap + margin;
        const canFitLeft = anchorRect.left - bounds.left >= panelWidth + gap + margin;

        let placement = 'bottom';
        let left = anchorRect.left + (anchorRect.width / 2) - (panelWidth / 2);
        let top = anchorRect.bottom + gap;

        if (canFitRight || canFitLeft) {
            placement = canFitRight ? 'right' : 'left';
            left = canFitRight ? anchorRect.right + gap : anchorRect.left - panelWidth - gap;
            top = anchorRect.top + (anchorRect.height / 2) - (panelHeight / 2);
        } else if (anchorRect.bottom + gap + panelHeight <= bounds.bottom - margin) {
            placement = 'bottom';
            top = anchorRect.bottom + gap;
        } else {
            placement = 'top';
            top = anchorRect.top - panelHeight - gap;
        }

        left = clamp(left, bounds.left + margin, bounds.right - panelWidth - margin);
        top = clamp(top, bounds.top + margin, bounds.bottom - panelHeight - margin);

        this.panel.dataset.placement = placement;
        this.panel.style.setProperty('--bfm-popover-left', `${left}px`);
        this.panel.style.setProperty('--bfm-popover-top', `${top}px`);
    }

    anchorRectForTables(tableIds = []) {
        const rects = tableIds
            .map((id) => this.root.querySelector(`[data-blueprint-marker][data-table-id="${Number(id)}"]`))
            .filter(Boolean)
            .map((marker) => marker.getBoundingClientRect())
            .filter((rect) => rect.width > 0 && rect.height > 0);

        if (rects.length === 0) return null;

        const left = Math.min(...rects.map((rect) => rect.left));
        const top = Math.min(...rects.map((rect) => rect.top));
        const right = Math.max(...rects.map((rect) => rect.right));
        const bottom = Math.max(...rects.map((rect) => rect.bottom));

        return {
            left,
            top,
            right,
            bottom,
            width: right - left,
            height: bottom - top,
        };
    }

    resetOperationsPopover() {
        if (!this.panel) return;

        this.panel.classList.remove('is-popover');
        this.panel.removeAttribute('data-placement');
        this.panel.style.removeProperty('--bfm-popover-left');
        this.panel.style.removeProperty('--bfm-popover-top');
        this.panel.style.removeProperty('--bfm-popover-max-height');
    }

    tablePanelHtml(table) {
        const statusClass = STATUS_CLASSES[normalizeStatus(table.status)] || STATUS_CLASSES.available;
        const booking = table.booking;
        const guest = booking?.guest || table.guest?.name || (normalizeStatus(table.status) === 'occupied' ? 'Walk-in / current party' : 'No current guest');
        const actions = `
            ${this.tableActionsHtml(table)}
            ${booking ? `<a href="${this.bookingsUrl}?search=${encodeURIComponent(booking.ref || '')}" class="bfm-action">View Booking</a>` : ''}
            ${!this.operationsMode ? `
                <button type="button" class="bfm-action border-sky-200 bg-sky-50 text-sky-800" data-blueprint-action="start-merge">
                    Merge Tables
                </button>
            ` : ''}
        `;

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
                    ${this.bookingBannerHtml(table)}
                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                        <span class="bfm-field-label">Current guest</span>
                        <p class="text-sm font-semibold text-slate-800">${escapeHtml(guest)}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    ${actions}
                </div>
            </div>
        `;
    }

    editPanelHtml(table) {
        const seatCount = Math.max(1, Number(table.seat_count || 1));
        const minimumCapacity = Math.max(1, Number(table.min_capacity || table.capacity || seatCount));
        const canSplit = this.editMode && this.apiUnmerge && seatCount > 1;
        const canRemoveSeat = minimumCapacity > 1;

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
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="bfm-field-label">Table Name</label>
                        <input class="bfm-input" type="text" maxlength="50" data-edit-field="label" value="${escapeHtml(table.label)}">
                    </div>
                    <div>
                        <label class="bfm-field-label">Capacity</label>
                        <input class="bfm-input" type="number" min="${minimumCapacity}" max="99" data-edit-field="capacity" value="${Number(table.capacity)}">
                        <p class="mt-1 text-[11px] font-medium text-slate-500">Minimum: ${minimumCapacity} mapped ${minimumCapacity === 1 ? 'seat' : 'seats'}.</p>
                    </div>
                    <div class="sm:col-span-2">
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
                    <button type="button" class="bfm-action border-sky-200 bg-sky-50 text-sky-800" data-blueprint-action="start-merge">
                        Merge Tables
                    </button>
                </div>
                ${canSplit ? `
                    <button type="button" class="bfm-action border-sky-200 bg-sky-50 text-sky-800" data-panel-action="unmerge-table">
                        Split Tables
                    </button>
                ` : ''}
                <div class="rounded-xl border border-rose-200 bg-rose-50/40 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-rose-900">Danger zone</p>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        ${canRemoveSeat ? `
                            <button type="button" class="bfm-action border-rose-200 bg-white text-rose-800" data-panel-action="delete-seat">
                                Remove One Seat
                            </button>
                        ` : ''}
                        <button type="button" class="bfm-action border-rose-200 bg-white text-rose-800" data-panel-action="delete-marker">
                            Remove Whole Table
                        </button>
                    </div>
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

    tableActionsHtml(table) {
        const status = normalizeStatus(table.status);
        const hasBooking = this.tableHasBooking(table);

        if (status === 'reserved') {
            return [
                hasBooking ? this.commandButton('check_in', 'Check in') : '',
                hasBooking ? this.commandButton('no_show', 'No-show') : '',
                !hasBooking ? this.commandButton('seat_walk_in', 'Seat walk-in') : '',
                this.commandButton('release_table', 'Release table'),
            ].filter(Boolean).join('');
        }

        if (status === 'occupied') {
            return [
                this.commandButton('send_to_cleaning', 'Send to cleaning'),
                this.commandButton('mark_free', 'Mark free'),
            ].join('');
        }

        if (status === 'available') {
            if (hasBooking) {
                return this.emptyActionMessage('Online booking attached; seating is blocked.');
            }

            return [
                this.commandButton('seat_walk_in', 'Seat walk-in'),
                `<button type="button" class="bfm-action border-sky-200 bg-sky-50 text-sky-800" data-panel-action="seat-waitlist" data-panel-table-id="${Number(table.id)}">Seat from queue</button>`,
            ].join('');
        }

        if (status === 'cleaning') {
            return this.commandButton('mark_free', 'Mark free');
        }

        return this.emptyActionMessage('No staff actions available.');
    }

    groupActionsHtml(tables) {
        if (!Array.isArray(tables) || tables.length === 0) {
            return this.emptyActionMessage('No tables selected.');
        }

        const statuses = [...new Set(tables.map((table) => normalizeStatus(table.status)))];
        if (statuses.length !== 1) {
            return this.emptyActionMessage('Select one status group at a time.');
        }

        const status = statuses[0];
        const anyBooking = tables.some((table) => this.tableHasBooking(table));
        const allHaveBookings = tables.every((table) => this.tableHasBooking(table));

        if (status === 'reserved') {
            return [
                allHaveBookings ? this.commandButton('check_in', 'Check in') : '',
                allHaveBookings ? this.commandButton('no_show', 'No-show') : '',
                !anyBooking ? this.commandButton('seat_walk_in', 'Seat walk-in') : '',
                this.commandButton('release_table', 'Release table'),
            ].filter(Boolean).join('');
        }

        if (status === 'occupied') {
            return [
                this.commandButton('send_to_cleaning', 'Send to cleaning'),
                this.commandButton('mark_free', 'Mark free'),
            ].join('');
        }

        if (status === 'available') {
            if (anyBooking) {
                return this.emptyActionMessage('Online booking attached; seating is blocked.');
            }

            return [
                this.commandButton('seat_walk_in', 'Seat walk-in'),
                `<button type="button" class="bfm-action border-sky-200 bg-sky-50 text-sky-800" data-panel-action="seat-waitlist" data-panel-table-id="${tables[0]?.id || ''}">Seat from queue</button>`,
            ].join('');
        }

        if (status === 'cleaning') {
            return this.commandButton('mark_free', 'Mark free');
        }

        return this.emptyActionMessage('No staff actions available.');
    }

    commandButton(command, label) {
        return `<button type="button" class="bfm-action" data-panel-command="${command}">${label}</button>`;
    }

    emptyActionMessage(message) {
        return `<p class="col-span-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">${escapeHtml(message)}</p>`;
    }

    tableHasBooking(table) {
        return Number(table?.booking_id || 0) > 0;
    }

    bookingBannerHtml(table) {
        const booking = table.booking;
        if (booking) {
            const parts = [
                booking.guest || 'Guest',
                booking.booked_at || booking.time || null,
                `${Number(booking.party || 1)} pax`,
            ].filter(Boolean);

            return `
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <span class="bfm-field-label text-amber-900">Online booking</span>
                    <p class="text-sm font-semibold text-amber-950">${escapeHtml(parts.join(' - '))}</p>
                    ${booking.ref ? `<p class="mt-1 font-mono text-xs text-amber-800">${escapeHtml(booking.ref)}</p>` : ''}
                </div>
            `;
        }

        const fallback = normalizeStatus(table.status) === 'occupied'
            ? (table.guest?.name || 'Walk-in')
            : 'No reservation';

        return `
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <span class="bfm-field-label">Booking</span>
                <p class="text-sm font-semibold text-slate-700">${escapeHtml(fallback)}</p>
            </div>
        `;
    }

    bindPanelActions(tableIds, groupId = null) {
        this.panel.querySelectorAll('[data-panel-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.dataset.panelAction;
                if (action === 'close') {
                    this.closePanelSelection();
                }
                if (action === 'unmerge' && groupId) {
                    this.unmergeGroup(groupId);
                }
                if (action === 'save-marker') {
                    this.saveSelectedMarker();
                }
                if (action === 'delete-seat') {
                    this.deleteSelectedSeat();
                }
                if (action === 'delete-marker') {
                    this.deleteSelectedMarker();
                }
                if (action === 'unmerge-table') {
                    this.unmergePhysicalTable();
                }
                if (action === 'seat-waitlist') {
                    const tableId = Number(button.dataset.panelTableId || tableIds[0] || 0);
                    if (!tableId) {
                        notify('error', 'Choose a table marker first.');
                        return;
                    }
                    if (typeof window.Livewire?.dispatch !== 'function') {
                        notify('error', 'Waitlist actions are not ready yet.');
                        return;
                    }
                    window.Livewire.dispatch('floor-map-seat-waitlist-guest', { tableId });
                    if (this.operationsMode) {
                        this.closePanelSelection();
                    } else {
                        this.selectedTableId = tableId;
                        this.selectedGroupId = null;
                        this.render();
                    }
                }
            });
        });

        this.panel.querySelectorAll('[data-panel-command]').forEach((button) => {
            button.addEventListener('click', () => this.runCommand(tableIds, button.dataset.panelCommand));
        });

        this.panel.querySelectorAll('[data-panel-status]').forEach((button) => {
            button.addEventListener('click', () => this.updateStatus(tableIds, button.dataset.panelStatus));
        });
    }

    closePanelSelection() {
        this.selectedTableId = null;
        this.selectedGroupId = null;
        this.body?.classList.remove('has-panel');
        if (this.panel) {
            this.panel.hidden = true;
            this.panel.innerHTML = '';
            this.resetOperationsPopover();
        }
        this.renderMarkers();
        this.renderGroups();
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
                    this.restorePendingMoves();
                    notify('error', BOUNDARY_ERROR);
                    return;
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

        const label = String(this.panel.querySelector('[data-edit-field="label"]')?.value || '').trim();
        const capacity = Number(this.panel.querySelector('[data-edit-field="capacity"]')?.value || 1);
        const furnitureType = String(this.panel.querySelector('[data-edit-field="type"]')?.value || 'standard');
        const minimumCapacity = Math.max(1, Number(table.min_capacity || table.capacity || table.seat_count || 1));

        if (!label) {
            notify('error', 'Enter a table name.');
            return;
        }

        if (Number.isNaN(capacity) || capacity < 1) {
            notify('error', 'Enter a valid capacity.');
            return;
        }

        if (capacity < minimumCapacity) {
            notify('error', `Capacity must be at least ${minimumCapacity} mapped ${minimumCapacity === 1 ? 'seat' : 'seats'}.`);
            return;
        }

        try {
            const response = await axios.post(this.apiUpdate, {
                seat_id: table.seat_id,
                label,
                capacity,
                furniture_type: furnitureType,
            }, {
                headers: { Accept: 'application/json' },
            });

            table.label = label;
            table.capacity = capacity;
            table.min_capacity = Number(response.data?.table?.min_capacity || capacity);
            table.furniture_type = furnitureType;
            this.render();
            notify('success', 'Marker saved.');
        } catch (error) {
            notify('error', firstError(error, 'Could not save marker'));
        }
    }

    async deleteSelectedSeat() {
        const table = this.selectedTableId ? this.table(this.selectedTableId) : null;
        if (!table?.seat_id || !this.apiDelete) return;
        if (Math.max(1, Number(table.min_capacity || table.capacity || table.seat_count || 1)) <= 1) {
            notify('error', 'This table only has one mapped seat.');
            return;
        }
        if (!window.confirm(`Remove one mapped seat from ${table.label}?`)) return;

        try {
            await axios.post(this.apiDelete, {
                seat_id: table.seat_id,
                scope: 'seat',
            }, {
                headers: { Accept: 'application/json' },
            });

            notify('success', 'Seat removed.');
            window.location.reload();
        } catch (error) {
            notify('error', firstError(error, 'Could not remove seat'));
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

    async unmergePhysicalTable() {
        const table = this.selectedTableId ? this.table(this.selectedTableId) : null;
        if (!table?.seat_id || !this.apiUnmerge) return;
        if (!window.confirm(`Split ${table.label} into separate table markers?`)) return;

        try {
            await axios.post(this.apiUnmerge, {
                seat_id: table.seat_id,
            }, {
                headers: { Accept: 'application/json' },
            });

            notify('success', 'Tables split.');
            window.location.reload();
        } catch (error) {
            notify('error', firstError(error, 'Could not split table'));
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
        if (!(this.editMode && this.apiGroup) && !this.mergeSetIsConnected([...this.selectedIds])) {
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
        if (!(this.editMode && this.apiGroup) && !this.mergeSetIsConnected(ids)) {
            notify('error', 'These tables are too far apart to merge.');
            return;
        }

        const label = String(this.mergeModal?.querySelector('[data-merge-field="label"]')?.value || '').trim()
            || ids.map((id) => this.table(id)?.label).filter(Boolean).join(' + ');

        if (this.editMode && this.apiGroup) {
            const seatIds = ids.flatMap((id) => {
                const table = this.table(id);
                if (Array.isArray(table?.seat_ids) && table.seat_ids.length > 0) {
                    return table.seat_ids;
                }

                return table?.seat_id ? [table.seat_id] : [];
            }).map(Number).filter(Boolean);

            if (seatIds.length < 2) {
                notify('error', 'Select two or more table markers to merge.');
                return;
            }

            try {
                await axios.post(this.apiGroup, {
                    seat_ids: seatIds,
                    label,
                }, {
                    headers: { Accept: 'application/json' },
                });

                this.selectedIds.clear();
                this.selectionMode = false;
                this.closeMergeModal();
                notify('success', 'Tables grouped.');
                window.location.reload();
            } catch (error) {
                notify('error', firstError(error, 'Could not group tables'));
            }

            return;
        }

        const bookingId = Number(this.mergeModal?.querySelector('[data-merge-field="booking"]')?.value || 0);
        const booking = this.bookings.find((item) => Number(item.id) === bookingId) || null;

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
            let latestPayload = null;
            for (const id of tableIds) {
                const { data } = await axios.post(this.apiStatus, {
                    table_id: id,
                    status,
                }, {
                    headers: { Accept: 'application/json' },
                });
                latestPayload = data;
                const table = this.table(id);
                if (table) {
                    table.status = normalizeStatus(status);
                    table.status_label = statusLabel(status);
                    if (status === 'available') {
                        table.booking = null;
                    }
                }
            }

            if (latestPayload?.planner) {
                this.applyPlannerPayload(latestPayload.planner);
            }
            if (this.operationsMode) {
                this.selectedTableId = null;
                this.selectedGroupId = null;
                this.body?.classList.remove('has-panel');
            }
            this.render();
            if (typeof window.Livewire?.dispatch === 'function') {
                window.Livewire.dispatch('tables-refresh');
                window.Livewire.dispatch('table-updated');
                window.Livewire.dispatch('queue-updated');
                window.Livewire.dispatch('eta-recalculated');
            }
            notify('success', tableIds.length > 1 ? 'Merged tables updated.' : `Table marked ${statusLabel(status).toLowerCase()}.`);
        } catch (error) {
            notify('error', firstError(error, 'Could not update table status'));
        }
    }

    async runCommand(tableIds, command) {
        if (!this.apiStatus || !command || tableIds.length === 0) return;

        try {
            let latestPayload = null;
            for (const id of tableIds) {
                const { data } = await axios.post(this.apiStatus, {
                    table_id: id,
                    action: command,
                }, {
                    headers: { Accept: 'application/json' },
                });
                latestPayload = data;
            }

            if (latestPayload?.planner) {
                this.applyPlannerPayload(latestPayload.planner);
            }
            if (this.operationsMode) {
                this.selectedTableId = null;
                this.selectedGroupId = null;
                this.body?.classList.remove('has-panel');
            }
            this.render();
            if (typeof window.Livewire?.dispatch === 'function') {
                window.Livewire.dispatch('tables-refresh');
                window.Livewire.dispatch('table-updated');
                window.Livewire.dispatch('queue-updated');
                window.Livewire.dispatch('eta-recalculated');
            }
            notify('success', this.commandSuccessMessage(command, tableIds.length));
        } catch (error) {
            notify('error', firstError(error, 'Could not update table'));
        }
    }

    commandSuccessMessage(command, count) {
        const prefix = count > 1 ? 'Tables' : 'Table';

        return {
            check_in: count > 1 ? 'Guests checked in.' : 'Guest checked in.',
            no_show: count > 1 ? 'No-shows marked.' : 'No-show marked.',
            seat_walk_in: count > 1 ? 'Walk-ins seated.' : 'Walk-in seated.',
            send_to_cleaning: `${prefix} sent to cleaning.`,
            mark_free: `${prefix} marked free.`,
            release_table: `${prefix} released.`,
        }[command] || `${prefix} updated.`;
    }
}

document.querySelectorAll('[data-blueprint-floor-map]').forEach((root) => {
    if (!root.blueprintFloorMap) {
        root.blueprintFloorMap = new BlueprintFloorMap(root);
    }
});

document.addEventListener('livewire:init', () => {
    if (typeof window.Livewire?.on !== 'function') {
        return;
    }

    window.Livewire.on('operations-highlight-compatible-tables', (payload) => {
        document.querySelectorAll('[data-blueprint-floor-map]').forEach((root) => {
            root.blueprintFloorMap?.setOperationsHighlight(payload);
        });
    });

    ['tables-refresh', 'table-updated', 'guest-seated', 'reservation-updated'].forEach((eventName) => {
        window.Livewire.on(eventName, () => {
            document.querySelectorAll('[data-blueprint-floor-map]').forEach((root) => {
                root.blueprintFloorMap?.scheduleServerRefresh();
            });
        });
    });
});
