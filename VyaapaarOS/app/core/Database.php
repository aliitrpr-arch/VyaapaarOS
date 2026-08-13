<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            // IPv4 कंपैटिबल पूल होस्ट और पोर्ट का सटीक उपयोग
            $host = 'aws-0-ap-northeast-1.pooler.supabase.com'; // पूलर होस्ट (IPv4 सपोर्टेड)
            $port = '6543';                                      // पूलर पोर्ट नंबर
            $database = 'postgres';
            $username = 'postgres.aeaatfmrophpbgyqcrom';        // आपका पूरा यूजरनेम (डॉट आईडी के साथ)
            $password = '120888Shoukat';                        // आपका वही नया रीसेट पासवर्ड

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
