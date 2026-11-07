@extends('layouts.app')

@section('title', 'Kloter ' . $upload->code)
@section('heading', 'Kloter ' . $upload->code)
@section('subheading', $upload->filename . ', diunggah ' . $upload->created_at->translatedFormat('d F Y H:i') . ', ' . number_format($upload->carton_total ?? 0, 0, ',', '.') . ' karton')

@section('actions')
    <a class="btn-soft" href="{{ route('batches.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Riwayat</a>
@endsection

@section('content')
<div id="messages" aria-live="polite"></div>

@include('partials.results', ['uploadId' => $upload->id])

<div class="mt-3">
    @include('partials.schedule-panel', ['upload' => $upload, 'schedule' => $schedule, 'shipments' => $shipments])
</div>
@endsection

@push('scripts')
<script src="https://cdn.plot.ly/plotly-2.35.2.min.js" charset="utf-8"></script>
<script src="{{ asset('js/packing.js') }}?v={{ filemtime(public_path('js/packing.js')) }}"></script>
@endpush
