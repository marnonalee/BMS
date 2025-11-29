document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('fileInput');
    const previewContainer = document.getElementById('previewContainer');

    if(fileInput) {
        fileInput.addEventListener('change', function() {
            previewContainer.innerHTML = '';
            Array.from(fileInput.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const ext = file.name.split('.').pop().toLowerCase();
                    let el;
                    if(['mp4','webm','ogg'].includes(ext)){
                        el = document.createElement('video');
                        el.src = e.target.result;
                        el.controls = true;
                    } else {
                        el = document.createElement('img');
                        el.src = e.target.result;
                    }
                    el.classList.add('w-24','h-24','object-cover','rounded','mr-2','mb-2');
                    previewContainer.appendChild(el);
                }
                reader.readAsDataURL(file);
            });
        });
    }

    const toggleButtons = document.querySelectorAll('.toggle-content-btn');
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const wrapper = btn.previousElementSibling;
            const p = wrapper.querySelector('p.announcement-content');
            if(wrapper.classList.contains('expanded')){
                p.innerHTML = p.dataset.short;
                wrapper.classList.remove('expanded');
                btn.textContent = 'View More';
            } else {
                p.innerHTML = p.dataset.full;
                wrapper.classList.add('expanded');
                btn.textContent = 'View Less';
            }
        });
    });
});

function editPost(id, title, content){
    document.getElementById('edit_id').value = id;
    document.getElementById('titleInput').value = title;
    tinymce.get('contentInput').setContent(content);
    document.querySelector('input[name="action"]').value = 'edit';
    document.getElementById('submitBtn').textContent = 'Update';
}
function hideDeleteModal() {
    const modal = document.getElementById("deleteModal");
    modal.classList.add("hidden");
    const confirmBtn = document.getElementById("confirmDeleteBtn");
    confirmBtn.onclick = null;
}

function showDeleteModal(id, title) {
    document.getElementById("deleteMessage").innerText =
        `Are you sure you want to delete this announcement: "${title}"?`;
    const modal = document.getElementById("deleteModal");
    modal.classList.remove("hidden");
    document.getElementById("confirmDeleteBtn").onclick = function () {
        window.location.href = "announcements.php?delete_id=" + id;
    };
}

document.getElementById("cancelDeleteBtn").addEventListener("click", function (e) {
    e.preventDefault();
    hideDeleteModal();
});

document.getElementById("deleteModal").addEventListener("click", function (e) {
    if (e.target && e.target.id === "deleteModal") {
        hideDeleteModal();
    }
});

document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
        const modal = document.getElementById("deleteModal");
        if (modal && !modal.classList.contains("hidden")) {
            hideDeleteModal();
        }
    }
});


const toggleSidebar = document.getElementById('toggleSidebar');
const sidebar = document.getElementById('sidebar');

toggleSidebar.addEventListener('click', () => {
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent = toggleSidebar.textContent === 'chevron_left' ? 'chevron_right' : 'chevron_left';
});