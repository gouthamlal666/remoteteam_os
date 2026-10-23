@php
    $chips = ['all' => 'All', 'image' => '🖼️ Images', 'pdf' => '📕 PDFs', 'video' => '🎬 Videos', 'document' => '📄 Documents', 'link' => '🔗 Links'];
    $folderPath = function ($f) use ($allFolders) {
        $names = [$f->name];
        $parent = $allFolders->firstWhere('id', $f->parent_id);
        while ($parent) { array_unshift($names, $parent->name); $parent = $allFolders->firstWhere('id', $parent->parent_id); }
        return implode(' / ', $names);
    };
@endphp
<div x-data="{
        dragging: false,
        progress: null,
        preview: null,
        upload(files) {
            if (! files.length) return;
            this.progress = 0;
            $wire.uploadMultiple('uploads', files,
                () => { this.progress = null },
                () => { this.progress = null },
                (e) => { this.progress = e.detail.progress });
        },
     }"
     x-on:dragover.prevent="@if ($canEdit) dragging = true @endif"
     x-on:dragleave.self="dragging = false"
     x-on:drop.prevent="dragging = false; @if ($canEdit) upload($event.dataTransfer.files) @endif"
     x-on:keydown.escape.window="preview = null"
     class="relative space-y-4">

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3 lg:flex-row lg:items-center">
        <nav class="flex min-w-0 flex-1 items-center gap-1 text-sm" aria-label="Folder path">
            <button wire:click="openFolder" @class(['rounded-md px-2 py-1 font-medium hover:bg-slate-100', 'text-slate-900' => ! $current, 'text-slate-500' => $current])>📁 Drive</button>
            @foreach ($breadcrumbs as $crumb)
                <span class="text-slate-300">/</span>
                <button wire:click="openFolder({{ $crumb->id }})" @class(['truncate rounded-md px-2 py-1 hover:bg-slate-100', 'font-semibold text-slate-900' => $loop->last, 'text-slate-500' => ! $loop->last])>{{ $crumb->name }}</button>
            @endforeach
        </nav>
        <div class="relative w-full lg:w-56">
            <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-slate-400" />
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search this Drive…" class="input py-1.5 pl-9">
        </div>
        @if ($canEdit)
            <div class="flex flex-wrap gap-2">
                <button wire:click="editFolder" class="btn-secondary py-1.5">📁 New folder</button>
                <button wire:click="$set('showLink', true)" class="btn-secondary py-1.5">🔗 Add link</button>
                <label class="btn-primary cursor-pointer py-1.5">
                    <input type="file" multiple class="sr-only" x-on:change="upload($event.target.files); $event.target.value = ''">
                    <x-icon name="plus" class="size-4" /> Upload
                </label>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($chips as $value => $label)
            <button wire:click="$set('filter', '{{ $value }}')" @class([
                'rounded-full border px-3 py-1 text-xs font-medium',
                'border-indigo-600 bg-indigo-600 text-white' => $filter === $value,
                'border-slate-200 bg-white text-slate-600 hover:border-slate-300' => $filter !== $value,
            ])>{{ $label }}</button>
        @endforeach
        @if ($canEdit)
            <span class="ml-auto text-xs text-slate-400">Drag & drop files anywhere here · up to {{ $maxMb }} MB each</span>
        @endif
    </div>

    {{-- Upload progress / errors --}}
    <div x-show="progress !== null" x-cloak class="rounded-lg border border-indigo-200 bg-indigo-50 p-3">
        <div class="flex justify-between text-xs text-indigo-800"><span>Uploading…</span><span x-text="(progress ?? 0) + '%'"></span></div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-indigo-100"><div class="h-full rounded-full bg-indigo-600 transition-all" :style="'width:' + (progress ?? 0) + '%'"></div></div>
    </div>
    @error('uploads') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    @error('uploads.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    {{-- Empty Drive: suggested folders --}}
    @if ($totalItems === 0 && $allFolders->isEmpty() && ! $current)
        <div class="rounded-xl border-2 border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-4xl">🗂️</p>
            <p class="mt-2 font-semibold text-slate-900">{{ $client->name }}'s Drive is empty</p>
            <p class="mt-1 text-sm text-slate-500">Keep logos, images, PDFs, videos and documents here, or save Google Drive, YouTube, Vimeo and Loom links.</p>
            @if ($canEdit)
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <button wire:click="createSuggestedFolders" class="btn-primary">Create suggested folders</button>
                    <label class="btn-secondary cursor-pointer">
                        <input type="file" multiple class="sr-only" x-on:change="upload($event.target.files); $event.target.value = ''">
                        Upload files
                    </label>
                </div>
                <p class="mt-3 text-xs text-slate-400">Suggested: {{ implode(', ', \App\Models\ClientFolder::SUGGESTED) }}</p>
            @endif
        </div>
    @else
        {{-- Folders --}}
        @if ($folders->isNotEmpty() && $filter === 'all')
            <div>
                <p class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Folders</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($folders as $f)
                        <div wire:key="fd-{{ $f->id }}" class="group relative">
                            <button wire:click="openFolder({{ $f->id }})" class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-left hover:border-indigo-300 hover:bg-indigo-50/40">
                                <span class="text-2xl">📁</span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $f->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $f->items_count }} {{ str('item')->plural($f->items_count) }}{{ $f->children_count ? ' · '.$f->children_count.' '.str('folder')->plural($f->children_count) : '' }}</span>
                                </span>
                            </button>
                            @if ($canEdit)
                                <div class="absolute top-2 right-2 hidden gap-0.5 rounded-md bg-white shadow-sm ring-1 ring-slate-200 group-hover:flex">
                                    <button wire:click="editFolder({{ $f->id }})" class="p-1 text-slate-400 hover:text-slate-700" aria-label="Rename {{ $f->name }}"><x-icon name="pencil" class="size-3.5" /></button>
                                    <button wire:click="deleteFolder({{ $f->id }})" wire:confirm="Delete the “{{ $f->name }}” folder? Anything inside it moves up one level, so nothing is lost." class="p-1 text-slate-400 hover:text-red-600" aria-label="Delete {{ $f->name }}"><x-icon name="trash" class="size-3.5" /></button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Items --}}
        <div>
            @if ($folders->isNotEmpty() && $filter === 'all') <p class="mb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Files</p> @endif
            @if ($items->isEmpty())
                <div class="rounded-xl border-2 border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                    {{ $search ? 'Nothing matches “'.$search.'”.' : ($filter !== 'all' ? 'No '.strtolower(strip_tags($chips[$filter])).' here.' : 'This folder is empty.'.($canEdit ? ' Drop files here or use Upload.' : '')) }}
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($items as $item)
                        @php
                            $previewData = [
                                'title' => $item->title,
                                'kind' => $item->isLink() ? ($item->embedUrl() ? 'embed' : 'none') : ($item->isImage() ? 'image' : ($item->isPdf() ? 'pdf' : ($item->isVideo() ? 'video' : 'none'))),
                                'src' => $item->isLink() ? $item->embedUrl() : $item->viewUrl(),
                                'mime' => $item->mime_type,
                                'open' => $item->isLink() ? $item->url : route('client-files.show', $item),
                                'openLabel' => $item->isLink() ? 'Open in '.$item->linkProvider() : 'Download',
                            ];
                        @endphp
                        <div wire:key="it-{{ $item->id }}" class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:border-indigo-300 hover:shadow-md">
                            <button type="button"
                                    @if ($item->isPreviewable()) x-on:click="preview = @js($previewData)" @else x-on:click="window.open(@js($previewData['open']), '_blank', 'noopener')" @endif
                                    class="block w-full text-left">
                                <div class="relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-slate-50">
                                    @if ($thumb = $item->thumbnailUrl())
                                        <img src="{{ $thumb }}" alt="" loading="lazy" class="size-full object-cover" x-data x-on:error="$el.remove()">
                                    @else
                                        <span class="text-5xl">{{ $item->icon() }}</span>
                                    @endif
                                    @if ($item->category() === 'video')
                                        <span class="absolute inset-0 flex items-center justify-center"><span class="flex size-11 items-center justify-center rounded-full bg-black/55 text-white shadow-lg">▶</span></span>
                                    @endif
                                    <span class="absolute top-2 left-2 rounded bg-white/90 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700 shadow-sm">{{ $item->typeLabel() }}</span>
                                </div>
                                <div class="p-2.5">
                                    <p class="truncate text-sm font-medium text-slate-900" title="{{ $item->title }}">{{ $item->title }}</p>
                                    <p class="truncate text-[11px] text-slate-500">
                                        @if ($search && $item->folder_id) 📁 {{ $folderPath($allFolders->firstWhere('id', $item->folder_id)) }} · @endif
                                        {{ $item->humanSize() ?: $item->linkProvider() }} · {{ $item->created_at->format('d M Y') }}
                                    </p>
                                </div>
                            </button>
                            <div class="absolute top-2 right-2 hidden gap-0.5 rounded-md bg-white shadow-sm ring-1 ring-slate-200 group-hover:flex">
                                <a href="{{ $previewData['open'] }}" @if ($item->isLink()) target="_blank" rel="noopener noreferrer" @endif class="p-1 text-slate-400 hover:text-slate-700" aria-label="{{ $previewData['openLabel'] }}"><x-icon :name="$item->isLink() ? 'external' : 'download'" class="size-3.5" /></a>
                                @if ($canEdit)
                                    <button wire:click="editItem({{ $item->id }})" class="p-1 text-slate-400 hover:text-slate-700" aria-label="Rename or move {{ $item->title }}"><x-icon name="pencil" class="size-3.5" /></button>
                                    <button wire:click="deleteItem({{ $item->id }})" wire:confirm="Delete “{{ $item->title }}”?{{ $item->isLink() ? '' : ' The file is permanently removed.' }}" class="p-1 text-slate-400 hover:text-red-600" aria-label="Delete {{ $item->title }}"><x-icon name="trash" class="size-3.5" /></button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Drop overlay --}}
    <div x-show="dragging" x-cloak class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center rounded-xl border-2 border-dashed border-indigo-500 bg-indigo-50/90">
        <p class="text-lg font-semibold text-indigo-700">Drop to upload{{ $current ? ' to “'.$current->name.'”' : '' }}</p>
    </div>

    {{-- Preview --}}
    <template x-teleport="body">
        <div x-show="preview" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex flex-col bg-slate-950/90 p-4" x-on:click.self="preview = null">
            <div class="mx-auto flex w-full max-w-6xl items-center gap-3 pb-3 text-white">
                <p class="min-w-0 flex-1 truncate font-medium" x-text="preview?.title"></p>
                <a :href="preview?.open" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-white/10 px-3 py-1.5 text-sm hover:bg-white/20" x-text="preview?.openLabel"></a>
                <button x-on:click="preview = null" class="rounded-lg bg-white/10 p-1.5 hover:bg-white/20" aria-label="Close preview"><x-icon name="x" class="size-5" /></button>
            </div>
            <div class="mx-auto flex min-h-0 w-full max-w-6xl flex-1 items-center justify-center" x-on:click.self="preview = null">
                <template x-if="preview?.kind === 'image'"><img :src="preview.src" :alt="preview.title" class="max-h-full max-w-full rounded-lg object-contain shadow-2xl"></template>
                <template x-if="preview?.kind === 'video'"><video :src="preview.src" controls autoplay playsinline class="max-h-full max-w-full rounded-lg bg-black shadow-2xl"></video></template>
                <template x-if="preview?.kind === 'pdf'"><iframe :src="preview.src" class="size-full rounded-lg bg-white" title="PDF preview"></iframe></template>
                <template x-if="preview?.kind === 'embed'"><iframe :src="preview.src" class="aspect-video max-h-full w-full rounded-lg bg-black" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" title="Preview"></iframe></template>
            </div>
        </div>
    </template>

    {{-- Dialogs --}}
    @if ($canEdit)
        <x-modal wire:model="showFolder" :title="$folderId ? 'Rename folder' : 'New folder'.($current ? ' in “'.$current->name.'”' : '')" max-width="max-w-md">
            <form wire:submit="saveFolder" class="space-y-4">
                <x-field label="Folder name" for="dr-folder" error="folderName">
                    <input wire:model="folderName" id="dr-folder" class="input" list="dr-folder-suggestions" placeholder="e.g. Logos & branding" autofocus>
                    <datalist id="dr-folder-suggestions">@foreach (\App\Models\ClientFolder::SUGGESTED as $s) <option value="{{ $s }}"></option> @endforeach</datalist>
                </x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">{{ $folderId ? 'Rename' : 'Create folder' }}</button>
                </div>
            </form>
        </x-modal>

        <x-modal wire:model="showLink" :title="'Add a link'.($current ? ' to “'.$current->name.'”' : '')" max-width="max-w-lg">
            <form wire:submit="addLink" class="space-y-4">
                <x-field label="Link" for="dr-url" error="linkUrl">
                    <input wire:model="linkUrl" id="dr-url" type="url" class="input" placeholder="https://drive.google.com/file/d/… or a YouTube / Vimeo / Loom link">
                    <p class="text-xs text-slate-400">Google Drive files and Docs, YouTube, Vimeo and Loom links preview and play right here. Any other link opens in a new tab.</p>
                </x-field>
                <x-field label="Name (optional)" for="dr-title" error="linkTitle">
                    <input wire:model="linkTitle" id="dr-title" class="input" placeholder="e.g. Product launch video">
                </x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Add link</button>
                </div>
            </form>
        </x-modal>

        <x-modal wire:model="showRename" title="Rename or move" max-width="max-w-md">
            <form wire:submit="saveItem" class="space-y-4">
                <x-field label="Name" for="dr-item" error="itemTitle"><input wire:model="itemTitle" id="dr-item" class="input"></x-field>
                <x-field label="Folder" for="dr-move" error="moveTo">
                    <select wire:model="moveTo" id="dr-move" class="input">
                        <option value="">📁 Drive (top level)</option>
                        @foreach ($allFolders->sortBy(fn ($f) => $folderPath($f)) as $f)
                            <option value="{{ $f->id }}">📁 {{ $folderPath($f) }}</option>
                        @endforeach
                    </select>
                </x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="open = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
