@props(['title', 'hint' => null, 'icon' => 'cube'])

<div class="px-5 py-12 text-center">
    <x-dynamic-component :component="'heroicon-o-' . $icon" class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" />
    <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $title }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
</div>
