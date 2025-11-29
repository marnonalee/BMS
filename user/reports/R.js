const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('toggleSidebar');

toggleBtn.onclick = () => {
    sidebar.classList.toggle('sidebar-collapsed');
    let icon = toggleBtn.textContent;
    toggleBtn.textContent = icon === 'chevron_left' ? 'chevron_right' : 'chevron_left';
};

let currentPage = 1;

function loadChartData() {
    const sex = $('#sexFilter').val();
    const voter = $('#voterFilter').val();
    const senior = $('#seniorFilter').val();
    const pwd = $('#pwdFilter').val();
    const solo = $('#soloFilter').val();
    const fourps = $('#fourpsFilter').val();
    const ageCategory = $('#ageCategoryFilter').val();
    const citizenship = $('#citizenshipFilter').val();
    const employment = $('#employmentFilter').val();

    $.ajax({
        url: 'chart_data.php',
        type: 'GET',
        data: { sex, voter, senior, pwd, solo, fourps, ageCategory, citizenship, employment },
        dataType: 'json',
        success: function(data) {
            // Update sex chart
            residentsSexChart.data.datasets[0].data = [data.male, data.female];
            residentsSexChart.update();

            // Update categories chart including new employment statuses
            categoriesChart.data.datasets[0].data = [
                data.seniors, data.pwds, data.solo_parents, data.four_ps,
                data.voters, data.filipino, data.non_filipino,
                data.employed, data.unemployed, data.student, data.self_employed, data.retired,
                data.ofw, data.ip // <-- added
            ];
            categoriesChart.update();

            // Update age chart
            ageChart.data.datasets[0].data = [
                data.ageBrackets.under5, data.ageBrackets.age5_9, data.ageBrackets.age10_14, data.ageBrackets.age15_19,
                data.ageBrackets.age20_24, data.ageBrackets.age25_29, data.ageBrackets.age30_34, data.ageBrackets.age35_39,
                data.ageBrackets.age40_44, data.ageBrackets.age45_49, data.ageBrackets.age50_54, data.ageBrackets.age55_59,
                data.ageBrackets.age60_64, data.ageBrackets.age65_69, data.ageBrackets.age70_74, data.ageBrackets.age75_79,
                data.ageBrackets.age80_over
            ];
            ageChart.update();
        }
    });
}

// ------------------ TABLE & PAGINATION ------------------

function loadResidents(page = 1) {
    currentPage = page;
    const sex = $('#sexFilter').val();
    const voter = $('#voterFilter').val();
    const senior = $('#seniorFilter').val();
    const pwd = $('#pwdFilter').val();
    const solo = $('#soloFilter').val();
    const fourps = $('#fourpsFilter').val();
    const ageCategory = $('#ageCategoryFilter').val();
    const citizenship = $('#citizenshipFilter').val();
    const employment = $('#employmentFilter').val();
    const perPage = $('#perPageSelect').val();

    $.ajax({
        url: 'load_residents.php',  // Make sure this is correct
        type: 'GET',
        data: { sex, voter, senior, pwd, solo, fourps, ageCategory, citizenship, employment, perPage, page },
        dataType: 'json',
        success: function(data) {
            $('#residentsBody').html(data.rows);  // Replace table body
            $('#filteredTotals').text('Total: ' + data.total);
            renderPagination(data.page, data.totalPages);
        },
        error: function(xhr, status, error) {
            console.error('Error loading residents:', error);
        }
    });
}

function renderPagination(page, totalPages) {
    $('#paginationWrapper').html('');
    if(totalPages <= 1) return; // hide if only one page

    let paginationHtml = `
        <div class="pagination-container flex justify-center mt-4 space-x-2">
            <button ${page <= 1 ? 'disabled' : ''} class="px-3 py-1 bg-gray-300 rounded prev-btn">Prev</button>
            <span class="px-3 py-1 bg-gray-200 rounded">Page ${page} of ${totalPages}</span>
            <button ${page >= totalPages ? 'disabled' : ''} class="px-3 py-1 bg-gray-300 rounded next-btn">Next</button>
        </div>
    `;
    $('#paginationWrapper').html(paginationHtml);

    $('.prev-btn').click(() => { if(page > 1) loadResidents(page - 1); });
    $('.next-btn').click(() => { if(page < totalPages) loadResidents(page + 1); });
}

// ------------------ EVENT LISTENERS ------------------

$('#sexFilter, #voterFilter, #seniorFilter, #pwdFilter, #soloFilter, #fourpsFilter, #ageCategoryFilter, #citizenshipFilter, #employmentFilter, #perPageSelect')
    .change(() => loadResidents(1));

$(document).ready(() => {
    loadResidents(1);
    loadChartData();
});
