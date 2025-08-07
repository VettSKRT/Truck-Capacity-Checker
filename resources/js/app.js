import './bootstrap';
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

// Initialize when document is ready
$(document).ready(function() {
    initVisualization();
});