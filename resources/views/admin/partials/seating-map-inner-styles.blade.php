<style>
    [data-seating-layout] {
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    .seating-tool-hint {
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        margin-bottom: 8px;
    }

    /* Admin toolbar — canvas-style SaaS */
    .seating-admin-toolbar {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 12px;
    }

    .seating-toolbar-label {
        font-size: 13px;
        font-weight: 500;
        color: #475569;
    }

    .seating-floorplan-form {
        margin-bottom: 16px;
    }

    .seating-upload-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px 14px;
    }

    .seating-upload-trigger {
        position: relative;
        cursor: pointer;
    }

    .seating-upload-trigger input[type='file'] {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .seating-upload-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        transition: background 0.15s, border-color 0.15s;
    }

    .seating-upload-trigger:hover .seating-upload-btn {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .seating-save-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 18px;
        border-radius: 12px;
        border: none;
        background: #0f172a;
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        cursor: pointer;
        transition: background 0.15s;
    }

    .seating-save-btn:hover {
        background: #1e293b;
    }

    .seating-path-hint {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        color: #94a3b8;
        cursor: help;
    }

    .seating-path-hint:hover {
        color: #64748b;
        background: #f1f5f9;
    }

    .seating-toolbox-row {
        margin-bottom: 0;
    }

    .seating-btn-group {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: stretch;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        overflow: hidden;
    }

    .seating-tool-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 14px;
        border: none;
        border-right: 1px solid #e2e8f0;
        background: #fff;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
    }

    .seating-btn-group>details>summary.seating-tool-btn {
        border-right: none;
    }

    .seating-tool-btn:hover {
        background: #f8fafc;
    }

    .seating-tool-btn--active-placement {
        background: #ecfdf5 !important;
        color: #047857 !important;
    }

    .seating-tool-btn--active-selection {
        background: #eff6ff !important;
        color: #1d4ed8 !important;
    }

    .seating-more-details {
        position: relative;
        display: flex;
        align-items: stretch;
    }

    .seating-more-details>summary {
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 14px;
        cursor: pointer;
        user-select: none;
    }

    .seating-more-details>summary::-webkit-details-marker {
        display: none;
    }

    .seating-more-panel {
        position: absolute;
        right: 0;
        top: calc(100% + 6px);
        z-index: 50;
        min-width: 220px;
        padding: 16px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 10px 25px -5px rgb(15 23 42 / 0.12);
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .seating-more-panel>* {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    .seating-info-callout {
        margin-top: 16px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        font-size: 12px;
        line-height: 1.45;
        color: #64748b;
    }

    .seating-info-callout kbd {
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 1px 5px;
        font-size: 10px;
        font-family: ui-monospace, monospace;
        color: #334155;
    }

    .seating-info-callout-dismiss {
        border: none;
        background: transparent;
        color: #94a3b8;
        cursor: pointer;
        padding: 2px 6px;
        border-radius: 6px;
        line-height: 1;
        font-size: 16px;
    }

    .seating-info-callout-dismiss:hover {
        color: #64748b;
        background: #f1f5f9;
    }

    /* Dot-grid canvas behind floor plan */
    .seating-canvas-wrap {
        position: relative;
        width: 100%;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background-color: #fafbfc;
        background-image: radial-gradient(circle, #cbd5e1 0.65px, transparent 0.65px);
        background-size: 14px 14px;
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }

    /* Centers the floor-plan stage when it sits below the in-card legend */
    .seating-map-stack__stage-row {
        display: flex;
        width: 100%;
        justify-content: center;
        align-items: flex-start;
        flex: 1 1 auto;
        min-width: 0;
    }

    #seating-map-stage {
        font-size: 0;
        line-height: 0;
    }

    .seating-floorplan-img {
        display: block;
        width: 100%;
        height: auto;
    }

    /* Stage = pixel box of the rendered image (shrink-wrap). % positions match the drawing. */
    .seating-map-stage {
        position: relative;
        display: inline-block;
        max-width: 100%;
        line-height: 0;
        vertical-align: top;
    }

    .seating-map-stage.is-placement-mode {
        cursor: crosshair;
    }

    .seating-map-stage.is-selection-mode {
        box-shadow: inset 0 0 0 2px rgba(37, 99, 235, 0.45);
        border-radius: 6px;
    }

    .seating-map-stage.is-selection-mode:not(.is-placement-mode) {
        cursor: default;
    }

    .seating-map-stage img.seating-floorplan-img {
        display: block;
        width: 100%;
        max-width: 100%;
        height: auto;
        max-height: min(72dvh, calc(100dvh - 15rem));
        pointer-events: none;
    }

    /* Multi-seat merged group: one dashed region + single label (seats are muted below). */
    .seating-group-shell {
        position: absolute;
        z-index: 8;
        pointer-events: none;
        box-sizing: border-box;
    }

    .seating-group-shell__fill {
        position: absolute;
        inset: 0;
        border: 1px dashed rgba(59, 130, 246, 0.28);
        background: rgba(59, 130, 246, 0.028);
        border-radius: 16px;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
    }

    [data-seating-layout] .seating-group-shell__fill {
        background: transparent;
    }

    .seating-group-shell__label {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        z-index: 14;
        max-width: min(92%, 14rem);
        text-align: center;
        pointer-events: none;
    }

    .seating-tbl-label {
        position: absolute;
        transform: translate(-50%, 16px);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 3px;
        z-index: 14;
        pointer-events: none;
    }

    [data-seating-layout] .seating-badge-card {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        min-width: unset;
        max-width: unset;
        border-radius: 0;
        overflow: visible;
        background: transparent;
        border: none;
        box-shadow: none;
        padding: 0;
        pointer-events: none;
    }

    .seating-badge--free {
        border-top-color: #22c55e;
    }

    .seating-badge--reserved {
        border-top-color: #d97706;
    }

    .seating-badge--occupied {
        border-top-color: #dc2626;
    }

    .seating-badge--cleaning {
        border-top-color: #2563eb;
    }

    .seating-badge-inner {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        gap: 2px;
        padding: 8px 10px 9px;
        text-align: left;
        line-height: 1.2;
        min-width: 0;
    }

    .seating-badge-stack {
        flex-direction: column;
        align-items: stretch;
    }

    .seating-badge-label {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: -0.01em;
        color: #0f172a;
    }

    [data-seating-layout] .seating-badge-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #0f172a;
        text-shadow: 0 0 3px #fff, 0 0 6px #fff;
        display: block;
        text-align: center;
        margin-top: 2px;
        letter-spacing: -0.01em;
    }

    .seating-badge-status {
        font-size: 10px;
        font-weight: 500;
        color: #64748b;
    }

    [data-seating-layout] .seating-badge-status {
        display: none;
    }

    .seating-badge-guest {
        display: block;
        max-width: 100%;
        font-size: 10px;
        font-weight: 600;
        color: #334155;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    [data-seating-layout] .seating-badge-guest {
        display: none;
    }

    .seating-badge-party {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.25rem;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        letter-spacing: 0;
    }

    [data-seating-layout] .seating-badge-party {
        display: none;
    }

    .seating-badge-party-label {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
    }

    .seating-badge-party-value {
        font-variant-numeric: tabular-nums;
    }

    .seating-badge--reserved .seating-badge-status {
        color: #b45309;
    }

    .seating-badge--occupied .seating-badge-status {
        color: #b91c1c;
    }

    .seating-badge--cleaning .seating-badge-status {
        color: #1d4ed8;
    }

    .seating-badge-cap {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        letter-spacing: 0;
    }

    .seating-badge-cap i {
        font-size: 11px;
        opacity: 0.85;
    }

    /* Sticky merge bar — stays visible while scrolling the map on busy shifts */
    #seating-selection-bar.seating-selection-bar--peak {
        position: sticky;
        bottom: 0;
        z-index: 40;
        margin-top: 0.5rem;
        padding-top: 0.75rem;
        padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
        box-shadow: 0 -6px 24px rgba(15, 23, 42, 0.12);
        border-width: 2px;
    }

    .seating-merge-tag {
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #64748b;
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 9999px;
        padding: 2px 8px;
        margin-top: 2px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }

    [data-seating-layout] .seating-merge-tag {
        background: transparent;
        border: none;
        box-shadow: none;
    }

    .seating-legend-pill {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .seating-legend-pill--free {
        background: #dcfce7;
        color: #14532d;
    }

    .seating-legend-pill--reserved {
        background: #ffedd5;
        color: #9a3412;
    }

    .seating-legend-pill--occupied {
        background: #fee2e2;
        color: #991b1b;
    }

    /*
                 * Seat hits: large invisible tap area; visible marker is a small pill/dot (preview-style).
                 */
    .seating-seat-dot {
        position: absolute;
        width: 44px;
        height: 44px;
        min-width: 44px;
        min-height: 44px;
        border-radius: 9999px;
        box-sizing: border-box;
        transform: translate(-50%, -50%);
        transform-origin: center center;
        border: none;
        background: transparent;
        box-shadow: none;
        cursor: pointer;
        transition: transform 0.14s ease;
        padding: 0;
        z-index: 12;
        touch-action: manipulation;
        -webkit-tap-highlight-color: transparent;
    }

    .seating-seat-dot::after {
        content: '';
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        width: 22px;
        height: 22px;
        border-radius: 9999px;
        box-sizing: border-box;
        border: 1px solid rgba(255, 255, 255, 0.95);
        box-shadow:
            0 2px 6px rgba(15, 23, 42, 0.14),
            0 0 0 1px rgba(15, 23, 42, 0.05);
        pointer-events: none;
    }

    .seating-seat-dot.available::after {
        background: radial-gradient(circle at 30% 25%, rgba(255, 255, 255, 0.35), transparent 45%), #22c55e;
    }

    .seating-seat-dot.reserved::after {
        background: radial-gradient(circle at 30% 25%, rgba(255, 255, 255, 0.35), transparent 45%), #d97706;
    }

    .seating-seat-dot.occupied::after {
        background: radial-gradient(circle at 30% 25%, rgba(255, 255, 255, 0.3), transparent 45%), #dc2626;
    }

    .seating-seat-dot.cleaning::after {
        background: #3b82f6;
        border-color: #2563eb;
    }

    .seating-seat-dot.is-marquee-selected::after {
        box-shadow:
            0 0 0 3px rgba(251, 191, 36, 0.98),
            0 2px 8px rgba(15, 23, 42, 0.2),
            0 0 0 1px rgba(15, 23, 42, 0.08);
        z-index: 1;
    }

    .seating-seat-dot:hover {
        transform: translate(-50%, -50%) scale(1.05);
        z-index: 14;
    }

    .seating-seat-dot:hover::after {
        transform: translate(-50%, -50%) scale(1.08);
    }

    .seating-seat-dot:focus-visible {
        outline: none;
    }

    .seating-seat-dot:focus-visible::after {
        box-shadow:
            0 0 0 3px rgba(37, 99, 235, 0.55),
            0 2px 6px rgba(15, 23, 42, 0.18);
    }

    .seating-seat-dot--grouped::after {
        width: 6px;
        height: 14px;
        border-radius: 9999px;
        border-width: 1px;
    }

    .seating-seat-dot--grouped:hover::after {
        transform: translate(-50%, -50%) scale(1.12);
    }

    .seating-seat-dot--grouped.is-marquee-selected::after {
        box-shadow:
            0 0 0 3px rgba(251, 191, 36, 0.95),
            0 1px 4px rgba(0, 0, 0, 0.15);
    }

    .seating-marquee-rect {
        position: absolute;
        display: none;
        border: 3px dashed rgba(37, 99, 235, 0.95);
        background: rgba(37, 99, 235, 0.2);
        pointer-events: none;
        z-index: 20;
        box-sizing: border-box;
        box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.5) inset;
    }

    .seating-marquee-rect.is-active {
        display: block;
    }

    #group-table-modal {
        display: none;
    }

    #group-table-modal.open {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #seat-modal {
        display: none;
    }

    #seat-modal.open {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Single scroll on the panel (whole card scrolls together when needed) */
    #seat-modal .seat-modal__panel {
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: rgba(0, 0, 0, 0.35) transparent;
    }

    #seat-modal .seat-modal__panel::-webkit-scrollbar {
        width: 6px;
    }

    #seat-modal .seat-modal__panel::-webkit-scrollbar-track {
        background: transparent;
    }

    #seat-modal .seat-modal__panel::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.22);
        border-radius: 8px;
    }

    /* Seat editor — editorial segmented control (rounded, matches base .seating-s-opts) */
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

    #seat-modal.seat-modal--editorial .seating-s-opt {
        display: flex;
        min-height: 48px;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border-radius: 12px;
        border: 1px solid #d8dee8;
        background: #fff;
        color: #334155;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
    }

    #seat-modal.seat-modal--editorial .seating-s-dot {
        display: inline-flex;
        height: 9px;
        width: 9px;
        border-radius: 999px;
        background: currentColor;
    }

    #seat-modal.seat-modal--editorial .seating-s-opt.av {
        color: #15803d;
    }

    #seat-modal.seat-modal--editorial .seating-s-opt.av.active {
        background: #ecfdf5;
        border-color: #22c55e;
        color: #14532d;
        box-shadow: 0 0 0 3px rgb(34 197 94 / 0.14);
    }

    #seat-modal.seat-modal--editorial .seating-s-opt.re {
        color: #a16207;
    }

    #seat-modal.seat-modal--editorial .seating-s-opt.re.active {
        background: #fef9c3;
        border-color: #eab308;
        color: #713f12;
        box-shadow: 0 0 0 3px rgb(234 179 8 / 0.16);
    }

    #seat-modal.seat-modal--editorial .seating-s-opt.oc {
        color: #b91c1c;
    }

    #seat-modal.seat-modal--editorial .seating-s-opt.oc.active {
        background: #fee2e2;
        border-color: #ef4444;
        color: #7f1d1d;
        box-shadow: 0 0 0 3px rgb(239 68 68 / 0.14);
    }

    @media (max-width: 520px) {
        #seat-modal.seat-modal--editorial .seating-s-opts {
            grid-template-columns: 1fr;
        }
    }

    #seat-modal #seat-modal-delete-row details[open] .seat-modal__delete-caret {
        transform: rotate(180deg);
    }

    #seat-modal #seat-modal-delete-row .seat-modal__delete-caret {
        transition: transform 0.2s ease;
    }

    .seating-s-opts {
        display: flex;
        width: 100%;
        gap: 6px;
        padding: 6px;
        margin-bottom: 4px;
        border-radius: 12px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
    }

    .seating-s-opt {
        flex: 1;
        min-width: 0;
        padding: 10px 8px;
        border-radius: 8px;
        border: 1.5px solid #e2e8f0;
        text-align: center;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
        transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s;
        color: #64748b;
        background: #fff;
    }

    .seating-s-opt-label {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
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

    /* Compact toolbar — Auto Table dashboard (single row) */
    .seating-admin-toolbar--compact {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        margin-bottom: 0;
        border-bottom: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 0;
    }

    .seating-admin-toolbar--compact .seating-floorplan-form {
        margin-bottom: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1rem;
    }

    .seating-admin-toolbar--compact .seating-toolbar-label-row {
        display: none;
    }

    .seating-admin-toolbar--compact .seating-upload-row {
        gap: 0.5rem 0.75rem;
    }

    .seating-admin-toolbar--compact .seating-toolbox-row {
        flex: 1 1 auto;
        display: flex;
        justify-content: flex-end;
        min-width: 0;
    }

    @media (max-width: 1023px) {
        .seating-admin-toolbar--compact .seating-toolbox-row {
            justify-content: flex-start;
            width: 100%;
        }
    }

    /* Embed: unified strip in Auto Table (no extra chrome, short controls) */
    .seating-admin-toolbar--compact.seating-admin-toolbar--embed {
        padding: 0;
        margin: 0;
        border-bottom: none;
        background: transparent;
        flex: 1 1 260px;
        min-width: 0;
        gap: 0.35rem 0.5rem;
    }

    .seating-admin-toolbar--embed .seating-toolbar-label-row {
        display: none;
    }

    .seating-admin-toolbar--embed .seating-toolbox-row {
        flex: 0 1 auto;
        justify-content: flex-start;
    }

    .seating-admin-toolbar--embed .seating-tool-btn,
    .seating-admin-toolbar--embed .seating-more-details>summary {
        min-height: 32px;
        padding: 0 10px;
        font-size: 12px;
    }

    .seating-admin-toolbar--embed .seating-upload-btn {
        padding: 6px 10px;
        font-size: 12px;
    }

    .seating-admin-toolbar--embed .seating-save-btn {
        padding: 6px 14px;
        font-size: 12px;
    }

    .seating-layout--embed #seating-selection-mode-hint,
    .seating-layout--embed #seating-placement-hint {
        margin-bottom: 0.25rem;
    }

    .seating-layout--embed #seating-selection-bar {
        margin-top: 0.25rem;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }

    .seating-canvas-wrap--embed {
        border: none;
        border-radius: 8px;
        box-shadow: none;
        background-color: #fafbfc;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: center;
    }

    /* Auto Table embed: stage shrink-wraps image; row centers stage so % coords match the blueprint */
    .seating-layout--embed .seating-map-stack {
        width: 100%;
    }

    .seating-layout--embed .seating-map-stack__stage-row {
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .seating-layout--embed .seating-map-stage {
        display: inline-block;
        width: auto;
        max-width: 100%;
    }

    .seating-layout--embed .seating-map-stage img.seating-floorplan-img {
        display: block;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: min(78dvh, calc(100dvh - 11rem));
    }

    /* Full editor (admin/seating-layout): unified strip + scrollable map */
    .seating-layout--full-editor {
        overflow-x: hidden;
    }

    .seating-layout--full-editor #seating-selection-mode-hint,
    .seating-layout--full-editor #seating-placement-hint {
        margin-bottom: 0.25rem;
    }

    .seating-layout--full-editor #seating-selection-bar {
        margin-top: 0.25rem;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }

    .seating-layout--full-editor .seating-map-stack {
        width: 100%;
    }

    .seating-layout--full-editor .seating-map-stack__stage-row {
        justify-content: stretch;
    }

    .seating-layout--full-editor .seating-map-stage {
        width: 100%;
        max-width: 100%;
        display: block;
    }

    .seating-layout--full-editor .seating-map-stage img.seating-floorplan-img {
        width: 100%;
        max-width: 100%;
        height: auto;
        object-fit: contain;
        vertical-align: top;
        max-height: min(82dvh, calc(100dvh - 10rem));
    }

    /* Full editor: legend row only above map; map + collapsible tools split */
    .sle-full-editor-frame {
        min-width: 0;
    }

    /* Match dashboard “Seat map” toolbar vertical rhythm (.dsm-toolbar-strip) */
    .sle-legend-strip {
        flex-shrink: 0;
        border-bottom: 1px solid #e2e8f0;
        background: #fff;
        padding: 0.65rem 0.75rem 0.7rem;
    }

    @media (min-width: 1024px) {
        .sle-legend-strip {
            padding: 0.7rem 1rem 0.75rem;
        }
    }

    .sle-legend-strip__inner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.65rem 1rem;
        width: 100%;
    }

    .sle-legend-strip__main {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.85rem;
        min-width: 0;
        flex: 1 1 auto;
    }

    .sle-toolbar-actions--legend-top {
        margin-left: 0;
    }

    .sle-legend-inline--above-map {
        border-left: 1px solid #e2e8f0;
        padding-left: 0.65rem;
        margin: 0;
    }

    @media (max-width: 639px) {
        .sle-legend-inline--above-map {
            border-left: none;
            padding-left: 0;
            width: 100%;
        }
    }

    .sle-editor-split {
        min-height: 0;
        min-width: 0;
    }

    .sle-tools-panel {
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
        align-items: stretch;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        min-height: 0;
        transition: width 0.2s ease;
    }

    @media (min-width: 768px) {
        .sle-tools-panel {
            flex-direction: row;
            width: 288px;
            max-width: min(288px, 42vw);
            border-top: none;
            border-left: 1px solid #e2e8f0;
        }

        /* Collapsed: match waitlist rail width + column stack (chevron + vertical TOOLS label) */
        .sle-tools-panel[data-collapsed='true'] {
            flex-direction: column;
            width: 64px;
            max-width: 64px;
            align-items: stretch;
        }

        .sle-tools-panel[data-collapsed='true'] .sle-tools-panel__body {
            display: none;
        }

        .sle-tools-panel[data-collapsed='true'] .sle-tools-panel__chrome {
            border-right: none;
            border-bottom: 1px solid #e8edf3;
            width: 100%;
            justify-content: center;
            padding: 0.5rem 0.25rem;
        }

        .sle-tools-panel[data-collapsed='true'] .sle-tools-panel__rail {
            display: flex;
            flex: 1 1 0%;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            min-height: 0;
            padding: 0.5rem 0.15rem 0.75rem;
            overflow: hidden;
        }
    }

    .sle-tools-panel__rail {
        display: none;
    }

    .sle-tools-panel__chrome {
        flex-shrink: 0;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 0.5rem 0.2rem;
        border-right: 1px solid #e8edf3;
    }

    @media (max-width: 767px) {
        .sle-tools-panel__chrome {
            border-right: none;
            border-bottom: 1px solid #e8edf3;
            width: 100%;
            justify-content: flex-end;
            padding: 0.35rem 0.5rem;
        }

        .sle-tools-panel[data-collapsed='true'] .sle-tools-panel__body {
            display: none;
        }
    }

    .sle-tools-panel__toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s, color 0.15s;
    }

    .sle-tools-panel__toggle:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    .sle-tools-panel__body {
        flex: 1;
        min-width: 0;
        overflow-x: hidden;
        overflow-y: auto;
        padding: 0.65rem 0.75rem 0.85rem;
        -webkit-overflow-scrolling: touch;
    }

    @media (min-width: 768px) {
        .sle-tools-panel__body {
            padding: 0.75rem 0.85rem 1rem;
        }
    }

    .sle-tools-panel .seating-admin-toolbar--compact.seating-admin-toolbar--embed {
        flex: none;
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        min-width: 0;
        gap: 0.65rem;
    }

    .sle-tools-panel .seating-admin-toolbar--embed .seating-upload-row {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .sle-tools-panel .seating-admin-toolbar--embed .seating-toolbox-row {
        justify-content: flex-start;
        width: 100%;
    }

    .sle-full-editor-frame .sle-map-scroll {
        padding: 0.45rem 0.65rem 1rem;
    }

    @media (min-width: 1024px) {
        .sle-full-editor-frame .sle-map-scroll {
            padding-left: 0.85rem;
            padding-right: 0.5rem;
        }
    }

    .sle-toolbar-strip {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.25rem 0.5rem;
        padding: 0.25rem 0.5rem;
        border-bottom: 1px solid #e2e8f0;
        background: #fff;
        flex-shrink: 0;
    }

    @media (min-width: 1024px) {
        .sle-toolbar-strip {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
    }

    .sle-title {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1.15;
        flex-shrink: 0;
    }

    .sle-legend-inline {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.25rem 0.35rem;
        padding-left: 0.35rem;
        border-left: 1px solid #e2e8f0;
    }

    .sle-legend-inline .seating-legend-pill {
        padding: 3px 10px;
        font-size: 11px;
    }

    @media (max-width: 639px) {
        .sle-legend-inline {
            border-left: none;
            padding-left: 0;
            width: 100%;
        }
    }

    .sle-toolbar-actions {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: 0.35rem;
    }

    .sle-info-popover {
        position: relative;
    }

    .sle-info-popover-panel {
        position: absolute;
        right: 0;
        top: calc(100% + 6px);
        z-index: 60;
        width: min(22rem, calc(100vw - 2rem));
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 10px 25px -5px rgb(15 23 42 / 0.12);
        font-size: 12px;
        line-height: 1.5;
        color: #64748b;
    }

    .sle-info-popover-panel kbd {
        display: inline-block;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 1px 5px;
        font-family: ui-monospace, monospace;
        font-size: 10px;
        color: #334155;
    }

    .sle-info-popover-panel strong {
        color: #0f172a;
    }

    .sle-map-scroll {
        flex: 1;
        min-height: 0;
        min-width: 0;
        overflow-x: hidden;
        overflow-y: auto;
        padding: 0.25rem 0.5rem 0.5rem;
        background: #fff;
    }

    @media (min-width: 1024px) {
        .sle-map-scroll {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
    }
</style>
