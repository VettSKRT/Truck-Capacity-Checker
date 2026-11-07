{{-- Status kloter dari ringkasan per jalur. Variabel: $overall (hasil RouteLoadService::summarizeMany()[id]['overall']) --}}
@switch($overall['state'])
    @case('ready')
        <span class="badge-status is-ready"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Semua {{ $overall['active'] }} jalur siap</span>
        @break
    @case('partial')
        <span class="badge-status is-wait"><i class="bi bi-hourglass-split" aria-hidden="true"></i> {{ $overall['ready'] }} dari {{ $overall['active'] }} jalur siap</span>
        @break
    @case('over')
        <span class="badge-status is-over"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i> {{ $overall['over'] }} jalur melebihi</span>
        @break
    @case('wait')
        <span class="badge-status is-wait"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Belum ada jalur siap</span>
        @break
    @default
        <span class="badge-status is-muted">Belum ada barang siap</span>
@endswitch
