<?php

function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function require_login(): void
{
    ensure_session_started();

    if (empty($_SESSION['username'])) {
        redirect_to('index.php');
    }
}

function sync_session_user(array $user): void
{
    ensure_session_started();

    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['username'] = trim((string) ($user['username'] ?? ''));
    $_SESSION['user_role'] = normalize_user_role($user['role'] ?? 'user');
}

function normalize_user_role(?string $role): string
{
    return strtolower(trim((string) $role)) === 'admin' ? 'admin' : 'user';
}

function is_admin_role(?string $role): bool
{
    return normalize_user_role($role) === 'admin';
}

function csrf_token(): string
{
    ensure_session_started();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function is_valid_csrf_token(?string $token): bool
{
    ensure_session_started();

    $sessionToken = $_SESSION['csrf_token'] ?? null;

    return is_string($token)
        && is_string($sessionToken)
        && $token !== ''
        && hash_equals($sessionToken, $token);
}

function validate_text_length(string $value, int $maxLength): bool
{
    return mb_strlen(trim($value)) <= $maxLength;
}

function medicament_options(): array
{
    return ['Аспирин', 'Бинт', 'Алертозан', 'Аналгин'];
}

function fetch_medicament_options(mysqli $connection): array
{
    $options = [];
    $result = mysqli_query($connection, 'SELECT name FROM medicament_options ORDER BY name ASC');

    if ($result === false) {
        return medicament_options();
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $option = trim((string) ($row['name'] ?? ''));

        if ($option === '' || in_array($option, $options, true)) {
            continue;
        }

        $options[] = $option;
    }

    mysqli_free_result($result);

    return $options !== [] ? $options : medicament_options();
}

function fetch_medicament_option_rows(mysqli $connection): array
{
    $rows = [];
    $result = mysqli_query($connection, 'SELECT id, name FROM medicament_options ORDER BY name ASC');

    if ($result === false) {
        foreach (medicament_options() as $index => $option) {
            $rows[] = [
                'id' => 0,
                'name' => $option,
            ];
        }

        return $rows;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $optionId = (int) ($row['id'] ?? 0);
        $optionName = trim((string) ($row['name'] ?? ''));

        if ($optionId < 1 || $optionName === '') {
            continue;
        }

        $rows[] = [
            'id' => $optionId,
            'name' => $optionName,
        ];
    }

    mysqli_free_result($result);

    return $rows;
}

function normalize_medicament_selection(array|string|null $value): array
{
    if (is_array($value)) {
        $rawValues = $value;
    } elseif (is_string($value)) {
        $rawValues = preg_split('/\s*,\s*/u', trim($value)) ?: [];
    } else {
        $rawValues = [];
    }

    $selected = [];

    foreach ($rawValues as $item) {
        $normalizedItem = trim((string) $item);

        if ($normalizedItem === '' || in_array($normalizedItem, $selected, true)) {
            continue;
        }

        $selected[] = $normalizedItem;
    }

    return $selected;
}

function normalize_new_medicament_entries(?string $value): array
{
    if (!is_string($value)) {
        return [];
    }

    $rawItems = preg_split('/\s*,\s*/u', trim($value)) ?: [];
    $normalizedItems = [];

    foreach ($rawItems as $rawItem) {
        $item = trim((string) $rawItem);

        if ($item === '' || in_array($item, $normalizedItems, true)) {
            continue;
        }

        $normalizedItems[] = $item;
    }

    return $normalizedItems;
}

function serialize_medicament_selection(array|string|null $value): string
{
    return implode(', ', normalize_medicament_selection($value));
}

function validate_medicament_selection(mysqli $connection, array|string|null $value): array
{
    $selectedMedicaments = normalize_medicament_selection($value);
    $availableOptions = fetch_medicament_options($connection);
    $allowedOptions = array_fill_keys($availableOptions, true);

    if ($selectedMedicaments === []) {
        return [
            'ok' => false,
            'value' => '',
            'errors' => ['Изберете поне един медикамент.'],
        ];
    }

    foreach ($selectedMedicaments as $selectedMedicament) {
        if (!isset($allowedOptions[$selectedMedicament])) {
            return [
                'ok' => false,
                'value' => '',
                'errors' => ['Избраният медикамент не е наличен.'],
            ];
        }
    }

    return [
        'ok' => true,
        'value' => implode(', ', $selectedMedicaments),
        'errors' => [],
    ];
}

function resolve_record_medicament_selection(
    mysqli $connection,
    array|string|null $selectedValue,
    ?string $newValue = null,
    bool $allowCreate = false
): array {
    $selectedItems = normalize_medicament_selection($selectedValue);
    $newItems = normalize_new_medicament_entries($newValue);

    if ($newItems !== [] && !$allowCreate) {
        return [
            'ok' => false,
            'value' => '',
            'selected_items' => $selectedItems,
            'errors' => ['Нямате права да добавяте нови медикаменти от този изглед.'],
        ];
    }

    $knownOptions = [];

    foreach (fetch_medicament_option_rows($connection) as $optionRow) {
        $optionName = trim((string) ($optionRow['name'] ?? ''));

        if ($optionName === '') {
            continue;
        }

        $knownOptions[mb_strtolower($optionName)] = $optionName;
    }

    foreach ($newItems as $newItem) {
        $normalizedKey = mb_strtolower($newItem);

        if (isset($knownOptions[$normalizedKey])) {
            if (!in_array($knownOptions[$normalizedKey], $selectedItems, true)) {
                $selectedItems[] = $knownOptions[$normalizedKey];
            }

            continue;
        }

        $createResult = create_medicament_option($connection, $newItem);

        if (($createResult['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'value' => '',
                'selected_items' => array_values(array_unique($selectedItems)),
                'errors' => (array) (($createResult['errors'] ?? ['Медикаментът не беше записан.'])),
            ];
        }

        $knownOptions[$normalizedKey] = $newItem;

        if (!in_array($newItem, $selectedItems, true)) {
            $selectedItems[] = $newItem;
        }
    }

    $selectedItems = array_values(array_unique($selectedItems));
    $validation = validate_medicament_selection($connection, $selectedItems);

    return [
        'ok' => (bool) ($validation['ok'] ?? false),
        'value' => (string) ($validation['value'] ?? ''),
        'selected_items' => $selectedItems,
        'errors' => (array) ($validation['errors'] ?? []),
    ];
}

function create_medicament_option(mysqli $connection, string $name): array
{
    $name = trim($name);

    if ($name === '') {
        return [
            'ok' => false,
            'errors' => ['Въведете име на медикамент.'],
        ];
    }

    if (!validate_text_length($name, 100)) {
        return [
            'ok' => false,
            'errors' => ['Името на медикамента е твърде дълго.'],
        ];
    }

    if (str_contains($name, ',')) {
        return [
            'ok' => false,
            'errors' => ['Името на медикамента не може да съдържа запетая.'],
        ];
    }

    $checkStatement = mysqli_prepare(
        $connection,
        'SELECT id FROM medicament_options WHERE LOWER(name) = LOWER(?) LIMIT 1'
    );

    if ($checkStatement === false) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше записан.'],
        ];
    }

    mysqli_stmt_bind_param($checkStatement, 's', $name);
    mysqli_stmt_execute($checkStatement);
    $result = mysqli_stmt_get_result($checkStatement);
    $existingOption = $result !== false ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($checkStatement);

    if ($existingOption !== null) {
        return [
            'ok' => false,
            'errors' => ['Този медикамент вече съществува.'],
        ];
    }

    $insertStatement = mysqli_prepare($connection, 'INSERT INTO medicament_options (name) VALUES (?)');

    if ($insertStatement === false) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше записан.'],
        ];
    }

    mysqli_stmt_bind_param($insertStatement, 's', $name);
    $isInserted = mysqli_stmt_execute($insertStatement);
    mysqli_stmt_close($insertStatement);

    if (!$isInserted) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше записан.'],
        ];
    }

    return [
        'ok' => true,
        'errors' => [],
    ];
}

