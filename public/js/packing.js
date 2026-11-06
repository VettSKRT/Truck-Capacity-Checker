// Truck Capacity Checker - packing page
// State disimpan di memori (bukan di-parse dari DOM) agar akurat dan aman.

const container = { length: 16, width: 1, height: 1 };
const CONTAINER_VOLUME = container.length * container.width * container.height;
const READY_RATIO = 0.8; // Status 4 >= 80% => siap dikirim
const STATUS_COLORS = { 2: '#ffc107', 3: '#17a2b8', 4: '#28a745' };
const MESH_I = [7, 0, 0, 0, 4, 4, 6, 6, 4, 0, 3, 2];
const MESH_J = [3, 4, 1, 2, 5, 6, 5, 2, 0, 1, 6, 3];
const MESH_K = [0, 7, 2, 3, 6, 7, 1, 1, 5, 5, 7, 6];

let items = [];          // semua item (dimensi dalam meter)
let packedItems = [];    // hasil packing (dengan posisi x,y,z)
let nextItemId = 1;
let selectedItemId = null;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

function round3(n) {
    return Math.round(n * 1000) / 1000;
}

function getStatusColor(status) {
    switch (Number(status)) {
        case 2: return 'warning';
        case 3: return 'info';
        case 4: return 'success';
        default: return 'secondary';
    }
}

/* ---------- 3D visualization ---------- */

function containerMesh() {
    return {
        type: 'mesh3d',
        x: [0, container.length, container.length, 0, 0, container.length, container.length, 0],
        y: [0, 0, container.width, container.width, 0, 0, container.width, container.width],
        z: [0, 0, 0, 0, container.height, container.height, container.height, container.height],
        i: MESH_I, j: MESH_J, k: MESH_K,
        opacity: 0.3,
        color: '#aaa',
        name: 'Container',
        hoverinfo: 'name'
    };
}

function createItemMesh(item, index) {
    const selected = item.id === selectedItemId;
    return {
        type: 'mesh3d',
        x: [item.x, item.x + item.length, item.x + item.length, item.x,
            item.x, item.x + item.length, item.x + item.length, item.x],
        y: [item.y, item.y, item.y + item.width, item.y + item.width,
            item.y, item.y, item.y + item.width, item.y + item.width],
        z: [item.z, item.z, item.z, item.z,
            item.z + item.height, item.z + item.height, item.z + item.height, item.z + item.height],
        i: MESH_I, j: MESH_J, k: MESH_K,
        opacity: selected ? 1 : 0.8,
        color: selected ? '#dc3545' : (STATUS_COLORS[item.status] || '#aaa'),
        flatshading: true,
        name: `Item ${index + 1} (Status ${item.status})`
    };
}

function renderVisualization() {
    const layout = {
        scene: {
            aspectmode: 'data',
            camera: { eye: { x: 1.5, y: 1.5, z: 1.5 } }
        },
        margin: { l: 0, r: 0, t: 0, b: 0 },
        showlegend: false
    };
    const data = [containerMesh(), ...packedItems.map(createItemMesh)];
    Plotly.react('visualization', data, layout);
}

/* ---------- Packing (shelf algorithm) ---------- */

function packItems(list) {
    // Prioritas: status tertinggi dulu, lalu volume terbesar (sama seperti backend)
    const sorted = [...list].sort((a, b) => b.status - a.status || b.volume - a.volume);

    const eps = 1e-9;
    let x = 0, y = 0, z = 0, rowDepth = 0, layerHeight = 0;
    const packed = [];

    sorted.forEach(item => {
        if (item.length > container.length + eps ||
            item.width > container.width + eps ||
            item.height > container.height + eps) {
            return;
        }

        if (x + item.length > container.length + eps) {   // baris baru
            x = 0;
            y += rowDepth;
            rowDepth = 0;
        }
        if (y + item.width > container.width + eps) {     // lapisan baru
            x = 0;
            y = 0;
            z += layerHeight;
            rowDepth = 0;
            layerHeight = 0;
        }
        if (z + item.height > container.height + eps) {   // container penuh
            return;
        }

        packed.push({ ...item, x, y, z });
        x += item.length;
        rowDepth = Math.max(rowDepth, item.width);
        layerHeight = Math.max(layerHeight, item.height);
    });

    return packed;
}

function optimizePacking() {
    packedItems = packItems(items);
    renderVisualization();
    updateEfficiencyMetrics();
    renderItemList();
}

/* ---------- Metrics ---------- */

function updateEfficiencyMetrics() {
    const packedVolume = packedItems.reduce((s, i) => s + i.volume, 0);
    const status4Volume = packedItems
        .filter(i => i.status === 4)
        .reduce((s, i) => s + i.volume, 0);
    const ratio = status4Volume / CONTAINER_VOLUME;
    const efficiency = ratio * 100;

    $('#volumeUsed').text(`${packedVolume.toFixed(2)} m³ (${((packedVolume / CONTAINER_VOLUME) * 100).toFixed(1)}%)`);
    $('#efficiency').text(`${efficiency.toFixed(1)}%`);

    const ready = ratio >= READY_RATIO;
    $('#readyStatus')
        .text(items.length === 0 ? '-' : (ready ? 'Siap Dikirim' : 'Belum Siap'))
        .removeClass('bg-success bg-danger bg-secondary')
        .addClass(items.length === 0 ? 'bg-secondary' : (ready ? 'bg-success' : 'bg-danger'));

    const $bar = $('.efficiency-progress .progress-bar');
    $bar.css('width', `${Math.min(efficiency, 100)}%`).attr('aria-valuenow', efficiency.toFixed(1));
    if (efficiency >= 80) {
        $bar.removeClass('bg-warning bg-danger').addClass('bg-success');
    } else if (efficiency >= 50) {
        $bar.removeClass('bg-success bg-danger').addClass('bg-warning');
    } else {
        $bar.removeClass('bg-success bg-warning').addClass('bg-danger');
    }

    const skipped = items.length - packedItems.length;
    $('#packingNote').text(skipped > 0 ? `${skipped} item tidak muat di container.` : '');
}

