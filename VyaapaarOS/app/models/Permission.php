<?php

require_once __DIR__ . '/../core/Database.php';

class Permission
{
    public static function getAll(): array
    {
        $db = Database::connect();

        $stmt = $db->query("
            SELECT
                id,
                permission_key,
                permission_name,
                module_name,
                description
            FROM permissions
            ORDER BY module_name, id
        ");

        return $stmt->fetchAll();
    }


    public static function getUserPermissions(
        int $userId
    ): array {

        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                permission_id,
                can_view,
                can_create,
                can_edit,
                can_delete
            FROM user_permissions
            WHERE user_id = :user_id
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        $result = [];

        foreach ($stmt->fetchAll() as $row) {

            $result[(int) $row['permission_id']] = [
                'can_view' => (bool) $row['can_view'],
                'can_create' => (bool) $row['can_create'],
                'can_edit' => (bool) $row['can_edit'],
                'can_delete' => (bool) $row['can_delete']
            ];
        }

        return $result;
    }


    public static function saveUserPermissions(
        int $userId,
        array $permissions
    ): void {

        $db = Database::connect();

        $db->beginTransaction();

        try {

            /*
             * Remove old permissions first.
             */
            $delete = $db->prepare("
                DELETE FROM user_permissions
                WHERE user_id = :user_id
            ");

            $delete->execute([
                'user_id' => $userId
            ]);


            /*
             * Insert only submitted permissions.
             */
            $insert = $db->prepare("
                INSERT INTO user_permissions
                (
                    user_id,
                    permission_id,
                    can_view,
                    can_create,
                    can_edit,
                    can_delete,
                    updated_at
                )
                VALUES
                (
                    :user_id,
                    :permission_id,
                    CAST(:can_view AS BOOLEAN),
                    CAST(:can_create AS BOOLEAN),
                    CAST(:can_edit AS BOOLEAN),
                    CAST(:can_delete AS BOOLEAN),
                    CURRENT_TIMESTAMP
                )
            ");


            foreach ($permissions as $permissionId => $rights) {

                $canView = !empty($rights['view'])
                    ? 'true'
                    : 'false';

                $canCreate = !empty($rights['create'])
                    ? 'true'
                    : 'false';

                $canEdit = !empty($rights['edit'])
                    ? 'true'
                    : 'false';

                $canDelete = !empty($rights['delete'])
                    ? 'true'
                    : 'false';


                $insert->execute([
                    'user_id' => $userId,

                    'permission_id' => (int) $permissionId,

                    'can_view' => $canView,

                    'can_create' => $canCreate,

                    'can_edit' => $canEdit,

                    'can_delete' => $canDelete
                ]);
            }


            $db->commit();

        } catch (Throwable $e) {

            $db->rollBack();

            throw $e;
        }
    }
}