// Real-time update functionality for packing optimization
let currentUploadId = null;
let plot = null;
const container = {
    length: 16,
    width: 1,
    height: 1
};

function initVisualization() {
    const layout = {
        scene: {
            aspectmode: "data",
            camera: {
                eye: {x: 1.5, y: 1.5, z: 1.5}
            }
        },
        margin: {l: 0, r: 0, t: 0, b: 0},
        showlegend: true
    };

    const data = [{
        type: "mesh3d",
        x: [0, container.length, container.length, 0, 0, container.length, container.length, 0],
        y: [0, 0, container.width, container.width, 0, 0, container.width, container.width],
        z: [0, 0, 0, 0, container.height, container.height, container.height, container.height],
        i: [7, 0, 0, 0, 4, 4, 6, 6, 4, 0, 3, 2],
        j: [3, 4, 1, 2, 5, 6, 5, 2, 0, 1, 6, 3],
        k: [0, 7, 2, 3, 6, 7, 1, 1, 5, 5, 7, 6],
        opacity: 0.3,
        color: '#aaa',
        name: 'Container'
    }];

    Plotly.newPlot('visualization', data, layout);
}

function updateVisualization(items) {
    const data = [{
        type: "mesh3d",
        x: [0, container.length, container.length, 0, 0, container.length, container.length, 0],
        y: [0, 0, container.width, container.width, 0, 0, container.width, container.width],
        z: [0, 0, 0, 0, container.height, container.height, container.height, container.height],
        i: [7, 0, 0, 0, 4, 4, 6, 6, 4, 0, 3, 2],
        j: [3, 4, 1, 2, 5, 6, 5, 2, 0, 1, 6, 3],
        k: [0, 7, 2, 3, 6, 7, 1, 1, 5, 5, 7, 6],
        opacity: 0.3,
        color: '#aaa',
        name: 'Container'
    }];

    // Add items to visualization
    items.forEach((item, index) => {
        const itemMesh = createItemMesh(item, index);
        data.push(itemMesh);
    });

    Plotly.react('visualization', data);
}

function createItemMesh(item, index) {
    const colors = {
        2: '#ffc107',
        3: '#17a2b8',
        4: '#28a745'
    };

    return {
        type: "mesh3d",
        x: [item.x, item.x + item.length, item.x + item.length, item.x,
            item.x, item.x + item.length, item.x + item.length, item.x],
        y: [item.y, item.y, item.y + item.width, item.y + item.width,
            item.y, item.y, item.y + item.width, item.y + item.width],
        z: [item.z, item.z, item.z, item.z,
            item.z + item.height, item.z + item.height, item.z + item.height, item.z + item.height],
        i: [7, 0, 0, 0, 4, 4, 6, 6, 4, 0, 3, 2],
        j: [3, 4, 1, 2, 5, 6, 5, 2, 0, 1, 6, 3],
        k: [0, 7, 2, 3, 6, 7, 1, 1, 5, 5, 7, 6],
        opacity: 0.8,
        color: colors[item.status] || '#aaa',
        name: `Item ${index + 1} (Status ${item.status})`
    };
}

function initRealTimeUpdates() {
    // Handle Excel upload form
    $('#excelUploadForm').on('submit', function(e) {
        e.preventDefault();
        uploadExcelFile();
    });

    // Handle manual form submission
    $('#itemsForm').on('submit', function(e) {
        e.preventDefault();
        addItem();
    });

    // Listen for changes in items
    $(document).on('click', '.item-card', function() {
        const itemId = $(this).data('item-id');
        highlightItem(itemId);
    });

    // Optimize packing when needed
    function optimizePacking() {
        // Get all items from the list
        const items = [];
        $('.item-card').each(function() {
            const $card = $(this);
            const itemId = $card.data('item-id');
            const $text = $card.find('small').first().text();
            const dimensions = $text.match(/(\d+\.?\d*)m × (\d+\.?\d*)m × (\d+\.?\d*)m/);
            const volumeText = $card.find('small').last().text();
            const volume = parseFloat(volumeText.match(/(\d+\.?\d*)/)[1]);
            const status = parseInt($card.find('.badge').text().match(/(\d+)/)[1]);
            
            if (dimensions) {
                items.push({
                    id: itemId,
                    length: parseFloat(dimensions[1]),
                    width: parseFloat(dimensions[2]),
                    height: parseFloat(dimensions[3]),
                    status: status,
                    volume: volume
                });
            }
        });
        
        // Simple packing algorithm
        const packedItems = simplePackingAlgorithm(items);
        
        // Update visualization and metrics
        updateVisualization(packedItems);
        updateEfficiencyMetrics(packedItems);
    }
    
    function simplePackingAlgorithm(items) {
        const containerLength = 16;
        const containerWidth = 1;
        const containerHeight = 1;
        
        let currentX = 0;
        let currentY = 0;
        let currentZ = 0;
        const packedItems = [];
        
        items.forEach((item, index) => {
            // Check if item fits in current position
            if (currentX + item.length <= containerLength &&
                currentY + item.width <= containerWidth &&
                currentZ + item.height <= containerHeight) {
                
                packedItems.push({
                    ...item,
                    x: currentX,
                    y: currentY,
                    z: currentZ
                });
                
                // Move to next position
                currentX += item.length;
                if (currentX >= containerLength) {
                    currentX = 0;
                    currentY += item.width;
                    if (currentY >= containerWidth) {
                        currentY = 0;
                        currentZ += item.height;
                    }
                }
            }
        });
        
        return packedItems;
    }

    function updateEfficiencyMetrics(items) {
        const totalVolume = items.reduce((sum, item) => sum + item.volume, 0);
        const efficiency = (totalVolume / 16) * 100; // 16m³ container volume
        
        $('#volumeUsed').text(`${totalVolume.toFixed(2)} m³`);
        $('#efficiency').text(`${efficiency.toFixed(1)}%`);
        
        // Update progress bar color based on efficiency
        const $progressBar = $('.efficiency-progress .progress-bar');
        $progressBar.css('width', `${efficiency}%`);
        
        if (efficiency >= 80) {
            $progressBar.removeClass('bg-warning bg-danger').addClass('bg-success');
        } else if (efficiency >= 50) {
            $progressBar.removeClass('bg-success bg-danger').addClass('bg-warning');
        } else {
            $progressBar.removeClass('bg-success bg-warning').addClass('bg-danger');
        }
    }

    function highlightItem(itemId) {
        // Remove previous highlights
        $('.item-card').removeClass('selected');
        // Add highlight to selected item
        $(`.item-card[data-item-id="${itemId}"]`).addClass('selected');
        
        // Highlight corresponding 3D item
        highlightMesh(itemId);
    }

    function highlightMesh(itemId) {
        // Implementation for highlighting 3D mesh
        // This can be customized based on your needs
        console.log('Highlighting mesh for item:', itemId);
    }

    // Initial optimization
    optimizePacking();
}

