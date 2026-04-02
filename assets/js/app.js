/**
 * SISTEMA DE GESTÃO DE IGREJA - JavaScript Principal
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initSidebar();
    initDropdowns();
    initModals();
    initConfirmModal();
    initToasts();
    initTabs();
    initForms();
    initTables();
    initGlobalSearch();
    
    // Exibir mensagem flash como toast
    if (window._flashMessage) {
        showToast(window._flashMessage.message, window._flashMessage.type);
        delete window._flashMessage;
    }
});

/**
 * Sidebar Toggle
 */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarToggle = document.getElementById('sidebarToggle');

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }
}

/**
 * Dropdowns
 */
function initDropdowns() {
    const dropdownBtns = document.querySelectorAll('[data-dropdown]');
    
    dropdownBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const menu = btn.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                closeAllDropdowns();
                menu.classList.toggle('show');
            }
        });
    });

    // User dropdown
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userDropdownBtn && userDropdown) {
        userDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
    }

    // Close on outside click
    document.addEventListener('click', () => {
        closeAllDropdowns();
    });
}

function closeAllDropdowns() {
    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
        menu.classList.remove('show');
    });
}

/**
 * Modals
 */
function initModals() {
    const modalBackdrop = document.getElementById('modalBackdrop');
    const modal = document.getElementById('modal');
    const modalClose = document.getElementById('modalClose');

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }

    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', closeModal);
    }

    // ESC key closes modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
}

function openModal(options = {}) {
    const modal = document.getElementById('modal');
    const modalBackdrop = document.getElementById('modalBackdrop');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalFooter = document.getElementById('modalFooter');

    if (options.title) modalTitle.textContent = options.title;
    if (options.body) modalBody.innerHTML = options.body;
    if (options.footer) {
        modalFooter.innerHTML = options.footer;
        modalFooter.style.display = 'flex';
    } else {
        modalFooter.style.display = 'none';
    }

    if (options.size) {
        modal.classList.remove('modal-lg', 'modal-xl');
        modal.classList.add(`modal-${options.size}`);
    }

    modal.classList.add('show');
    modalBackdrop.classList.add('show');
    document.body.style.overflow = 'hidden';

    // Re-init lucide icons in modal
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function closeModal() {
    const modal = document.getElementById('modal');
    const modalBackdrop = document.getElementById('modalBackdrop');

    if (modal) modal.classList.remove('show');
    if (modalBackdrop) modalBackdrop.classList.remove('show');
    document.body.style.overflow = '';
}

/**
 * Toasts
 */
function initToasts() {
    window.showToast = showToast;
}

function showToast(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
        success: 'check-circle',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i data-lucide="${icons[type]}" class="toast-icon"></i>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i data-lucide="x"></i>
        </button>
    `;

    container.appendChild(toast);
    lucide.createIcons();

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Tabs
 */
function initTabs() {
    const tabLinks = document.querySelectorAll('.tab-link');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            // Se o link tem href e não tem data-tab, deixa navegar normalmente
            if (link.getAttribute('href') && !link.getAttribute('data-tab')) {
                return; // Permite navegação normal
            }
            
            e.preventDefault();
            const targetId = link.getAttribute('data-tab');
            const tabContainer = link.closest('.tabs').parentElement;

            // Remove active from all tabs
            tabContainer.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
            tabContainer.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            // Add active to clicked
            link.classList.add('active');
            const targetContent = tabContainer.querySelector(`#${targetId}`);
            if (targetContent) targetContent.classList.add('active');
        });
    });
}

/**
 * Forms
 */
function initForms() {
    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });
    });

    // Input masks
    initInputMasks();

    // File upload preview
    initFileUploads();
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('[required]');
    
    inputs.forEach(input => {
        removeError(input);
        
        if (!input.value.trim()) {
            showError(input, 'Este campo é obrigatório');
            isValid = false;
        } else if (input.type === 'email' && !isValidEmail(input.value)) {
            showError(input, 'Email inválido');
            isValid = false;
        }
    });
    
    return isValid;
}

