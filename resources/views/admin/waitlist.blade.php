@extends('layouts.admin')

@section('page_title', 'Waitlist Management')

@section('panel_heading')
    <x-admin-panel-heading title="Waitlist Management" />
@endsection

@push('scripts')
    @vite(['resources/js/blueprint-floor-map.js'])
@endpush

@section('content')
    <div class="mx-auto min-h-[50vh] w-full max-w-[1800px] rounded-xl bg-panel-canvas p-3">
        <div class="grid items-stretch gap-4 xl:grid-cols-[minmax(0,1.08fr)_minmax(0,0.92fr)]">
            <section class="min-w-0 space-y-3 xl:sticky xl:top-4 xl:self-start" aria-labelledby="operations-floor-map-title">
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 shadow-sm">
                    <div class="flex min-h-11 flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Operations</p>
                            <h2 id="operations-floor-map-title" class="mt-0.5 text-base font-bold text-slate-950">Cafe Floor Map</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Tap table markers for service actions.</p>
                        </div>
                    </div>
                </div>

                @include('admin.partials.blueprint-floor-map', ['operationsMode' => true])
            </section>

            <section class="min-w-0 xl:flex xl:h-full xl:flex-col" aria-label="Waitlist panel">
                @livewire('admin.waitlist-panel')
            </section>
        </div>
    </div>
@endsection
