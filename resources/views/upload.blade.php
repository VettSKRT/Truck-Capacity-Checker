<!DOCTYPE html>
<html>
<head>
    <title>Upload Data Truk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <h2>Upload File Excel</h2>
    <form action="{{ route('upload') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <label class="form-label mt-2">Kendaraan</label>
        <select name="vehicle" class="form-select mb-3">
            @foreach ($vehicles as $v)
                <option value="{{ $v->code }}" @selected($v->code === config('logistics.default_vehicle'))>
                    {{ $v->name }} - {{ number_format($v->volume_m3, 2) }} m³ / {{ number_format($v->max_payload_kg) }} kg
                </option>
            @endforeach
        </select>
        <input type="file" name="excel_file" class="form-control @error('excel_file') is-invalid @enderror"
               accept=".xlsx,.xls,.csv" required>
        @error('excel_file')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <button type="submit" class="btn btn-primary mt-3">Upload</button>
        <a href="{{ route('packing.index') }}" class="btn btn-secondary mt-3">Kembali</a>
    </form>
</body>
</html>
