document.addEventListener('DOMContentLoaded', function() {
    const t_form = document.getElementById('finance-transaction-form');
    const c_form = document.getElementById('finance-category-form');
    const b_form = document.getElementById('finance-budget-form');
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
        })
        .catch(error => console.error('Error loading transactions:', error));
    }
});