function rename_medicament_option(mysqli $connection, int $optionId, string $newName): array
{
    $newName = trim($newName);

    if ($optionId < 1) {
        return [
            'ok' => false,
            'errors' => ['Невалиден медикамент.'],
        ];
    }

    if ($newName === '') {
        return [
            'ok' => false,
            'errors' => ['Въведете ново име на медикамент.'],
        ];
    }

    if (!validate_text_length($newName, 100)) {
        return [
            'ok' => false,
            'errors' => ['Името на медикамента е твърде дълго.'],
        ];
    }

    if (str_contains($newName, ',')) {
        return [
            'ok' => false,
            'errors' => ['Името на медикамента не може да съдържа запетая.'],
        ];
    }

    $optionStatement = mysqli_prepare($connection, 'SELECT id, name FROM medicament_options WHERE id = ? LIMIT 1');

    if ($optionStatement === false) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше обновен.'],
        ];
    }

    mysqli_stmt_bind_param($optionStatement, 'i', $optionId);
    mysqli_stmt_execute($optionStatement);
    $optionResult = mysqli_stmt_get_result($optionStatement);
    $optionRow = $optionResult !== false ? mysqli_fetch_assoc($optionResult) : null;
    mysqli_stmt_close($optionStatement);

    if ($optionRow === null) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше намерен.'],
        ];
    }

    $oldName = trim((string) ($optionRow['name'] ?? ''));

    if (mb_strtolower($oldName) === mb_strtolower($newName) && $oldName === $newName) {
        return [
            'ok' => true,
            'errors' => [],
            'updated_records' => 0,
            'name' => $newName,
        ];
    }

    $duplicateStatement = mysqli_prepare(
        $connection,
        'SELECT id FROM medicament_options WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1'
    );

    if ($duplicateStatement === false) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше обновен.'],
        ];
    }

    mysqli_stmt_bind_param($duplicateStatement, 'si', $newName, $optionId);
    mysqli_stmt_execute($duplicateStatement);
    $duplicateResult = mysqli_stmt_get_result($duplicateStatement);
    $hasDuplicate = $duplicateResult !== false && mysqli_fetch_assoc($duplicateResult) !== null;
    mysqli_stmt_close($duplicateStatement);

    if ($hasDuplicate) {
        return [
            'ok' => false,
            'errors' => ['Вече има медикамент с това име.'],
        ];
    }

    mysqli_begin_transaction($connection);

    try {
        $updateOptionStatement = mysqli_prepare($connection, 'UPDATE medicament_options SET name = ? WHERE id = ? LIMIT 1');

        if ($updateOptionStatement === false) {
            throw new RuntimeException('option_update_failed');
        }

        mysqli_stmt_bind_param($updateOptionStatement, 'si', $newName, $optionId);
        $isOptionUpdated = mysqli_stmt_execute($updateOptionStatement);
        mysqli_stmt_close($updateOptionStatement);

        if (!$isOptionUpdated) {
            throw new RuntimeException('option_update_failed');
        }

        $recordsStatement = mysqli_prepare(
            $connection,
            "SELECT id, medicament FROM medicaments WHERE FIND_IN_SET(?, REPLACE(medicament, ', ', ',')) > 0"
        );

        if ($recordsStatement === false) {
            throw new RuntimeException('records_lookup_failed');
        }

        mysqli_stmt_bind_param($recordsStatement, 's', $oldName);
        mysqli_stmt_execute($recordsStatement);
        $recordsResult = mysqli_stmt_get_result($recordsStatement);

        if ($recordsResult === false) {
            mysqli_stmt_close($recordsStatement);
            throw new RuntimeException('records_lookup_failed');
        }

        $recordsToUpdate = [];

        while ($recordRow = mysqli_fetch_assoc($recordsResult)) {
            $recordId = (int) ($recordRow['id'] ?? 0);
            $items = normalize_medicament_selection($recordRow['medicament'] ?? '');
            $didChange = false;

            foreach ($items as $index => $item) {
                if ($item !== $oldName) {
                    continue;
                }

                $items[$index] = $newName;
                $didChange = true;
            }

            if (!$didChange) {
                continue;
            }

            $recordsToUpdate[] = [
                'id' => $recordId,
                'medicament' => serialize_medicament_selection($items),
            ];
        }

        mysqli_stmt_close($recordsStatement);

        if ($recordsToUpdate !== []) {
            $updateRecordStatement = mysqli_prepare($connection, 'UPDATE medicaments SET medicament = ? WHERE id = ? LIMIT 1');

            if ($updateRecordStatement === false) {
                throw new RuntimeException('record_update_failed');
            }

            foreach ($recordsToUpdate as $recordToUpdate) {
                $recordMedicament = $recordToUpdate['medicament'];
                $recordId = (int) $recordToUpdate['id'];
                mysqli_stmt_bind_param($updateRecordStatement, 'si', $recordMedicament, $recordId);

                if (!mysqli_stmt_execute($updateRecordStatement)) {
                    mysqli_stmt_close($updateRecordStatement);
                    throw new RuntimeException('record_update_failed');
                }
            }

            mysqli_stmt_close($updateRecordStatement);
        }

        mysqli_commit($connection);

        return [
            'ok' => true,
            'errors' => [],
            'updated_records' => count($recordsToUpdate),
            'name' => $newName,
            'previous_name' => $oldName,
        ];
    } catch (Throwable $exception) {
        mysqli_rollback($connection);

        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше обновен.'],
        ];
    }
}

