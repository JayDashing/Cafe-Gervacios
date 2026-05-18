import axios from 'axios';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { TABLE_OBJECT_MAP, TABLE_OBJECT_NAME_HINTS, TABLE_VISIBLE_OBJECT_MAP } from './restaurant-table-object-map.js';

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const PLANNER_WIDTH = 1200;
const PLANNER_HEIGHT = 760;

const STATUS_COLORS = {
    available: 0x4f8a68,
    reserved: 0xb98537,
    occupied: 0x9e4d54,
    cleaning: 0x5e738a,
};

const CAFE_MATERIALS = {
    floor: { color: 0xb8895f, roughness: 0.94, metalness: 0.02 },
    floorReveal: { color: 0x8f6848, roughness: 0.96, metalness: 0.01 },
    walkway: { color: 0xe8d9bd, roughness: 0.92, metalness: 0.01, opacity: 0.82 },
    zoneWindow: { color: 0x91a3b0, roughness: 0.9, metalness: 0.01, opacity: 0.28 },
    zoneDining: { color: 0xd8b98a, roughness: 0.9, metalness: 0.01, opacity: 0.22 },
    zoneRound: { color: 0xd0b28a, roughness: 0.9, metalness: 0.01, opacity: 0.2 },
    zoneCounter: { color: 0x4a3a31, roughness: 0.88, metalness: 0.03, opacity: 0.34 },
    zoneKitchen: { color: 0xa99a86, roughness: 0.9, metalness: 0.01, opacity: 0.2 },
    zoneWaiting: { color: 0xeadbc2, roughness: 0.92, metalness: 0.01, opacity: 0.28 },
    marble: { color: 0xded2bd, roughness: 0.72, metalness: 0.02 },
    marbleDark: { color: 0xcfc2ad, roughness: 0.74, metalness: 0.02 },
    walnut: { color: 0x6e4d33, roughness: 0.74, metalness: 0.04 },
    darkWalnut: { color: 0x3b2a22, roughness: 0.78, metalness: 0.04 },
    charcoal: { color: 0x252b32, roughness: 0.82, metalness: 0.06 },
    boothBlue: { color: 0x40566d, roughness: 0.86, metalness: 0.02 },
    wall: { color: 0xd7c5ab, roughness: 0.9, metalness: 0.01 },
    glass: { color: 0x9eb6c4, roughness: 0.2, metalness: 0.02, opacity: 0.42 },
    brass: { color: 0xb08a47, roughness: 0.48, metalness: 0.35 },
    light: { color: 0xf2d9a7, roughness: 0.48, metalness: 0.02 },
    plant: { color: 0x55755d, roughness: 0.9, metalness: 0.01 },
    plantPot: { color: 0x6f5544, roughness: 0.88, metalness: 0.02 },
};

const STATUS_CLASSES = {
    available: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    reserved: 'border-amber-200 bg-amber-50 text-amber-900',
    occupied: 'border-rose-200 bg-rose-50 text-rose-800',
    cleaning: 'border-cyan-200 bg-cyan-50 text-cyan-800',
};

