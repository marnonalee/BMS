// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('toggleSidebar');

toggleBtn.onclick = () => {
    sidebar.classList.toggle('sidebar-collapsed');

    let icon = toggleBtn.textContent.trim();
    toggleBtn.textContent = icon === 'chevron_left' ? 'chevron_right' : 'chevron_left';
};

// Success modal on page load
document.addEventListener('DOMContentLoaded', function() {
    if (window.sessionMessage) {
        const modal = document.getElementById('successModal');
        const modalMessage = document.getElementById('successMessage');
        const modalIcon = document.getElementById('modalIcon');

        let type = 'success', text = '';
        if(typeof window.sessionMessage === 'string'){
            text = window.sessionMessage;
        } else {
            type = window.sessionMessage.type || 'success';
            text = window.sessionMessage.text || '';
        }

        modalMessage.textContent = text;
        if(type === 'success'){
            modalIcon.textContent = 'check_circle';
            modalIcon.className = 'material-icons text-green-500 text-4xl mb-2';
        } else if(type === 'error'){
            modalIcon.textContent = 'error';
            modalIcon.className = 'material-icons text-red-500 text-4xl mb-2';
        } else if(type === 'warning'){
            modalIcon.textContent = 'warning';
            modalIcon.className = 'material-icons text-yellow-500 text-4xl mb-2';
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.getElementById('closeSuccessModal').addEventListener('click', ()=> modal.classList.add('hidden'));
        document.getElementById('okSuccessBtn').addEventListener('click', ()=> modal.classList.add('hidden'));
    }
});

// Globals
let currentOfficial = {};
const residentsList = window.residentsList || [];
const assignedResidents = window.assignedResidents || [];

function openModal(o) {
    currentOfficial = o;

    const modalPhoto = document.getElementById("modalPhoto");
    if(modalPhoto) {
        modalPhoto.innerHTML = `<img src="../uploads/${o.photo && o.photo.trim() !== '' ? o.photo : 'default-avatar.jpg'}" class="object-cover h-full w-full rounded">`;
    }

    const modalName = document.getElementById("modalName");
    if(modalName) modalName.textContent = o.first_name + ' ' + o.last_name;

    const modalPosition = document.getElementById("modalPosition");
    if(modalPosition) modalPosition.textContent = "Position: " + o.position_name;

    const modalTerm = document.getElementById("modalTerm");
    if(modalTerm) modalTerm.textContent = "Term: " + o.start_date + " - " + o.end_date;

    const officialModal = document.getElementById("officialModal");
    if(officialModal){
        officialModal.classList.remove("hidden");
        officialModal.classList.add("flex");
    }

    const editContent = document.getElementById("editContent");
    if(editContent) editContent.classList.add("hidden");

    const viewContent = document.getElementById("viewContent");
    if(viewContent) viewContent.classList.remove("hidden");
}

// CLOSE MODAL
function closeModal() {
    const officialModal = document.getElementById("officialModal");
    if(officialModal){
        officialModal.classList.add("hidden");
        officialModal.classList.remove("flex");
    }
}
// SWITCH TO EDIT (INLINE PANEL, NOT MODAL)
function switchToEdit() {
    // Close the modal
    const officialModal = document.getElementById("officialModal");
    if(officialModal){
        officialModal.classList.add("hidden");
        officialModal.classList.remove("flex");
    }

    // Open the inline edit panel
    const editPanel = document.getElementById("editPanel");
    if(editPanel) editPanel.classList.remove("hidden");

    // Populate form fields
    const editOfficialId = document.getElementById("editOfficialId");
    if(editOfficialId) editOfficialId.value = currentOfficial.id || currentOfficial.official_id;

    const editPhotoPreview = document.getElementById("editPhotoPreview");
    if(editPhotoPreview) editPhotoPreview.src = currentOfficial.photo ? `../uploads/${currentOfficial.photo}` : '';

    const editStart = document.getElementById("editStart");
    if(editStart) editStart.value = currentOfficial.start_date;

    const editEnd = document.getElementById("editEnd");
    if(editEnd) editEnd.value = currentOfficial.end_date;

    const selectResident = document.getElementById("editResident");
    if(selectResident){
        selectResident.innerHTML = "";
        residentsList.forEach(res => {
            const isAssigned = assignedResidents.includes(res.resident_id) && res.resident_id != currentOfficial.resident_id;
            const option = document.createElement("option");
            option.value = res.resident_id;
            option.text = res.first_name + " " + res.last_name + (isAssigned ? " (Already in Position)" : "");
            option.disabled = isAssigned;
            if(res.resident_id == currentOfficial.resident_id) option.selected = true;
            selectResident.appendChild(option);
        });
    }

   const selectPosition = document.getElementById("editPosition");
    if(selectPosition){
        selectPosition.innerHTML = "";
        window.positionsList.forEach(p => {
            const count = window.positionCounts[p.id] || 0;
            const disabled = (count >= p.limit);
            const opt = document.createElement("option");
            opt.value = p.id;
            opt.text = p.position_name + (disabled ? " (Full)" : "");
            opt.disabled = disabled;
            if(opt.value == currentOfficial.position_id) opt.selected = true;
            selectPosition.appendChild(opt);
        });
    }

}

// CLOSE EDIT PANEL
function closeEditPanel(){
    const editPanel = document.getElementById("editPanel");
    if(editPanel) editPanel.classList.add("hidden");
}

// SWITCH BACK TO VIEW
function switchToView() {
    const editContent = document.getElementById("editContent");
    if(editContent) editContent.classList.add("hidden");

    const viewContent = document.getElementById("viewContent");
    if(viewContent) viewContent.classList.remove("hidden");
}

// EDIT PHOTO PREVIEW
const editPhotoInput = document.getElementById("editPhotoInput");
const editPhotoPreview = document.getElementById("editPhotoPreview");
if(editPhotoInput){
    editPhotoInput.addEventListener("change", function() {
        const file = this.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(e){
                if(editPhotoPreview) editPhotoPreview.src = e.target.result;
            }
            reader.readAsDataURL(file);
        } else {
            if(editPhotoPreview) editPhotoPreview.src = currentOfficial.photo ? `../uploads/${currentOfficial.photo}` : '';
        }
    });
}

// ADD MODAL
function openAddModal(){
    const addModal = document.getElementById('addOfficialModal');
    if(addModal){
        addModal.classList.remove('hidden');
        addModal.classList.add('flex');
    }
}

function closeAddModal(){
    const addModal = document.getElementById('addOfficialModal');
    if(addModal){
        addModal.classList.add('hidden');
        addModal.classList.remove('flex');
    }
}
const addPhotoInput = document.getElementById('addPhotoInput');
const addPhotoPreview = document.getElementById('addPhotoPreview');

addPhotoInput.addEventListener('change', function() {
    const file = this.files[0];
    if(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            addPhotoPreview.src = e.target.result;
        }
        reader.readAsDataURL(file);
    } else {
        addPhotoPreview.src = '../uploads/default-avatar.jpg';
    }
});