@if ($upload->isOver())
    <span class="badge-status is-over"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i> Melebihi kapasitas</span>
@elseif ($upload->isReady())
    <span class="badge-status is-ready"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Siap dikirim</span>
@else
    <span class="badge-status is-wait"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Belum siap</span>
@endif
