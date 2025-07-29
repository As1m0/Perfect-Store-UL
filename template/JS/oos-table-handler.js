
// Configuration
const API_BASE_URL = './api/index.php'; // Updated for no mod_rewrite

// State management
let currentData = [];
let categories = [];
let brands = [];
let selectedCategories = [];
let selectedBrands = [];
let selectedStatuses = [];

// Initialize the application
document.addEventListener('DOMContentLoaded', function () {
    initializeMultiSelect();
    loadInitialData();
    setupEventListeners();
});

// Initialize multi-select dropdowns
function initializeMultiSelect() {
    const multiSelects = document.querySelectorAll('.multi-select');
    multiSelects.forEach(select => {
        const input = select.querySelector('input');
        const dropdown = select.querySelector('.multi-select-dropdown');

        input.addEventListener('click', () => {
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!select.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    });
}

// Setup event listeners
function setupEventListeners() {
    // Table header sorting
    document.querySelectorAll('.data-table th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            const column = th.dataset.column;
            document.getElementById('sortColumn').value = column;

            // Toggle sort direction
            const currentDirection = document.getElementById('sortDirection').value;
            document.getElementById('sortDirection').value = currentDirection === 'ASC' ? 'DESC' : 'ASC';

            loadData();
        });
    });

    // Status multi-select
    document.querySelectorAll('#statusDropdown .multi-select-option').forEach(option => {
        option.addEventListener('click', () => {
            const value = option.dataset.value;
            const text = option.textContent;

            if (option.classList.contains('selected')) {
                option.classList.remove('selected');
                selectedStatuses = selectedStatuses.filter(s => s !== value);
            } else {
                option.classList.add('selected');
                selectedStatuses.push(value);
            }

            updateSelectedTags('status', selectedStatuses, getStatusText);
        });
    });
}

// Load initial data for filters
async function loadInitialData() {
    try {
        // Load categories and brands for filters
        const [categoriesResponse, brandsResponse] = await Promise.all([
            fetch(`${API_BASE_URL}/categories`),
            fetch(`${API_BASE_URL}/brands`)
        ]);

        if (categoriesResponse.ok) {
            const categoriesData = await categoriesResponse.json();
            categories = categoriesData.success ? categoriesData.data : [];
            populateMultiSelect('category', categories, 'name');
        }

        if (brandsResponse.ok) {
            const brandsData = await brandsResponse.json();
            brands = brandsData.success ? brandsData.data : [];
            populateMultiSelect('brand', brands, 'name');
        }

        // Load initial data
        loadData();
    } catch (error) {
        console.error('Error loading initial data:', error);
        showError('Failed to load initial data. Please refresh the page.');
    }
}

// Populate multi-select dropdown
function populateMultiSelect(type, data, textField) {
    const dropdown = document.getElementById(`${type}Dropdown`);
    dropdown.innerHTML = '';

    data.forEach(item => {
        const option = document.createElement('div');
        option.className = 'multi-select-option';
        option.dataset.value = item[textField];
        option.textContent = item[textField];

        option.addEventListener('click', () => {
            const value = option.dataset.value;
            const selectedArray = type === 'category' ? selectedCategories : selectedBrands;

            if (option.classList.contains('selected')) {
                option.classList.remove('selected');
                const index = selectedArray.indexOf(value);
                if (index > -1) selectedArray.splice(index, 1);
            } else {
                option.classList.add('selected');
                selectedArray.push(value);
            }

            updateSelectedTags(type, selectedArray);
        });

        dropdown.appendChild(option);
    });
}

// Update selected tags display
function updateSelectedTags(type, selectedArray, textConverter = null) {
    const tagsContainer = document.getElementById(`${type}Tags`);
    tagsContainer.innerHTML = '';

    selectedArray.forEach(value => {
        const tag = document.createElement('span');
        tag.className = 'tag';
        tag.textContent = textConverter ? textConverter(value) : value;

        tag.addEventListener('click', () => {
            const index = selectedArray.indexOf(value);
            if (index > -1) {
                selectedArray.splice(index, 1);
                updateSelectedTags(type, selectedArray, textConverter);

                // Update dropdown selection
                const option = document.querySelector(`#${type}Dropdown [data-value="${value}"]`);
                if (option) option.classList.remove('selected');
            }
        });

        tagsContainer.appendChild(tag);
    });
}

// Get status text for display
function getStatusText(value) {
    const statusMap = { '1': 'In Stock', '0': 'Out of Stock', '-1': 'Unavailable' };
    return statusMap[value] || value;
}

