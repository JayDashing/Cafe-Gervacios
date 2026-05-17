{{--
Visual overrides for seating-map-inner — loads after inline styles.
--}}
<style>
    [data-seating-layout],
    [data-seating-layout] .seating-canvas-wrap,
    [data-seating-layout] .seating-badge-card,
    [data-seating-layout] .seating-status-legend,
    [data-seating-layout] #seating-selection-bar,
    [data-seating-layout] #group-table-modal,
    [data-seating-layout] #seat-modal,
    [data-seating-layout] .seating-merge-tag,
    [data-seating-layout] .seating-map-stage,
    [data-seating-layout] .seating-admin-toolbar {
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    /* Canvas — subtle dot grid + light border */
    .seating-canvas-wrap {
        background-color: #fafbfc;
        background-image: radial-gradient(circle, #cbd5e1 0.65px, transparent 0.65px);
        background-size: 14px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    /* Legend */
    .seating-status-legend--in-card {
        background: #fff !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }

    /* Table badge — top accent only (no left rail) */
    .seating-badge-card {
        border: 1px solid #e2e8f0;
        border-top-width: 2px;
        border-radius: 10px;
        box-shadow:
            0 4px 6px -1px rgb(15 23 42 / 0.08),
            0 2px 4px -2px rgb(15 23 42 / 0.06);
        min-width: 6rem;
        max-width: 10rem;
    }

    .seating-badge--free {
        border-top-color: #22c55e;
    }

    .seating-badge--reserved {
        border-top-color: #d97706;
    }

    .seating-badge--occupied {
        border-top-color: #e11d48;
    }

    .seating-badge-label {
        font-size: 12px;
        font-weight: 700;
        color: #0f172a;
    }

    .seating-badge-status {
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
    }

    .seating-badge--reserved .seating-badge-status {
        color: #9a3412;
    }

    .seating-badge--occupied .seating-badge-status {
        color: #9f1239;
    }

    .seating-badge-cap {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
    }

    .seating-merge-tag {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.06em;
        border-radius: 999px;
        padding: 2px 8px;
        margin-top: 3px;
        box-shadow: none;
    }

    /* Merged region — lighter stroke */
    .seating-group-shell__fill {
        border: 1px dashed rgba(59, 130, 246, 0.22);
        background: rgba(59, 130, 246, 0.02);
        border-radius: 12px;
        box-shadow: none;
    }

    /* Seat dots — thinner ring */
    .seating-seat-dot::after {
        width: 20px;
        height: 20px;
        border: 1px solid rgba(255, 255, 255, 0.95);
        box-shadow:
            0 2px 5px rgba(26, 34, 50, 0.16),
            0 0 0 1px rgba(26, 34, 50, 0.05);
    }

    .seating-seat-dot.available::after {
        background: #22c55e;
    }

    .seating-seat-dot.reserved::after {
        background: #f59e0b;
    }

    .seating-seat-dot.occupied::after {
        background: #f43f5e;
    }

    .seating-seat-dot--grouped::after {
        width: 5px;
        height: 14px;
        border-radius: 999px;
        border-width: 1px;
    }

    .seating-seat-dot.is-marquee-selected::after {
        box-shadow:
            0 0 0 3px rgba(245, 158, 11, 0.95),
            0 2px 8px rgba(26, 34, 50, 0.18);
    }

    .seating-seat-dot.is-waitlist-seat-at::after {
        box-shadow:
            0 0 0 3px rgba(26, 34, 50, 0.85),
            0 2px 8px rgba(26, 34, 50, 0.2);
    }

    .seating-seat-dot.is-table-ops-selected::after {
        box-shadow:
            0 0 0 3px rgba(26, 34, 50, 0.9),
            0 2px 8px rgba(26, 34, 50, 0.2),
            0 0 0 1px rgba(255, 255, 255, 0.6) inset;
    }

    .seating-map-stage.is-selection-mode {
        box-shadow: inset 0 0 0 2px rgba(37, 99, 235, 0.35);
    }

    .seating-marquee-rect {
        border: 2px dashed rgba(37, 99, 235, 0.65);
        background: rgba(37, 99, 235, 0.1);
        box-shadow: none;
    }

    #seating-selection-bar {
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #0f172a;
    }

    #seating-group-open {
        background: #0f172a;
        color: #fff;
    }

    #seating-group-open:hover:not(:disabled) {
        background: #1e293b;
    }

    #seating-clear-selection {
        border-color: #cbd5e1;
        color: #0f172a;
    }

    #seating-clear-selection:hover {
        background: #f1f5f9;
    }

    #group-table-modal .tc-ios-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 8px 32px rgba(26, 34, 50, 0.14);
    }

    #seat-modal {
        display: none;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    #seat-modal.open {
        display: flex;
        pointer-events: auto;
    }

    #seat-modal .seat-modal__panel {
        background: #fff;
        pointer-events: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: rgba(15, 23, 42, 0.3) transparent;
    }

    #seat-modal.seat-modal--editorial .seating-s-opts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        padding: 0;
        margin-bottom: 4px;
        border: 0;
        border-radius: 0;
        background: transparent;
    }

    .seating-s-opt {
        display: flex;
        min-height: 48px;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border-radius: 8px;
        border: 1.5px solid #e2e8f0;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        background: #fff;
        transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s;
    }

    .seating-s-dot {
        display: inline-flex;
        height: 9px;
        width: 9px;
        border-radius: 999px;
        background: currentColor;
    }

    .seating-s-opt.av {
        color: #15803d;
    }

    .seating-s-opt.av.active {
        background: #ecfdf5;
        border-color: #22c55e;
        color: #14532d;
        box-shadow: 0 1px 2px rgb(22 101 52 / 0.1);
    }

    .seating-s-opt.re {
        color: #a16207;
    }

    .seating-s-opt.re.active {
        background: #fef9c3;
        border-color: #eab308;
        color: #713f12;
        box-shadow: 0 1px 2px rgb(161 98 7 / 0.15);
    }

    .seating-s-opt.oc {
        color: #b91c1c;
    }

    .seating-s-opt.oc.active {
        background: #fee2e2;
        border-color: #ef4444;
        color: #7f1d1d;
        box-shadow: 0 1px 2px rgb(185 28 28 / 0.12);
    }

    #seating-placement-hint,
    #seating-selection-mode-hint {
        border-radius: 10px;
    }
</style>
