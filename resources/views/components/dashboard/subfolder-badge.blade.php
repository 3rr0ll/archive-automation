<span x-show="item.deployment_type !== 'redirect' && item.subdomain_folders_count != null"
      :class="item.subdomain_folders_count > 0
          ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300'
          : 'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400'"
      title="Subdomain folders detected in the archive, such as blog-sub."
      {{ $attributes->merge(['class' => 'inline-flex w-fit items-center gap-1 rounded border px-1.5 py-0.5 text-[10px] font-medium']) }}>
    <x-lucide-folder class="size-3 shrink-0" />
    <span x-text="item.subdomain_folders_count > 0
        ? `${item.subdomain_folders_count} subfolder${Number(item.subdomain_folders_count) === 1 ? '' : 's'}`
        : 'No subfolders detected'"></span>
</span>
