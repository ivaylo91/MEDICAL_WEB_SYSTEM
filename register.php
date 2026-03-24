<?php
$pageTitle = 'Регистрация';

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/connect.php';

ensure_session_started();

if (!empty($_SESSION['username'])) {
    redirect_to('main_page.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_valid_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
        $errors[] = 'Невалидна заявка. Обновете страницата и опитайте отново.';
    } else {
        $result = create_user($connection, $username, $password);

        if (($result['ok'] ?? false) === true) {
            redirect_to('index.php?registered=1');
        }

        $errors = $result['errors'] ?? ['Регистрацията не беше успешна.'];
    }
}

include 'includes/header.php';
?>
<div class="flex min-h-[calc(100vh-3rem)] items-center justify-center py-8 sm:py-12">
    <div class="grid w-full max-w-6xl gap-6 lg:grid-cols-[0.92fr_1.08fr] lg:items-center">
        <section data-reveal class="mx-auto w-full max-w-lg <?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
            <div class="space-y-2">
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300/75">Нов профил</p>
                <h1 class="text-3xl font-bold text-white">Регистрация</h1>
                <p class="text-sm leading-6 text-slate-300">Създайте потребител и след това влезте в системата за управление на медикаменти.</p>
            </div>

            <div class="mt-6 space-y-3">
                <?php foreach ($errors as $error) { ?>
                    <div data-alert class="<?php echo ui_alert_classes('error'); ?>"><?php echo h($error); ?></div>
                <?php } ?>
            </div>

            <form action="register.php" method="post" class="mt-6 space-y-5">
                <?php echo csrf_input(); ?>
                <div>
                    <label for="username" class="<?php echo ui_label_classes(); ?>">Потребителско име</label>
                    <input type="text" name="username" id="username" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($_POST['username'] ?? ''); ?>" placeholder="Поне 5 символа" autocomplete="username">
                </div>
                <div>
                    <label for="password" class="<?php echo ui_label_classes(); ?>">Парола</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" class="<?php echo ui_input_classes(); ?> pr-20" placeholder="Поне 5 символа" autocomplete="new-password">
                        <button type="button" data-password-toggle="password" aria-pressed="false" class="absolute inset-y-0 right-3 my-auto inline-flex h-10 items-center rounded-full px-3 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200 transition hover:bg-white/5">Покажи</button>
                    </div>
                </div>
                <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                    <button type="submit" class="<?php echo ui_primary_button_classes(); ?> w-full sm:w-auto">Регистрация</button>
                    <a href="index.php" class="<?php echo ui_secondary_button_classes(); ?> w-full sm:w-auto">Към вход</a>
                </div>
            </form>
        </section>

        <section data-reveal class="hidden lg:block <?php echo ui_card_classes(); ?> p-10">
            <div class="max-w-xl space-y-6">
                <p class="text-sm font-semibold uppercase tracking-[0.35em] text-orange-300/70">Сигурен достъп</p>
                <h2 class="text-4xl font-extrabold leading-tight text-white">По-чист интерфейс, по-ясна регистрация.</h2>
                <p class="text-base leading-7 text-slate-300">Формата използва по-големи полета, четлив контраст и мобилно ориентирана подредба. Това намалява грешките при въвеждане и работи добре на малки екрани.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-5 text-sm leading-6 text-slate-300">Паролите се записват чрез сигурен hash, а потребителското име се валидира преди запис.</div>
                    <div class="rounded-3xl border border-orange-300/20 bg-orange-300/10 p-5 text-sm leading-6 text-orange-50/85">След регистрация системата връща потребителя към екрана за вход със статус за успех.</div>
                </div>
            </div>
        </section>
    </div>
</div>
<?php
include 'includes/footer.php';
?>
