<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) app_config('app.name')) ?> - Sunucular</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body data-page="servers">
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
                <p class="menu-description">Sunuculari ve node yapisini sade, acik kaynak dostu bir arayuzle izle.</p>
            </div>
            <nav class="menu-nav">
                <a class="menu-link active" href="/servers.php">Sunucular</a>
                <a class="menu-link" href="/nodes.php">Node'lar</a>
            </nav>
        </aside>
        <div id="menu-overlay" class="menu-overlay hidden"></div>

        <main class="content">
            <section class="hero panel-card">
                <div class="hero-copy">
                    <p class="eyebrow">Sunucu Izleyicisi</p>
                    <h1><?= htmlspecialchars((string) app_config('app.name')) ?></h1>
                    <h2>Durum, yuk ve trend bilgilerini tek akista takip et</h2>
                    <p class="muted">Bu ekran; tum sunuculari durum, CPU, RAM, disk, ag trafigi ve uptime bilgileriyle profesyonel bir dashboard duzeninde listeler.</p>
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

            <section id="error-banner" class="error-banner hidden"></section>
            <section class="stat-grid" id="summary-grid"></section>
            <section class="status-grid" id="status-grid"></section>
            <section class="insight-grid" id="insight-grid"></section>

            <section class="panel-card filter-panel">
                <div class="panel-head compact-head">
                    <div>
                        <p class="eyebrow">Filtreler</p>
                        <h3>Aramayi ve siralamayi hizlandir</h3>
                    </div>
                </div>

                <div class="filter-layout">
                    <label class="control-field control-field-wide">
                        <span>Arama</span>
                        <input id="search-input" class="search-input" type="search" placeholder="Sunucu, tur, node veya kullanici ara">
                    </label>

                    <label class="control-field">
                        <span>Durum</span>
                        <select id="status-filter" class="control-input"></select>
                    </label>

                    <label class="control-field">
                        <span>Tur</span>
                        <select id="family-filter" class="control-input"></select>
                    </label>

                    <label class="control-field">
                        <span>Node</span>
                        <select id="node-filter" class="control-input"></select>
                    </label>

                    <label class="control-field">
                        <span>Siralama</span>
                        <select id="sort-select" class="control-input"></select>
                    </label>
                </div>
            </section>

            <section class="section-shell">
                <div class="section-title">
                    <div>
                        <p class="eyebrow">Trend Merkezi</p>
                        <h3>CPU, RAM ve disk trendlerini ayri ayri incele</h3>
                    </div>
                    <div class="trend-toolbar">
                        <div id="trend-metric-tabs" class="segmented-control"></div>
                        <div id="trend-window-tabs" class="segmented-control"></div>
                    </div>
                </div>

                <section class="panel-grid trend-layout">
                    <article class="panel-card chart-card trend-card">
                        <div class="panel-head compact-head">
                            <div>
                                <p class="eyebrow">Grafik</p>
                                <h3 id="trend-title">En yuksek yukteki sunucular</h3>
                            </div>
                        </div>
                        <div class="trend-stage">
                            <canvas id="trend-chart" width="1200" height="460"></canvas>
                        </div>
                    </article>

                    <article class="panel-card legend-card">
                        <div class="panel-head compact-head">
                            <div>
                                <p class="eyebrow">Trend Listesi</p>
                                <h3>Gosterilen sunucular</h3>
                            </div>
                        </div>
                        <div id="trend-list" class="trend-list"></div>
                    </article>
                </section>
            </section>

            <section class="section-shell">
                <div class="section-title">
                    <div>
                        <p class="eyebrow">Canli Liste</p>
                        <h3>Tum sunucular ve acilir detay alani</h3>
                    </div>
                </div>

                <section class="panel-grid usage-grid">
                    <section class="panel-card table-card">
                        <div class="panel-head compact-head table-head">
                            <div>
                                <p class="eyebrow">Tablo</p>
                                <h3>Sunucu envanteri</h3>
                            </div>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Sunucu</th>
                                        <th>Durum</th>
                                        <th>Tur</th>
                                        <th>CPU</th>
                                        <th>RAM</th>
                                        <th>Disk</th>
                                        <th>Ag</th>
                                        <th>Uptime</th>
                                        <th>Node</th>
                                        <th>Skor</th>
                                        <th>Detay</th>
                                    </tr>
                                </thead>
                                <tbody id="server-table"></tbody>
                            </table>
                        </div>
                        <div id="table-pagination" class="table-pagination"></div>
                    </section>
                </section>
            </section>
        </main>
    </div>

    <script src="/assets/app.js"></script>
</body>
</html>