function delete_medicament_option(mysqli $connection, int $optionId): array
{
    if ($optionId < 1) {
        return [
            'ok' => false,
            'errors' => ['Невалиден медикамент.'],
        ];
    }

    $countResult = mysqli_query($connection, 'SELECT COUNT(*) AS total FROM medicament_options');

    if ($countResult === false) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше изтрит.'],
        ];
    }

    $countRow = mysqli_fetch_assoc($countResult) ?: ['total' => 0];
    mysqli_free_result($countResult);

    if ((int) ($countRow['total'] ?? 0) <= 1) {
        return [
            'ok' => false,
            'errors' => ['Трябва да остане поне един медикамент в списъка.'],
        ];
    }

    $optionStatement = mysqli_prepare($connection, 'SELECT name FROM medicament_options WHERE id = ? LIMIT 1');

    if ($optionStatement === false) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше изтрит.'],
        ];
    }

    mysqli_stmt_bind_param($optionStatement, 'i', $optionId);
    mysqli_stmt_execute($optionStatement);
    $optionResult = mysqli_stmt_get_result($optionStatement);
    $optionRow = $optionResult !== false ? mysqli_fetch_assoc($optionResult) : null;
    mysqli_stmt_close($optionStatement);

    if ($optionRow === null) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше намерен.'],
        ];
    }

    $optionName = trim((string) ($optionRow['name'] ?? ''));
    $usageStatement = mysqli_prepare(
        $connection,
        "SELECT id FROM medicaments WHERE FIND_IN_SET(?, REPLACE(medicament, ', ', ',')) > 0 LIMIT 1"
    );

    if ($usageStatement === false) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше изтрит.'],
        ];
    }

    mysqli_stmt_bind_param($usageStatement, 's', $optionName);
    mysqli_stmt_execute($usageStatement);
    $usageResult = mysqli_stmt_get_result($usageStatement);
    $isInUse = $usageResult !== false && mysqli_fetch_assoc($usageResult) !== null;
    mysqli_stmt_close($usageStatement);

    if ($isInUse) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не може да бъде изтрит, защото се използва в запис.'],
        ];
    }

    $statement = mysqli_prepare($connection, 'DELETE FROM medicament_options WHERE id = ? LIMIT 1');

    if ($statement === false) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше изтрит.'],
        ];
    }

    mysqli_stmt_bind_param($statement, 'i', $optionId);
    $isDeleted = mysqli_stmt_execute($statement);
    $affectedRows = mysqli_stmt_affected_rows($statement);
    mysqli_stmt_close($statement);

    if (!$isDeleted || $affectedRows < 1) {
        return [
            'ok' => false,
            'errors' => ['Медикаментът не беше намерен.'],
        ];
    }

    return [
        'ok' => true,
        'errors' => [],
    ];
}