/* ---------- Item list & table ---------- */

function renderItemList() {
    const packedIds = new Set(packedItems.map(i => i.id));
    const html = items.map((item, index) => {
        const fits = packedIds.has(item.id);
        const title = item.customer_name || `Item ${index + 1}`;
        return `
        <div class="item-card ${item.id === selectedItemId ? 'selected' : ''}" data-item-id="${item.id}">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${escapeHtml(title)}</strong><br>
                    <small>${item.length}m × ${item.width}m × ${item.height}m</small><br>
                    <small>Volume: ${item.volume.toFixed(2)} m³</small>
                    ${item.cargo_destination ? `<br><small>Destination: ${escapeHtml(item.cargo_destination)}</small>` : ''}
                    ${fits ? '' : '<br><small class="text-danger">Tidak muat di container</small>'}
                </div>
                <span class="badge bg-${getStatusColor(item.status)}">Status ${item.status}</span>
            </div>
        </div>`;
    }).join('');
    $('#itemsList').html(html);
}

function updateDataTable(list) {
    if (!list.length) {
        $('#dataTableContainer').html('<p class="text-muted text-center">Upload Excel/CSV file to see data table here</p>');
        return;
    }
    const rows = list.map((item, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(item.customer_name) || 'N/A'}</td>
            <td>${escapeHtml(item.cargo_destination) || 'N/A'}</td>
            <td>${Math.round(item.length * 1000)} × ${Math.round(item.width * 1000)} × ${Math.round(item.height * 1000)}</td>
            <td>${item.volume.toFixed(2)}</td>
            <td><span class="badge bg-${getStatusColor(item.status)}">Status ${item.status}</span></td>
        </tr>`).join('');

    $('#dataTableContainer').html(`
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>No</th><th>Customer Name</th><th>Destination</th>
                        <th>Dimensions (mm)</th><th>Volume (m³)</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`);
}

/* ---------- Selection / highlight ---------- */

function highlightItem(itemId) {
    selectedItemId = selectedItemId === itemId ? null : itemId; // klik lagi = batal pilih
    $('.item-card').removeClass('selected');
    if (selectedItemId !== null) {
        $(`.item-card[data-item-id="${selectedItemId}"]`).addClass('selected');
    }
    renderVisualization();
}

/* ---------- Actions ---------- */

function normalizeItem(raw, id) {
    const length = round3(Number(raw.panjang ?? raw.length));
    const width = round3(Number(raw.lebar ?? raw.width));
    const height = round3(Number(raw.tinggi ?? raw.height));
    return {
        id,
        customer_name: raw.customer_name || '',
        customer_code: raw.customer_code || '',
        cargo_destination: raw.cargo_destination || '',
        length, width, height,
        status: Number(raw.status),
        volume: length * width * height
    };
}

function addItem() {
    const lengthMm = parseFloat($('#itemLength').val());
    const widthMm = parseFloat($('#itemWidth').val());
    const heightMm = parseFloat($('#itemHeight').val());
    const status = parseInt($('#itemStatus').val(), 10);

    if (!(lengthMm > 0 && widthMm > 0 && heightMm > 0)) {
        alert('Panjang, lebar, dan tinggi harus lebih dari 0.');
        return;
    }

    items.push(normalizeItem({
        panjang: lengthMm / 1000,
        lebar: widthMm / 1000,
        tinggi: heightMm / 1000,
        status
    }, nextItemId++));

    $('#itemsForm')[0].reset();
    optimizePacking();
}

function uploadExcelFile() {
    const fileInput = document.getElementById('excel_file');
    const uploadBtn = document.getElementById('uploadBtn');

    if (fileInput.files.length === 0) {
        alert('Please select a file');
        return;
    }

    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';

    const formData = new FormData();
    formData.append('excel_file', fileInput.files[0]);
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

    $.ajax({
        url: '/upload',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: { 'Accept': 'application/json' },
        success: function (response) {
            if (!response.success) {
                alert('Error loading file: ' + response.message);
                return;
            }
            selectedItemId = null;
            items = response.items.map(raw => normalizeItem(raw, nextItemId++));
            updateDataTable(items);
            optimizePacking();

            let msg = `File uploaded successfully! ${items.length} items loaded.`;
            if (response.warnings && response.warnings.length) {
                msg += '\n\nBaris dilewati:\n' + response.warnings.slice(0, 5).join('\n');
            }
            alert(msg);
        },
        error: function (xhr) {
            console.error('Upload error:', xhr);
            const errors = xhr.responseJSON?.errors;
            const detail = errors ? Object.values(errors).flat().join('\n') : null;
            alert('Error uploading file: ' + (detail || xhr.responseJSON?.message || 'Unknown error'));
        },
        complete: function () {
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Upload & Load Data';
        }
    });
}

/* ---------- Init ---------- */

$(document).ready(function () {
    $('#excelUploadForm').on('submit', function (e) {
        e.preventDefault();
        uploadExcelFile();
    });

    $('#itemsForm').on('submit', function (e) {
        e.preventDefault();
        addItem();
    });

    $(document).on('click', '.item-card', function () {
        highlightItem($(this).data('item-id'));
    });

    optimizePacking();
});
