<span x-show="item.deployment_type !== 'redirect' && item.subdomain_folders_count > 0"
      title="Subdomain folders detected in the archive, such as blog-sub."
      {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 text-[11px] text-zinc-400 dark:text-zinc-500 font-normal leading-none whitespace-nowrap']) }}>
    <span>&middot;</span>
    <x-lucide-folder class="size-3 shrink-0" />
    <span x-text="`${item.subdomain_folders_count} subfolder${Number(item.subdomain_folders_count) === 1 ? '' : 's'}`"></span>
</span>
