@props(['title', 'description' => '', 'icon' => null])

<div class="bg-white/70 backdrop-blur-md rounded-2xl shadow-sm border border-zinc-200/80 overflow-hidden mb-6 transition-all duration-300 hover:shadow-md hover:border-zinc-300">
    <div class="p-6 border-b border-zinc-100 flex items-center gap-4">
        @if($icon)
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-zinc-100 flex items-center justify-center text-zinc-600">
                {!! $icon !!}
            </div>
        @endif
        <div>
            <h3 class="text-lg font-semibold text-zinc-900 tracking-tight">{{ $title }}</h3>
            @if($description)
                <p class="text-sm text-zinc-500 mt-0.5">{{ $description }}</p>
            @endif
        </div>
    </div>
    <div class="p-6 bg-white/50">
        {{ $slot }}
    </div>
</div>
