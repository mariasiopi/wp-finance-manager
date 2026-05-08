<?php
declare(strict_types=1);

class TransactionManager{

    private $wpdb;
    private string $table;
    
    public function __construct($wpdb){
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'fin_transactions';
    }

    public function addTransaction(float $amount, int $categoryId, string $description): int|false{
        return $this->wpdb->insert(
            $this->table,
            [
                'amount' => $amount,
                'catergory_id' => $categoryId,
                'description' => $description
            ]);
    }

    public function getAllTransactions(): array{
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table}"
        );
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