
// State management
let currentData = [];
let categories = [];
let subCategories = [];
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
        const [categoriesResponse, subCategoriesResponse, brandsResponse] = await Promise.all([
            fetch(`${API_BASE_URL}/categories`),
            fetch(`${API_BASE_URL}/sub-categories`),
            fetch(`${API_BASE_URL}/brands`)
        ]);

        if (categoriesResponse.ok) {
            const categoriesData = await categoriesResponse.json();
            categories = categoriesData.success ? categoriesData.data : [];
            populateMultiSelect('category', categories, 'name');
            populateNewProductSelect('category', categories, 'name');
        }

        if (brandsResponse.ok) {
            const brandsData = await brandsResponse.json();
            brands = brandsData.success ? brandsData.data : [];
            populateMultiSelect('brand', brands, 'name');
            populateNewProductSelect('brand', brands, 'name');
        }

        if (subCategoriesResponse.ok) {
            const subCategoriesData = await subCategoriesResponse.json();
            subCategories = subCategoriesData.success ? subCategoriesData.data : [];
            populateNewProductSelect('subcategory', subCategories, 'name');
            //console.log('Subcategories loaded:', subCategories);
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

function populateNewProductSelect(type, data, textField, elementName) {
    const dropdown = document.getElementById(`${type}Dropdown2`);
    data.forEach(item => {
        const option = document.createElement('option');
        option.value = item.id; // Assuming each item has an 'id' field
        option.textContent = item[textField];

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


// Load data from API
async function loadData() {
    showLoading();
    hideError();
    try {
        const requestData = {
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

        const response = await fetch(`${API_BASE_URL}/prod-list`, {
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
        //console.log(currentData.length + ' records loaded');
        displayData(responseData.data);
        updateSortHeaders();
    } catch (error) {
        console.error('Error loading data:', error);
        showError('Failed to load data. Please check your connection and try again.');
    } finally {
        hideLoading();
    }
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

    document.getElementById('resultCount').textContent = `Total records: ${data.length}`;

    dataTable.style.display = 'table';
    noDataMessage.style.display = 'none';

    tableBody.innerHTML = '';

    data.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${row.category_name || '-'}</td>
            <td>${row.subcategory_name || '-'}</td>
            <td>${row.brand_name || '-'}</td>
            <td>${row.ean}</td>
            <td>${row.name}</td>
            <td>
            <button onclick="openNewProductForm('edit', '${row.ean}')" class="edit-btn">Edit</button>
            <button onclick="removeProduct('${row.ean}')" class="remove-btn">Remove</button>
            </td>
        `;
        tableBody.appendChild(tr);
    });

}

function editProduct(ean) {
    //TDOD
}

function removeProduct(ean) {
    if (confirm(`Are you sure you want to remove product with EAN: ${ean}?`)) {
        if (confirm(`All history / search / content data will be lost for this product. Are you sure?`)) {
            fetch(`${API_BASE_URL}/remove-product`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ EAN: parseInt(ean) })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showFeedback(`Product with EAN: ${ean} removed successfully.`);
                        loadData(); // Reload data after removal
                    } else {
                        throw new Error(data.message || 'Failed to remove product');
                    }
                })
                .catch(error => {
                    console.error('Error removing product:', error);
                    showError('Failed to remove product. Please try again later.');
                });
        }
    }
}

// Search by product name
document.getElementById('searchName').addEventListener('input', function () {
    const searchTerm = this.value.toLowerCase().trim();
    const filteredData = currentData.filter(row => {
        return row.name.toLowerCase().includes(searchTerm);
    });
    displayData(filteredData);
});

//search by ean
document.getElementById('searchEAN').addEventListener('input', function () {
    const searchTerm = this.value.trim();
    if (searchTerm.length < 8) {
        displayData(currentData); // Reset to full data if search term is too short
        return;
    }
    const filteredData = currentData.filter(row => {
        return row.ean.toString().includes(searchTerm);
    });
    displayData(filteredData);
});


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

    document.getElementById('searchName').value = '';

    document.getElementById('searchEAN').value = '';

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

function showFeedback(message) {
    const feedbackDiv = document.getElementById('feedbackMessage');
    feedbackDiv.textContent = message;
    feedbackDiv.style.display = 'block';
    setTimeout(() => {
        feedbackDiv.style.display = 'none';
    }, 3000);
}

// Hide error message
function hideError() {
    document.getElementById('errorMessage').style.display = 'none';
}

function exportTableToExcel(tableID, filename = '') {
    const table = document.getElementById(tableID);
    const html = table.outerHTML.replace(/ /g, '%20');

    const dataUri = 'data:application/vnd.ms-excel,' + html;

    const link = document.createElement('a');
    link.href = dataUri;
    link.download = filename ? `${filename}.xls` : 'table.xls';
    link.click();
}

// New Product Form Handling
const formLayer = document.getElementById('newProductFormLayer');
const form = document.getElementById('newProductForm');
const eanInput = document.getElementById('newEan');
const nameInput = document.getElementById('newName');
const subcatDropdown = document.getElementById('subcategoryDropdown2');
const catDropdown = document.getElementById('categoryDropdown2');
const brandDropdown = document.getElementById('brandDropdown2');
const addBtn = document.getElementById('addProductButton');
const editBtn = document.getElementById('editProductButton');

let ProductExists = false;
let currentMode = 'add'; // or 'edit'

function resetFormState(mode = 'add') {
    form.reset();
    subcatDropdown.value = '';
    catDropdown.value = '';
    brandDropdown.value = '';
    eanInput.placeholder = '';
    eanInput.readOnly = (mode === 'edit');
    eanInput.required = (mode === 'add');

    addBtn.classList.toggle('d-none', mode !== 'add');
    editBtn.classList.toggle('d-none', mode !== 'edit');
    addBtn.classList.remove('disabled');
    addBtn.value = "add product";

    ProductExists = false;
}

async function apiCheckEan(ean) {
    try {
        const res = await fetch(`${API_BASE_URL}/check-ean`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ EAN: parseInt(ean) })
        });
        const data = await res.json();

        if (data.success && data.data?.length) {
            fillFormFromData(data.data[0]);
            ProductExists = true;
            addBtn.classList.add('disabled');
            addBtn.value = "already exists";
        } else {
            clearFormInputs();
            ProductExists = false;
        }
    } catch (err) {
        console.error('Error checking EAN:', err);
    }
}

function clearFormInputs() {
    subcatDropdown.value = '';
    catDropdown.value = '';
    brandDropdown.value = '';
    nameInput.value = '';
    addBtn.classList.remove('disabled');
    addBtn.value = "add product";
}

function fillFormFromData(p) {
    nameInput.value = p.name || '';
    subcatDropdown.value = p.subcategory_id || '';
    catDropdown.value = p.category_id || '';
    brandDropdown.value = p.brand_id || '';
}

function openNewProductForm(mode, ean = '') {
    currentMode = mode;
    resetFormState(mode);

    if (mode === 'edit') {
        eanInput.value = ean;
        eanInput.placeholder = ean;
        apiCheckEan(ean);
    }
    formLayer.style.display = 'block';
}

function closeNewProductForm() {
    formLayer.style.display = 'none';
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const ean = (currentMode === 'edit') ? eanInput.placeholder.trim() : eanInput.value.trim();
    const name = nameInput.value.trim();
    const categoryId = catDropdown.value;
    const subcategoryId = subcatDropdown.value;
    const brandId = brandDropdown.value;

    if (!ean || !name || !categoryId || !subcategoryId || !brandId) {
        alert('Please fill in all required fields.');
        return;
    }

    if (currentMode === 'add' && ProductExists) {
        showPopUpFeedback('Product already exists. Please edit instead.', false);
        return;
    }

    const endpoint = currentMode === 'add' ? '/add-product' : '/edit-product';
    try {
        const res = await fetch(`${API_BASE_URL}${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                EAN: parseInt(ean),
                name,
                category_id: parseInt(categoryId),
                subcategory_id: parseInt(subcategoryId),
                brand_id: parseInt(brandId),
            })
        });
        const data = await res.json();
        if (data.success) {
            showPopUpFeedback(`Product ${currentMode}ed successfully.`);
            loadData();
            setTimeout(() => {
                closeNewProductForm();
                resetFormState();
            }, 500);
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        console.error(`Error ${currentMode}ing product:`, err);
        showPopUpFeedback(`Failed to ${currentMode} product. Please try again.`, false);
    }
});

// Only check EAN when typing in add mode
eanInput.addEventListener('input', () => {
    if (currentMode === 'add') {
        const ean = eanInput.value.trim();
        if (ean.length >= 8) {
            apiCheckEan(ean);
        } else {
            clearFormInputs();
        }
    }
});