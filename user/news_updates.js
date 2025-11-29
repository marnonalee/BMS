// Delete Modal
let deleteId = null;
const deleteModal = document.getElementById('deleteModal');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const deleteMessage = document.getElementById('deleteMessage');

function showDeleteModal(id, title){
    deleteId = id;
    deleteMessage.textContent = `Do you really want to delete the post titled "${title}"?`;
    deleteModal.classList.remove('hidden');
    deleteModal.classList.add('flex');
}

cancelDeleteBtn?.addEventListener('click', () => {
    deleteModal.classList.add('hidden');
    deleteModal.classList.remove('flex');
    deleteId = null;
});

confirmDeleteBtn?.addEventListener('click', () => {
    if(deleteId !== null){
        window.location.href = '?delete_id=' + deleteId;
    }
});

// Image Preview
const fileInput = document.getElementById('fileInput');
const previewContainer = document.getElementById('previewContainer');
let filesArray = [];

fileInput?.addEventListener('change', e => {
    filesArray = Array.from(e.target.files);
    renderPreviews();
});

function renderPreviews() {
    previewContainer.innerHTML = '';
    document.querySelectorAll('.existing-preview').forEach(img => previewContainer.appendChild(img));
    filesArray.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = e => {
            const wrapper = document.createElement('div');
            wrapper.className = 'relative';
            let preview;
            if(file.type.startsWith('image/')) preview = document.createElement('img');
            else if(file.type.startsWith('video/')){
                preview = document.createElement('video');
                preview.controls = true;
            }
            preview.src = e.target.result;
            preview.className = 'w-32 h-32 object-cover rounded shadow';
            const removeBtn = document.createElement('button');
            removeBtn.innerHTML = '×';
            removeBtn.className = 'absolute top-0 right-0 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600';
            removeBtn.onclick = () => {
                filesArray = filesArray.filter((_, i) => i !== index);
                renderPreviews();
            };
            wrapper.appendChild(preview);
            wrapper.appendChild(removeBtn);
            previewContainer.appendChild(wrapper);
        };
        reader.readAsDataURL(file);
    });
}

// Update Form Files
document.getElementById('postForm')?.addEventListener('submit', function(e){
    const dataTransfer = new DataTransfer();
    filesArray.forEach(file => dataTransfer.items.add(file));
    fileInput.files = dataTransfer.files;
});

// Edit Post
function editPost(id, title, content) {
    document.getElementById('edit_id').value = id;
    document.getElementById('titleInput').value = title;
    tinymce.get('contentInput').setContent(content);
    document.querySelector('input[name="action"]').value = 'edit';
    document.getElementById('submitBtn').textContent = 'Update';
    previewContainer.innerHTML = '';

    fetch('get_news_images.php?id=' + id).then(res => res.json()).then(images => {
        images.forEach(img => {
            const wrapper = document.createElement('div');
            wrapper.className = 'relative existing-preview';
            const preview = document.createElement('img');
            preview.src = img.image_path;
            preview.className = 'w-32 h-32 object-cover rounded shadow';
            const removeBtn = document.createElement('button');
            removeBtn.innerHTML = '×';
            removeBtn.className = 'absolute top-0 right-0 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600';
            removeBtn.onclick = () => {
                wrapper.remove();
                const delInput = document.createElement('input');
                delInput.type = 'hidden';
                delInput.name = 'delete_images[]';
                delInput.value = img.id;
                document.getElementById('postForm').appendChild(delInput);
            };
            wrapper.appendChild(preview);
            wrapper.appendChild(removeBtn);
            previewContainer.appendChild(wrapper);
        });
    });

    filesArray = [];
    document.getElementById('postForm').scrollIntoView({behavior: 'smooth'});
}

// View More / Less
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-content-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.flex-1.flex.flex-col');
            const wrapper = card.querySelector('.announcement-content-wrapper');
            const p = wrapper.querySelector('.announcement-content');
            if(wrapper.classList.contains('expanded')){
                wrapper.classList.remove('expanded');
                p.innerHTML = p.dataset.short;
                btn.textContent = 'View More';
            } else {
                wrapper.classList.add('expanded');
                p.innerHTML = p.dataset.full;
                btn.textContent = 'View Less';
            }
        });
    });
});
