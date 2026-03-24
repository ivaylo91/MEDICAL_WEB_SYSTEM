<?php
$pageTitle = 'Клиент';

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/connect.php';

require_login();

$isAdmin = is_admin_role($_SESSION['user_role'] ?? null);
$clientName = trim((string) ($_GET['client'] ?? ''));
$selectedRecordId = filter_input(INPUT_GET, 'edit_record', FILTER_VALIDATE_INT);
$today = new DateTimeImmutable('today');
$highlightUpdatedRecord = isset($_GET['updated']) && $selectedRecordId !== false && $selectedRecordId !== null;
$message = isset($_GET['updated']) ? 'Записът е обновен успешно.' : '';
$messageType = $message !== '' ? 'success' : 'info';

if ($message === '' && isset($_GET['deleted'])) {
    $message = 'Записът е изтрит успешно.';
    $messageType = 'success';
}

$medicamentOptions = fetch_medicament_options($connection);
$editFormValues = [
    'id' => $selectedRecordId !== false && $selectedRecordId !== null ? (int) $selectedRecordId : 0,
    'client' => '',
    'address' => '',
    'medicaments' => [],
    'new_medicament' => '',
    'date_produce' => '',
    'date_expiri' => '',
];

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_valid_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
        $message = 'Невалидна заявка. Обновете страницата и опитайте отново.';
        $messageType = 'error';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'update_record') {
            $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
            $client = trim((string) ($_POST['client'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $medicaments = $_POST['medicaments'] ?? [];
            $newMedicament = trim((string) ($_POST['new_medicament'] ?? ''));
            $dateProduce = trim((string) ($_POST['date_produce'] ?? ''));
            $dateExpiri = trim((string) ($_POST['date_expiri'] ?? ''));

            $selectedRecordId = $recordId;
            $editFormValues = [
                'id' => $recordId === false || $recordId === null ? 0 : (int) $recordId,
                'client' => $client,
                'address' => $address,
                'medicaments' => normalize_medicament_selection($medicaments),
                'new_medicament' => $newMedicament,
                'date_produce' => $dateProduce,
                'date_expiri' => $dateExpiri,
            ];

            $result = update_medicament_record(
                $connection,
                $recordId === false || $recordId === null ? 0 : (int) $recordId,
                $client,
                $address,
                $medicaments,
                $dateProduce,
                $dateExpiri,
                $newMedicament,
                true
            );

            if (($result['ok'] ?? false) === true && is_array($result['record'] ?? null)) {
                redirect_to('client_details.php?client=' . urlencode((string) $result['record']['client']) . '&edit_record=' . (int) $result['record']['id'] . '&updated=1');
            }

            $message = (string) (($result['errors'][0] ?? null) ?: 'Промените не бяха записани.');
            $messageType = 'error';
        } elseif ($action === 'delete_record') {
            $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
            $record = ($recordId === false || $recordId === null) ? null : fetch_medicament_by_id($connection, (int) $recordId);
            $targetClient = trim((string) ($record['client'] ?? $clientName));
            $result = delete_medicament_record($connection, $recordId === false || $recordId === null ? 0 : (int) $recordId);

            if (($result['ok'] ?? false) === true) {
                redirect_to('client_details.php?client=' . urlencode($targetClient) . '&deleted=1');
            }

            $message = (string) (($result['errors'][0] ?? null) ?: 'Записът не беше изтрит.');
            $messageType = 'error';
        }
    }
}

$allRecords = fetch_medicaments($connection);
$allClientPayloads = client_collection_view_model($allRecords, $today);
$clientRecords = filter_records_by_client_name($allRecords, $clientName);
$clientPayloads = client_collection_view_model($clientRecords, $today);
$clientSummary = $clientPayloads[0] ?? null;
$recordPayloads = medicament_collection_view_model($clientRecords, $today);
$totals = medicament_totals($clientRecords, $today);
$selectedRecord = null;

if ($selectedRecordId !== false && $selectedRecordId !== null && (int) $selectedRecordId > 0) {
    $selectedRecord = fetch_medicament_by_id($connection, (int) $selectedRecordId);

    if ($selectedRecord !== null && strcasecmp(trim((string) ($selectedRecord['client'] ?? '')), $clientName) === 0 && $editFormValues['client'] === '') {
        $editFormValues = [
            'id' => (int) ($selectedRecord['id'] ?? 0),
            'client' => trim((string) ($selectedRecord['client'] ?? '')),
            'address' => trim((string) ($selectedRecord['address'] ?? '')),
            'medicaments' => normalize_medicament_selection($selectedRecord['medicament'] ?? ''),
            'new_medicament' => '',
            'date_produce' => date_value_for_input($selectedRecord['date_produce'] ?? null),
            'date_expiri' => date_value_for_input($selectedRecord['date_expiri'] ?? null),
        ];
    }
}

include 'includes/header.php';
?>
<div class="space-y-6 py-4 sm:py-6">
    <section data-reveal class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.35em] text-cyan-300/75">Клиентски детайли</p>
                <h1 class="mt-2 text-3xl font-bold text-white"><?php echo $clientName !== '' ? h($clientName) : 'Изберете клиент'; ?></h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Подробен изглед на всички записи, адреси и медикаменти за избрания клиент.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="main_page.php#clients" class="<?php echo ui_secondary_button_classes(); ?>">Към таблото</a>
                <?php if (is_admin_role($_SESSION['user_role'] ?? null)) { ?>
                    <a href="admin_panel.php#clients" class="<?php echo ui_secondary_button_classes(); ?>">Към админ панела</a>
                <?php } ?>
            </div>
        </div>

        <form action="client_details.php#records" method="get" class="mt-6 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
            <div>
                <input type="text" name="client" list="client-list" value="<?php echo h($clientName); ?>" class="<?php echo ui_input_classes(); ?> mt-0" placeholder="Бързо отваряне на клиент">
                <datalist id="client-list">
                    <?php foreach ($allClientPayloads as $client) { ?>
                        <option value="<?php echo h($client['client']); ?>"></option>
                    <?php } ?>
                </datalist>
            </div>
            <button type="submit" class="<?php echo ui_secondary_button_classes(); ?>">Отвори клиент</button>
            <?php if ($clientName !== '') { ?>
                <a href="client_details.php" class="<?php echo ui_secondary_button_classes(); ?>">Изчисти</a>
            <?php } ?>
        </form>

        <?php if ($message !== '') { ?>
            <div data-alert class="mt-6 <?php echo ui_alert_classes($messageType); ?>"><?php echo h($message); ?></div>
        <?php } ?>
    </section>

    <?php if ($clientName === '' || $clientSummary === null) { ?>
        <section data-reveal class="<?php echo ui_card_classes(); ?> p-6 text-center text-slate-300 shadow-halo sm:p-8">
            Няма намерен клиент с това име.
        </section>
    <?php } else { ?>
        <section data-reveal class="grid gap-4 md:grid-cols-3">
            <div class="<?php echo ui_card_classes(); ?> p-5">
                <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Общо записи</p>
                <p class="mt-3 text-3xl font-bold text-white"><?php echo (int) $clientSummary['record_count']; ?></p>
            </div>
            <div class="<?php echo ui_card_classes(); ?> p-5">
                <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Активни</p>
                <p class="mt-3 text-3xl font-bold text-emerald-100"><?php echo (int) $clientSummary['active_count']; ?></p>
            </div>
            <div class="<?php echo ui_card_classes(); ?> p-5">
                <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Изтекли</p>
                <p class="mt-3 text-3xl font-bold text-rose-100"><?php echo (int) $clientSummary['expired_count']; ?></p>
            </div>
        </section>

        <section data-reveal class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Адреси</p>
                    <p class="mt-2 text-sm leading-6 text-slate-200"><?php echo h(implode(' • ', $clientSummary['addresses'])); ?></p>
                </div>
                <div>
                    <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Медикаменти</p>
                    <p class="mt-2 text-sm leading-6 text-slate-200"><?php echo h(implode(', ', $clientSummary['medicaments'])); ?></p>
                </div>
            </div>
        </section>

        <section id="records" data-reveal class="<?php echo ui_card_classes(); ?> overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h2 class="text-xl font-semibold text-white">Записи на клиента</h2>
                <p class="mt-1 text-sm text-slate-300">Всички записи за <?php echo h($clientName); ?>.</p>
            </div>

            <div class="grid gap-4 p-4 md:hidden">
                <?php foreach ($recordPayloads as $record) { ?>
                    <?php $isHighlightedRecord = $highlightUpdatedRecord && (int) $record['id'] === (int) $selectedRecordId; ?>
                    <article class="rounded-3xl border <?php echo $isHighlightedRecord ? 'border-emerald-300/30 bg-emerald-300/10 shadow-[0_0_0_1px_rgba(110,231,183,0.15)]' : 'border-white/10 bg-white/5'; ?> p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Запис #<?php echo $record['id']; ?></p>
                                <p class="mt-2 text-sm text-slate-300"><?php echo h($record['address']); ?></p>
                            </div>
                            <span class="<?php echo ui_status_badge_classes($record['is_expired']); ?>"><?php echo h($record['status_text']); ?></span>
                        </div>
                        <dl class="mt-4 grid gap-3 text-sm text-slate-300">
                            <div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Медикаменти</dt><dd class="text-right text-slate-100"><?php echo h($record['medicament']); ?></dd></div>
                            <div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Производство</dt><dd class="text-right text-slate-100"><?php echo h($record['formatted_date_produce']); ?></dd></div>
                            <div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Срок</dt><dd class="text-right text-slate-100"><?php echo h($record['formatted_date_expiri']); ?></dd></div>
                        </dl>
                        <?php if ($isHighlightedRecord) { ?>
                            <div class="mt-4 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm font-medium text-emerald-100">Този запис беше обновен успешно.</div>
                        <?php } ?>
                        <?php if ($isAdmin) { ?>
                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <a href="client_details.php?client=<?php echo urlencode($clientName); ?>&edit_record=<?php echo (int) $record['id']; ?>" class="<?php echo ui_secondary_button_classes(); ?> w-full">Промени</a>
                                <form action="client_details.php?client=<?php echo urlencode($clientName); ?>" method="post" onsubmit="return window.confirm('Сигурни ли сте, че искате да изтриете този запис?');">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="action" value="delete_record">
                                    <input type="hidden" name="record_id" value="<?php echo (int) $record['id']; ?>">
                                    <button type="submit" class="<?php echo ui_danger_button_classes(); ?> w-full">Изтрий</button>
                                </form>
                            </div>
                        <?php } ?>
                    </article>
                <?php } ?>
            </div>

            <div class="hidden md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10 text-left text-sm text-slate-200">
                        <thead class="bg-white/5 text-xs uppercase tracking-[0.2em] text-slate-400">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Номер</th>
                            <th class="px-6 py-4 font-semibold">Адрес</th>
                            <th class="px-6 py-4 font-semibold">Медикаменти</th>
                            <th class="px-6 py-4 font-semibold">Дата на производство</th>
                            <th class="px-6 py-4 font-semibold">Срок на годност</th>
                            <th class="px-6 py-4 font-semibold">Статус</th>
                            <?php if ($isAdmin) { ?>
                                <th class="px-6 py-4 font-semibold">Действия</th>
                            <?php } ?>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                        <?php foreach ($recordPayloads as $record) { ?>
                            <?php $isHighlightedRecord = $highlightUpdatedRecord && (int) $record['id'] === (int) $selectedRecordId; ?>
                            <tr class="transition hover:bg-white/5 <?php echo $isHighlightedRecord ? 'bg-emerald-300/10' : ''; ?>">
                                <td class="px-6 py-4 text-slate-400"><?php echo $record['id']; ?></td>
                                <td class="px-6 py-4 text-slate-300"><?php echo h($record['address']); ?></td>
                                <td class="px-6 py-4 text-slate-200"><?php echo h($record['medicament']); ?></td>
                                <td class="px-6 py-4"><?php echo h($record['formatted_date_produce']); ?></td>
                                <td class="px-6 py-4"><?php echo h($record['formatted_date_expiri']); ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="<?php echo ui_status_badge_classes($record['is_expired']); ?>"><?php echo h($record['status_text']); ?></span>
                                        <?php if ($isHighlightedRecord) { ?>
                                            <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-100">Обновен</span>
                                        <?php } ?>
                                    </div>
                                </td>
                                <?php if ($isAdmin) { ?>
                                    <td class="px-6 py-4">
                                        <div class="flex gap-3">
                                            <a href="client_details.php?client=<?php echo urlencode($clientName); ?>&edit_record=<?php echo (int) $record['id']; ?>" class="<?php echo ui_secondary_button_classes(); ?>">Промени</a>
                                            <form action="client_details.php?client=<?php echo urlencode($clientName); ?>" method="post" onsubmit="return window.confirm('Сигурни ли сте, че искате да изтриете този запис?');">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete_record">
                                                <input type="hidden" name="record_id" value="<?php echo (int) $record['id']; ?>">
                                                <button type="submit" class="<?php echo ui_danger_button_classes(); ?>">Изтрий</button>
                                            </form>
                                        </div>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <?php if ($isAdmin) { ?>
            <section data-reveal class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-orange-300/75">Админ действия</p>
                        <h2 class="mt-2 text-2xl font-bold text-white">Редакция на запис</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-300">Променете данните за конкретен запис директно от клиентската страница.</p>
                    </div>
                    <?php if ($selectedRecord !== null) { ?>
                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-slate-200">Редактирате запис #<?php echo (int) $selectedRecord['id']; ?></span>
                    <?php } ?>
                </div>

                <?php if ($selectedRecord === null) { ?>
                    <p class="mt-6 text-sm leading-6 text-slate-300">Изберете запис с бутона „Промени“, за да редактирате клиента, адреса, медикаментите и датите.</p>
                <?php } else { ?>
                    <form action="client_details.php?client=<?php echo urlencode($clientName); ?>&edit_record=<?php echo (int) $selectedRecord['id']; ?>" method="post" class="mt-6 grid gap-5 sm:grid-cols-2">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="update_record">
                        <input type="hidden" name="record_id" value="<?php echo (int) $editFormValues['id']; ?>">
                        <div>
                            <label for="client-detail-client" class="<?php echo ui_label_classes(); ?>">Клиент</label>
                            <input type="text" id="client-detail-client" name="client" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($editFormValues['client']); ?>" placeholder="Име на клиент">
                        </div>
                        <div>
                            <label for="client-detail-address" class="<?php echo ui_label_classes(); ?>">Адрес</label>
                            <input type="text" id="client-detail-address" name="address" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($editFormValues['address']); ?>" placeholder="Адрес на обекта">
                        </div>
                        <div class="sm:col-span-2">
                            <span class="<?php echo ui_label_classes(); ?>">Медикаменти</span>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <?php foreach ($medicamentOptions as $option) { ?>
                                    <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-200 transition hover:border-cyan-400/30 hover:bg-white/5">
                                        <input type="checkbox" name="medicaments[]" value="<?php echo h($option); ?>" class="rounded border-white/20 bg-slate-950 text-cyan-300 focus:ring-cyan-400/30"<?php echo in_array($option, $editFormValues['medicaments'], true) ? ' checked' : ''; ?>>
                                        <span><?php echo h($option); ?></span>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="client-detail-new-medicament" class="<?php echo ui_label_classes(); ?>">Добави нов медикамент</label>
                            <input type="text" id="client-detail-new-medicament" name="new_medicament" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($editFormValues['new_medicament']); ?>" placeholder="Напр. Ибупрофен">
                            <p class="mt-2 text-xs text-slate-400">Новото име ще бъде записано в базата данни и автоматично добавено към този запис.</p>
                        </div>
                        <div>
                            <label for="client-detail-date-produce" class="<?php echo ui_label_classes(); ?>">Дата на производство</label>
                            <input type="date" id="client-detail-date-produce" name="date_produce" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($editFormValues['date_produce']); ?>">
                        </div>
                        <div>
                            <label for="client-detail-date-expiri" class="<?php echo ui_label_classes(); ?>">Срок на годност</label>
                            <input type="date" id="client-detail-date-expiri" name="date_expiri" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($editFormValues['date_expiri']); ?>">
                        </div>
                        <div class="sm:col-span-2 flex flex-col gap-3 pt-2 sm:flex-row">
                            <button type="submit" class="<?php echo ui_primary_button_classes(); ?> w-full sm:w-auto">Запази промените</button>
                            <a href="client_details.php?client=<?php echo urlencode($clientName); ?>" class="<?php echo ui_secondary_button_classes(); ?> w-full sm:w-auto">Отказ</a>
                        </div>
                    </form>
                <?php } ?>
            </section>
        <?php } ?>
    <?php } ?>
</div>
<?php
include 'includes/footer.php';
?>