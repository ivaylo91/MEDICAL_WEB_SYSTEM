<?php
$pageTitle = 'Добави';

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/connect.php';

require_login();

$medicamentOptions = fetch_medicament_options($connection);
$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_btn'])) {
    $client = trim($_POST['client'] ?? '');
    $medicament = $_POST['medicaments'] ?? [];
    $address = trim($_POST['address'] ?? '');
    $rawDateProduce = trim($_POST['date_produce'] ?? '');
    $rawDateExpiri = trim($_POST['date_expiri'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_valid_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
        $message = 'Невалидна заявка. Опитайте отново.';
        $messageClass = 'error-message';
    } else {
        $result = create_medicament(
            $connection,
            $client,
            $address,
            $medicament,
            $rawDateProduce,
            $rawDateExpiri,
            $keywords
        );

        if (($result['ok'] ?? false) === true) {
            $message = 'Записът е направен успешно.';
            $messageClass = 'success-message';
            $_POST = [];
        } else {
            $message = (string) (($result['errors'][0] ?? null) ?: 'Записът не беше осъществен.');
            $messageClass = 'error-message';
        }
    }
}

include 'includes/header.php';
?>
<div class="mx-auto max-w-3xl py-6 sm:py-10">
    <div data-reveal class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300/75">Добавяне</p>
                <h1 class="mt-2 text-3xl font-bold text-white">Нов запис за аптечка</h1>
                <p class="mt-2 text-sm leading-6 text-slate-300">Попълнете информацията за клиент, медикамент и срокове. Формата е оразмерена за телефон и за по-големи екрани.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="main_page.php" class="<?php echo ui_secondary_button_classes(); ?>">Назад</a>
                <a href="includes/logout.php" data-confirm-logout class="<?php echo ui_danger_button_classes(); ?>">Изход</a>
            </div>
        </div>

        <?php if ($message !== '') { ?>
            <div data-alert class="mt-6 <?php echo $messageClass === 'success-message' ? ui_alert_classes('success') : ui_alert_classes('error'); ?>"><?php echo h($message); ?></div>
        <?php } ?>

        <form action="add_form.php" method="post" class="mt-6 grid gap-5 sm:grid-cols-2">
            <?php echo csrf_input(); ?>
            <?php $selectedMedicaments = normalize_medicament_selection($_POST['medicaments'] ?? []); ?>
            <div>
                <label for="client" class="<?php echo ui_label_classes(); ?>">Клиент</label>
                <input type="text" name="client" id="client" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($_POST['client'] ?? ''); ?>" placeholder="Име на клиент">
            </div>
            <div>
                <label for="address" class="<?php echo ui_label_classes(); ?>">Адрес</label>
                <input type="text" name="address" id="address" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($_POST['address'] ?? ''); ?>" placeholder="Адрес на обекта">
            </div>
            <div class="sm:col-span-2">
                <span class="<?php echo ui_label_classes(); ?>">Медикаменти</span>
                <p class="mt-2 text-sm text-slate-400">Изберете един или повече медикаменти за този запис.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <?php foreach ($medicamentOptions as $option) { ?>
                        <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-950/50 px-4 py-3 text-sm text-slate-200 transition hover:border-cyan-400/30 hover:bg-white/5">
                            <input type="checkbox" name="medicaments[]" value="<?php echo h($option); ?>" class="rounded border-white/20 bg-slate-950 text-cyan-300 focus:ring-cyan-400/30"<?php echo in_array($option, $selectedMedicaments, true) ? ' checked' : ''; ?>>
                            <span><?php echo h($option); ?></span>
                        </label>
                    <?php } ?>
                </div>
            </div>
            <div>
                <label for="keywords" class="<?php echo ui_label_classes(); ?>">Ключови думи</label>
                <input type="text" name="keywords" id="keywords" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($_POST['keywords'] ?? ''); ?>" placeholder="Напр. болка, бинт, аптечка">
            </div>
            <div>
                <label for="date_produce" class="<?php echo ui_label_classes(); ?>">Дата производство</label>
                <input type="date" name="date_produce" id="date_produce" class="<?php echo ui_input_classes(); ?>" value="<?php echo h(date_value_for_input($_POST['date_produce'] ?? null)); ?>">
            </div>
            <div>
                <label for="date_expiri" class="<?php echo ui_label_classes(); ?>">Срок на годност</label>
                <input type="date" name="date_expiri" id="date_expiri" class="<?php echo ui_input_classes(); ?>" value="<?php echo h(date_value_for_input($_POST['date_expiri'] ?? null)); ?>">
            </div>
            <div class="sm:col-span-2 flex flex-col gap-3 pt-2 sm:flex-row">
                <button type="submit" name="add_btn" class="<?php echo ui_primary_button_classes(); ?> w-full sm:w-auto">Добави запис</button>
                <a href="main_page.php" class="<?php echo ui_secondary_button_classes(); ?> w-full sm:w-auto">Отказ</a>
            </div>
        </form>
    </div>
</div>
<?php
include "includes/footer.php";
?>
