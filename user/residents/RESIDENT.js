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


const deleteModal = document.getElementById('deleteConfirmModal');
const deleteResidentIdInput = document.getElementById('delete_resident_id');
const cancelDeleteBtn = document.getElementById('cancelDelete');
const successModal = document.getElementById('successModal');
const successMessage = document.getElementById('successMessage');
const closeSuccessModal = document.getElementById('closeSuccessModal');
const deleteBtn = document.getElementById('deleteResident');
const deleteArchivedResidentBtn = document.getElementById('deleteArchivedResident');
const restoreBtn = document.getElementById('restoreResident');
const archivedModal = document.getElementById('archivedResidentModal');
const closeArchivedModal = document.getElementById('closeArchivedModal');
const archivedRows = document.querySelectorAll('#archivedResidentsTable tbody tr[data-resident]');
const deleteSelectedArchivedBtn = document.getElementById('deleteSelectedArchived');
const selectAllArchived = document.getElementById('selectAllArchived');

deleteBtn?.addEventListener('click', () => {
    deleteModal.dataset.action = "archive";
    deleteResidentIdInput.value = document.getElementById('resident_id').value;
    deleteModal.classList.remove('hidden');
});

deleteArchivedResidentBtn?.addEventListener('click', () => {
    deleteModal.dataset.action = "delete";
    deleteResidentIdInput.value = document.getElementById('archived_resident_id').value;
    archivedModal.classList.add('hidden');
    deleteModal.classList.remove('hidden');
});

deleteSelectedArchivedBtn?.addEventListener('click', () => {
    const selected = Array.from(document.querySelectorAll('.selectArchived:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    deleteModal.dataset.action = "bulk_delete";
    deleteResidentIdInput.value = JSON.stringify(selected);
    deleteModal.classList.remove('hidden');
});

cancelDeleteBtn?.addEventListener('click', () => deleteModal.classList.add('hidden'));

document.querySelector("#deleteConfirmModal form")?.addEventListener("submit", function(e) {
    e.preventDefault();
    const action = deleteModal.dataset.action;

    if (action === "bulk_delete") {
        const ids = JSON.parse(deleteResidentIdInput.value);
        const formData = new FormData();
        ids.forEach(id => formData.append('resident_ids[]', id));
        fetch('delete_archived_residents.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                deleteModal.classList.add('hidden');
                successMessage.textContent = data.message || "Deleted successfully!";
                successModal.classList.remove('hidden');
                setTimeout(() => location.reload(), 1500);
            });
        return;
    }

    const formData = new FormData(this);
    let url = deleteModal.dataset.action === "delete" ? "delete_resident.php" : "archived_resident.php";

    fetch(url, { method: "POST", body: formData })
        .then(r => r.json())
        .then(data => {
            deleteModal.classList.add('hidden');
            successMessage.textContent = data.message || "Success!";
            successModal.classList.remove('hidden');
            setTimeout(() => location.reload(), 1500);
        });
});

closeSuccessModal?.addEventListener('click', () => successModal.classList.add('hidden'));

archivedRows.forEach(row => {
    row.addEventListener('click', () => {
        const data = JSON.parse(row.dataset.resident);
        document.getElementById('archived_resident_id').value = data.resident_id;
        document.getElementById('arch_first_name').value = data.first_name;
        document.getElementById('arch_middle_name').value = data.middle_name;
        document.getElementById('arch_last_name').value = data.last_name;
        document.getElementById('arch_alias').value = data.alias;
        document.getElementById('arch_suffix').value = data.suffix;
        document.getElementById('arch_birthdate').value = data.birthdate;
        document.getElementById('arch_age').value = data.age;
        document.getElementById('arch_sex').value = data.sex;
        document.getElementById('arch_civil_status').value = data.civil_status;
        document.getElementById('arch_resident_address').value = data.resident_address;
        document.getElementById('arch_birth_place').value = data.birth_place;
        document.getElementById('arch_street').value = data.street;
        document.getElementById('arch_citizenship').value = data.citizenship;
        document.getElementById('arch_voter_status').value = data.voter_status;
        document.getElementById('arch_employment_status').value = data.employment_status;
        document.getElementById('arch_contact_number').value = data.contact_number;
        document.getElementById('arch_email_address').value = data.email_address;
        document.getElementById('arch_religion').value = data.religion;
        document.getElementById('arch_profession_occupation').value = data.profession_occupation;
        document.getElementById('arch_educational_attainment').value = data.educational_attainment;
        document.getElementById('arch_education_details').value = data.education_details;
        document.getElementById('arch_school_status').value = data.school_status;
        document.getElementById('arch_is_family_head').checked = data.is_family_head == 1;
        document.getElementById('arch_philsys_card_no').value = data.philsys_card_no;
        document.getElementById('arch_is_senior').checked = data.is_senior == 1;
        document.getElementById('arch_is_pwd').checked = data.is_pwd == 1;
        document.getElementById('arch_is_4ps').checked = data.is_4ps == 1;
        document.getElementById('arch_is_solo_parent').checked = data.is_solo_parent == 1;
        archivedModal.classList.remove('hidden');
    });
});

closeArchivedModal?.addEventListener('click', () => archivedModal.classList.add('hidden'));

restoreBtn?.addEventListener('click', () => {
    const residentId = document.getElementById('archived_resident_id').value;
    if (!residentId) return;
    const formData = new FormData();
    formData.append('resident_id', residentId);
    fetch('restore_resident.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            archivedModal.classList.add('hidden');
            successMessage.textContent = data.message || "Resident restored successfully!";
            successModal.classList.remove('hidden');
            setTimeout(() => location.reload(), 1500);
        });
});

selectAllArchived?.addEventListener('change', function() {
    document.querySelectorAll('.selectArchived').forEach(cb => cb.checked = this.checked);
});
