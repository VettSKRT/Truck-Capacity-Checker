@extends('layouts.app')

@section('title', 'Container Packing')

@section('content')
<div class="row mt-4">
    <div class="col-md-4">
        <!-- Upload Excel Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Upload Excel/CSV Data</h5>
            </div>
            <div class="card-body">
                <form id="excelUploadForm" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="excel_file" class="form-label">Select Excel/CSV File</label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                        <small class="text-muted">File should contain: Cargo Destination, Customer Code, Customer Name, Panjang, Lebar, Tinggi, Status</small>
                    </div>
                    <button type="submit" class="btn btn-success" id="uploadBtn">Upload & Load Data</button>
                </form>
            </div>
        </div>

        <!-- Form Container -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Container Settings</h5>
            </div>
            <div class="card-body">
                @include('components.container-form')
            </div>
        </div>

        <!-- Form Items -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Manual Items Entry</h5>
            </div>
            <div class="card-body">
                @include('components.items-form')
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Data Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Excel Data Table</h5>
            </div>
            <div class="card-body">
                <div id="dataTableContainer">
                    <p class="text-muted text-center">Upload Excel/CSV file to see data table here</p>
                </div>
            </div>
        </div>

        <!-- 3D Visualization -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Real-time Preview</h5>
            </div>
            <div class="card-body">
                @include('components.visualization')
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/packing.js') }}"></script>
@endpush