function showError(input, message) {
    input.classList.add('is-invalid');
    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback';
    feedback.textContent = message;
    input.parentNode.appendChild(feedback);
}

function removeError(input) {
    input.classList.remove('is-invalid');
    const feedback = input.parentNode.querySelector('.invalid-feedback');
    if (feedback) feedback.remove();
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function initInputMasks() {
    // CPF Mask
    document.querySelectorAll('[data-mask="cpf"]').forEach(input => {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    });

    // Phone Mask
    document.querySelectorAll('[data-mask="phone"]').forEach(input => {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 10) {
                value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            } else if (value.length > 6) {
                value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
            }
            e.target.value = value;
        });
    });

    // Date Mask
    document.querySelectorAll('[data-mask="date"]').forEach(input => {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) value = value.slice(0, 8);
            value = value.replace(/(\d{2})(\d)/, '$1/$2');
            value = value.replace(/(\d{2})(\d)/, '$1/$2');
            e.target.value = value;
        });
    });

    // Money Mask
    document.querySelectorAll('[data-mask="money"]').forEach(input => {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            value = (parseInt(value) / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            e.target.value = 'R$ ' + value;
        });
    });

    // CEP Mask
    document.querySelectorAll('[data-mask="cep"]').forEach(input => {
        input.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) value = value.slice(0, 8);
            if (value.length > 5) {
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
            }
            e.target.value = value;
        });
    });
}

function initFileUploads() {
    document.querySelectorAll('.file-upload-input').forEach(input => {
        input.addEventListener('change', (e) => {
            const preview = document.querySelector(input.dataset.preview);
            if (preview && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    preview.src = event.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    });
}

/**
 * Tables
 */
function initTables() {
    // Select all checkbox
    document.querySelectorAll('.select-all-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', (e) => {
            const table = e.target.closest('table');
            table.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = e.target.checked;
            });
            updateBulkActions(table);
        });
    });

    // Row checkboxes
    document.querySelectorAll('.row-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', (e) => {
            const table = e.target.closest('table');
            updateBulkActions(table);
        });
    });
}

function updateBulkActions(table) {
    const checkedCount = table.querySelectorAll('.row-checkbox:checked').length;
    const bulkActions = document.querySelector('.bulk-actions');
    if (bulkActions) {
        bulkActions.style.display = checkedCount > 0 ? 'flex' : 'none';
        const countSpan = bulkActions.querySelector('.selected-count');
        if (countSpan) countSpan.textContent = checkedCount;
    }
}

function getSelectedIds() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

/**
 * AJAX Helpers
 */
async function fetchApi(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken || ''
        }
    };

    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    };

    try {
        showLoading();
        const response = await fetch(url, mergedOptions);
        hideLoading();

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        hideLoading();
        console.error('Fetch error:', error);
        showToast('Erro ao processar requisição', 'error');
        throw error;
    }
}

async function postApi(url, data) {
    return fetchApi(url, {
        method: 'POST',
        body: JSON.stringify(data)
    });
}

async function deleteApi(url) {
    return fetchApi(url, {
        method: 'DELETE'
    });
}

/**
 * Loading Overlay
 */
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.add('show');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.remove('show');
}

/**
 * Confirmation Dialog - Modal Personalizado
 */
let confirmCallback = null;

function initConfirmModal() {
    const confirmBackdrop = document.getElementById('confirmBackdrop');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const confirmOkBtn = document.getElementById('confirmOkBtn');

    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', closeConfirmModal);
    }
    if (confirmBackdrop) {
        confirmBackdrop.addEventListener('click', closeConfirmModal);
    }
    if (confirmOkBtn) {
        confirmOkBtn.addEventListener('click', () => {
            const callback = confirmCallback;
            closeConfirmModal();
            if (callback) {
                callback();
            }
        });
    }

    // ESC key closes confirm modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeConfirmModal();
        }
    });
}

