<?php
declare(strict_types=1);

/**
 * PDO singleton. Always use prepared statements — never interpolate
 * user input into SQL strings.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/../config/config.php';
        $c = $config['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $c['host'],
            $c['port'],
            $c['name'],
            $c['charset']
        );

        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('[activityflow] DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            // Never leak connection details or raw exception text to the client.
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "Database connection failed. Check config/config.php.\n");
                exit(1);
            }
            require __DIR__ . '/../500.php';
            exit;
        }
    }

    return $pdo;
}
