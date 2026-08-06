<style>
    @font-face {
        font-family: 'Vazirmatn';
        src: url('{{ asset("fonts/Vazirmatn-Regular.woff2") }}') format('woff2');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    :root {
        --melkban-ink: #0f172a;
        --melkban-muted: #64748b;
        --melkban-glass: rgba(255, 255, 255, .70);
        --melkban-glass-strong: rgba(255, 255, 255, .86);
        --melkban-border: rgba(255, 255, 255, .78);
        --melkban-shadow: 0 24px 64px rgba(15, 23, 42, .11);
    }

    html { direction: rtl; }

    body.fi-body,
    button,
    input,
    select,
    textarea {
        font-family: 'Vazirmatn', Tahoma, sans-serif;
    }

    body.fi-body {
        color: var(--melkban-ink);
        background:
            radial-gradient(circle at 12% 12%, rgba(45, 212, 191, .30), transparent 28rem),
            radial-gradient(circle at 92% 22%, rgba(56, 189, 248, .24), transparent 30rem),
            radial-gradient(circle at 62% 96%, rgba(167, 139, 250, .20), transparent 32rem),
            linear-gradient(145deg, #ecfeff 0%, #f8fafc 46%, #f0fdf4 100%);
        background-attachment: fixed;
        min-height: 100vh;
    }

    .fi-sidebar,
    .fi-topbar nav,
    .fi-section,
    .fi-ta-ctn,
    .fi-wi-stats-overview-stat,
    .fi-simple-main,
    .fi-modal-window,
    .fi-dropdown-panel,
    .fi-fo-repeater-item,
    .fi-fo-builder-item {
        background: var(--melkban-glass) !important;
        border: 1px solid var(--melkban-border) !important;
        box-shadow: var(--melkban-shadow) !important;
        -webkit-backdrop-filter: blur(22px) saturate(145%);
        backdrop-filter: blur(22px) saturate(145%);
    }

    .fi-sidebar { background: rgba(248, 250, 252, .74) !important; }
    .fi-topbar nav { margin: .65rem; border-radius: 1.35rem; }
    .fi-main { position: relative; z-index: 1; }
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat, .fi-simple-main, .fi-modal-window {
        border-radius: 1.55rem !important;
        overflow: hidden;
    }

    .melkban-card {
        background: var(--melkban-glass) !important;
        border: 1px solid var(--melkban-border) !important;
        border-radius: 1.55rem !important;
        box-shadow: var(--melkban-shadow) !important;
        -webkit-backdrop-filter: blur(22px) saturate(145%);
        backdrop-filter: blur(22px) saturate(145%);
    }

    .fi-sidebar-item-button,
    .fi-tabs-item,
    .fi-btn,
    .fi-input-wrp {
        border-radius: 1rem !important;
        transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .fi-sidebar-item-active > .fi-sidebar-item-button,
    .fi-btn-color-primary {
        background: linear-gradient(135deg, #059669, #0d9488) !important;
        box-shadow: 0 12px 28px rgba(5, 150, 105, .24) !important;
    }

    .fi-sidebar-item-active .fi-sidebar-item-label,
    .fi-sidebar-item-active .fi-icon { color: #fff !important; }
    .fi-btn:hover { transform: translateY(-1px); }

    .fi-input-wrp,
    .fi-select-input,
    .fi-fo-rich-editor,
    .fi-fo-markdown-editor {
        background: rgba(255, 255, 255, .62) !important;
        border-color: rgba(148, 163, 184, .24) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .6) !important;
    }

    .fi-logo { font-weight: 900; letter-spacing: -.035em; }
    .fi-header-heading { font-weight: 900; letter-spacing: -.035em; }

    .melkban-credit {
        display: flex;
        align-items: center;
        gap: .7rem;
        margin: .85rem;
        padding: .75rem;
        color: var(--melkban-muted);
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 1.1rem;
        background: rgba(255, 255, 255, .48);
    }

    .melkban-credit--compact { justify-content: center; margin-top: 1.4rem; }
    .melkban-credit__mark {
        display: grid;
        place-items: center;
        width: 2.25rem;
        height: 2.25rem;
        flex: 0 0 auto;
        color: #fff;
        font-size: 1.1rem;
        font-weight: 900;
        border-radius: .8rem;
        background: linear-gradient(135deg, #059669, #0d9488);
        box-shadow: 0 8px 20px rgba(5, 150, 105, .24);
    }

    .melkban-credit strong, .melkban-credit small { display: block; }
    .melkban-credit strong { color: var(--melkban-ink); font-size: .86rem; }
    .melkban-credit small { margin-top: .08rem; font-size: .69rem; }

    .dark {
        --melkban-ink: #f8fafc;
        --melkban-muted: #94a3b8;
        --melkban-glass: rgba(15, 23, 42, .68);
        --melkban-glass-strong: rgba(15, 23, 42, .84);
        --melkban-border: rgba(148, 163, 184, .18);
        --melkban-shadow: 0 24px 64px rgba(2, 6, 23, .35);
    }

    .dark body.fi-body {
        background:
            radial-gradient(circle at 12% 12%, rgba(13, 148, 136, .22), transparent 28rem),
            radial-gradient(circle at 92% 22%, rgba(14, 116, 144, .22), transparent 30rem),
            radial-gradient(circle at 62% 96%, rgba(109, 40, 217, .15), transparent 32rem),
            linear-gradient(145deg, #020617 0%, #0f172a 52%, #052e2b 100%);
    }

    .dark .fi-sidebar { background: rgba(2, 6, 23, .72) !important; }
    .dark .fi-input-wrp,
    .dark .fi-select-input,
    .dark .fi-fo-rich-editor,
    .dark .fi-fo-markdown-editor,
    .dark .melkban-credit { background: rgba(15, 23, 42, .58) !important; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
    }
</style>
