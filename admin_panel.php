<?php
$pageTitle = 'Админ панел';

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/connect.php';

require_admin($connection);

$currentUser = current_user($connection);
$message = '';
$messageType = 'info';
$formValues = [
    'username' => '',
    'role' => 'user',
];
$medicamentOptionName = '';
$medicamentOptionEdits = [];
$medicamentOptions = fetch_medicament_options($connection);
$medicamentOptionRows = fetch_medicament_option_rows($connection);
$selectedRecordId = filter_input(INPUT_GET, 'edit_record', FILTER_VALIDATE_INT);
$selectedClient = trim((string) ($_GET['client'] ?? ''));
$clientSearchValue = trim((string) ($_GET['client_search'] ?? ''));
$medicamentFormValues = [
    'id' => $selectedRecordId !== false && $selectedRecordId !== null ? (int) $selectedRecordId : 0,
    'client' => '',
    'address' => '',
    'medicaments' => [],
    'new_medicament' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_valid_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
        $message = 'Невалидна заявка. Обновете страницата и опитайте отново.';
        $messageType = 'error';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'add_user') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role = normalize_user_role($_POST['role'] ?? 'user');

            $formValues['username'] = $username;
            $formValues['role'] = $role;

            $result = create_user($connection, $username, $password, $role);

            if (($result['ok'] ?? false) === true) {
                $message = 'Потребителят е създаден успешно.';
                $messageType = 'success';
                $formValues = [
                    'username' => '',
                    'role' => 'user',
                ];
            } else {
                $message = (string) (($result['errors'][0] ?? null) ?: 'Потребителят не беше създаден.');
                $messageType = 'error';

                if ($message === 'Регистрацията не беше успешна.') {
                    $message = 'Потребителят не беше създаден.';
                }
            }
        } elseif ($action === 'delete_user') {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

            if ($userId === false || $userId === null) {
                $message = 'Невалиден потребител.';
                $messageType = 'error';
            } else {
                $userToDelete = fetch_user_by_id($connection, $userId);

                if ($userToDelete === null) {
                    $message = 'Потребителят не беше намерен.';
                    $messageType = 'error';
                } elseif ((int) ($currentUser['id'] ?? 0) === (int) $userId) {
                    $message = 'Не можете да изтриете собствения си профил.';
                    $messageType = 'error';
                } elseif (is_admin_role($userToDelete['role'] ?? null) && admin_user_count($connection) <= 1) {
                    $message = 'Последният администратор не може да бъде изтрит.';
                    $messageType = 'error';
                } else {
                    $statement = mysqli_prepare($connection, 'DELETE FROM users WHERE id = ? LIMIT 1');

                    if ($statement === false) {
                        $message = 'Потребителят не беше изтрит.';
                        $messageType = 'error';
                    } else {
                        mysqli_stmt_bind_param($statement, 'i', $userId);
                        $isDeleted = mysqli_stmt_execute($statement);
                        $affectedRows = mysqli_stmt_affected_rows($statement);
                        mysqli_stmt_close($statement);

                        if ($isDeleted && $affectedRows > 0) {
                            $message = 'Потребителят е изтрит успешно.';
                            $messageType = 'success';
                        } else {
                            $message = 'Потребителят не беше изтрит.';
                            $messageType = 'error';
                        }
                    }
                }
            }
        } elseif ($action === 'add_medicament_option') {
            $medicamentOptionName = trim((string) ($_POST['medicament_name'] ?? ''));
            $result = create_medicament_option($connection, $medicamentOptionName);

            if (($result['ok'] ?? false) === true) {
                $message = 'Медикаментът е добавен успешно.';
                $messageType = 'success';
                $medicamentOptionName = '';
            } else {
                $message = (string) (($result['errors'][0] ?? null) ?: 'Медикаментът не беше записан.');
                $messageType = 'error';
            }
        } elseif ($action === 'rename_medicament_option') {
            $optionId = filter_input(INPUT_POST, 'option_id', FILTER_VALIDATE_INT);
            $newName = trim((string) ($_POST['new_name'] ?? ''));

            if ($optionId !== false && $optionId !== null) {
                $medicamentOptionEdits[(int) $optionId] = $newName;
            }

            $result = rename_medicament_option($connection, $optionId === false || $optionId === null ? 0 : (int) $optionId, $newName);

            if (($result['ok'] ?? false) === true) {
                $updatedRecords = (int) ($result['updated_records'] ?? 0);
                $message = $updatedRecords > 0
                    ? 'Медикаментът е преименуван успешно и е обновен в ' . $updatedRecords . ' запис(а).'
                    : 'Медикаментът е преименуван успешно.';
                $messageType = 'success';
                $medicamentOptionEdits = [];
            } else {
                $message = (string) (($result['errors'][0] ?? null) ?: 'Медикаментът не беше обновен.');
                $messageType = 'error';
            }
        } elseif ($action === 'delete_medicament_option') {
            $optionId = filter_input(INPUT_POST, 'option_id', FILTER_VALIDATE_INT);
            $result = delete_medicament_option($connection, $optionId === false || $optionId === null ? 0 : (int) $optionId);

            if (($result['ok'] ?? false) === true) {
                $message = 'Медикаментът е изтрит успешно.';
                $messageType = 'success';
            } else {
                $message = (string) (($result['errors'][0] ?? null) ?: 'Медикаментът не беше изтрит.');
                $messageType = 'error';
            }
        } elseif ($action === 'update_medicament') {
            $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
            $client = trim((string) ($_POST['client'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $medicament = $_POST['medicaments'] ?? [];
            $newMedicament = trim((string) ($_POST['new_medicament'] ?? ''));

            $selectedRecordId = $recordId;
            $medicamentFormValues = [
                'id' => $recordId === false || $recordId === null ? 0 : (int) $recordId,
                'client' => $client,
                'address' => $address,
                'medicaments' => normalize_medicament_selection($medicament),
                'new_medicament' => $newMedicament,
            ];

            $result = update_medicament_details(
                $connection,
                $recordId === false || $recordId === null ? 0 : (int) $recordId,
                $client,
                $address,
                $medicament,
                $newMedicament,
                true
            );

            if (($result['ok'] ?? false) === true) {
                $message = 'Записът е обновен успешно.';
                $messageType = 'success';

                if (is_array($result['record'] ?? null)) {
                    $record = $result['record'];
                    $medicamentFormValues = [
                        'id' => (int) ($record['id'] ?? 0),
                        'client' => trim((string) ($record['client'] ?? '')),
                        'address' => trim((string) ($record['address'] ?? '')),
                        'medicaments' => normalize_medicament_selection($record['medicament'] ?? ''),
                        'new_medicament' => '',
                    ];
                }
            } else {
                $message = (string) (($result['errors'][0] ?? null) ?: 'Промените не бяха записани.');
                $messageType = 'error';
            }
        }
    }
}

$medicamentOptions = fetch_medicament_options($connection);
$medicamentOptionRows = fetch_medicament_option_rows($connection);

$selectedRecord = null;

if ($selectedRecordId !== false && $selectedRecordId !== null && (int) $selectedRecordId > 0) {
    $selectedRecord = fetch_medicament_by_id($connection, (int) $selectedRecordId);

    if ($selectedRecord !== null && $medicamentFormValues['client'] === '') {
        $medicamentFormValues = [
            'id' => (int) ($selectedRecord['id'] ?? 0),
            'client' => trim((string) ($selectedRecord['client'] ?? '')),
            'address' => trim((string) ($selectedRecord['address'] ?? '')),
            'medicaments' => normalize_medicament_selection($selectedRecord['medicament'] ?? ''),
            'new_medicament' => '',
        ];
    }
}

$users = fetch_all_users($connection);
$allMedicamentRecords = fetch_medicaments($connection);
$medicamentRecords = $allMedicamentRecords;
$clientPayloads = client_collection_view_model($allMedicamentRecords, new DateTimeImmutable('today'));
$filteredClientPayloads = filter_client_collection($clientPayloads, $clientSearchValue);

if ($selectedClient !== '') {
    $medicamentRecords = filter_records_by_client_name($allMedicamentRecords, $selectedClient);
}

$adminCount = 0;

foreach ($users as $user) {
    if (is_admin_role($user['role'] ?? null)) {
        $adminCount++;
    }
}

$totalUsers = count($users);
$standardUsers = max(0, $totalUsers - $adminCount);

include 'includes/header.php';
?>
<div class="space-y-6 py-4 sm:py-6">
    <section data-reveal class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.35em] text-orange-300/75">Администриране</p>
                <h1 class="mt-2 text-3xl font-bold text-white">Управление на потребители</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Добавяйте нови профили и премахвайте стари. Само администраторите имат достъп до тази страница.</p>
            </div>
            <div class="flex flex-wrap gap-3 text-sm text-slate-200">
                <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2">Общо: <?php echo $totalUsers; ?></span>
                <span class="rounded-full border border-orange-300/25 bg-orange-300/10 px-4 py-2 text-orange-100">Админи: <?php echo $adminCount; ?></span>
                <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2">Потребители: <?php echo $standardUsers; ?></span>
            </div>
        </div>

        <?php if ($message !== '') { ?>
            <div data-alert class="mt-6 <?php echo ui_alert_classes($messageType); ?>"><?php echo h($message); ?></div>
        <?php } ?>
    </section>

    <section data-reveal class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
        <div class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300/75">Нов профил</p>
            <h2 class="mt-2 text-2xl font-bold text-white">Добавяне на потребител</h2>
            <form action="admin_panel.php" method="post" class="mt-6 space-y-5">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="add_user">
                <div>
                    <label for="admin-username" class="<?php echo ui_label_classes(); ?>">Потребителско име</label>
                    <input type="text" id="admin-username" name="username" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($formValues['username']); ?>" placeholder="Поне 5 символа" autocomplete="username">
                </div>
                <div>
                    <label for="admin-password" class="<?php echo ui_label_classes(); ?>">Парола</label>
                    <div class="relative">
                        <input type="password" id="admin-password" name="password" class="<?php echo ui_input_classes(); ?> pr-20" placeholder="Поне 5 символа" autocomplete="new-password">
                        <button type="button" data-password-toggle="admin-password" aria-pressed="false" class="absolute inset-y-0 right-3 my-auto inline-flex h-10 items-center rounded-full px-3 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200 transition hover:bg-white/5">Покажи</button>
                    </div>
                </div>
                <div>
                    <label for="admin-role" class="<?php echo ui_label_classes(); ?>">Роля</label>
                    <select id="admin-role" name="role" class="<?php echo ui_input_classes(); ?>">
                        <option value="user"<?php echo $formValues['role'] === 'user' ? ' selected' : ''; ?>>Потребител</option>
                        <option value="admin"<?php echo $formValues['role'] === 'admin' ? ' selected' : ''; ?>>Администратор</option>
                    </select>
                </div>
                <button type="submit" class="<?php echo ui_primary_button_classes(); ?> w-full sm:w-auto">Създай профил</button>
            </form>
        </div>

        <div class="<?php echo ui_card_classes(); ?> overflow-hidden shadow-halo">
            <div class="border-b border-white/10 px-6 py-5">
                <h2 class="text-xl font-semibold text-white">Налични потребители</h2>
                <p class="mt-1 text-sm text-slate-300">Изтриването е забранено за текущия профил и за последния останал администратор.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-left text-sm text-slate-200">
                    <thead class="bg-white/5 text-xs uppercase tracking-[0.2em] text-slate-400">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Потребител</th>
                        <th class="px-6 py-4 font-semibold">Роля</th>
                        <th class="px-6 py-4 font-semibold">Статус</th>
                        <th class="px-6 py-4 font-semibold">Действие</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                    <?php foreach ($users as $user) {
                        $isCurrentUser = (int) ($currentUser['id'] ?? 0) === (int) $user['id'];
                        $isAdminUser = is_admin_role($user['role'] ?? null);
                        $canDelete = !$isCurrentUser && !($isAdminUser && $adminCount <= 1);
                    ?>
                        <tr class="transition hover:bg-white/5">
                            <td class="px-6 py-4 font-semibold text-white"><?php echo h($user['username']); ?></td>
                            <td class="px-6 py-4">
                                <span class="<?php echo $isAdminUser ? 'inline-flex items-center rounded-full border border-orange-300/25 bg-orange-300/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-orange-100' : 'inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200'; ?>">
                                    <?php echo $isAdminUser ? 'Админ' : 'Потребител'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-300"><?php echo $isCurrentUser ? 'Текущ профил' : 'Активен'; ?></td>
                            <td class="px-6 py-4">
                                <?php if ($canDelete) { ?>
                                    <form action="admin_panel.php" method="post" onsubmit="return window.confirm('Сигурни ли сте, че искате да изтриете този потребител?');">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                        <button type="submit" class="<?php echo ui_danger_button_classes(); ?>">Изтрий</button>
                                    </form>
                                <?php } else { ?>
                                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Недостъпно</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section data-reveal class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
        <div class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300/75">Каталог</p>
            <h2 class="mt-2 text-2xl font-bold text-white">Добавяне на медикамент</h2>
            <p class="mt-2 text-sm leading-6 text-slate-300">Новите медикаменти се записват в базата данни и веднага стават налични във всички форми за добавяне и редакция.</p>

            <form action="admin_panel.php" method="post" class="mt-6 space-y-5">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="add_medicament_option">
                <div>
                    <label for="medicament-name" class="<?php echo ui_label_classes(); ?>">Име на медикамент</label>
                    <input type="text" id="medicament-name" name="medicament_name" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($medicamentOptionName); ?>" placeholder="Напр. Ибупрофен">
                </div>
                <button type="submit" class="<?php echo ui_primary_button_classes(); ?> w-full sm:w-auto">Запази медикамент</button>
            </form>
        </div>

        <div class="<?php echo ui_card_classes(); ?> overflow-hidden shadow-halo">
            <div class="border-b border-white/10 px-6 py-5">
                <h2 class="text-xl font-semibold text-white">Налични медикаменти</h2>
                <p class="mt-1 text-sm text-slate-300">Този списък определя опциите в страниците за добавяне и редакция на записи.</p>
            </div>

            <?php if ($medicamentOptions === []) { ?>
                <div class="px-6 py-10 text-center text-slate-300">Все още няма налични медикаменти.</div>
            <?php } else { ?>
                <div class="divide-y divide-white/5">
                    <?php foreach ($medicamentOptionRows as $optionIndex => $option) { ?>
                        <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-white"><?php echo h($option['name']); ?></p>
                                <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">Опция #<?php echo $optionIndex + 1; ?></p>
                            </div>
                            <?php if ((int) $option['id'] > 0) { ?>
                                <div class="flex flex-col gap-3 sm:items-end">
                                    <form action="admin_panel.php" method="post" class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="rename_medicament_option">
                                        <input type="hidden" name="option_id" value="<?php echo (int) $option['id']; ?>">
                                        <input type="text" name="new_name" class="<?php echo ui_input_classes(); ?> sm:min-w-[220px]" value="<?php echo h($medicamentOptionEdits[(int) $option['id']] ?? $option['name']); ?>" aria-label="Ново име за медикамент <?php echo h($option['name']); ?>">
                                        <button type="submit" class="<?php echo ui_secondary_button_classes(); ?>">Преименувай</button>
                                    </form>
                                    <form action="admin_panel.php" method="post" onsubmit="return window.confirm('Сигурни ли сте, че искате да премахнете този медикамент от списъка?');">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="action" value="delete_medicament_option">
                                        <input type="hidden" name="option_id" value="<?php echo (int) $option['id']; ?>">
                                        <button type="submit" class="<?php echo ui_danger_button_classes(); ?>">Премахни</button>
                                    </form>
                                </div>
                            <?php } else { ?>
                                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Недостъпно</span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </section>

    <section id="clients" data-reveal class="<?php echo ui_card_classes(); ?> overflow-hidden">
        <div class="border-b border-white/10 px-6 py-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-white">Преглед по клиенти</h2>
                    <p class="mt-1 text-sm text-slate-300">Администраторски изглед с филтър към записите за конкретен клиент.</p>
                </div>
                <?php if ($selectedClient !== '') { ?>
                    <a href="admin_panel.php#clients" class="<?php echo ui_secondary_button_classes(); ?>">Покажи всички клиенти</a>
                <?php } ?>
            </div>
        </div>

        <div class="border-b border-white/10 px-6 py-4">
            <form action="admin_panel.php#clients" method="get" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                <?php if ($selectedClient !== '') { ?>
                    <input type="hidden" name="client" value="<?php echo h($selectedClient); ?>">
                <?php } ?>
                <input type="text" name="client_search" value="<?php echo h($clientSearchValue); ?>" class="<?php echo ui_input_classes(); ?> mt-0" placeholder="Търсене по клиент, адрес или медикамент">
                <button type="submit" class="<?php echo ui_secondary_button_classes(); ?>">Филтрирай</button>
                <?php if ($clientSearchValue !== '') { ?>
                    <a href="admin_panel.php#clients" class="<?php echo ui_secondary_button_classes(); ?>">Изчисти</a>
                <?php } ?>
            </form>
        </div>

        <?php if ($filteredClientPayloads === []) { ?>
            <div class="px-6 py-10 text-center text-slate-300">Все още няма клиенти за показване.</div>
        <?php } else { ?>
            <div class="grid gap-4 p-4 xl:grid-cols-2">
                <?php foreach ($filteredClientPayloads as $client) {
                    $isSelectedClient = $selectedClient !== '' && strcasecmp($selectedClient, $client['client']) === 0;
                ?>
                    <article class="rounded-3xl border <?php echo $isSelectedClient ? 'border-cyan-300/30 bg-cyan-300/10' : 'border-white/10 bg-white/5'; ?> p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Клиент</p>
                                <h3 class="mt-2 text-xl font-semibold text-white"><?php echo h($client['client']); ?></h3>
                            </div>
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-200"><?php echo (int) $client['record_count']; ?> записа</span>
                        </div>
                        <div class="mt-4 space-y-3 text-sm text-slate-300">
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Адреси</p>
                                <p class="mt-1"><?php echo h(implode(' • ', $client['addresses'])); ?></p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Медикаменти</p>
                                <p class="mt-1"><?php echo h(implode(', ', $client['medicaments'])); ?></p>
                            </div>
                        </div>
                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-200">Активни: <?php echo (int) $client['active_count']; ?></span>
                            <span class="rounded-full border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-rose-100">Изтекли: <?php echo (int) $client['expired_count']; ?></span>
                            <a href="admin_panel.php?client=<?php echo urlencode($client['client']); ?>#medicaments" class="<?php echo ui_secondary_button_classes(); ?>">Виж записите</a>
                            <a href="client_details.php?client=<?php echo urlencode($client['client']); ?>" class="<?php echo ui_secondary_button_classes(); ?>">Детайли</a>
                        </div>
                    </article>
                <?php } ?>
            </div>
        <?php } ?>
    </section>

    <section id="medicaments" data-reveal class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="<?php echo ui_card_classes(); ?> overflow-hidden shadow-halo">
            <div class="border-b border-white/10 px-6 py-5">
                <h2 class="text-xl font-semibold text-white">Записи с медикаменти</h2>
                <p class="mt-1 text-sm text-slate-300"><?php echo $selectedClient !== '' ? 'Показани са само записите за избрания клиент. Администраторът може да променя клиента, адреса и един или повече медикаменти директно от този панел.' : 'Администраторът може да променя клиента, адреса и един или повече медикаменти директно от този панел.'; ?></p>
            </div>

            <?php if ($medicamentRecords === []) { ?>
                <div class="px-6 py-10 text-center text-slate-300">Все още няма въведени записи.</div>
            <?php } else { ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10 text-left text-sm text-slate-200">
                        <thead class="bg-white/5 text-xs uppercase tracking-[0.2em] text-slate-400">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Клиент</th>
                            <th class="px-6 py-4 font-semibold">Адрес</th>
                            <th class="px-6 py-4 font-semibold">Медикаменти</th>
                            <th class="px-6 py-4 font-semibold">Дати</th>
                            <th class="px-6 py-4 font-semibold">Действие</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                        <?php foreach ($medicamentRecords as $record) {
                            $isSelectedRecord = (int) ($selectedRecordId ?: 0) === (int) $record['id'];
                        ?>
                            <tr class="transition hover:bg-white/5 <?php echo $isSelectedRecord ? 'bg-white/5' : ''; ?>">
                                <td class="px-6 py-4 font-semibold text-white"><?php echo h($record['client']); ?></td>
                                <td class="px-6 py-4 text-slate-300"><?php echo h($record['address']); ?></td>
                                <td class="px-6 py-4 text-slate-200"><?php echo h($record['medicament']); ?></td>
                                <td class="px-6 py-4 text-slate-300">
                                    <div><?php echo h(format_app_date($record['date_produce'] ?? '')); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo h(format_app_date($record['date_expiri'] ?? '')); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="admin_panel.php?edit_record=<?php echo (int) $record['id']; ?>#medicaments" class="<?php echo ui_secondary_button_classes(); ?>">Промени</a>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>

        <div class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300/75">Редакция на запис</p>
            <h2 class="mt-2 text-2xl font-bold text-white">Промяна на клиент, адрес и медикаменти</h2>

            <?php if ($selectedRecord === null) { ?>
                <p class="mt-4 text-sm leading-6 text-slate-300">Изберете запис от таблицата, за да промените името на клиента, адреса и медикаментите.</p>
            <?php } else { ?>
                <div class="mt-4 rounded-3xl border border-white/10 bg-white/5 p-5">
                    <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Избран запис #<?php echo (int) $selectedRecord['id']; ?></p>
                    <p class="mt-2 text-sm text-slate-300">Производство: <?php echo h(format_app_date($selectedRecord['date_produce'] ?? '')); ?></p>
                    <p class="mt-1 text-sm text-slate-300">Срок: <?php echo h(format_app_date($selectedRecord['date_expiri'] ?? '')); ?></p>
                </div>

                <form action="admin_panel.php?edit_record=<?php echo (int) $selectedRecord['id']; ?>#medicaments" method="post" class="mt-6 space-y-5">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="update_medicament">
                    <input type="hidden" name="record_id" value="<?php echo (int) $medicamentFormValues['id']; ?>">
                    <div>
                        <label for="admin-client" class="<?php echo ui_label_classes(); ?>">Клиент</label>
                        <input type="text" id="admin-client" name="client" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($medicamentFormValues['client']); ?>" placeholder="Име на клиент">
                    </div>
                    <div>
                        <label for="admin-address" class="<?php echo ui_label_classes(); ?>">Адрес</label>
                        <input type="text" id="admin-address" name="address" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($medicamentFormValues['address']); ?>" placeholder="Адрес на обекта">
                    </div>
                    <div>
                        <span class="<?php echo ui_label_classes(); ?>">Медикаменти</span>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <?php foreach ($medicamentOptions as $option) { ?>
                                <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-200 transition hover:border-cyan-400/30 hover:bg-white/5">
                                    <input type="checkbox" name="medicaments[]" value="<?php echo h($option); ?>" class="rounded border-white/20 bg-slate-950 text-cyan-300 focus:ring-cyan-400/30"<?php echo in_array($option, $medicamentFormValues['medicaments'], true) ? ' checked' : ''; ?>>
                                    <span><?php echo h($option); ?></span>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                    <div>
                        <label for="admin-new-medicament" class="<?php echo ui_label_classes(); ?>">Добави нов медикамент</label>
                        <input type="text" id="admin-new-medicament" name="new_medicament" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($medicamentFormValues['new_medicament']); ?>" placeholder="Напр. Ибупрофен">
                        <p class="mt-2 text-xs text-slate-400">Ако въведете ново име, то ще бъде добавено в каталога и избрано за този запис.</p>
                    </div>
                    <button type="submit" class="<?php echo ui_primary_button_classes(); ?> w-full sm:w-auto">Запази запис</button>
                </form>
            <?php } ?>
        </div>
    </section>
</div>
<?php
include 'includes/footer.php';
?>