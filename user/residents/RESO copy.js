/* ===============================
   SIDEBAR TOGGLE
================================ */
const toggleSidebar = document.getElementById('toggleSidebar');
const sidebar = document.getElementById('sidebar');

toggleSidebar.onclick = () => {
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent =
        toggleSidebar.textContent === 'chevron_left'
            ? 'chevron_right'
            : 'chevron_left';
};


/* ===============================
   PER PAGE DROPDOWN
================================ */
document.getElementById('perPageSelect')?.addEventListener('change', function () {
    window.location.href = `?page=1&perPage=${this.value}`;
});


/* ===============================
   TAB SYSTEM
================================ */
function setupTabs(tabContainerId, personalId, otherId) {
    const tabs = document.querySelectorAll(`#${tabContainerId} button`);

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            tabs.forEach(b => {
                b.classList.remove('border-green-500');
                b.classList.add('border-transparent');
            });

            this.classList.add('border-green-500');
            this.classList.remove('border-transparent');

            const isPersonal = this.dataset.tab === 'personal';
            document.getElementById(personalId).classList.toggle('hidden', !isPersonal);
            document.getElementById(otherId).classList.toggle('hidden', isPersonal);
        });
    });
}


/* ===============================
   AGE CALCULATION
================================ */
function setupAgeCalculation(birthdateInputSelector, ageSelector, seniorSelector) {
    const birthInput = document.querySelector(birthdateInputSelector);
    if (!birthInput) return;

    birthInput.addEventListener('change', function () {
        const birth = new Date(this.value);
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


function setupLettersOnly(fields) {
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;

        el.addEventListener('keydown', e => {
            if (
                e.key === "Backspace" || 
                e.key === "Delete" || 
                e.key === "ArrowLeft" || 
                e.key === "ArrowRight" || 
                e.key === "Tab"
            ) return;

            if (e.key.length === 1 && !/^[a-zA-Z\s]$/.test(e.key)) {
                e.preventDefault();
            }
        });
    });
}

setupLettersOnly(['first_name', 'middle_name', 'last_name', 'alias', 'suffix']);


/* ===============================
   NUMBERS ONLY
================================ */
function setupNumbersOnly(fields, maxLength = null) {
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;

        el.addEventListener('keydown', e => {
            const allowed = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab'];
            if (allowed.includes(e.key)) return;

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


/* ===============================
   MODALS
================================ */
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


/* ===============================
   CLICK ROW → OPEN EDIT MODAL
================================ */
function setupResidentRows() {
    const modal = document.getElementById('residentModal');

    document.querySelectorAll('#residentsTable tbody tr').forEach(row => {
        row.addEventListener('click', () => {
            const data = JSON.parse(row.dataset.resident);
            modal.classList.remove('hidden');

            Object.keys(data).forEach(key => {
                const el = document.getElementById(key);
                if (!el) return;

                if (el.type === 'checkbox') {
                    el.checked = data[key] == 1 || data[key] === true;
                } else {
                    el.value = data[key];
                }
            });

            updateAgeAndSenior(data.birthdate);
        });
    });
}


/* ===============================
   UPDATE AGE IN EDIT MODAL
================================ */
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


/* ===============================
   GLOBAL SUCCESS/WARNING MODAL
================================ */
function showResidentModalMessage(message, type = 'success', closeMainModalId = null) {
    const modal = document.getElementById('successModal');
    const messageEl = document.getElementById('successMessage');
    const icon = modal.querySelector('span.material-icons');
    const okBtn = document.getElementById('okSuccessBtn');
    const closeBtn = document.getElementById('closeSuccessModal');

    messageEl.textContent = message;
    icon.classList.remove('text-green-500', 'text-red-500', 'text-yellow-500');

    if (type === 'success') {
        icon.textContent = 'check_circle';
        icon.classList.add('text-green-500');
        okBtn.classList.add('hidden');
        closeBtn.classList.add('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 2000);
    } else {
        icon.textContent = type === 'warning' ? 'warning_amber' : 'cancel';
        icon.classList.add(type === 'warning' ? 'text-yellow-500' : 'text-red-500');
        okBtn.classList.remove('hidden');
        closeBtn.classList.remove('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    if (closeMainModalId) {
        document.getElementById(closeMainModalId).classList.add('hidden');
    }
}

/* ===============================
   FORM VALIDATIONS
================================ */
document.getElementById('residentForm')?.addEventListener('submit', e => {
    const firstName = first_name.value.trim();
    const lastName = last_name.value.trim();
    const contact = contact_number.value.trim();

    if (!firstName || !lastName) {
        e.preventDefault();
        showResidentModalMessage("First and Last Name are required", 'warning', 'residentModal');
        return;
    }
    if (contact && contact.length !== 11) {
        e.preventDefault();
        showResidentModalMessage("Contact Number must be exactly 11 digits", 'warning', 'residentModal');
        return;
    }
});


/* ===============================
   SUCCESS MODAL BUTTONS
================================ */
['closeSuccessModal', 'okSuccessBtn'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => {
        const modal = document.getElementById('successModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    });
});


/* ===============================
   INITIALIZATION
================================ */
setupTabs('modalTabs', 'personalTab', 'otherTab');
setupTabs('addModalTabs', 'addPersonalTab', 'addOtherTab');
setupAgeCalculation('#birthdate', 'input[name="age"]', '#is_senior');
setupAgeCalculation('#addPersonalTab input[name="birthdate"]', 'input[name="age"]', 'input[name="is_senior"]');
setupLettersOnly(['first_name', 'middle_name', 'last_name', 'alias', 'suffix', 'religion', 'occupation', 'education']);
setupNumbersOnly(['contact_number', 'philsys_card_no']);
setupModal('addResidentModal', 'addResidentBtn', 'closeAddModal');
setupModal('residentModal', null, 'closeModal');
document.addEventListener('DOMContentLoaded', setupResidentRows);


/* ===============================
   AUTO SHOW SUCCESS MESSAGE
================================ */
document.addEventListener('DOMContentLoaded', () => {
    const msg = document.body.dataset.success;
    if (!msg) return;

    let type = msg.match(/error|required|must|no changes/i) ? 'warning' : 'success';

    showResidentModalMessage(msg, type);
    setTimeout(() => {
        document.getElementById('successModal').classList.add('hidden');
    }, 2000);
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

    const cardNavigation = {
        card_total: 'resident.php?filter=all',
        card_voters: 'resident.php?filter=voters',
        card_unvoters: 'resident.php?filter=unregistered_voter',
        card_senior: 'resident.php?filter=senior',
        card_pwd: 'resident.php?filter=pwd',
        card_4ps: 'resident.php?filter=4ps',
        card_solo: 'resident.php?filter=solo_parent'
    };
    for (const [id, url] of Object.entries(cardNavigation)) {
        const card = document.getElementById(id);
        if (card) card.onclick = () => location.href = url;
    }

    const setupEduCheckboxes = (containerId) => {
        const checkboxes = document.querySelectorAll(`#${containerId} input[type="checkbox"]`);
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    checkboxes.forEach(cb => {
                        if (cb !== this) {
                            cb.checked = false;
                            const select = cb.parentElement.querySelector('.edu-status');
                            if (select) {
                                select.classList.add('hidden');
                                select.value = '';
                            }
                        }
                    });
                }
                const select = this.parentElement.querySelector('.edu-status');
                if (select) {
                    if (this.checked) select.classList.remove('hidden');
                    else {
                        select.classList.add('hidden');
                        select.value = '';
                    }
                }
            });
        });
    };

    setupEduCheckboxes('educationContainer');
    setupEduCheckboxes('editEducationContainer');

    const searchInput = document.getElementById('searchInput');
    const tableBody = document.querySelector('#residentsTable tbody');
    const tableRows = tableBody.querySelectorAll('tr');

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


/* ===============================
DELETE RESIDENT WITH AJAX + SUCCESS MODAL
================================ */
const deleteBtn = document.getElementById('deleteResident');
const deleteModal = document.getElementById('deleteConfirmModal');
const cancelDelete = document.getElementById('cancelDelete');
const deleteIdField = document.getElementById('delete_resident_id');

// Open delete modal
if (deleteBtn) {
    deleteBtn.addEventListener('click', () => {
        const id = document.getElementById('resident_id').value;
        deleteIdField.value = id;
        deleteModal.classList.remove('hidden');
    });
}

cancelDelete?.addEventListener('click', () => {
    deleteModal.classList.add('hidden');
});
document
    .querySelector("#deleteConfirmModal form")
    .addEventListener("submit", function (e) {

        e.preventDefault();
        const formData = new FormData(this);

        fetch("archived_resident.php", {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            deleteModal.classList.add('hidden');

            if (data.success) {
                document.getElementById("successMessage").textContent =
                    data.message || "Resident deleted successfully!";

                document.getElementById("successModal").classList.remove("hidden");
            }
        });
});
document.getElementById("okSuccessBtn").onclick = () => {
    location.reload();
};

document.getElementById("closeSuccessModal").onclick = () => {
    location.reload();
};

