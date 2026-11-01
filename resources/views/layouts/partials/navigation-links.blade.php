<nav aria-label="Navigation principale" class="space-y-6">
    @foreach ($groups as $group)
        <section>
            <h2 class="px-3 text-xs font-bold uppercase tracking-wider text-slate-400">{{ $group['label'] }}</h2>
            <div class="mt-2 grid gap-1">
                @foreach ($group['items'] as $item)
                    @php($active = request()->routeIs($item['active']))
                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif @class([
                        'flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                        'bg-teal-50 text-teal-900' => $active,
                        'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! $active,
                    ])>
                        <span>{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-800">
                                {{ min($item['badge'], 99) }}{{ $item['badge'] > 99 ? '+' : '' }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</nav>
