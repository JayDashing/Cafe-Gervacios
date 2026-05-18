{{-- Seating analytics panel - aligned with dashboard/waitlist admin surfaces --}}
<style>
    .sa-panel-card,
    .sa-chart-card,
    .sa-empty-state,
    .sa-source-link,
    .sa-timestamp {
        font-family: var(--font-admin-ui, 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif);
    }

    .sa-panel-card,
    .sa-chart-card {
        border: 1px solid #d8dee8;
        border-radius: 0.75rem;
        background: #ffffff;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
    }

    .sa-panel-head,
    .sa-chart-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.8rem 1.25rem;
    }

    .sa-section-title,
    .sa-chart-title {
        margin: 0;
        color: #0f172a;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: -0.015em;
        line-height: 1.25;
    }

    .sa-panel-actions {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.65rem;
    }

    .sa-panel-head--compact {
        justify-content: flex-end;
        padding-block: 0.65rem;
    }

    .sa-timestamp {
        margin: 0;
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .sa-stat-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        padding: 1rem;
    }

    .sa-stat-tile {
        min-width: 0;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        background: #f8fafc;
        padding: 0.9rem 1rem;
        color: inherit;
        text-decoration: none;
        transition: background 150ms ease, border-color 150ms ease, transform 150ms ease;
    }

    .sa-stat-tile:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
        transform: translateY(-1px);
    }

    .sa-stat-label {
        margin: 0;
        overflow: hidden;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-overflow: ellipsis;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .sa-stat-value {
        margin: 0.55rem 0 0;
        color: #0f172a;
        font-size: 1.9rem;
        font-weight: 700;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }

    .sa-stat-meta {
        margin: 0.35rem 0 0;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 500;
    }

    .sa-source-link {
        display: inline-flex;
        min-height: 2rem;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        width: fit-content;
        border-radius: 0.65rem;
        border: 1px solid #dbe3ee;
        background: #f8fafc;
        padding: 0.35rem 0.65rem;
        color: #0f172a;
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1;
        text-decoration: none;
        transition: background 150ms ease, border-color 150ms ease;
    }

    .sa-source-link:hover {
        background: #eef2f7;
        border-color: #cbd5e1;
    }

    .sa-chart-card {
        display: flex;
        min-width: 0;
        flex-direction: column;
        overflow: hidden;
    }

    .sa-chart-head {
        align-items: flex-start;
    }

    .sa-chart-sub {
        margin: 0.25rem 0 0;
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 500;
    }

    .sa-chart-empty {
        margin: 1rem 1.25rem 0;
        border: 1px dashed #cbd5e1;
        border-radius: 0.75rem;
        background: #f8fafc;
        padding: 0.8rem 0.95rem;
        color: #64748b;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .sa-chart-scroll {
        min-width: 0;
        overflow-x: auto;
        padding: 1rem 1.25rem 1.25rem;
    }

    .sa-chart-wrap {
        position: relative;
        height: 320px;
        min-width: 420px;
    }

    .sa-chart-wrap--wide {
        min-width: 680px;
    }

    .sa-empty-state {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        border: 1px dashed #cbd5e1;
        border-radius: 0.75rem;
        background: #f8fafc;
        padding: 1rem;
    }

    @media (min-width: 768px) {
        .sa-stat-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .sa-panel-head,
        .sa-chart-head {
            align-items: flex-start;
        }

        .sa-panel-actions {
            width: 100%;
            justify-content: flex-start;
        }

        .sa-timestamp {
            white-space: normal;
        }

        .sa-chart-scroll {
            padding: 0.85rem 1rem 1rem;
        }

        .sa-chart-wrap {
            height: 270px;
        }
    }
</style>
