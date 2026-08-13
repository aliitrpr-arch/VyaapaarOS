<?php

require_once __DIR__ . '/../core/Database.php';

class Role
{
    public static function getAllByCompany(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                company_id,
                role_name,
                description,
                is_active,
                created_at
            FROM roles
            WHERE company_id = :company_id
            ORDER BY id
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function create(
        int $companyId,
        string $roleName,
        string $description = ''
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO roles
            (
                company_id,
                role_name,
                description,
                is_active
            )
            VALUES
            (
                :company_id,
                :role_name,
                :description,
                true
            )
            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'role_name' => trim($roleName),
            'description' => trim($description)
        ]);

        return (int) $stmt->fetchColumn();
    }
}