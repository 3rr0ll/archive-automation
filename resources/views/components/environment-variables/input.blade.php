@props(['id', 'label', 'type' => 'text', 'placeholder' => ''])

<div class="space-y-1.5">
    <label for="{{ $id }}" class="block text-sm font-medium text-zinc-700">{{ $label }}</label>
    <div class="relative rounded-md shadow-sm group">
        <input 
            type="{{ $type }}" 
            name="{{ $id }}" 
            id="{{ $id }}" 
            class="block w-full rounded-xl border-zinc-200 py-2.5 px-4 text-zinc-900 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm transition-all duration-200 group-hover:border-zinc-300 bg-white/70 backdrop-blur-sm" 
            placeholder="{{ $placeholder }}"
        >
    </div>
</div>
