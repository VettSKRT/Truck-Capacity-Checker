@php
    $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.');
    $compact = $compact ?? false;
@endphp
<div class="table-wrap table-wrap--free" style="border:0;border-radius:0">
    <table class="table-clean">
        <thead>
            <tr>
                <th>Kloter</th>
                <th>File</th>
                <th>Waktu unggah</th>
                <th>Kendaraan</th>
                <th class="num">Karton</th>
                <th class="num">Dealer</th>
                <th>Terisi</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($batches as $b)
                @php $pct = min(100, $b->ratio * 100); $cls = $b->ratio > 1 ? 'is-over' : ($b->isReady() ? 'is-ok' : ''); @endphp
                <tr>
                    <td class="nowrap"><a href="{{ route('batches.show', $b) }}"><strong>{{ $b->code }}</strong></a></td>
                    <td>{{ \Illuminate\Support\Str::limit($b->filename, 34) }}</td>
                    <td class="nowrap">{{ $b->created_at->translatedFormat('d M Y, H:i') }}</td>
                    <td>{{ $b->vehicle?->name ?? '-' }}</td>
                    <td class="num">{{ $fmt($b->carton_total ?? 0, 0) }}</td>
                    <td class="num">{{ $b->dealer_total ?? 0 }}</td>
                    <td>
                        <span class="minibar" title="Volume {{ $fmt($b->volume_ratio * 100, 1) }}%, berat {{ $fmt($b->weight_ratio * 100, 1) }}%">
                            <span class="minibar__track"><span class="minibar__fill {{ $cls }}" style="width: {{ $pct }}%"></span></span>
                            {{ $fmt($b->ratio * 100, 1) }}%
                        </span>
                    </td>
                    <td>@include('partials.status-badge', ['upload' => $b])</td>
                    <td class="actions">
                        <a href="{{ route('batches.show', $b) }}" class="btn-soft btn-soft--sm">Buka</a>
                        @unless ($compact)
                            <a href="{{ route('result', $b->id) }}" class="btn-soft btn-soft--sm">Laporan</a>
                            <form action="{{ route('batches.destroy', $b) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Hapus kloter {{ $b->code }} ({{ e($b->filename) }})? Data karton di kloter ini ikut terhapus dan tidak bisa dikembalikan.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-soft btn-soft--sm btn-soft--danger" aria-label="Hapus kloter {{ $b->code }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
