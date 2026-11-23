<span x-show="item.deployment_type !== 'redirect' && item.subdomain_folders_count > 0"
      class="border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-700/80 dark:bg-zinc-800/60 dark:text-zinc-300"
      title="Subdomain folders detected in the archive, such as blog-sub."
      {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 rounded border px-1.5 py-0.5 text-[10px] font-medium leading-none']) }}>
    <x-lucide-folder class="size-3 shrink-0 text-zinc-500 dark:text-zinc-400" />
    <span x-text="`${item.subdomain_folders_count} subfolder${Number(item.subdomain_folders_count) === 1 ? '' : 's'}`"></span>
</span>
