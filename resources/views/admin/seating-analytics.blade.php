@extends('layouts.admin')

@section('page_title', 'Reports & Analytics')

@section('panel_heading')
    <x-admin-panel-heading
        title="Reports & Analytics"
        subtitle="View booking, queue, and table performance." />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-7xl">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        @livewire('admin.seating-analytics')
    </div>
@endsection
