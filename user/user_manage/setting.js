function showModal(type, message){
    const modal = document.getElementById('successModal');
    const icon = document.getElementById('successIcon');
    const msg = document.getElementById('successMessage');
    icon.classList.remove('text-green-500','text-red-500');
    if(type==='success'){ 
        icon.textContent='check_circle'; 
        icon.classList.add('text-green-500'); 
    } else { 
        icon.textContent='error'; 
        icon.classList.add('text-red-500'); 
    }
    msg.textContent = message;
    modal.classList.remove('hidden');
    document.getElementById('closeSuccessModal').onclick = () => modal.classList.add('hidden');
    document.getElementById('okSuccessBtn').onclick = () => modal.classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('editSaveBtn');
    const form = document.getElementById('settingsForm');
    const input = document.getElementById('landing_bg_multiple');
    const preview = document.getElementById('landing_bg_preview');
    let editing = false;
    
    function createPreview(src, isNew=true){
        const wrapper = document.createElement('div');
        wrapper.className = 'relative group';
        const img = document.createElement('img');
        img.src = src;
        img.className = 'w-40 h-20 rounded shadow';
        img.dataset.new = isNew; 
        const btnRemove = document.createElement('button');
        btnRemove.type='button';
        btnRemove.innerHTML='&times;';
        btnRemove.className='absolute top-0 right-0 bg-red-500 text-white rounded-full w-5 h-5 text-xs hidden group-hover:block remove-img';
        btnRemove.onclick = () => wrapper.remove();
        wrapper.appendChild(img);
        wrapper.appendChild(btnRemove);
        preview.appendChild(wrapper);
    }

    input.addEventListener('change', ()=>{
        Array.from(input.files).forEach(file=>{
            const reader = new FileReader();
            reader.onload = e => createPreview(e.target.result, true);
            reader.readAsDataURL(file);
        });
    });
    preview.addEventListener('click', e=>{
        if(e.target.classList.contains('remove-img')) e.target.parentElement.remove();
    });

btn.addEventListener('click', async ()=>{
    if(!editing){
        form.querySelectorAll('input, textarea, select').forEach(el=>{
            el.removeAttribute('readonly'); 
            el.removeAttribute('disabled'); 
        });
        btn.textContent='Save Changes';
        editing=true;
    } else {
        const formData = new FormData(form);
        formData.append('ajax', 1); // <-- IMPORTANT

        const existingImgs = [];
        preview.querySelectorAll('img').forEach(img=>{
            if(!img.dataset.new){
                existingImgs.push(img.src);
            }
        });
        formData.append('existing_hero', JSON.stringify(existingImgs));

        try{
            const res = await fetch('settings.php',{method:'POST',body:formData});
            const data = await res.json(); // Now this will be valid JSON
            if(data.success){
                preview.innerHTML='';
                data.images.forEach(src => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'relative group';
                    const img = document.createElement('img');
                    img.src = src;
                    img.className = 'w-40 h-20 rounded shadow';
                    wrapper.appendChild(img);
                    preview.appendChild(wrapper);
                });
                showModal('success','Settings updated successfully!');
            } else {
                showModal('error', data.message || 'Failed to update settings.');
            }
        }catch(e){
            showModal('error','Error: '+e.message);
        }
    }
});


    preview.querySelectorAll('img').forEach(img=>{
        createPreview(img.src, false);
    });

    if(phpModalType && phpModalMessage) showModal(phpModalType, phpModalMessage);
});
