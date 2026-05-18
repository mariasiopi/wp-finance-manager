<?php
/*
Plugin Name: Digital Cash Flow Monitor
*/
require_once plugin_dir_path(__FILE__) . 'src/models/CategoryManager.php';
require_once plugin_dir_path(__FILE__) . 'src/models/TransactionManager.php';
require_once plugin_dir_path(__FILE__) . 'src/models/BudgetManager.php';


function create_db(){
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_categories = $wpdb->prefix. 'fin_categories';
    $table_transactions = $wpdb->prefix. 'fin_transactions';
    $table_budgets = $wpdb->prefix. 'fin_budgets';

    $sql_categories = "CREATE TABLE $table_categories( 
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL, 

        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),

        KEY name (name) 
    ) $charset_collate;";

    $sql_transactions = "CREATE TABLE $table_transactions (
        id bigint(20) NOT NULL AUTO_INCREMENT, 
        amount decimal(10,2) NOT NULL, 
        transaction_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        category_id mediumint(9) NOT NULL,
        description text,

        PRIMARY KEY  (id),
        FOREIGN KEY (category_id)
        REFERENCES $table_categories(id)
        ON DELETE CASCADE,
        
        KEY category_date (transaction_date, category_id)
    ) $charset_collate;";

    $sql_budgets = "CREATE TABLE $table_budgets (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        category_id mediumint(9) NOT NULL,
        amount_limit decimal(10,2) NOT NULL,
        period ENUM('monthly','weekly','yearly') NOT NULL,

        PRIMARY KEY (id),
        FOREIGN KEY (category_id)
        REFERENCES $table_categories(id)
        ON DELETE CASCADE,
        KEY period (period)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    dbDelta( $sql_categories );
    dbDelta( $sql_transactions );
    dbDelta( $sql_budgets );
}

// Εκτέλεση κατά την ενεργοποίηση (activation)
register_activation_hook( __FILE__, 'create_db' );

class FinancePluginInit{
    private TransactionManager $transactionManager;
    private CategoryManager $categoryManager;
    private BudgetManager $budgetManager;

    public function __construct(){
        global $wpdb;
    
        $this->categoryManager = new CategoryManager($wpdb);
        $this->transactionManager = new TransactionManager($wpdb);
        $this->budgetManager = new BudgetManager(
            $wpdb,
            $this->transactionManager);
    
        add_action('admin_menu', [$this, 'create_admin_pages']);
        add_action('rest_api_init', [$this, 'register_finance_routes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        }

    public function create_admin_pages(): void{
        add_menu_page(
            'Finance App',
            'Finance Manager', 
            'manage_options',
            'finance-manager-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-chart-line'
        );

        add_action('admin_head', function () {
            echo '
            <style>

                table {
                    width: 100%;
                    border-collapse: collapse;
                }

                th, td {
                    text-align: left;
                    padding: 12px;
                    border-bottom: 1px solid #eee;
                }

                tr:hover {
                    background-color: #f9f9f9;
                }

                .finance-wrap{
                    max-width: 900px;
                }

                .finance-card{
                    background: #fff;
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
                }

                .finance-card h2{
                    margin-top: 0;
                }

                .finance-form{
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .finance-form input,
                .finance-form select,
                .finance-form textarea{
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                }

                .finance-form button{
                    background: #2271b1;
                    color: white;
                    border: none;
                    padding: 10px;
                    border-radius: 6px;
                    cursor: pointer;
                }

                .finance-form button:hover{
                    background: #135e96;
                }

                .finance-title{
                    font-size: 22px;
                    margin-bottom: 10px;
                }
            </style>';
        });
    }

    public function register_finance_routes(): void{

        register_rest_route(
            'finance-app/v1',
            '/transactions',
            [
                'methods' => "GET",
                'callback' => [$this, 'get_api_transactions'],
                'permission_callback' => [$this, 'check_api_permissions']
            ]
        );

        register_rest_route(
            'finance-app/v1',
            '/transactions',
            [
                'methods' => 'POST',
                'callback' => [$this, 'post_api_transactions'],
                'permission_callback' => [$this, 'check_api_permissions']
            ]
        );

        register_rest_route(
            'finance-app/v1', 
            '/transactions', 
            [
                'methods'  => 'PUT',
                'callback' => [$this, 'update_api_transactions'],
                'permission_callback' => [$this, 'check_api_permissions'],
            ]
        );

        register_rest_route(
            'finance-app/v1',
            '/transactions',
            [
                'methods'  => 'DELETE',
                'callback' => [$this, 'delete_api_transactions'],
                'permission_callback' => [$this, 'check_api_permissions'],
            ]
        );

        register_rest_route(
            'finance-app/v1',
            '/categories',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_api_categories'],
                'permission_callback' => [$this, 'check_api_permissions']
            ]
        );

        register_rest_route(
            'finance-app/v1',
            '/categories',
            [
                'methods' => 'POST',
                'callback' => [$this, 'post_api_categories'],
                'permission_callback' => [$this, 'check_api_permissions']
            ]
        );

        register_rest_route(
            'finance-app/v1', 
            '/categories', 
            [
                'methods'  => 'PUT',
                'callback' => [$this, 'update_api_categories'],
                'permission_callback' => [$this, 'check_api_permissions'],
            ]
        );

        register_rest_route(
            'finance-app/v1',
            '/categories',
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_api_categories'],
                'permission_callback' => [$this, 'check_api_permissions']
            ]
        );

        register_rest_route(
            'finance-app/v1',
            '/budgets',
            [
                'methods' => 'POST',
                'callback' => [$this, 'post_api_budgets'],
                'permission_callback' => [$this, 'check_api_permissions']
            ]
        );

        register_rest_route(
            'finance-app/v1',
            '/budgets',
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_api_budgets'],
                'permission_callback' => [$this, 'check_api_permissions']
            ]
        );

        register_rest_route(
            'finance-app/v1',
            '/budgets',
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_api_budgets'],
                                'permission_callback' => [$this, 'check_api_permissions']

            ]
        );
    }

