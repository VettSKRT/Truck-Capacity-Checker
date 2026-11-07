@php
    $fmt = fn ($n, $d = 2) => number_format($n, $d, ',', '.');
    $compact = $compact ?? false;
@endphp
<div class="table-wrap table-wrap--free table-wrap--flush">
    <table class="table-clean">
        <thead>
            <tr>
                <th>Kloter</th>
                <th>File</th>
                <th>Waktu unggah</th>
                <th class="num">Karton</th>
                <th class="num">Dealer</th>
                <th>Jalur (terisi)</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($batches as $b)
                @php $s = $summaries[$b->id]; @endphp
                <tr>
                    <td class="nowrap"><a href="{{ route('batches.show', $b) }}"><strong>{{ $b->code }}</strong></a></td>
                    <td>{{ \Illuminate\Support\Str::limit($b->filename, 34) }}</td>
                    <td class="nowrap">{{ $b->created_at->translatedFormat('d M Y, H:i') }}</td>
                    <td class="num">{{ $fmt($b->carton_total ?? 0, 0) }}</td>
                    <td class="num">{{ $b->dealer_total ?? 0 }}</td>
                    <td>@include('partials.route-chips', ['routes' => $s['routes']])</td>
                    <td>@include('partials.batch-status', ['overall' => $s['overall']])</td>
                    <td class="actions">
                        <a href="{{ route('batches.show', $b) }}" class="btn-soft btn-soft--sm">Buka</a>
                        @unless ($compact)
                            <form action="{{ route('batches.destroy', $b) }}" method="POST" class="d-inline"
                                  data-confirm="File {{ $b->filename }} beserta seluruh data kartonnya akan dihapus permanen dan tidak bisa dikembalikan."
                                  data-confirm-title="Hapus kloter {{ $b->code }}?" data-confirm-ok="Hapus kloter" data-loading="Menghapus kloter">
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
