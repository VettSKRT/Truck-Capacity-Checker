<!DOCTYPE html>
<html>
<head>
    <title>Hasil Evaluasi Truk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .destination-progress {
            height: 15px;
            margin-top: 5px;
        }
        .volume-cell {
            min-width: 150px;
        }
        .text-center-icon {
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .view-details {
            cursor: pointer;
            color: #0d6efd;
            font-size: 1.2rem; /* Membuat ikon sedikit lebih besar */
        }
    </style>
</head>
<body class="p-4">
    <h2>Status Truk Logistik</h2>
    <p><strong>Total Volume:</strong> {{ number_format($upload->total_volume, 2) }} m³</p>
    <p><strong>Rasio Pengisian:</strong> {{ round($upload->ratio * 100, 2) }}%</p>
    <p><strong>Status:</strong> 
        <span class="badge {{ $upload->ratio >= 0.8 ? 'bg-success' : 'bg-danger' }}">
            {{ $upload->status }}
        </span>
    </p>

    <h4 class="mt-5">Data Pengiriman dari Excel</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-striped mt-3">
            <thead>
                <tr>
                    <th>Cargo Destination</th>
                    <th>Customer Code</th>
                    <th>Customer Name</th>
                    <th class="volume-cell">Outer Case (m³)</th>
                    <th class="volume-cell">Status 4 (m³)</th>
                    <th class="volume-cell">Status 3 (m³)</th>
                    <th class="volume-cell">Status 2 (m³)</th>
                    <th class="volume-cell">Progress</th>
                    <th class="text-center">Detail</th> <!-- Tambahkan class text-center -->
                </tr>
            </thead>
            <tbody>
                @php
                    $grouped = $upload->boxes->groupBy(['cargo_destination', 'customer_code', 'customer_name']);
                    $maxVolumePerDestination = [];
                    $truckCapacity = 16;
                    
                    // Hitung volume maksimum per destinasi
                    foreach ($upload->boxes->groupBy('cargo_destination') as $dest => $boxes) {
                        $maxVolumePerDestination[$dest] = $truckCapacity;
                    }
                @endphp

                @foreach ($grouped as $destination => $byCode)
                    @foreach ($byCode as $code => $byName)
                        @foreach ($byName as $name => $boxes)
                            @php
                                $outerCase = $boxes->sum('volume');
                                $status_4 = $boxes->where('status', 4)->sum('volume');
                                $status_3 = $boxes->where('status', 3)->sum('volume');
                                $status_2 = $boxes->where('status', 2)->sum('volume');
                                
                                $totalVolumePerDestination = $upload->boxes
                                    ->where('cargo_destination', $destination)
                                    ->sum('volume');
                                    
                                $percentage = ($totalVolumePerDestination / $truckCapacity) * 100;
                                
                                $progressColor = 'bg-info';
                                if ($percentage >= 80) {
                                    $progressColor = 'bg-success';
                                } elseif ($percentage >= 50) {
                                    $progressColor = 'bg-warning';
                                }
                            @endphp
                            <tr>
                                <td>{{ $destination }}</td>
                                <td>{{ $code }}</td>
                                <td>{{ $name }}</td>
                                <td>{{ number_format($outerCase, 4) }}</td>
                                <td>{{ number_format($status_4, 4) }}</td>
                                <td>{{ number_format($status_3, 4) }}</td>
                                <td>{{ number_format($status_2, 4) }}</td>
                                <td>
                                    <div class="progress destination-progress">
                                        <div class="progress-bar {{ $progressColor }}"
                                             role="progressbar"
                                             style="width: {{ $percentage }}%"
                                             aria-valuenow="{{ $percentage }}"
                                             aria-valuemin="0"
                                             aria-valuemax="100"
                                             title="{{ number_format($percentage, 1) }}%">
                                            {{ number_format($percentage, 1) }}%
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center-icon">
                                    <i class="bi bi-eye view-details" 
                                       data-bs-toggle="modal" 
                                       data-bs-target="#detailModal{{ $destination }}_{{ $code }}"></i>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Modal untuk setiap destinasi -->
    @foreach ($grouped as $destination => $byCode)
        @foreach ($byCode as $code => $byName)
            <div class="modal fade" id="detailModal{{ $destination }}_{{ $code }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Detail Ukuran - {{ $destination }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Panjang</th>
                                        <th>Lebar</th>
                                        <th>Tinggi</th>
                                        <th>Volume</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byName as $name => $boxes)
                                        @foreach ($boxes as $box)
                                            <tr>
                                                <td>{{ $box->panjang }}</td>
                                                <td>{{ $box->lebar }}</td>
                                                <td>{{ $box->tinggi }}</td>
                                                <td>{{ number_format($box->volume, 4) }}</td>
                                                <td>{{ $box->status }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach

    <a href="/" class="btn btn-secondary mt-4">Upload Lagi</a>

    <!-- Tambahkan Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