function fetch_user_by_id(mysqli $connection, int $userId): ?array
{
    $statement = mysqli_prepare(
        $connection,
        'SELECT id, username, password, role FROM users WHERE id = ? LIMIT 1'
    );

    if ($statement === false) {
        return null;
    }

    mysqli_stmt_bind_param($statement, 'i', $userId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $user = $result !== false ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($statement);

    return $user ?: null;
}

function fetch_user_by_username(mysqli $connection, string $username): ?array
{
    $statement = mysqli_prepare(
        $connection,
        'SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1'
    );

    if ($statement === false) {
        return null;
    }

    mysqli_stmt_bind_param($statement, 's', $username);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $user = $result !== false ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($statement);

    return $user ?: null;
}

function verify_user_password(string $password, string $storedPassword): bool
{
    return password_verify($password, $storedPassword)
        || hash_equals($storedPassword, $password)
        || hash_equals($storedPassword, md5($password));
}

function refresh_user_password_hash(mysqli $connection, array $user, string $password): void
{
    $storedPassword = (string) ($user['password'] ?? '');
    $passwordInfo = password_get_info($storedPassword);

    if ($passwordInfo['algo'] !== 0 && !password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
        return;
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $userId = (int) ($user['id'] ?? 0);

    if ($newHash === false || $userId < 1) {
        return;
    }

    $statement = mysqli_prepare($connection, 'UPDATE users SET password = ? WHERE id = ?');

    if ($statement === false) {
        return;
    }

    mysqli_stmt_bind_param($statement, 'si', $newHash, $userId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

function attempt_login(mysqli $connection, string $username, string $password): bool
{
    $user = fetch_user_by_username($connection, $username);

    if ($user === null) {
        return false;
    }

    $storedPassword = (string) ($user['password'] ?? '');

    if (!verify_user_password($password, $storedPassword)) {
        return false;
    }

    session_regenerate_id(true);
    sync_session_user($user);
    refresh_user_password_hash($connection, $user, $password);

    return true;
}

function validate_username_and_password(string $username, string $password): array
{
    $errors = [];

    if (mb_strlen($username) < 5 || mb_strlen($username) > 50) {
        $errors[] = 'Потребителското име трябва да е между 5 и 50 символа.';
    }

    if (mb_strlen($password) < 5 || mb_strlen($password) > 50) {
        $errors[] = 'Паролата трябва да е между 5 и 50 символа.';
    }

    return $errors;
}

function create_user(mysqli $connection, string $username, string $password, string $role = 'user'): array
{
    $validationErrors = validate_username_and_password($username, $password);

    if ($validationErrors !== []) {
        return [
            'ok' => false,
            'errors' => $validationErrors,
        ];
    }

    if (fetch_user_by_username($connection, $username) !== null) {
        return [
            'ok' => false,
            'errors' => ['Потребителското име вече съществува.'],
        ];
    }

    $normalizedRole = normalize_user_role($role);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($passwordHash === false) {
        return [
            'ok' => false,
            'errors' => ['Регистрацията не беше успешна.'],
        ];
    }

    $nextUserId = next_counter_value($connection, 'users');

    if ($nextUserId === null) {
        return [
            'ok' => false,
            'errors' => ['Регистрацията не беше успешна.'],
        ];
    }

    $statement = mysqli_prepare($connection, 'INSERT INTO users (id, username, password, role) VALUES (?, ?, ?, ?)');

    if ($statement === false) {
        return [
            'ok' => false,
            'errors' => ['Регистрацията не беше успешна.'],
        ];
    }

    mysqli_stmt_bind_param($statement, 'isss', $nextUserId, $username, $passwordHash, $normalizedRole);
    $isInserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    if (!$isInserted) {
        return [
            'ok' => false,
            'errors' => ['Регистрацията не беше успешна.'],
        ];
    }

    return [
        'ok' => true,
        'errors' => [],
    ];
}

function next_counter_value(mysqli $connection, string $counterKey): ?int
{
    if (!mysqli_begin_transaction($connection)) {
        return null;
    }

    try {
        $statement = mysqli_prepare($connection, 'SELECT current_value FROM app_counters WHERE counter_key = ? LIMIT 1 FOR UPDATE');

        if ($statement === false) {
            mysqli_rollback($connection);
            return null;
        }

        mysqli_stmt_bind_param($statement, 's', $counterKey);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $row = $result !== false ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);

        if ($row === null) {
            mysqli_rollback($connection);
            return null;
        }

        $nextValue = ((int) ($row['current_value'] ?? 0)) + 1;
        $updateStatement = mysqli_prepare($connection, 'UPDATE app_counters SET current_value = ? WHERE counter_key = ?');

        if ($updateStatement === false) {
            mysqli_rollback($connection);
            return null;
        }

        mysqli_stmt_bind_param($updateStatement, 'is', $nextValue, $counterKey);
        $isUpdated = mysqli_stmt_execute($updateStatement);
        mysqli_stmt_close($updateStatement);

        if (!$isUpdated) {
            mysqli_rollback($connection);
            return null;
        }

        mysqli_commit($connection);

        return $nextValue;
    } catch (Throwable $exception) {
        mysqli_rollback($connection);
        return null;
    }
}

function create_medicament(
    mysqli $connection,
    string $client,
    string $address,
    array|string|null $medicament,
    string $dateProduce,
    string $dateExpiri,
    string $keywords
): array {
    $client = trim($client);
    $address = trim($address);
    $keywords = trim($keywords);
    $medicamentValidation = validate_medicament_selection($connection, $medicament);
    $medicament = (string) ($medicamentValidation['value'] ?? '');

    if ($client === '' || $address === '' || $medicament === '' || $dateProduce === '' || $dateExpiri === '' || $keywords === '') {
        return [
            'ok' => false,
            'errors' => (array) (($medicamentValidation['ok'] ?? true) ? ['Всички полета са задължителни.'] : ($medicamentValidation['errors'] ?? ['Всички полета са задължителни.'])),
        ];
    }

    if (!validate_text_length($client, 150) || !validate_text_length($address, 255) || !validate_text_length($medicament, 255) || !validate_text_length($keywords, 255)) {
        return [
            'ok' => false,
            'errors' => ['Едно или повече полета надвишават допустимата дължина.'],
        ];
    }

    $normalizedDateProduce = normalize_app_date($dateProduce);
    $normalizedDateExpiri = normalize_app_date($dateExpiri);

    if ($normalizedDateProduce === null || $normalizedDateExpiri === null) {
        return [
            'ok' => false,
            'errors' => ['Изберете валидни дати.'],
        ];
    }

    $nextMedicamentId = next_counter_value($connection, 'medicaments');

    if ($nextMedicamentId === null) {
        return [
            'ok' => false,
            'errors' => ['Записът не беше осъществен.'],
        ];
    }

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO medicaments (id, client, address, medicament, date_produce, date_expiri, keywords) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    if ($statement === false) {
        return [
            'ok' => false,
            'errors' => ['Записът не беше осъществен.'],
        ];
    }

    mysqli_stmt_bind_param(
        $statement,
        'issssss',
        $nextMedicamentId,
        $client,
        $address,
        $medicament,
        $normalizedDateProduce,
        $normalizedDateExpiri,
        $keywords
    );
    $isInserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    if (!$isInserted) {
        return [
            'ok' => false,
            'errors' => ['Записът не беше осъществен.'],
        ];
    }

    return [
        'ok' => true,
        'errors' => [],
    ];
}

function current_user(mysqli $connection): ?array
{
    ensure_session_started();

    $sessionUserId = $_SESSION['user_id'] ?? null;

    if (is_int($sessionUserId) || ctype_digit((string) $sessionUserId)) {
        $user = fetch_user_by_id($connection, (int) $sessionUserId);

        if ($user !== null) {
            sync_session_user($user);
            return $user;
        }
    }

    $sessionUsername = $_SESSION['username'] ?? '';

    if (!is_string($sessionUsername) || trim($sessionUsername) === '') {
        return null;
    }

    $user = fetch_user_by_username($connection, $sessionUsername);

    if ($user !== null) {
        sync_session_user($user);
    }

    return $user;
}

function require_admin(mysqli $connection): void
{
    require_login();

    $user = current_user($connection);

    if ($user === null || !is_admin_role($user['role'] ?? null)) {
        redirect_to('main_page.php');
    }
}

function fetch_all_users(mysqli $connection): array
{
    $users = [];
    $result = mysqli_query(
        $connection,
        "SELECT id, username, role FROM users ORDER BY CASE WHEN LOWER(role) = 'admin' THEN 0 ELSE 1 END, username ASC"
    );

    if ($result === false) {
        return [];
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $row['role'] = normalize_user_role($row['role'] ?? 'user');
        $users[] = $row;
    }

    return $users;
}

function admin_user_count(mysqli $connection): int
{
    $result = mysqli_query($connection, "SELECT COUNT(*) AS total FROM users WHERE LOWER(role) = 'admin'");

    if ($result === false) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);

    return (int) ($row['total'] ?? 0);
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalize_app_date(string $value): ?string
{
    $date = parse_app_date($value);

    return $date?->format('Y-m-d');
}

function parse_app_date(string $value): ?DateTimeImmutable
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $formats = ['d/m/Y', 'Y-m-d'];

    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false) {
            continue;
        }

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            continue;
        }

        return $date;
    }

    return null;
}

