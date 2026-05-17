@php
    $compact = $compact ?? false;
    $showQuickHelp = $showQuickHelp ?? true;
    $embed = $embed ?? false;
    $enableGrouping = $enableGrouping ?? true;
@endphp
<div
    class="seating-admin-toolbar {{ $compact ? 'seating-admin-toolbar--compact' : '' }} {{ $embed ? 'seating-admin-toolbar--embed' : '' }}">
    <form action="{{ route('admin.seating-layout.floorplan') }}" method="post" enctype="multipart/form-data"
        class="seating-floorplan-form">
        @csrf
        <div class="seating-toolbar-label-row mb-2 flex flex-wrap items-center justify-between gap-2">
            <span class="seating-toolbar-label">Floor plan image</span>
            <span class="seating-path-hint"
                title="Saved as public/images/floorplan.png — replaces the map background for this venue." tabindex="0"
                role="img" aria-label="File path info"><i class="fa-solid fa-circle-info"></i></span>
        </div>
        <div class="seating-upload-row">
            <label class="seating-upload-trigger">
                <input name="floorplan" type="file" accept="image/*" required />
                <span class="seating-upload-btn"><i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                    Upload</span>
            </label>
            <button type="submit" class="seating-save-btn">Save</button>
        </div>
        <p class="mt-2 text-xs text-slate-500">Recommended: under 4MB, JPG or PNG</p>
    </form>

    <div class="seating-toolbox-row">
        <div class="seating-btn-group" role="group" aria-label="Floor Map tools">
            <button type="button" id="seating-placement-toggle" class="seating-tool-btn seating-tool-btn--placement">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span class="seating-tool-btn__text">Add marker</span>
            </button>
            @if ($enableGrouping)
                <button type="button" id="seating-selection-mode-toggle" aria-pressed="false"
                    class="seating-tool-btn seating-tool-btn--selection">
                    <i class="fa-solid fa-arrow-pointer" aria-hidden="true"></i>
                    <span class="seating-tool-btn__text">Selection</span>
                </button>
            @endif
            <details class="seating-more-details">
                <summary class="seating-tool-btn seating-tool-btn--more"><i class="fa-solid fa-ellipsis"
                        aria-hidden="true"></i> More</summary>
                <div class="seating-more-panel">
                    <button type="button" id="copy-seating-snippet"
                        class="mb-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-left text-[13px] font-medium text-slate-700 hover:bg-slate-100">
                        Copy seeder snippet
                    </button>
                    <p class="text-[12px] leading-relaxed text-slate-500"><strong
                            class="font-semibold text-slate-700">Map:</strong> markers use % of the image. Daily
                        table merging is handled from the Floor Map page.</p>
                    @if ($enableGrouping)
                        <p class="mt-2 text-[12px] leading-relaxed text-slate-500"><strong
                                class="font-semibold text-slate-700">Touch:</strong> long-press a marker to enable
                            selection mode.</p>
                    @endif
                </div>
            </details>
        </div>
    </div>

    @if ($showQuickHelp)
        <div id="seating-quick-help" class="seating-info-callout flex items-start gap-2">
            <p class="min-w-0 flex-1">
                <strong class="font-semibold text-slate-700">Quick workflow:</strong>
                upload the blueprint, click <strong>Add marker</strong>, then tap the image to place table markers.
            </p>
            <button type="button" id="seating-quick-help-dismiss" class="seating-info-callout-dismiss shrink-0"
                aria-label="Dismiss help">&times;</button>
        </div>
    @endif
</div>