    public function get_api_transactions(): WP_REST_Response{

        $data = $this->transactionManager->getAllTransactions();
        return new WP_REST_Response($data, 200);
    }

    public function post_api_transactions(WP_REST_Request $request): WP_REST_Response {
        
        $params = $request->get_json_params(); 

        $amount      = (float) $params['amount'];
        $category_id = absint($params['category_id']);
        $description = sanitize_textarea_field($params['description']);

        if ($amount <= 0 || $category_id <= 0) {
            return new WP_REST_Response(['message' => 'Λάθος δεδομένα'], 400); // 400 = Bad Request
        }

        $result = $this->transactionManager->addTransaction($amount, $category_id, $description);

        if ($result) {
            return new WP_REST_Response(['message' => 'Η συναλλαγή προστέθηκε!'], 201); // 201 = Created
        } else {
            return new WP_REST_Response(['message' => 'Αποτυχία αποθήκευσης'], 500);
        }
    
    }

    public function update_api_transactions(WP_REST_Request $request): WP_REST_Response {
        $id = $request['id']; // Το ID από το URL
        $params = $request->get_json_params();

        $amount      = (float) $params['amount'];
        $description = sanitize_textarea_field($params['description']);

        $result = $this->transactionManager->updateTransaction($id, $amount, $description);

        if ($result !== false) {
            return new WP_REST_Response(['message' => 'Ενημερώθηκε επιτυχώς!'], 200);
        }else{
            return new WP_REST_Response(['message' => 'Αποτυχία ενημέρωσης'], 500);
        }
    }

    public function get_api_categories(): WP_REST_Response{

        $data = $this->categoryManager->getAllCategories();

        if(!$data){
            return new WP_REST_Response(['message' => 'Δεν βρέθηκαν κατηγορίες!'], 500);
        }else{
            return new WP_REST_Response($data, 200);
        }
    }

    public function post_api_categories(WP_REST_Request $request): WP_REST_Response{

        $params = $request->get_json_params();

        $name = sanitize_text_field($params['name']);

        $result = $this->categoryManager->addCategory($name, sanitize_title($name));

        if($result){
             return new WP_REST_Response(['message' => 'Η κατηγορία προστέθηκε!'], 201); // 201 = Created
        }else {
            return new WP_REST_Response(['message' => 'Αποτυχία αποθήκευσης'], 500);
        }
    }
    