function uploadExcelFile() {
    const formData = new FormData();
    const fileInput = document.getElementById('excel_file');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (fileInput.files.length === 0) {
        alert('Please select a file');
        return;
    }
    
    // Disable button and show loading
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';
    
    formData.append('excel_file', fileInput.files[0]);
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    
    $.ajax({
        url: '/upload',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                // Clear existing items
                $('#itemsList').empty();
                
                // Add items from Excel
                response.items.forEach(item => {
                    addItemFromExcel(item);
                });
                
                // Update data table
                updateDataTable(response.items);
                
                // Trigger optimization
                setTimeout(optimizePacking, 100);
                
                alert('File uploaded successfully! ' + response.items.length + ' items loaded.');
            } else {
                alert('Error loading file: ' + response.message);
            }
        },
        error: function(xhr) {
            console.error('Upload error:', xhr);
            alert('Error uploading file: ' + (xhr.responseJSON?.message || xhr.responseText || 'Unknown error'));
        },
        complete: function() {
            // Re-enable button
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Upload & Load Data';
        }
    });
}

function updateDataTable(items) {
    let tableHtml = `
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Customer Name</th>
                        <th>Destination</th>
                        <th>Dimensions (mm)</th>
                        <th>Volume (m³)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    items.forEach((item, index) => {
        const volume = item.panjang * item.lebar * item.tinggi;
        const statusColor = getStatusColor(item.status);
        
        tableHtml += `
            <tr>
                <td>${index + 1}</td>
                <td>${item.customer_name || 'N/A'}</td>
                <td>${item.cargo_destination || 'N/A'}</td>
                <td>${item.panjang * 1000} × ${item.lebar * 1000} × ${item.tinggi * 1000}</td>
                <td>${volume.toFixed(2)}</td>
                <td><span class="badge bg-${statusColor}">Status ${item.status}</span></td>
            </tr>
        `;
    });
    
    tableHtml += `
                </tbody>
            </table>
        </div>
    `;
    
    $('#dataTableContainer').html(tableHtml);
}

function addItemFromExcel(item) {
    const itemId = Date.now() + Math.random(); // Unique ID
    const volume = item.panjang * item.lebar * item.tinggi;
    
    // Add item card
    const itemCard = `
        <div class="item-card" data-item-id="${itemId}">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${item.customer_name || 'Item ' + itemId}</strong><br>
                    <small>${item.panjang}m × ${item.lebar}m × ${item.tinggi}m</small><br>
                    <small>Volume: ${volume.toFixed(2)} m³</small>
                    ${item.cargo_destination ? `<br><small>Destination: ${item.cargo_destination}</small>` : ''}
                </div>
                <span class="badge bg-${getStatusColor(item.status)}">Status ${item.status}</span>
            </div>
        </div>
    `;
    
    $('#itemsList').append(itemCard);
}

function addItem() {
    const lengthMm = parseFloat($('#itemLength').val());
    const widthMm = parseFloat($('#itemWidth').val());
    const heightMm = parseFloat($('#itemHeight').val());
    const status = parseInt($('#itemStatus').val());
    
    if (lengthMm && widthMm && heightMm) {
        // Convert mm to meters for calculations
        const lengthM = lengthMm / 1000;
        const widthM = widthMm / 1000;
        const heightM = heightMm / 1000;
        const volume = lengthM * widthM * heightM;
        const itemId = Date.now(); // Simple ID generation
        
        // Add item card
        const itemCard = `
            <div class="item-card" data-item-id="${itemId}">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Item ${itemId}</strong><br>
                        <small>${lengthM}m × ${widthM}m × ${heightM}m</small><br>
                        <small>Volume: ${volume.toFixed(2)} m³</small>
                    </div>
                    <span class="badge bg-${getStatusColor(status)}">Status ${status}</span>
                </div>
            </div>
        `;
        
        $('#itemsList').append(itemCard);
        
        // Clear form
        $('#itemsForm')[0].reset();
        
        // Trigger optimization
        setTimeout(optimizePacking, 100);
    }
}

function getStatusColor(status) {
    switch(status) {
        case 2: return 'warning';
        case 3: return 'info';
        case 4: return 'success';
        default: return 'secondary';
    }
}

// Initialize when document is ready
$(document).ready(function() {
    initVisualization();
    initRealTimeUpdates();
});