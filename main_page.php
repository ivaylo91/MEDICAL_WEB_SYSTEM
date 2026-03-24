<?php
$pageTitle = 'Добре дошли';

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/connect.php';

require_login();

$medicaments = fetch_medicaments($connection);
$today = new DateTimeImmutable('today');
$currentDateLabel = $today->format('d/m/Y');
$totals = medicament_totals($medicaments, $today);
$medicamentPayloads = medicament_collection_view_model($medicaments, $today);
$clientPayloads = client_collection_view_model($medicaments, $today);
$clientSearchValue = trim((string) ($_GET['client_search'] ?? ''));
$filteredClientPayloads = filter_client_collection($clientPayloads, $clientSearchValue);
$expiredCount = $totals['expired'];
$totalCount = $totals['total'];
$username = $_SESSION['username'] ?? '';
$isAdmin = is_admin_role($_SESSION['user_role'] ?? null);
$medicamentOptions = fetch_medicament_options($connection);

include 'includes/header.php';
?>
    <div data-medicament-app data-api-endpoint="includes/medicaments_api.php" data-csrf-token="<?php echo h(csrf_token()); ?>" data-empty-message="Все още няма въведени записи." data-initial-query="" class="space-y-6 py-4 sm:py-6">
        <script type="application/json" data-records-json><?php echo json_encode($medicamentPayloads, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <script type="application/json" data-totals-json><?php echo json_encode($totals, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>

        <section data-reveal class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.35em] text-cyan-300/75">Табло за медикаменти</p>
                        <h1 class="mt-2 text-3xl font-bold text-white sm:text-4xl">Добре дошли, <?php echo h($username); ?></h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Прегледайте записите, търсете по клиент, адрес, медикамент или ключова дума и редактирайте сроковете от едно интерактивно табло.</p>
                    </div>
                    <div class="flex flex-wrap gap-3 text-sm text-slate-200">
                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2">Днес: <?php echo h($currentDateLabel); ?></span>
                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2">Общо записи: <span data-total-count><?php echo $totalCount; ?></span></span>
                        <span class="rounded-full border border-rose-400/20 bg-rose-400/10 px-4 py-2 text-rose-100">Изтекли: <span data-expired-count><?php echo $expiredCount; ?></span></span>
                    </div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <a href="add_form.php" class="<?php echo ui_primary_button_classes(); ?>">Добавяне на аптечка</a>
                    <a href="includes/logout.php" data-confirm-logout class="<?php echo ui_danger_button_classes(); ?>">Изход</a>
                </div>
            </div>

            <div data-app-alerts class="mt-6 space-y-3"></div>

            <form action="search.php" method="get" data-remote-search-form class="mt-6 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                <input type="text" name="value" value="" autocomplete="off" id="search" data-remote-search-input class="<?php echo ui_input_classes(); ?> mt-0" placeholder="Търсене по клиент, адрес, медикамент или ключова дума">
                <button type="submit" class="<?php echo ui_secondary_button_classes(); ?>">Търси</button>
            </form>
            <p class="mt-3 text-sm text-slate-400">Показани записи: <span data-records-count><?php echo $totalCount; ?></span></p>
        </section>

        <section data-reveal class="grid gap-4 md:grid-cols-3">
            <div class="<?php echo ui_card_classes(); ?> p-5">
                <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Всички записи</p>
                <p class="mt-3 text-3xl font-bold text-white" data-count-up="<?php echo $totalCount; ?>" data-total-count><?php echo $totalCount; ?></p>
            </div>
            <div class="<?php echo ui_card_classes(); ?> p-5">
                <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Не е в срок</p>
                <p class="mt-3 text-3xl font-bold text-rose-100" data-count-up="<?php echo $expiredCount; ?>" data-expired-count><?php echo $expiredCount; ?></p>
            </div>
            <div class="<?php echo ui_card_classes(); ?> p-5">
                <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Активни записи</p>
                <p class="mt-3 text-3xl font-bold text-emerald-100" data-count-up="<?php echo $totals['active']; ?>" data-active-count><?php echo $totals['active']; ?></p>
            </div>
        </section>

        <section data-reveal class="<?php echo ui_card_classes(); ?> overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h2 class="text-xl font-semibold text-white">Преглед по клиенти</h2>
                <p class="mt-1 text-sm text-slate-300">Вижте всеки клиент с неговите адреси, медикаменти и броя записи.</p>
            </div>

            <div class="border-b border-white/10 px-6 py-4">
                <form action="main_page.php#clients" method="get" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                    <input type="text" name="client_search" value="<?php echo h($clientSearchValue); ?>" class="<?php echo ui_input_classes(); ?> mt-0" placeholder="Търсене по клиент, адрес или медикамент">
                    <button type="submit" class="<?php echo ui_secondary_button_classes(); ?>">Филтрирай</button>
                    <?php if ($clientSearchValue !== '') { ?>
                        <a href="main_page.php#clients" class="<?php echo ui_secondary_button_classes(); ?>">Изчисти</a>
                    <?php } ?>
                </form>
            </div>

            <?php if ($filteredClientPayloads === []) { ?>
                <div class="px-6 py-10 text-center text-slate-300">Все още няма клиенти за показване.</div>
            <?php } else { ?>
                <div class="grid gap-4 p-4 lg:grid-cols-2">
                    <?php foreach ($filteredClientPayloads as $client) { ?>
                        <article class="rounded-3xl border border-white/10 bg-white/5 p-5">
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
                            <div class="mt-5 flex flex-wrap gap-3 text-xs font-semibold uppercase tracking-[0.18em]">
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-slate-200">Активни: <?php echo (int) $client['active_count']; ?></span>
                                <span class="rounded-full border border-rose-400/20 bg-rose-400/10 px-3 py-2 text-rose-100">Изтекли: <?php echo (int) $client['expired_count']; ?></span>
                                <a href="client_details.php?client=<?php echo urlencode($client['client']); ?>" class="<?php echo ui_secondary_button_classes(); ?>">Детайли</a>
                            </div>
                        </article>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <section data-reveal class="<?php echo ui_card_classes(); ?> overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <h2 class="text-xl font-semibold text-white">Списък с аптечки</h2>
                <p class="mt-1 text-sm text-slate-300">Използвайте бърза редакция и изтриване директно от тази страница.</p>
            </div>

            <div data-records-empty class="<?php echo $medicamentPayloads === [] ? '' : 'hidden '; ?>px-6 py-10 text-center text-slate-300">Все още няма въведени записи.</div>

            <div data-record-cards class="grid gap-4 p-4 md:hidden <?php echo $medicamentPayloads === [] ? 'hidden' : ''; ?>">
                <?php foreach ($medicamentPayloads as $record) { ?>
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

            <div class="hidden md:block <?php echo $medicamentPayloads === [] ? 'hidden' : ''; ?>" data-record-table-wrap>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10 text-left text-sm text-slate-200">
                        <thead class="bg-white/5 text-xs uppercase tracking-[0.2em] text-slate-400">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Номер</th>
                            <th class="px-6 py-4 font-semibold">Клиент</th>
                            <th class="px-6 py-4 font-semibold">Адрес</th>
                            <th class="px-6 py-4 font-semibold">Медикаменти</th>
                            <th class="px-6 py-4 font-semibold">Дата на производство</th>
                            <th class="px-6 py-4 font-semibold">Срок на годност</th>
                            <th class="px-6 py-4 font-semibold">Статус</th>
                            <th class="px-6 py-4 font-semibold">Действия</th>
                        </tr>
                        </thead>
                        <tbody data-record-rows class="divide-y divide-white/5">
                        <?php foreach ($medicamentPayloads as $record) { ?>
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
                        <label for="modal-client" class="<?php echo ui_label_classes(); ?>">Клиент</label>
                        <input type="text" id="modal-client" name="client" data-edit-client class="<?php echo ui_input_classes(); ?>">
                    </div>
                    <div>
                        <label for="modal-address" class="<?php echo ui_label_classes(); ?>">Адрес</label>
                        <input type="text" id="modal-address" name="address" data-edit-address class="<?php echo ui_input_classes(); ?>">
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
                            <label for="modal-new-medicament" class="<?php echo ui_label_classes(); ?>">Добави нов медикамент</label>
                            <input type="text" id="modal-new-medicament" name="new_medicament" data-edit-new-medicament class="<?php echo ui_input_classes(); ?>" placeholder="Напр. Ибупрофен">
                            <p class="mt-2 text-xs text-slate-400">Само администратор може да добавя нов медикамент директно от редакцията на запис.</p>
                        </div>
                    <?php } ?>
                    <div>
                        <label for="modal-date-produce" class="<?php echo ui_label_classes(); ?>">Дата на производство</label>
                        <input type="date" id="modal-date-produce" name="date_produce" data-edit-date-produce class="<?php echo ui_input_classes(); ?>">
                    </div>
                    <div>
                        <label for="modal-date-expiri" class="<?php echo ui_label_classes(); ?>">Срок на годност</label>
                        <input type="date" id="modal-date-expiri" name="date_expiri" data-edit-date-expiri class="<?php echo ui_input_classes(); ?>">
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
include 'includes/footer.php';
?>