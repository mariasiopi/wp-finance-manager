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

    $sql_transactions = "CREATE TABLE $table_transactions(
        id bigint(20) NOT NULL AUTO_INCREMENT, 
        amount decimal(10,2) NOT NULL, 
        transaction_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        category_id mediumint(9) NOT NULL,
        description text,

        PRIMARY KEY (id),
        FOREIGN KEY (category_id)
        REFERENCES $table_categories(id),
        ON DELETE CASCADE
        
        KEY category_date (transaction_date, category_id)
    ) $charset_collate;";

    $sql_budgets = "CREATE TABLE $table_budgets (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        category_id mediumint(9) NOT NULL,
        amount_limit decimal(10,2) NOT NULL,
        period ENUM('monthly','weekly','yearly') NOT NULL,

        PRIMARY KEY  (id),
        FOREIGN KEY (category_id)
        REFERENCES $table_categories(id),
        ON DELETE CASCADE
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
    }

    public function render_dashboard():void{

        $categories = $this->categoryManager->getAllCategories();

        echo "<h1>Categories: </h1>";
        echo "<ul>";

        foreach ($categories as $cat){
            echo "<li> {$cat->name} </li>";
        }

        echo "</ul>";
    }
    
}
new FinancePluginInit();