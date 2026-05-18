@extends('layouts.admin')

@section('page_title', 'Reports & Analytics')

@section('panel_heading')
    <x-admin-panel-heading title="Reports & Analytics" />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-[1500px]">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        @livewire('admin.seating-analytics')
    </div>
@endsection
