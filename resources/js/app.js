import './bootstrap';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import Chart from 'chart.js/auto';

let aceLoaderPromise = null;
let aceInlineFailureNotified = false;

const loadAce = async () => {
    if (aceLoaderPromise) {
        return aceLoaderPromise;
    }

    aceLoaderPromise = (async () => {
        const aceModule = await import('ace-builds/src-noconflict/ace');
        const resolvedAce = aceModule.default ?? aceModule;
        const ace = resolvedAce?.default ?? resolvedAce;

        if (typeof window !== 'undefined' && ace) {
            window.ace = ace;
        }

        await import('ace-builds/src-noconflict/ext-language_tools');
        await import('ace-builds/src-noconflict/mode-html');
        await import('ace-builds/src-noconflict/theme-tomorrow_night');

        const readyAce = (typeof window !== 'undefined' ? window.ace : null) || ace;
        if (!readyAce || typeof readyAce.edit !== 'function') {
            throw new Error('Ace core failed to initialize.');
        }

        return readyAce;
    })()
        .catch((error) => {
            aceLoaderPromise = null;
            throw error;
        });

    return aceLoaderPromise;
};

const initAceLauncher = () => {
    const overlay = document.getElementById('admin-ace-overlay');
    const editorRoot = document.getElementById('admin-ace-editor');
    const titleNode = document.getElementById('admin-ace-title');
    const closeButton = document.getElementById('admin-ace-close');
    const cancelButton = document.getElementById('admin-ace-cancel');
    const applyButton = document.getElementById('admin-ace-apply');

    if (!overlay || !editorRoot || !closeButton || !cancelButton || !applyButton) {
        return;
    }

    if (overlay.dataset.aceLauncherReady === '1') {
        return;
    }
    overlay.dataset.aceLauncherReady = '1';

    let editor = null;
    let targetTextarea = null;
    let openNonce = 0;

    const ensureEditor = async () => {
        if (editor) {
            return editor;
        }

        const ace = await loadAce();
        editor = ace.edit(editorRoot);
        editor.session.setMode('ace/mode/html');
        editor.setTheme('ace/theme/tomorrow_night');
        editor.setOptions({
            fontSize: '13px',
            showPrintMargin: false,
            useSoftTabs: true,
            tabSize: 2,
            enableBasicAutocompletion: true,
            enableLiveAutocompletion: true,
        });
        editor.session.setUseWorker(false);

        return editor;
    };

    const close = () => {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        targetTextarea = null;
    };

    const open = async (textarea, label) => {
        const requestNonce = ++openNonce;
        targetTextarea = textarea;
        titleNode.textContent = label || 'HTML Editor';
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');

        try {
            const loadedEditor = await ensureEditor();
            if (requestNonce !== openNonce || !overlay.classList.contains('is-open')) {
                return;
            }

            loadedEditor.setValue(textarea.value || '', -1);
            setTimeout(() => loadedEditor.focus(), 0);
        } catch (error) {
            console.error('Failed to load Ace editor', error);
            close();
            window.dispatchEvent(new CustomEvent('admin:notify', {
                detail: { type: 'danger', message: 'Ace editor failed to load.' },
            }));
        }
    };

    const apply = () => {
        if (!targetTextarea || !editor) {
            close();
            return;
        }

        const value = editor.getValue();
        if (targetTextarea.value !== value) {
            targetTextarea.value = value;
            targetTextarea.dispatchEvent(new Event('input', { bubbles: true }));
            targetTextarea.dispatchEvent(new Event('change', { bubbles: true }));
        }

        close();
    };

    const bindLaunchButtons = () => {
        const buttons = document.querySelectorAll('[data-ace-open][data-ace-target]');
        buttons.forEach((button) => {
            if (button.dataset.aceBound === '1') {
                return;
            }
            button.dataset.aceBound = '1';

            button.addEventListener('click', async () => {
                const targetId = button.getAttribute('data-ace-target');
                if (!targetId) return;

                const textarea = document.getElementById(targetId);
                if (!(textarea instanceof HTMLTextAreaElement)) {
                    return;
                }

                await open(textarea, button.getAttribute('data-ace-label') || 'HTML Editor');
            });
        });
    };

    closeButton.addEventListener('click', close);
    cancelButton.addEventListener('click', close);
    applyButton.addEventListener('click', apply);

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            close();
        }
    });

    window.addEventListener('keydown', (event) => {
        if (!overlay.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }

        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            apply();
        }
    });

    bindLaunchButtons();

    const observer = new MutationObserver(() => {
        bindLaunchButtons();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
};

const initTomSelect = () => {
    if (!document.body) {
        return;
    }

    const buildTypeRender = () => ({
        option(data, escape) {
            const value = escape(String(data.value ?? ''));
            const label = escape(String(data.text ?? data.label ?? data.value ?? ''));
            return `<div class="tom-type-option"><span class="tom-type-chip tom-type-chip--${value}"></span><span>${label}</span></div>`;
        },
        item(data, escape) {
            const value = escape(String(data.value ?? ''));
            const label = escape(String(data.text ?? data.label ?? data.value ?? ''));
            return `<div class="tom-type-option"><span class="tom-type-chip tom-type-chip--${value}"></span><span>${label}</span></div>`;
        },
    });

    const bindElement = (element) => {
        if (!(element instanceof HTMLSelectElement)) {
            return;
        }
        const tom = element.tomselect;
        if (tom) {
            const wrapperConnected = Boolean(tom.wrapper?.isConnected);
            const controlConnected = Boolean(tom.control?.isConnected);
            if (wrapperConnected && controlConnected) {
                return;
            }

            try {
                tom.destroy();
            } catch (_error) {
                // Ignore stale instance destroy errors.
            }

            delete element.tomselect;
            delete element.dataset.tomSelectBound;
        }

        if (element.dataset.tomSelectBound === '1' && !element.tomselect) {
            delete element.dataset.tomSelectBound;
        }

        if (element.dataset.tomSelectBound === '1') {
            return;
        }

        const noSearch = element.dataset.tomNoSearch === '1';
        const visual = element.dataset.tomVisual || '';
        const placeholder = element.getAttribute('placeholder') || element.dataset.tomPlaceholder || '';
        const maxItemsRaw = element.dataset.tomMaxItems;
        const maxItems = maxItemsRaw ? Number.parseInt(maxItemsRaw, 10) : (element.multiple ? null : 1);

        const plugins = [];
        if (!noSearch) {
            plugins.push('dropdown_input');
        }
        if (element.multiple) {
            plugins.push('remove_button');
        }

        const config = {
            allowEmptyOption: true,
            maxItems: Number.isNaN(maxItems) ? (element.multiple ? null : 1) : maxItems,
            create: false,
            plugins,
            controlInput: noSearch ? null : undefined,
            searchField: noSearch ? [] : ['text'],
            sortField: [{ field: 'text', direction: 'asc' }],
            placeholder,
            onChange() {
                element.dispatchEvent(new Event('change', { bubbles: true }));
            },
        };

        if (visual === 'block-type') {
            config.render = buildTypeRender();
        }

        element.dataset.tomSelectBound = '1';
        // eslint-disable-next-line no-new
        new TomSelect(element, config);
    };

    const selectSelector = 'select[data-tom-select], select.admin-select:not([multiple])';

    const bindAll = (root) => {
        if (!root) {
            return;
        }
        if (root instanceof HTMLSelectElement && root.matches(selectSelector)) {
            bindElement(root);
        }
        root.querySelectorAll?.(selectSelector).forEach(bindElement);
    };

    bindAll(document);

    if (!window.__tomSelectObserver) {
        window.__tomSelectObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.target instanceof HTMLElement || mutation.target instanceof HTMLSelectElement) {
                    bindAll(mutation.target);
                }

                mutation.addedNodes.forEach((node) => {
                    if (node instanceof HTMLElement || node instanceof HTMLSelectElement) {
                        bindAll(node);
                    }
                });

                if (mutation.removedNodes.length > 0 && mutation.target instanceof HTMLElement) {
                    bindAll(mutation.target);
                }
            });
        });
    }

    if (window.__tomSelectObserverBody !== document.body) {
        window.__tomSelectObserver.disconnect();
        window.__tomSelectObserver.observe(document.body, {
            childList: true,
            subtree: true,
        });
        window.__tomSelectObserverBody = document.body;
    }
};

