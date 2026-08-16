<?php

/**
 * Persistent storage adapter.
 *
 * XAMPP/local development keeps using the CSV files in this repository.
 * Vercel uses PostgreSQL because a Function's bundled filesystem is read-only.
 */

function persistent_storage_database_url()
{
    foreach (['DATABASE_URL', 'POSTGRES_URL', 'NEON_DATABASE_URL'] as $name) {
        $value = getenv($name);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return null;
}

function persistent_storage_is_vercel()
{
    return getenv('VERCEL') === '1';
}

function persistent_storage_relative_path($filepath)
{
    $root = rtrim(str_replace('\\', '/', __DIR__), '/') . '/';
    $path = str_replace('\\', '/', (string) $filepath);

    if (strpos($path, $root) !== 0) {
        throw new RuntimeException('Lokasi berkas data berada di luar direktori aplikasi.');
    }

    $relative = substr($path, strlen($root));
    if (!preg_match('/^csv_[a-z0-9_]+\/[a-z0-9_-]+\.csv$/i', $relative)) {
        throw new RuntimeException('Nama berkas data tidak valid.');
    }

    return $relative;
}

function persistent_storage_pdo()
{
    static $resolved = false;
    static $pdo = null;

    if ($resolved) {
        return $pdo;
    }
    $resolved = true;

    $databaseUrl = persistent_storage_database_url();
    if ($databaseUrl === null) {
        return null;
    }

    $parts = parse_url($databaseUrl);
    if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
        throw new RuntimeException('Format DATABASE_URL tidak valid.');
    }

    $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
    if (!in_array($scheme, ['postgres', 'postgresql'], true)) {
        throw new RuntimeException('DATABASE_URL harus menggunakan PostgreSQL.');
    }

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $host = $parts['host'];
    $port = isset($parts['port']) ? (int) $parts['port'] : 5432;
    $database = ltrim(rawurldecode($parts['path']), '/');
    $sslmode = isset($query['sslmode']) ? preg_replace('/[^a-z-]/i', '', $query['sslmode']) : 'require';
    $username = isset($parts['user']) ? rawurldecode($parts['user']) : '';
    $password = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';

    $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS dashboard_csv_files ('
        . 'path TEXT PRIMARY KEY, '
        . 'content TEXT NOT NULL, '
        . 'updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()'
        . ')'
    );

    return $pdo;
}

function persistent_storage_read($filepath)
{
    $relative = persistent_storage_relative_path($filepath);
    $pdo = persistent_storage_pdo();

    if ($pdo !== null) {
        $statement = $pdo->prepare('SELECT content FROM dashboard_csv_files WHERE path = :path');
        $statement->execute(['path' => $relative]);
        $row = $statement->fetch();
        if ($row !== false) {
            return $row['content'];
        }
    }

    if (!is_file($filepath)) {
        return null;
    }

    $contents = file_get_contents($filepath);
    return $contents === false ? null : $contents;
}

function persistent_storage_write($filepath, $contents)
{
    $relative = persistent_storage_relative_path($filepath);
    $pdo = persistent_storage_pdo();

    if ($pdo !== null) {
        $statement = $pdo->prepare(
            'INSERT INTO dashboard_csv_files (path, content, updated_at) '
            . 'VALUES (:path, :content, NOW()) '
            . 'ON CONFLICT (path) DO UPDATE '
            . 'SET content = EXCLUDED.content, updated_at = NOW()'
        );
        $statement->execute([
            'path' => $relative,
            'content' => (string) $contents,
        ]);
        return;
    }

    if (persistent_storage_is_vercel()) {
        throw new RuntimeException('DATABASE_URL belum dikonfigurasi di Vercel.');
    }

    if (file_put_contents($filepath, (string) $contents, LOCK_EX) === false) {
        throw new RuntimeException('Gagal menyimpan berkas CSV.');
    }
}