function is_expired(string $value, ?DateTimeImmutable $referenceDate = null): bool
{
    $expirationDate = parse_app_date($value);

    if ($expirationDate === null) {
        return false;
    }

    $today = $referenceDate ?? new DateTimeImmutable('today');

    return $expirationDate < $today;
}

function format_app_date(?string $value): string
{
    if ($value === null) {
        return '';
    }

    $parsedDate = parse_app_date($value);

    return $parsedDate?->format('d/m/Y') ?? trim($value);
}

function date_value_for_input(?string $value): string
{
    $parsedDate = $value === null ? null : parse_app_date($value);

    return $parsedDate?->format('Y-m-d') ?? '';
}

function ui_card_classes(): string
{
    return 'rounded-[28px] border border-white/10 bg-slate-900/70 shadow-2xl shadow-slate-950/40 backdrop-blur-xl';
}

function ui_input_classes(): string
{
    return 'mt-2 block w-full rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 outline-none transition focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/10';
}

function ui_label_classes(): string
{
    return 'text-sm font-semibold text-slate-200';
}

function ui_primary_button_classes(): string
{
    return 'inline-flex items-center justify-center rounded-full bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200 focus:outline-none focus:ring-4 focus:ring-cyan-300/30';
}

function ui_secondary_button_classes(): string
{
    return 'inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-300/40 hover:bg-white/10 focus:outline-none focus:ring-4 focus:ring-white/10';
}