const initQuillEditors = () => {
    if (!document.body) {
        return;
    }

    const selector = 'textarea[data-quill-editor]';

    const normalizeHtml = (html) => {
        const value = String(html ?? '').trim();
        return value === '<p><br></p>' ? '' : value;
    };

    const stripInlineStyles = (html) => {
        const source = String(html ?? '').trim();
        if (source === '') {
            return '';
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(source, 'text/html');
        doc.body.querySelectorAll('[style]').forEach((node) => node.removeAttribute('style'));

        return normalizeHtml(doc.body.innerHTML || '');
    };

    const readTextareaHtml = (textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return '';
        }

        const fromValue = stripInlineStyles(normalizeHtml(textarea.value));
        if (fromValue !== '') {
            return fromValue;
        }

        // Livewire can occasionally hydrate <textarea> content without dispatching input/change.
        return stripInlineStyles(normalizeHtml(textarea.textContent ?? ''));
    };

    const bindElement = (textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }
        const existingQuillWrapper = textarea.nextElementSibling instanceof HTMLElement
            && textarea.nextElementSibling.classList.contains('admin-quill')
            ? textarea.nextElementSibling
            : null;

        // Livewire can remove injected Quill wrapper during morphs.
        // If that happened, clear the bound flag so editor can be mounted again.
        if (textarea.dataset.quillBound === '1' && !existingQuillWrapper) {
            delete textarea.dataset.quillBound;
            textarea.style.display = '';
            textarea.removeAttribute('aria-hidden');
            textarea.tabIndex = 0;
        }

        // If this field was previously mounted with Ace, tear it down and restore textarea.
        const staleAce = textarea.nextElementSibling;
        if (staleAce instanceof HTMLElement && staleAce.classList.contains('admin-ace-inline')) {
            staleAce.remove();
            textarea.style.display = '';
            textarea.removeAttribute('aria-hidden');
            textarea.tabIndex = 0;
            delete textarea.dataset.aceInlineBound;
        }
        if (textarea.dataset.quillBound === '1') {
            return;
        }
        textarea.dataset.quillBound = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'admin-quill';

        const editorRoot = document.createElement('div');
        editorRoot.className = 'admin-quill-editor';
        wrapper.appendChild(editorRoot);

        textarea.insertAdjacentElement('afterend', wrapper);
        textarea.style.display = 'none';
        textarea.setAttribute('aria-hidden', 'true');
        textarea.tabIndex = -1;

        let quill = null;
        try {
            quill = new Quill(editorRoot, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'link'],
                        ['clean'],
                    ],
                },
                placeholder: textarea.getAttribute('placeholder') || '',
            });
        } catch (error) {
            console.error('Failed to initialize Quill editor', error);
            wrapper.remove();
            textarea.style.display = '';
            textarea.removeAttribute('aria-hidden');
            textarea.tabIndex = 0;
            window.dispatchEvent(new CustomEvent('admin:notify', {
                detail: { type: 'danger', message: 'WYSIWYG editor failed to load.' },
            }));
            return;
        }

        // Paste sanitization: remove inline style attributes from incoming HTML.
        quill.clipboard.addMatcher(Node.ELEMENT_NODE, (node, delta) => {
            if (node instanceof HTMLElement && node.hasAttribute('style')) {
                node.removeAttribute('style');
            }
            return delta;
        });

        if (!Array.isArray(window.__adminQuillEditors)) {
            window.__adminQuillEditors = [];
        }
        window.__adminQuillEditors.push(quill);
        window.__activeAdminQuill = quill;
        quill.__lastRange = null;

        quill.on('selection-change', (range) => {
            if (range) {
                window.__activeAdminQuill = quill;
                quill.__lastRange = range;
            }
        });

        const rows = Number.parseInt(textarea.getAttribute('rows') || '8', 10);
        const minHeight = Number.isNaN(rows) ? 220 : Math.max(180, rows * 26);
        const editorNode = editorRoot.querySelector('.ql-editor');
        if (editorNode instanceof HTMLElement) {
            editorNode.style.minHeight = `${minHeight}px`;
        }

        const initial = readTextareaHtml(textarea);
        if (initial) {
            quill.clipboard.dangerouslyPasteHTML(initial);
        } else {
            quill.setText('');
        }

        let syncingFromQuill = false;
        let syncingFromTextarea = false;

        const syncTextareaFromQuill = () => {
            if (syncingFromTextarea) {
                return;
            }
            const html = stripInlineStyles(normalizeHtml(quill.root.innerHTML));
            if (normalizeHtml(textarea.value) === html) {
                return;
            }
            syncingFromQuill = true;
            textarea.value = html;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
            syncingFromQuill = false;
        };

        const syncQuillFromTextarea = () => {
            if (syncingFromQuill) {
                return;
            }
            const source = stripInlineStyles(readTextareaHtml(textarea));
            const current = normalizeHtml(quill.root.innerHTML);
            if (source === current) {
                return;
            }
            syncingFromTextarea = true;
            if (source) {
                quill.clipboard.dangerouslyPasteHTML(source);
            } else {
                quill.setText('');
            }
            syncingFromTextarea = false;
        };

        quill.on('text-change', syncTextareaFromQuill);
        textarea.addEventListener('input', syncQuillFromTextarea);
        textarea.addEventListener('change', syncQuillFromTextarea);

        // Keep editor in sync when the textarea is patched by Livewire without input events.
        const valueObserver = new MutationObserver(() => {
            syncQuillFromTextarea();
        });
        valueObserver.observe(textarea, {
            childList: true,
            characterData: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['value'],
        });

        // Hydration can complete just after Quill mount, so do a delayed pull as well.
        setTimeout(syncQuillFromTextarea, 0);
        setTimeout(syncQuillFromTextarea, 200);
    };

    const bindAll = (root) => {
        if (!root) {
            return;
        }
        if (root instanceof HTMLTextAreaElement && root.matches(selector)) {
            bindElement(root);
        }
        root.querySelectorAll?.(selector).forEach(bindElement);
    };

    bindAll(document);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement || node instanceof HTMLTextAreaElement) {
                    bindAll(node);
                }
            });

            if (mutation.removedNodes.length > 0 && mutation.target instanceof HTMLElement) {
                bindAll(mutation.target);
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });

    if (window.__adminQuillSmartLinkBound !== '1') {
        window.__adminQuillSmartLinkBound = '1';
        window.addEventListener('admin-quill-insert-link', (event) => {
            const detail = event?.detail || {};
            const url = String(detail.url || '').trim();
            const label = String(detail.label || '').trim();
            if (!url) {
                return;
            }

            let quill = window.__activeAdminQuill || null;
            if (!quill && Array.isArray(window.__adminQuillEditors) && window.__adminQuillEditors.length > 0) {
                quill = window.__adminQuillEditors[0];
            }
            if (!quill) {
                return;
            }

            let range = quill.getSelection();
            if (!range && quill.__lastRange) {
                range = quill.__lastRange;
                quill.setSelection(range.index, range.length || 0, 'silent');
            }
            if (!range) {
                return;
            }

            if (range.length > 0) {
                quill.formatText(range.index, range.length, 'link', url, 'user');
                quill.setSelection(range.index + range.length, 0, 'silent');
                return;
            }

            const text = label !== '' ? label : url;
            quill.insertText(range.index, text, 'link', url, 'user');
            quill.setSelection(range.index + text.length, 0, 'silent');
        });
    }
};

