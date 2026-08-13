<?php

require_once __DIR__ . '/../core/Database.php';

class Zone
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL ZONES
    |--------------------------------------------------------------------------
    */

    public static function getAll(
        int $companyId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                z.id,
                z.company_id,
                z.zone_name,
                z.parent_zone_id,
                z.state_code,
                z.description,
                z.is_active,
                z.created_at,

                pz.zone_name AS parent_zone_name

            FROM zones z

            LEFT JOIN zones pz
                ON pz.id = z.parent_zone_id
               AND pz.company_id = z.company_id

            WHERE z.company_id = :company_id

            ORDER BY z.id DESC
        ");

        $stmt->execute([
            'company_id' => $companyId
        ]);

        return $stmt->fetchAll();
    }


    /*
    |--------------------------------------------------------------------------
    | FIND ZONE
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
                zone_name,
                parent_zone_id,
                state_code,
                description,
                is_active

            FROM zones

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
    | CREATE ZONE
    |--------------------------------------------------------------------------
    */

    public static function create(
        int $companyId,
        string $zoneName,
        ?int $parentZoneId,
        ?string $stateCode,
        ?string $description
    ): int {

        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO zones
            (
                company_id,
                zone_name,
                parent_zone_id,
                state_code,
                description,
                is_active,
                created_at
            )

            VALUES
            (
                :company_id,
                :zone_name,
                :parent_zone_id,
                :state_code,
                :description,
                TRUE,
                CURRENT_TIMESTAMP
            )

            RETURNING id
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'zone_name' => $zoneName,
            'parent_zone_id' => $parentZoneId,
            'state_code' => $stateCode ?: null,
            'description' => $description ?: null
        ]);

        return (int) $stmt->fetchColumn();
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE ZONE
    |--------------------------------------------------------------------------
    */

    public static function update(
        int $id,
        int $companyId,
        string $zoneName,
        ?int $parentZoneId,
        ?string $stateCode,
        ?string $description,
        bool $isActive
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE zones

            SET
                zone_name = :zone_name,
                parent_zone_id = :parent_zone_id,
                state_code = :state_code,
                description = :description,
                is_active = :is_active

            WHERE id = :id
              AND company_id = :company_id
        ");

        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId,
            'zone_name' => $zoneName,
            'parent_zone_id' => $parentZoneId,
            'state_code' => $stateCode ?: null,
            'description' => $description ?: null,
            'is_active' => $isActive ? 'true' : 'false'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE ZONE
    |--------------------------------------------------------------------------
    */

    public static function delete(
        int $id,
        int $companyId
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM zones

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
    | GET ACTIVE PARENT ZONES
    |--------------------------------------------------------------------------
    */

    public static function getActive(
        int $companyId,
        ?int $excludeId = null
    ): array {

        $db = Database::connect();

        $sql = "
            SELECT
                id,
                zone_name,
                state_code

            FROM zones

            WHERE company_id = :company_id
              AND is_active = TRUE
        ";

        $params = [
            'company_id' => $companyId
        ];

        /*
        | Prevent selecting itself as parent
        */

        if ($excludeId !== null && $excludeId > 0) {

            $sql .= "
                AND id <> :exclude_id
            ";

            $params['exclude_id'] = $excludeId;
        }

        $sql .= "
            ORDER BY zone_name
        ";

        $stmt = $db->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}