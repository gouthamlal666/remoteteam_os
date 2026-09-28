@props(['task'])
@if ($task->isOverdue())
    <x-badge color="bg-red-100 text-red-700">Overdue · {{ $task->status->label() }}</x-badge>
@else
    <x-badge :color="$task->status->badge()">{{ $task->status->label() }}</x-badge>
@endif
