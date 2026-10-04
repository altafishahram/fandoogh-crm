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
    .fi-topbar { background: rgba(244, 251, 249, .90) !important; border-bottom: 1px solid var(--melkban-border); box-shadow: none !important; backdrop-filter: blur(14px); }
    .fi-main { width: min(100%, 1540px); min-width: 0; margin-inline: auto; }

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
    .fi-logo { font-size: 1rem; line-height: 1.5; }
    .melkban-card-link { display: block; transition: transform .16s ease, border-color .16s ease; }
    .melkban-card-link:hover { transform: translateY(-2px); border-color: #9FD6CE !important; }
    .melkban-card-link:focus-visible { outline: 2px solid var(--melkban-primary); outline-offset: 4px; }

    /* Explicit component styles: Filament's bundled theme does not compile
       arbitrary Tailwind utility classes from our custom Blade views. */
    .melkban-dashboard-flow { display: grid; gap: 1.5rem; min-width: 0; }
    .melkban-action-grid, .melkban-dashboard-columns { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; min-width: 0; }
    .melkban-dashboard-columns { gap: 1.5rem; align-items: start; }
    .melkban-action-grid > *, .melkban-dashboard-columns > * { min-width: 0; }
    .melkban-record-list { display: grid; gap: .75rem; min-width: 0; }
    .melkban-record-card { padding: 1rem; overflow-wrap: anywhere; }
    .melkban-record-heading { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: .5rem .75rem; }
    .melkban-record-status { color: var(--melkban-muted); font-size: .75rem; }
    .melkban-record-meta { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; margin-top: .5rem; color: var(--melkban-muted); font-size: .875rem; line-height: 1.7; }
    .melkban-match-summary { margin-top: .75rem; }
    .melkban-note-preview { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; margin-top: .5rem; color: var(--melkban-muted); font-size: .875rem; line-height: 1.7; }
    .melkban-empty-state { margin: 0; padding-block: .75rem; color: var(--melkban-muted); font-size: .875rem; line-height: 1.8; }

    .fi-wi-stats-overview .fi-section { background: transparent !important; border: 0 !important; box-shadow: none !important; }
    .fi-wi-stats-overview .fi-section-content { gap: 1rem; }
    .fi-wi-stats-overview .fi-section-content > .fi-grid-col:nth-child(4n + 1) .fi-wi-stats-overview-stat-value { color: var(--melkban-accent); }
    .fi-wi-stats-overview .fi-section-content > .fi-grid-col:nth-child(4n + 2) .fi-wi-stats-overview-stat-value { color: var(--melkban-blue); }
    .fi-wi-stats-overview .fi-section-content > .fi-grid-col:nth-child(4n + 3) .fi-wi-stats-overview-stat-value { color: var(--melkban-violet); }
    .fi-wi-stats-overview .fi-section-content > .fi-grid-col:nth-child(4n + 4) .fi-wi-stats-overview-stat-value { color: var(--melkban-orange); }

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
        --melkban-accent: #8DDCCD;
        --melkban-blue: #8DD6F7;
        --melkban-blue-soft: #153744;
        --melkban-violet: #D0B8F5;
        --melkban-violet-soft: #352C48;
        --melkban-orange: #FED5A1;
        --melkban-orange-soft: #453322;
        --melkban-border: #2B4541;
        --melkban-ink: #EEFAF7;
        --melkban-muted: #9AB4B0;
        --melkban-shadow: 0 10px 28px rgba(0, 0, 0, .20);
    }
    .dark body.fi-body { background: var(--melkban-background); }
    .dark .fi-sidebar { background: #142421 !important; }
    .dark .fi-topbar, .dark .fi-input-wrp, .dark .fi-select-input,
    .dark .fi-fo-rich-editor, .dark .fi-fo-markdown-editor, .dark .melkban-credit { background: #1D302E !important; }
    .dark .fi-logo, .dark .fi-header-heading, .dark .melkban-credit strong { color: var(--melkban-ink); }

    .melkban-dashboard-welcome {
        position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        min-height: 9rem; padding: 1.5rem 1.75rem; color: #F3FFFC; border-radius: 1.35rem;
        background: linear-gradient(125deg, #115E59 0%, #0F766E 52%, #17988C 100%);
        box-shadow: 0 18px 36px rgba(15, 94, 89, .18);
    }
    .melkban-dashboard-welcome::after {
        content: ''; position: absolute; inset-inline-end: -3rem; bottom: -7rem; width: 18rem; height: 18rem;
        border: 2.8rem solid rgba(255,255,255,.08); border-radius: 999px;
    }
    .melkban-dashboard-welcome h2 { position: relative; z-index: 1; margin: 0; font-size: clamp(1.25rem, 2vw, 1.5rem); font-weight: 900; line-height: 1.6; overflow-wrap: anywhere; }
    .melkban-dashboard-welcome p { position: relative; z-index: 1; margin-top: .5rem; color: #D6F6EF; line-height: 1.8; }
    .melkban-quick-link { display: flex !important; align-items: center; gap: .8rem; min-height: 5.5rem; padding: 1rem; font-weight: 700; line-height: 1.6; }
    .melkban-quick-link > span:last-child { min-width: 0; overflow-wrap: anywhere; }
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

    @media (min-width: 640px) {
        .melkban-action-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (min-width: 1024px) {
        .melkban-dashboard-columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (min-width: 1280px) {
        .melkban-action-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }

    @media (max-width: 639px) {
        .melkban-dashboard-flow, .melkban-dashboard-columns { gap: 1rem; }
        .melkban-dashboard-welcome { min-height: 8rem; padding: 1.25rem; }
        .melkban-dashboard-welcome::after { opacity: .7; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
    }

    .melkban-marketplace, .melkban-marketplace-chat { min-width: 0; line-height: 1.7; }
    .melkban-marketplace > * + *, .melkban-listing-detail > * + *, .melkban-chat-thread > * + *, .melkban-chat-form > * + * { margin-top: 1.25rem; }
    .melkban-marketplace-filters, .melkban-marketplace-cards, .melkban-listing-specs, .melkban-listing-gallery, .melkban-marketplace-chat { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
    .melkban-marketplace label, .melkban-chat-form label { display: block; min-width: 0; }
    .melkban-marketplace label > span, .melkban-chat-form label > span { display: block; margin-bottom: .5rem; font-weight: 650; }
    .melkban-marketplace input, .melkban-marketplace select, .melkban-chat-form textarea {
        width: 100%; min-height: 2.75rem; padding: .65rem .8rem; border: 1px solid var(--melkban-border); border-radius: .8rem;
        color: var(--melkban-ink); background: var(--melkban-surface); font: inherit;
    }
    .melkban-marketplace input:focus-visible, .melkban-marketplace select:focus-visible, .melkban-chat-form textarea:focus-visible, .melkban-chat-sidebar button:focus-visible { outline: 2px solid var(--melkban-primary); outline-offset: 3px; }
    .melkban-listing-card, .melkban-listing-detail, .melkban-chat-message, .melkban-chat-sidebar button {
        min-width: 0; padding: 1.25rem; border: 1px solid var(--melkban-border); border-radius: 1rem;
        color: var(--melkban-ink); background: var(--melkban-surface); box-shadow: var(--melkban-shadow);
    }
    .melkban-listing-card > * + *, .melkban-chat-message > * + * { margin-top: .65rem; }
    .melkban-listing-card h2, .melkban-listing-detail h2, .melkban-chat-thread h2 { font-size: 1.15rem; font-weight: 800; overflow-wrap: anywhere; }
    .melkban-listing-specs dt { color: var(--melkban-muted); font-size: .875rem; }
    .melkban-listing-specs dd { margin-top: .25rem; font-weight: 650; overflow-wrap: anywhere; }
    .melkban-listing-card img, .melkban-listing-gallery img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: .8rem; }
    .melkban-marketplace-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
    .melkban-public-copy, .melkban-chat-message p { white-space: pre-wrap; overflow-wrap: anywhere; }
    .melkban-chat-sidebar button { display: block; width: 100%; min-height: 2.75rem; text-align: right; cursor: pointer; }
    .melkban-chat-sidebar button + button { margin-top: .75rem; }
    .melkban-chat-sidebar button span { display: block; }
    .melkban-chat-sidebar button[aria-pressed="true"] { border-color: var(--melkban-primary); outline: 2px solid var(--melkban-primary); }
    .melkban-chat-messages { max-height: 32rem; overflow-y: auto; padding: .25rem; }
    .melkban-chat-message + .melkban-chat-message { margin-top: .75rem; }
    .melkban-chat-message img { max-width: 100%; max-height: 15rem; object-fit: contain; border-radius: .75rem; }
    .melkban-chat-message time { color: var(--melkban-muted); font-size: .8rem; }
    .melkban-chat-form textarea { min-height: 7rem; resize: vertical; }
    .melkban-chat-form [role="alert"] { color: var(--melkban-coral); }
    @media (min-width: 640px) {
        .melkban-marketplace-filters, .melkban-marketplace-cards, .melkban-listing-specs { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .melkban-listing-gallery { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (min-width: 1024px) {
        .melkban-marketplace-filters, .melkban-listing-specs { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .melkban-marketplace-chat { grid-template-columns: minmax(14rem, 1fr) minmax(0, 2fr); gap: 1.5rem; }
    }
    @media (min-width: 1280px) { .melkban-marketplace-cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
</style>