function showConfirm(options = {}) {
    const defaults = {
        title: 'Confirmar ação',
        message: 'Tem certeza que deseja realizar esta ação?',
        type: 'danger', // danger, warning, info, success
        confirmText: 'Confirmar',
        cancelText: 'Cancelar',
        icon: 'alert-triangle',
        onConfirm: null
    };

    const settings = { ...defaults, ...options };
    
    const confirmModal = document.getElementById('confirmModal');
    const confirmBackdrop = document.getElementById('confirmBackdrop');
    const confirmIcon = document.getElementById('confirmIcon');
    const confirmTitle = document.getElementById('confirmTitle');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmOkBtn = document.getElementById('confirmOkBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');

    // Set content
    confirmTitle.textContent = settings.title;
    confirmMessage.textContent = settings.message;
    confirmCancelBtn.textContent = settings.cancelText;
    
    // Set icon and type
    confirmIcon.className = 'confirm-icon ' + settings.type;
    confirmIcon.innerHTML = `<i data-lucide="${settings.icon}"></i>`;
    
    // Set button style and text
    confirmOkBtn.className = 'btn btn-' + settings.type;
    confirmOkBtn.innerHTML = `<i data-lucide="${settings.type === 'danger' ? 'trash-2' : 'check'}"></i> ${settings.confirmText}`;
    
    // Store callback
    confirmCallback = settings.onConfirm;

    // Show modal
    confirmBackdrop.classList.add('show');
    confirmModal.classList.add('show');
    document.body.style.overflow = 'hidden';

    // Re-init lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function closeConfirmModal() {
    const confirmModal = document.getElementById('confirmModal');
    const confirmBackdrop = document.getElementById('confirmBackdrop');

    if (confirmModal) confirmModal.classList.remove('show');
    if (confirmBackdrop) confirmBackdrop.classList.remove('show');
    document.body.style.overflow = '';
    confirmCallback = null;
}

// Função de compatibilidade com código antigo
function confirmAction(message, callback) {
    showConfirm({
        title: 'Confirmar ação',
        message: message,
        type: 'warning',
        icon: 'alert-triangle',
        confirmText: 'Confirmar',
        onConfirm: callback
    });
}

/**
 * Delete confirmation - Modal Personalizado
 */
function confirmDelete(url, redirectUrl = null, itemName = 'este item') {
    showConfirm({
        title: 'Excluir registro',
        message: `Tem certeza que deseja excluir ${itemName}? Esta ação não pode ser desfeita.`,
        type: 'danger',
        icon: 'trash-2',
        confirmText: 'Excluir',
        onConfirm: async () => {
            try {
                const response = await deleteApi(url);
                if (response.success) {
                    showToast(response.message || 'Item excluído com sucesso', 'success');
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        location.reload();
                    }
                } else {
                    showToast(response.message || 'Erro ao excluir', 'error');
                }
            } catch (error) {
                showToast('Erro ao excluir item', 'error');
            }
        }
    });
}

/**
 * Date/Time Helpers
 */
function formatDate(dateString, format = 'dd/mm/yyyy') {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return format
        .replace('dd', day)
        .replace('mm', month)
        .replace('yyyy', year)
        .replace('HH', hours)
        .replace('MM', minutes);
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'agora mesmo';
    if (diff < 3600) return `${Math.floor(diff / 60)} min atrás`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h atrás`;
    if (diff < 604800) return `${Math.floor(diff / 86400)} dias atrás`;
    return formatDate(dateString);
}

/**
 * Debounce
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Search functionality
 */
function initSearch(inputId, tableId, columns = []) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!input || !table) return;

    input.addEventListener('input', debounce((e) => {
        const searchTerm = e.target.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            let found = false;
            if (columns.length === 0) {
                found = row.textContent.toLowerCase().includes(searchTerm);
            } else {
                columns.forEach(col => {
                    const cell = row.cells[col];
                    if (cell && cell.textContent.toLowerCase().includes(searchTerm)) {
                        found = true;
                    }
                });
            }
            row.style.display = found ? '' : 'none';
        });
    }, 300));
}

/**
 * Export to Excel
 */
function exportToExcel(tableId, filename = 'export') {
    const table = document.getElementById(tableId);
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            // Skip action columns
            if (!col.classList.contains('actions')) {
                rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
            }
        });
        csv.push(rowData.join(';'));
    });

    const csvContent = '\uFEFF' + csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${filename}_${formatDate(new Date(), 'yyyy-mm-dd')}.csv`;
    link.click();
}

