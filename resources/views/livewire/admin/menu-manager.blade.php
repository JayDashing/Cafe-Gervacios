<div class="min-w-0 w-full max-w-full">
    <div class="mb-6 flex flex-wrap items-center justify-end gap-3">
        <button type="button" wire:click="openCategoryForm"
            class="rounded-xl bg-panel-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-panel-primary-hover">
            + New category
        </button>
    </div>


    <?php if ($showCategoryForm): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        wire:click.self="$set('showCategoryForm', false)">
        <div class="w-full max-w-md rounded-2xl border border-panel-stroke bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-lg font-semibold text-panel-primary">{{ $editingCategoryId ? 'Edit category' : 'New category' }}</h2>
            <form wire:submit="saveCategory">
                <label class="mb-1 block text-sm font-medium text-[#5a6a7e]">Category name</label>
                <input type="text" wire:model="categoryName"
                    class="w-full rounded-lg border border-panel-stroke px-3 py-2 text-sm text-panel-primary focus:border-panel-primary focus:outline-none focus:ring-2 focus:ring-panel-primary/15"
                    placeholder="e.g. Pasta, Grills, Beverages…" autofocus />
                <?php    $__errorArgs = ['categoryName'];
    $__bag = $errors->getBag($__errorArgs[1] ?? 'default');
    if ($__bag->has($__errorArgs[0])):
        if (isset($message)) {
            $__messageOriginal = $message;
        }
        $message = $__bag->first($__errorArgs[0]); ?>
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p> <?php        unset($message);
        if (isset($__messageOriginal)) {
            $message = $__messageOriginal;
        }
    endif;
    unset($__errorArgs, $__bag); ?>
                <div class="mt-4 flex flex-col-reverse items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                    <button type="button" wire:click="$set('showCategoryForm', false)"
                        class="px-4 py-2 text-sm text-[#5a6a7e] hover:text-panel-primary">Cancel</button>
                    <button type="submit"
                        class="rounded-xl bg-panel-primary px-4 py-2 text-sm font-semibold text-white hover:bg-panel-primary-hover">Save</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>


    <?php if ($showItemForm): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        wire:click.self="$set('showItemForm', false)">
        <div class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-2xl border border-panel-stroke bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-lg font-semibold text-panel-primary">{{ $editingItemId ? 'Edit item' : 'New item' }}</h2>
            <form wire:submit="saveItem" class="flex flex-col gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#5a6a7e]">Category</label>
                    <select wire:model="itemCategoryId"
                        class="w-full rounded-lg border border-panel-stroke bg-white px-3 py-2 text-sm text-panel-primary focus:border-panel-primary focus:outline-none focus:ring-2 focus:ring-panel-primary/15">
                        <option value="">Select…</option>
                        <?php    $__currentLoopData = $categories;
    $__env->addLoop($__currentLoopData);
    foreach ($__currentLoopData as $cat):
        $__env->incrementLoopIndices();
        $loop = $__env->getLastLoop(); ?>
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        <?php    endforeach;
    $__env->popLoop();
    $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php    $__errorArgs = ['itemCategoryId'];
    $__bag = $errors->getBag($__errorArgs[1] ?? 'default');
    if ($__bag->has($__errorArgs[0])):
        if (isset($message)) {
            $__messageOriginal = $message;
        }
        $message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p> <?php        unset($message);
        if (isset($__messageOriginal)) {
            $message = $__messageOriginal;
        }
    endif;
    unset($__errorArgs, $__bag); ?>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#5a6a7e]">Name</label>
                    <input type="text" wire:model="itemName"
                        class="w-full rounded-lg border border-panel-stroke px-3 py-2 text-sm text-panel-primary placeholder:text-[#94a3b8] focus:border-panel-primary focus:outline-none focus:ring-2 focus:ring-panel-primary/15" placeholder="Item name" />
                    <?php    $__errorArgs = ['itemName'];
    $__bag = $errors->getBag($__errorArgs[1] ?? 'default');
    if ($__bag->has($__errorArgs[0])):
        if (isset($message)) {
            $__messageOriginal = $message;
        }
        $message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p> <?php        unset($message);
        if (isset($__messageOriginal)) {
            $message = $__messageOriginal;
        }
    endif;
    unset($__errorArgs, $__bag); ?>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-[#5a6a7e]">Description</label>
                    <textarea wire:model="itemDescription" rows="2"
                        class="w-full rounded-lg border border-panel-stroke px-3 py-2 text-sm text-panel-primary focus:border-panel-primary focus:outline-none focus:ring-2 focus:ring-panel-primary/15"
                        placeholder="Short description"></textarea>
                    <?php    $__errorArgs = ['itemDescription'];
    $__bag = $errors->getBag($__errorArgs[1] ?? 'default');
    if ($__bag->has($__errorArgs[0])):
        if (isset($message)) {
            $__messageOriginal = $message;
        }
        $message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p> <?php        unset($message);
        if (isset($__messageOriginal)) {
            $message = $__messageOriginal;
        }
    endif;
    unset($__errorArgs, $__bag); ?>
                </div>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium text-[#5a6a7e]">Price</label>
                        <input type="number" step="0.01" wire:model="itemPrice"
                            class="w-full rounded-lg border border-panel-stroke px-3 py-2 text-sm text-panel-primary focus:border-panel-primary focus:outline-none focus:ring-2 focus:ring-panel-primary/15" placeholder="0.00" />
                        <?php    $__errorArgs = ['itemPrice'];
    $__bag = $errors->getBag($__errorArgs[1] ?? 'default');
    if ($__bag->has($__errorArgs[0])):
        if (isset($message)) {
            $__messageOriginal = $message;
        }
        $message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p> <?php        unset($message);
        if (isset($__messageOriginal)) {
            $message = $__messageOriginal;
        }
    endif;
    unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium text-[#5a6a7e]">Image</label>
                        <input type="file" wire:model="itemImage" accept="image/*"
                            class="w-full text-sm text-[#5a6a7e] file:mr-2 file:rounded-lg file:border-0 file:bg-[#eef1f5] file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-panel-primary hover:file:bg-[#dde3ec]" />
                        <?php    $__errorArgs = ['itemImage'];
    $__bag = $errors->getBag($__errorArgs[1] ?? 'default');
    if ($__bag->has($__errorArgs[0])):
        if (isset($message)) {
            $__messageOriginal = $message;
        }
        $message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p> <?php        unset($message);
        if (isset($__messageOriginal)) {
            $message = $__messageOriginal;
        }
    endif;
    unset($__errorArgs, $__bag); ?>
                    </div>
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="itemAvailable"
                        class="rounded border-panel-stroke text-panel-primary focus:ring-panel-primary/25" />
                    <span class="text-sm text-panel-primary">Available</span>
                </label>
                <div class="mt-2 flex flex-col-reverse items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                    <button type="button" wire:click="$set('showItemForm', false)"
                        class="px-4 py-2 text-sm text-[#5a6a7e] hover:text-panel-primary">Cancel</button>
                    <button type="submit"
                        class="rounded-xl bg-panel-primary px-4 py-2 text-sm font-semibold text-white hover:bg-panel-primary-hover">
                        <span wire:loading.remove wire:target="saveItem">Save</span>
                        <span wire:loading wire:target="saveItem">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex min-w-0 w-full flex-col gap-6 md:flex-row md:items-start">

        <div class="w-full shrink-0 md:w-64">
            <div class="overflow-hidden rounded-[14px] border border-panel-stroke bg-[#eef1f5] shadow-[0_1px_3px_rgba(26,34,50,0.10)]">
                <div class="border-b border-panel-stroke px-4 py-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#5a6a7e]">Categories</h3>
                </div>
                <?php if ($categories->isEmpty()): ?>
                <p class="px-4 py-6 text-center text-sm text-[#5a6a7e]">No categories yet</p>
                <?php else: ?>
                <ul class="divide-y divide-panel-stroke/60 bg-white">
                    <?php    $__currentLoopData = $categories;
    $__env->addLoop($__currentLoopData);
    foreach ($__currentLoopData as $cat):
        $__env->incrementLoopIndices();
        $loop = $__env->getLastLoop(); ?>
                    <li wire:click="$set('activeCategory', {{ $cat->id }})"
                        class="flex cursor-pointer items-center justify-between px-4 py-3 transition-colors hover:bg-[#eef1f5] {{ $activeCategory === $cat->id ? 'border-l-4 border-panel-primary bg-[#eef1f5]' : '' }}">
                        <span
                            class="text-sm font-medium {{ $activeCategory === $cat->id ? 'text-panel-primary' : 'text-[#5a6a7e]' }}">
                            {{ $cat->name }}

                            <span class="ml-1 text-xs text-[#5a6a7e]">({{ $cat->items()->count() }})</span>
                        </span>
                        <div class="flex items-center gap-1">
                            <button wire:click.stop="moveCategoryUp({{ $cat->id }})"
                                class="p-1 text-[#94a3b8] hover:text-panel-primary" title="Move up">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 15l7-7 7 7" />
                                </svg>
                            </button>
                            <button wire:click.stop="moveCategoryDown({{ $cat->id }})"
                                class="p-1 text-[#94a3b8] hover:text-panel-primary" title="Move down">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <button wire:click.stop="openCategoryForm({{ $cat->id }})"
                                class="p-1 text-[#94a3b8] hover:text-panel-primary" title="Edit">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                            <?php        if ($confirmDeleteCategory === $cat->id): ?>
                            <button wire:click.stop="deleteCategory({{ $cat->id }})"
                                class="p-1 text-red-600 text-xs font-bold" title="Confirm delete">Yes</button>
                            <button wire:click.stop="$set('confirmDeleteCategory', null)"
                                class="p-1 text-xs text-[#94a3b8]" title="Cancel">No</button>
                            <?php        else: ?>
                            <button wire:click.stop="$set('confirmDeleteCategory', {{ $cat->id }})"
                                class="p-1 text-[#94a3b8] hover:text-[#b91c1c]" title="Delete">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                            <?php        endif; ?>
                        </div>
                    </li>
                    <?php    endforeach;
    $__env->popLoop();
    $loop = $__env->getLastLoop(); ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>


        <div class="min-w-0 flex-1">
            <?php if ($activeCat): ?>
            <div class="overflow-hidden rounded-[14px] border border-panel-stroke bg-white shadow-[0_1px_3px_rgba(26,34,50,0.10)]">
                <div class="flex items-center justify-between gap-3 border-b border-panel-stroke bg-[#eef1f5] px-4 py-4 sm:px-6">
                    <h2 class="min-w-0 truncate text-lg font-semibold text-panel-primary">{{ $activeCat->name }}</h2>
                    <button type="button" wire:click="openItemForm({{ $activeCat->id }})"
                        class="shrink-0 rounded-xl bg-panel-primary px-3 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-panel-primary-hover">
                        + Add item
                    </button>
                </div>

                <?php    if ($activeCat->items->isEmpty()): ?>
                <p class="px-6 py-12 text-center text-sm text-[#5a6a7e]">No items in this category yet. Click “+ Add
                    item” to get started.</p>
                <?php    else: ?>
                <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="border-b border-panel-stroke bg-[#eef1f5]">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e] sm:px-6">Image</th>
                            <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e] sm:px-6">Name</th>
                            <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e] sm:px-6">Price</th>
                            <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e] sm:px-6">Status</th>
                            <th class="whitespace-nowrap px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-[#5a6a7e] sm:px-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-panel-stroke/50">
                        <?php        $__currentLoopData = $activeCat->items;
        $__env->addLoop($__currentLoopData);
        foreach ($__currentLoopData as $item):
            $__env->incrementLoopIndices();
            $loop = $__env->getLastLoop(); ?>
                        <tr class="{{ !$item->is_available ? 'opacity-50' : '' }}">
                            <td class="px-3 py-3 sm:px-6">
                                <?php            if ($item->image): ?>
                                <img src="{{ asset('storage/' . $item->image) }}" alt="{{ $item->name }}"
                                    class="w-14 h-10 object-cover rounded" />
                                <?php            else: ?>
                                <div class="flex h-10 w-14 items-center justify-center rounded bg-[#eef1f5]">
                                    <svg class="h-5 w-5 text-[#c2cad6]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <?php            endif; ?>
                            </td>
                            <td class="px-3 py-3 sm:px-6">
                                <div class="text-sm font-medium text-panel-primary">{{ $item->name }}</div>
                                <?php            if ($item->description): ?>
                                <div class="max-w-xs truncate text-xs text-[#5a6a7e]">{{ $item->description }}</div>
                                <?php            endif; ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm font-medium text-panel-primary sm:px-6">
                                ₱{{ number_format($item->price, 2) }}</td>
                            <td class="px-3 py-3 sm:px-6">
                                <button wire:click="toggleAvailability({{ $item->id }})"
                                    class="whitespace-nowrap rounded-full px-2 py-1 text-xs font-semibold {{ $item->is_available ? 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-200/80' : 'bg-rose-100 text-rose-900 ring-1 ring-rose-200/80' }}">
                                    {{ $item->is_available ? 'Available' : 'Unavailable' }}

                                </button>
                            </td>
                            <td class="px-3 py-3 text-right sm:px-6">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" wire:click="openItemForm(null, {{ $item->id }})"
                                        class="text-sm font-semibold text-panel-primary hover:text-panel-primary-hover">Edit</button>
                                    <?php            if ($confirmDeleteItem === $item->id): ?>
                                    <button wire:click="deleteItem({{ $item->id }})"
                                        class="text-red-600 font-bold text-sm">Yes, delete</button>
                                    <button wire:click="$set('confirmDeleteItem', null)"
                                        class="text-sm text-[#5a6a7e]">Cancel</button>
                                    <?php            else: ?>
                                    <button wire:click="$set('confirmDeleteItem', {{ $item->id }})"
                                        class="text-red-500 hover:text-red-700 text-sm">Delete</button>
                                    <?php            endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php        endforeach;
        $__env->popLoop();
        $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
                </div>
                <?php    endif; ?>
            </div>
            <?php else: ?>
            <div class="rounded-[14px] border border-panel-stroke bg-[#eef1f5] p-12 text-center shadow-[0_1px_3px_rgba(26,34,50,0.10)]">
                <p class="mb-2 text-lg font-medium text-panel-primary">No categories yet</p>
                <p class="text-sm text-[#5a6a7e]">Create your first category to start building your menu.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
