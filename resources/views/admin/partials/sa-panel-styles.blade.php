{{-- Seating analytics panel - KPI + chart tokens --}}
<style>
    .sa-page-head,
    .sa-stat-card,
    .sa-chart-card,
    .sa-empty-state,
    .sa-source-link,
    .sa-timestamp {
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    .sa-page-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        padding: 1rem;
        box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.05);
    }

    .sa-eyebrow {
        margin: 0;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .sa-page-title {
        margin: 0.25rem 0 0;
        color: #0f172a;
        font-size: 1.125rem;
        font-weight: 800;
        line-height: 1.25;
    }

    .sa-page-sub {
        margin: 0.25rem 0 0;
        color: #64748b;
        font-size: 0.875rem;
        line-height: 1.45;
    }

    .sa-page-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .sa-stat-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .sa-stat-card {
        position: relative;
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.05);
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-height: 150px;
    }

    .sa-stat-card-head,
    .sa-chart-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }

    .sa-stat-label {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        line-height: 1.35;
        margin: 0;
        padding-right: 4px;
    }

    .sa-stat-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: rgba(15, 23, 42, 0.06);
        color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 14px;
    }

    .sa-stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.05;
        letter-spacing: 0;
        font-variant-numeric: tabular-nums;
        margin: 0;
    }

    .sa-stat-sub {
        font-size: 12px;
        font-weight: 500;
        color: #64748b;
        line-height: 1.45;
        margin: 0;
        min-height: 2.15rem;
    }

    .sa-source-link {
        display: inline-flex;
        min-height: 2rem;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        width: fit-content;
        border-radius: 8px;
        border: 1px solid #dbe3ee;
        background: #f8fafc;
        padding: 0.35rem 0.65rem;
        color: #0f172a;
        font-size: 12px;
        font-weight: 800;
        line-height: 1;
        text-decoration: none;
        transition: background 150ms ease, border-color 150ms ease;
        margin-top: auto;
    }

    .sa-source-link:hover {
        background: #eef2f7;
        border-color: #cbd5e1;
    }

    .sa-source-link--strong {
        background: #0f172a;
        border-color: #0f172a;
        color: #fff;
        margin-top: 0;
    }

    .sa-source-link--strong:hover {
        background: #1e293b;
        border-color: #1e293b;
    }

    .sa-chart-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.05);
        padding: 18px;
        display: flex;
        min-width: 0;
        flex-direction: column;
    }

    .sa-chart-title {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.3;
        margin: 0;
    }

    .sa-chart-title span {
        font-weight: 600;
        color: #64748b;
    }

    .sa-chart-sub {
        font-size: 12px;
        font-weight: 500;
        color: #64748b;
        margin: 6px 0 0;
    }

    .sa-chart-empty {
        margin: 0.85rem 0 0;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        background: #f8fafc;
        padding: 0.75rem;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
    }

    .sa-chart-scroll {
        min-width: 0;
        overflow-x: auto;
        padding-bottom: 0.25rem;
    }

    .sa-chart-wrap {
        position: relative;
        height: 280px;
        min-width: 420px;
        margin-top: 1rem;
        flex: 1;
    }

    .sa-chart-wrap--wide {
        min-width: 680px;
    }

    .sa-timestamp {
        margin: 0;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-align: right;
        white-space: nowrap;
    }

    .sa-empty-state {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        background: #f8fafc;
        padding: 1rem;
    }

    @media (min-width: 640px) {
        .sa-stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1280px) {
        .sa-stat-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .sa-page-head,
        .sa-chart-head {
            flex-direction: column;
        }

        .sa-page-actions {
            width: 100%;
            align-items: flex-start;
        }

        .sa-timestamp {
            text-align: left;
            white-space: normal;
        }

        .sa-stat-card {
            min-height: 140px;
        }

        .sa-chart-card {
            padding: 14px;
        }

        .sa-chart-wrap {
            height: 250px;
        }
    }
</style>