// Load data from API
async function loadData() {
    showLoading();
    hideError();

    try {
        const requestData = {
            shopId: parseInt(document.getElementById('shopSelect').value),
            filters: {},
            sort: {
                column: document.getElementById('sortColumn').value,
                direction: document.getElementById('sortDirection').value
            }
        };

        // Add filters
        if (selectedCategories.length > 0) {
            requestData.filters.category = selectedCategories;
        }
        if (selectedBrands.length > 0) {
            requestData.filters.brand = selectedBrands;
        }
        if (selectedStatuses.length > 0) {
            requestData.filters.last_status = selectedStatuses.map(s => parseInt(s));
        }

        const response = await fetch(`${API_BASE_URL}/oos-data`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestData)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseData = await response.json();

        if (!responseData.success) {
            throw new Error(responseData.message || 'API returned error');
        }

        currentData = responseData.data;
        displayData(responseData.data);
        updateSortHeaders();

    } catch (error) {
        console.error('Error loading data:', error);
        showError('Failed to load data. Please check your connection and try again.');
    } finally {
        hideLoading();
    }
}

// Get product name with link HTML
function getProductNameLink(name, url) {
    if (url && url.trim() !== '') {
        return `<a href="${url}" target="_blank" style="color: #667eea; text-decoration: none; font-weight: 500;">${name}</a>`;
    }
    return name;
}

// Display data in table
function displayData(data) {
    const tableBody = document.getElementById('tableBody');
    const dataTable = document.getElementById('dataTable');
    const noDataMessage = document.getElementById('noDataMessage');

    if (!data || data.length === 0) {
        dataTable.style.display = 'none';
        noDataMessage.style.display = 'block';
        return;
    }



    dataTable.style.display = 'table';
    noDataMessage.style.display = 'none';

    tableBody.innerHTML = '';

    data.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${row.category || '-'}</td>
            <td>${row.subcategory || '-'}</td>
            <td>${row.brand || '-'}</td>
            <td>${row.ean}</td>
            <td>${getProductNameLink(row.name, row.product_url)}</td>
            <td>${getStatusBadge(row.last_status)}</td>
            <td>${getPercentageBar(row.oos_percentage)}</td>
            <td><strong>${row.days_oos}</strong></td>
            <td><a href="?p=edit-product&ean=${row.ean}" target="blank">edit</a></td>
        `;
        tableBody.appendChild(tr);
    });
}

// Get status badge HTML
function getStatusBadge(status) {
    const statusMap = {
        1: { text: 'In Stock', class: 'status-in-stock' },
        0: { text: 'Out of Stock', class: 'status-out-of-stock' },
        '-1': { text: 'Unavailable', class: 'status-unavailable' }
    };

    const statusInfo = statusMap[status] || statusMap['-1'];
    return `<span class="status-badge ${statusInfo.class}">${statusInfo.text}</span>`;
}

// Get percentage bar HTML
function getPercentageBar(percentage) {
    const value = parseFloat(percentage) || 0;
    return `
                <div class="percentage-bar">
                    <div class="percentage-fill" style="width: ${value}%"></div>
                    <div class="percentage-text">${value}%</div>
                </div>
            `;
}

// Update sort headers
function updateSortHeaders() {
    const sortColumn = document.getElementById('sortColumn').value;
    const sortDirection = document.getElementById('sortDirection').value;

    // Reset all headers
    document.querySelectorAll('.data-table th').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });

    // Set current sort header
    const currentHeader = document.querySelector(`[data-column="${sortColumn}"]`);
    if (currentHeader) {
        currentHeader.classList.add(sortDirection === 'ASC' ? 'sort-asc' : 'sort-desc');
    }
}

// Clear all filters
function clearFilters() {
    selectedCategories = [];
    selectedBrands = [];
    selectedStatuses = [];

    // Clear multi-select dropdowns
    document.querySelectorAll('.multi-select-option.selected').forEach(option => {
        option.classList.remove('selected');
    });

    // Clear tags
    document.querySelectorAll('.selected-tags').forEach(container => {
        container.innerHTML = '';
    });

    // Reset sort
    document.getElementById('sortColumn').value = 'category';
    document.getElementById('sortDirection').value = 'ASC';

    // Load data
    loadData();
}

// Show loading indicator
function showLoading() {
    document.getElementById('loadingIndicator').style.display = 'block';
    document.getElementById('dataTable').style.display = 'none';
    document.getElementById('noDataMessage').style.display = 'none';
}

// Hide loading indicator
function hideLoading() {
    document.getElementById('loadingIndicator').style.display = 'none';
}

// Show error message
function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

// Hide error message
function hideError() {
    document.getElementById('errorMessage').style.display = 'none';
}
