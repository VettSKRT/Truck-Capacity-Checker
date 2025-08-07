<form id="itemsForm">
    <div class="mb-3">
        <label for="itemLength" class="form-label">Length (mm)</label>
        <input type="number" class="form-control" id="itemLength" step="1" required>
    </div>
    
    <div class="mb-3">
        <label for="itemWidth" class="form-label">Width (mm)</label>
        <input type="number" class="form-control" id="itemWidth" step="1" required>
    </div>
    
    <div class="mb-3">
        <label for="itemHeight" class="form-label">Height (mm)</label>
        <input type="number" class="form-control" id="itemHeight" step="1" required>
    </div>
    
    <div class="mb-3">
        <label for="itemStatus" class="form-label">Status</label>
        <select class="form-control" id="itemStatus" required>
            <option value="2">Status 2</option>
            <option value="3">Status 3</option>
            <option value="4">Status 4</option>
        </select>
    </div>
    
    <button type="submit" class="btn btn-primary">Add Item</button>
</form>

<!-- Items List -->
<div class="mt-4">
    <h6>Items List</h6>
    <div id="itemsList">
        <!-- Item cards will be dynamically added here -->
    </div>
</div>

<style>
.item-card {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.item-card:hover {
    background-color: #f8f9fa;
    border-color: #007bff;
}

.item-card.selected {
    background-color: #007bff;
    color: white;
    border-color: #0056b3;
}

.item-card.selected:hover {
    background-color: #0056b3;
}
</style>
