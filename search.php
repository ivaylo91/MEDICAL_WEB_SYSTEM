<?php
$pageTitle = 'Търсене';

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/connect.php';

require_login();

$searchInput = $_GET['value'] ?? $_GET['search'] ?? $_POST['value'] ?? $_POST['search'] ?? '';
$searchValue = trim(is_string($searchInput) ? $searchInput : '');
$results = $searchValue === '' ? [] : fetch_medicaments($connection, $searchValue);
$message = '';
$today = new DateTimeImmutable('today');
$username = $_SESSION['username'] ?? '';
$isAdmin = is_admin_role($_SESSION['user_role'] ?? null);
$totals = medicament_totals($results, $today);
$resultPayloads = medicament_collection_view_model($results, $today);
$medicamentOptions = fetch_medicament_options($connection);

if ($searchValue === '') {
    $message = 'Въведете ключова дума за търсене.';
} elseif ($results === []) {
    $message = 'Няма намерени резултати.';
}

include 'includes/header.php';
?>
<div data-medicament-app data-api-endpoint="includes/medicaments_api.php" data-csrf-token="<?php echo h(csrf_token()); ?>" data-empty-message="Няма намерени резултати." data-initial-query="<?php echo h($searchValue); ?>" data-sync-search-url="true" data-search-param="value" class="space-y-6 py-4 sm:py-6">
    <script type="application/json" data-records-json><?php echo json_encode($resultPayloads, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script type="application/json" data-totals-json><?php echo json_encode($totals, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>

    <section data-reveal class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.35em] text-cyan-300/75">Резултати от търсенето</p>
                <h1 class="mt-2 text-3xl font-bold text-white">Търсене за <?php echo h($username); ?></h1>
                <p class="mt-2 text-sm leading-6 text-slate-300">Използвайте AJAX търсене, бърза редакция и изтриване без да напускате страницата.</p>
            </div>
            <div class="flex flex-wrap gap-3 text-sm text-slate-200">
                <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2">Днес: <?php echo h($today->format('d/m/Y')); ?></span>
                <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2">Резултати: <span data-records-count><?php echo count($resultPayloads); ?></span></span>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <a href="main_page.php" class="<?php echo ui_secondary_button_classes(); ?> w-full sm:w-auto">Назад</a>
            <a href="includes/logout.php" data-confirm-logout class="<?php echo ui_danger_button_classes(); ?> w-full sm:w-auto">Изход</a>
        </div>

        <div data-app-alerts class="mt-6 space-y-3"></div>

        <form action="search.php" method="get" data-remote-search-form class="mt-6 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
            <input type="text" name="value" autocomplete="off" data-remote-search-input class="<?php echo ui_input_classes(); ?> mt-0" value="<?php echo h($searchValue); ?>" placeholder="Търсене по клиент, адрес, медикамент, дата или ключова дума">
            <button type="submit" class="<?php echo ui_primary_button_classes(); ?>">Ново търсене</button>
            <?php if ($searchValue !== '') { ?>
                <a href="search.php" class="<?php echo ui_secondary_button_classes(); ?>">Изчисти</a>
            <?php } ?>
        </form>

        <?php if ($message !== '') { ?>
            <div data-alert class="mt-6 <?php echo $resultPayloads === [] ? ui_alert_classes('error') : ui_alert_classes('success'); ?>"><?php echo h($message); ?></div>
        <?php } ?>
    </section>

    <section data-reveal class="<?php echo ui_card_classes(); ?> overflow-hidden">
        <div class="border-b border-white/10 px-6 py-5">
            <h2 class="text-xl font-semibold text-white">Намерени записи</h2>
            <p class="mt-1 text-sm text-slate-300">Редактирайте или изтривайте записите директно от резултатите.</p>
        </div>

        <div data-records-empty class="<?php echo $resultPayloads === [] ? '' : 'hidden '; ?>px-6 py-10 text-center text-slate-300">Няма намерени резултати.</div>

        <div data-record-cards class="grid gap-4 p-4 md:hidden <?php echo $resultPayloads === [] ? 'hidden' : ''; ?>">
            <?php foreach ($resultPayloads as $record) { ?>
                <article data-record data-record-id="<?php echo $record['id']; ?>" data-search="<?php echo h($record['search_text']); ?>" class="rounded-3xl border border-white/10 bg-white/5 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Запис #<?php echo $record['id']; ?></p>
                            <h3 class="mt-2 text-lg font-semibold text-white"><?php echo h($record['client']); ?></h3>
                            <p class="mt-1 text-sm text-slate-300"><?php echo h($record['address']); ?></p>
                        </div>
                        <span class="<?php echo ui_status_badge_classes($record['is_expired']); ?>"><?php echo h($record['status_text']); ?></span>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm text-slate-300">
                        <div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Медикамент</dt><dd class="text-right text-slate-100"><?php echo h($record['medicament']); ?></dd></div>
                        <div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Производство</dt><dd class="text-right text-slate-100"><?php echo h($record['formatted_date_produce']); ?></dd></div>
                        <div class="flex items-center justify-between gap-4"><dt class="text-slate-400">Срок</dt><dd class="text-right text-slate-100"><?php echo h($record['formatted_date_expiri']); ?></dd></div>
                    </dl>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <button type="button" data-edit-trigger="<?php echo $record['id']; ?>" class="<?php echo ui_secondary_button_classes(); ?> w-full">Промени</button>
                        <button type="button" data-delete-trigger="<?php echo $record['id']; ?>" class="<?php echo ui_danger_button_classes(); ?> w-full">Изтрий</button>
                    </div>
                </article>
            <?php } ?>
        </div>

        <div class="hidden md:block <?php echo $resultPayloads === [] ? 'hidden' : ''; ?>" data-record-table-wrap>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-left text-sm text-slate-200">
                    <thead class="bg-white/5 text-xs uppercase tracking-[0.2em] text-slate-400">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Номер</th>
                        <th class="px-6 py-4 font-semibold">Клиент</th>
                        <th class="px-6 py-4 font-semibold">Адрес</th>
                        <th class="px-6 py-4 font-semibold">Медикамент</th>
                        <th class="px-6 py-4 font-semibold">Дата на производство</th>
                        <th class="px-6 py-4 font-semibold">Срок на годност</th>
                        <th class="px-6 py-4 font-semibold">Статус</th>
                        <th class="px-6 py-4 font-semibold">Действия</th>
                    </tr>
                    </thead>
                    <tbody data-record-rows class="divide-y divide-white/5">
                    <?php foreach ($resultPayloads as $record) { ?>
                        <tr data-record data-record-id="<?php echo $record['id']; ?>" data-search="<?php echo h($record['search_text']); ?>" class="transition hover:bg-white/5">
                            <td class="px-6 py-4 text-slate-400"><?php echo $record['id']; ?></td>
                            <td class="px-6 py-4 font-semibold text-white"><?php echo h($record['client']); ?></td>
                            <td class="px-6 py-4 text-slate-300"><?php echo h($record['address']); ?></td>
                            <td class="px-6 py-4 text-slate-200"><?php echo h($record['medicament']); ?></td>
                            <td class="px-6 py-4"><?php echo h($record['formatted_date_produce']); ?></td>
                            <td class="px-6 py-4"><?php echo h($record['formatted_date_expiri']); ?></td>
                            <td class="px-6 py-4"><span class="<?php echo ui_status_badge_classes($record['is_expired']); ?>"><?php echo h($record['status_text']); ?></span></td>
                            <td class="px-6 py-4">
                                <div class="flex gap-3">
                                    <button type="button" data-edit-trigger="<?php echo $record['id']; ?>" class="<?php echo ui_secondary_button_classes(); ?>">Промени</button>
                                    <button type="button" data-delete-trigger="<?php echo $record['id']; ?>" class="<?php echo ui_danger_button_classes(); ?>">Изтрий</button>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <div data-record-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm">
        <div class="w-full max-w-lg rounded-[28px] border border-white/10 bg-slate-900 p-6 shadow-halo sm:p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-cyan-300/75">Бърза редакция</p>
                    <h2 class="mt-2 text-2xl font-bold text-white" data-modal-title>Редакция на запис</h2>
                    <p class="mt-2 text-sm text-slate-300" data-modal-subtitle>Променете клиента, адреса, медикаментите и сроковете, след което запазете директно в базата данни.</p>
                </div>
                <button type="button" data-modal-close class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 text-xl text-slate-200 transition hover:bg-white/10">&times;</button>
            </div>
            <form data-edit-form class="mt-6 space-y-5">
                <input type="hidden" name="id" data-edit-id>
                <div>
                    <label for="search-modal-client" class="<?php echo ui_label_classes(); ?>">Клиент</label>
                    <input type="text" id="search-modal-client" name="client" data-edit-client class="<?php echo ui_input_classes(); ?>">
                </div>
                <div>
                    <label for="search-modal-address" class="<?php echo ui_label_classes(); ?>">Адрес</label>
                    <input type="text" id="search-modal-address" name="address" data-edit-address class="<?php echo ui_input_classes(); ?>">
                </div>
                <div>
                    <span class="<?php echo ui_label_classes(); ?>">Медикаменти</span>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <?php foreach ($medicamentOptions as $option) { ?>
                            <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-200 transition hover:border-cyan-400/30 hover:bg-white/5">
                                <input type="checkbox" name="medicaments[]" value="<?php echo h($option); ?>" data-edit-medicament class="rounded border-white/20 bg-slate-950 text-cyan-300 focus:ring-cyan-400/30">
                                <span><?php echo h($option); ?></span>
                            </label>
                        <?php } ?>
                    </div>
                </div>
                <?php if ($isAdmin) { ?>
                    <div>
                        <label for="search-modal-new-medicament" class="<?php echo ui_label_classes(); ?>">Добави нов медикамент</label>
                        <input type="text" id="search-modal-new-medicament" name="new_medicament" data-edit-new-medicament class="<?php echo ui_input_classes(); ?>" placeholder="Напр. Ибупрофен">
                        <p class="mt-2 text-xs text-slate-400">Само администратор може да добавя нов медикамент директно от редакцията на запис.</p>
                    </div>
                <?php } ?>
                <div>
                    <label for="search-modal-date-produce" class="<?php echo ui_label_classes(); ?>">Дата на производство</label>
                    <input type="date" id="search-modal-date-produce" name="date_produce" data-edit-date-produce class="<?php echo ui_input_classes(); ?>">
                </div>
                <div>
                    <label for="search-modal-date-expiri" class="<?php echo ui_label_classes(); ?>">Срок на годност</label>
                    <input type="date" id="search-modal-date-expiri" name="date_expiri" data-edit-date-expiri class="<?php echo ui_input_classes(); ?>">
                </div>
                <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                    <button type="submit" data-edit-submit class="<?php echo ui_primary_button_classes(); ?> w-full sm:w-auto">Запази</button>
                    <button type="button" data-modal-close class="<?php echo ui_secondary_button_classes(); ?> w-full sm:w-auto">Отказ</button>
                </div>
            </form>
        </div>
    </div>

    <div data-delete-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-[28px] border border-white/10 bg-slate-900 p-6 shadow-halo sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-rose-300/80">Изтриване</p>
            <h2 class="mt-2 text-2xl font-bold text-white">Потвърждение</h2>
            <p class="mt-3 text-sm leading-6 text-slate-300">На път сте да изтриете <span data-delete-name class="font-semibold text-white"></span>. Това действие не може да бъде отменено.</p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <button type="button" data-delete-confirm class="<?php echo ui_danger_button_classes(); ?> w-full sm:w-auto">Изтрий</button>
                <button type="button" data-delete-cancel class="<?php echo ui_secondary_button_classes(); ?> w-full sm:w-auto">Отказ</button>
            </div>
        </div>
    </div>
</div>
<?php
include "includes/footer.php";
?>
