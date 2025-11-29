document.addEventListener('DOMContentLoaded', () => {

    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('tbody tr');

    // Add "No results" row
    let noResultRow = document.createElement('tr');
    noResultRow.innerHTML = `
        <td colspan="3" class="text-center py-4 text-gray-500">No certificate found</td>
    `;
    noResultRow.style.display = "none";
    document.querySelector('tbody').appendChild(noResultRow);

    // Search functionality
    searchInput.addEventListener('input', () => {
        const searchValue = searchInput.value.toLowerCase();
        let matchFound = false;

        tableRows.forEach(row => {
            const templateFor = row.children[0].textContent.toLowerCase();
            const templateName = row.children[1].textContent.toLowerCase();

            if(templateFor.includes(searchValue) || templateName.includes(searchValue)) {
                row.style.display = '';
                matchFound = true;
            } else {
                row.style.display = 'none';
            }
        });

        noResultRow.style.display = matchFound ? 'none' : '';
    });

    // DELETE BUTTONS
    const deleteBtns = document.querySelectorAll('.deleteBtn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            deleteId = btn.dataset.id;
            deleteRow = btn.closest('tr');
            const name = btn.dataset.name;

            // Show confirmation modal for delete
            showModalMessage(
                `Are you sure you want to delete "${name}"? This action cannot be undone.`,
                'warning',
                true
            );
        });
    });

    // EDIT BUTTONS
    document.querySelectorAll('.editBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            const cert = JSON.parse(btn.closest('tr').dataset.certificate);
            openModal('edit', cert);
        });
    });

        $('#toggleSidebar').click(function() {
            $('#sidebar').toggleClass('sidebar-collapsed');
            let icon = $(this).text();
            $(this).text(icon === 'chevron_left' ? 'chevron_right' : 'chevron_left');
        });


    // OPEN MODAL (ADD / EDIT)
    const modal = document.getElementById('certificateModal');
    const addBtn = document.getElementById('addCertificateBtn');
    const closeBtn = document.getElementById('closeModal');
    const form = document.getElementById('certificateForm');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');

    const templateFor = document.getElementById('template_for');
    const templateName = document.getElementById('template_name');
    const templateFile = document.getElementById('template_file');
    const certificateId = document.getElementById('certificate_id');
    const pdfPreview = document.getElementById('pdfPreview');
    const pdfError = document.getElementById('pdfError');

    const successModal = document.getElementById('successModal');
    const successMessageEl = document.getElementById('successMessage');
    const closeSuccessModal = document.getElementById('closeSuccessModal');
    const okSuccessBtn = document.getElementById('okSuccessBtn');
    const modalIcon = successModal.querySelector('span.material-icons');

    let originalData = {};
    let deleteId = null;
    let deleteRow = null;

    // Show success/error/warning messages
    function showModalMessage(message, type='success', isDelete=false){
        successMessageEl.textContent = message;
        modalIcon.className = 'material-icons';
        modalIcon.classList.remove('text-green-500','text-red-500','text-yellow-500');
        okSuccessBtn.textContent = 'OK';

        const existingCancel = document.getElementById('cancelDeleteBtn');
        if(existingCancel) existingCancel.remove();

        if(type==='success'){
            modalIcon.textContent = 'check_circle';
            modalIcon.classList.add('text-green-500');
            modal.classList.add('hidden');
        } else if(type==='error'){
            modalIcon.textContent = 'error';
            modalIcon.classList.add('text-red-500');
            modal.classList.add('hidden');
        } else if(type==='warning'){
            modalIcon.textContent = 'warning';
            modalIcon.classList.add('text-yellow-500');
            modal.classList.add('hidden');
        }

        if(isDelete){
            okSuccessBtn.textContent = 'Yes, Delete';

            const cancelBtn = document.createElement('button');
            cancelBtn.id = 'cancelDeleteBtn';
            cancelBtn.textContent = 'Cancel';
            cancelBtn.className = 'mt-4 ml-2 bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400';
            okSuccessBtn.after(cancelBtn);

            cancelBtn.addEventListener('click', () => {
                successModal.classList.add('hidden');
                successModal.classList.remove('flex');
                cancelBtn.remove();
                okSuccessBtn.textContent = 'OK';
                deleteId = null;
                deleteRow = null;
            });
        }

        successModal.classList.remove('hidden');
        successModal.classList.add('flex');
    }

    closeSuccessModal.addEventListener('click', () => {
        successModal.classList.add('hidden');
        successModal.classList.remove('flex');
        deleteId = null;
        deleteRow = null;
    });

    okSuccessBtn.addEventListener('click', () => {
        if(deleteId && deleteRow){
            fetch(`delete_certificate.php?id=${deleteId}`)
            .then(res => res.text())
            .then(() => {
                deleteRow.remove();
                showModalMessage('Certificate deleted successfully!', 'success');
                deleteId = null;
                deleteRow = null;
            })
            .catch(() => {
                showModalMessage('Error deleting certificate.', 'error');
            });
        } else {
            successModal.classList.add('hidden');
            successModal.classList.remove('flex');
        }
    });

    // OPEN MODAL FOR ADD/EDIT CERTIFICATE
    function openModal(type, cert = null){
        modalTitle.textContent = type === 'add' ? 'Add Certificate Type' : 'Edit Certificate Type';
        submitBtn.name = type === 'add' ? 'add_certificate' : 'update_certificate';
        submitBtn.textContent = type === 'add' ? 'Save' : 'Update';

        pdfError.textContent = '';

        if(type === 'add'){
            form.reset();
            certificateId.value = '';
            pdfPreview.innerHTML = '';
            originalData = {};

        } else if(cert){
            templateFor.value = cert.template_for;
            templateName.value = cert.template_name;
            certificateId.value = cert.id;

            pdfPreview.innerHTML = cert.file_path 
                ? `Current PDF: <a href="../${cert.file_path}" target="_blank" class="text-blue-500 underline">${cert.file_path.split('/').pop()}</a>`
                : 'No PDF uploaded.';

            templateFile.value = '';

            originalData = {
                template_for: cert.template_for,
                template_name: cert.template_name,
                file_path: cert.file_path || ''
            };
        }

        checkFormValidity();
        modal.classList.remove('hidden');
    }

    // BUTTONS TO OPEN MODAL
    addBtn.addEventListener('click', () => openModal('add'));
    closeBtn.addEventListener('click', () => modal.classList.add('hidden'));

    // BUTTON TO OPEN EDIT MODAL
    document.querySelectorAll('.editBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            const cert = JSON.parse(btn.closest('tr').dataset.certificate);
            openModal('edit', cert);
        });
    });

    // FILE CHANGE
    templateFile.addEventListener('change', () => {
        const file = templateFile.files[0];
        pdfPreview.textContent = file ? `Selected file: ${file.name}` : '';
        pdfError.textContent = '';
        checkFormValidity();
    });

    // VALIDATION
    function checkFormValidity(){
        let valid = templateFor.value.trim() && templateName.value.trim();

        if(submitBtn.name === 'add_certificate')
            valid = valid && templateFile.files.length > 0;

        submitBtn.disabled = !valid;
        submitBtn.classList.toggle('opacity-50', !valid);
    }

    templateFor.addEventListener('input', checkFormValidity);
    templateName.addEventListener('input', checkFormValidity);
    templateFile.addEventListener('change', checkFormValidity);

    // FORM SUBMIT
    form.addEventListener('submit', e => {
        pdfError.textContent = '';
        const file = templateFile.files[0];

        if(file){
            const ext = file.name.split('.').pop().toLowerCase();

            if(ext !== 'pdf'){
                e.preventDefault();
                pdfError.textContent = 'Only PDF files are allowed.';
                templateFile.value = '';
                pdfPreview.innerHTML = '';
                checkFormValidity();
                return;
            }

            const existingFiles = Array.from(document.querySelectorAll('tbody tr'))
            .map(row => row.dataset.file)
            .filter(name => name && name.trim() !== "");

            if(existingFiles.includes(file.name)){
                e.preventDefault();
                pdfError.textContent = 'File with the same name already exists!';
                templateFile.value = '';
                pdfPreview.innerHTML = '';
                checkFormValidity();
                return;
            }
        }

        if(submitBtn.name === 'update_certificate'){
            const currentData = {
                template_for: templateFor.value.trim(),
                template_name: templateName.value.trim(),
                file_path: templateFile.files.length > 0 ? 'new_file' : originalData.file_path
            };

            const isChanged = currentData.template_for !== originalData.template_for ||
                              currentData.template_name !== originalData.template_name ||
                              currentData.file_path !== originalData.file_path;

            if(!isChanged){
                e.preventDefault();
                showModalMessage('No changes detected.', 'warning');
                return;
            }
        }
    });

    // PHP ERRORS / SUCCESS HANDLING
    document.addEventListener('DOMContentLoaded', () => {
        const phpError = document.body.dataset.error;
        if(phpError && phpError.trim() !== ''){
            pdfError.textContent = phpError;
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50');
            modal.classList.remove('hidden');
        }

        const phpSuccessMsg = document.body.dataset.success;
        if(phpSuccessMsg && phpSuccessMsg.trim() !== ''){
            showModalMessage(phpSuccessMsg, 'success');
            setTimeout(() => successModal.classList.add('hidden'), 2000);
        }
    });

    // CLICK OFF MODAL
    window.addEventListener('click', e => {
        if(e.target === modal) modal.classList.add('hidden');
        if(e.target === successModal){
            successModal.classList.add('hidden');
            successModal.classList.remove('flex');
            deleteId = null;
            deleteRow = null;
        }
    });

});
