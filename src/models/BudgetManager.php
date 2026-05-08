<?php

declare(strict_types=1);

class BudgetManager{

    private $wpdb;
    private string $table;
    private $transactionManager;
    
    public function __construct($wpdb, $transactionManager){
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'fin_budgets';
        $this->transactionManager = $transactionManager;
    }

    public function setBudget(int $categoryId,float $amountLimit,string $period): int|false{
 
        return $this->wpdb->insert(
            $this->table,
            [
                'category_id' => $categoryId,
                'amount_limit' => $amountLimit,
                'period' => $period
            ]
        );
    }

    public function getBudgetByCategory(int $categoryId): array{
        
        return $this->wpdb->get_row(

            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} 
                WHERE category_id = %d",
                $categoryId
            )
        );
    }

    public function updateBudget(int $categoryId, float $newLimit): int|false{
        return $this->wpdb->update(
            $this->table,
            [
                'amount_limit' => $newLimit
            ],
            [
                'category_id' => $categoryId
            ]
        );
    }
    
    public function getRemainingBudget(int $categoryId):float{
        $budget = $this->getBudgetByCategory($categoryId);
        $spent = $this->transactionManager->calculateSpentByCategory($categoryId);

        if(!$bugdet){
            return 0;
        }

        return $budget->amount_limit - $spent;
    }

    public function isBudgetExceeded(int $categoryId): bool {
        $budget = $this->getBudgetByCategory($categoryId);
        $spent = $this->transactionManager
            ->calculateSpentByCategory($categoryId);

        if (!$budget) {
            return false;
        }

        return $spent > $budget->amount_limit;
    }
}