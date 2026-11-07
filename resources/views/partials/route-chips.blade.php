{{-- Chip jalur dengan warna menurut status. Variabel: $routes (daftar jalur dari RouteLoadService) --}}
<span class="chip-list">
    @forelse ($routes as $r)
        @if ($r['state'] !== 'none')
            <span class="chip chip--{{ $r['state'] }}" title="{{ $r['label'] }}{{ $r['name'] ? ' (' . $r['name'] . ')' : '' }}: {{ number_format($r['ratio'] * 100, 1, ',', '.') }}% dari {{ $r['vehicle']['name'] ?? 'truk' }}">
                {{ $r['route'] === null ? '?' : 'J' . $r['route'] }} {{ number_format($r['ratio'] * 100, 0) }}%
            </span>
        @endif
    @empty
        <span class="text-muted">-</span>
    @endforelse
</span>
