<style>
    @font-face {
        font-family: 'Vazirmatn';
        src: url('{{ asset("fonts/Vazirmatn-Regular.woff2") }}') format('woff2');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    :root {
        --melkban-background: #F3F7EF;
        --melkban-surface: #FFFFFF;
        --melkban-primary: #6F9A73;
        --melkban-primary-soft: #A8C6AA;
        --melkban-accent: #426448;
        --melkban-border: #D5E2D2;
        --melkban-ink: #26362A;
        --melkban-muted: #6B756C;
        --melkban-shadow: 0 10px 28px rgba(66, 100, 72, .10);
    }

    html { direction: rtl; }

    body.fi-body,
    button,
    input,
    select,
    textarea {
        font-family: 'Vazirmatn', Tahoma, sans-serif;
    }

    body.fi-body { color: var(--melkban-ink); background: var(--melkban-background); min-height: 100vh; }

    .fi-sidebar { background: #EDF4E9 !important; border-left: 1px solid var(--melkban-border); }
    .fi-topbar nav { background: rgba(255, 255, 255, .96) !important; border-bottom: 1px solid var(--melkban-border); box-shadow: 0 4px 18px rgba(66, 100, 72, .06) !important; }

    .fi-section,
    .fi-ta-ctn,
    .fi-wi-stats-overview-stat,
    .fi-simple-main,
    .fi-modal-window,
    .fi-dropdown-panel,
    .fi-fo-repeater-item,
    .fi-fo-builder-item,
    .melkban-card {
        background: var(--melkban-surface) !important;
        border: 1px solid var(--melkban-border) !important;
        border-radius: 1.25rem !important;
        box-shadow: var(--melkban-shadow) !important;
    }

    .fi-ta-record { border-radius: 1rem; }
    .fi-ta-record-content { padding: 1rem !important; }
    .fi-sidebar-item-button, .fi-tabs-item, .fi-btn, .fi-input-wrp { border-radius: .85rem !important; }
    .fi-sidebar-item-button, .fi-btn { transition: transform .16s ease, background-color .16s ease, box-shadow .16s ease; }
    .fi-btn:hover { transform: translateY(-1px); }

    .fi-sidebar-item-active > .fi-sidebar-item-button,
    .fi-btn-color-primary {
        background: var(--melkban-primary) !important;
        box-shadow: 0 8px 18px rgba(66, 100, 72, .20) !important;
    }

    .fi-sidebar-item-active .fi-sidebar-item-label,
    .fi-sidebar-item-active .fi-icon { color: #fff !important; }

    .fi-input-wrp, .fi-select-input, .fi-fo-rich-editor, .fi-fo-markdown-editor {
        background: #FBFDF9 !important;
        border-color: var(--melkban-border) !important;
        box-shadow: none !important;
    }

    .melkban-property-image-upload .filepond--file-info { display: none !important; }

    .fi-logo, .fi-header-heading { color: var(--melkban-accent); font-weight: 900; letter-spacing: -.025em; }
    .melkban-card-link { display: block; transition: transform .16s ease, border-color .16s ease; }
    .melkban-card-link:hover { transform: translateY(-2px); border-color: var(--melkban-primary) !important; }

    .melkban-credit {
        display: flex; align-items: center; gap: .7rem; margin: .85rem; padding: .75rem;
        color: var(--melkban-muted); border: 1px solid var(--melkban-border); border-radius: 1rem; background: #F8FBF5;
    }
    .melkban-credit--compact { justify-content: center; margin-top: 1.4rem; }
    .melkban-credit__mark {
        display: grid; place-items: center; width: 2.25rem; height: 2.25rem; flex: 0 0 auto;
        color: #fff; font-size: 1.1rem; font-weight: 900; border-radius: .75rem; background: var(--melkban-primary);
    }
    .melkban-credit strong, .melkban-credit small { display: block; }
    .melkban-credit strong { color: var(--melkban-ink); font-size: .86rem; }
    .melkban-credit small { margin-top: .08rem; font-size: .69rem; }

    .dark {
        --melkban-background: #17231A;
        --melkban-surface: #223126;
        --melkban-border: #3D5141;
        --melkban-ink: #EDF4E9;
        --melkban-muted: #A8B6AA;
        --melkban-shadow: 0 10px 28px rgba(0, 0, 0, .20);
    }
    .dark body.fi-body { background: var(--melkban-background); }
    .dark .fi-sidebar { background: #1C2A20 !important; }
    .dark .fi-topbar nav, .dark .fi-input-wrp, .dark .fi-select-input,
    .dark .fi-fo-rich-editor, .dark .fi-fo-markdown-editor, .dark .melkban-credit { background: #26372A !important; }
    .dark .fi-logo, .dark .fi-header-heading, .dark .melkban-credit strong { color: var(--melkban-ink); }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
    }
</style>
