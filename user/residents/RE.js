const toggleSidebar = document.getElementById('toggleSidebar');
const sidebar = document.getElementById('sidebar');

toggleSidebar.onclick = () => {
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent =
        toggleSidebar.textContent === 'chevron_left' ? 'chevron_right' : 'chevron_left';
};

document.getElementById('perPageSelect')?.addEventListener('change', function () {
    window.location.href = `?page=1&perPage=${this.value}`;
});

// ===================== TAB LOGIC =====================
function setupTabs(tabContainerId, personalId, otherId) {
    const tabs = document.querySelectorAll(`#${tabContainerId} button`);
    const toggleTabFields = (personalVisible) => {
        document.getElementById(personalId).classList.toggle('hidden', !personalVisible);
        document.getElementById(otherId).classList.toggle('hidden', personalVisible);
        // Do NOT disable fields; keep them enabled for form submission
    };
    toggleTabFields(true); // default personal tab
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            tabs.forEach(b => {
                b.classList.remove('border-green-500');
                b.classList.add('border-transparent');
            });
            this.classList.add('border-green-500');
            this.classList.remove('border-transparent');
            const isPersonal = this.dataset.tab === 'personal';
            toggleTabFields(isPersonal);
        });
    });
}

function switchToTab(tabContainerId, tabName) {
    const tabs = document.querySelectorAll(`#${tabContainerId} button`);
    tabs.forEach(b => {
        b.classList.remove('border-green-500');
        b.classList.add('border-transparent');
        if (b.dataset.tab === tabName) {
            b.classList.add('border-green-500');
            b.classList.remove('border-transparent');
        }
    });
    const personalTab = document.getElementById(tabName === 'add' ? 'addPersonalTab' : 'personalTab');
    const otherTab = document.getElementById(tabName === 'add' ? 'addOtherTab' : 'otherTab');
    personalTab.classList.toggle('hidden', tabName !== 'personal');
    otherTab.classList.toggle('hidden', tabName !== 'other');
    // Keep inputs enabled to preserve submitted data
}

// ===================== AGE CALCULATION =====================
function setupAgeCalculation(birthdateInputSelector, ageSelector, seniorSelector) {
    const birthInput = document.querySelector(birthdateInputSelector);
    if (!birthInput) return;
    birthInput.addEventListener('change', function () {
        if (!this.value) return;
        const birth = new Date(this.value);
        if (isNaN(birth)) return;
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        const form = this.closest('form');
        const ageInput = form.querySelector(ageSelector);
        const seniorCheckbox = form.querySelector(seniorSelector);
        if (ageInput) ageInput.value = age;
        if (seniorCheckbox) seniorCheckbox.checked = age >= 60;
    });
}

// ===================== LETTERS ONLY =====================
function setupLettersOnly(fields) {
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('keydown', e => {
            if (['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) return;
            if (e.key.length === 1 && !/^[a-zA-Z\s]$/.test(e.key)) e.preventDefault();
        });
    });
}
setupLettersOnly(['first_name', 'middle_name', 'last_name', 'alias', 'suffix', 'religion', 'occupation', 'education']);

// ===================== NUMBERS ONLY =====================
function setupNumbersOnly(fields, maxLength = null) {
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('keydown', e => {
            if (['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(e.key)) return;
            if (e.key.length === 1 && !/^[0-9]$/.test(e.key)) e.preventDefault();
            if (maxLength && el.value.length >= maxLength) e.preventDefault();
        });
        el.addEventListener('paste', e => {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            if (!/^\d*$/.test(paste)) e.preventDefault();
            if (maxLength && paste.length + el.value.length > maxLength) e.preventDefault();
        });
    });
}
setupNumbersOnly(['contact_number', 'philsys_card_no']);

// ===================== MODAL SETUP =====================
function setupModal(modalId, openBtnId = null, closeBtnId = null) {
    const modal = document.getElementById(modalId);
    document.getElementById(openBtnId)?.addEventListener('click', () => {
        modal.classList.remove('hidden');
    });
    document.getElementById(closeBtnId)?.addEventListener('click', () => {
        modal.classList.add('hidden');
    });
    return modal;
}

// ===================== RESIDENT ROWS =====================
function setupResidentRows() {
    const modal = document.getElementById('residentModal');
    document.querySelectorAll('#residentsTable tbody tr').forEach(row => {
        row.addEventListener('click', () => {
            const data = JSON.parse(row.dataset.resident);

            Object.keys(data).forEach(key => {
                const el = document.getElementById(key);
                if (!el) return;

                if (el.tagName === 'INPUT') {
                    if (el.type === 'checkbox') el.checked = data[key] == 1;
                    else el.value = data[key] ?? '';
                } else if (el.tagName === 'SELECT') {
                    if ([...el.options].some(o => o.value === data[key])) el.value = data[key];
                    else el.selectedIndex = 0;
                } else if (el.tagName === 'TEXTAREA') {
                    el.value = data[key] ?? '';
                }
            });

            updateAgeAndSenior(data.birthdate);
            switchToTab('modalTabs', 'personal'); // default to personal tab
            modal.classList.remove('hidden');
        });
    });
}

