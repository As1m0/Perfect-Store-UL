
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


// Open new product form
function openNewProductForm(method, ean = null) {
    resetNewProductForm();
    const NewEanInput = document.getElementById('newEan');

    if (method === 'edit') {
        document.getElementById('editProductButton').classList.remove('d-none');
        document.getElementById('newProductForm').setAttribute('onsubmit', 'editProduct(event)');
        checkEanExistsInDB(ean);
        NewEanInput.value = ean;
        NewEanInput.readOnly = true;
    } else if (method === 'add') {
        document.getElementById('addProductButton').classList.remove('d-none');
        document.getElementById('newProductForm').setAttribute('onsubmit', 'submitNewProductForm(event)');
        NewEanInput.addEventListener('input', function () {
            const ean = NewEanInput.value.trim();
            if (ean.length >= 8) {
                checkEanExistsInDB(ean);
            }
            else {
                resetNewProductForm();
            }
        });
    }

    document.getElementById('newProductFormLayer').style.display = 'block';
    document.getElementById('newProductForm').reset();
}

// Close new product form
function closeNewProductForm() {
    document.getElementById('newProductFormLayer').style.display = 'none';
    document.getElementById('editProductButton').classList.add('d-none');
    document.getElementById('addProductButton').classList.add('d-none');
}


let ProductExists = false;

// Check if EAN exists in the database
async function checkEanExistsInDB(ean) {
    try {
        const response = await fetch(`${API_BASE_URL}/check-ean`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ EAN: parseInt(ean) })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        if (data.success && data.data && data.data.length > 0) {
            console.log('EAN exists in the database:', data);
            ProductExists = true;
            fillBackDataInFrom(data.data);
            document.getElementById('addProductButton').classList.add('disabled');
            document.getElementById('addProductButton').value = "already exists";
        } else {
            ProductExists = false;
            document.getElementById('addProductButton').classList.remove('disabled');
            document.getElementById('addProductButton').value = "add product";
            resetNewProductForm();
            console.log('EAN does not exist in the database:', data.message);
        }
    } catch (error) {
        console.error('Error checking EAN:', error);
        //showPopUpFeedback('Error checking EAN. Please try again.', false);
    }
}

function resetNewProductForm() {
    //document.getElementById('newProductForm').reset();
    document.getElementById('subcategoryDropdown2').value = '';
    document.getElementById('newName').value = '';
    document.getElementById('categoryDropdown2').value = '';
    document.getElementById('brandDropdown2').value = '';
    document.getElementById('addProductButton').classList.remove('disabled');
    document.getElementById('addProductButton').value = "add product";
}

// Fill form with existing product data
function fillBackDataInFrom(product) {
    document.getElementById('newName').value = product[0].name || '';
    document.getElementById('subcategoryDropdown2').value = product[0].subcategory_id || '';
    document.getElementById('categoryDropdown2').value = product[0].category_id || '';
    document.getElementById('brandDropdown2').value = product[0].brand_id || '';
}

function submitNewProductForm(event) {
    event.preventDefault();
    const ean = document.getElementById('newEan').value.trim();
    const name = document.getElementById('newName').value.trim();
    const categoryId = document.getElementById('categoryDropdown2').value;
    const subcategoryId = document.getElementById('subcategoryDropdown2').value;
    const brandId = document.getElementById('brandDropdown2').value;

    if (!ean || !name || !categoryId || !subcategoryId || !brandId) {
        alert('Please fill in all required fields.');
        return;
    }
    if (!ProductExists) {
        fetch(`${API_BASE_URL}/add-product`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                EAN: parseInt(ean),
                name,
                category_id: parseInt(categoryId),
                subcategory_id: parseInt(subcategoryId),
                brand_id: parseInt(brandId),
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showPopUpFeedback('Product added successfully.');

                    loadData(); // Reload data after adding product
                    setTimeout(() => {
                        resetNewProductForm();
                        document.getElementById('newProductFormLayer').style.display = 'none';
                        document.getElementById('newProductForm').reset();
                    }, 500);
                } else {
                    throw new Error(data.message || 'Failed to add product');
                }
            })
            .catch(error => {
                console.error('Error adding product:', error);
                showPopUpFeedback('Failed to add product. Please try again.', false);
            })
    }
    else {
        showPopUpFeedback('Product already exists in the database. Please edit it instead.', false);
    }

    ProductExists = false; // Reset ProductExists for next submission

}

function editProduct(event) {
    event.preventDefault();
    const ean = document.getElementById('newEan').value.trim();
    const name = document.getElementById('newName').value.trim();
    const categoryId = document.getElementById('categoryDropdown2').value;
    const subcategoryId = document.getElementById('subcategoryDropdown2').value;
    const brandId = document.getElementById('brandDropdown2').value;

    if (!ean || !name || !categoryId || !subcategoryId || !brandId) {
        alert('Please fill in all required fields.');
        return;
    }

    fetch(`${API_BASE_URL}/edit-product`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            EAN: parseInt(ean),
            name,
            category_id: parseInt(categoryId),
            subcategory_id: parseInt(subcategoryId),
            brand_id: parseInt(brandId),
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPopUpFeedback('Product edited successfully.');

                loadData(); // Reload data after editing product
                setTimeout(() => {
                    resetNewProductForm();
                    document.getElementById('newProductFormLayer').style.display = 'none';
                    document.getElementById('newProductForm').reset();
                }, 500);
            } else {
                throw new Error(data.message || 'Failed to edit product');
            }
        })
        .catch(error => {
            console.error('Error editing product:', error);
            showPopUpFeedback('Failed to edit product. Please try again.', false);
        });
}