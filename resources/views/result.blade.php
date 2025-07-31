<!DOCTYPE html>
<html>
<head>
    <title>Hasil Evaluasi Truk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

    <div class="progress mt-3" style="height: 30px;">
        <div class="progress-bar {{ $upload->ratio >= 0.8 ? 'bg-success' : 'bg-warning' }}" 
             role="progressbar" 
             style="width: {{ $upload->ratio * 100 }}%;" 
             aria-valuenow="{{ $upload->ratio * 100 }}" 
             aria-valuemin="0" 
             aria-valuemax="100">
            {{ round($upload->ratio * 100, 2) }}%
        </div>
    </div>

    <h4 class="mt-5">Data Pengiriman dari Excel</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-striped mt-3">
            <thead>
                <tr>
                    <th>Cargo Destination</th>
                    <th>Customer Code</th>
                    <th>Customer Name</th>
                    <th>Outer Case (m³)</th>
                    <th>Status 4 (m³)</th>
                    <th>Status 3 (m³)</th>
                    <th>Status 2 (m³)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $grouped = $upload->boxes->groupBy(['cargo_destination', 'customer_code', 'customer_name']);
                @endphp

                @foreach ($grouped as $destination => $byCode)
                    @foreach ($byCode as $code => $byName)
                        @foreach ($byName as $name => $boxes)
                            @php
                                $outerCase = $boxes->sum('volume');
                                $status_4 = $boxes->where('status', 4)->sum('volume');
                                $status_3 = $boxes->where('status', 3)->sum('volume');
                                $status_2 = $boxes->where('status', 2)->sum('volume');
                            @endphp
                            <tr>
                                <td>{{ $destination }}</td>
                                <td>{{ $code }}</td>
                                <td>{{ $name }}</td>
                                <td>{{ number_format($outerCase, 4) }}</td>
                                <td>{{ number_format($status_4, 4) }}</td>
                                <td>{{ number_format($status_3, 4) }}</td>
                                <td>{{ number_format($status_2, 4) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <a href="/" class="btn btn-secondary mt-4">Upload Lagi</a>
</body>
</html>
