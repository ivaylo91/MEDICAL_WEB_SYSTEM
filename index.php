<?php
$pageTitle = 'Вход';

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/connect.php';

ensure_session_started();

if (!empty($_SESSION['username'])) {
    redirect_to('main_page.php');
}

$errorMessage = '';
$successMessage = isset($_GET['registered']) ? 'Регистрацията е успешна. Можете да влезете.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_valid_csrf_token(is_string($csrfToken) ? $csrfToken : null)) {
        $errorMessage = 'Невалидна заявка. Обновете страницата и опитайте отново.';
    } elseif ($username === '' || $password === '') {
        $errorMessage = 'Моля попълнете потребителско име и парола.';
    } elseif (attempt_login($connection, $username, $password)) {
        redirect_to('main_page.php');
    } else {
        $errorMessage = 'Потребителското име или паролата са невалидни.';
    }
}

include 'includes/header.php';
?>
    <div class="flex min-h-[calc(100vh-3rem)] items-center justify-center py-8 sm:py-12">
        <div class="grid w-full max-w-6xl gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
            <section data-reveal class="hidden lg:block <?php echo ui_card_classes(); ?> overflow-hidden p-10 shadow-halo">
                <div class="max-w-xl space-y-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.35em] text-cyan-300/75">Medical Platform</p>
                    <h1 class="text-4xl font-extrabold leading-tight text-white xl:text-5xl">Управлявайте аптечки, срокове и търсене от едно място.</h1>
                    <p class="text-base leading-7 text-slate-300">Интерфейсът е обновен с Tailwind CSS и адаптивен layout, така че достъпът до запаси и редакции да е удобен и на телефон, и на десктоп.</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                            <p class="text-sm font-semibold text-white">По-бърз достъп</p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Вход, търсене и редакция са на по-малко кликове с ясен визуален приоритет.</p>
                        </div>
                        <div class="rounded-3xl border border-cyan-300/20 bg-cyan-300/10 p-5">
                            <p class="text-sm font-semibold text-cyan-100">Мобилна употреба</p>
                            <p class="mt-2 text-sm leading-6 text-cyan-50/80">Формите и бутоните са оразмерени за докосване и работят без хоризонтално счупване.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section data-reveal class="mx-auto w-full max-w-lg <?php echo ui_card_classes(); ?> p-6 shadow-halo sm:p-8">
                <div class="space-y-2">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300/75">Потребителски вход</p>
                    <h2 class="text-3xl font-bold text-white">Добре дошли</h2>
                    <p class="text-sm leading-6 text-slate-300">Въведете своите данни, за да отворите панела с медикаменти и срокове.</p>
                </div>

                <div class="mt-6 space-y-3">
                    <?php if ($successMessage !== '') { ?>
                        <div data-alert class="<?php echo ui_alert_classes('success'); ?>"><?php echo h($successMessage); ?></div>
                    <?php } ?>
                    <?php if ($errorMessage !== '') { ?>
                        <div data-alert class="<?php echo ui_alert_classes('error'); ?>"><?php echo h($errorMessage); ?></div>
                    <?php } ?>
                </div>

                <form method="post" class="mt-6 space-y-5">
                    <?php echo csrf_input(); ?>
                    <div>
                        <label for="username" class="<?php echo ui_label_classes(); ?>">Потребителско име</label>
                        <input type="text" name="username" id="username" class="<?php echo ui_input_classes(); ?>" value="<?php echo h($_POST['username'] ?? ''); ?>" placeholder="Въведете потребителско име" autocomplete="username">
                    </div>
                    <div>
                        <label for="password" class="<?php echo ui_label_classes(); ?>">Парола</label>
                        <div class="relative">
                            <input type="password" name="password" id="password" class="<?php echo ui_input_classes(); ?> pr-20" placeholder="Въведете парола" autocomplete="current-password">
                            <button type="button" data-password-toggle="password" aria-pressed="false" class="absolute inset-y-0 right-3 my-auto inline-flex h-10 items-center rounded-full px-3 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-200 transition hover:bg-white/5">Покажи</button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                        <button type="submit" name="login_btn" class="<?php echo ui_primary_button_classes(); ?> w-full sm:w-auto">Вход</button>
                        <a href="register.php" class="<?php echo ui_secondary_button_classes(); ?> w-full sm:w-auto">Регистрация</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
<?php
include 'includes/footer.php';
?>