const initMediaImageEditor = () => {
    if (!document.body || document.body.dataset.mediaImageEditorReady === '1') {
        return;
    }

    const currentPath = window.location?.pathname || '';
    const isAdminPath = currentPath === '/admin' || currentPath.startsWith('/admin/');
    if (!isAdminPath) {
        return;
    }

    document.body.dataset.mediaImageEditorReady = '1';

    const clamp = (value, min = 0, max = 100) => Math.max(min, Math.min(max, value));
    const toFixed = (value) => Number.parseFloat(String(value || 0)).toFixed(1);

    const createModal = () => {
        const existing = document.getElementById('admin-image-edit-overlay');
        if (existing instanceof HTMLElement) {
            return existing;
        }

        const overlay = document.createElement('div');
        overlay.id = 'admin-image-edit-overlay';
        overlay.className = 'admin-image-edit-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <div class="admin-image-edit-modal" role="dialog" aria-modal="true" aria-labelledby="admin-image-edit-title">
                <div class="admin-image-edit-header">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Image Edit</p>
                        <h2 id="admin-image-edit-title" class="mt-1 text-base font-semibold tracking-tight text-slate-900">Crop & Focus</h2>
                    </div>
                    <button type="button" id="admin-image-edit-close" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">Close</button>
                </div>
                <div class="admin-image-edit-body">
                    <div class="admin-image-edit-canvas-wrap">
                        <div class="admin-image-edit-canvas" id="admin-image-edit-canvas"></div>
                        <span class="admin-image-focal-dot" id="admin-image-focal-dot" aria-hidden="true"></span>
                    </div>
                    <div class="admin-image-edit-side">
                        <label class="admin-switch">
                            <input type="checkbox" id="admin-image-edit-crop-enabled" />
                            <span class="admin-switch-slider" aria-hidden="true"></span>
                            <span>Use crop box</span>
                        </label>
                        <div class="admin-image-edit-meta">
                            <div class="admin-image-meta-row"><span>Focal X</span><strong id="admin-image-meta-focal-x">50.0%</strong></div>
                            <div class="admin-image-meta-row"><span>Focal Y</span><strong id="admin-image-meta-focal-y">50.0%</strong></div>
                            <div class="admin-image-meta-row"><span>Crop X</span><strong id="admin-image-meta-crop-x">0.0%</strong></div>
                            <div class="admin-image-meta-row"><span>Crop Y</span><strong id="admin-image-meta-crop-y">0.0%</strong></div>
                            <div class="admin-image-meta-row"><span>Crop W</span><strong id="admin-image-meta-crop-w">100.0%</strong></div>
                            <div class="admin-image-meta-row"><span>Crop H</span><strong id="admin-image-meta-crop-h">100.0%</strong></div>
                        </div>
                        <p class="text-xs text-slate-500">Tip: drag the crop box to frame conversion area. Click image to set focus point.</p>
                    </div>
                </div>
                <div class="admin-image-edit-footer">
                    <button type="button" id="admin-image-edit-reset" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Reset</button>
                    <button type="button" id="admin-image-edit-cancel" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</button>
                    <button type="button" id="admin-image-edit-apply" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Apply</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        return overlay;
    };

    const overlay = createModal();
    const closeButton = document.getElementById('admin-image-edit-close');
    const cancelButton = document.getElementById('admin-image-edit-cancel');
    const resetButton = document.getElementById('admin-image-edit-reset');
    const applyButton = document.getElementById('admin-image-edit-apply');
    const canvasHost = document.getElementById('admin-image-edit-canvas');
    const canvasWrap = canvasHost?.closest('.admin-image-edit-canvas-wrap');
    const focalDot = document.getElementById('admin-image-focal-dot');
    const cropEnabledInput = document.getElementById('admin-image-edit-crop-enabled');

    const metaFocalX = document.getElementById('admin-image-meta-focal-x');
    const metaFocalY = document.getElementById('admin-image-meta-focal-y');
    const metaCropX = document.getElementById('admin-image-meta-crop-x');
    const metaCropY = document.getElementById('admin-image-meta-crop-y');
    const metaCropW = document.getElementById('admin-image-meta-crop-w');
    const metaCropH = document.getElementById('admin-image-meta-crop-h');

    if (
        !(overlay instanceof HTMLElement) ||
        !(closeButton instanceof HTMLButtonElement) ||
        !(cancelButton instanceof HTMLButtonElement) ||
        !(resetButton instanceof HTMLButtonElement) ||
        !(applyButton instanceof HTMLButtonElement) ||
        !(canvasHost instanceof HTMLElement) ||
        !(canvasWrap instanceof HTMLElement) ||
        !(focalDot instanceof HTMLElement) ||
        !(cropEnabledInput instanceof HTMLInputElement) ||
        !(metaFocalX instanceof HTMLElement) ||
        !(metaFocalY instanceof HTMLElement) ||
        !(metaCropX instanceof HTMLElement) ||
        !(metaCropY instanceof HTMLElement) ||
        !(metaCropW instanceof HTMLElement) ||
        !(metaCropH instanceof HTMLElement)
    ) {
        return;
    }

    /** @type {Cropper|null} */
    let cropper = null;
    let openState = null;
    let busy = false;

    const parseBool = (value) => {
        const raw = String(value ?? '').toLowerCase();
        return raw === '1' || raw === 'true' || raw === 'yes';
    };

    const updateMeta = (state) => {
        metaFocalX.textContent = `${toFixed(state.focalX)}%`;
        metaFocalY.textContent = `${toFixed(state.focalY)}%`;
        metaCropX.textContent = `${toFixed(state.cropX)}%`;
        metaCropY.textContent = `${toFixed(state.cropY)}%`;
        metaCropW.textContent = `${toFixed(state.cropWidth)}%`;
        metaCropH.textContent = `${toFixed(state.cropHeight)}%`;
    };

    const updateFocalDot = (state) => {
        focalDot.style.left = `${state.focalX}%`;
        focalDot.style.top = `${state.focalY}%`;
    };

    const toNaturalPercent = (state, cropData) => {
        if (!state.imageNaturalWidth || !state.imageNaturalHeight) {
            return null;
        }

        const x = clamp((cropData.x / state.imageNaturalWidth) * 100);
        const y = clamp((cropData.y / state.imageNaturalHeight) * 100);
        const width = clamp((cropData.width / state.imageNaturalWidth) * 100, 1, 100);
        const height = clamp((cropData.height / state.imageNaturalHeight) * 100, 1, 100);

        return { x, y, width, height };
    };

    const setCropDataFromPercent = (state) => {
        if (!cropper || !state.imageNaturalWidth || !state.imageNaturalHeight) {
            return;
        }

        cropper.setData({
            x: (state.cropX / 100) * state.imageNaturalWidth,
            y: (state.cropY / 100) * state.imageNaturalHeight,
            width: (state.cropWidth / 100) * state.imageNaturalWidth,
            height: (state.cropHeight / 100) * state.imageNaturalHeight,
        });
    };

    const syncCropperMode = () => {
        if (!cropper || !openState) {
            return;
        }

        if (openState.cropEnabled) {
            cropper.setDragMode('crop');
            cropper.crop();
            setCropDataFromPercent(openState);
        } else {
            cropper.clear();
            cropper.setDragMode('move');
        }
    };

    const destroyCropper = () => {
        if (!cropper) {
            return;
        }

        cropper.destroy();
        cropper = null;
        canvasHost.innerHTML = '';
    };

    const close = () => {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        destroyCropper();
        openState = null;
        busy = false;
        applyButton.disabled = false;
    };

    const open = (button) => {
        const imageUrl = String(button.dataset.imageUrl || '').trim();
        const mediaId = Number.parseInt(String(button.dataset.mediaId || ''), 10);
        if (!imageUrl || Number.isNaN(mediaId)) {
            return;
        }

        const state = {
            trigger: button,
            mediaId,
            imageUrl,
            wireId: button.closest('[wire\\:id]')?.getAttribute('wire:id') || '',
            focalX: clamp(Number.parseFloat(String(button.dataset.focalX || '50')) || 50),
            focalY: clamp(Number.parseFloat(String(button.dataset.focalY || '50')) || 50),
            cropEnabled: parseBool(button.dataset.cropEnabled),
            cropX: clamp(Number.parseFloat(String(button.dataset.cropX || '0')) || 0),
            cropY: clamp(Number.parseFloat(String(button.dataset.cropY || '0')) || 0),
            cropWidth: clamp(Number.parseFloat(String(button.dataset.cropWidth || '100')) || 100, 1, 100),
            cropHeight: clamp(Number.parseFloat(String(button.dataset.cropHeight || '100')) || 100, 1, 100),
            imageNaturalWidth: 0,
            imageNaturalHeight: 0,
        };

        openState = state;
        cropEnabledInput.checked = state.cropEnabled;
        updateMeta(state);
        updateFocalDot(state);

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        applyButton.disabled = false;
        busy = false;

        destroyCropper();
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = '';
        img.className = 'admin-image-edit-image';
        canvasHost.appendChild(img);

        cropper = new Cropper(img, {
            viewMode: 1,
            autoCropArea: 1,
            background: false,
            responsive: true,
            zoomable: false,
            scalable: false,
            rotatable: false,
            guides: true,
            center: true,
            movable: true,
            ready() {
                if (!openState || !cropper) {
                    return;
                }
                const imageData = cropper.getImageData();
                openState.imageNaturalWidth = imageData.naturalWidth || 0;
                openState.imageNaturalHeight = imageData.naturalHeight || 0;
                syncCropperMode();
            },
            crop(event) {
                if (!openState || !cropper || !openState.cropEnabled) {
                    return;
                }
                const next = toNaturalPercent(openState, event.detail);
                if (!next) {
                    return;
                }

                openState.cropX = next.x;
                openState.cropY = next.y;
                openState.cropWidth = next.width;
                openState.cropHeight = next.height;
                openState.focalX = clamp(next.x + next.width / 2);
                openState.focalY = clamp(next.y + next.height / 2);
                updateMeta(openState);
                updateFocalDot(openState);
            },
        });
    };

    const setFocalFromClick = (event) => {
        if (!openState || !canvasWrap) {
            return;
        }

        const image = canvasHost.querySelector('img');
        if (!(image instanceof HTMLImageElement)) {
            return;
        }

        const rect = image.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) {
            return;
        }

        const clickX = ((event.clientX - rect.left) / rect.width) * 100;
        const clickY = ((event.clientY - rect.top) / rect.height) * 100;
        openState.focalX = clamp(clickX);
        openState.focalY = clamp(clickY);
        updateFocalDot(openState);
        updateMeta(openState);
    };

    const notify = (type, message) => {
        window.dispatchEvent(new CustomEvent('admin:notify', {
            detail: { type, message },
        }));
    };

    const getLivewireComponent = (wireId) => {
        if (!window.Livewire) {
            return null;
        }

        if (wireId && typeof window.Livewire.find === 'function') {
            const found = window.Livewire.find(wireId);
            if (found) {
                return found;
            }
        }

        if (typeof window.Livewire.all === 'function') {
            const all = window.Livewire.all();
            if (Array.isArray(all) && all.length) {
                return all[0];
            }
        }

        return null;
    };

    const apply = async () => {
        if (busy || !openState) {
            return;
        }

        const component = getLivewireComponent(openState.wireId);
        if (!component || typeof component.call !== 'function') {
            notify('danger', 'Livewire component not available.');
            return;
        }

        busy = true;
        applyButton.disabled = true;

        try {
            await component.call('saveImageEditFromModal', openState.mediaId, {
                focal_x: openState.focalX,
                focal_y: openState.focalY,
                crop_enabled: openState.cropEnabled,
                crop_x: openState.cropX,
                crop_y: openState.cropY,
                crop_width: openState.cropWidth,
                crop_height: openState.cropHeight,
            });
            close();
        } catch (error) {
            console.error('Failed to save image edit', error);
            notify('danger', 'Failed to save crop/focus.');
            busy = false;
            applyButton.disabled = false;
        }
    };

    cropEnabledInput.addEventListener('change', () => {
        if (!openState) {
            return;
        }
        openState.cropEnabled = cropEnabledInput.checked;
        syncCropperMode();
        updateMeta(openState);
    });

    resetButton.addEventListener('click', () => {
        if (!openState) {
            return;
        }
        openState.focalX = 50;
        openState.focalY = 50;
        openState.cropEnabled = false;
        openState.cropX = 0;
        openState.cropY = 0;
        openState.cropWidth = 100;
        openState.cropHeight = 100;
        cropEnabledInput.checked = false;
        syncCropperMode();
        updateFocalDot(openState);
        updateMeta(openState);
    });

    closeButton.addEventListener('click', close);
    cancelButton.addEventListener('click', close);
    applyButton.addEventListener('click', apply);

    canvasWrap.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (
            target === canvasWrap ||
            target === focalDot ||
            target instanceof HTMLImageElement ||
            target.closest('.cropper-container')
        ) {
            setFocalFromClick(event);
        }
    });

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            close();
        }
    });

    window.addEventListener('keydown', (event) => {
        if (!overlay.classList.contains('is-open')) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const button = target.closest('[data-image-edit-open]');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }
        event.preventDefault();
        open(button);
    });
};

