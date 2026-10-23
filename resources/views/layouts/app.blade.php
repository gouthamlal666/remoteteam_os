<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Support\Settings::companyName() }}</title>
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#4f46e5">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">
@php
    $user = auth()->user();
    $nav = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'match' => 'dashboard'],
        ['label' => 'Projects', 'route' => 'projects.index', 'icon' => 'folder', 'match' => 'projects.*'],
        ['label' => $user->isMember() ? 'My Tasks' : 'Tasks', 'route' => 'tasks.index', 'icon' => 'check', 'match' => 'tasks.*'],
    ];
    if ($user->isManager()) {
        $nav[] = ['label' => 'Clients', 'route' => 'clients.index', 'icon' => 'briefcase', 'match' => 'clients.*'];
        $nav[] = ['label' => 'People', 'route' => 'users.index', 'icon' => 'users', 'match' => 'users.*'];
    }
    if ($user->isSuperAdmin()) {
        $nav[] = ['label' => 'Departments', 'route' => 'departments.index', 'icon' => 'building', 'match' => 'departments.*'];
    }
    $nav[] = ['label' => 'Chat', 'route' => 'chat', 'icon' => 'chat', 'match' => 'chat*', 'badge' => $user->unreadMessagesCount()];
    $nav[] = ['label' => 'Meetings', 'route' => 'meetings.index', 'icon' => 'calendar', 'match' => 'meetings.*'];
    $nav[] = ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'chart', 'match' => 'reports.*'];
    $nav[] = ['label' => 'Activity', 'route' => 'activity.index', 'icon' => 'clock', 'match' => 'activity.*'];
    $nav[] = ['label' => 'My settings', 'route' => 'settings', 'icon' => 'cog', 'match' => 'settings'];
    if ($user->isSuperAdmin()) {
        $nav[] = ['label' => 'System settings', 'route' => 'system-settings', 'icon' => 'shield', 'match' => 'system-settings'];
    }
@endphp

<div x-data="{ sidebar: false }" class="min-h-full">
    {{-- Mobile backdrop --}}
    <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" x-on:click="sidebar = false"></div>

    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0">
        <div class="flex h-16 items-center gap-2 border-b border-slate-100 px-5">
            <x-brand class="min-w-0 truncate" />
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3">
            @foreach ($nav as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}" wire:navigate
                   @class([
                       'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium',
                       'bg-indigo-50 text-indigo-700' => $active,
                       'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! $active,
                   ])>
                    <x-icon :name="$item['icon']" />
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if (! empty($item['badge']))
                        <span class="rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="border-t border-slate-100 p-3">
            <a href="{{ route('users.show', $user) }}" wire:navigate class="flex items-center gap-3 rounded-lg p-2 hover:bg-slate-50">
                <x-avatar :user="$user" size="size-9" presence />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-900">{{ $user->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $user->activeStatus() ?? $user->role->label().($user->job_title ? ' · '.$user->job_title : '') }}</p>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                    <x-icon name="logout" /> Sign out
                </button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden" x-on:click="sidebar = true" aria-label="Open menu">
                <x-icon name="menu" />
            </button>
            <livewire:topbar :title="$title ?? ''" :key="'topbar-'.request()->path()" />
        </header>

        <main class="mx-auto max-w-7xl p-4 sm:p-6">
            {{ $slot }}
        </main>
    </div>
</div>

<livewire:command-palette />

{{-- Toasts: dispatch('notify', message: '...') from any component --}}
<div x-data="{ toasts: [] }"
     x-on:notify.window="let id = Date.now(); toasts.push({ id, message: $event.detail.message ?? $event.detail[0]?.message }); setTimeout(() => toasts = toasts.filter(t => t.id !== id), 3500)"
     class="pointer-events-none fixed right-4 bottom-4 z-50 flex flex-col gap-2">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition class="pointer-events-auto rounded-lg bg-slate-900 px-4 py-3 text-sm text-white shadow-lg" x-text="toast.message"></div>
    </template>
</div>
@if (session('status'))
    <div x-data x-init="$dispatch('notify', { message: @js(session('status')) })"></div>
@endif
</body>
</html>
