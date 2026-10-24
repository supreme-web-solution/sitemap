<style>
    html.sm-editing [data-edit] { cursor: text; outline: 2px solid transparent; outline-offset: 3px; border-radius: 4px; transition: outline-color .12s ease, background-color .12s ease; }
    html.sm-editing [data-edit]:hover { outline-color: rgba(37, 99, 235, .55); }
    html.sm-editing [data-edit].sm-selected, html.sm-editing [data-edit]:focus { outline-color: #2563eb; }
    html.sm-editing [data-edit][contenteditable] { outline-style: solid; outline-color: #2563eb; background-color: rgba(37, 99, 235, .07); }
    html.sm-editing [data-edit-image] { cursor: pointer; outline: 2px solid transparent; outline-offset: -2px; transition: outline-color .12s ease, filter .12s ease; }
    html.sm-editing [data-edit-image]:hover { outline-color: rgba(37, 99, 235, .8); filter: brightness(.92); }
    html.sm-editing [data-edit-image].sm-selected { outline-color: #2563eb; }
    html.sm-editing .edit-add-image { position: absolute; z-index: 2; right: 1.25rem; top: 5.5rem; padding: .6rem 1rem; border-radius: 999px; border: 2px dashed #2563eb; background: rgba(255,255,255,.92); color: #1d4ed8; font: 600 .9rem system-ui, sans-serif; cursor: pointer; }
    html.sm-editing details > summary { cursor: text; }
    html.sm-editing .mobile-call, html.sm-editing .claim-bar { display: none !important; }
    html.sm-editing [data-section].sm-section-hover { outline: 2px dashed rgba(37, 99, 235, .5); outline-offset: -2px; }
    html.sm-editing [data-section].sm-section-selected { outline: 2px solid #2563eb; outline-offset: -2px; }
    html.sm-preview-mode [data-edit], html.sm-preview-mode [data-edit-image] { outline: none !important; cursor: auto; background: none !important; }
    html.sm-preview-mode [data-section] { outline: none !important; }
    .sm-chrome { position: fixed; z-index: 2147483000; display: none; align-items: center; gap: .25rem; padding: .3rem .4rem; border-radius: 10px; background: #0f172a; color: #fff; font: 600 12px/1 system-ui, sans-serif; box-shadow: 0 8px 24px rgba(0,0,0,.28); }
    .sm-chrome.is-visible { display: flex; }
    .sm-chrome .sm-chrome-label { padding: 0 .5rem 0 .35rem; opacity: .85; white-space: nowrap; }
    .sm-chrome button { all: unset; box-sizing: border-box; cursor: pointer; padding: .4rem .55rem; border-radius: 7px; font: 600 12px/1 system-ui, sans-serif; color: #fff; }
    .sm-chrome button:hover { background: rgba(255,255,255,.16); }
    .sm-chrome button:focus-visible { outline: 2px solid #93c5fd; }
    .sm-chrome button[disabled] { opacity: .35; cursor: default; }
    .sm-textarea-wrap { position: absolute; z-index: 2147483000; box-shadow: 0 12px 32px rgba(15,23,42,.3); border-radius: 10px; background: #fff; border: 2px solid #2563eb; }
    .sm-textarea-wrap textarea { display: block; width: 100%; min-height: 6rem; resize: vertical; border: 0; outline: 0; padding: .65rem .8rem; border-radius: 8px; background: #fff; color: #0f172a; font: 400 16px/1.55 system-ui, sans-serif; }
    .sm-textarea-wrap .sm-meta { display: flex; justify-content: space-between; gap: 1rem; padding: .3rem .8rem .5rem; font: 500 11px/1 system-ui, sans-serif; color: #64748b; }
</style>