/**
 * Print functionality
 */
function printContent(elementId) {
    const content = document.getElementById(elementId);
    if (!content) return;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Imprimir</title>
            <link rel="stylesheet" href="${window.location.origin}/SISTEMA%20IGREJA%202026/assets/css/app.css">
            <style>
                body { padding: 20px; }
                .no-print { display: none !important; }
            </style>
        </head>
        <body>
            ${content.innerHTML}
            <script>
                window.onload = function() {
                    window.print();
                    window.close();
                }
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

/**
 * Copy to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copiado para a área de transferência', 'success');
    }).catch(() => {
        showToast('Erro ao copiar', 'error');
    });
}

/**
 * QR Code Generator (basic)
 */
function generateQRCode(containerId, text, size = 200) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // Using QR Server API
    container.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(text)}" alt="QR Code">`;
}

// Export functions for global use
window.openModal = openModal;
window.closeModal = closeModal;
window.showToast = showToast;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.confirmAction = confirmAction;
window.confirmDelete = confirmDelete;
window.showConfirm = showConfirm;
window.closeConfirmModal = closeConfirmModal;
/**
 * Busca Global
 */
function initGlobalSearch() {
    const searchInput = document.getElementById('globalSearch');
    const searchResults = document.createElement('div');
    searchResults.className = 'search-results';
    searchResults.style.cssText = 'position: absolute; top: 100%; left: 0; right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-height: 400px; overflow-y: auto; display: none; z-index: 1000; margin-top: 8px;';
    
    if (searchInput) {
        searchInput.parentElement.style.position = 'relative';
        searchInput.parentElement.appendChild(searchResults);
        
        let searchTimeout;
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                performSearch(query, searchResults);
            }, 300);
        });
        
        // Fechar ao clicar fora
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }
}

function performSearch(query, resultsContainer) {
    const appUrl = window.location.origin + '/SISTEMA%20IGREJA%202026';
    fetch(`${appUrl}/api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na resposta');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.results && data.results.length > 0) {
                displaySearchResults(data.results, resultsContainer);
            } else {
                resultsContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">Nenhum resultado encontrado</div>';
                resultsContainer.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Erro na busca:', error);
            resultsContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">Digite para buscar pessoas, eventos ou produtos</div>';
            resultsContainer.style.display = 'block';
        });
}

function displaySearchResults(results, container) {
    let html = '<div style="padding: 8px;">';
    
    results.forEach(result => {
        const icon = getIconForType(result.type);
        html += `
            <a href="${result.url}" style="display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 6px; text-decoration: none; color: #1f2937; transition: background 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                <div style="width: 32px; height: 32px; background: #eff6ff; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="${icon}" style="width: 18px; height: 18px; color: #3b82f6;"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 500; margin-bottom: 2px;">${result.title}</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">${result.subtitle || ''}</div>
                </div>
                <div style="font-size: 0.75rem; color: #9ca3af; text-transform: uppercase;">${result.type}</div>
            </a>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
    container.style.display = 'block';
    
    // Re-inicializar ícones Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function getIconForType(type) {
    const icons = {
        'pessoa': 'user',
        'evento': 'calendar',
        'produto': 'package',
        'ministerio': 'users'
    };
    return icons[type] || 'search';
}


window.fetchApi = fetchApi;
window.postApi = postApi;
window.deleteApi = deleteApi;
window.exportToExcel = exportToExcel;
window.printContent = printContent;
window.copyToClipboard = copyToClipboard;
window.generateQRCode = generateQRCode;
window.initSearch = initSearch;
