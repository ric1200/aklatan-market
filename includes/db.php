<?php
/** PDO connection factory. One shared connection per request. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . (int) DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $ex) {
        log_error('Database connection failed: ' . $ex->getMessage());
        if (PHP_SAPI === 'cli') { throw $ex; }
        include APP_ROOT . '/includes/db-error.php';
        exit;
    }
    return $pdo;
}

/** Run a prepared statement and return it. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $name = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
        $type = PDO::PARAM_STR;
        if (is_int($value)) { $type = PDO::PARAM_INT; }
        elseif (is_bool($value)) { $type = PDO::PARAM_BOOL; }
        elseif ($value === null) { $type = PDO::PARAM_NULL; }
        $stmt->bindValue($name, $value, $type);
    }
    $stmt->execute();
    return $stmt;
}

/** First matching row, or null. */
function fetch_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** All matching rows. */
function fetch_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** Single scalar value from the first column of the first row. */
function fetch_value(string $sql, array $params = [], $default = null)
{
    $value = q($sql, $params)->fetchColumn();
    return $value === false ? $default : $value;
}

/** Insert an associative array into a table, returning the new id. */
function db_insert(string $table, array $data): int
{
    $columns = array_keys($data);
    $quoted = array_map(function ($c) { return '`' . str_replace('`', '', $c) . '`'; }, $columns);
    $holders = array_map(function ($c) { return ':' . $c; }, $columns);
    $sql = 'INSERT INTO `' . str_replace('`', '', $table) . '` (' . implode(', ', $quoted) . ') VALUES (' . implode(', ', $holders) . ')';
    q($sql, $data);
    return (int) db()->lastInsertId();
}

/** Update a table row by primary key. */
function db_update(string $table, array $data, string $whereColumn, $whereValue): int
{
    $sets = [];
    foreach (array_keys($data) as $c) { $sets[] = '`' . str_replace('`', '', $c) . '` = :' . $c; }
    $sql = 'UPDATE `' . str_replace('`', '', $table) . '` SET ' . implode(', ', $sets)
         . ' WHERE `' . str_replace('`', '', $whereColumn) . '` = :__where_id';
    $data['__where_id'] = $whereValue;
    return q($sql, $data)->rowCount();
}
