<?php

require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Database.php';

class PermissionMiddleware
{
    public static function check(
        string $permissionKey,
        string $action = 'view'
    ): bool {

        Session::start();

        // User login check
        if (!Session::has('user_id')) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | ADMIN = Super Administrator
        |--------------------------------------------------------------------------
        | ADMIN user ko saare permissions automatically milenge.
        | Iske liye user_permissions table me entry zaroori nahi hai.
        */

        $roleName = strtoupper(
            trim((string) Session::get('role_name'))
        );

        if ($roleName === 'ADMIN') {
            return true;
        }


        $userId = (int) Session::get('user_id');

        $db = Database::connect();


        /*
        |--------------------------------------------------------------------------
        | Action -> Database Column
        |--------------------------------------------------------------------------
        */

        $column = match ($action) {

            'view'   => 'can_view',

            'create' => 'can_create',

            'edit'   => 'can_edit',

            'delete' => 'can_delete',

            default  => null
        };


        if ($column === null) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Permission Check
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT up.{$column}

            FROM user_permissions up

            INNER JOIN permissions p
                ON p.id = up.permission_id

            WHERE up.user_id = :user_id
              AND p.permission_key = :permission_key

            LIMIT 1
        ";


        $stmt = $db->prepare($sql);


        $stmt->execute([
            'user_id' => $userId,
            'permission_key' => $permissionKey
        ]);


        $result = $stmt->fetch();


        return $result
            && (bool) $result[$column];
    }


    public static function require(
        string $permissionKey,
        string $action = 'view'
    ): void {

        if (!self::check($permissionKey, $action)) {

            http_response_code(403);

            echo '<!DOCTYPE html>

            <html>

            <head>

                <title>Access Denied</title>

                <style>

                    body {
                        font-family: Arial;
                        background: #f4f6f9;
                        text-align: center;
                        padding-top: 100px;
                    }

                    .box {
                        background: white;
                        width: 450px;
                        margin: auto;
                        padding: 35px;
                        border-radius: 10px;
                        box-shadow: 0 5px 20px rgba(0,0,0,.1);
                    }

                    h1 {
                        color: #dc2626;
                    }

                    a {
                        display: inline-block;
                        margin-top: 20px;
                        text-decoration: none;
                    }

                </style>

            </head>

            <body>

                <div class="box">

                    <h1>Access Denied ❌</h1>

                    <p>
                        You do not have permission to access this page.
                    </p>

                    <a href="dashboard.php">
                        ← Back to Dashboard
                    </a>

                </div>

            </body>

            </html>';

            exit;
        }
    }
}