    public function update_api_categories(WP_REST_Request $request): WP_REST_Response {
    
        $params = $request->get_json_params();
        $id = absint($params['id']);
        $name = sanitize_text_field($params['name']);

        $result = $this->categoryManager->updateCategory($id, $name, sanitize_title($name));

        if ($result !== false) {
            return new WP_REST_Response(['message' => 'Ενημερώθηκε επιτυχώς!'], 200);
        }else{
            return new WP_REST_Response(['message' => 'Αποτυχία ενημέρωσης'], 500);
        }
    }

    public function delete_api_categories(WP_REST_Request $request): WP_REST_Response{

        $params = $request->get_json_params();
        $id = absint($params['id']);

        $result = $this->categoryManager->deleteCategory($id);

         if ($result !== false) {
            return new WP_REST_Response(['message' => 'Διαγράφηκε επιτυχώς!'], 200);
        }else{
            return new WP_REST_Response(['message' => 'Αποτυχία διαγραφής'], 500);
        }

    }

    public function post_api_budgets(WP_REST_Request $request): WP_REST_Response{

        $params = $request->get_json_params();

        $limit = (float) $params['amount_limit'];
        $category_id = absint($params['category_id']);  
        $period      = sanitize_text_field($params['period']);

        if ($category_id <= 0 || $limit <= 0 || !in_array($period, ['monthly', 'weekly', 'yearly'])) {
            return new WP_REST_Response(['message' => 'Μη έγκυρα δεδομένα'], 400);
        }

        $result = $this->budgetManager->setBudget($category_id, $limit, $period);

        if ($result !== false) {
            return new WP_REST_Response(['message' => 'Το όριο ορίστηκε επιτυχώς!'], 201);
        } else {
            return new WP_REST_Response(['message' => 'Αποτυχία ενημέρωσης προϋπολογισμού'], 500);
        }
    }

    public function update_api_budgets(WP_REST_Request $request): WP_REST_Response {
       
        $params = $request->get_json_params();

        $id = absint($params['id']);
        $limit = (float) $params['amount_limit'];

        $result = $this->budgetManager->updateBudget($id, $limit);

        if ($result !== false) {
            return new WP_REST_Response(['message' => 'Ενημερώθηκε επιτυχώς!'], 200);
        }else{
            return new WP_REST_Response(['message' => 'Αποτυχία ενημέρωσης'], 500);
        }
    }
    
    public function delete_api_budgets(WP_REST_Request $request): WP_REST_Response{

        $params = $request->get_json_params();
        $id = absint($params['id']);

        $result = $this->budgetManager->deleteBudget($id);

         if ($result !== false) {
            return new WP_REST_Response(['message' => 'Διαγράφηκε επιτυχώς!'], 200);
        }else{
            return new WP_REST_Response(['message' => 'Αποτυχία διαγραφής'], 500);
        }

    }
    

    
    public function check_api_permissions(): bool{
        return current_user_can('manage_options');
    }