function ui_danger_button_classes(): string
{
    return 'inline-flex items-center justify-center rounded-full border border-amber-300/30 bg-amber-400/10 px-5 py-3 text-sm font-semibold text-amber-100 transition hover:bg-amber-400/20 focus:outline-none focus:ring-4 focus:ring-amber-300/20';
}

function ui_alert_classes(string $type = 'info'): string
{
    $classes = [
        'success' => 'rounded-2xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-medium text-emerald-100',
        'error' => 'rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm font-medium text-rose-100',
        'info' => 'rounded-2xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-3 text-sm font-medium text-cyan-100',
    ];

    return $classes[$type] ?? $classes['info'];
}

function ui_status_badge_classes(bool $isExpired): string
{
    if ($isExpired) {
        return 'inline-flex items-center rounded-full border border-rose-400/20 bg-rose-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-rose-100';
    }

    return 'inline-flex items-center rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-100';
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mysqli_bind_dynamic_params(mysqli_stmt $statement, string $types, array $values): bool
{
    if ($types === '') {
        return true;
    }

    $parameters = [$statement, $types];

    foreach ($values as $index => $value) {
        $parameters[] = &$values[$index];
    }

    return call_user_func_array('mysqli_stmt_bind_param', $parameters);
}

function fetch_medicaments(mysqli $connection, ?string $searchValue = null): array
{
    $records = [];
    $trimmedSearch = trim((string) $searchValue);

    if ($trimmedSearch === '') {
        $result = mysqli_query($connection, 'SELECT id, client, address, medicament, date_produce, date_expiri, keywords FROM medicaments ORDER BY id ASC');

        if ($result === false) {
            return [];
        }
    } else {
        $searchTokens = preg_split('/\s+/u', $trimmedSearch) ?: [];
        $searchTokens = array_values(array_filter($searchTokens, static fn(string $token): bool => $token !== ''));

        if ($searchTokens === []) {
            return [];
        }

        $conditions = [];
        $parameterTypes = '';
        $parameterValues = [];

        foreach ($searchTokens as $token) {
            $conditions[] = '(CAST(id AS CHAR) LIKE ? OR client LIKE ? OR address LIKE ? OR medicament LIKE ? OR keywords LIKE ? OR DATE_FORMAT(date_produce, "%d/%m/%Y") LIKE ? OR DATE_FORMAT(date_expiri, "%d/%m/%Y") LIKE ? OR DATE_FORMAT(date_produce, "%Y-%m-%d") LIKE ? OR DATE_FORMAT(date_expiri, "%Y-%m-%d") LIKE ?)';

            $searchPattern = '%' . $token . '%';

            for ($index = 0; $index < 9; $index++) {
                $parameterTypes .= 's';
                $parameterValues[] = $searchPattern;
            }
        }

        $statement = mysqli_prepare(
            $connection,
            'SELECT id, client, address, medicament, date_produce, date_expiri, keywords
             FROM medicaments
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY id ASC'
        );

        if ($statement === false) {
            return [];
        }

        if (!mysqli_bind_dynamic_params($statement, $parameterTypes, $parameterValues)) {
            mysqli_stmt_close($statement);
            return [];
        }

        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        mysqli_stmt_close($statement);

        if ($result === false) {
            return [];
        }
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = $row;
    }

    return $records;
}

function medicament_view_model(array $row, ?DateTimeImmutable $referenceDate = null): array
{
    $isExpired = is_expired((string) ($row['date_expiri'] ?? ''), $referenceDate);
    $medicamentItems = normalize_medicament_selection($row['medicament'] ?? '');

    return [
        'id' => (int) ($row['id'] ?? 0),
        'client' => trim((string) ($row['client'] ?? '')),
        'address' => trim((string) ($row['address'] ?? '')),
        'medicament' => trim((string) ($row['medicament'] ?? '')),
        'medicament_items' => $medicamentItems,
        'date_produce' => trim((string) ($row['date_produce'] ?? '')),
        'date_expiri' => trim((string) ($row['date_expiri'] ?? '')),
        'formatted_date_produce' => format_app_date($row['date_produce'] ?? ''),
        'formatted_date_expiri' => format_app_date($row['date_expiri'] ?? ''),
        'input_date_produce' => date_value_for_input($row['date_produce'] ?? null),
        'input_date_expiri' => date_value_for_input($row['date_expiri'] ?? null),
        'is_expired' => $isExpired,
        'status_text' => $isExpired ? 'Не е в срок' : 'В срок',
        'search_text' => implode(' ', [
            (int) ($row['id'] ?? 0),
            trim((string) ($row['client'] ?? '')),
            trim((string) ($row['address'] ?? '')),
            trim((string) ($row['medicament'] ?? '')),
            trim((string) ($row['date_produce'] ?? '')),
            trim((string) ($row['date_expiri'] ?? '')),
            trim((string) ($row['keywords'] ?? '')),
        ]),
    ];
}

function medicament_collection_view_model(array $records, ?DateTimeImmutable $referenceDate = null): array
{
    return array_map(
        static fn(array $row): array => medicament_view_model($row, $referenceDate),
        $records
    );
}

function client_collection_view_model(array $records, ?DateTimeImmutable $referenceDate = null): array
{
    $clients = [];

    foreach ($records as $row) {
        $clientName = trim((string) ($row['client'] ?? ''));

        if ($clientName === '') {
            continue;
        }

        $clientKey = mb_strtolower($clientName);
        $address = trim((string) ($row['address'] ?? ''));
        $medicaments = normalize_medicament_selection($row['medicament'] ?? '');
        $isExpired = is_expired((string) ($row['date_expiri'] ?? ''), $referenceDate);

        if (!isset($clients[$clientKey])) {
            $clients[$clientKey] = [
                'client' => $clientName,
                'addresses' => [],
                'medicaments' => [],
                'record_count' => 0,
                'expired_count' => 0,
                'active_count' => 0,
            ];
        }

        $clients[$clientKey]['record_count']++;
        $clients[$clientKey][$isExpired ? 'expired_count' : 'active_count']++;

        if ($address !== '' && !in_array($address, $clients[$clientKey]['addresses'], true)) {
            $clients[$clientKey]['addresses'][] = $address;
        }

        foreach ($medicaments as $medicament) {
            if (!in_array($medicament, $clients[$clientKey]['medicaments'], true)) {
                $clients[$clientKey]['medicaments'][] = $medicament;
            }
        }
    }

    $clientList = array_values($clients);

    usort($clientList, static function (array $left, array $right): int {
        return strcasecmp($left['client'], $right['client']);
    });

    return $clientList;
}

function filter_client_collection(array $clients, string $searchValue): array
{
    $searchValue = trim($searchValue);

    if ($searchValue === '') {
        return $clients;
    }

    $needle = mb_strtolower($searchValue);

    return array_values(array_filter($clients, static function (array $client) use ($needle): bool {
        $haystacks = [
            trim((string) ($client['client'] ?? '')),
            implode(' ', $client['addresses'] ?? []),
            implode(' ', $client['medicaments'] ?? []),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && mb_strpos(mb_strtolower($haystack), $needle) !== false) {
                return true;
            }
        }

        return false;
    }));
}

