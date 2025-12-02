document.addEventListener('DOMContentLoaded', () => {
  $(document).ready(function() {
    // Sidebar toggle
    $('#toggleSidebar').click(function() {
        $('#sidebar').toggleClass('sidebar-collapsed');
        let icon = $(this).text();
        $(this).text(icon === 'chevron_left' ? 'chevron_right' : 'chevron_left');
    });
  });

  // Search filter
  const searchInput = document.getElementById('searchInput');
  const tableRows = document.querySelectorAll('#requestsTable tr:not(#noResultsRow)');
  const noResultsRow = document.getElementById('noResultsRow');

  searchInput.addEventListener('input', () => {
      const query = searchInput.value.toLowerCase();
      let anyVisible = false;

      tableRows.forEach(row => {
          const match = row.textContent.toLowerCase().includes(query);
          row.style.display = match ? '' : 'none';
          if(match) anyVisible = true;
      });

      noResultsRow.classList.toggle('hidden', anyVisible);
  });

  // Per page selection
  const perPageSelect = document.getElementById('perPageSelect');
  perPageSelect.addEventListener('change', function(){
      window.location.href = "?page=1&perPage=" + this.value;
  });

  // Tabs setup
  window.setupTabs = function(tabContainerId, personalId, otherId){
      const tabs = document.querySelectorAll(`#${tabContainerId} button`);
      tabs.forEach(tab => {
          tab.addEventListener('click', function(){
              tabs.forEach(b => {
                  b.classList.remove('border-green-500');
                  b.classList.add('border-transparent');
              });
              this.classList.add('border-green-500');
              this.classList.remove('border-transparent');

              if(this.dataset.tab === 'personal'){
                  document.getElementById(personalId).classList.remove('hidden');
                  document.getElementById(otherId).classList.add('hidden');
              } else {
                  document.getElementById(personalId).classList.add('hidden');
                  document.getElementById(otherId).classList.remove('hidden');
              }
          });
      });
  };

window.printCertificate = function(requestId, certificateName) {
    let url = '';
    certificateName = certificateName.toLowerCase();

    if (certificateName.includes('attestation')) {
        url = `print_attestation.php?id=${requestId}`;
    } else if (certificateName.includes('guardianship')) {
        url = `print_guardianship.php?id=${requestId}`;
    } else if (certificateName.includes('certification') || certificateName.includes('clearance')) {
        url = `print_certificate_barangay.php?id=${requestId}`;
    } else if (certificateName.includes('maynilad')) {
        url = `print_maynilad.php?id=${requestId}`;
    } else if (certificateName.includes('moral')) {
        url = `print_moral.php?id=${requestId}`;
    } else if (certificateName.includes('indigency')) {
        url = `print_indigency.php?id=${requestId}`;
    } else if (certificateName.includes('residency')) {
        url = `print_residency.php?id=${requestId}`;
    } else {
        alert('Certificate template not supported!');
        return;
    }

    const printFrame = document.getElementById('printFrame');
    printFrame.src = url;

    printFrame.onload = function() {
        printFrame.contentWindow.focus();
        printFrame.contentWindow.print();
    };
};


});
