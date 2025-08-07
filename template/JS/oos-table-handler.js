
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
    document.getElementById('startDateTable').value = getDateAgo();
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
        const [shopresponese, categoriesResponse, subCategoriesResponse, brandsResponse] = await Promise.all([
            fetch(`${API_BASE_URL}/shops`),
            fetch(`${API_BASE_URL}/categories`),
            fetch(`${API_BASE_URL}/sub-categories`),
            fetch(`${API_BASE_URL}/brands`)
        ]);

        if (shopresponese.ok) {
            const shopData = await shopresponese.json();
            shops = shopData.success ? shopData.data : [];
            const dropDowns = [document.getElementById('shopDropdown'), document.getElementById('newshopSelect')];
            for (const dropdown of dropDowns) {
                if (!dropdown) continue;
                shops.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id; // Assuming each item has an 'id' field
                    option.textContent = item.name ? item.name.toUpperCase() : '';
                    dropdown.appendChild(option);
                });
            }
            document.getElementById('shopDropdown').selectedIndex = 2;
        }

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

async function untrackProduct(ean, shopId) {
    if (!confirm(`Are you sure you want to untrack product with EAN ${ean} in shop ${shopId}?`)) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/untrack-product`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ EAN: parseInt(ean), shopId: parseInt(shopId) })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseData = await response.json();
        if (!responseData.success) {
            throw new Error(responseData.message || 'API returned error');
        }

        // Reload data after deletion
        loadData();
        showPopUpFeedback(`Product with EAN ${ean} has been successfully untracked.`, true);
    } catch (error) {
        console.error('Error deleting product:', error);
        showError('Failed to untrack product. Please try again.');
    } finally {
        hideLoading();
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

function populateNewProductSelect(type, data, textField, elementName = 'Dropdown2') {
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
            shopId: parseInt(document.getElementById('shopDropdown').value),
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

        // Add date filters
        const startDateInput = document.getElementById('startDateTable');
        const endDateInput = document.getElementById('endDateTable');
        
        if (startDateInput && startDateInput.value) {
            requestData.filters.start_time = startDateInput.value;
        }
        if (endDateInput && endDateInput.value) {
            requestData.filters.end_time = endDateInput.value;
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

    document.getElementById('resultCount').textContent = `Total records: ${data.length}`;

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
            <td><strong>${row.days_oos * 7}</strong></td>
            <td onclick="showProductHistory('${row.ean}', '${document.getElementById('shopDropdown').value}' , event)"><img class="history-icon" src="content/img/history_icon.svg"></td>
            <td>
            <button class="edit-url-btn" onclick="editUrl('${row.ean}', '${document.getElementById('shopDropdown').value}', '${row.product_url}')">EDIT</button>
            <button class="untrack-btn" onclick="untrackProduct('${row.ean}', '${document.getElementById('shopDropdown').value}')">UNTRACK</button>
            </td>
        `;
        tableBody.appendChild(tr);
    });

    let totalOOSScore = 0;
    let sumOOSScore = 0;
    data.forEach(row => {
        const percentage = parseFloat(row.oos_percentage) || 0;
        sumOOSScore += percentage;
    });
    totalOOSScore = (sumOOSScore / data.length).toFixed(1);
    document.getElementById('oos-rate-score').textContent = `${totalOOSScore}%`;

}

// Edit product URL POP-up
function editUrl(ean, shopId, currentUrl) {
    const newUrl = prompt('Enter new product URL:', currentUrl);
    if (newUrl === null || newUrl.trim() === '') {
        return; // User cancelled or entered empty URL
    }

    fetch(`${API_BASE_URL}/edit-product-url`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ EAN: parseInt(ean), shopId: parseInt(shopId), product_url: newUrl })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPopUpFeedback('Product URL updated successfully.', true);
                loadData(); // Reload data to reflect changes
            } else {
                throw new Error(data.message || 'Failed to update product URL');
            }
        })
        .catch(error => {
            console.error('Error updating product URL:', error);
            showPopUpFeedback('Failed to update product URL. Please try again.', false);
        })
}

// Search by product name
document.getElementById('searchName').addEventListener('input', function () {
    const searchTerm = this.value.toLowerCase().trim();
    const filteredData = currentData.filter(row => {
        return row.name.toLowerCase().includes(searchTerm);
    });
    displayData(filteredData);
});

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

    //  clear date filters
    document.getElementById('startDateTable').value = getDateAgo();
    document.getElementById('endDateTable').value = '';

    // Clear tags
    document.querySelectorAll('.selected-tags').forEach(container => {
        container.innerHTML = '';
    });

    // Reset sort
    document.getElementById('sortColumn').value = 'category';
    document.getElementById('sortDirection').value = 'ASC';

    document.getElementById('searchName').value = '';

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
function openNewProductForm() {
    document.getElementById('newProductFormLayer').style.display = 'block';
    document.getElementById('newProductFeedback').style.display = 'none';
    document.getElementById('newProductForm').reset();
}

