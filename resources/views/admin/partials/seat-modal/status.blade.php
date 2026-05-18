{{-- Table status segmented control. --}}
<div class="mt-5">
    <div class="mb-2 flex items-center justify-between gap-3">
        <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-700">Status</p>
        <p class="text-xs text-slate-500">Applies to this table marker.</p>
    </div>
    <div class="seating-s-opts" role="radiogroup" aria-label="Status for this table marker">
        <div class="seating-s-opt av" data-seat-status="free" tabindex="0" role="radio" aria-checked="false">
            <span class="seating-s-dot" aria-hidden="true"></span>
            <span class="seating-s-opt-label">Available</span>
        </div>
        <div class="seating-s-opt oc" data-seat-status="occupied" tabindex="0" role="radio" aria-checked="false">
            <span class="seating-s-dot" aria-hidden="true"></span>
            <span class="seating-s-opt-label">Occupied</span>
        </div>
    </div>
</div>
