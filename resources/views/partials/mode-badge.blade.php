@switch($mode)
    @case('daily')
        <span class="badge-status is-info"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Pengiriman harian</span>
        @break
    @case('kapal')
        <span class="badge-status is-info"><i class="bi bi-water" aria-hidden="true"></i> Jadwal kapal</span>
        @break
    @case('campuran')
        <span class="badge-status is-wait"><i class="bi bi-shuffle" aria-hidden="true"></i> Campuran harian dan kapal</span>
        @break
    @case('belum_diatur')
        <span class="badge-status is-wait"><i class="bi bi-question-circle" aria-hidden="true"></i> Wilayah belum diatur</span>
        @break
    @default
        <span class="badge-status is-muted">Tidak ada data</span>
@endswitch
