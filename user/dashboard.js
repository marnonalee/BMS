
const stats = {
    totalResidents: 1254,
    activePermits: 312,
    pendingRequests: 27,
    monthlyRevenue: 58450,
    seniorCitizens: 154,
    blotterCases: 18,
    voters: 1020,
    households: 320
};

for (const key in stats) {
    let elem = document.getElementById(key);
    let count = 0;
    const interval = setInterval(() => {
        if(count >= stats[key]) clearInterval(interval);
        elem.innerText = key === 'monthlyRevenue' ? '₱' + count.toLocaleString() : count;
        count += Math.ceil(stats[key]/100);
    }, 10);
}

// Charts
const ctxResidentGrowth = document.getElementById('residentGrowthChart').getContext('2d');
new Chart(ctxResidentGrowth, {
    type: 'line',
    data: {
        labels: ['2019','2020','2021','2022','2023','2024','2025'],
        datasets: [{
            label: 'Residents',
            data: [1000, 1025, 1050, 1100, 1150, 1200, 1254],
            borderColor: '#16a34a',
            backgroundColor: 'rgba(22,163,74,0.2)',
            fill: true,
            tension: 0.4
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

const ctxAgeDistribution = document.getElementById('ageDistributionChart').getContext('2d');
new Chart(ctxAgeDistribution, {
    type: 'doughnut',
    data: {
        labels: ['0-17', '18-35', '36-50', '51+'],
        datasets: [{
            data: [250, 500, 350, 154],
            backgroundColor: ['#16a34a','#3b82f6','#22c55e','#60a5fa']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

const ctxPermitBar = document.getElementById('permitBarChart').getContext('2d');
new Chart(ctxPermitBar, {
    type: 'bar',
    data: {
        labels: ['Business', 'Clearance', 'Building', 'Others'],
        datasets: [{
            label: 'Active Permits',
            data: [120, 80, 60, 52],
            backgroundColor: ['#16a34a','#3b82f6','#22c55e','#60a5fa']
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

document.getElementById('searchTable').addEventListener('input', function(){
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#transactionsTable tbody tr');
    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});