// Close new product form
function closeNewProductForm() {
    document.getElementById('newProductFormLayer').style.display = 'none';
    document.getElementById('newProductFeedback').style.display = 'none';
}

const NewEanInput = document.getElementById('newEan');
NewEanInput.addEventListener('input', function () {
    const ean = NewEanInput.value.trim();
    if (ean.length >= 8) {
        checkEanExistsInDB(ean);
    }
    else {
        resetNewProductForm();
    }
});


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
        } else {
            ProductExists = false;
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
    document.getElementById('newProductFeedback').textContent = '';
    document.getElementById('newProductFeedback').style.display = 'none';
    document.getElementById('newProductFeedback').style.backgroundColor = '';
}

// Fill form with existing product data
function fillBackDataInFrom(product) {
    document.getElementById('newName').value = product[0].name || '';
    document.getElementById('newName').style.borded = "1px solid green";
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
    const link = document.getElementById('newLink').value.trim();

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
                } else {
                    throw new Error(data.message || 'Failed to add product');
                }
            })
            .catch(error => {
                console.error('Error adding product:', error);
                showPopUpFeedback('Failed to add product. Please try again.', false);
            })
    }

    ProductExists = false; // Reset ProductExists for next submission


    fetch(`${API_BASE_URL}/add-product-link`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            EAN: parseInt(ean),
            shopId: parseInt(document.getElementById('newshopSelect').value),
            product_url: link
        })

    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPopUpFeedback('Product link added successfully.');
                setTimeout(() => {
                    closeNewProductForm();
                    resetNewProductForm();
                    loadData();
                }, 500);
            } else {
                throw new Error(data.message || 'Failed to add product link');
            }
        })
        .catch(error => {
            console.error('Error adding product link:', error);
            showPopUpFeedback('Failed to add product link. Please try again.', false);
        })
}


async function loadProductHistory(ean, shopId, startDate = null, endDate = null) {
    try {
        const response = await fetch(`${API_BASE_URL}/product-history`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ean: parseInt(ean), shop_id: parseInt(shopId), start_date: startDate ? startDate : null, end_date: endDate ? endDate : null  })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        if (data.success && data.data && data.data.length > 0) {
            return data;
        } else {
            return null;
        }
    } catch (error) {
        console.error('Error loading product history:', error);
        return null;
    }
}

function showProductHistory(ean, shopId, event) {
    document.getElementById("productHistoryModal").style.display = "block";
    const triggerElement = event.currentTarget; // or event.target if you want the <img>
    const rect = triggerElement.getBoundingClientRect();

    // Get scroll offsets in case the page is scrolled
    const top = rect.top + window.scrollY;
    const left = rect.left + window.scrollX;

    // Show and position the popup
    const popup = document.getElementById("productHistoryModal");
    popup.style.position = "absolute";
    popup.style.top = `${top + rect.height - 25}px`; // 8px below the element
    popup.style.left = `${left - 220}px`;
    popup.style.display = "block";
    (async () => {
        const history = await loadProductHistory(ean, shopId, document.getElementById('startDateTable').value, document.getElementById('endDateTable').value);

        if (!history || !history.data) {
            console.warn('No product history found.');
            return;
        }


        //console.log('History for ' + ean + ': ', history.data);

        const historyTable = document.getElementById("popUpHistoryTable");

        // Clear any previous rows
        historyTable.innerHTML = '';

        const headerRow = document.createElement('tr');
        headerRow.innerHTML = `
            <th>Shop</th>
            <th>Date</th>
            <th>Stock status</th>
        `;
        historyTable.appendChild(headerRow);

        history.data.reverse().forEach(element => {
            let imgElement = "<img src=''>";
            if(element.is_available == 1)
            {
                imgElement = '<img src="content/img/check.png">'
            }
            else if (element.is_available == 0) {
                imgElement = '<img src="content/img/cross.png">'
            }
            else {
                imgElement = '<img src="content/img/empty_cross.png">'
            }
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${element.shop_name}</td>
                <td>${element.date_checked}</td>
                <td class="history-status-img">${imgElement}</td>
            `;
            historyTable.appendChild(row);
        });
    })();
}

//hide pop-up if clicking outside
document.addEventListener('click', function (event) {
    const popup = document.getElementById('productHistoryModal');

    if (popup.style.display === 'none') return;

    if (popup.contains(event.target)) return;

    if (event.target.classList.contains('history-icon')) return;

    popup.style.display = 'none';
});