function filter_records_by_client_name(array $records, string $clientName): array
{
    $clientName = trim($clientName);

    if ($clientName === '') {
        return [];
    }

    return array_values(array_filter($records, static function (array $record) use ($clientName): bool {
        return strcasecmp(trim((string) ($record['client'] ?? '')), $clientName) === 0;
    }));
}

function medicament_totals(array $records, ?DateTimeImmutable $referenceDate = null): array
{
    $expiredCount = 0;

    foreach ($records as $row) {
        $expirationValue = is_array($row) ? (string) ($row['date_expiri'] ?? '') : '';

        if (is_expired($expirationValue, $referenceDate)) {
            $expiredCount++;
        }
    }

    $totalCount = count($records);

    return [
        'total' => $totalCount,
        'expired' => $expiredCount,
        'active' => max(0, $totalCount - $expiredCount),
    ];
}

function fetch_medicament_by_id(mysqli $connection, int $recordId): ?array
{
    $statement = mysqli_prepare(
        $connection,
        'SELECT id, client, address, medicament, date_produce, date_expiri, keywords FROM medicaments WHERE id = ? LIMIT 1'
    );

    if ($statement === false) {
        return null;
    }

    mysqli_stmt_bind_param($statement, 'i', $recordId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $record = $result !== false ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($statement);

    return $record ?: null;
}

function update_medicament_record(
    mysqli $connection,
    int $recordId,
    string $client,
    string $address,
    array|string|null $medicament,
    string $dateProduce,
    string $dateExpiri,
    ?string $newMedicament = null,
    bool $allowCreateMedicament = false
): array {
    $client = trim($client);
    $address = trim($address);
    $medicamentValidation = resolve_record_medicament_selection($connection, $medicament, $newMedicament, $allowCreateMedicament);
    $medicament = (string) ($medicamentValidation['value'] ?? '');
    $normalizedDateProduce = normalize_app_date($dateProduce);
    $normalizedDateExpiri = normalize_app_date($dateExpiri);

    if ($recordId < 1) {
        return [
            'ok' => false,
            'errors' => ['Невалиден запис.'],
        ];
    }

    if ($client === '' || $address === '' || $medicament === '') {
        return [
            'ok' => false,
            'errors' => (array) (($medicamentValidation['ok'] ?? true) ? ['Клиент, адрес и медикамент са задължителни.'] : ($medicamentValidation['errors'] ?? ['Клиент, адрес и медикамент са задължителни.'])),
        ];
    }

    if ($normalizedDateProduce === null || $normalizedDateExpiri === null) {
        return [
            'ok' => false,
            'errors' => ['Изберете валидни дати.'],
        ];
    }

    if (!validate_text_length($client, 150)
        || !validate_text_length($address, 255)
        || !validate_text_length($medicament, 255)) {
        return [
            'ok' => false,
            'errors' => ['Едно или повече полета надвишават допустимата дължина.'],
        ];
    }

    $statement = mysqli_prepare(
        $connection,
        'UPDATE medicaments SET client = ?, address = ?, medicament = ?, date_produce = ?, date_expiri = ? WHERE id = ? LIMIT 1'
    );

    if ($statement === false) {
        return [
            'ok' => false,
            'errors' => ['Промените не бяха записани.'],
        ];
    }

    mysqli_stmt_bind_param($statement, 'sssssi', $client, $address, $medicament, $normalizedDateProduce, $normalizedDateExpiri, $recordId);
    $isUpdated = mysqli_stmt_execute($statement);
    $affectedRows = mysqli_stmt_affected_rows($statement);
    mysqli_stmt_close($statement);

    if (!$isUpdated || $affectedRows < 1) {
        $record = fetch_medicament_by_id($connection, $recordId);

        if ($record !== null
            && trim((string) ($record['client'] ?? '')) === $client
            && trim((string) ($record['address'] ?? '')) === $address
            && trim((string) ($record['medicament'] ?? '')) === $medicament
            && trim((string) ($record['date_produce'] ?? '')) === $normalizedDateProduce
            && trim((string) ($record['date_expiri'] ?? '')) === $normalizedDateExpiri) {
            return [
                'ok' => true,
                'errors' => [],
                'record' => $record,
            ];
        }

        return [
            'ok' => false,
            'errors' => ['Промените не бяха записани.'],
        ];
    }

    return [
        'ok' => true,
        'errors' => [],
        'record' => fetch_medicament_by_id($connection, $recordId),
    ];
}

function delete_medicament_record(mysqli $connection, int $recordId): array
{
    if ($recordId < 1) {
        return [
            'ok' => false,
            'errors' => ['Невалиден запис.'],
        ];
    }

    $statement = mysqli_prepare($connection, 'DELETE FROM medicaments WHERE id = ? LIMIT 1');

    if ($statement === false) {
        return [
            'ok' => false,
            'errors' => ['Записът не беше изтрит.'],
        ];
    }

    mysqli_stmt_bind_param($statement, 'i', $recordId);
    $isDeleted = mysqli_stmt_execute($statement);
    $affectedRows = mysqli_stmt_affected_rows($statement);
    mysqli_stmt_close($statement);

    if (!$isDeleted || $affectedRows < 1) {
        return [
            'ok' => false,
            'errors' => ['Записът не беше намерен или вече е изтрит.'],
        ];
    }

    return [
        'ok' => true,
        'errors' => [],
    ];
}

function update_medicament_details(
    mysqli $connection,
    int $recordId,
    string $client,
    string $address,
    array|string|null $medicament,
    ?string $newMedicament = null,
    bool $allowCreateMedicament = false
): array
{
    $record = fetch_medicament_by_id($connection, $recordId);

    if ($record === null) {
        return [
            'ok' => false,
            'errors' => ['Записът не беше намерен.'],
        ];
    }

    return update_medicament_record(
        $connection,
        $recordId,
        $client,
        $address,
        $medicament,
        (string) ($record['date_produce'] ?? ''),
        (string) ($record['date_expiri'] ?? ''),
        $newMedicament,
        $allowCreateMedicament
    );
}