@extends('layouts.admin')

@section('page_title', 'Floor Map Management')

@section('panel_heading')
    <x-admin-panel-heading title="Floor Map Management" />
@endsection

@push('scripts')
    @vite(['resources/js/blueprint-floor-map.js'])
@endpush

@section('content')
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @livewire('admin.staff-table-assignment-notifications')

    <div
        x-data="{
            tab: ['floor', 'calendar', 'list'].includes(new URLSearchParams(window.location.search).get('tab')) ? new URLSearchParams(window.location.search).get('tab') : 'floor',
            setTab(value) {
                this.tab = value;
                const url = new URL(window.location.href);
                if (value === 'floor') {
                    url.searchParams.delete('tab');
                } else {
                    url.searchParams.set('tab', value);
                }
                window.history.replaceState({}, '', url);
            }
        }"
        class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                x-on:click="setTab('floor')"
                class="tc-admin-btn-secondary inline-flex min-h-10 items-center justify-center gap-2 px-3 py-2 text-sm"
                x-bind:class="tab === 'floor' ? 'border-slate-900 text-slate-950' : ''">
                <i class="fa-solid fa-map-location-dot text-xs" aria-hidden="true"></i>
                Floor Map
            </button>
            <button type="button"
                x-on:click="setTab('list')"
                class="tc-admin-btn-secondary inline-flex min-h-10 items-center justify-center gap-2 px-3 py-2 text-sm"
                x-bind:class="tab === 'list' ? 'border-slate-900 text-slate-950' : ''">
                <i class="fa-solid fa-table-cells text-xs" aria-hidden="true"></i>
                Table List
            </button>
            <button type="button"
                x-on:click="setTab('calendar')"
                class="tc-admin-btn-secondary inline-flex min-h-10 items-center justify-center gap-2 px-3 py-2 text-sm"
                x-bind:class="tab === 'calendar' ? 'border-slate-900 text-slate-950' : ''">
                <i class="fa-solid fa-calendar-days text-xs" aria-hidden="true"></i>
                Calendar
            </button>
            @if (auth()->user()?->isAdmin())
                <a href="{{ route('admin.tables', ['edit' => 1]) }}"
                    class="tc-admin-btn-primary inline-flex min-h-10 items-center justify-center gap-2 px-3 py-2 text-sm"
                    x-show="tab === 'floor'"
                    x-cloak>
                    <i class="fa-solid fa-pen-ruler text-xs" aria-hidden="true"></i>
                    Edit Layout
                </a>
            @endif
        </div>

        <section x-show="tab === 'floor'" x-cloak>
            @include('admin.partials.blueprint-floor-map')
        </section>

        <section x-show="tab === 'calendar'" x-cloak>
            @include('admin.partials.floor-calendar')
        </section>

        <section x-show="tab === 'list'" x-cloak>
            @livewire('admin.table-management-panel')
        </section>
    </div>
@endsection
