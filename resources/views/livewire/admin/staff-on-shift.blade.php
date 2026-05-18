<div
    class="rounded-xl border border-slate-200 bg-white p-4 font-sans sm:p-5"
    wire:poll.15s>
    <h2 class="text-base font-bold tracking-tight text-slate-950 [font-family:var(--font-admin-display)]">Staff on
        shift</h2>
    <p class="mt-1.5 text-[13px] leading-relaxed text-slate-600">
        People signed in to admin or staff right now (active browser or tablet sessions).
    </p>

    @if ($sessionsUnavailable)
        <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
            Active staff list needs sessions stored in the database. Set <code
                class="text-xs">SESSION_DRIVER=database</code> in
            <code class="text-xs">.env</code> and ensure the <code class="text-xs">sessions</code> table exists.
        </p>
    @elseif ($rows->isEmpty())
        <p class="mt-5 text-sm text-slate-500">
            No staff or admin sessions in the last {{ (int) config('session.lifetime', 120) }} minutes.
        </p>
    @else
        <ul class="mt-5 divide-y divide-slate-100 text-sm">
            @foreach ($rows as $row)
                <li class="flex flex-wrap items-baseline justify-between gap-2 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <span class="font-semibold text-slate-900">{{ $row->name }}</span>
                        @if ($currentUserId && (int) $row->id === (int) $currentUserId)
                            <span class="ml-1.5 text-xs font-semibold text-panel-primary">(you)</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-semibold text-slate-700">{{ $row->role_label }}</span>
                        <span>{{ $row->last_seen }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
