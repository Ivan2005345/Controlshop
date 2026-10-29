<?php
/**
 * Conexión a PostgreSQL (Supabase) con PDO.
 * Los datos de conexión vienen SIEMPRE de variables de entorno, nunca del código.
 */

class DatabaseUnavailable extends RuntimeException
{
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST');
    $port = env('DB_PORT', '5432');
    $name = env('DB_NAME', 'postgres');
    $user = env('DB_USER');
    $pass = env('DB_PASSWORD');

    if (!$host || !$user || $pass === null) {
        throw new DatabaseUnavailable('Faltan las variables de entorno DB_HOST, DB_USER o DB_PASSWORD.');
    }

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $host, $port, $name, env('DB_SSLMODE', 'require'));

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Compatible con el pooler de Supabase en modo "transaction" y en modo "session".
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        // Las fechas y los reportes ("hoy", "este mes") se calculan en hora de Colombia.
        $pdo->exec("SET TIME ZONE 'America/Bogota'");
    } catch (PDOException $e) {
        $pdo = null;
        // Se registra el motivo real en el log, pero nunca se muestra al visitante.
        error_log('[controlshop] Falló la conexión a la base de datos: ' . $e->getMessage());
        throw new DatabaseUnavailable('No se pudo conectar con la base de datos.', 0, $e);
    }

    return $pdo;
}

/** Devuelve todas las filas de una consulta. Siempre con parámetros, nunca concatenando datos. */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Devuelve una fila (o null si no hay). */
function db_row(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Devuelve el primer valor de la primera fila (o false si no hay). */
function db_val(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/** Ejecuta INSERT/UPDATE/DELETE y devuelve cuántas filas afectó. */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
