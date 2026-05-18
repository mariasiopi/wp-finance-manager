document.addEventListener('DOMContentLoaded', function() {
    const t_form = document.getElementById('finance-transaction-form');
    const c_form = document.getElementById('finance-category-form');
    const b_form = document.getElementById('finance-budget-form');

    let expensePieChart = null;
    let cashFlowChart = null;

    loadTransactions();

    if (t_form) {
        t_form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(t_form);

            // Δεδομένα που θέλουμε να στείλουμε
            const data = {
                amount: formData.get('tr_amount'),
                category_id: formData.get('tr_category'),
                description: formData.get('tr_description') 
            };

            // Το FETCH
            fetch(financeData.restUrl + '/transactions', { // Το financeData έρχεται από το wp_localize_script
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': financeData.nonce // Το "κλειδί" ασφαλείας
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                console.log('Success:', result);
                alert(result.message);
                loadTransactions(); // Το μήνυμα που επιστρέφει η PHP
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    }

    if(c_form){
        c_form.addEventListener('submit', function(e){
            e.preventDefault();

            const formData = new FormData(c_form);

            const data = {
                name: formData.get('name')
            };

            fetch(financeData.restUrl +  '/categories',
                {
                    method: 'POST',
                    headers:{
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': financeData.nonce
                    },
                    body: JSON.stringify(data)
                }
            )
            .then(response => response.json())
            .then(result => {
                console.log('Category Success:', result);
                alert(result.message);
            })
            .catch(error => console.error('Error:', error));
        })
    }

    if(b_form){
        b_form.addEventListener('submit', function(e){
            e.preventDefault();

            const formData = new FormData(b_form);

            const data = {
                category_id: formData.get('bg_category'),
                amount_limit: formData.get('bg_limit'),
                period: formData.get('bg_period')
            };

            fetch(financeData.restUrl + '/budgets',
                {
                    method: 'POST',
                    headers:{
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': financeData.nonce
                    },
                    body: JSON.stringify(data)
                }
            )
            .then(response => response.json())
            .then(result => {
                console.log('Budget Success', result)
                alert(result.message)
            })
            .catch(error => console.log('Error', error))
        })
    }

    function loadTransactions() {
        fetch(financeData.restUrl + '/transactions', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': financeData.nonce
            }
        })
        .then(response => response.json())
        .then(transactions => {
            const tbody = document.querySelector('.finance-card table tbody');
            tbody.innerHTML = ''; // Καθαρισμός παλιών δεδομένων

            if (transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">No transactions available</td></tr>';
                renderCharts([]); //καθαρισμός charts
                return;
            }

            transactions.forEach(tr => {
                const row = `<tr>
                    <td>${parseFloat(tr.amount).toFixed(2)}</td>
                    <td>${tr.category_name || 'N/A'}</td>
                    <td>${tr.description || ''}</td>
                    <td>${tr.transaction_date}</td>
                </tr>`;
                tbody.insertAdjacentHTML('beforeend', row);
            });

            renderCharts(transactions);
        })
        .catch(error => console.error('Error loading transactions:', error));
    }

    function renderCharts(transactions){
        if (transactions.length === 0) return;

        const categoryTotals = {};
        transactions.forEach( tr => {
            const category = tr.category_name || 'Uncategorized';
            const amount = parseFloat(tr.amount);

            if (!categoryTotals[category]){
                categoryTotals[category] = 0;
            }
            categoryTotals[category]+= amount
        });

        const pieLabels = Object.keys(categoryTotals);
        const pieData = Object.values(categoryTotals);

        const dateTotals = {};
        transactions.forEach(tr => {
            // Παίρνουμε μόνο την ημερομηνία (π.χ. "2024-03-15") αγνοώντας την ώρα
            const dateOnly = tr.transaction_date.split(' ')[0]; 
            const amount = parseFloat(tr.amount);

            if (!dateTotals[dateOnly]) {
                dateTotals[dateOnly] = 0;
            }
            dateTotals[dateOnly] += amount;
        });

        const sortedDates = Object.keys(dateTotals).sort();
        const barData = sortedDates.map(date => dateTotals[date]);

        const pieCtx = document.getElementById('expensePieChart');
        if (pieCtx) {
            // Αν υπάρχει ήδη γράφημα, το καταστρέφουμε πριν φτιάξουμε το νέο (για να μην "κολλάει" όταν ανανεώνεται)
            if (expensePieChart) expensePieChart.destroy();
            
            expensePieChart = new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        label: 'Έξοδα ανά Κατηγορία',
                        data: pieData,
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { display: true, text: 'Κατανομή Εξόδων' }
                    }
                }
            });
        }

        const barCtx = document.getElementById('cashFlowChart');
        if (barCtx) {
            if (cashFlowChart) cashFlowChart.destroy();

            cashFlowChart = new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: sortedDates,
                    datasets: [{
                        label: 'Ημερήσιες Συναλλαγές (€)',
                        data: barData,
                        backgroundColor: '#36A2EB',
                        borderColor: '#2271b1',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    },
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Ιστορικό Cash Flow' }
                    }
                }
            });
        }
    }
});