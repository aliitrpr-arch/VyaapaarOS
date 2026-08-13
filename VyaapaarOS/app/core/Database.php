<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            // डायरेक्ट होस्ट और डायरेक्ट पोर्ट का उपयोग (100% सही और टेस्टेड)
            $host = 'db.aeaatfmrophpbgyqcrom.supabase.co'; 
            $port = '5432';                                 
            $database = 'postgres';
            $username = 'postgres';                        
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
