<?php
$pageTitle = 'Промени';

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/connect.php';

require_login();

$recordId = filter_input(INPUT_GET, 'update', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recordId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: $recordId;
}

$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $rawNewDateProduce = trim($_POST['newDateProduce'] ?? '');
    $rawNewDateExpiri = trim($_POST['newDateExpiri'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_valid_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
        $message = 'Невалидна заявка. Опитайте отново.';
        $messageClass = 'error-message';
    } elseif ($recordId === false || $recordId === null) {
        $message = 'Невалиден запис за редакция.';
        $messageClass = 'error-message';
    } elseif ($rawNewDateProduce === '' || $rawNewDateExpiri === '') {
        $message = 'Всички полета са задължителни.';
        $messageClass = 'error-message';
    } else {
        $newDateProduce = normalize_app_date($rawNewDateProduce);
        $newDateExpiri = normalize_app_date($rawNewDateExpiri);

        if ($newDateProduce === null || $newDateExpiri === null) {
        $message = 'Изберете валидни дати.';
        $messageClass = 'error-message';
        } else {
            $statement = mysqli_prepare(
                $connection,
                'UPDATE medicaments SET date_produce = ?, date_expiri = ? WHERE id = ? LIMIT 1'
            );

            if ($statement === false) {
                $message = 'Промените не бяха записани.';
                $messageClass = 'error-message';
            } else {
                mysqli_stmt_bind_param($statement, 'ssi', $newDateProduce, $newDateExpiri, $recordId);
                $isUpdated = mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);

                if ($isUpdated) {
                    $message = 'Промените са направени успешно.';
                    $messageClass = 'success-message';
                } else {
                    $message = 'Промените не бяха записани.';
                    $messageClass = 'error-message';
                }
            }
        }
    }
}

$medicament = null;

if ($recordId !== false && $recordId !== null) {
    $loadStatement = mysqli_prepare(
        $connection,
        'SELECT id, client, medicament, date_produce, date_expiri FROM medicaments WHERE id = ? LIMIT 1'
    );

    if ($loadStatement !== false) {
        mysqli_stmt_bind_param($loadStatement, 'i', $recordId);
        mysqli_stmt_execute($loadStatement);
        $result = mysqli_stmt_get_result($loadStatement);
        $medicament = $result !== false ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($loadStatement);
    }
}

include 'includes/header.php';
?>
<div class="mx-auto max-w-3xl py-6 sm:py-10">
    <div data-reveal class="<?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300/75">Редакция</p>
                <h1 class="mt-2 text-3xl font-bold text-white">Промяна на срокове</h1>
                <p class="mt-2 text-sm leading-6 text-slate-300">Актуализирайте датата на производство и срока на годност без да губите контекст за избрания запис.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <a href="main_page.php" class="<?php echo ui_secondary_button_classes(); ?>">Назад</a>
                <a href="includes/logout.php" data-confirm-logout class="<?php echo ui_danger_button_classes(); ?>">Изход</a>
            </div>
        </div>

        <?php if ($message !== '') { ?>
            <div data-alert class="mt-6 <?php echo $messageClass === 'success-message' ? ui_alert_classes('success') : ui_alert_classes('error'); ?>"><?php echo h($message); ?></div>
        <?php } ?>

        <?php if ($medicament === null) { ?>
            <div data-alert class="mt-6 <?php echo ui_alert_classes('error'); ?>">Записът не беше намерен.</div>
        <?php } else { ?>
            <div class="mt-6 rounded-3xl border border-white/10 bg-white/5 p-5">
                <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Избран запис</p>
                <h2 class="mt-2 text-xl font-semibold text-white"><?php echo h($medicament['client']); ?></h2>
                <p class="mt-1 text-sm text-slate-300"><?php echo h($medicament['medicament']); ?></p>
            </div>

            <form action="edit.php?update=<?php echo (int) $medicament['id']; ?>" method="post" class="mt-6 grid gap-5 sm:grid-cols-2">
                <?php echo csrf_input(); ?>
                <div>
                    <label for="newDateProduce" class="<?php echo ui_label_classes(); ?>">Дата на производство</label>
                    <input type="date" name="newDateProduce" id="newDateProduce" class="<?php echo ui_input_classes(); ?>" value="<?php echo h(date_value_for_input($_POST['newDateProduce'] ?? $medicament['date_produce'])); ?>">
                </div>
                <div>
                    <label for="newDateExpiri" class="<?php echo ui_label_classes(); ?>">Срок на годност</label>
                    <input type="date" name="newDateExpiri" id="newDateExpiri" class="<?php echo ui_input_classes(); ?>" value="<?php echo h(date_value_for_input($_POST['newDateExpiri'] ?? $medicament['date_expiri'])); ?>">
                </div>
                <input type="hidden" name="id" value="<?php echo (int) $medicament['id']; ?>">
                <div class="sm:col-span-2 flex flex-col gap-3 pt-2 sm:flex-row">
                    <button type="submit" name="submit" class="<?php echo ui_primary_button_classes(); ?> w-full sm:w-auto">Запази промените</button>
                    <a href="main_page.php" class="<?php echo ui_secondary_button_classes(); ?> w-full sm:w-auto">Отказ</a>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
<?php
include "includes/footer.php";
?>