    public function render_dashboard():void{

        if (!current_user_can('manage_options')) {
            wp_die('Δεν έχετε δικαίωμα πρόσβασης σε αυτή τη σελίδα.');
        }

        $categories = $this->categoryManager->getAllCategories();

        //Δημιουργία dashboard

        echo '<div class="finance-wrap">';
        echo '<h1 class="finance-title">Finance Dashboard</h1>';

        echo '<div class="finance-card" style="display: flex; justify-content: space-between; gap: 20px;">';
            
            // Container για το Pie Chart (Κατανομή Εξόδων)
            echo '<div style="width: 48%;">';
            echo '<canvas id="expensePieChart"></canvas>';
            echo '</div>';

            // Container για το Bar Chart (Ιστορικό Cash Flow)
            echo '<div style="width: 48%;">';
            echo '<canvas id="cashFlowChart"></canvas>';
            echo '</div>';
            
        echo '</div>';

        //--------Filters-------------------------------------------

        echo '<form id="finance-filter-form" style="display: flex; gap: 10px; margin-bottom: 20px;">';
        
        echo '  <input type="date" id="filter-start" name="start_date">';
        echo '  <input type="date" id="filter-end" name="end_date">';
        
        echo '  <button type="submit" style="background: #2271b1; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Φιλτράρισμα</button>';
        echo '  <button type="button" id="reset-filter" style="background: #ccc; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Καθαρισμός</button>';
        
        echo '</form>';

        //--------Transaction Table--------------------------------
        echo '<h2>Ιστορικό Συναλλαγών</h2>';
        echo '<div class="finance-card">';

        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Amount</th>';
        echo '<th>Category</th>';
        echo '<th>Description</th>';
        echo '<th>Date</th>';
        echo '</tr>';
        echo '</thead>';
        
        echo '<tbody>';
        
        echo '</tbody>';

        echo '</table>';
        echo '</div>';

        // CATEGORY CARD
        echo '<div class="finance-card">';
        echo '<h2>Προσθήκη κατηγορίας</h2>';

        echo '<form id="finance-category-form" method="POST" class="finance-form">';
             wp_nonce_field('fin_category_action', 'fin_category_nonce');
        echo '<input type="text" name="name" placeholder="Category name" required>';
        echo '<button type="submit" name="submit_category">Add Category</button>';
        echo '</form>';

        echo '</div>';

        // TRANSACTION CARD
        echo '<div class="finance-card">';
            echo '<h2>Προσθήκη συναλλαγής</h2>';

            echo '<form id="finance-transaction-form" method="POST" class="finance-form">';
                    wp_nonce_field('fin_transaction_action', 'fin_transaction_nonce');

                echo '<input type="number" step="0.01" name="tr_amount" placeholder="Amount" required>';

                echo '<select name="tr_category" required>';
                    foreach ($categories as $cat){
                        echo "<option value='" . esc_attr($cat->id) . "'>" . esc_html($cat->name) . "</option>";
                    }
                echo '</select>';

                echo '<textarea name="tr_description" placeholder="Description"></textarea>';

                echo '<button type="submit" name="submit_transaction" id="submit-btn">Add Transaction</button>';
            echo '</form>';

        echo '</div>';

        // BUDGET CARD
        echo '<div class="finance-card">';
        
        echo '<h2>Προσθήκη ορίου συναλλαγών</h2>';

        echo '<form id="finance-budget-form" method="POST" class="finance-form">';
            wp_nonce_field('fin_budget_action', 'fin_budget_nonce');

        
        echo '<select name="bg_category" required style="width:100%">';
        echo '<option value="">Επιλογή Κατηγορίας</option>';
        foreach ($categories as $cat) {
             echo "<option value='" . esc_attr($cat->id) . "'>" . esc_html($cat->name) . "</option>";
        }
        echo '</select><br><br>';

        echo '<input type="number" step="0.01" name="bg_limit" placeholder="Όριο (€)" style="width:100%" required><br><br>';

        echo '<select name="bg_period" style="width:100%">';
        echo '<option value="monthly">Μηνιαίος</option>';
        echo '<option value="weekly">Εβδομαδιαίος</option>';
        echo '<option value="yearly">Ετήσιος</option>';
        echo '</select><br><br>';

        echo '<button type="submit" name="submit_budget">Add Budget</button>';
        echo '</form></div>';
        echo '</div>';
    }


    public function enqueue_assets(): void{
        wp_enqueue_script('finance-dashboard',
            plugins_url('assets/js/dashboard.js', __FILE__),
            ['jquery'],
            '1.0.0',
            true);

        wp_localize_script('finance-dashboard', 'financeData', [
            'restUrl' => esc_url_raw(rest_url('finance-app/v1')),
            'nonce'   => wp_create_nonce('wp_rest')
        ]);

        wp_enqueue_script('chartjs-lib', 
            'https://cdn.jsdelivr.net/npm/chart.js', 
            [], 
            '4.0.0', 
            true
        );
    }
}
new FinancePluginInit();