// ===================== AGE UPDATE =====================
function updateAgeAndSenior(birthdate) {
    const birth = new Date(birthdate);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    const ageInput = document.querySelector('#residentForm input[name="age"]');
    const seniorCheckbox = document.querySelector('#residentForm input[name="is_senior"]');
    if (ageInput) ageInput.value = age;
    if (seniorCheckbox) seniorCheckbox.checked = age >= 60;
}

// ===================== SUCCESS MODAL =====================
function showResidentModalMessage(message, type = 'success', closeMainModalId = null) {
    const modal = document.getElementById('successModal');
    const messageEl = document.getElementById('successMessage');
    const icon = modal.querySelector('span.material-icons');
    messageEl.textContent = message;
    icon.classList.remove('text-green-500', 'text-red-500', 'text-yellow-500');
    if (type === 'success') {
        icon.textContent = 'check_circle';
        icon.classList.add('text-green-500');
    } else {
        icon.textContent = type === 'warning' ? 'warning_amber' : 'cancel';
        icon.classList.add(type === 'warning' ? 'text-yellow-500' : 'text-red-500');
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (closeMainModalId) document.getElementById(closeMainModalId)?.classList.add('hidden');

    const okBtn = document.getElementById('okSuccessModal');
    const closeBtn = document.getElementById('closeSuccessModal');
    okBtn.onclick = closeBtn.onclick = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
}

// ===================== INITIALIZATION =====================
document.addEventListener('DOMContentLoaded', () => {
    const msg = document.body.dataset.success;
    if (msg) {
        let type = msg.match(/error|required|must|no changes/i) ? 'warning' : 'success';
        showResidentModalMessage(msg, type);
    }

    setupTabs('modalTabs', 'personalTab', 'otherTab');
    setupTabs('addModalTabs', 'addPersonalTab', 'addOtherTab');
    setupAgeCalculation('#birthdate', 'input[name="age"]', '#is_senior');
    setupAgeCalculation('#addPersonalTab input[name="birthdate"]', 'input[name="age"]', 'input[name="is_senior"]');
    setupModal('addResidentModal', 'addResidentBtn', 'closeAddModal');
    setupModal('residentModal', null, 'closeModal');
    setupResidentRows();
});


document.addEventListener('DOMContentLoaded', () => {
    const sortSelect = document.getElementById("sortSelect");
    if (sortSelect) {
        sortSelect.addEventListener("change", function() {
            const url = new URL(window.location.href);
            url.searchParams.set("sort", this.value);
            window.location.href = url.toString();
        });
    }

    const params = new URLSearchParams(window.location.search);
    const addModal = document.getElementById('addResidentModal');
    const closeAddBtn = document.getElementById('closeAddModal');
    if (params.get('openAdd') === 'true' && addModal) addModal.classList.remove('hidden');
    if (closeAddBtn) closeAddBtn.onclick = () => addModal.classList.add('hidden');

    const searchInput = document.getElementById('searchInput');
    const tableBody = document.querySelector('#residentsTable tbody');
    const tableRows = tableBody?.querySelectorAll('tr') || [];
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const filter = searchInput.value.toLowerCase();
            let hasVisible = false;
            tableRows.forEach(row => {
                if (row.dataset.resident) {
                    const match = row.innerText.toLowerCase().includes(filter);
                    row.style.display = match ? '' : 'none';
                    if (match) hasVisible = true;
                }
            });
            let noFoundRow = tableBody.querySelector('.no-found-row');
            if (!noFoundRow) {
                noFoundRow = document.createElement('tr');
                noFoundRow.classList.add('no-found-row');
                noFoundRow.innerHTML = `<td colspan="6" class="px-4 py-2 text-center text-gray-500">No residents found.</td>`;
                tableBody.appendChild(noFoundRow);
            }
            noFoundRow.style.display = hasVisible ? 'none' : '';
        });
    }
});

// --- HELPER FUNCTIONS ---
const $ = id => document.getElementById(id);
const show = el => el?.classList.remove('hidden');
const hide = el => el?.classList.add('hidden');
const hideAllModals = () => [deleteModal, archivedModal, successModal].forEach(hide);

