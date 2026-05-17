@extends('layouts.admin')

@section('page_title', 'Waitlist Management')

@section('panel_heading')
    <x-admin-panel-heading
        title="Waitlist Management"
        subtitle="Manage waiting, notified, seated, and cancelled walk-in guests." />
@endsection

@section('content')
    <div class="-mx-4 min-h-[50vh] rounded-xl bg-panel-canvas px-3 py-3 md:-mx-5 md:px-4 md:py-4">
        @livewire('admin.waitlist-panel')
    </div>
@endsection
