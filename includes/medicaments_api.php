<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connect.php';

require_login();

$today = new DateTimeImmutable('today');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'GET') {
    $searchValue = trim($_GET['search'] ?? '');
    $records = fetch_medicaments($connection, $searchValue);

    json_response([
        'ok' => true,
        'records' => medicament_collection_view_model($records, $today),
        'totals' => medicament_totals($records, $today),
        'query' => $searchValue,
    ]);
}

if ($requestMethod !== 'POST') {
    json_response([
        'ok' => false,
        'message' => 'Unsupported request method.',
    ], 405);
}

$action = trim($_POST['action'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? null;

if (!is_valid_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
    json_response([
        'ok' => false,
        'message' => 'Невалидна заявка. Обновете страницата и опитайте отново.',
    ], 419);
}

if ($action === 'update') {
    $recordId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $client = trim((string) ($_POST['client'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $medicaments = $_POST['medicaments'] ?? [];
    $newMedicament = trim((string) ($_POST['new_medicament'] ?? ''));
    $dateProduce = trim((string) ($_POST['date_produce'] ?? ''));
    $dateExpiri = trim((string) ($_POST['date_expiri'] ?? ''));
    $isAdmin = is_admin_role($_SESSION['user_role'] ?? null);

    if ($recordId === false || $recordId === null) {
        json_response([
            'ok' => false,
            'message' => 'Невалиден запис.',
        ], 422);
    }

    $record = fetch_medicament_by_id($connection, $recordId);

    if ($record === null) {
        json_response([
            'ok' => false,
            'message' => 'Записът не беше намерен.',
        ], 404);
    }

    if (!validate_text_length((string) ($record['client'] ?? ''), 150)
        || !validate_text_length((string) ($record['address'] ?? ''), 255)
        || !validate_text_length((string) ($record['medicament'] ?? ''), 255)
        || !validate_text_length((string) ($record['keywords'] ?? ''), 255)) {
        json_response([
            'ok' => false,
            'message' => 'Записът съдържа невалидни данни и не може да бъде променен.',
        ], 422);
    }

    $result = update_medicament_record(
        $connection,
        $recordId,
        $client !== '' ? $client : (string) ($record['client'] ?? ''),
        $address !== '' ? $address : (string) ($record['address'] ?? ''),
        $medicaments !== [] ? $medicaments : ($record['medicament'] ?? ''),
        $dateProduce !== '' ? $dateProduce : (string) ($record['date_produce'] ?? ''),
        $dateExpiri !== '' ? $dateExpiri : (string) ($record['date_expiri'] ?? ''),
        $newMedicament,
        $isAdmin
    );

    if (($result['ok'] ?? false) !== true) {
        json_response([
            'ok' => false,
            'message' => (string) (($result['errors'][0] ?? null) ?: 'Промяната не беше записана.'),
        ], 422);
    }

    $record = is_array($result['record'] ?? null) ? $result['record'] : fetch_medicament_by_id($connection, $recordId);

    if ($record === null) {
        json_response([
            'ok' => false,
            'message' => 'Записът не беше намерен.',
        ], 404);
    }

    json_response([
        'ok' => true,
        'message' => 'Промените са записани успешно.',
        'record' => medicament_view_model($record, $today),
    ]);
}

if ($action === 'delete') {
    $recordId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if ($recordId === false || $recordId === null) {
        json_response([
            'ok' => false,
            'message' => 'Невалиден запис.',
        ], 422);
    }

    $statement = mysqli_prepare($connection, 'DELETE FROM medicaments WHERE id = ? LIMIT 1');

    if ($statement === false) {
        json_response([
            'ok' => false,
            'message' => 'Изтриването не беше успешно.',
        ], 500);
    }

    mysqli_stmt_bind_param($statement, 'i', $recordId);
    $isDeleted = mysqli_stmt_execute($statement);
    $affectedRows = mysqli_stmt_affected_rows($statement);
    mysqli_stmt_close($statement);

    if (!$isDeleted || $affectedRows < 1) {
        json_response([
            'ok' => false,
            'message' => 'Записът не беше намерен или вече е изтрит.',
        ], 404);
    }

    json_response([
        'ok' => true,
        'message' => 'Записът е изтрит успешно.',
        'deleted_id' => $recordId,
    ]);
}

json_response([
    'ok' => false,
    'message' => 'Непознато действие.',
], 400);