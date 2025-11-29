document.addEventListener('DOMContentLoaded', () => {
  const userModal = document.getElementById('userModal');
  const addUserBtn = document.getElementById('addUserBtn');
  const closeModalBtn = document.getElementById('closeModal');
  const modalTitle = document.getElementById('modalTitle');
  const userForm = document.getElementById('userForm');
  const submitBtn = document.getElementById('submitBtn');

  const userIdInput = document.getElementById('user_id');
  const usernameInput = document.getElementById('username');
  const usernameList = document.getElementById('usernameList');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const roleSelect = document.getElementById('role');
  const passwordField = document.getElementById('passwordField');

  const statusDot = document.getElementById('statusDot');
  const statusText = document.getElementById('statusText');
  const statusDate = document.getElementById('statusDate');

  const searchInput = document.getElementById('searchInput');
  const userGrid = document.getElementById('userGrid');

  const successModal = document.getElementById('successModal');
  const successMessage = document.getElementById('successMessage');
  const successIcon = document.getElementById('successIcon');
  const closeSuccessModal = document.getElementById('closeSuccessModal');
  const okSuccessBtn = document.getElementById('okSuccessBtn');

  const showModal = (msg, type = 'success') => {
    successMessage.textContent = msg;
    successIcon.textContent =
      type === 'success' ? 'check_circle' :
      type === 'warning' ? 'warning_amber' : 'cancel';
    successIcon.className =
      'material-icons text-4xl mb-2 ' +
      (type === 'success' ? 'text-green-500' :
       type === 'warning' ? 'text-yellow-500' : 'text-red-500');
    successModal.classList.remove('hidden');
  };

  closeSuccessModal.addEventListener('click', () => successModal.classList.add('hidden'));
  okSuccessBtn.addEventListener('click', () => successModal.classList.add('hidden'));

  function generatePassword(length = 10) {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*";
    let pass = "";
    for (let i = 0; i < length; i++) {
      pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return pass;
  }

  addUserBtn?.addEventListener('click', () => {
    modalTitle.textContent = 'Add User';
    submitBtn.textContent = 'Save';
    submitBtn.name = 'add_user';

    userForm.reset();
    userIdInput.value = '';
    passwordField.style.display = 'block';
    usernameInput.removeAttribute('readonly');
    emailInput.removeAttribute('readonly');

    const autoPass = generatePassword();
    passwordInput.type = "text"; 
    passwordInput.value = autoPass;

    statusDot.className = 'inline-block w-3 h-3 rounded-full bg-gray-300';
    statusText.textContent = '';
    statusDate.textContent = '';

    usernameList.innerHTML = '';
    userModal.classList.remove('hidden');
  });


  document.addEventListener('click', (e) => {
    if (e.target.classList.contains('editBtn')) {

      const btn = e.target;
      const user = JSON.parse(btn.dataset.user);

      modalTitle.textContent = 'Edit User';
      submitBtn.textContent = 'Update';
      submitBtn.name = 'update_user';

      userIdInput.value = user.id;
      usernameInput.value = user.username;
      emailInput.value = user.email;

      passwordField.style.display = 'none';
      usernameInput.setAttribute('readonly', true);
      emailInput.setAttribute('readonly', true);

      for (let i = 0; i < roleSelect.options.length; i++) {
        if (roleSelect.options[i].value === user.role) {
          roleSelect.selectedIndex = i;
          break;
        }
      }

      statusDot.className = 'inline-block w-3 h-3 rounded-full ' +
        (user.status === 'Active' ? 'bg-green-500' : 'bg-red-500');
      statusText.textContent = user.status;
      statusDate.textContent = user.status_date ? `(${user.status_date})` : '';

      usernameList.innerHTML = '';
      userModal.classList.remove('hidden');
    }
  });


  closeModalBtn?.addEventListener('click', () => {
    userModal.classList.add('hidden');
    usernameList.innerHTML = '';
  });

  const confirmModal = document.getElementById('confirmModal');
  const confirmMessage = document.getElementById('confirmMessage');
  const closeConfirmModal = document.getElementById('closeConfirmModal');
  const cancelConfirmBtn = document.getElementById('cancelConfirmBtn');
  const okConfirmBtn = document.getElementById('okConfirmBtn');

  let userIdToDelete = null;

  document.querySelectorAll('.deleteBtn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const username = btn.closest('.bg-white').querySelector('h3').textContent;
      userIdToDelete = btn.dataset.id;

      confirmMessage.textContent = `Are you sure you want to delete the account of ${username}?`;
      confirmModal.classList.remove('hidden');
    });
  });

  closeConfirmModal.addEventListener('click', () => confirmModal.classList.add('hidden'));
  cancelConfirmBtn.addEventListener('click', () => confirmModal.classList.add('hidden'));

  okConfirmBtn.addEventListener('click', () => {
    if(!userIdToDelete) return;

    fetch(`delete_user.php?id=${userIdToDelete}`)
      .then(res => res.text())
      .then(() => {
        confirmModal.classList.add('hidden'); 
        showModal('User deleted successfully!', 'success'); 
        const card = document.querySelector(`.deleteBtn[data-id='${userIdToDelete}']`)?.closest('.bg-white');
        if(card) card.remove();

        userIdToDelete = null;
      })
      .catch(err => {
        confirmModal.classList.add('hidden');
        showModal('Failed to delete user.', 'error');
        console.error(err);
      });
  });


  let noResults = document.createElement('p');
  noResults.id = "noResultsMsg";
  noResults.className = "text-center text-gray-500 mt-4 hidden";
  noResults.textContent = "No users found.";
  userGrid.parentNode.insertBefore(noResults, userGrid.nextSibling);

  searchInput.addEventListener('keyup', () => {
      const filter = searchInput.value.toLowerCase();
      const users = userGrid.querySelectorAll('.bg-white');

      let found = 0;

      users.forEach(card => {
          const username = card.querySelector('h3').textContent.toLowerCase();
          const email = card.querySelector('p').textContent.toLowerCase();
          const role = card.querySelector('.flex.mt-1.gap-2 span:last-child')?.textContent.toLowerCase();

          if (
              username.includes(filter) ||
              email.includes(filter) ||
              (role && role.includes(filter))
          ) {
              card.style.display = "";
              found++;
          } else {
              card.style.display = "none";
          }
      });

      if (found === 0) {
          noResults.classList.remove("hidden");
      } else {
          noResults.classList.add("hidden");
      }
  });

  usernameInput.addEventListener('input', () => {
    if (usernameInput.hasAttribute('readonly')) return;

    const query = usernameInput.value;
    if (query.length < 1) {
      usernameList.innerHTML = '';
      usernameList.classList.add('hidden');
      emailInput.value = '';
      return;
    }

    fetch(`search_residents.php?q=${encodeURIComponent(query)}`)
      .then(res => res.json())
      .then(data => {
        usernameList.innerHTML = '';
        usernameList.classList.remove('hidden');

        if (data.length === 0) {
          const noResult = document.createElement('div');
          noResult.textContent = 'No resident found';
          noResult.classList.add('px-2', 'py-1', 'text-gray-500');
          usernameList.appendChild(noResult);
        } else {
          data.forEach(resident => {
            const item = document.createElement('div');
            item.textContent = `${resident.first_name} ${resident.middle_name} ${resident.last_name}`;
            item.classList.add('cursor-pointer', 'hover:bg-gray-200', 'px-2', 'py-1');

            item.addEventListener('click', () => {
              usernameInput.value = item.textContent;
              emailInput.value = resident.email || '';
              usernameList.innerHTML = '';
            });

            usernameList.appendChild(item);
          });
        }
      });
  });

  document.addEventListener('click', (e) => {
    if (e.target !== usernameInput) {
      usernameList.innerHTML = '';
    }
  });

  const successMsg = document.body.dataset.success;
  const errorMsg = document.body.dataset.error;
  if (successMsg) showModal(successMsg, 'success');
  if (errorMsg) showModal(errorMsg, 'error');

  const toggleSidebar = document.getElementById('toggleSidebar');
  const sidebar = document.getElementById('sidebar');
  toggleSidebar?.addEventListener('click', () => {
    sidebar.classList.toggle('sidebar-collapsed');
    toggleSidebar.textContent = toggleSidebar.textContent === 'chevron_left' ? 'chevron_right' : 'chevron_left';
  });
});
