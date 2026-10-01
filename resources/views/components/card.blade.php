@props(['title' => null])

<div {{ $attributes->merge(['class' => 'bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm']) }}>
    @if($title)
        <h3 class="text-lg font-semibold text-white mb-4">{{ $title }}</h3>
    @endif
    {{ $slot }}
</div>
