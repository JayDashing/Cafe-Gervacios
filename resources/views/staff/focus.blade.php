<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Focus Mode - {{ config('app.venue_name') }}</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        body {
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
        }
    </style>
</head>

<body class="min-h-screen text-slate-100 antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-[1600px] flex-col p-4 md:p-5" x-data="{ addQueueOpen: false }"
        x-on:keydown.escape.window="addQueueOpen = false">
        <header class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Staff Focus Mode</h1>
                <p class="text-sm text-slate-300">Queue + reservation check-in only</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" x-on:click="addQueueOpen = true"
                    class="min-h-[44px] rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-slate-900">
                    + Add to Queue
                </button>
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.tables') }}"
                    class="min-h-[44px] rounded-lg border border-slate-600 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white">
                    Exit Focus Mode
                </a>
            </div>
        </header>

        @livewire('admin.focus-mode-board')

        <div x-show="addQueueOpen" x-cloak x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="fixed inset-0 z-[120] flex items-end justify-center bg-black/55 p-4 sm:items-center"
            style="display: none;">
            <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white text-slate-900 shadow-2xl"
                x-on:click.outside="addQueueOpen = false">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h2 class="text-lg font-bold">Add to Queue</h2>
                    <button type="button" class="min-h-[44px] min-w-[44px] rounded-lg border border-slate-200 text-slate-600"
                        x-on:click="addQueueOpen = false">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="max-h-[75vh] overflow-y-auto p-4">
                    @livewire('staff-walk-in-queue')
                </div>
            </div>
        </div>
    </div>

    @livewireScripts
</body>

</html>
