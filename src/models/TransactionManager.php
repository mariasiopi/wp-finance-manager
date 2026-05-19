<?php
declare(strict_types=1);

class TransactionManager{
    
    private $wpdb;
    private string $table;
    
    public function __construct($wpdb){
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'fin_transactions';
    }

    public function addTransaction(float $amount, int $categoryId, string $description): bool{
        return $this->wpdb->insert(
            $this->table,
            [
                'amount' => $amount,
                'category_id' => $categoryId,
                'description' => $description
            ]
        )!== false;

    }

    public function updateTransaction(int $transactionId, float $amount, string $description) : bool {

        return $this->wpdb->update(
            $this->table,
            [
                'amount' => $amount,
                'description' => $description
            ],
            [
                'id' => $transactionId
            ]
        );
    }
    

    public function getAllTransactions($start_date = '', $end_date = ''): array{
            global $wpdb;
            $table_transactions = $wpdb->prefix . 'fin_transactions';
            $table_categories = $wpdb->prefix . 'fin_categories';

            $sql = "SELECT
                t.amount,
                t.description,
                t.transaction_date,
                c.name as category_name
                FROM $table_transactions t
                LEFT JOIN $table_categories c ON t.category_id = c.id
                WHERE 1=1";

            $params = [];

            if (!empty($start_date)){
                $sql .= " AND t.transaction_date >= %s";
                $params[] = $start_date . ' 00:00:00';
            }

            if (!empty($end_date)){
                $sql .= " AND t.transaction_date <= %s";
                $params[] = $end_date . ' 23:59:59';
            }

            $sql .= " ORDER BY t.transaction_date DESC";

            if (!empty($params)){
                $prepared_sql = $wpdb->prepare($sql, ...$params);
                $results = $wpdb->get_results($prepared_sql);
            } else {
                $results = $wpdb->get_results($sql);
            }

            return $results;
    }
    
    public function calculateSpentByCategory(int $categoryId): float{
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(amount)
                FROM {$this->table}
                WHERE category_id = %d",
                $categoryId
            )
        );
    }

    public function getTotalExpenses():float{
        return $this->wpdb->get_var(

                "SELECT SUM(amount) FROM {$this->table}"
            );
    }

    public function getTransactionsByCategory(int $categoryId): array{
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE category_id = %d",
                $categoryId
            )
        );
    }
}