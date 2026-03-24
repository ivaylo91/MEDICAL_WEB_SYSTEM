<?php

$connection = new mysqli('localhost', 'root', '', 'websystem1');

if ($connection->connect_error) {
    exit('No database connection.');
}

$connection->set_charset('utf8mb4');

ensure_users_role_schema($connection);
ensure_app_counters_schema($connection);
ensure_medicament_options_schema($connection);
ensure_medicaments_schema($connection);

function ensure_users_role_schema(mysqli $connection): void
{
    $columnResult = mysqli_query($connection, "SHOW COLUMNS FROM users LIKE 'role'");

    if ($columnResult === false) {
        return;
    }

    $hasRoleColumn = mysqli_num_rows($columnResult) > 0;
    mysqli_free_result($columnResult);

    if (!$hasRoleColumn) {
        mysqli_query(
            $connection,
            "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER password"
        );
    }

    mysqli_query($connection, "UPDATE users SET role = 'user' WHERE role IS NULL OR TRIM(role) = ''");

    $adminResult = mysqli_query($connection, "SELECT id FROM users WHERE LOWER(role) = 'admin' LIMIT 1");

    if ($adminResult === false) {
        return;
    }

    $hasAdmin = mysqli_num_rows($adminResult) > 0;
    mysqli_free_result($adminResult);

    if (!$hasAdmin) {
        mysqli_query($connection, "UPDATE users SET role = 'admin' ORDER BY id ASC LIMIT 1");
    }
}

function ensure_app_counters_schema(mysqli $connection): void
{
    mysqli_query(
        $connection,
        'CREATE TABLE IF NOT EXISTS app_counters (
            counter_key VARCHAR(50) NOT NULL,
            current_value INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (counter_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    seed_app_counter($connection, 'users', 'users');
    seed_app_counter($connection, 'medicaments', 'medicaments');
}

function seed_app_counter(mysqli $connection, string $counterKey, string $tableName): void
{
    $counterKeyEscaped = mysqli_real_escape_string($connection, $counterKey);
    $tableNameEscaped = str_replace('`', '``', $tableName);
    $existingResult = mysqli_query(
        $connection,
        "SELECT current_value FROM app_counters WHERE counter_key = '" . $counterKeyEscaped . "' LIMIT 1"
    );

    if ($existingResult !== false && mysqli_num_rows($existingResult) > 0) {
        mysqli_free_result($existingResult);
        return;
    }

    if ($existingResult !== false) {
        mysqli_free_result($existingResult);
    }

    $maxResult = mysqli_query($connection, "SELECT COALESCE(MAX(id), 0) AS max_id FROM `" . $tableNameEscaped . "`");

    if ($maxResult === false) {
        return;
    }

    $row = mysqli_fetch_assoc($maxResult);
    mysqli_free_result($maxResult);
    $maxId = (int) ($row['max_id'] ?? 0);
    $statement = mysqli_prepare($connection, 'INSERT INTO app_counters (counter_key, current_value) VALUES (?, ?)');

    if ($statement === false) {
        return;
    }

    mysqli_stmt_bind_param($statement, 'si', $counterKey, $maxId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

function ensure_medicaments_schema(mysqli $connection): void
{
    $columnResult = mysqli_query($connection, "SHOW COLUMNS FROM medicaments LIKE 'medicament'");

    if ($columnResult === false) {
        return;
    }

    $column = mysqli_fetch_assoc($columnResult) ?: null;
    mysqli_free_result($columnResult);

    if ($column === null) {
        return;
    }

    $columnType = strtolower((string) ($column['Type'] ?? ''));

    if ($columnType !== 'varchar(255)') {
        mysqli_query($connection, 'ALTER TABLE medicaments MODIFY COLUMN medicament VARCHAR(255) NOT NULL');
    }
}

function ensure_medicament_options_schema(mysqli $connection): void
{
    mysqli_query(
        $connection,
        'CREATE TABLE IF NOT EXISTS medicament_options (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_medicament_options_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $countResult = mysqli_query($connection, 'SELECT COUNT(*) AS total FROM medicament_options');

    if ($countResult === false) {
        return;
    }

    $countRow = mysqli_fetch_assoc($countResult) ?: ['total' => 0];
    mysqli_free_result($countResult);

    if ((int) ($countRow['total'] ?? 0) > 0) {
        return;
    }

    $seedOptions = medicament_options();
    $recordsResult = mysqli_query($connection, 'SELECT medicament FROM medicaments');

    if ($recordsResult !== false) {
        while ($row = mysqli_fetch_assoc($recordsResult)) {
            foreach (normalize_medicament_selection($row['medicament'] ?? '') as $recordOption) {
                if (!in_array($recordOption, $seedOptions, true)) {
                    $seedOptions[] = $recordOption;
                }
            }
        }

        mysqli_free_result($recordsResult);
    }

    $statement = mysqli_prepare($connection, 'INSERT IGNORE INTO medicament_options (name) VALUES (?)');

    if ($statement === false) {
        return;
    }

    foreach ($seedOptions as $seedOption) {
        mysqli_stmt_bind_param($statement, 's', $seedOption);
        mysqli_stmt_execute($statement);
    }

    mysqli_stmt_close($statement);
}