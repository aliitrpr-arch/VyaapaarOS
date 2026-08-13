<?php

require_once __DIR__ . '/../core/Database.php';

class Party
{
    public static function getAll(int $companyId): array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                p.id,
                p.company_id,
                p.zone_id,
                p.salesman_id,
                p.party_name,
                p.party_type,
                p.gst_type,
                p.phone,
                p.email,
                p.gst_number,
                p.state_code,
                p.address,
                p.opening_balance,
                p.opening_balance_type,
                p.credit_limit,
                p.credit_days,
                p.is_active,
                p.created_at,
                p.updated_at,

                z.zone_name,
                s.salesman_name

            FROM parties p

            LEFT JOIN zones z
                ON z.id = p.zone_id
               AND z.company_id = p.company_id

            LEFT JOIN salesmen s
                ON s.id = p.salesman_id
               AND s.company_id = p.company_id

            WHERE p.company_id = :company_id

            ORDER BY p.id DESC
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function find(
        int $id,
        int $companyId
    ): ?array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                company_id,
                zone_id,
                salesman_id,
                party_name,
                party_type,
                gst_type,
                phone,
                email,
                gst_number,
                state_code,
                address,
                opening_balance,
                opening_balance_type,
                credit_limit,
                credit_days,
                is_active

            FROM parties

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


    public static function create(
        int $companyId,
        ?int $zoneId,
        ?int $salesmanId,
        string $partyName,
        string $partyType,
        string $gstType,
        ?string $phone,
        ?string $email,
        ?string $gstNumber,
        ?string $stateCode,
        ?string $address,
        float $openingBalance,
        string $openingBalanceType,
        float $creditLimit,
        int $creditDays
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO parties
            (
                company_id,
                zone_id,
                salesman_id,
                party_name,
                party_type,
                gst_type,
                phone,
                email,
                gst_number,
                state_code,
                address,
                opening_balance,
                opening_balance_type,
                credit_limit,
                credit_days,
                is_active,
                created_at,
                updated_at
            )

            VALUES
            (
                :company_id,
                :zone_id,
                :salesman_id,
                :party_name,
                :party_type,
                :gst_type,
                :phone,
                :email,
                :gst_number,
                :state_code,
                :address,
                :opening_balance,
                :opening_balance_type,
                :credit_limit,
                :credit_days,
                TRUE,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )

            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'zone_id' => $zoneId,
            'salesman_id' => $salesmanId,
            'party_name' => $partyName,
            'party_type' => $partyType,
            'gst_type' => $gstType,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'gst_number' => $gstNumber ?: null,
            'state_code' => $stateCode ?: null,
            'address' => $address ?: null,
            'opening_balance' => $openingBalance,
            'opening_balance_type' => $openingBalanceType,
            'credit_limit' => $creditLimit,
            'credit_days' => $creditDays
        ]);

        return (int) $stmt->fetchColumn();
    }


    public static function update(
        int $id,
        int $companyId,
        ?int $zoneId,
        ?int $salesmanId,
        string $partyName,
        string $partyType,
        string $gstType,
        ?string $phone,
        ?string $email,
        ?string $gstNumber,
        ?string $stateCode,
        ?string $address,
        float $openingBalance,
        string $openingBalanceType,
        float $creditLimit,
        int $creditDays,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE parties

            SET
                zone_id = :zone_id,
                salesman_id = :salesman_id,
                party_name = :party_name,
                party_type = :party_type,
                gst_type = :gst_type,
                phone = :phone,
                email = :email,
                gst_number = :gst_number,
                state_code = :state_code,
                address = :address,
                opening_balance = :opening_balance,
                opening_balance_type = :opening_balance_type,
                credit_limit = :credit_limit,
                credit_days = :credit_days,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP

            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'zone_id' => $zoneId,
            'salesman_id' => $salesmanId,
            'party_name' => $partyName,
            'party_type' => $partyType,
            'gst_type' => $gstType,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'gst_number' => $gstNumber ?: null,
            'state_code' => $stateCode ?: null,
            'address' => $address ?: null,
            'opening_balance' => $openingBalance,
            'opening_balance_type' => $openingBalanceType,
            'credit_limit' => $creditLimit,
            'credit_days' => $creditDays,
            'is_active' => $isActive ? 'true' : 'false'
        ]);
    }


    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM parties

            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }


    public static function getZones(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                zone_name,
                state_code

            FROM zones

            WHERE company_id = :company_id
              AND is_active = TRUE

            ORDER BY zone_name
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    public static function getSalesmen(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                salesman_name,
                phone

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