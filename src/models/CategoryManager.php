<?php
declare(strict_types=1);

class CategoryManager{

    private $wpdb;
    private string $table;

    public function __construct($wpdb){

        $this->wpdb =$wpdb;
        $this->table = $wpdb->prefix . 'fin_categories';
    }

    public function addCategory( string $name,string $slug): int|false
    {
        return $this->wpdb->insert(
            $this->table,
            [
                'name' => $name,
                'slug' => $slug
            ]
        );
    }

    public function getAllCategories(): array
    {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table}"
        );
    }

    public function getCategoryById(int $id): object|null{

        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE id = %d",
                $id
            )
        );
    }

    public function updateCategory(int $id,string $name,string $slug): int|false{

        return $this->wpdb->update(
            $this->table,
            [
                'name' => $name,
                'slug' => $slug
            ],
            [
                'id' => $id
            ]
        );
    }

    public function deleteCategory(int $id): int|false{
        return $this->wpdb->delete(

            $this->table,
            [
                'id' => $id
            ]
        );
    }
}