<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            // सीधे लाइव Supabase डेटाबेस से जोड़ना (No config file dependency)
            $host = '://supabase.com';
            $port = '6543';
            $database = 'postgres';
            $username = 'postgres.aeaatfmrophpbgyqcrom';
            $password = '120888Shoukat'; 

            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

            self::$pdo = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        }

        return self::$pdo;
    }
}
