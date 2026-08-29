<style>
    @font-face {
        font-family: 'YekanBakh';
        src: url('{{ asset("fonts/YekanBakhFaNum-VF.woff2") }}') format('woff2');
        font-weight: 100 1000;
        font-style: normal;
        font-display: swap;
    }

    :root {
        --melkban-background: #F4FBF9;
        --melkban-surface: #FFFFFF;
        --melkban-surface-soft: #F7FCFB;
        --melkban-primary: #0F766E;
        --melkban-primary-soft: #D9F5EE;
        --melkban-accent: #115E59;
        --melkban-blue: #0369A1;
        --melkban-blue-soft: #E1F3FB;
        --melkban-violet: #7557B7;
        --melkban-violet-soft: #EEE9FB;
        --melkban-orange: #B45309;
        --melkban-orange-soft: #FFF0DC;
        --melkban-coral: #C2414F;
        --melkban-border: #DCECE8;
        --melkban-ink: #16302E;
        --melkban-muted: #68817E;
        --melkban-shadow: 0 12px 30px rgba(18, 73, 67, .08);
    }

    html { direction: rtl; }

    body.fi-body,
    button,
    input,
    select,
    textarea {
        font-family: 'YekanBakh', Tahoma, sans-serif;
    }

    body.fi-body { color: var(--melkban-ink); background: var(--melkban-background); min-height: 100vh; }

    .fi-sidebar { background: rgba(255, 255, 255, .97) !important; border-left: 1px solid var(--melkban-border); }
    .fi-topbar nav { background: rgba(244, 251, 249, .90) !important; border-bottom: 1px solid var(--melkban-border); box-shadow: none !important; backdrop-filter: blur(14px); }
    .fi-main { width: min(100%, 1540px); margin-inline: auto; }

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
        border-radius: 1.35rem !important;
        box-shadow: var(--melkban-shadow) !important;
    }

    .fi-ta-record { border-radius: 1rem; }
    .fi-ta-record-content { padding: 1rem !important; }
    .fi-sidebar-item-button, .fi-tabs-item, .fi-btn, .fi-input-wrp { border-radius: .9rem !important; }
    .fi-sidebar-item-button, .fi-btn { transition: transform .16s ease, background-color .16s ease, box-shadow .16s ease; }
    .fi-btn:hover { transform: translateY(-1px); }

    .fi-btn-color-primary {
        background: var(--melkban-primary) !important;
        box-shadow: 0 8px 18px rgba(15, 118, 110, .18) !important;
    }

    .fi-sidebar-item-active > .fi-sidebar-item-button {
        color: var(--melkban-accent) !important;
        background: var(--melkban-primary-soft) !important;
        box-shadow: none !important;
    }
    .fi-sidebar-item-active .fi-sidebar-item-label,
    .fi-sidebar-item-active .fi-icon { color: var(--melkban-accent) !important; }

    .fi-input-wrp, .fi-select-input, .fi-fo-rich-editor, .fi-fo-markdown-editor {
        background: var(--melkban-surface-soft) !important;
        border-color: var(--melkban-border) !important;
        box-shadow: none !important;
    }

    .melkban-property-image-upload .filepond--file-info { display: none !important; }

    .fi-logo, .fi-header-heading { color: var(--melkban-accent); font-weight: 900; letter-spacing: -.025em; }
    .melkban-card-link { display: block; transition: transform .16s ease, border-color .16s ease; }
    .melkban-card-link:hover { transform: translateY(-2px); border-color: #9FD6CE !important; }

    .fi-wi-stats-overview-stat:nth-child(4n + 1) .fi-wi-stats-overview-stat-value { color: var(--melkban-primary); }
    .fi-wi-stats-overview-stat:nth-child(4n + 2) .fi-wi-stats-overview-stat-value { color: var(--melkban-blue); }
    .fi-wi-stats-overview-stat:nth-child(4n + 3) .fi-wi-stats-overview-stat-value { color: var(--melkban-violet); }
    .fi-wi-stats-overview-stat:nth-child(4n + 4) .fi-wi-stats-overview-stat-value { color: var(--melkban-orange); }

    .melkban-credit {
        display: flex; align-items: center; gap: .7rem; margin: .85rem; padding: .75rem;
        color: var(--melkban-muted); border: 1px solid var(--melkban-border); border-radius: 1rem; background: var(--melkban-surface-soft);
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
        --melkban-background: #0F1C1B;
        --melkban-surface: #172725;
        --melkban-surface-soft: #1D302E;
        --melkban-primary-soft: #173F3A;
        --melkban-border: #2B4541;
        --melkban-ink: #EEFAF7;
        --melkban-muted: #9AB4B0;
        --melkban-shadow: 0 10px 28px rgba(0, 0, 0, .20);
    }
    .dark body.fi-body { background: var(--melkban-background); }
    .dark .fi-sidebar { background: #142421 !important; }
    .dark .fi-topbar nav, .dark .fi-input-wrp, .dark .fi-select-input,
    .dark .fi-fo-rich-editor, .dark .fi-fo-markdown-editor, .dark .melkban-credit { background: #1D302E !important; }
    .dark .fi-logo, .dark .fi-header-heading, .dark .melkban-credit strong { color: var(--melkban-ink); }

    .melkban-dashboard-welcome {
        position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        min-height: 11rem; padding: 1.75rem 2rem; color: #F3FFFC; border-radius: 1.75rem;
        background: linear-gradient(125deg, #115E59 0%, #0F766E 52%, #17988C 100%);
        box-shadow: 0 18px 36px rgba(15, 94, 89, .18);
    }
    .melkban-dashboard-welcome::after {
        content: ''; position: absolute; inset-inline-end: -3rem; bottom: -7rem; width: 18rem; height: 18rem;
        border: 2.8rem solid rgba(255,255,255,.08); border-radius: 999px;
    }
    .melkban-dashboard-welcome h2 { position: relative; z-index: 1; margin: 0; font-size: 1.65rem; font-weight: 950; }
    .melkban-dashboard-welcome p { position: relative; z-index: 1; margin-top: .25rem; color: #D6F6EF; }
    .melkban-quick-link { display: flex !important; align-items: center; gap: .8rem; min-height: 5rem; }
    .melkban-quick-icon {
        display: grid; place-items: center; width: 2.7rem; height: 2.7rem; flex: 0 0 auto;
        color: var(--melkban-accent); border-radius: .85rem; background: var(--melkban-primary-soft);
    }
    .melkban-quick-icon svg { width: 1.25rem; height: 1.25rem; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .melkban-quick-link:nth-child(2) .melkban-quick-icon { color: var(--melkban-violet); background: var(--melkban-violet-soft); }
    .melkban-quick-link:nth-child(3) .melkban-quick-icon { color: var(--melkban-blue); background: var(--melkban-blue-soft); }
    .melkban-quick-link:nth-child(4) .melkban-quick-icon { color: var(--melkban-orange); background: var(--melkban-orange-soft); }
    .melkban-notification-poller { display: flex; align-items: center; margin-inline-start: .25rem; }
    .melkban-notification-button {
        position: relative; display: grid; place-items: center; width: 2.75rem; height: 2.75rem;
        color: var(--melkban-accent); border: 1px solid var(--melkban-border); border-radius: .9rem; background: var(--melkban-surface);
    }
    .melkban-notification-button svg { width: 1.25rem; height: 1.25rem; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .melkban-notification-button span {
        position: absolute; top: -.35rem; inset-inline-end: -.35rem; min-width: 1.2rem; height: 1.2rem; padding-inline: .25rem;
        color: #fff; border: 2px solid var(--melkban-surface); border-radius: 999px; background: var(--melkban-coral);
        font-size: .65rem; font-weight: 900; line-height: 1rem; text-align: center;
    }

    @media (max-width: 640px) {
        .melkban-dashboard-welcome { min-height: 9rem; padding: 1.4rem; }
        .melkban-dashboard-welcome h2 { font-size: 1.35rem; }
        .melkban-dashboard-welcome::after { opacity: .7; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
    }
</style>
