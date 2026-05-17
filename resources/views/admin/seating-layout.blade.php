@extends('layouts.admin')

@section('page_title', 'Edit Blueprint')

@section('panel_heading')
    <div class="admin-panel-heading-inner flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900 md:text-[22px]">
                Edit Blueprint
            </h1>
            <p class="mt-1 text-sm text-slate-500">Upload the cafe floor image and place table markers on the blueprint.</p>
        </div>
        <a href="{{ route('admin.tables') }}"
            class="tc-admin-btn-secondary inline-flex min-h-10 items-center justify-center gap-2 px-3 py-2 text-sm">
            <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
            Back to Floor Map
        </a>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/seating-layout.js'])
@endpush

@section('content')
    <div class="space-y-3">
        @include('admin.partials.seating-map-inner', [
            'fullEditor' => true,
            'showToolbar' => true,
            'enableGrouping' => false,
        ])
    </div>
@endsection