const initAceInline = () => {
    if (!document.body) {
        return;
    }

    const selector = 'textarea[data-ace-inline]';

    const readTextareaValue = (textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return '';
        }

        if ((textarea.value ?? '') !== '') {
            return textarea.value;
        }

        // Livewire can patch textarea contents without firing input/change.
        return textarea.textContent || '';
    };

    const bindElement = (textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }
        if (textarea.dataset.aceInlineBound === '1') {
            return;
        }

        textarea.dataset.aceInlineBound = '1';

        const mount = document.createElement('div');
        mount.className = 'admin-ace-inline';
        const rows = Number.parseInt(textarea.getAttribute('rows') || '8', 10);
        const minHeight = Number.isNaN(rows) ? 220 : Math.max(180, rows * 26);
        mount.style.minHeight = `${minHeight}px`;
        textarea.insertAdjacentElement('afterend', mount);
        textarea.style.display = 'none';
        textarea.setAttribute('aria-hidden', 'true');
        textarea.tabIndex = -1;

        let editor = null;
        let syncTimer = null;
        let syncingFromEditor = false;
        let syncingFromTextarea = false;

        const syncTextareaFromEditor = () => {
            if (!editor || syncingFromTextarea) {
                return;
            }

            const value = editor.getValue();
            if (textarea.value === value) {
                return;
            }

            syncingFromEditor = true;
            textarea.value = value;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
            syncingFromEditor = false;
        };

        const scheduleSyncTextarea = () => {
            if (syncTimer) {
                clearTimeout(syncTimer);
            }
            syncTimer = setTimeout(syncTextareaFromEditor, 120);
        };

        const syncEditorFromTextarea = () => {
            if (!editor || syncingFromEditor) {
                return;
            }

            const value = readTextareaValue(textarea);
            if (editor.getValue() === value) {
                return;
            }

            syncingFromTextarea = true;
            editor.setValue(value, -1);
            syncingFromTextarea = false;
        };

        textarea.addEventListener('input', syncEditorFromTextarea);
        textarea.addEventListener('change', syncEditorFromTextarea);

        const valueObserver = new MutationObserver(() => {
            syncEditorFromTextarea();
        });
        valueObserver.observe(textarea, {
            childList: true,
            characterData: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['value'],
        });

        loadAce()
            .then((ace) => {
                editor = ace.edit(mount);
                editor.session.setMode('ace/mode/html');
                editor.setTheme('ace/theme/tomorrow_night');
                editor.setOptions({
                    fontSize: '13px',
                    showPrintMargin: false,
                    useSoftTabs: true,
                    tabSize: 2,
                    enableBasicAutocompletion: true,
                    enableLiveAutocompletion: true,
                });
                editor.session.setUseWorker(false);
                editor.setValue(readTextareaValue(textarea), -1);
                editor.session.on('change', scheduleSyncTextarea);
                editor.on('blur', syncTextareaFromEditor);

                // Hydration can finish right after mount.
                setTimeout(syncEditorFromTextarea, 0);
                setTimeout(syncEditorFromTextarea, 200);
            })
            .catch((error) => {
                console.error('Failed to initialize inline Ace editor', error);
                mount.remove();
                textarea.style.display = '';
                textarea.removeAttribute('aria-hidden');
                textarea.tabIndex = 0;

                if (!aceInlineFailureNotified) {
                    aceInlineFailureNotified = true;
                    window.dispatchEvent(new CustomEvent('admin:notify', {
                        detail: { type: 'danger', message: 'Inline Ace editor failed to load.' },
                    }));
                }
            });
    };

    const bindAll = (root) => {
        if (!root) {
            return;
        }
        if (root instanceof HTMLTextAreaElement && root.matches(selector)) {
            bindElement(root);
        }
        root.querySelectorAll?.(selector).forEach(bindElement);
    };

    bindAll(document);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement || node instanceof HTMLTextAreaElement) {
                    bindAll(node);
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
};