// --- ELEMENTS ---
const deleteModal = $('deleteConfirmModal');
const deleteResidentIdInput = $('delete_resident_id');
const cancelDeleteBtn = $('cancelDelete');
const successModal = $('successModal');
const successMessage = $('successMessage');
const closeSuccessModal = $('closeSuccessModal');
const deleteBtn = $('deleteResident');
const archivedModal = $('archivedResidentModal');
const closeArchivedModal = $('closeArchivedModal');
const archivedTbody = document.querySelector('#archivedResidentsTable tbody');
const selectAllArchived = $('selectAllArchived');
const restoreBtn = $('restoreResident'); // single restore in modal
const deleteArchivedResidentBtn = $('deleteArchivedResident');
const restoreSelectedBtn = $('restoreSelectedArchived'); // bulk restore
const deleteSelectedBtn = $('deleteSelectedArchived');    // bulk delete

// --- OPEN DELETE MODAL ---
function openDeleteModal(action, value) {
    deleteModal.dataset.action = action;
    deleteResidentIdInput.value = value;
    hideAllModals();
    show(deleteModal);
}

// --- SINGLE DELETE BUTTON ---
deleteBtn?.addEventListener('click', () => openDeleteModal('archive', $('resident_id').value));

// --- BULK DELETE ---
deleteSelectedBtn?.addEventListener('click', () => {
    const selected = Array.from(document.querySelectorAll('.selectArchived:checked')).map(cb => cb.value);
    if (!selected.length) return;
    openDeleteModal('bulk_delete', JSON.stringify(selected));
});

// --- BULK RESTORE ---
restoreSelectedBtn?.addEventListener('click', () => {
    const selected = Array.from(document.querySelectorAll('.selectArchived:checked')).map(cb => cb.value);
    if (!selected.length) return;

    const formData = new FormData();
    selected.forEach(id => formData.append('resident_ids[]', id));

    fetch('restore_resident.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(txt => {
            try {
                const data = JSON.parse(txt);
                successMessage.textContent = data.message || 'Selected residents restored successfully!';
                show(successModal);
                setTimeout(() => location.reload(), 4000);
            } catch(err) {
                console.error('Invalid JSON:', txt);
            }
        })
        .catch(err => console.error('Error:', err));
});

// --- CANCEL DELETE MODAL ---
cancelDeleteBtn?.addEventListener('click', () => hide(deleteModal));

// --- DELETE FORM SUBMISSION ---
deleteModal?.querySelector('form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const action = deleteModal.dataset.action;

    let url = '';
    let formData = new FormData(this);

    if (action === 'bulk_delete') {
        const ids = JSON.parse(deleteResidentIdInput.value || '[]');
        if (!ids.length) return;
        formData = new FormData();
        ids.forEach(id => formData.append('resident_ids[]', id));
        url = 'delete_archived_residents.php';
    } else {
        url = action === 'delete' ? 'delete_resident.php' : 'archived_resident.php';
    }

    fetch(url, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(txt => {
            try {
                const data = JSON.parse(txt);
                hideAllModals();
                successMessage.textContent = data.message || 'Action completed successfully!';
                show(successModal);
                setTimeout(() => location.reload(), 4000);
            } catch(err) {
                console.error('Invalid JSON:', txt);
            }
        })
        .catch(err => console.error('Error:', err));
});

// --- CLOSE SUCCESS MODAL ---
closeSuccessModal?.addEventListener('click', () => hide(successModal));

// --- ARCHIVED ROW CLICK ---
archivedTbody?.addEventListener('click', function(e) {
    if (e.target.classList.contains('selectArchived')) return;
    const row = e.target.closest('tr[data-resident]');
    if (!row) return;

    try {
        const data = JSON.parse(row.dataset.resident);
        Object.entries(data).forEach(([key, value]) => {
            const el = $(`arch_${key}`);
            if (!el) return;
            if (el.type === 'checkbox') el.checked = value == 1;
            else el.value = value;
        });
        show(archivedModal);
    } catch (err) {
        console.error('Invalid resident data:', err);
    }
});

// --- CLOSE ARCHIVED MODAL ---
closeArchivedModal?.addEventListener('click', () => hide(archivedModal));

// --- SINGLE RESTORE INSIDE MODAL ---
restoreBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    const residentId = $('archived_resident_id')?.value;
    if (!residentId) return;

    const formData = new FormData();
    formData.append('resident_id', residentId);

    fetch('restore_resident.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(txt => {
            try {
                const data = JSON.parse(txt);
                hideAllModals();
                successMessage.textContent = data.message || 'Resident restored successfully!';
                show(successModal);
                setTimeout(() => location.reload(), 1500);
            } catch(err) {
                console.error('Invalid JSON:', txt);
            }
        })
        .catch(err => console.error('Error:', err));
});

// --- DELETE INSIDE ARCHIVED MODAL ---
deleteArchivedResidentBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    const residentId = $('archived_resident_id')?.value;
    if (!residentId) return;
    openDeleteModal('delete', residentId);
});

// --- SELECT ALL CHECKBOX ---
selectAllArchived?.addEventListener('change', function() {
    document.querySelectorAll('.selectArchived').forEach(cb => cb.checked = this.checked);
});
