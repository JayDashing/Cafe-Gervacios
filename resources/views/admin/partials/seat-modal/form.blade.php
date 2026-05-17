{{-- Table marker modal form. IDs are wired in seating-layout.js. --}}
@php
    $seatField =
        'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-panel-primary focus:outline-none focus:ring-2 focus:ring-panel-primary/15';
@endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-4">
    <div class="min-w-0 sm:col-span-1">
        <label for="seat-modal-table-name" class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-700">Table Name</label>
        <input id="seat-modal-table-name" type="text" maxlength="50" placeholder="T4 or Window 2"
            class="{{ $seatField }}" />
    </div>

    <div id="seat-modal-capacity-row" class="min-w-0 sm:col-span-1">
        <label for="seat-modal-capacity" class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-700">Capacity</label>
        <input id="seat-modal-capacity" type="number" min="1" max="99" value="1"
            class="{{ $seatField }}" />
        <p id="seat-modal-capacity-hint" class="mt-1.5 text-[11px] leading-snug text-slate-500"></p>
    </div>
</div>

<div class="mt-4">
    <label for="seat-modal-furniture-type" class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-700">Table Type</label>
    <select id="seat-modal-furniture-type"
        class="{{ $seatField }}">
        <option value="standard">Standard</option>
        <option value="booth">Booth</option>
        <option value="bar">Bar / counter</option>
        <option value="high-top">High-top</option>
        <option value="outdoor">Outdoor</option>
        <option value="bench">Bench</option>
    </select>
</div>
