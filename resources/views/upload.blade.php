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
        <input type="file" name="file" class="form-control" required>
        <button type="submit" class="btn btn-primary mt-3">Upload</button>
    </form>
</body>
</html>
