<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) app_config('app.name')) ?> - Node'lar</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body data-page="nodes">
    <div class="page-shell">
        <button id="menu-toggle" class="menu-toggle" type="button" aria-label="Menuyu ac">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <aside id="side-menu" class="side-menu">
            <div class="menu-header">
                <p class="eyebrow">Panel</p>
                <h3><?= htmlspecialchars((string) app_config('app.name')) ?></h3>
                <p class="menu-description">Node kapasitesini ve node uzerindeki dagilimi ayri bir ekranda gosterir.</p>
            </div>
            <nav class="menu-nav">
                <a class="menu-link" href="/servers.php">Sunucular</a>
                <a class="menu-link active" href="/nodes.php">Node'lar</a>
            </nav>
        </aside>
        <div id="menu-overlay" class="menu-overlay hidden"></div>

        <main class="content">
            <section class="hero panel-card">
                <div class="hero-copy">
                    <p class="eyebrow">Node Izleyicisi</p>
                    <h1><?= htmlspecialchars((string) app_config('app.name')) ?></h1>
                    <h2>Node kapasitesini ve dagilimini net bicimde gor</h2>
                    <p class="muted">Toplam RAM, depolama, cekirdek ve sunucu sayisini ayri bir ekranda izle. Boylece node tarafini sunucu listesinden bagimsiz takip edebilirsin.</p>
                </div>
                <div class="hero-actions">
                    <div class="hero-mini-grid">
                        <div class="sidebar-card">
                            <span class="sidebar-label">Yenileme</span>
                            <strong id="refresh-value"><?= (int) app_config('app.refresh_seconds') ?> sn</strong>
                        </div>
                        <div class="sidebar-card">
                            <span class="sidebar-label">Mod</span>
                            <strong id="mode-value"><?= app_config('pterodactyl.demo_mode') ? 'Demo' : 'Canli API' ?></strong>
                        </div>
                        <div class="sidebar-card wide">
                            <span class="sidebar-label">Baglanti</span>
                            <strong id="connection-status">Hazirlaniyor</strong>
                        </div>
                    </div>
                    <button id="manual-refresh" class="primary-button">Veriyi yenile</button>
                </div>
            </section>

            <section class="stat-grid" id="summary-grid"></section>
            <section id="error-banner" class="error-banner hidden"></section>

            <section class="section-shell">
                <div class="section-title">
                    <p class="eyebrow">Kapasite</p>
                    <h3>Toplam kaynaklar</h3>
                </div>
                <section id="node-capacity-grid" class="spotlight-grid"></section>
            </section>

            <section class="section-shell">
                <div class="section-title">
                    <p class="eyebrow">Dagilim</p>
                    <h3>Node kullanimi ve bagli sunucular</h3>
                </div>
                <section class="panel-grid lower-grid">
                    <article class="panel-card">
                        <div class="panel-head compact-head">
                            <div>
                                <p class="eyebrow">Ozet</p>
                                <h3>Node kullanim ozeti</h3>
                            </div>
                        </div>
                        <div id="node-list" class="node-list"></div>
                    </article>

                    <section class="panel-card table-card">
                        <div class="panel-head compact-head">
                            <div>
                                <p class="eyebrow">Sunucular</p>
                                <h3>Node altinda calisan sunucular</h3>
                            </div>
                        </div>
                        <div id="node-server-groups" class="node-server-groups"></div>
                    </section>
                </section>
            </section>
        </main>
    </div>

    <script src="/assets/app.js"></script>
</body>
</html>
