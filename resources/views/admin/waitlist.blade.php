@extends('layouts.admin')

@section('page_title', 'Waitlist Management')

@section('panel_heading')
    <x-admin-panel-heading
        title="Waitlist Management"
        subtitle="Daily operations: monitor table availability, walk-ins, holds, and seating from one screen." />
@endsection

@push('scripts')
    @vite(['resources/js/blueprint-floor-map.js'])
@endpush

@section('content')
    <div class="-mx-4 min-h-[50vh] rounded-xl bg-panel-canvas px-3 py-3 md:-mx-5 md:px-4 md:py-4">
        <div class="grid gap-4 xl:grid-cols-[minmax(520px,1.08fr)_minmax(430px,0.92fr)] xl:items-start">
            <section class="min-w-0 space-y-3 xl:sticky xl:top-4" aria-labelledby="operations-floor-map-title">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Operations</p>
                            <h2 id="operations-floor-map-title" class="mt-0.5 text-base font-bold text-slate-950">Floor Map Panel</h2>
                        </div>
                        <a href="{{ route('admin.tables') }}"
                            class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs">
                            <i class="fa-solid fa-table-cells text-[11px]" aria-hidden="true"></i>
                            Floor Map Utility
                        </a>
                    </div>
                </div>

                @include('admin.partials.blueprint-floor-map')
            </section>

            <section class="min-w-0" aria-label="Waitlist panel">
                @livewire('admin.waitlist-panel')
            </section>
        </div>
    </div>
@endsection
