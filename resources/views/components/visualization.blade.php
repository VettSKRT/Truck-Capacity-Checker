<div id="visualization" style="width: 100%; height: 400px;"></div>

<!-- Efficiency Metrics -->
<div class="mt-3">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Volume Terpacking (semua status)</h6>
                    <p class="card-text" id="volumeUsed">0.00 m³ (0.0%)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Efficiency (Status 4)</h6>
                    <p class="card-text" id="efficiency">0.0%</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Kesiapan Kirim (≥ 80%)</h6>
                    <p class="card-text"><span class="badge bg-secondary" id="readyStatus">-</span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="mt-3">
        <label class="form-label">Container Utilization (Status 4)</label>
        <div class="progress efficiency-progress">
            <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <small class="text-danger" id="packingNote"></small>
    </div>
</div>
