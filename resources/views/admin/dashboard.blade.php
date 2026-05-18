@extends('layouts.admin')

@section('page_title', 'Dashboard')

@section('panel_heading')
    <x-admin-panel-heading title="Dashboard" />
@endsection

@section('content')
    <div class="admin-dashboard-animate mx-auto w-full max-w-[1400px]">
        @if (auth()->user()->isAdmin())
            @livewire('admin.summary-bar')
        @else
            <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
                Staff accounts open the floor map from the sidebar.
            </div>
        @endif
    </div>
@endsection
