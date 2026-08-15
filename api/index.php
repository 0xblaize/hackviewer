<?php

declare(strict_types=1);

$root = dirname(__DIR__);
if (getenv('VERCEL') !== false && getenv('DATABASE_PATH') === false) {
    putenv('DATABASE_PATH=/tmp/hackview.sqlite');
}

require_once $root . '/app/Bootstrap.php';
require_once $root . '/app/Database.php';

$pdo = App\Database::connection();
$migrationFiles = glob($root . '/database/migrations/*.sql') ?: [];
sort($migrationFiles, SORT_STRING);
foreach ($migrationFiles as $migrationFile) {
    $sql = (string) file_get_contents($migrationFile);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (PDOException $error) {
            if (!str_contains(strtolower($error->getMessage()), 'duplicate column name')) {
                throw $error;
            }
        }
    }
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
(new App\Router())->dispatch(rtrim($path, '/') ?: '/');