const initDashboardCharts = () => {
    if (!document.body || document.body.dataset.dashboardChartsReady === '1') {
        return;
    }
    document.body.dataset.dashboardChartsReady = '1';

    const selector = 'canvas[data-dashboard-chart]';
    const instances = new WeakMap();

    const parseConfig = (canvas) => {
        const raw = canvas.getAttribute('data-chart-payload');
        if (!raw) {
            return null;
        }

        try {
            const config = JSON.parse(raw);
            if (!config || typeof config !== 'object') {
                return null;
            }

            return config;
        } catch (error) {
            return null;
        }
    };

    const destroyChart = (canvas) => {
        if (!(canvas instanceof HTMLCanvasElement)) {
            return;
        }

        const chart = instances.get(canvas);
        if (!chart) {
            return;
        }

        chart.destroy();
        instances.delete(canvas);
    };

    const bindCanvas = (canvas) => {
        if (!(canvas instanceof HTMLCanvasElement)) {
            return;
        }

        const config = parseConfig(canvas);
        if (!config) {
            destroyChart(canvas);
            return;
        }

        destroyChart(canvas);

        const context = canvas.getContext('2d');
        if (!context) {
            return;
        }

        try {
            const chart = new Chart(context, {
                type: config.type || 'line',
                data: config.data || { labels: [], datasets: [] },
                options: config.options || {},
            });
            instances.set(canvas, chart);
        } catch (error) {
            console.error('Failed to render dashboard chart', error);
        }
    };

    const bindAll = (root) => {
        if (!root) {
            return;
        }
        if (root instanceof HTMLCanvasElement && root.matches(selector)) {
            bindCanvas(root);
        }
        root.querySelectorAll?.(selector).forEach(bindCanvas);
    };

    const destroyFromNode = (node) => {
        if (!node) {
            return;
        }
        if (node instanceof HTMLCanvasElement && node.matches(selector)) {
            destroyChart(node);
        }
        if (node instanceof HTMLElement) {
            node.querySelectorAll(selector).forEach(destroyChart);
        }
    };

    bindAll(document);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.target instanceof HTMLCanvasElement) {
                bindCanvas(mutation.target);
                return;
            }

            mutation.removedNodes.forEach(destroyFromNode);
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement || node instanceof HTMLCanvasElement) {
                    bindAll(node);
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-chart-payload'],
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initAceLauncher();
        initAceInline();
        initQuillEditors();
        initMediaImageEditor();
        initTomSelect();
        initDashboardCharts();
    }, { once: true });
} else {
    initAceLauncher();
    initAceInline();
    initQuillEditors();
    initMediaImageEditor();
    initTomSelect();
    initDashboardCharts();
}

document.addEventListener('livewire:navigated', () => {
    initTomSelect();
});