const ZONES = [
    { key: 'entrance', name: 'Entrance View', x: 28, y: 520, width: 246, height: 198 },
    { key: 'counter', name: 'Counter', x: 28, y: 38, width: 252, height: 178 },
    { key: 'dining-a', name: 'Dining Area A', x: 312, y: 58, width: 352, height: 286 },
    { key: 'dining-b', name: 'Dining Area B', x: 312, y: 376, width: 352, height: 316 },
    { key: 'window', name: 'Window Side', x: 700, y: 58, width: 462, height: 190 },
    { key: 'group', name: 'Group Area', x: 700, y: 286, width: 306, height: 406 },
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

function normalizeStatus(status) {
    if (status === 'free') return 'available';

    return status || 'available';
}

function statusLabel(status) {
    return normalizeStatus(status) === 'available'
        ? 'FREE'
        : normalizeStatus(status).replace(/_/g, ' ').toUpperCase();
}

function statusClass(status) {
    return STATUS_CLASSES[normalizeStatus(status)] || 'border-slate-200 bg-slate-50 text-slate-700';
}

function normalizeKey(value) {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');
}

function vectorSnapshot(vector) {
    return [
        Number(vector.x.toFixed(3)),
        Number(vector.y.toFixed(3)),
        Number(vector.z.toFixed(3)),
    ];
}

function cafeMaterialForNode(name) {
    if (/^Floor_Operational_Map/i.test(name)) return CAFE_MATERIALS.floor;
    if (/^Floor_Subtle_Plank_Reveal/i.test(name)) return CAFE_MATERIALS.floorReveal;
    if (/^ServicePath_/i.test(name)) return CAFE_MATERIALS.walkway;
    if (/^Zone_Window/i.test(name)) return CAFE_MATERIALS.zoneWindow;
    if (/^Zone_Main_Dining/i.test(name)) return CAFE_MATERIALS.zoneDining;
    if (/^Zone_Round_Table/i.test(name)) return CAFE_MATERIALS.zoneRound;
    if (/^Zone_Counter/i.test(name)) return CAFE_MATERIALS.zoneCounter;
    if (/^Zone_Kitchen/i.test(name)) return CAFE_MATERIALS.zoneKitchen;
    if (/^Zone_Entrance/i.test(name)) return CAFE_MATERIALS.zoneWaiting;
    if (/^Table_.*_Edge$/i.test(name) || /^Booth_.*_Edge$/i.test(name)) return CAFE_MATERIALS.walnut;
    if (/^Table_.*_(Base|Foot)$/i.test(name) || /^Counter_\d+_(Post|Foot)$/i.test(name)) return CAFE_MATERIALS.charcoal;
    if (/^Table_/i.test(name) || /^Booth_[A-Z]\d$/i.test(name)) return CAFE_MATERIALS.marble;
    if (/^Booth_.*_(Bench|Back)/i.test(name)) return CAFE_MATERIALS.boothBlue;
    if (/^Booth_.*_Base$/i.test(name)) return CAFE_MATERIALS.darkWalnut;
    if (/^Chair_/i.test(name)) return CAFE_MATERIALS.charcoal;
    if (/^Counter_Cashier_Marble_Top/i.test(name)) return CAFE_MATERIALS.marbleDark;
    if (/^(Counter_Cashier_Run|Kitchen_|Waiting_Bench|Waiting_Bench_Back|Host_Stand|Small_Menu_Board)/i.test(name)) return CAFE_MATERIALS.darkWalnut;
    if (/^(POS_|Pastry_|Espresso_)/i.test(name)) return CAFE_MATERIALS.charcoal;
    if (/^(LowWall|Entrance_Left_LowWall|Entrance_Right_LowWall|Window_Walnut_Sill)/i.test(name)) return CAFE_MATERIALS.wall;
    if (/^(Entrance_Glass|Window_Glass|Window_Mullion)/i.test(name)) return CAFE_MATERIALS.glass;
    if (/Pendant_.*_Cord/i.test(name)) return CAFE_MATERIALS.brass;
    if (/Pendant_.*_Soft_Globe/i.test(name)) return CAFE_MATERIALS.light;
    if (/Plant_.*_Pot/i.test(name)) return CAFE_MATERIALS.plantPot;
    if (/Plant_.*_Foliage/i.test(name)) return CAFE_MATERIALS.plant;

    return null;
}

function parseTables(root) {
    const node = root.querySelector('[data-restaurant-preview-tables]');
    if (!node) return [];

    try {
        return JSON.parse(node.textContent || '[]').map((table) => normalizeTable(table));
    } catch (error) {
        console.warn('Could not parse restaurant floor view tables', error);
        return [];
    }
}

function normalizeTable(table) {
    return {
        id: Number(table.id),
        label: String(table.label || `T${table.id}`),
        capacity: Number(table.capacity || 1),
        status: normalizeStatus(table.status),
        x: Number(table.x || 0),
        y: Number(table.y || 0),
        width: Number(table.width || 120),
        height: Number(table.height || 90),
        booking: table.booking || null,
    };
}

function zoneForTable(table) {
    const cx = Number(table.x || 0) + (Number(table.width || 0) / 2);
    const cy = Number(table.y || 0) + (Number(table.height || 0) / 2);

    return ZONES.find((zone) => (
        cx >= zone.x
        && cx <= zone.x + zone.width
        && cy >= zone.y
        && cy <= zone.y + zone.height
    )) || ZONES[2];
}

function easeInOut(value) {
    return value < 0.5
        ? 2 * value * value
        : 1 - ((-2 * value + 2) ** 2) / 2;
}

class RestaurantPreview {
    constructor(root) {
        this.root = root;
        this.container = root.querySelector('[data-preview-canvas]');
        this.stage = root.querySelector('[data-preview-stage]');
        this.error = root.querySelector('[data-preview-error]');
        this.loading = root.querySelector('[data-preview-loading]');
        this.panel = root.querySelector('[data-preview-panel]');
        this.tooltip = root.querySelector('[data-preview-tooltip]');
        this.zoneChip = root.querySelector('[data-preview-zone-chip]');
        this.search = root.querySelector('[data-preview-search]');
        this.mappingSummary = root.querySelector('[data-preview-mapping-summary]');
        this.debugPanel = root.querySelector('[data-preview-debug-panel]');
        this.modelUrl = root.dataset.modelUrl;
        this.apiStatus = root.dataset.apiStatus;
        this.bookingsUrl = root.dataset.bookingsUrl || '/admin/bookings';
        this.canDebug = root.dataset.canDebug === '1';
        this.readonlyPreview = root.dataset.previewReadonly === '1';
        this.tables = parseTables(root);
        this.selectedTableId = null;
        this.hoveredTableId = null;
        this.activeZoneKey = null;
        this.debugVisible = false;
        this.model = null;
        this.modelBounds = null;
        this.modelSize = new THREE.Vector3(120, 40, 80);
        this.defaultCamera = null;
        this.animation = null;
        this.pointerStart = null;
        this.cameraMove = null;
        this.meshRecords = [];
        this.tableObjects = new Map();
        this.clickableObjects = [];
        this.objectTableMap = new Map();
        this.modelObjectsByName = new Map();
        this.unmappedDbTables = [];
        this.unmappedModelObjects = [];
        this.missingMappedObjects = [];
        this.manualMap = TABLE_OBJECT_MAP || {};
        this.visibleMap = TABLE_VISIBLE_OBJECT_MAP || {};
        this.normalizedManualMap = new Map(
            Object.entries(this.manualMap).map(([objectName, tableLabel]) => [normalizeKey(objectName), tableLabel]),
        );

        this.raycaster = new THREE.Raycaster();
        this.pointer = new THREE.Vector2();
        this.markers = new Map();
        this.markerGroup = new THREE.Group();
        this.markerGroup.name = 'CafeGervacios_TablePins';
        this.zoneHighlight = null;
        this.selectionBox = null;

        this.init();
    }

    init() {
        if (!this.container || !this.modelUrl) return;

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0xe9e2d5);

        this.camera = new THREE.OrthographicCamera(-10, 10, 10, -10, 0.01, 5000);
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 0.46;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.container.appendChild(this.renderer.domElement);

        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.08;
        this.controls.enableRotate = false;
        this.controls.enablePan = false;
        this.controls.enableZoom = true;
        this.controls.screenSpacePanning = false;
        this.controls.minZoom = 0.85;
        this.controls.maxZoom = 1.65;

        this.addLights();
        this.scene.add(this.markerGroup);
        this.bind();
        this.resize();
        this.hidePanel();
        this.loadModel();
        this.animate();
    }

    addLights() {
        const hemi = new THREE.HemisphereLight(0xfff3df, 0x475569, 0.3);
        this.scene.add(hemi);

        const key = new THREE.DirectionalLight(0xfff1d6, 0.68);
        key.position.set(5, 18, 6);
        key.castShadow = true;
        key.shadow.mapSize.set(2048, 2048);
        key.shadow.camera.near = 1;
        key.shadow.camera.far = 60;
        key.shadow.camera.left = -18;
        key.shadow.camera.right = 18;
        key.shadow.camera.top = 18;
        key.shadow.camera.bottom = -18;
        this.scene.add(key);

        const fill = new THREE.DirectionalLight(0xb7c6d8, 0.1);
        fill.position.set(-8, 8, -6);
        this.scene.add(fill);
    }

    bind() {
        window.addEventListener('resize', () => this.resize());
        window.addEventListener('restaurant-preview:activate', () => {
            this.resize();
            this.render();
        });

        this.root.querySelector('[data-preview-action="reset"]')?.addEventListener('click', () => this.resetCamera());
        this.root.querySelector('[data-preview-action="toggle-debug"]')?.addEventListener('click', () => this.toggleDebug());

        this.root.querySelectorAll('[data-preview-preset]').forEach((button) => {
            button.addEventListener('click', () => this.focusZone(button.dataset.previewPreset));
        });

        this.renderer.domElement.addEventListener('pointerdown', (event) => {
            this.pointerStart = { x: event.clientX, y: event.clientY };
        });

        this.renderer.domElement.addEventListener('pointermove', (event) => this.handlePointerMove(event));
        this.renderer.domElement.addEventListener('pointerleave', () => {
            this.hoveredTableId = null;
            this.hideTooltip();
            this.updateMarkerState();
        });
        this.renderer.domElement.addEventListener('pointerup', (event) => this.handleClick(event));

        this.search?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.searchTable(this.search.value);
            }
        });

        this.search?.addEventListener('input', () => {
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => this.searchTable(this.search.value, false), 250);
        });
    }

    loadModel() {
        const loader = new GLTFLoader();
        loader.load(
            this.modelUrl,
            (gltf) => {
                this.model = gltf.scene;
                this.model.traverse((node) => {
                    if (node.isMesh) {
                        node.castShadow = true;
                        node.receiveShadow = true;
                        this.prepareModelNode(node);
                    }
                });

                this.scene.add(this.model);
                this.frameModel();
                this.collectModelObjects();
                this.linkModelTables();
                this.buildZoneHighlight();
                this.buildTableMarkers();
                this.loading.hidden = true;
                this.loading.classList.add('hidden');
            },
            undefined,
            (error) => {
                this.loading.hidden = true;
                this.loading.classList.add('hidden');
                this.showError('The restaurant floor view could not be loaded. Returning to Table Status.');
                window.dispatchEvent(new CustomEvent('restaurant-preview-model-failed'));
                console.error('Restaurant floor view GLTF load failed', error);
            },
        );
    }

    prepareModelNode(node) {
        this.applyCafeMaterial(node);

        if (node.name?.startsWith('Clickable_')) {
            const materials = Array.isArray(node.material) ? node.material : [node.material].filter(Boolean);
            materials.forEach((material) => {
                material.transparent = true;
                material.opacity = 0;
                material.depthWrite = false;
            });
            node.userData.cg_object_type = node.userData.cg_object_type || 'interaction_zone';
            node.userData.cg_raycast_target = true;
            node.renderOrder = -10;
        }

        if (node.name?.startsWith('Status_')) {
            node.visible = false;
        }

        if (node.name?.startsWith('Label_') || node.name?.startsWith('LabelPlate_')) {
            node.visible = false;
        }
    }

    applyCafeMaterial(node) {
        const style = cafeMaterialForNode(node.name || '');
        if (!style || !node.material) return;

        const apply = (material) => {
            const clone = material.clone();
            if (clone.color) clone.color.setHex(style.color);
            if (clone.emissive) clone.emissive.setHex(style.emissive || 0x000000);
            if (clone.roughness !== undefined) clone.roughness = style.roughness ?? 0.82;
            if (clone.metalness !== undefined) clone.metalness = style.metalness ?? 0.03;
            if (style.opacity !== undefined && style.opacity < 1) {
                clone.transparent = true;
                clone.opacity = style.opacity;
                clone.depthWrite = false;
                clone.side = THREE.DoubleSide;
            } else {
                clone.transparent = false;
                clone.opacity = 1;
                clone.depthWrite = true;
            }
            clone.needsUpdate = true;
            return clone;
        };

        node.material = Array.isArray(node.material)
            ? node.material.map((material) => apply(material))
            : apply(node.material);
    }

    frameModel() {
        const box = new THREE.Box3().setFromObject(this.model);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        const maxDim = Math.max(size.x, size.y, size.z) || 10;

        this.model.position.sub(center);
        this.modelBounds = new THREE.Box3().setFromObject(this.model);
        this.modelSize = this.modelBounds.getSize(new THREE.Vector3());

        const target = new THREE.Vector3(0, 0, 0);
        const cameraHeight = maxDim * 1.75;

        this.camera.position.set(0, cameraHeight, 0);
        this.camera.up.set(0, 0, -1);
        this.camera.near = Math.max(0.01, maxDim / 1000);
        this.camera.far = Math.max(1000, maxDim * 12);
        this.camera.lookAt(target);
        this.camera.zoom = 1;
        this.fitFixedCamera();
        this.camera.updateProjectionMatrix();

        this.controls.target.copy(target);
        this.controls.update();

        this.defaultCamera = {
            position: this.camera.position.clone(),
            target: this.controls.target.clone(),
            up: this.camera.up.clone(),
            zoom: this.camera.zoom,
        };
    }

    fitFixedCamera() {
        if (!this.camera?.isOrthographicCamera || !this.stage || !this.modelSize) return;

        const rect = this.stage.getBoundingClientRect();
        const aspect = Math.max(0.5, (rect.width || 1) / (rect.height || 1));
        const viewHeight = Math.max(
            this.modelSize.z * 1.18,
            (this.modelSize.x / aspect) * 1.18,
            12,
        );
        const viewWidth = viewHeight * aspect;

        this.camera.left = -viewWidth / 2;
        this.camera.right = viewWidth / 2;
        this.camera.top = viewHeight / 2;
        this.camera.bottom = -viewHeight / 2;
        this.camera.updateProjectionMatrix();
    }

    collectModelObjects() {
        this.meshRecords = [];
        this.clickableObjects = [];
        this.modelObjectsByName.clear();

        this.model.updateWorldMatrix(true, true);
        this.model.traverse((object) => {
            if (object.name) {
                this.modelObjectsByName.set(object.name, object);
            }
            if (!object.isMesh) return;

            const box = new THREE.Box3().setFromObject(object);
            const size = box.getSize(new THREE.Vector3());
            const center = box.getCenter(new THREE.Vector3());
            const path = this.objectPath(object);
            const name = object.name || '(unnamed)';

            this.meshRecords.push({
                object,
                name,
                path,
                box,
                size,
                center,
                mapped: false,
                tableId: null,
                tableLabel: null,
                mappingSource: null,
                tableCandidate: this.isTableCandidate(name, path),
            });

            if (this.isClickableTarget(object)) {
                this.clickableObjects.push(object);
            }
        });

        console.groupCollapsed('Restaurant Floor View GLTF object names');
        this.meshRecords.forEach((record) => {
            console.log(record.name, {
                path: record.path,
                center: vectorSnapshot(record.center),
                size: vectorSnapshot(record.size),
            });
        });
        console.groupEnd();
        console.info(`Restaurant Floor View clickable table objects: ${this.clickableObjects.map((object) => object.name).join(', ') || 'none'}`);
    }

    linkModelTables() {
        this.tableObjects.clear();
        this.objectTableMap.clear();
        this.unmappedModelObjects = [];
        this.missingMappedObjects = [];

        this.meshRecords.forEach((record) => {
            if (!this.isClickableTarget(record.object)) {
                return;
            }

            const match = this.tableLabelForObject(record.object);
            record.mapped = false;
            record.tableId = null;
            record.tableLabel = match?.label || null;
            record.mappingSource = match?.source || null;
            record.visibleObject = this.visibleObjectFor(record.object);

            if (!match) {
                this.unmappedModelObjects.push({
                    objectName: record.name,
                    tableLabel: null,
                    reason: 'no_table_mapping',
                });
                return;
            }

            const table = this.findTableByLabel(match.label);
            if (!table) {
                this.unmappedModelObjects.push({
                    objectName: record.name,
                    tableLabel: match.label,
                    reason: 'database_table_missing',
                });
                return;
            }

            record.mapped = true;
            record.tableId = table.id;
            record.tableLabel = table.label;
            this.tableObjects.set(Number(table.id), record);
            this.registerObjectTree(record.object, Number(table.id));
        });

        Object.entries(this.manualMap).forEach(([objectName, tableLabel]) => {
            const exists = this.meshRecords.some((record) => (
                record.name === objectName
                || normalizeKey(record.name) === normalizeKey(objectName)
                || record.path.split(' > ').includes(objectName)
            ));

            if (!exists) {
                this.missingMappedObjects.push({ objectName, tableLabel });
            }
        });

        const mappedLabels = new Set(
            Array.from(this.tableObjects.values()).map((record) => normalizeKey(record.tableLabel)),
        );
        this.unmappedDbTables = this.tables.filter((table) => !mappedLabels.has(normalizeKey(table.label)));
        this.updateMappingSummary();
        this.renderDebugPanel();
    }

    tableLabelForObject(object) {
        let node = object;

        while (node) {
            const name = node.name || '';
            if (this.manualMap[name]) {
                return { label: this.manualMap[name], source: 'manual' };
            }

            const normalized = normalizeKey(name);
            if (this.normalizedManualMap.has(normalized)) {
                return { label: this.normalizedManualMap.get(normalized), source: 'manual_normalized' };
            }

            const directTable = this.findTableByLabel(name);
            if (directTable) {
                return { label: directTable.label, source: 'direct_name' };
            }

            const tableNumber = name.match(/\bT[\s_-]*0*(\d+)\b/i) || name.match(/\btable[\s_-]*0*(\d+)\b/i);
            if (tableNumber) {
                return { label: `T${Number(tableNumber[1])}`, source: 'name_pattern' };
            }

            node = node.parent;
        }

        return null;
    }

    registerObjectTree(object, tableId) {
        object.traverse((node) => {
            this.objectTableMap.set(node.uuid, Number(tableId));
        });
    }

    objectPath(object) {
        const parts = [];
        let node = object;

        while (node) {
            if (node.name) parts.unshift(node.name);
            node = node.parent;
        }

        return parts.join(' > ') || '(unnamed)';
    }

    isTableCandidate(name, path) {
        const haystack = `${name || ''} ${path || ''}`;

        return TABLE_OBJECT_NAME_HINTS.some((pattern) => pattern.test(haystack));
    }

    isClickableTarget(object) {
        return Boolean(
            object?.isMesh
            && object.name?.startsWith('Clickable_')
            && (object.userData?.cg_raycast_target === true || this.manualMap[object.name]),
        );
    }

    visibleObjectFor(clickableObject) {
        const visibleName = this.visibleMap[clickableObject.name];

        return visibleName ? this.modelObjectsByName.get(visibleName) || clickableObject : clickableObject;
    }

    findTableByLabel(label) {
        const key = normalizeKey(label);

        return this.tables.find((table) => normalizeKey(table.label) === key) || null;
    }

    buildZoneHighlight() {
        if (!this.modelBounds) return;

        this.zoneHighlight = new THREE.Mesh(
            new THREE.PlaneGeometry(1, 1),
            new THREE.MeshBasicMaterial({
                color: 0xffffff,
                transparent: true,
                opacity: 0.14,
                side: THREE.DoubleSide,
                depthWrite: false,
            }),
        );
        this.zoneHighlight.rotation.x = -Math.PI / 2;
        this.zoneHighlight.position.y = this.modelBounds.min.y + Math.max(0.08, this.modelSize.y * 0.004);
        this.zoneHighlight.visible = false;
        this.scene.add(this.zoneHighlight);
    }

    buildTableMarkers() {
        this.markerGroup.clear();
        this.markers.clear();

        this.tables.forEach((table) => {
            const record = this.tableObjects.get(Number(table.id));
            if (!record) return;

            const marker = this.createTableMarker(table, record);
            this.markerGroup.add(marker);
            this.markers.set(table.id, marker);
        });

        this.updateMarkerState();
        this.updateSelectionBox();
    }

    createTableMarker(table, record) {
        const position = this.positionForTableObject(record);
        const color = STATUS_COLORS[normalizeStatus(table.status)] || STATUS_COLORS.available;
        const group = new THREE.Group();
        group.name = `TablePin_${table.label}`;
        group.position.copy(position);
        group.userData = {
            tableId: table.id,
            tableLabel: table.label,
            markerType: 'table',
            objectName: record.name,
        };

        const radius = Math.max(0.48, Math.min(0.92, Math.sqrt(Math.max(1, table.capacity)) * 0.26));
        const ringMaterial = new THREE.MeshBasicMaterial({
            color,
            transparent: true,
            opacity: 0.52,
            depthWrite: false,
        });
        const ring = new THREE.Mesh(new THREE.TorusGeometry(radius, 0.028, 10, 42), ringMaterial);
        ring.rotation.x = Math.PI / 2;
        ring.name = `${table.label}_status_ring`;
        ring.userData = group.userData;
        group.add(ring);

        const glow = new THREE.Mesh(
            new THREE.CircleGeometry(radius * 1.65, 42),
            new THREE.MeshBasicMaterial({
                color,
                transparent: true,
                opacity: 0.1,
                depthWrite: false,
                side: THREE.DoubleSide,
            }),
        );
        glow.rotation.x = -Math.PI / 2;
        glow.position.y = 0.02;
        glow.name = `${table.label}_status_glow`;
        glow.userData = group.userData;
        group.add(glow);

        const label = this.createLabelSprite(table.label, statusLabel(table.status), color);
        label.position.set(0, 0.72, 0);
        label.visible = true;
        label.userData = group.userData;
        group.add(label);

        return group;
    }

    positionForTableObject(record) {
        if (!record?.object) {
            return new THREE.Vector3(0, 0, 0);
        }

        const box = new THREE.Box3().setFromObject(record.visibleObject || record.object);
        const center = box.getCenter(new THREE.Vector3());
        const heightOffset = Math.max(0.12, this.modelSize.y * 0.008);

        return new THREE.Vector3(center.x, box.max.y + heightOffset, center.z);
    }

    createLabelSprite(text, subtext, color) {
        const canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 116;
        const context = canvas.getContext('2d');

        context.shadowColor = 'rgba(15, 23, 42, 0.22)';
        context.shadowBlur = 10;
        context.shadowOffsetY = 4;
        context.fillStyle = 'rgba(255, 250, 239, 0.96)';
        this.roundRect(context, 32, 20, 192, 76, 18);
        context.fill();
        context.shadowColor = 'transparent';
        context.shadowBlur = 0;
        context.shadowOffsetY = 0;
        context.strokeStyle = `#${color.toString(16).padStart(6, '0')}`;
        context.lineWidth = 4;
        this.roundRect(context, 32, 20, 192, 76, 18);
        context.stroke();
        context.font = '800 26px Arial';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillStyle = '#1f2937';
        context.fillText(text, 128, 45);
        context.font = '700 18px Arial';
        context.fillStyle = '#475569';
        context.fillText(subtext, 128, 72);

        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;
        const material = new THREE.SpriteMaterial({
            map: texture,
            transparent: true,
            depthTest: false,
        });
        const sprite = new THREE.Sprite(material);
        sprite.name = `${text}_status_label`;
        sprite.scale.set(1.92, 0.87, 1);

        return sprite;
    }

    roundRect(context, x, y, width, height, radius) {
        context.beginPath();
        context.moveTo(x + radius, y);
        context.lineTo(x + width - radius, y);
        context.quadraticCurveTo(x + width, y, x + width, y + radius);
        context.lineTo(x + width, y + height - radius);
        context.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        context.lineTo(x + radius, y + height);
        context.quadraticCurveTo(x, y + height, x, y + height - radius);
        context.lineTo(x, y + radius);
        context.quadraticCurveTo(x, y, x + radius, y);
        context.closePath();
    }

    positionForTable(table) {
        if (!this.modelBounds) {
            return new THREE.Vector3(0, 0, 0);
        }

        const bounds = this.modelBounds;
        const size = this.modelSize;
        const usableX = Math.max(16, size.x * 0.78);
        const usableZ = Math.max(12, size.z * 0.78);
        const xRatio = Math.min(1, Math.max(0, (Number(table.x || 0) + (Number(table.width || 0) / 2)) / PLANNER_WIDTH));
        const yRatio = Math.min(1, Math.max(0, (Number(table.y || 0) + (Number(table.height || 0) / 2)) / PLANNER_HEIGHT));
        const x = bounds.min.x + ((size.x - usableX) / 2) + (usableX * xRatio);
        const z = bounds.min.z + ((size.z - usableZ) / 2) + (usableZ * yRatio);
        const y = bounds.min.y + Math.max(0.75, size.y * 0.024);

        return new THREE.Vector3(x, y, z);
    }

    vectorForZone(zone) {
        return this.positionForTable({
            x: zone.x + (zone.width / 2),
            y: zone.y + (zone.height / 2),
            width: 0,
            height: 0,
        });
    }

    sizeForZone(zone) {
        const xScale = (this.modelSize.x * 0.78) / PLANNER_WIDTH;
        const zScale = (this.modelSize.z * 0.78) / PLANNER_HEIGHT;

        return {
            width: Math.max(4, zone.width * xScale),
            height: Math.max(4, zone.height * zScale),
        };
    }

    handlePointerMove(event) {
        const hit = this.tableHitFromEvent(event);
        const tableId = hit?.tableId || null;

        if (tableId !== this.hoveredTableId) {
            this.hoveredTableId = tableId;
            this.updateMarkerState();
        }

        if (tableId) {
            const table = this.findTable(tableId);
            if (table) this.showTooltip(event, table);
        } else {
            this.hideTooltip();
        }
    }

    handleClick(event) {
        if (this.pointerStart) {
            const moved = Math.hypot(event.clientX - this.pointerStart.x, event.clientY - this.pointerStart.y);
            this.pointerStart = null;
            if (moved > 6) return;
        }

        const tableHit = this.tableHitFromEvent(event);
        if (tableHit?.tableId) {
            this.selectTable(tableHit.tableId, true);
            return;
        }

        const objectHit = this.modelHitFromEvent(event);
        if (!objectHit) return;

        const mappedLabel = this.tableLabelForObject(objectHit.object);
        if (mappedLabel && !this.findTableByLabel(mappedLabel.label)) {
            this.showMessage(`This 3D table is not linked to a system table yet. Object ${objectHit.object.name || '(unnamed)'} maps to ${mappedLabel.label}, but no matching database table was found.`);
            return;
        }

        if (this.isTableCandidate(objectHit.object.name, this.objectPath(objectHit.object))) {
            this.showMessage(`This 3D object ${objectHit.object.name || '(unnamed)'} is not linked to a system table yet.`);
            return;
        }

        return;
    }

    tableHitFromEvent(event) {
        this.setPointerFromEvent(event);

        const markerHit = this.raycaster.intersectObjects(this.markerGroup.children, true)[0] || null;
        const markerTableId = markerHit ? this.tableIdFromObject(markerHit.object) : null;
        if (markerTableId) {
            return { tableId: markerTableId, object: markerHit.object, source: 'marker' };
        }

        const modelHits = this.clickableObjects.length
            ? this.raycaster.intersectObjects(this.clickableObjects, true)
            : [];
        const tableHit = modelHits.find((hit) => this.tableIdFromObject(hit.object));
        if (!tableHit) return null;

        return {
            tableId: this.tableIdFromObject(tableHit.object),
            object: tableHit.object,
            source: 'model',
        };
    }

    modelHitFromEvent(event) {
        this.setPointerFromEvent(event);

        const modelHits = this.clickableObjects.length
            ? this.raycaster.intersectObjects(this.clickableObjects, true)
            : [];
        return modelHits[0] || null;
    }

    setPointerFromEvent(event) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        this.pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.pointer.y = -(((event.clientY - rect.top) / rect.height) * 2 - 1);
        this.raycaster.setFromCamera(this.pointer, this.camera);
    }

    tableIdFromObject(object) {
        let node = object;
        while (node) {
            if (node.userData?.markerType === 'table') {
                return Number(node.userData.tableId);
            }
            if (this.objectTableMap.has(node.uuid)) {
                return Number(this.objectTableMap.get(node.uuid));
            }
            node = node.parent;
        }

        return null;
    }

    selectTable(tableId, focus = false) {
        const table = this.findTable(tableId);
        if (!table) {
            this.selectedTableId = null;
            this.showMessage('This 3D table is not linked to a system table yet.');
            return;
        }

        if (!this.tableObjects.has(Number(table.id))) {
            this.showUnmappedTableMessage(table);
            return;
        }

        this.selectedTableId = table.id;
        this.activeZoneKey = zoneForTable(table).key;
        this.updateZoneHighlight();
        this.updateMarkerState();
        this.updateSelectionBox();
        this.renderPanel();
        if (focus) this.focusTable(table.id);
    }

    findTable(tableId) {
        return this.tables.find((table) => Number(table.id) === Number(tableId)) || null;
    }

    updateMarkerState() {
        this.markers.forEach((marker, id) => {
            const selected = Number(id) === Number(this.selectedTableId);
            const hovered = Number(id) === Number(this.hoveredTableId);
            const dimmed = this.selectedTableId !== null && !selected;
            const scale = selected ? 1.42 : (hovered ? 1.18 : (dimmed ? 0.82 : 1));
            const opacity = selected ? 1 : (hovered ? 0.9 : (dimmed ? 0.24 : 0.56));

            marker.scale.setScalar(scale);
            marker.children.forEach((child) => {
                if (child.isSprite) {
                    child.visible = true;
                }
                if (child.material) {
                    child.material.opacity = child.isSprite
                        ? (selected ? 1 : (hovered ? 0.92 : (dimmed ? 0.3 : 0.7)))
                        : (child.name.includes('status_glow') ? Math.max(0.04, opacity * 0.16) : opacity);
                    if (child.material.emissiveIntensity !== undefined) {
                        child.material.emissiveIntensity = selected ? 0.72 : (hovered ? 0.46 : 0.24);
                    }
                }
            });
        });

        this.updateSelectionBox();
    }

    updateSelectionBox() {
        if (this.selectionBox) {
            this.scene.remove(this.selectionBox);
            this.selectionBox.geometry?.dispose?.();
            this.selectionBox.material?.dispose?.();
            this.selectionBox = null;
        }

        if (!this.selectedTableId) return;

        const record = this.tableObjects.get(Number(this.selectedTableId));
        const table = this.findTable(this.selectedTableId);
        if (!record?.object || !table) return;

        const color = STATUS_COLORS[normalizeStatus(table.status)] || 0xffffff;
        this.selectionBox = new THREE.BoxHelper(record.visibleObject || record.object, color);
        this.selectionBox.name = `SelectedTable_${table.label}`;
        this.selectionBox.material.depthTest = false;
        this.selectionBox.material.transparent = true;
        this.selectionBox.material.opacity = 0.95;
        this.scene.add(this.selectionBox);
    }

    updateMappingSummary() {
        if (!this.mappingSummary) return;

        const mappedCount = this.tableObjects.size;
        const floorZoneCount = Object.keys(this.manualMap).length || this.clickableObjects.length;
        const warningCount = this.unmappedDbTables.length + this.unmappedModelObjects.length + this.missingMappedObjects.length;
        const tone = warningCount > 0 ? 'amber' : 'emerald';
        const topUnmappedTables = this.unmappedDbTables.slice(0, 5);
        const extraTables = this.unmappedDbTables.length - topUnmappedTables.length;

        this.mappingSummary.innerHTML = `
            <div class="rp-mapping-head">
                <span class="rp-mapping-dot rp-mapping-dot-${tone}"></span>
                <span>${mappedCount}/${floorZoneCount} floor click zones linked</span>
            </div>
            ${topUnmappedTables.length ? `
                <div class="mt-2 space-y-1">
                    ${topUnmappedTables.map((table) => `
                        <div class="rp-mapping-warning">Table ${this.escape(table.label)} is not linked to a 3D object yet.</div>
                    `).join('')}
                    ${extraTables > 0 ? `<div class="rp-mapping-warning">+${extraTables} more unmapped system tables</div>` : ''}
                </div>
            ` : ''}
            ${this.unmappedModelObjects.slice(0, 3).map((item) => `
                <div class="rp-mapping-warning mt-1">3D object ${this.escape(item.objectName)} is not linked to a system table yet.</div>
            `).join('')}
            ${this.missingMappedObjects.slice(0, 3).map((item) => `
                <div class="rp-mapping-warning mt-1">Mapping object ${this.escape(item.objectName)} was not found in the GLTF model.</div>
            `).join('')}
        `;
        this.mappingSummary.classList.remove('hidden');
        this.mappingSummary.hidden = false;
    }

    toggleDebug() {
        if (!this.canDebug || !this.debugPanel) return;

        this.debugVisible = !this.debugVisible;
        const button = this.root.querySelector('[data-preview-action="toggle-debug"]');
        if (button) {
            button.classList.toggle('is-active', this.debugVisible);
            button.innerHTML = this.debugVisible
                ? '<i class="fa-solid fa-bug text-[11px]" aria-hidden="true"></i> Hide Mapping Debug'
                : '<i class="fa-solid fa-bug text-[11px]" aria-hidden="true"></i> Show Mapping Debug';
        }

        this.renderDebugPanel();
    }

    renderDebugPanel() {
        if (!this.debugPanel || !this.canDebug) return;

        if (!this.debugVisible) {
            this.debugPanel.classList.add('hidden');
            this.debugPanel.hidden = true;
            return;
        }

        const rows = this.meshRecords.map((record) => {
            const status = record.mapped
                ? `Mapped to ${record.tableLabel} (#${record.tableId})`
                : (record.tableLabel ? `No DB match for ${record.tableLabel}` : 'Unmapped');

            return `
                <tr>
                    <td>${this.escape(record.name)}</td>
                    <td>${this.escape(record.path)}</td>
                    <td>${this.escape(status)}</td>
                </tr>
            `;
        }).join('');

        this.debugPanel.innerHTML = `
            <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-2">
                <div>
                    <p class="text-xs font-black uppercase tracking-wide text-white">Mapping Debug</p>
                    <p class="mt-0.5 text-[11px] text-white/60">Browser console also logs every GLTF mesh name.</p>
                </div>
                <button type="button" class="rp-debug-close" data-preview-debug-close aria-label="Close mapping debug">
                    <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
                </button>
            </div>
            <div class="mt-2 max-h-56 overflow-auto">
                <table class="rp-debug-table">
                    <thead>
                        <tr>
                            <th>Object</th>
                            <th>Path</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>${rows || '<tr><td colspan="3">No mesh objects found.</td></tr>'}</tbody>
                </table>
            </div>
        `;
        this.debugPanel.querySelector('[data-preview-debug-close]')?.addEventListener('click', () => this.toggleDebug());
        this.debugPanel.classList.remove('hidden');
        this.debugPanel.hidden = false;
    }

    showTooltip(event, table) {
        if (!this.tooltip) return;

        this.tooltip.innerHTML = `
            <div class="font-black">${this.escape(table.label)}</div>
            <div class="mt-1 text-white/75">${statusLabel(table.status)}</div>
        `;
        this.tooltip.style.left = `${event.offsetX + 16}px`;
        this.tooltip.style.top = `${event.offsetY + 16}px`;
        this.tooltip.classList.remove('hidden');
        this.tooltip.hidden = false;
    }

    hideTooltip() {
        if (!this.tooltip) return;
        this.tooltip.classList.add('hidden');
        this.tooltip.hidden = true;
    }

    focusTable(tableId) {
        const marker = this.markers.get(Number(tableId));
        if (!marker) return;

        marker.scale.setScalar(1.32);
    }

    focusZone(key) {
        const zone = ZONES.find((candidate) => candidate.key === key);
        if (!zone || !this.modelBounds) return;

        this.activeZoneKey = zone.key;
        this.selectedTableId = null;
        this.hidePanel();
        this.updatePresetButtons();
        this.updateZoneHighlight();
        this.updateMarkerState();

        const target = this.vectorForZone(zone);
        const size = Math.max(this.modelSize.x, this.modelSize.z, 30);
        const distance = Math.max(24, size * 0.28);
        const angle = {
            entrance: new THREE.Vector3(0, distance * 0.58, distance),
            counter: new THREE.Vector3(-distance * 0.8, distance * 0.55, distance * 0.35),
            'dining-a': new THREE.Vector3(distance * 0.65, distance * 0.55, distance * 0.65),
            'dining-b': new THREE.Vector3(distance * 0.6, distance * 0.52, -distance * 0.72),
            window: new THREE.Vector3(distance * 0.85, distance * 0.52, distance * 0.2),
            group: new THREE.Vector3(distance * 0.62, distance * 0.5, -distance * 0.58),
        }[zone.key] || new THREE.Vector3(distance, distance * 0.56, distance);

        this.moveCameraTo(target.clone().add(angle), target, 780);
    }

    updateZoneHighlight() {
        const zone = ZONES.find((candidate) => candidate.key === this.activeZoneKey);
        if (!this.zoneHighlight || !zone) {
            if (this.zoneHighlight) this.zoneHighlight.visible = false;
            this.hideZoneChip();
            return;
        }

        const target = this.vectorForZone(zone);
        const size = this.sizeForZone(zone);
        this.zoneHighlight.scale.set(size.width, size.height, 1);
        this.zoneHighlight.position.x = target.x;
        this.zoneHighlight.position.z = target.z;
        this.zoneHighlight.visible = true;

        if (this.zoneChip) {
            this.zoneChip.textContent = zone.name;
            this.zoneChip.classList.remove('hidden');
            this.zoneChip.hidden = false;
        }
        this.updatePresetButtons();
    }

    updatePresetButtons() {
        this.root.querySelectorAll('[data-preview-preset]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.previewPreset === this.activeZoneKey);
        });
    }

    hideZoneChip() {
        if (!this.zoneChip) return;
        this.zoneChip.classList.add('hidden');
        this.zoneChip.hidden = true;
        this.updatePresetButtons();
    }

    moveCameraTo(position, target, duration = 650) {
        this.cameraMove = {
            start: performance.now(),
            duration,
            fromPosition: this.camera.position.clone(),
            toPosition: position.clone(),
            fromTarget: this.controls.target.clone(),
            toTarget: target.clone(),
        };
    }

    updateCameraMove() {
        if (!this.cameraMove) return;

        const elapsed = performance.now() - this.cameraMove.start;
        const progress = Math.min(1, elapsed / this.cameraMove.duration);
        const eased = easeInOut(progress);

        this.camera.position.lerpVectors(this.cameraMove.fromPosition, this.cameraMove.toPosition, eased);
        this.controls.target.lerpVectors(this.cameraMove.fromTarget, this.cameraMove.toTarget, eased);
        this.camera.updateProjectionMatrix();
        this.controls.update();

        if (progress >= 1) {
            this.cameraMove = null;
        }
    }

    searchTable(value, showMissing = true) {
        const query = String(value || '').trim().toLowerCase();
        if (query === '') return;

        const table = this.tables.find((row) => {
            const booking = row.booking || {};
            return [
                row.label,
                `#${row.id}`,
                booking.ref,
                booking.guest,
            ].filter(Boolean).some((part) => String(part).toLowerCase().includes(query));
        });

        if (!table) {
            if (showMissing) this.showMessage('No linked table or booking matched that search.');
            return;
        }

        if (!this.tableObjects.has(Number(table.id))) {
            if (showMissing) this.showUnmappedTableMessage(table);
            return;
        }

        this.selectTable(table.id, true);
    }

    renderPanel() {
        const table = this.findTable(this.selectedTableId);
        if (!table) {
            this.hidePanel();
            return;
        }

        const booking = table.booking;
        const bookingRef = booking?.ref || 'None';
        const guestName = booking?.guest || 'No assigned guest';

        this.panel.innerHTML = `
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-950">${this.escape(table.label)}</h3>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">${Number(table.capacity)} capacity</p>
                </div>
                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" data-preview-close aria-label="Close table details">
                    <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
                </button>
            </div>

            <div class="mt-3 space-y-3">
                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-black uppercase tracking-wide ${statusClass(table.status)}">
                    ${statusLabel(table.status)}
                </span>

                <div class="grid gap-2 text-sm">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Assigned booking</p>
                        <p class="mt-1 font-semibold text-slate-800">${this.escape(bookingRef)}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Guest name</p>
                        <p class="mt-1 font-semibold text-slate-800">${this.escape(guestName)}</p>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Actions</p>
                    <div class="grid gap-2">
                        ${this.actionButtons(table)}
                    </div>
                </div>
            </div>
        `;

        this.panel.querySelector('[data-preview-close]')?.addEventListener('click', () => {
            this.selectedTableId = null;
            this.hidePanel();
            this.updateMarkerState();
        });

        this.panel.querySelectorAll('[data-preview-status]').forEach((button) => {
            button.addEventListener('click', () => this.updateStatus(button.dataset.previewStatus));
        });

        this.showPanel();
    }

    actionButtons(table) {
        if (this.readonlyPreview) {
            const floorUrl = new URL(this.bookingsUrl, window.location.origin);
            floorUrl.pathname = '/admin/tables';
            floorUrl.search = '';
            return `
                <a href="${floorUrl.toString()}" class="rp-panel-action border-slate-900 bg-slate-900 text-white hover:bg-slate-800">Open Floor Plan</a>
                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold leading-relaxed text-slate-600">
                    This 3D tab is a visual reference only. Status changes are handled from Floor Plan or Table Status.
                </p>
            `;
        }

        const status = normalizeStatus(table.status);
        const canSeat = status === 'reserved';
        const canFree = status === 'reserved' || status === 'cleaning';
        const canOccupy = status === 'available';
        const canClean = status === 'occupied';
        const bookingUrl = this.bookingUrlForTable(table);

        return `
            ${table.booking ? `<a href="${bookingUrl}" class="rp-panel-action">View Booking</a>` : ''}
            ${this.statusButton('occupied', 'Seat Guest', canSeat)}
            ${this.statusButton('available', 'Mark Free', canFree)}
            ${this.statusButton('occupied', 'Mark Occupied', canOccupy)}
            ${this.statusButton('cleaning', 'Mark Cleaning', canClean)}
        `;
    }

    bookingUrlForTable(table) {
        const url = new URL(this.bookingsUrl, window.location.origin);
        url.searchParams.set('table_id', table.id);
        if (table.booking?.ref) {
            url.searchParams.set('search', table.booking.ref);
        }

        return url.toString();
    }

    statusButton(status, label, enabled) {
        const colorClass = {
            available: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            reserved: 'border-amber-200 bg-amber-50 text-amber-900',
            occupied: 'border-rose-200 bg-rose-50 text-rose-800',
            cleaning: 'border-cyan-200 bg-cyan-50 text-cyan-800',
        }[status] || '';

        return `
            <button type="button" class="rp-panel-action ${enabled ? colorClass : ''}" data-preview-status="${status}" ${enabled ? '' : 'disabled'}>
                ${label}
            </button>
        `;
    }

    showPanel() {
        this.panel.classList.remove('hidden');
        this.panel.hidden = false;
    }

    hidePanel() {
        this.panel.classList.add('hidden');
        this.panel.hidden = true;
        this.panel.innerHTML = '';
    }

    showMessage(message) {
        this.selectedTableId = null;
        this.updateMarkerState();
        this.panel.innerHTML = `
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                    <h3 class="text-sm font-semibold text-slate-950">Restaurant Floor View</h3>
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" data-preview-close aria-label="Close message">
                        <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="text-sm leading-relaxed text-slate-600">${this.escape(message)}</p>
            </div>
        `;
        this.panel.querySelector('[data-preview-close]')?.addEventListener('click', () => this.hidePanel());
        this.showPanel();
    }

    showUnmappedTableMessage(table) {
        this.selectedTableId = null;
        this.updateMarkerState();
        this.showMessage(`Table ${table.label} is not linked to a 3D object yet.`);
    }

    async updateStatus(status) {
        if (this.readonlyPreview) {
            notify('info', 'Open Floor Plan or Table Status to update table status.');
            return;
        }

        const table = this.findTable(this.selectedTableId);
        if (!this.selectedTableId || !table || !status) {
            notify('error', 'Select a linked 3D table before using actions.');
            return;
        }

        if (!this.tableObjects.has(Number(table.id))) {
            this.showUnmappedTableMessage(table);
            return;
        }

        const buttons = this.panel.querySelectorAll('[data-preview-status]');
        buttons.forEach((button) => {
            button.disabled = true;
        });

        try {
            const response = await axios.post(this.apiStatus, {
                table_id: table.id,
                status,
            });

            this.setTablesFromResponse(response);
            this.selectedTableId = table.id;
            this.buildTableMarkers();
            this.renderPanel();
            this.focusTable(table.id);
            window.Livewire?.dispatch?.('tables-refresh');
            window.dispatchEvent(new CustomEvent('tables-refresh'));
            notify('success', `Table marked ${statusLabel(status).toLowerCase()}`);
        } catch (error) {
            notify('error', firstError(error, 'Could not update table status'));
            buttons.forEach((button) => {
                button.disabled = false;
            });
        }
    }

    setTablesFromResponse(response) {
        const rows = response?.data?.planner?.plannerTables || [];
        this.tables = rows.map((table) => normalizeTable(table));
        this.linkModelTables();
    }

    resetCamera() {
        if (!this.defaultCamera) return;

        this.selectedTableId = null;
        this.activeZoneKey = null;
        this.cameraMove = null;
        this.hidePanel();
        this.hideZoneChip();
        this.updateZoneHighlight();
        this.updateMarkerState();
        this.camera.position.copy(this.defaultCamera.position);
        this.camera.up.copy(this.defaultCamera.up);
        this.camera.zoom = this.defaultCamera.zoom;
        this.camera.updateProjectionMatrix();
        this.controls.target.copy(this.defaultCamera.target);
        this.controls.update();
        this.render();
    }

    resize() {
        const rect = this.stage.getBoundingClientRect();
        const width = Math.max(320, Math.floor(rect.width));
        const height = Math.max(360, Math.floor(rect.height));

        if (this.camera.isPerspectiveCamera) {
            this.camera.aspect = width / height;
            this.camera.updateProjectionMatrix();
        } else {
            this.fitFixedCamera();
        }
        this.renderer.setSize(width, height, false);
        this.render();
    }

    animate() {
        this.animation = window.requestAnimationFrame(() => this.animate());
        this.updateCameraMove();
        this.controls.update();
        this.render();
    }

    render() {
        this.renderer.render(this.scene, this.camera);
    }

    showError(message) {
        if (!this.error) return;
        this.error.textContent = message;
        this.error.classList.remove('hidden');
    }

    escape(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

function initRestaurantPreviews() {
    document.querySelectorAll('[data-restaurant-preview]').forEach((root) => {
        if (root.dataset.restaurantPreviewReady === '1') return;
        root.dataset.restaurantPreviewReady = '1';
        new RestaurantPreview(root);
    });
}

document.addEventListener('DOMContentLoaded', initRestaurantPreviews);
document.addEventListener('livewire:navigated', initRestaurantPreviews);
