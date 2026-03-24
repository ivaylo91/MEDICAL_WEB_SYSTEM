<!doctype html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medical Platform</title>
    <script>document.documentElement.classList.add('js');</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Manrope', 'sans-serif']
                    },
                    boxShadow: {
                        halo: '0 30px 80px rgba(8, 47, 73, 0.45)'
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="stylesheets/main.css">
    <script src="scripts/app.js" defer></script>
</head>
<body class="min-h-screen bg-slate-950 font-sans text-slate-100 antialiased">
<?php
ensure_session_started();
$headerUsername = is_string($_SESSION['username'] ?? null) ? trim((string) $_SESSION['username']) : '';
$headerIsAdmin = is_admin_role($_SESSION['user_role'] ?? null);
?>
<div class="fixed inset-0 -z-10 overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.16),_transparent_28%),radial-gradient(circle_at_top_right,_rgba(251,146,60,0.16),_transparent_24%),linear-gradient(180deg,_#020617_0%,_#0f172a_52%,_#111827_100%)]"></div>
    <div class="absolute left-[10%] top-16 h-40 w-40 rounded-full bg-cyan-300/10 blur-3xl"></div>
    <div class="absolute bottom-16 right-[8%] h-52 w-52 rounded-full bg-orange-300/10 blur-3xl"></div>
    <div class="absolute left-1/2 top-1/3 h-64 w-64 -translate-x-1/2 rounded-full bg-sky-500/5 blur-3xl"></div>
</div>
<main class="relative mx-auto min-h-screen max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <?php if ($headerUsername !== '') { ?>
        <header class="mb-6 rounded-[28px] border border-white/10 bg-slate-900/50 px-4 py-4 shadow-2xl shadow-slate-950/20 backdrop-blur-xl sm:px-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.32em] text-cyan-300/75">Medical Platform</p>
                    <p class="mt-1 text-sm text-slate-300">Влезли сте като <span class="font-semibold text-white"><?php echo htmlspecialchars($headerUsername, ENT_QUOTES, 'UTF-8'); ?></span><?php if ($headerIsAdmin) { ?> <span class="ml-2 rounded-full border border-orange-300/25 bg-orange-300/10 px-2 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-orange-100">Admin</span><?php } ?></p>
                </div>
                <nav class="flex flex-wrap gap-3 text-sm">
                    <a href="main_page.php" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-4 py-2 font-semibold text-slate-100 transition hover:border-cyan-300/40 hover:bg-white/10 focus:outline-none focus:ring-4 focus:ring-white/10">Табло</a>
                    <a href="search.php" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-4 py-2 font-semibold text-slate-100 transition hover:border-cyan-300/40 hover:bg-white/10 focus:outline-none focus:ring-4 focus:ring-white/10">Търсене</a>
                    <?php if ($headerIsAdmin) { ?>
                        <a href="admin_panel.php" class="inline-flex items-center justify-center rounded-full border border-orange-300/25 bg-orange-300/10 px-4 py-2 font-semibold text-orange-100 transition hover:bg-orange-300/20 focus:outline-none focus:ring-4 focus:ring-orange-300/20">Админ панел</a>
                    <?php } ?>
                    <a href="includes/logout.php" class="inline-flex items-center justify-center rounded-full border border-amber-300/30 bg-amber-400/10 px-4 py-2 font-semibold text-amber-100 transition hover:bg-amber-400/20 focus:outline-none focus:ring-4 focus:ring-amber-300/20">Изход</a>
                </nav>
            </div>
        </header>
    <?php } ?>


