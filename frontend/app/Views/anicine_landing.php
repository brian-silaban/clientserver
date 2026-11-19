<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniCine — Arsip Katalog Anime & Jadwal Bioskop Medan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-paper: #FAF8F5;
            --bg-charcoal: #121316;
            --bg-charcoal-elevated: #1A1C20;
            --surface-card: #FFFFFF;
            --surface-subtle: #F3F0EA;
            --text-heading: #1C1917;
            --text-body: #44403C;
            --text-muted: #78716C;
            --brand-crimson: #BE123C;
            --brand-crimson-hover: #9F1239;
            --brand-crimson-soft: #FFE4E6;
            --brand-slate: #1E293B;
            --brand-amber: #D97706;
            --brand-amber-soft: #FEF3C7;
            --status-emerald: #15803D;
            --status-emerald-soft: #DCFCE7;
            --border-hairline: rgba(214, 211, 209, 0.85);
            --border-dark: rgba(255, 255, 255, 0.12);
            --radius-outer: 16px;
            --radius-inner: 12px;
            --radius-pill: 8px;
            --font-display: 'Fraunces', Georgia, serif;
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg-paper);
            color: var(--text-body);
            font-family: var(--font-sans);
            font-size: 15px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        .tabular-nums {
            font-variant-numeric: tabular-nums;
        }

        .container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Top Masthead */
        .masthead {
            background-color: var(--bg-paper);
            border-bottom: 1px solid var(--border-hairline);
            position: sticky;
            top: 0;
            z-index: 40;
            backdrop-filter: blur(10px);
            background-color: rgba(250, 248, 245, 0.92);
        }

        .masthead-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
            gap: 16px;
        }

        .brand-lockup {
            display: flex;
            align-items: baseline;
            gap: 12px;
            text-decoration: none;
        }

        .brand-logo {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1;
        }

        .brand-logo .part-ani {
            color: var(--brand-crimson);
        }

        .brand-logo .part-cine {
            color: var(--brand-slate);
        }

        .brand-Subtitle {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            padding-left: 12px;
            border-left: 1px solid var(--border-hairline);
        }

        .masthead-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            background-color: var(--status-emerald-soft);
            color: var(--status-emerald);
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(21, 128, 61, 0.2);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--status-emerald);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 600;
            padding: 9px 16px;
            border-radius: var(--radius-pill);
            cursor: pointer;
            transition: all 0.18s ease;
            text-decoration: none;
            border: 1px solid transparent;
        }

        .btn:focus-visible {
            outline: 2px solid var(--brand-crimson);
            outline-offset: 2px;
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn-primary {
            background-color: var(--brand-crimson);
            color: #FFFFFF;
        }

        .btn-primary:hover {
            background-color: var(--brand-crimson-hover);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--text-heading);
            border-color: var(--border-hairline);
        }

        .btn-secondary:hover {
            background-color: var(--surface-subtle);
            border-color: #A8A29E;
        }

        .btn-ghost-dark {
            background-color: rgba(255, 255, 255, 0.06);
            color: #F5F5F4;
            border-color: var(--border-dark);
        }

        .btn-ghost-dark:hover {
            background-color: rgba(255, 255, 255, 0.14);
        }

        /* Theatrical Spotlight Hero (Dark Theater Charcoal) */
        .spotlight-hero {
            background-color: var(--bg-charcoal);
            color: #F5F5F4;
            padding: 44px 0 40px;
            border-bottom: 1px solid var(--border-hairline);
        }

        .spotlight-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-dark);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #FDA4AF;
            margin-bottom: 8px;
        }

        .spotlight-title {
            font-family: var(--font-display);
            font-size: clamp(24px, 3.2vw, 36px);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.15;
            color: #FAFAF9;
        }

        .endpoint-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-mono);
            font-size: 12px;
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            background-color: var(--bg-charcoal-elevated);
            color: #E7E5E4;
            border: 1px solid var(--border-dark);
        }

        .endpoint-method {
            color: #4ADE80;
            font-weight: 600;
        }

        /* Spotlight 5 Theatrical Movies Strip */
        .theatrical-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .theatrical-card {
            position: relative;
            border-radius: var(--radius-inner);
            overflow: hidden;
            background-color: var(--bg-charcoal-elevated);
            border: 1px solid var(--border-dark);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.2s ease, transform 0.2s ease;
            text-align: left;
            color: inherit;
            font-family: inherit;
        }

        .theatrical-card:hover {
            border-color: rgba(253, 164, 175, 0.6);
            transform: translateY(-2px);
        }

        .theatrical-card.active {
            border: 2px solid var(--brand-crimson);
            box-shadow: 0 0 0 3px rgba(190, 18, 60, 0.25);
        }

        .theatrical-card:focus-visible {
            outline: 2px solid #FDA4AF;
            outline-offset: 2px;
        }

        .poster-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 2 / 3;
            overflow: hidden;
            background-color: #1C1917;
        }

        .poster-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.45s ease-out;
        }

        .theatrical-card:hover .poster-wrap img,
        .catalog-card:hover .poster-wrap img {
            transform: scale(1.05);
        }

        .poster-scrim {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(12, 10, 9, 0.94) 0%, rgba(12, 10, 9, 0.42) 45%, rgba(12, 10, 9, 0.1) 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 12px;
        }

        .poster-top-badges {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 6px;
        }

        .badge-theatrical {
            background-color: var(--brand-crimson);
            color: #FFFFFF;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .badge-rating-age {
            background-color: rgba(12, 10, 9, 0.82);
            color: #E7E5E4;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 7px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .poster-bottom-meta {
            color: #FAFAF9;
        }

        .score-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #FBBF24;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .card-movie-title {
            font-family: var(--font-display);
            font-size: 15px;
            font-weight: 600;
            line-height: 1.25;
            color: #FFFFFF;
            margin-bottom: 2px;
        }

        .card-movie-sub {
            font-size: 11px;
            color: #D6D3D1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .theatrical-card-footer {
            padding: 10px 12px;
            background-color: var(--bg-charcoal-elevated);
            border-top: 1px solid var(--border-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            color: #A8A29E;
        }

        .showtime-count-tag {
            color: #4ADE80;
            font-weight: 600;
        }

        /* Interactive Search & Filter Bar */
        .filter-bar {
            background-color: var(--bg-charcoal-elevated);
            border: 1px solid var(--border-dark);
            border-radius: var(--radius-outer);
            padding: 16px 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .search-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 320px;
        }

        .search-input-wrap {
            position: relative;
            flex: 1;
        }

        .search-input-wrap svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #A8A29E;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            background-color: var(--bg-charcoal);
            color: #FAFAF9;
            border: 1px solid var(--border-dark);
            border-radius: var(--radius-pill);
            padding: 9px 12px 9px 38px;
            font-family: var(--font-sans);
            font-size: 13px;
            transition: border-color 0.15s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #FDA4AF;
        }

        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .year-select {
            background-color: var(--bg-charcoal);
            color: #E7E5E4;
            border: 1px solid var(--border-dark);
            border-radius: var(--radius-pill);
            padding: 8px 12px;
            font-family: var(--font-sans);
            font-size: 13px;
            cursor: pointer;
        }

        .year-select:focus-visible {
            outline: 2px solid #FDA4AF;
        }

        /* Main Content Area: Split Mashup Inspector + Catalog */
        .main-section {
            padding: 44px 0 64px;
        }

        .section-heading-row {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 700;
            color: var(--text-heading);
            letter-spacing: -0.02em;
        }

        .section-subtitle {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Asymmetric 2-Column Editorial Detail & Cinema Schedule Split Layout */
        .mashup-split-grid {
            display: grid;
            grid-template-columns: 5fr 7fr;
            gap: 28px;
            margin-bottom: 56px;
            align-items: start;
        }

        .editorial-panel {
            background-color: var(--surface-card);
            border: 1px solid var(--border-hairline);
            border-radius: var(--radius-outer);
            padding: 24px;
        }

        .panel-source-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding-bottom: 14px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-hairline);
            font-size: 12px;
        }

        .source-tag {
            font-weight: 700;
            color: var(--brand-slate);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .source-db-code {
            font-family: var(--font-mono);
            font-size: 11px;
            background-color: var(--surface-subtle);
            color: var(--text-body);
            padding: 3px 8px;
            border-radius: 6px;
        }

        .detail-top-layout {
            display: grid;
            grid-template-columns: 148px 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-poster {
            width: 100%;
            aspect-ratio: 2 / 3;
            border-radius: var(--radius-inner);
            object-fit: cover;
            border: 1px solid var(--border-hairline);
            background-color: var(--surface-subtle);
        }

        .detail-main-title {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            color: var(--text-heading);
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .detail-subtitles {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .meta-badges-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }

        .meta-pill {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            background-color: var(--surface-subtle);
            color: var(--text-heading);
        }

        .meta-pill.score {
            background-color: var(--brand-amber-soft);
            color: #92400E;
        }

        .meta-pill.theatrical {
            background-color: #FFE4E6;
            color: var(--brand-crimson);
        }

        .spec-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 16px;
            padding-top: 12px;
            border-top: 1px solid var(--border-hairline);
            font-size: 13px;
        }

        .spec-item-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            display: block;
        }

        .spec-item-val {
            font-weight: 600;
            color: var(--text-heading);
        }

        .detail-synopsis {
            font-size: 13.5px;
            color: var(--text-body);
            line-height: 1.65;
            padding-top: 16px;
            border-top: 1px solid var(--border-hairline);
        }

        /* Right Column: Cinema Ticket Stub Panel */
        .ticket-panel {
            background-color: var(--surface-card);
            border: 1px solid var(--border-hairline);
            border-radius: var(--radius-outer);
            padding: 24px;
        }

        .cinema-group-list {
            display: flex;
            flex-direction: column;
        }

        .cinema-venue-block {
            padding: 18px 0;
            border-bottom: 1px dashed #D6D3D1;
        }

        .cinema-venue-block:first-child {
            padding-top: 4px;
        }

        .cinema-venue-block:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .venue-header {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 6px;
        }

        .venue-name {
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 700;
            color: var(--text-heading);
        }

        .venue-district {
            font-size: 12px;
            font-weight: 600;
            color: var(--brand-crimson);
            background-color: #FFE4E6;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .venue-address {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        .showtime-ticket-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: var(--radius-inner);
            background-color: var(--bg-paper);
            border: 1px solid var(--border-hairline);
            margin-top: 8px;
        }

        .studio-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .studio-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-heading);
        }

        .studio-type-tag {
            font-size: 11px;
            color: var(--text-muted);
        }

        .ticket-right-metrics {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
        }

        .time-pill {
            background-color: var(--brand-slate);
            color: #FFFFFF;
            font-family: var(--font-mono);
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: var(--radius-pill);
        }

        .price-tag {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-heading);
        }

        .seat-status {
            font-size: 12px;
            font-weight: 600;
            color: var(--status-emerald);
            background-color: var(--status-emerald-soft);
            padding: 4px 10px;
            border-radius: 6px;
        }

        .empty-schedule-box {
            padding: 32px 20px;
            text-align: center;
            background-color: var(--bg-paper);
            border: 1px dashed #D6D3D1;
            border-radius: var(--radius-inner);
            color: var(--text-muted);
        }

        /* General Catalog Grid */
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 20px;
        }

        .catalog-card {
            background-color: var(--surface-card);
            border: 1px solid var(--border-hairline);
            border-radius: var(--radius-outer);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            cursor: pointer;
            transition: border-color 0.18s ease, transform 0.18s ease;
            text-align: left;
            font-family: inherit;
            color: inherit;
        }

        .catalog-card:hover {
            border-color: var(--brand-slate);
            transform: translateY(-2px);
        }

        .catalog-card:focus-visible {
            outline: 2px solid var(--brand-crimson);
            outline-offset: 2px;
        }

        .catalog-card-body {
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex: 1;
            gap: 10px;
        }

        .catalog-card-title {
            font-family: var(--font-display);
            font-size: 15px;
            font-weight: 700;
            color: var(--text-heading);
            line-height: 1.3;
        }

        .catalog-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 10px;
            border-top: 1px solid var(--border-hairline);
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Live JSON API Inspector Drawer */
        .json-drawer-section {
            margin-top: 56px;
            background-color: var(--bg-charcoal);
            color: #E7E5E4;
            border-radius: var(--radius-outer);
            padding: 24px;
            border: 1px solid var(--border-dark);
        }

        .json-pre {
            margin-top: 16px;
            background-color: #090A0C;
            padding: 18px;
            border-radius: var(--radius-inner);
            font-family: var(--font-mono);
            font-size: 12.5px;
            line-height: 1.55;
            color: #A7F3D0;
            overflow-x: auto;
            max-height: 380px;
            border: 1px solid var(--border-dark);
        }

        footer {
            border-top: 1px solid var(--border-hairline);
            padding: 28px 0;
            font-size: 13px;
            color: var(--text-muted);
            background-color: var(--surface-card);
        }

        @media (max-width: 1024px) {
            .theatrical-strip {
                grid-template-columns: repeat(3, 1fr);
            }
            .mashup-split-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .masthead-inner {
                height: auto;
                padding: 14px 0;
                flex-direction: column;
                align-items: flex-start;
            }
            .brand-Subtitle {
                display: none;
            }
            .theatrical-strip {
                grid-template-columns: repeat(2, 1fr);
            }
            .detail-top-layout {
                grid-template-columns: 110px 1fr;
            }
            .spec-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Top Editorial Masthead -->
    <header class="masthead">
        <div class="container masthead-inner">
            <a href="#top" class="brand-lockup">
                <span class="brand-logo"><span class="part-ani">Ani</span><span class="part-cine">Cine</span></span>
                <span class="brand-Subtitle">Arsip Katalog Anime &amp; Agregator Jadwal Bioskop Medan</span>
            </a>
            <div class="masthead-actions">
                <div class="status-badge" id="api-status-badge" title="Status koneksi ke database bioskop partner">
                    <span class="status-dot"></span>
                    <span id="api-status-text" class="tabular-nums">DB Bioskop: LOCAL_FALLBACK</span>
                </div>
                <a href="#json-inspector" class="btn btn-secondary">Inspector JSON</a>
            </div>
        </div>
    </header>

    <!-- Theatrical Spotlight & Interactive Filter Hero -->
    <section class="spotlight-hero" id="top">
        <div class="container">
            <div class="spotlight-header">
                <div>
                    <div class="eyebrow">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                        Sorotan Layar Lebar Medan (Service Mashup)
                    </div>
                    <h1 class="spotlight-title">5 Anime Movie dengan Jadwal Aktif di Bioskop Medan</h1>
                </div>
                <div class="endpoint-pill">
                    <span class="endpoint-method">GET</span>
                    <span id="active-endpoint-label" class="tabular-nums">/api/anime/theatrical</span>
                </div>
            </div>

            <!-- 5 Theatrical Anime Movies Showcase Strip -->
            <div class="theatrical-strip" id="theatrical-strip">
                <!-- Diisi otomatis dari /api/anime/theatrical -->
            </div>

            <!-- Interactive Search & Filter Bar -->
            <div class="filter-bar">
                <form class="search-group" id="search-form">
                    <div class="search-input-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="search" id="search-input" class="search-input" placeholder="Cari judul anime di db_anitrack (contoh: Naruto, Kimi no Na wa, Gintama)...">
                    </div>
                    <button type="submit" class="btn btn-primary">Cari Katalog</button>
                </form>

                <div class="filter-controls">
                    <select id="year-filter" class="year-select tabular-nums" aria-label="Filter Tahun Rilis">
                        <option value="">Semua Tahun (/api/anime)</option>
                        <option value="2016">Rilis Tahun 2016 (/tahun/2016)</option>
                        <option value="2014">Rilis Tahun 2014 (/tahun/2014)</option>
                        <option value="2012">Rilis Tahun 2012 (/tahun/2012)</option>
                        <option value="2001">Rilis Tahun 2001 (/tahun/2001)</option>
                    </select>
                    <button type="button" id="btn-test-tv" class="btn btn-ghost-dark">Uji Serial TV (Naruto #20)</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Section: Editorial 2-Column Split & General Catalog -->
    <main class="main-section">
        <div class="container">

            <!-- 2-Column Asymmetric Split: Left (db_anitrack) & Right (db_bioskop) -->
            <div class="section-heading-row" id="detail-anchor">
                <div>
                    <h2 class="section-title">Inspeksi Data Gabungan (Server-to-Server Mashup)</h2>
                    <p class="section-subtitle">Klik salah satu kartu anime untuk melihat penggabungan metadata <code>db_anitrack</code> (Brian) dan jadwal tayang <code>db_bioskop</code> (Sat).</p>
                </div>
                <span class="source-db-code tabular-nums" id="detail-endpoint-indicator">Endpoint: GET /api/anime/32281</span>
            </div>

            <div class="mashup-split-grid">
                <!-- Left Column: Info Katalog db_anitrack -->
                <article class="editorial-panel" id="anime-detail-panel">
                    <!-- Diisi secara dinamis -->
                </article>

                <!-- Right Column: Jadwal Bioskop Medan db_bioskop -->
                <aside class="ticket-panel" id="cinema-schedule-panel">
                    <!-- Diisi secara dinamis -->
                </aside>
            </div>

            <!-- Katalog Eksplorasi Anime -->
            <div class="section-heading-row">
                <div>
                    <h2 class="section-title" id="catalog-section-title">Katalog Anime (db_anitrack)</h2>
                    <p class="section-subtitle" id="catalog-section-sub">Menampilkan data dari endpoint <code>GET /api/anime</code></p>
                </div>
                <button type="button" id="btn-reset-catalog" class="btn btn-secondary">Muat Ulang Katalog</button>
            </div>

            <div class="catalog-grid" id="catalog-grid">
                <!-- Diisi secara dinamis -->
            </div>

            <!-- Live JSON Response Inspector -->
            <section class="json-drawer-section" id="json-inspector">
                <div class="section-heading-row" style="margin-bottom: 0;">
                    <div>
                        <h3 class="spotlight-title" style="font-size: 20px;">Payload JSON Asli dari Endpoint REST API</h3>
                        <p class="section-subtitle" style="color: #A8A29E;">Bukti integrasi data mentah yang dikembalikan oleh controller CodeIgniter 4</p>
                    </div>
                    <span class="endpoint-pill tabular-nums" id="json-endpoint-badge">GET /api/anime/32281</span>
                </div>
                <pre class="json-pre tabular-nums" id="json-output-box">Memuat payload JSON...</pre>
            </section>

        </div>
    </main>

    <footer>
        <div class="container" style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px;">
            <span><strong>AniCine REST API Project</strong> — Integrasi Layanan Katalog Anime (<code>db_anitrack</code>) &amp; Layanan Jadwal Bioskop Medan (<code>db_bioskop</code>)</span>
            <span class="tabular-nums">CodeIgniter 4 + PostgreSQL</span>
        </div>
    </footer>

    <script>
        // Data fallback representatif sesuai isi asli db_anitrack & db_bioskop jika server DB sedang tidak aktif
        const FALLBACK_THEATRICAL = [
            {
                anime_id: 32281,
                title: "Kimi no Na wa.",
                title_english: "Your Name.",
                title_japanese: "君の名は。",
                image_url: "https://myanimelist.cdn-dena.com/images/anime/5/87048.jpg",
                type_name: "Movie",
                duration_min: 106,
                rating_code: "PG-13",
                rating_description: "PG-13 - Teens 13 or older",
                score: "9.19",
                score_rank: 2,
                popularity: 33,
                genres: "Drama, Romance, School, Supernatural",
                studios: "Amuse, CoMix Wave Films, Kadokawa Shoten, Toho",
                background: "Film anime layar lebar karya Makoto Shinkai yang mengisahkan pertukaran tubuh misterius antara Mitsuha Miyamizu di pedesaan Itomori dan Taki Tachibana di pusat kota Tokyo.",
                cinema_movie_id: 372058,
                is_theatrical: true,
                cinema_showtimes: [
                    {
                        showtime_id: 21,
                        show_date: "2026-10-15",
                        start_time: "14:00:00",
                        ticket_price: "75000.00",
                        movie_id: 372058,
                        movie_title: "Kimi no Na wa.",
                        age_rating: "R13+",
                        duration_minutes: 106,
                        cinema_name: "Delipark XXI & IMAX Medan",
                        kecamatan: "Medan Barat",
                        address: "Podomoro City Deli Medan Lt. 5, Jl. Putri Hijau No. 1",
                        studio_name: "DELIPARK IMAX Studio 1",
                        studio_type: "IMAX Laser 2D",
                        total_capacity: 60,
                        seats_booked: 0,
                        seats_available: 60
                    },
                    {
                        showtime_id: 22,
                        show_date: "2026-10-15",
                        start_time: "17:30:00",
                        ticket_price: "50000.00",
                        movie_id: 372058,
                        movie_title: "Kimi no Na wa.",
                        age_rating: "R13+",
                        duration_minutes: 106,
                        cinema_name: "Delipark XXI & IMAX Medan",
                        kecamatan: "Medan Barat",
                        address: "Podomoro City Deli Medan Lt. 5, Jl. Putri Hijau No. 1",
                        studio_name: "Delipark Regular Studio 2",
                        studio_type: "Regular 2D",
                        total_capacity: 45,
                        seats_booked: 0,
                        seats_available: 45
                    }
                ]
            },
            {
                anime_id: 28851,
                title: "Koe no Katachi",
                title_english: "A Silent Voice",
                title_japanese: "聲の形",
                image_url: "https://myanimelist.cdn-dena.com/images/anime/3/80136.jpg",
                type_name: "Movie",
                duration_min: 130,
                rating_code: "PG-13",
                rating_description: "PG-13 - Teens 13 or older",
                score: "9.04",
                score_rank: 11,
                popularity: 42,
                genres: "Drama, School, Shounen",
                studios: "Kyoto Animation, Pony Canyon, Shochiku",
                background: "Kisah penebusan kesalahan Shouya Ishida terhadap Shouko Nishimiya, teman sekelas tunarungu yang pernah ia rundung semasa sekolah dasar.",
                cinema_movie_id: 378064,
                is_theatrical: true,
                cinema_showtimes: [
                    {
                        showtime_id: 23,
                        show_date: "2026-10-15",
                        start_time: "14:30:00",
                        ticket_price: "50000.00",
                        movie_id: 378064,
                        movie_title: "Koe no Katachi",
                        age_rating: "R13+",
                        duration_minutes: 130,
                        cinema_name: "Centre Point XXI Medan",
                        kecamatan: "Medan Timur",
                        address: "Mall Centre Point Lt. 3A, Jl. Jawa No. 8",
                        studio_name: "Centre Point Studio 1",
                        studio_type: "Regular 2D",
                        total_capacity: 50,
                        seats_booked: 0,
                        seats_available: 50
                    }
                ]
            },
            {
                anime_id: 199,
                title: "Sen to Chihiro no Kamikakushi",
                title_english: "Spirited Away",
                title_japanese: "千と千尋の神隠し",
                image_url: "https://myanimelist.cdn-dena.com/images/anime/6/79597.jpg",
                type_name: "Movie",
                duration_min: 125,
                rating_code: "PG",
                rating_description: "PG - Children",
                score: "8.92",
                score_rank: 19,
                popularity: 40,
                genres: "Adventure, Drama, Supernatural",
                studios: "Studio Ghibli, Toho, Walt Disney Studios",
                background: "Pemenang Academy Award ke-75 untuk Film Animasi Terbaik. Mengisahkan Chihiro yang terjebak di pemandian dunia roh milik penyihir Yubaba.",
                cinema_movie_id: 129,
                is_theatrical: true,
                cinema_showtimes: [
                    {
                        showtime_id: 24,
                        show_date: "2026-10-15",
                        start_time: "16:00:00",
                        ticket_price: "100000.00",
                        movie_id: 129,
                        movie_title: "Sen to Chihiro no Kamikakushi",
                        age_rating: "SU",
                        duration_minutes: 125,
                        cinema_name: "Sun Plaza Cinepolis Medan",
                        kecamatan: "Medan Polonia",
                        address: "Sun Plaza Lt. 4, Jl. KH. Zainul Arifin No. 7",
                        studio_name: "Sun Plaza VIP Studio 1",
                        studio_type: "Cinepolis VIP",
                        total_capacity: 30,
                        seats_booked: 0,
                        seats_available: 30
                    }
                ]
            },
            {
                anime_id: 21469,
                title: "Stand By Me Doraemon",
                title_english: "Stand By Me Doraemon",
                title_japanese: "STAND BY ME ドラえもん",
                image_url: "https://myanimelist.cdn-dena.com/images/anime/9/65571.jpg",
                type_name: "Movie",
                duration_min: 95,
                rating_code: "PG",
                rating_description: "PG - Children",
                score: "8.13",
                score_rank: 410,
                popularity: 1250,
                genres: "Comedy, Kids, Sci-Fi, Shounen",
                studios: "Shin-Ei Animation, Shirogumi, Robot Communications, Toho",
                background: "Film 3D CG Doraemon yang merangkum pertemuan awal hingga perpisahan mengharukan antara Nobita Nobi dan Doraemon.",
                cinema_movie_id: 266396,
                is_theatrical: true,
                cinema_showtimes: [
                    {
                        showtime_id: 25,
                        show_date: "2026-10-15",
                        start_time: "13:00:00",
                        ticket_price: "40000.00",
                        movie_id: 266396,
                        movie_title: "Stand By Me Doraemon",
                        age_rating: "SU",
                        duration_minutes: 95,
                        cinema_name: "Manhattan XXI Medan",
                        kecamatan: "Medan Sunggal",
                        address: "Manhattan Times Square Lt. 3, Jl. Gatot Subroto No. 217",
                        studio_name: "Manhattan Regular Studio 1",
                        studio_type: "Regular 2D",
                        total_capacity: 40,
                        seats_booked: 0,
                        seats_available: 40
                    }
                ]
            },
            {
                anime_id: 31490,
                title: "One Piece Film: Gold",
                title_english: "One Piece Film: Gold",
                title_japanese: "ONE PIECE FILM GOLD",
                image_url: "https://myanimelist.cdn-dena.com/images/anime/12/81081.jpg",
                type_name: "Movie",
                duration_min: 120,
                rating_code: "PG-13",
                rating_description: "PG-13 - Teens 13 or older",
                score: "8.14",
                score_rank: 398,
                popularity: 890,
                genres: "Action, Adventure, Comedy, Drama, Fantasy, Shounen",
                studios: "Toei Animation, Fuji TV",
                background: "Luffy dan kru Topi Jerami berlabuh di Gran Tesoro, kota hiburan kasino raksasa yang dikuasai oleh Gildo Tesoro.",
                cinema_movie_id: 376044,
                is_theatrical: true,
                cinema_showtimes: [
                    {
                        showtime_id: 26,
                        show_date: "2026-10-15",
                        start_time: "19:00:00",
                        ticket_price: "35000.00",
                        movie_id: 376044,
                        movie_title: "One Piece Film: Gold",
                        age_rating: "R13+",
                        duration_minutes: 120,
                        cinema_name: "Ringroad City Walks XXI",
                        kecamatan: "Medan Sunggal",
                        address: "Ringroad City Walks Lt. 3, Jl. Gagak Hitam No. 28",
                        studio_name: "Ringroad Regular Studio 1",
                        studio_type: "Regular 2D",
                        total_capacity: 40,
                        seats_booked: 0,
                        seats_available: 40
                    }
                ]
            }
        ];

        const FALLBACK_TV_NARUTO = {
            anime_id: 20,
            title: "Naruto",
            title_english: "Naruto",
            title_japanese: "ナルト",
            image_url: "https://myanimelist.cdn-dena.com/images/anime/13/17405.jpg",
            type_name: "TV",
            episodes: 220,
            status: "Finished Airing",
            duration_min: 23,
            rating_code: "PG-13",
            rating_description: "PG-13 - Teens 13 or older",
            score: "7.88",
            genres: "Action, Adventure, Comedy, Martial Arts, Shounen, Super Power",
            studios: "Studio Pierrot, TV Tokyo, Aniplex",
            background: "Serial TV reguler. Karena bertipe TV Series dan tidak terdaftar pada pemetaan bioskop Medan, field is_theatrical bernilai false dan cinema_showtimes kosong.",
            cinema_movie_id: null,
            is_theatrical: false,
            cinema_showtimes: []
        };

        const POSTER_FALLBACK_SVG = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" width="300" height="450" viewBox="0 0 300 450">
                <rect width="300" height="450" fill="#1C1917"/>
                <rect x="24" y="24" width="252" height="402" rx="8" fill="none" stroke="#44403C" stroke-width="1.5" stroke-dasharray="6 6"/>
                <text x="150" y="215" text-anchor="middle" fill="#A8A29E" font-family="Georgia, serif" font-size="18" font-weight="bold">AniCine Archive</text>
                <text x="150" y="240" text-anchor="middle" fill="#78716C" font-family="sans-serif" font-size="12">Poster Katalog</text>
            </svg>
        `);

        const API_BASE_URL = <?= json_encode(rtrim((string) env('ANICINE_BACKEND_API_URL', 'http://localhost:8080'), '/')) ?>;
        let theatricalList = [...FALLBACK_THEATRICAL];
        let activeAnimeId = 32281;

        function formatRupiah(num) {
            return "Rp " + Number(num || 0).toLocaleString("id-ID");
        }

        function formatTimeWIB(timeStr) {
            if (!timeStr) return "-";
            return timeStr.slice(0, 5) + " WIB";
        }

        function starSvg() {
            return `<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>`;
        }

        function renderTheatricalStrip() {
            const container = document.getElementById("theatrical-strip");
            container.innerHTML = theatricalList.map(item => {
                const isActive = Number(item.anime_id) === Number(activeAnimeId);
                const showtimeCount = Array.isArray(item.cinema_showtimes) ? item.cinema_showtimes.length : 1;
                return `
                    <button type="button" class="theatrical-card ${isActive ? 'active' : ''}" onclick="selectAnimeDetail(${item.anime_id})">
                        <div class="poster-wrap">
                            <img src="${item.image_url || POSTER_FALLBACK_SVG}" alt="Poster ${item.title}" onerror="this.onerror=null;this.src='${POSTER_FALLBACK_SVG}';">
                            <div class="poster-scrim">
                                <div class="poster-top-badges">
                                    <span class="badge-theatrical">Movie</span>
                                    <span class="badge-rating-age tabular-nums">${item.rating_code || 'PG-13'}</span>
                                </div>
                                <div class="poster-bottom-meta">
                                    <div class="score-pill tabular-nums">${starSvg()} ${item.score || '-'}</div>
                                    <div class="card-movie-title">${item.title}</div>
                                    <div class="card-movie-sub">${item.title_japanese || item.title_english || ''}</div>
                                </div>
                            </div>
                        </div>
                        <div class="theatrical-card-footer tabular-nums">
                            <span>${item.duration_min || '-'} min</span>
                            <span class="showtime-count-tag">${showtimeCount} Jadwal Medan</span>
                        </div>
                    </button>
                `;
            }).join("");
        }

        function renderSplitDetail(anime, endpointPath) {
            document.getElementById("detail-endpoint-indicator").textContent = `Endpoint: GET ${endpointPath}`;
            document.getElementById("json-endpoint-badge").textContent = `GET ${endpointPath}`;
            document.getElementById("json-output-box").textContent = JSON.stringify({
                status: 200,
                message: "Detail anime",
                data: anime
            }, null, 2);

            // Render Left Column: Info Katalog db_anitrack
            const leftPanel = document.getElementById("anime-detail-panel");
            leftPanel.innerHTML = `
                <div class="panel-source-header">
                    <span class="source-tag">Service A • Katalog Anime (Brian)</span>
                    <span class="source-db-code tabular-nums">PostgreSQL: db_anitrack (ID #${anime.anime_id})</span>
                </div>
                <div class="detail-top-layout">
                    <img class="detail-poster" src="${anime.image_url || POSTER_FALLBACK_SVG}" alt="Poster ${anime.title}" onerror="this.onerror=null;this.src='${POSTER_FALLBACK_SVG}';">
                    <div>
                        <h3 class="detail-main-title">${anime.title}</h3>
                        <p class="detail-subtitles">${[anime.title_english, anime.title_japanese].filter(Boolean).join(" • ") || "Judul Orisinal"}</p>
                        <div class="meta-badges-row tabular-nums">
                            <span class="meta-pill score">${starSvg()} ${anime.score || '-'}</span>
                            <span class="meta-pill">${anime.type_name || 'Movie'}</span>
                            <span class="meta-pill">${anime.rating_code || 'PG-13'}</span>
                            <span class="meta-pill">${anime.duration_min || '-'} menit</span>
                            ${anime.is_theatrical
                                ? `<span class="meta-pill theatrical">Tayang di Bioskop (ID #${anime.cinema_movie_id})</span>`
                                : `<span class="meta-pill">Non-Bioskop (Serial/Arsip)</span>`
                            }
                        </div>
                        <div class="spec-list tabular-nums">
                            <div>
                                <span class="spec-item-label">Genre</span>
                                <span class="spec-item-val">${anime.genres || '-'}</span>
                            </div>
                            <div>
                                <span class="spec-item-label">Studio Produksi</span>
                                <span class="spec-item-val">${anime.studios || '-'}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="detail-synopsis">
                    <strong>Catatan Katalog:</strong> ${anime.background || 'Data metadata katalog ditarik dari skema anitrack.anime beserta relasi genre, studio, media_type, dan age_rating.'}
                </div>
            `;

            // Render Right Column: Jadwal Bioskop Medan (db_bioskop)
            const rightPanel = document.getElementById("cinema-schedule-panel");
            const showtimes = Array.isArray(anime.cinema_showtimes) ? anime.cinema_showtimes : [];

            if (!anime.is_theatrical || showtimes.length === 0) {
                rightPanel.innerHTML = `
                    <div class="panel-source-header">
                        <span class="source-tag">Service B • Jadwal Bioskop Medan (Sat)</span>
                        <span class="source-db-code tabular-nums">is_theatrical: false</span>
                    </div>
                    <div class="empty-schedule-box">
                        <p style="font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">Tidak Ada Jadwal Tayang Bioskop</p>
                        <p style="font-size: 13px;">Anime <strong>${anime.title}</strong> bertipe <code>${anime.type_name || 'TV'}</code> sehingga API mengembalikan <code>"cinema_showtimes": []</code>. Klik salah satu dari 5 film di atas untuk melihat jadwal bioskop Medan.</p>
                    </div>
                `;
                return;
            }

            // Kelompokkan berdasarkan nama bioskop
            const groupedByCinema = {};
            showtimes.forEach(st => {
                const key = st.cinema_name || "Bioskop Medan";
                if (!groupedByCinema[key]) {
                    groupedByCinema[key] = {
                        cinema_name: st.cinema_name,
                        kecamatan: st.kecamatan || "Medan",
                        address: st.address || "Kota Medan, Sumatera Utara",
                        items: []
                    };
                }
                groupedByCinema[key].items.push(st);
            });

            const venueBlocksHtml = Object.values(groupedByCinema).map(venue => {
                const rowsHtml = venue.items.map(item => `
                    <div class="showtime-ticket-row tabular-nums">
                        <div class="studio-meta">
                            <span class="studio-name">${item.studio_name}</span>
                            <span class="studio-type-tag">${item.studio_type} • Tanggal: ${item.show_date}</span>
                        </div>
                        <div class="ticket-right-metrics">
                            <span class="time-pill">${formatTimeWIB(item.start_time)}</span>
                            <span class="price-tag">${formatRupiah(item.ticket_price)}</span>
                            <span class="seat-status">Tersedia ${item.seats_available}/${item.total_capacity} kursi</span>
                        </div>
                    </div>
                `).join("");

                return `
                    <div class="cinema-venue-block">
                        <div class="venue-header">
                            <h4 class="venue-name">${venue.cinema_name}</h4>
                            <span class="venue-district">${venue.kecamatan}</span>
                        </div>
                        <p class="venue-address">${venue.address}</p>
                        ${rowsHtml}
                    </div>
                `;
            }).join("");

            rightPanel.innerHTML = `
                <div class="panel-source-header">
                    <span class="source-tag">Service B • Jadwal Bioskop Medan (Sat)</span>
                    <span class="source-db-code tabular-nums">PostgreSQL: db_bioskop (Movie ID #${anime.cinema_movie_id})</span>
                </div>
                <div class="cinema-group-list">
                    ${venueBlocksHtml}
                </div>
            `;
        }

        function renderCatalogGrid(items, subtitleText) {
            document.getElementById("catalog-section-sub").textContent = subtitleText;
            const grid = document.getElementById("catalog-grid");

            if (!Array.isArray(items) || items.length === 0) {
                grid.innerHTML = `<div class="empty-schedule-box" style="grid-column: 1 / -1;">Data anime tidak ditemukan untuk filter tersebut.</div>`;
                return;
            }

            grid.innerHTML = items.map(item => {
                const isTheatrical = [32281, 28851, 199, 21469, 31490].includes(Number(item.anime_id)) || Boolean(item.is_theatrical);
                return `
                    <button type="button" class="catalog-card" onclick="selectAnimeDetail(${item.anime_id})">
                        <div class="poster-wrap">
                            <img src="${item.image_url || POSTER_FALLBACK_SVG}" alt="Poster ${item.title}" onerror="this.onerror=null;this.src='${POSTER_FALLBACK_SVG}';">
                            <div class="poster-scrim">
                                <div class="poster-top-badges">
                                    ${isTheatrical
                                        ? `<span class="badge-theatrical">Tayang di Medan</span>`
                                        : `<span class="badge-rating-age">Katalog #${item.anime_id}</span>`
                                    }
                                </div>
                                <div class="poster-bottom-meta">
                                    <div class="score-pill tabular-nums">${starSvg()} ${item.score || '-'}</div>
                                </div>
                            </div>
                        </div>
                        <div class="catalog-card-body">
                            <div>
                                <div class="catalog-card-title">${item.title}</div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">${item.title_english || item.title_japanese || 'Arsip Katalog'}</div>
                            </div>
                            <div class="catalog-card-footer tabular-nums">
                                <span>${item.duration_min ? item.duration_min + ' mnt' : (item.episodes ? item.episodes + ' eps' : 'Detail')}</span>
                                <span style="font-weight: 600; color: var(--brand-crimson);">Inspeksi API →</span>
                            </div>
                        </div>
                    </button>
                `;
            }).join("");
        }

        async function selectAnimeDetail(animeId) {
            activeAnimeId = Number(animeId);
            renderTheatricalStrip();

            const endpoint = `/api/anime/${animeId}`;
            try {
                const res = await fetch(`${API_BASE_URL}${endpoint}`);
                if (res.ok) {
                    const json = await res.json();
                    if (json && json.data) {
                        renderSplitDetail(json.data, endpoint);
                        return;
                    }
                }
            } catch (e) {
                // Gunakan fallback jika server lokal belum dinyalakan
            }

            if (Number(animeId) === 20) {
                renderSplitDetail(FALLBACK_TV_NARUTO, endpoint);
                return;
            }

            const found = theatricalList.find(a => Number(a.anime_id) === Number(animeId)) || FALLBACK_THEATRICAL[0];
            renderSplitDetail(found, endpoint);
        }

        async function loadInitialData() {
            // 1. Cek status layanan bioskop
            try {
                const statusRes = await fetch(`${API_BASE_URL}/api/cinema/status`);
                if (statusRes.ok) {
                    const statusJson = await statusRes.json();
                    if (statusJson && statusJson.data) {
                        document.getElementById("api-status-text").textContent = `DB Bioskop: ${statusJson.data.mode}`;
                    }
                }
            } catch (e) {}

            // 2. Muat daftar theatrical anime (/api/anime/theatrical)
            try {
                const thRes = await fetch(`${API_BASE_URL}/api/anime/theatrical`);
                if (thRes.ok) {
                    const thJson = await thRes.json();
                    if (thJson && Array.isArray(thJson.data) && thJson.data.length > 0) {
                        theatricalList = thJson.data;
                    }
                }
            } catch (e) {}

            renderTheatricalStrip();
            await selectAnimeDetail(activeAnimeId);

            // 3. Muat katalog umum (/api/anime?page=1&per_page=10)
            await loadCatalog("/api/anime?page=1&per_page=10", "Menampilkan data live dari endpoint GET /api/anime?page=1&per_page=10");
        }

        async function loadCatalog(url, label) {
            document.getElementById("active-endpoint-label").textContent = url;
            try {
                const res = await fetch(`${API_BASE_URL}${url}`);
                if (res.ok) {
                    const json = await res.json();
                    if (json && Array.isArray(json.data)) {
                        renderCatalogGrid(json.data, label);
                        return;
                    }
                }
            } catch (e) {}

            // Fallback katalog
            renderCatalogGrid([...theatricalList, FALLBACK_TV_NARUTO], label + " (Data Pratinjau)");
        }

        document.getElementById("search-form").addEventListener("submit", function(e) {
            e.preventDefault();
            const q = document.getElementById("search-input").value.trim();
            const url = q ? `/api/anime?q=${encodeURIComponent(q)}` : `/api/anime?page=1&per_page=10`;
            loadCatalog(url, `Hasil pencarian katalog dari GET ${url}`);
        });

        document.getElementById("year-filter").addEventListener("change", function(e) {
            const yr = e.target.value;
            if (!yr) {
                loadCatalog("/api/anime?page=1&per_page=10", "Menampilkan katalog dari GET /api/anime?page=1&per_page=10");
            } else {
                loadCatalog(`/api/anime/tahun/${yr}`, `Filter anime rilis tahun ${yr} dari GET /api/anime/tahun/${yr}`);
            }
        });

        document.getElementById("btn-test-tv").addEventListener("click", function() {
            selectAnimeDetail(20);
            document.getElementById("detail-anchor").scrollIntoView({ behavior: "smooth" });
        });

        document.getElementById("btn-reset-catalog").addEventListener("click", function() {
            document.getElementById("search-input").value = "";
            document.getElementById("year-filter").value = "";
            loadCatalog("/api/anime?page=1&per_page=10", "Menampilkan katalog dari GET /api/anime?page=1&per_page=10");
        });

        loadInitialData();
    </script>
</body>
</html>
