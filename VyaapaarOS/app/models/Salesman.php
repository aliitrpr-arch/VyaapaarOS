<?php

require_once __DIR__ . '/../core/Database.php';

class Salesman
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL SALESMEN
    |--------------------------------------------------------------------------
    */

    public static function getAll(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                company_id,
                user_id,
                salesman_name,
                phone,
                commission_percent,
                is_active,
                created_at,
                updated_at

            FROM salesmen

            WHERE company_id = :company_id

            ORDER BY id DESC
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    /*
    |--------------------------------------------------------------------------
    | FIND SALESMAN
    |--------------------------------------------------------------------------
    */

    public static function find(
        int $id,
        int $companyId
    ): ?array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                company_id,
                user_id,
                salesman_name,
                phone,
                commission_percent,
                is_active

            FROM salesmen

            WHERE id = :id
              AND company_id = :company_id

            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE SALESMAN
    |--------------------------------------------------------------------------
    */

    public static function create(
        int $companyId,
        ?int $userId,
        string $salesmanName,
        ?string $phone,
        float $commissionPercent
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO salesmen
            (
                company_id,
                user_id,
                salesman_name,
                phone,
                commission_percent,
                is_active,
                created_at,
                updated_at
            )

            VALUES
            (
                :company_id,
                :user_id,
                :salesman_name,
                :phone,
                :commission_percent,
                TRUE,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )

            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
            'salesman_name' => $salesmanName,
            'phone' => $phone ?: null,
            'commission_percent' => $commissionPercent
        ]);

        return (int) $stmt->fetchColumn();
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE SALESMAN
    |--------------------------------------------------------------------------
    */

    public static function update(
        int $id,
        int $companyId,
        ?int $userId,
        string $salesmanName,
        ?string $phone,
        float $commissionPercent,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE salesmen

            SET
                user_id = :user_id,
                salesman_name = :salesman_name,
                phone = :phone,
                commission_percent = :commission_percent,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'user_id' => $userId,
            'salesman_name' => $salesmanName,
            'phone' => $phone ?: null,
            'commission_percent' => $commissionPercent,
            'is_active' => $isActive ? 'true' : 'false'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE SALESMAN
    |--------------------------------------------------------------------------
    */

    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM salesmen

            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE SALESMEN
    |--------------------------------------------------------------------------
    */

    public static function getActive(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                salesman_name,
                phone,
                commission_percent

            FROM salesmen

            WHERE company_id = :company_id
              AND is_active = TRUE

            ORDER BY salesman_name
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }
}