<div id="visualization" style="width: 100%; height: 400px;"></div>

<!-- Efficiency Metrics -->
<div class="mt-3">
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Volume Used</h6>
                    <p class="card-text" id="volumeUsed">0.00 m³</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Efficiency</h6>
                    <p class="card-text" id="efficiency">0.0%</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="mt-3">
        <label class="form-label">Container Utilization</label>
        <div class="progress efficiency-progress">
            <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>
</div>
