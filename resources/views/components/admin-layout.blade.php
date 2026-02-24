@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' | '.config('app.name') : config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            summary::-webkit-details-marker { display: none; }
            summary::marker { content: ""; }
            .admin-sidebar .sidebar-dot {
                width: 0.35rem;
                height: 0.35rem;
                border-radius: 9999px;
                background: currentColor;
                opacity: 0.72;
                flex-shrink: 0;
            }
            .admin-sidebar .sidebar-branch {
                display: inline-flex;
                width: 0.5rem;
                justify-content: center;
                font-size: 0.72rem;
                line-height: 1;
                opacity: 0.78;
                flex-shrink: 0;
            }
            .admin-sidebar details > summary {
                line-height: 1.15;
            }
            .admin-sidebar .sidebar-dropdown-summary {
                padding: 0.45rem 0.55rem;
                font-size: 0.79rem;
            }
            .admin-sidebar .sidebar-dropdown-link {
                padding: 0.35rem 0.5rem;
                font-size: 0.75rem;
            }
            .admin-sidebar .sidebar-dropdown-link.is-active-leaf {
                border-left: 2px solid #cbd5e1;
                padding-left: calc(0.5rem - 2px);
                background: #f3f6f8;
                color: #334155;
                font-weight: 600;
            }
            .admin-panel {
                border: 1px solid #dbe4ee;
                border-radius: 1rem;
                background: #ffffff;
                box-shadow: 0 10px 24px -18px rgba(15, 23, 42, 0.48);
            }
            .admin-panel-soft {
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            }
            .admin-search-panel {
                background: radial-gradient(circle at top right, #f1f5f9 0%, #ffffff 40%);
            }
            .admin-search-panel code,
            .admin-chip {
                display: inline-flex;
                align-items: center;
                padding: 0.2rem 0.45rem;
                border: 1px solid #e2e8f0;
                border-radius: 9999px;
                background: #f8fafc;
                color: #334155;
                font-size: 0.74rem;
                font-weight: 600;
            }
            .admin-search-input {
                border-color: #cbd5e1;
                background: #ffffff;
                transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
            }
            .admin-search-input:focus {
                border-color: #0891b2;
                box-shadow: 0 0 0 2px rgba(8, 145, 178, 0.14);
                background: #f8fcff;
                outline: none;
            }
            .admin-section-title {
                font-size: 0.72rem;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #64748b;
                font-weight: 700;
            }
            .admin-stack {
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
            }
            .admin-form-panel {
                position: relative;
                border-color: #dbe4ee;
                background: linear-gradient(180deg, #f8fbff 0%, #ffffff 22%);
            }
            .admin-form-panel::before {
                content: "";
                position: absolute;
                top: 0;
                left: 1.25rem;
                right: 1.25rem;
                height: 2px;
                border-radius: 9999px;
                background: linear-gradient(90deg, #64748b 0%, #94a3b8 55%, #cbd5e1 100%);
                opacity: 0.45;
            }
            .admin-form > div {
                padding: 0.7rem 0.85rem;
                border-radius: 0.75rem;
                background: rgba(248, 250, 252, 0.84);
            }
            .admin-form :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea) {
                background: #ffffff;
                border-color: #cbd5e1;
                transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
            }
            .admin-form :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea):focus {
                border-color: #0891b2;
                box-shadow: 0 0 0 2px rgba(8, 145, 178, 0.14);
                background: #f8fcff;
                outline: none;
            }
            .admin-form input[type="checkbox"],
            .admin-form input[type="radio"] {
                accent-color: #0e7490;
            }
            .admin-select {
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%2364758b' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 0.6rem center;
                background-size: 1rem 1rem;
                padding-right: 2rem;
            }
            .admin-form select:not([multiple]),
            .admin-search-panel select {
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%2364758b' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 0.6rem center;
                background-size: 1rem 1rem;
                padding-right: 2rem;
            }
            .admin-form select[multiple] {
                background-image: none;
                padding-right: 0.65rem;
            }
            .admin-form .ts-wrapper,
            .admin-form-panel .ts-wrapper,
            .admin-search-panel .ts-wrapper {
                width: 100%;
                border: 0 !important;
                background: transparent !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .admin-form .ts-wrapper .ts-control,
            .admin-form-panel .ts-wrapper .ts-control,
            .admin-search-panel .ts-wrapper .ts-control {
                border-color: #cbd5e1;
                border-radius: 0.75rem;
                min-height: 2.45rem;
                background: #ffffff;
                box-shadow: none;
                padding: 0.45rem 0.65rem;
                font-size: 0.875rem;
                color: #0f172a;
            }
            .admin-form .ts-wrapper.focus .ts-control,
            .admin-form-panel .ts-wrapper.focus .ts-control,
            .admin-search-panel .ts-wrapper.focus .ts-control {
                border-color: #0891b2;
                box-shadow: 0 0 0 2px rgba(8, 145, 178, 0.14);
                background: #f8fcff;
            }
            .admin-form .ts-wrapper.single .ts-control .item,
            .admin-form-panel .ts-wrapper.single .ts-control .item,
            .admin-search-panel .ts-wrapper.single .ts-control .item {
                color: #0f172a;
            }
            .admin-form .ts-wrapper .ts-control input,
            .admin-form-panel .ts-wrapper .ts-control input,
            .admin-search-panel .ts-wrapper .ts-control input {
                border: 0 !important;
                box-shadow: none !important;
                background: transparent !important;
                padding: 0 !important;
            }
            .ts-dropdown {
                border: 1px solid #cbd5e1;
                border-radius: 0.75rem;
                overflow: hidden;
                box-shadow: 0 12px 24px -16px rgba(15, 23, 42, 0.5);
            }
            .ts-dropdown .option,
            .ts-dropdown .create {
                padding: 0.5rem 0.65rem;
                font-size: 0.84rem;
                color: #334155;
            }
            .ts-dropdown .active {
                background: #f0f9ff;
                color: #0f172a;
            }
            .tom-type-option {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                width: 100%;
            }
            .tom-type-chip {
                width: 1.55rem;
                height: 0.92rem;
                border-radius: 0.35rem;
                border: 1px solid #cbd5e1;
                background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
                flex-shrink: 0;
            }
            .tom-type-chip--hero_single,
            .tom-type-chip--hero_main {
                background: linear-gradient(90deg, #c7d2fe 0%, #e2e8f0 100%);
            }
            .tom-type-chip--hero_slider {
                background: repeating-linear-gradient(90deg, #e2e8f0 0 25%, #cbd5e1 25% 50%);
            }
            .tom-type-chip--products_carousel {
                background: repeating-linear-gradient(90deg, #bbf7d0 0 20%, #d1fae5 20% 40%);
            }
            .tom-type-chip--blog_grid_3 {
                background: repeating-linear-gradient(90deg, #fde68a 0 20%, #fef3c7 20% 40%);
            }
            .tom-type-chip--cards_2,
            .tom-type-chip--cards_3 {
                background: repeating-linear-gradient(90deg, #bae6fd 0 33%, #e0f2fe 33% 66%);
            }
            .tom-type-chip--split_message {
                background: linear-gradient(90deg, #e2e8f0 0 50%, #cbd5e1 50% 100%);
            }
            .tom-type-chip--rich_text {
                background: linear-gradient(180deg, #f1f5f9 0%, #cbd5e1 100%);
            }
            .tom-type-chip--cta_banner {
                background: linear-gradient(90deg, #fecdd3 0%, #fda4af 100%);
            }
            .tom-type-chip--dev_polishing {
                background: linear-gradient(90deg, #d9f99d 0%, #bef264 100%);
            }
            .admin-multiselect {
                min-height: 12rem;
                padding: 0.55rem 0.65rem;
                background-image: none;
            }
            .admin-multiselect option {
                padding: 0.3rem 0.45rem;
                border-radius: 0.4rem;
            }
            .admin-multiselect option:checked {
                background: #dbeafe linear-gradient(0deg, #dbeafe 0%, #dbeafe 100%);
                color: #0f172a;
            }
            .admin-switch {
                display: inline-flex;
                align-items: center;
                gap: 0.65rem;
                border: 1px solid #cbd5e1;
                border-radius: 9999px;
                padding: 0.25rem 0.42rem 0.25rem 0.25rem;
                background: #f8fafc;
                cursor: pointer;
                transition: border-color 120ms ease, background-color 120ms ease;
            }
            .admin-switch:hover {
                border-color: #94a3b8;
                background: #f1f5f9;
            }
            .admin-switch-track {
                position: relative;
                width: 2.2rem;
                height: 1.2rem;
                border-radius: 9999px;
                border: 1px solid #94a3b8;
                background: #cbd5e1;
                transition: background-color 140ms ease, border-color 140ms ease;
            }
            .admin-switch-thumb {
                position: absolute;
                top: 50%;
                left: 0.1rem;
                width: 0.9rem;
                height: 0.9rem;
                border-radius: 9999px;
                background: #ffffff;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.25);
                transform: translate(0, -50%);
                transition: transform 140ms ease;
            }
            .admin-switch[data-state="on"] .admin-switch-track {
                background: #22c55e;
                border-color: #16a34a;
            }
            .admin-switch[data-state="on"] .admin-switch-thumb {
                transform: translate(1rem, -50%);
            }
            .admin-switch-label {
                min-width: 2.4rem;
                text-align: center;
                font-size: 0.68rem;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                font-weight: 700;
                color: #64748b;
            }
            .admin-switch[data-state="on"] .admin-switch-label {
                color: #166534;
            }
            .admin-form .admin-form-actions {
                margin-top: 0.35rem;
                padding-top: 0.85rem;
                border-top: 1px solid #e2e8f0;
                background: transparent;
            }
            .admin-items-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                border: 1px solid #e2e8f0;
                border-radius: 0.9rem;
                overflow: hidden;
            }
            .admin-items-table thead {
                background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            }
            .admin-items-table tbody tr {
                transition: background-color 120ms ease;
            }
            .admin-items-table tbody tr:hover {
                background: #f8fbff;
            }
            .admin-items-table th {
                font-size: 0.72rem;
                letter-spacing: 0.06em;
                text-transform: uppercase;
            }
            .admin-items-table th + th,
            .admin-items-table td + td {
                border-left: 1px solid #e2e8f0;
            }
            .admin-main {
                min-height: 100vh;
            }
            .admin-sidebar-rail {
                height: 100dvh;
                min-height: 100vh;
            }
            @media (min-width: 768px) {
                .admin-main {
                    margin-left: 18rem;
                    width: calc(100% - 18rem);
                }
            }
            .admin-toast-root {
                position: fixed;
                top: 5.2rem;
                right: 1rem;
                z-index: 70;
                display: flex;
                width: min(24rem, calc(100vw - 2rem));
                flex-direction: column;
                gap: 0.6rem;
                pointer-events: none;
            }
            .admin-toast {
                display: grid;
                grid-template-columns: auto 1fr auto;
                align-items: center;
                gap: 0.65rem;
                border: 1px solid #cbd5e1;
                border-left-width: 3px;
                border-radius: 0.85rem;
                padding: 0.72rem 0.72rem 0.72rem 0.78rem;
                background: #ffffff;
                color: #334155;
                box-shadow: 0 14px 28px -22px rgba(15, 23, 42, 0.65);
                pointer-events: auto;
                transition: opacity 160ms ease, transform 160ms ease;
                opacity: 0;
                transform: translateY(-12px);
            }
            .admin-toast.is-visible {
                opacity: 1;
                transform: translateY(0);
            }
            .admin-toast[data-type="success"] {
                border-color: #bbf7d0;
                border-left-color: #16a34a;
                background: #f0fdf4;
                color: #166534;
            }
            .admin-toast[data-type="warning"] {
                border-color: #fde68a;
                border-left-color: #f59e0b;
                background: #fffbeb;
                color: #92400e;
            }
            .admin-toast[data-type="danger"] {
                border-color: #fecaca;
                border-left-color: #dc2626;
                background: #fef2f2;
                color: #991b1b;
            }
            .admin-toast[data-type="info"] {
                border-color: #bae6fd;
                border-left-color: #0284c7;
                background: #f0f9ff;
                color: #0c4a6e;
            }
            .admin-toast-icon {
                display: inline-flex;
                height: 1.3rem;
                width: 1.3rem;
                align-items: center;
                justify-content: center;
                border-radius: 9999px;
                background: #f1f5f9;
                font-size: 0.72rem;
                font-weight: 700;
                color: #334155;
            }
            .admin-toast[data-type="success"] .admin-toast-icon { background: #dcfce7; color: #166534; }
            .admin-toast[data-type="warning"] .admin-toast-icon { background: #fef3c7; color: #92400e; }
            .admin-toast[data-type="danger"] .admin-toast-icon { background: #fee2e2; color: #991b1b; }
            .admin-toast[data-type="info"] .admin-toast-icon { background: #e0f2fe; color: #0c4a6e; }
            .admin-toast-message {
                font-size: 0.82rem;
                line-height: 1.3;
                color: inherit;
            }
            .admin-toast-close {
                border: 0;
                background: transparent;
                color: inherit;
                font-size: 1rem;
                line-height: 1;
                cursor: pointer;
                padding: 0 0.1rem;
                opacity: 0.7;
            }
            .admin-toast-close:hover { opacity: 1; }
            .admin-help-button {
                display: inline-flex;
                height: 2rem;
                width: 2rem;
                align-items: center;
                justify-content: center;
                border-radius: 9999px;
                border: 1px solid #dbe4ee;
                background: #ffffff;
                color: #475569;
                font-size: 0.8rem;
                font-weight: 700;
                transition: background-color 120ms ease, border-color 120ms ease;
            }
            .admin-help-button:hover {
                background: #f8fafc;
                border-color: #cbd5e1;
            }
            .admin-ai-button {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                border-radius: 9999px;
                border: 1px solid #cbd5e1;
                background: #f8fafc;
                color: #334155;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                padding: 0.35rem 0.6rem;
                transition: border-color 120ms ease, background-color 120ms ease;
            }
            .admin-ai-button:hover {
                border-color: #94a3b8;
                background: #f1f5f9;
            }
            .admin-help-overlay {
                position: fixed;
                inset: 0;
                z-index: 80;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                background: rgba(15, 23, 42, 0.38);
            }
            .admin-help-overlay.is-open {
                display: flex;
            }
            .admin-help-modal {
                width: min(64rem, 100%);
                max-height: calc(100dvh - 2.5rem);
                border-radius: 1rem;
                border: 1px solid #dbe4ee;
                background: #ffffff;
                box-shadow: 0 22px 42px -30px rgba(15, 23, 42, 0.75);
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }
            .admin-help-modal-header {
                padding: 0.9rem 1rem;
                border-bottom: 1px solid #e2e8f0;
                background: linear-gradient(90deg, #f8fafc 0%, #eef7ff 100%);
            }
            .admin-help-modal-body {
                padding: 1rem;
                color: #334155;
                font-size: 0.9rem;
                line-height: 1.45;
                overflow-y: auto;
            }
            .admin-help-sections {
                margin-top: 0.9rem;
                display: flex;
                flex-direction: column;
                gap: 0.85rem;
            }
            .admin-help-section {
                border-bottom: 1px solid #e2e8f0;
                background: #ffffff;
                border-radius: 0.8rem;
                padding: 0.35rem 0.1rem 0.85rem;
            }
            .admin-help-section-title {
                margin: 0 0 0.3rem 0;
                font-size: 0.9rem;
                line-height: 1.35;
                color: #0f172a;
                font-weight: 700;
            }
            .admin-help-section-subtitle {
                margin: 0 0 0.5rem 0;
                font-size: 0.8rem;
                color: #64748b;
            }
            .admin-help-paragraph {
                margin: 0.45rem 0 0;
                font-size: 0.84rem;
                line-height: 1.5;
                color: #334155;
            }
            .admin-help-params {
                margin-top: 0.65rem;
                border: 1px solid #e2e8f0;
                border-radius: 0.7rem;
                overflow: hidden;
                background: #f8fafc;
            }
            .admin-help-param-row {
                display: grid;
                grid-template-columns: minmax(8rem, 11rem) minmax(0, 1fr);
                gap: 0.7rem;
                padding: 0.5rem 0.65rem;
                border-top: 1px solid #e2e8f0;
                font-size: 0.8rem;
            }
            .admin-help-param-row:first-child {
                border-top: none;
            }
            .admin-help-param-key {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-weight: 700;
                color: #0f172a;
            }
            .admin-help-param-value {
                color: #334155;
            }
            .admin-help-list {
                margin-top: 0.6rem;
                display: grid;
                gap: 0.35rem;
                list-style: disc;
                padding-left: 1.2rem;
            }
            .admin-help-list.admin-help-list-flat {
                list-style: none;
                padding: 0;
            }
            .admin-help-list li {
                font-size: 0.82rem;
                color: #334155;
                line-height: 1.45;
            }
            .admin-help-list.admin-help-list-flat li {
                border: 1px solid #e2e8f0;
                background: #f8fafc;
                border-radius: 0.7rem;
                padding: 0.48rem 0.6rem;
            }
            .admin-ai-overlay {
                position: fixed;
                inset: 0;
                z-index: 81;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                background: rgba(15, 23, 42, 0.42);
            }
            .admin-ai-overlay.is-open {
                display: flex;
            }
            .admin-ai-modal {
                width: min(52rem, 100%);
                border-radius: 1rem;
                border: 1px solid #dbe4ee;
                background: #ffffff;
                box-shadow: 0 22px 42px -30px rgba(15, 23, 42, 0.75);
                overflow: hidden;
            }
            .admin-ai-modal-header {
                padding: 0.85rem 1rem;
                border-bottom: 1px solid #e2e8f0;
                background: linear-gradient(90deg, #f8fafc 0%, #eef7ff 100%);
            }
            .admin-ai-modal-body {
                padding: 1rem;
            }
            .admin-ai-input {
                width: 100%;
                min-height: 8.5rem;
                border-radius: 0.85rem;
                border: 1px solid #cbd5e1;
                padding: 0.75rem 0.85rem;
                font-size: 0.9rem;
                line-height: 1.45;
                resize: vertical;
                background: #ffffff;
                color: #0f172a;
            }
            .admin-ai-input:focus {
                border-color: #0891b2;
                box-shadow: 0 0 0 2px rgba(8, 145, 178, 0.14);
                outline: none;
            }
            .admin-ai-hint {
                margin-top: 0.65rem;
                font-size: 0.76rem;
                color: #64748b;
            }
            .cb-preview {
                position: relative;
                border: 1px solid #dbe4ee;
                border-radius: 0.85rem;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                padding: 0.6rem;
                display: flex;
                flex-direction: column;
                gap: 0.35rem;
                overflow: hidden;
            }
            .cb-preview::after {
                content: "";
                position: absolute;
                inset: 0;
                background: linear-gradient(115deg, transparent 20%, rgba(148, 163, 184, 0.16) 45%, transparent 70%);
                transform: translateX(-120%);
                animation: cbShimmer 2.8s linear infinite;
                pointer-events: none;
            }
            @keyframes cbShimmer {
                100% {
                    transform: translateX(120%);
                }
            }
            .cb-preview--xs {
                min-height: 4.7rem;
                padding: 0.45rem;
                border-radius: 0.7rem;
                gap: 0.2rem;
            }
            .cb-preview--sm {
                min-height: 6.3rem;
            }
            .cb-preview--md {
                min-height: 8rem;
            }
            .cb-line,
            .cb-pill,
            .cb-box {
                background: #d1d5db;
                opacity: 0.9;
            }
            .cb-line {
                height: 0.45rem;
                border-radius: 9999px;
            }
            .cb-pill {
                height: 0.85rem;
                border-radius: 9999px;
            }
            .cb-box {
                border-radius: 0.55rem;
            }
            .cb-w-90 { width: 90%; }
            .cb-w-85 { width: 85%; }
            .cb-w-80 { width: 80%; }
            .cb-w-75 { width: 75%; }
            .cb-w-72 { width: 72%; }
            .cb-w-70 { width: 70%; }
            .cb-w-65 { width: 65%; }
            .cb-w-60 { width: 60%; }
            .cb-w-58 { width: 58%; }
            .cb-w-55 { width: 55%; }
            .cb-w-50 { width: 50%; }
            .cb-w-45 { width: 45%; }
            .cb-w-35 { width: 35%; }
            .cb-w-30 { width: 30%; }
            .cb-hero-media {
                width: 100%;
                height: 2.2rem;
            }
            .cb-split {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.45rem;
                align-items: stretch;
            }
            .cb-split-media {
                height: 100%;
                min-height: 3.35rem;
            }
            .cb-cards3 {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 0.35rem;
                flex: 1;
            }
            .cb-card-mini {
                border: 1px solid #e2e8f0;
                border-radius: 0.45rem;
                padding: 0.28rem;
                display: flex;
                flex-direction: column;
                gap: 0.18rem;
                background: #f8fafc;
            }
            .cb-mini-media {
                width: 100%;
                height: 1.25rem;
            }
            .cb-banner {
                margin-top: auto;
                border: 1px solid #cbd5e1;
                border-radius: 0.65rem;
                background: #f1f5f9;
                padding: 0.45rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.4rem;
            }
            .cb-custom {
                width: 100%;
                height: 1.45rem;
                margin-top: auto;
            }
            .admin-ace-overlay {
                position: fixed;
                inset: 0;
                z-index: 85;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                background: rgba(15, 23, 42, 0.45);
            }
            .admin-ace-overlay.is-open {
                display: flex;
            }
            .admin-ace-modal {
                width: min(72rem, 100%);
                height: min(80vh, 52rem);
                border-radius: 1rem;
                border: 1px solid #dbe4ee;
                background: #ffffff;
                box-shadow: 0 22px 42px -30px rgba(15, 23, 42, 0.75);
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .admin-ace-header {
                padding: 0.8rem 1rem;
                border-bottom: 1px solid #e2e8f0;
                background: linear-gradient(90deg, #f8fafc 0%, #eef7ff 100%);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
            }
            .admin-ace-editor {
                flex: 1;
                min-height: 0;
            }
            .admin-ace-footer {
                padding: 0.7rem 1rem;
                border-top: 1px solid #e2e8f0;
                display: flex;
                justify-content: flex-end;
                gap: 0.5rem;
                background: #ffffff;
            }
            .admin-ace-inline {
                width: 100%;
                border: 1px solid #cbd5e1;
                border-radius: 0.75rem;
                overflow: hidden;
                box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
                background: #0f172a;
            }
            .admin-quill .ql-toolbar.ql-snow {
                border: 1px solid #cbd5e1;
                border-bottom: 0;
                border-radius: 0.75rem 0.75rem 0 0;
                background: #f8fafc;
            }
            .admin-quill .ql-container.ql-snow {
                border: 1px solid #cbd5e1;
                border-radius: 0 0 0.75rem 0.75rem;
                background: #ffffff;
                font-size: 0.875rem;
            }
            .admin-quill .ql-editor {
                color: #0f172a;
                line-height: 1.55;
            }
            .admin-quill .ql-editor h1,
            .admin-quill .ql-editor h2,
            .admin-quill .ql-editor h3,
            .admin-quill .ql-editor h4 {
                margin-top: 1.2em;
                margin-bottom: 0.55em;
                line-height: 1.25;
                font-weight: 700;
            }
            .admin-quill .ql-editor h1 { font-size: 1.75rem; }
            .admin-quill .ql-editor h2 { font-size: 1.45rem; }
            .admin-quill .ql-editor h3 { font-size: 1.2rem; }
            .admin-quill .ql-editor h4 { font-size: 1.05rem; }
            .admin-quill .ql-editor p {
                margin-bottom: 0.7em;
            }
            .admin-quill .ql-editor.ql-blank::before {
                color: #94a3b8;
                font-style: normal;
            }
            .admin-image-edit-overlay {
                position: fixed;
                inset: 0;
                z-index: 90;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                background: rgba(15, 23, 42, 0.45);
            }
            .admin-image-edit-overlay.is-open {
                display: flex;
            }
            .admin-image-edit-modal {
                width: min(76rem, 100%);
                max-height: min(88vh, 58rem);
                border-radius: 1rem;
                border: 1px solid #dbe4ee;
                background: #ffffff;
                box-shadow: 0 22px 42px -30px rgba(15, 23, 42, 0.75);
                display: grid;
                grid-template-rows: auto 1fr auto;
                overflow: hidden;
            }
            .admin-image-edit-header {
                padding: 0.8rem 1rem;
                border-bottom: 1px solid #e2e8f0;
                background: linear-gradient(90deg, #f8fafc 0%, #eef7ff 100%);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
            }
            .admin-image-edit-body {
                min-height: 0;
                display: grid;
                grid-template-columns: minmax(0, 1fr) 18rem;
                gap: 1rem;
                padding: 1rem;
                background: #f8fafc;
            }
            .admin-image-edit-canvas-wrap {
                position: relative;
                min-height: 24rem;
                border: 1px solid #d8e2ee;
                border-radius: 0.85rem;
                background: #ffffff;
                overflow: hidden;
            }
            .admin-image-edit-canvas {
                width: 100%;
                height: 100%;
                min-height: 24rem;
            }
            .admin-image-edit-image {
                display: block;
                max-width: 100%;
            }
            .admin-image-edit-side {
                border: 1px solid #d8e2ee;
                border-radius: 0.85rem;
                background: #ffffff;
                padding: 0.85rem;
                display: flex;
                flex-direction: column;
                gap: 0.7rem;
            }
            .admin-image-edit-meta {
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                background: #f8fafc;
                padding: 0.6rem 0.65rem;
                display: grid;
                gap: 0.45rem;
            }
            .admin-image-meta-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
                font-size: 0.72rem;
                color: #475569;
            }
            .admin-image-meta-row strong {
                font-size: 0.75rem;
                font-weight: 700;
                color: #0f172a;
            }
            .admin-image-focal-dot {
                position: absolute;
                width: 0.72rem;
                height: 0.72rem;
                border-radius: 9999px;
                border: 2px solid #ffffff;
                background: #06b6d4;
                box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.28);
                transform: translate(-50%, -50%);
                pointer-events: none;
                z-index: 35;
                left: 50%;
                top: 50%;
            }
            .admin-image-edit-footer {
                padding: 0.75rem 1rem;
                border-top: 1px solid #e2e8f0;
                display: flex;
                justify-content: flex-end;
                gap: 0.5rem;
                background: #ffffff;
            }
            .admin-image-edit-canvas .cropper-container {
                width: 100% !important;
                height: 100% !important;
            }
            .admin-image-edit-canvas .cropper-view-box {
                outline-color: #06b6d4;
                outline-width: 1px;
            }
            .admin-image-edit-canvas .cropper-point,
            .admin-image-edit-canvas .cropper-line {
                background-color: #06b6d4;
            }
            @media (max-width: 1080px) {
                .admin-image-edit-body {
                    grid-template-columns: 1fr;
                }
                .admin-image-edit-canvas-wrap,
                .admin-image-edit-canvas {
                    min-height: 19rem;
                }
            }
        </style>
        @stack('page-styles')
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <div class="min-h-screen">
            <aside class="admin-sidebar admin-sidebar-rail fixed inset-y-0 left-0 z-30 hidden w-72 overflow-y-auto border-r border-slate-200 bg-white md:block">
                <div class="flex h-16 items-center border-b border-slate-200 px-6">
                    <a href="{{ route('admin.dashboard') }}" class="text-lg font-semibold tracking-tight">
                        {{ config('app.name') }} Admin
                    </a>
                </div>

                @php
                    $catalogUseBlog = app(\App\Services\Catalog\CatalogFeatureService::class)->useBlog();
                    $catalogUseApi = app(\App\Services\Catalog\CatalogFeatureService::class)->useApi();
                    $catalogUseAttributes = app(\App\Services\Catalog\CatalogFeatureService::class)->useAttributes();
                    $catalogUseOptions = app(\App\Services\Catalog\CatalogFeatureService::class)->useOptions();
                    $catalogUseManufacturers = app(\App\Services\Catalog\CatalogFeatureService::class)->useManufacturers();
                    $catalogUseActions = app(\App\Services\Catalog\CatalogFeatureService::class)->useActions();
                    $catalogCategoriesActive = request()->routeIs('admin.categories*');
                    $catalogProductsActive = request()->routeIs('admin.products*');
                    $catalogAttributesActive = request()->routeIs('admin.attributes*');
                    $catalogOptionsActive = request()->routeIs('admin.options*');
                    $catalogManufacturersActive = request()->routeIs('admin.manufacturers*');
                    $catalogActionsActive = request()->routeIs('admin.actions*');
                    $catalogOpen = $catalogCategoriesActive || $catalogProductsActive || $catalogAttributesActive || $catalogOptionsActive || $catalogManufacturersActive || $catalogActionsActive;
                    $salesOrdersActive = request()->routeIs('admin.orders*');
                    $salesOpen = $salesOrdersActive;
                    $contentBlogActive = request()->routeIs('admin.content.blog.*');
                    $contentPagesActive = request()->routeIs('admin.content.pages.*');
                    $contentFaqsActive = request()->routeIs('admin.content.faqs.*');
                    $contentCommentsActive = request()->routeIs('admin.content.comments.*');
                    $contentBlocksActive = request()->routeIs('admin.content.blocks*');
                    $contentNavigationActive = request()->routeIs('admin.content.navigation*');
                    $contentSlotsActive = request()->routeIs('admin.content.slots*');
                    $contentOpen = $contentBlogActive || $contentPagesActive || $contentFaqsActive || $contentCommentsActive || $contentBlocksActive || $contentNavigationActive || $contentSlotsActive;
                    $settingsOpen = request()->routeIs('admin.settings.*');
                    $settingsLocalOpen = request()->routeIs('admin.settings.local.*');
                    $settingsSystemOpen = request()->routeIs('admin.settings.system.*');
                    $canManageUsersAccess = auth()->user() && (auth()->user()->isA('superadmin') || auth()->user()->can('users.access.manage'));
                    $canManageCatalogFeatures = auth()->user() && (auth()->user()->isA('superadmin') || auth()->user()->can('settings.system.catalog_features.manage'));
                    $canManageRuntimeTools = auth()->user() && (
                        auth()->user()->isA('superadmin')
                        || auth()->user()->can('settings.system.runtime.manage')
                    );
                    $canManageStoreSettings = auth()->user() && (
                        auth()->user()->isA('superadmin')
                        || auth()->user()->can('settings.system.store.manage')
                    );
                    $canManageApiSettings = auth()->user() && (
                        auth()->user()->isA('superadmin')
                        || auth()->user()->can('settings.api.manage')
                    );
                    $usersListActive = request()->routeIs('admin.users') || request()->routeIs('admin.users.edit') || request()->routeIs('admin.users.show');
                    $usersGroupsActive = request()->routeIs('admin.users.groups');
                    $usersAccessActive = $canManageUsersAccess && request()->routeIs('admin.users.access');
                    $usersActivityActive = request()->routeIs('admin.users.activity');
                    $usersLoyaltyActive = request()->routeIs('admin.users.loyalty');
                    $canViewUsersList = auth()->user() && (auth()->user()->isA('superadmin') || auth()->user()->can('users.list.view'));
                    $canManageUserGroups = auth()->user() && (auth()->user()->isA('superadmin') || auth()->user()->can('users.groups.manage'));
                    $canViewUserActivity = auth()->user() && (auth()->user()->isA('superadmin') || auth()->user()->can('users.activity.view'));
                    $canViewUserLoyalty = auth()->user() && (auth()->user()->isA('superadmin') || auth()->user()->can('users.loyalty.view'));
                    $usersOpen = $usersListActive || $usersGroupsActive || $usersAccessActive || $usersActivityActive || $usersLoyaltyActive;
                    $settingsResource = request()->route('resource');
                    $helpRoute = request()->route()?->getName() ?? '';
                    $helpConfig = config('admin_help', []);
                    $helpEntry = $helpConfig['default'] ?? [];
                    $canViewUsersSection = auth()->user() && (
                        auth()->user()->isA('superadmin')
                        || auth()->user()->can('users.list.view')
                        || auth()->user()->can('users.groups.manage')
                        || auth()->user()->can('users.activity.view')
                        || auth()->user()->can('users.loyalty.view')
                        || auth()->user()->can('users.access.manage')
                    );
                    $userLoyaltyEnabled = (bool) app(\App\Services\Settings\SystemSettingsService::class)->get(
                        'user_loyalty_enabled',
                        (bool) config('user_features.flags.user_loyalty_enabled', true)
                    );

                    foreach (($helpConfig['routes'] ?? []) as $pattern => $payload) {
                        if (\Illuminate\Support\Str::is($pattern, $helpRoute)) {
                            $helpEntry = array_merge($helpEntry, $payload);
                            break;
                        }
                    }

                    if ($helpRoute === 'admin.settings.local.resource' && $settingsResource) {
                        $helpEntry['title'] = 'Settings / Local / '.str_replace('-', ' ', ucwords((string) $settingsResource, '-'));
                        $helpEntry['summary'] = 'Manage local operational records. Keep sort order small and code values stable.';
                        $helpEntry['bullets'] = [
                            'Code values should be stable (used in integrations).',
                            'Use Default only for one row where supported.',
                            'When JSON settings are used, keep structure minimal and explicit.',
                        ];
                    }

                    $normalizeHelpEntry = static function (array $entry): array {
                        $normalized = [
                            'title' => trim((string) ($entry['title'] ?? 'Page Help')),
                            'summary' => trim((string) ($entry['summary'] ?? 'Use this panel to manage the current section.')),
                            'sections' => [],
                            'bullets' => [],
                            'manual_url' => $entry['manual_url'] ?? null,
                        ];

                        $rawSections = is_array($entry['sections'] ?? null) ? $entry['sections'] : [];
                        foreach ($rawSections as $index => $section) {
                            if (! is_array($section)) {
                                continue;
                            }

                            $sectionTitle = trim((string) ($section['title'] ?? ('Section '.($index + 1))));
                            if ($sectionTitle === '') {
                                $sectionTitle = 'Section '.($index + 1);
                            }

                            $sectionSubtitle = trim((string) ($section['subtitle'] ?? 'Practical guidance for this part of the page.'));
                            $explanation = [];

                            if (is_string($section['explanation'] ?? null)) {
                                $paragraph = trim((string) $section['explanation']);
                                if ($paragraph !== '') {
                                    $explanation[] = $paragraph;
                                }
                            } elseif (is_array($section['explanation'] ?? null)) {
                                foreach ((array) $section['explanation'] as $paragraph) {
                                    $text = trim((string) $paragraph);
                                    if ($text !== '') {
                                        $explanation[] = $text;
                                    }
                                }
                            }

                            if (is_array($section['items'] ?? null)) {
                                foreach ((array) $section['items'] as $item) {
                                    $text = trim((string) $item);
                                    if ($text !== '') {
                                        $explanation[] = $text;
                                    }
                                }
                            }

                            $params = [];
                            if (is_array($section['params'] ?? null)) {
                                foreach ((array) $section['params'] as $row) {
                                    if (! is_array($row)) {
                                        continue;
                                    }
                                    $key = trim((string) ($row['key'] ?? $row['name'] ?? ''));
                                    $value = trim((string) ($row['value'] ?? $row['description'] ?? ''));
                                    if ($key === '' && $value === '') {
                                        continue;
                                    }
                                    $params[] = ['key' => $key, 'value' => $value];
                                }
                            }

                            if ($explanation === [] && $params === []) {
                                continue;
                            }

                            $normalized['sections'][] = [
                                'title' => $sectionTitle,
                                'subtitle' => $sectionSubtitle,
                                'explanation' => $explanation,
                                'params' => $params,
                                'items' => [],
                            ];
                        }

                        if ($normalized['sections'] === []) {
                            $bulletParagraphs = [];
                            if (is_array($entry['bullets'] ?? null)) {
                                foreach ((array) $entry['bullets'] as $bullet) {
                                    $text = trim((string) $bullet);
                                    if ($text !== '') {
                                        $bulletParagraphs[] = $text;
                                    }
                                }
                            }

                            if ($bulletParagraphs !== []) {
                                $normalized['sections'][] = [
                                    'title' => 'Quick Guide',
                                    'subtitle' => 'Core operational notes for this page.',
                                    'explanation' => $bulletParagraphs,
                                    'params' => [],
                                    'items' => [],
                                ];
                            }
                        }

                        return $normalized;
                    };

                    $helpEntry = $normalizeHelpEntry(is_array($helpEntry) ? $helpEntry : []);
                @endphp

                <nav class="space-y-1 p-4">
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="block rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <circle cx="6" cy="6" r="3.5" stroke="currentColor" stroke-width="1.4" />
                                <rect x="10.5" y="10.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                            </svg>
                            <span>{{ __('admin.layout.menu.dashboard') }}</span>
                        </span>
                    </a>

                    <details class="group rounded-lg" @if($catalogOpen) open @endif>
                        <summary class="sidebar-dropdown-summary flex cursor-pointer list-none items-center justify-between rounded-lg font-medium [&::-webkit-details-marker]:hidden [&::marker]:content-[''] {{ $catalogOpen ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                            <span class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <rect x="3.5" y="3.5" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.4" />
                                    <rect x="11" y="3.5" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.4" />
                                    <rect x="3.5" y="11" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.4" />
                                    <rect x="11" y="11" width="5.5" height="5.5" rx="1.2" stroke="currentColor" stroke-width="1.4" />
                                </svg>
                                <span>{{ __('admin.layout.menu.catalog') }}</span>
                            </span>
                        </summary>
                        <div class="ml-3 mt-1 space-y-1 border-l border-slate-200 pl-4">
                            <a
                                href="{{ route('admin.categories') }}"
                                class="sidebar-dropdown-link block rounded-lg font-medium {{ $catalogCategoriesActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ __('admin.layout.menu.categories') }}</span>
                                </span>
                            </a>
                            <a
                                href="{{ route('admin.products') }}"
                                class="sidebar-dropdown-link block rounded-lg font-medium {{ $catalogProductsActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ __('admin.layout.menu.products') }}</span>
                                </span>
                            </a>
                            @if ($catalogUseAttributes)
                                <a
                                    href="{{ route('admin.attributes') }}"
                                    class="sidebar-dropdown-link block rounded-lg font-medium {{ $catalogAttributesActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="sidebar-dot"></span>
                                        <span>{{ __('admin.layout.menu.attributes') }}</span>
                                    </span>
                                </a>
                            @endif
                            @if ($catalogUseOptions)
                                <a
                                    href="{{ route('admin.options') }}"
                                    class="sidebar-dropdown-link block rounded-lg font-medium {{ $catalogOptionsActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="sidebar-dot"></span>
                                        <span>{{ __('admin.layout.menu.options') }}</span>
                                    </span>
                                </a>
                            @endif
                            @if ($catalogUseManufacturers)
                                <a
                                    href="{{ route('admin.manufacturers') }}"
                                    class="sidebar-dropdown-link block rounded-lg font-medium {{ $catalogManufacturersActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="sidebar-dot"></span>
                                        <span>{{ __('admin.layout.menu.manufacturers') }}</span>
                                    </span>
                                </a>
                            @endif
                            @if ($catalogUseActions)
                                <a
                                    href="{{ route('admin.actions') }}"
                                    class="sidebar-dropdown-link block rounded-lg font-medium {{ $catalogActionsActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="sidebar-dot"></span>
                                        <span>{{ __('admin.layout.menu.actions_discounts') }}</span>
                                    </span>
                                </a>
                            @endif
                        </div>
                    </details>

                    <details class="group rounded-lg" @if($salesOpen) open @endif>
                        <summary class="sidebar-dropdown-summary flex cursor-pointer list-none items-center justify-between rounded-lg font-medium [&::-webkit-details-marker]:hidden [&::marker]:content-[''] {{ $salesOpen ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                            <span class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <rect x="3.5" y="3.5" width="13" height="13" rx="2" stroke="currentColor" stroke-width="1.4" />
                                    <path d="M6.5 6.8h7M6.5 10h7M6.5 13.2h4.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                </svg>
                                <span>{{ __('admin.layout.menu.sales') }}</span>
                            </span>
                        </summary>
                        <div class="ml-3 mt-1 space-y-1 border-l border-slate-200 pl-4">
                            <a
                                href="{{ route('admin.orders') }}"
                                class="sidebar-dropdown-link block rounded-lg font-medium {{ $salesOrdersActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ __('admin.layout.menu.orders') }}</span>
                                </span>
                            </a>
                        </div>
                    </details>

                    <details class="group rounded-lg" @if($contentOpen) open @endif>
                        <summary class="sidebar-dropdown-summary flex cursor-pointer list-none items-center justify-between rounded-lg font-medium [&::-webkit-details-marker]:hidden [&::marker]:content-[''] {{ $contentOpen ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                            <span class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <rect x="3.5" y="4" width="13" height="12" rx="2" stroke="currentColor" stroke-width="1.4" />
                                    <path d="M3.5 8.5h13M8 8.5v7.5" stroke="currentColor" stroke-width="1.4" />
                                </svg>
                                <span>{{ __('admin.layout.menu.content') }}</span>
                            </span>
                        </summary>
                        <div class="ml-3 mt-1 space-y-1 border-l border-slate-200 pl-4">
                            @if ($catalogUseBlog)
                                <a
                                    href="{{ route('admin.content.blog.index') }}"
                                    class="sidebar-dropdown-link block rounded-lg font-medium {{ $contentBlogActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="sidebar-dot"></span>
                                        <span>{{ __('admin.layout.menu.blog') }}</span>
                                    </span>
                                </a>
                            @endif
                            <a
                                href="{{ route('admin.content.pages.index') }}"
                                class="sidebar-dropdown-link block rounded-lg font-medium {{ $contentPagesActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ __('admin.layout.menu.pages') }}</span>
                                </span>
                            </a>
                            <a
                                href="{{ route('admin.content.faqs.index') }}"
                                class="sidebar-dropdown-link block rounded-lg font-medium {{ $contentFaqsActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ __('admin.layout.menu.faqs') }}</span>
                                </span>
                            </a>
                            <a
                                href="{{ route('admin.content.comments.index') }}"
                                class="sidebar-dropdown-link block rounded-lg font-medium {{ $contentCommentsActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ __('admin.layout.menu.comments') }}</span>
                                </span>
                            </a>
                            <a
                                href="{{ route('admin.content.blocks') }}"
                                class="sidebar-dropdown-link block rounded-lg font-medium {{ $contentBlocksActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ __('admin.layout.menu.blocks') }}</span>
                                </span>
                            </a>
                            <a
                                href="{{ route('admin.content.navigation') }}"
                                class="sidebar-dropdown-link block rounded-lg font-medium {{ $contentNavigationActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ __('admin.layout.menu.navigation') }}</span>
                                </span>
                            </a>
                        </div>
                    </details>

                    <details class="group rounded-lg" @if($settingsOpen) open @endif>
                        <summary class="sidebar-dropdown-summary flex cursor-pointer list-none items-center justify-between rounded-lg font-medium [&::-webkit-details-marker]:hidden [&::marker]:content-[''] {{ $settingsOpen ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                            <span class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M6 5.5h8M6 10h8M6 14.5h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                    <circle cx="8" cy="5.5" r="1.2" fill="currentColor" />
                                    <circle cx="12" cy="10" r="1.2" fill="currentColor" />
                                    <circle cx="9.5" cy="14.5" r="1.2" fill="currentColor" />
                                </svg>
                                <span>{{ __('admin.layout.menu.settings') }}</span>
                            </span>
                        </summary>

                        <div class="ml-3 mt-1 space-y-1 border-l border-slate-200 pl-2">
                            <details class="group rounded-lg" @if($settingsLocalOpen) open @endif>
                                <summary class="sidebar-dropdown-summary flex cursor-pointer list-none items-center justify-between rounded-lg text-xs font-semibold [&::-webkit-details-marker]:hidden [&::marker]:content-[''] {{ $settingsLocalOpen ? 'bg-slate-200 text-slate-900' : 'text-slate-700 hover:bg-slate-100' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="sidebar-branch">&gt;</span>
                                        <span>{{ __('admin.layout.menu.local') }}</span>
                                    </span>
                                </summary>
                                <div class="ml-2 mt-1 space-y-1 border-l border-slate-200 pl-2">
                                    @foreach ([
                                        'payment-methods' => __('admin.layout.menu.payment_methods'),
                                        'shipping-methods' => __('admin.layout.menu.shipping_methods'),
                                        'geo-zones' => __('admin.layout.menu.geo_zones'),
                                        'geo-zone-countries' => __('admin.layout.menu.geo_zone_countries'),
                                        'regions' => __('admin.layout.menu.regions'),
                                        'currencies' => __('admin.layout.menu.currencies'),
                                        'tax-rates' => __('admin.layout.menu.tax_rates'),
                                        'order-statuses' => __('admin.layout.menu.order_statuses'),
                                        'languages' => __('admin.layout.menu.languages'),
                                    ] as $slug => $label)
                                        <a
                                            href="{{ route('admin.settings.local.resource', ['resource' => $slug]) }}"
                                            class="sidebar-dropdown-link block rounded-lg font-medium {{ $settingsResource === $slug ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                        >
                                            <span class="flex items-center gap-2">
                                                <span class="sidebar-dot"></span>
                                                <span>{{ $label }}</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </details>

                            <details class="group rounded-lg" @if($settingsSystemOpen) open @endif>
                                <summary class="sidebar-dropdown-summary flex cursor-pointer list-none items-center justify-between rounded-lg text-xs font-semibold [&::-webkit-details-marker]:hidden [&::marker]:content-[''] {{ $settingsSystemOpen ? 'bg-slate-200 text-slate-900' : 'text-slate-700 hover:bg-slate-100' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="sidebar-branch">&gt;</span>
                                        <span>{{ __('admin.layout.menu.system') }}</span>
                                    </span>
                                </summary>
                                <div class="ml-2 mt-1 space-y-1 border-l border-slate-200 pl-2">
                                    @if ($canManageRuntimeTools)
                                        <a
                                            href="{{ route('admin.settings.system.runtime') }}"
                                            class="sidebar-dropdown-link block rounded-lg font-medium {{ request()->routeIs('admin.settings.system.runtime') ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                        >
                                            <span class="flex items-center gap-2">
                                                <span class="sidebar-dot"></span>
                                                <span>{{ __('admin.layout.menu.runtime_controls') }}</span>
                                            </span>
                                        </a>
                                    @endif
                                    <a
                                        href="{{ route('admin.settings.system.admin-appearance-controls') }}"
                                        class="sidebar-dropdown-link block rounded-lg font-medium {{ request()->routeIs('admin.settings.system.admin-appearance-controls') ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                    >
                                        <span class="flex items-center gap-2">
                                            <span class="sidebar-dot"></span>
                                            <span>{{ __('admin.layout.menu.admin_appearance_controls') }}</span>
                                        </span>
                                    </a>
                                    @if ($canManageCatalogFeatures)
                                        <a
                                            href="{{ route('admin.settings.system.catalog-features') }}"
                                            class="sidebar-dropdown-link block rounded-lg font-medium {{ request()->routeIs('admin.settings.system.catalog-features') ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                        >
                                            <span class="flex items-center gap-2">
                                                <span class="sidebar-dot"></span>
                                                <span>{{ __('admin.layout.menu.catalog_features') }}</span>
                                            </span>
                                        </a>
                                    @endif
                                    @if ($canManageStoreSettings)
                                        <a
                                            href="{{ route('admin.settings.system.store-settings') }}"
                                            class="sidebar-dropdown-link block rounded-lg font-medium {{ request()->routeIs('admin.settings.system.store-settings') ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                        >
                                            <span class="flex items-center gap-2">
                                                <span class="sidebar-dot"></span>
                                                <span>{{ __('admin.layout.menu.store_settings') }}</span>
                                            </span>
                                        </a>
                                    @endif
                                </div>
                            </details>

                            @if ($canManageApiSettings && $catalogUseApi)
                                <a
                                    href="{{ route('admin.settings.api.index') }}"
                                    class="sidebar-dropdown-link block rounded-lg font-medium {{ request()->routeIs('admin.settings.api.*') ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="sidebar-dot"></span>
                                        <span>{{ __('admin.layout.menu.api') }}</span>
                                    </span>
                                </a>
                            @endif

                            <a
                                href="{{ route('admin.settings.user.index') }}"
                                class="sidebar-dropdown-link block rounded-lg font-medium {{ request()->routeIs('admin.settings.user.*') ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="sidebar-dot"></span>
                                    <span>{{ __('admin.layout.menu.user') }}</span>
                                </span>
                            </a>
                        </div>
                    </details>

                    @if ($canViewUsersSection)
                        <details class="group rounded-lg" @if($usersOpen) open @endif>
                            <summary class="sidebar-dropdown-summary flex cursor-pointer list-none items-center justify-between rounded-lg font-medium [&::-webkit-details-marker]:hidden [&::marker]:content-[''] {{ $usersOpen ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                                <span class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <circle cx="7" cy="7" r="2.4" stroke="currentColor" stroke-width="1.4" />
                                        <circle cx="13.5" cy="8" r="1.9" stroke="currentColor" stroke-width="1.4" />
                                        <path d="M3.8 15c.65-1.8 2.28-2.8 4.2-2.8 1.85 0 3.45.92 4.15 2.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        <path d="M12.1 15c.5-1.2 1.5-1.95 2.7-2.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                    </svg>
                                    <span>{{ __('admin.layout.menu.users') }}</span>
                                </span>
                            </summary>
                            <div class="ml-3 mt-1 space-y-1 border-l border-slate-200 pl-4">
                                @if ($canViewUsersList)
                                    <a
                                        href="{{ route('admin.users') }}"
                                        class="sidebar-dropdown-link block rounded-lg font-medium {{ $usersListActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                    >
                                        <span class="flex items-center gap-2">
                                            <span class="sidebar-dot"></span>
                                            <span>{{ __('admin.layout.menu.users_list') }}</span>
                                        </span>
                                    </a>
                                @endif
                                @if ($canManageUserGroups)
                                    <a
                                        href="{{ route('admin.users.groups') }}"
                                        class="sidebar-dropdown-link block rounded-lg font-medium {{ $usersGroupsActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                    >
                                        <span class="flex items-center gap-2">
                                            <span class="sidebar-dot"></span>
                                            <span>{{ __('admin.layout.menu.groups') }}</span>
                                        </span>
                                    </a>
                                @endif
                                @if ($canManageUsersAccess)
                                    <a
                                        href="{{ route('admin.users.access') }}"
                                        class="sidebar-dropdown-link block rounded-lg font-medium {{ $usersAccessActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                    >
                                        <span class="flex items-center gap-2">
                                            <span class="sidebar-dot"></span>
                                            <span>{{ __('admin.layout.menu.roles_abilities') }}</span>
                                        </span>
                                    </a>
                                @endif
                                @if ($canViewUserActivity)
                                    <a
                                        href="{{ route('admin.users.activity') }}"
                                        class="sidebar-dropdown-link block rounded-lg font-medium {{ $usersActivityActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                    >
                                        <span class="flex items-center gap-2">
                                            <span class="sidebar-dot"></span>
                                            <span>{{ __('admin.layout.menu.activity') }}</span>
                                        </span>
                                    </a>
                                @endif
                                @if ($userLoyaltyEnabled && $canViewUserLoyalty)
                                    <a
                                        href="{{ route('admin.users.loyalty') }}"
                                        class="sidebar-dropdown-link block rounded-lg font-medium {{ $usersLoyaltyActive ? 'is-active-leaf' : 'text-slate-700 hover:bg-slate-100' }}"
                                    >
                                        <span class="flex items-center gap-2">
                                            <span class="sidebar-dot"></span>
                                            <span>{{ __('admin.layout.menu.loyalty') }}</span>
                                        </span>
                                    </a>
                                @endif
                            </div>
                        </details>
                    @endif
                </nav>
            </aside>

            <div class="admin-main flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 md:px-6">
                    <div class="text-sm font-semibold text-slate-800 md:text-base">
                        {{ $title ?? __('admin.layout.admin') }}
                    </div>

                    <div class="flex items-center gap-3">
                        @php
                            $activeAdminLocale = strtolower((string) app()->getLocale());
                            $adminLocaleOptions = ['hr', 'en'];
                        @endphp
                        <div class="flex items-center rounded-lg border border-slate-200 bg-white p-0.5 text-xs font-semibold uppercase tracking-[0.1em] text-slate-600">
                            @foreach ($adminLocaleOptions as $localeCode)
                                <a
                                    href="{{ request()->fullUrlWithQuery(['admin_locale' => $localeCode]) }}"
                                    class="rounded-md px-2.5 py-1 {{ $activeAdminLocale === $localeCode ? 'bg-slate-900 text-white' : 'hover:bg-slate-100' }}"
                                >
                                    {{ $localeCode }}
                                </a>
                            @endforeach
                        </div>
                        <button
                            type="button"
                            id="admin-ai-open"
                            class="admin-ai-button"
                            aria-label="{{ __('admin.layout.assistant.open_ai') }}"
                            title="{{ __('admin.layout.assistant.open_ai') }}"
                        >
                            <span>AI</span>
                        </button>
                        <button
                            type="button"
                            id="admin-help-open"
                            class="admin-help-button"
                            aria-label="{{ __('admin.layout.assistant.open_help') }}"
                            title="{{ __('admin.layout.assistant.help') }}"
                        >
                            ?
                        </button>

                        @php
                            $userInitial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) auth()->user()->name, 0, 1));
                        @endphp

                        <details class="group relative">
                            <summary class="flex list-none cursor-pointer items-center gap-2 rounded-full px-2.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 group-open:bg-slate-100 [&::-webkit-details-marker]:hidden [&::marker]:content-['']">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">
                                    {{ $userInitial }}
                                </span>
                                <span class="hidden md:inline">{{ auth()->user()->name }}</span>
                                <svg class="h-4 w-4 shrink-0 transition group-open:rotate-180" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </summary>
                            <div class="absolute right-0 z-20 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
                                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.layout.quick_actions') }}</p>
                                    <p class="mt-1 text-sm font-medium text-slate-800">{{ auth()->user()->name }}</p>
                                </div>
                                <div class="p-2">
                                <a
                                    href="{{ route('admin.profile') }}"
                                    class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
                                >
                                    {{ __('admin.layout.profile') }}
                                </a>

                                <a
                                    href="{{ url('/') }}"
                                    target="_blank"
                                    class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"
                                >
                                    {{ __('admin.layout.open_frontend') }}
                                </a>

                                @if ($canManageRuntimeTools)
                                    @php
                                        $maintenanceOn = app()->isDownForMaintenance();
                                    @endphp

                                    <div class="mb-2 rounded-lg border px-3 py-2 text-sm {{ $maintenanceOn ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                        <span class="font-semibold">Maintenance:</span>
                                        <span>{{ $maintenanceOn ? 'ON' : 'OFF' }}</span>
                                    </div>

                                    <form method="POST" action="{{ route('admin.system.cache.clear') }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100">
                                            {{ __('admin.layout.clean_cache') }}
                                        </button>
                                    </form>

                                    <div class="my-2 border-t border-slate-200"></div>

                                    <form method="POST" action="{{ route('admin.system.maintenance.on') }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50">
                                            {{ __('admin.layout.maintenance_on') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.system.maintenance.off') }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-emerald-700 hover:bg-emerald-50">
                                            {{ __('admin.layout.maintenance_off') }}
                                        </button>
                                    </form>
                                @endif

                                <div class="my-2 border-t border-slate-200"></div>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100">
                                        {{ __('admin.layout.logout') }}
                                    </button>
                                </form>
                                </div>
                            </div>
                        </details>
                    </div>
                </header>

                <main class="flex-1 p-4 md:p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
        <div
            id="admin-help-overlay"
            class="admin-help-overlay"
            aria-hidden="true"
            data-help='@json($helpEntry)'
        >
            <div class="admin-help-modal" role="dialog" aria-modal="true" aria-labelledby="admin-help-title">
                <div class="admin-help-modal-header flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('admin.layout.quick_manual') }}</p>
                        <h2 id="admin-help-title" class="mt-1 text-base font-semibold tracking-tight text-slate-900"></h2>
                    </div>
                    <button type="button" id="admin-help-close" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">Close</button>
                </div>
                <div class="admin-help-modal-body">
                    <p id="admin-help-summary" class="text-sm text-slate-700"></p>
                    <div id="admin-help-sections" class="admin-help-sections"></div>
                    <ul id="admin-help-list" class="admin-help-list"></ul>
                    <a id="admin-help-manual-link" href="#" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-100">
                        Open Manual
                    </a>
                </div>
            </div>
        </div>
        <div id="admin-ai-overlay" class="admin-ai-overlay" aria-hidden="true">
            <div class="admin-ai-modal" role="dialog" aria-modal="true" aria-labelledby="admin-ai-title">
                <div class="admin-ai-modal-header flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Admin Agent</p>
                        <h2 id="admin-ai-title" class="mt-1 text-base font-semibold tracking-tight text-slate-900">Describe what you want to do</h2>
                    </div>
                    <button type="button" id="admin-ai-close" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">Close</button>
                </div>
                <form id="admin-ai-form" class="admin-ai-modal-body">
                    <textarea
                        id="admin-ai-input"
                        class="admin-ai-input"
                        placeholder="Napravi mi kategoriju Ugljikohidrati unutar Prehrane, dodaj opis i dodaj danas dodane artikle u kategoriju."
                    ></textarea>
                    <p class="admin-ai-hint">Shortcut: press <strong>Space</strong> twice anywhere outside form inputs.</p>
                    <div id="admin-ai-preview" class="mt-4 hidden rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p id="admin-ai-summary" class="text-sm font-medium text-slate-800"></p>
                        <ul id="admin-ai-actions" class="mt-2 list-disc space-y-1 pl-5 text-xs text-slate-700"></ul>
                        <ul id="admin-ai-warnings" class="mt-2 list-disc space-y-1 pl-5 text-xs text-amber-700"></ul>
                    </div>
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" id="admin-ai-cancel" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</button>
                        <button type="submit" id="admin-ai-preview-submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Preview</button>
                        <button type="button" id="admin-ai-confirm-submit" class="hidden rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Confirm & Execute</button>
                    </div>
                </form>
            </div>
        </div>
        <div id="admin-ace-overlay" class="admin-ace-overlay" aria-hidden="true">
            <div class="admin-ace-modal" role="dialog" aria-modal="true" aria-labelledby="admin-ace-title">
                <div class="admin-ace-header">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Ace Editor</p>
                        <h2 id="admin-ace-title" class="mt-1 text-base font-semibold tracking-tight text-slate-900">HTML Editor</h2>
                    </div>
                    <button type="button" id="admin-ace-close" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">Close</button>
                </div>
                <div id="admin-ace-editor" class="admin-ace-editor"></div>
                <div class="admin-ace-footer">
                    <button type="button" id="admin-ace-cancel" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</button>
                    <button type="button" id="admin-ace-apply" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Apply</button>
                </div>
            </div>
        </div>
        <div id="admin-toast-root" class="admin-toast-root" aria-live="polite" aria-atomic="true"></div>
        <script>
            (() => {
                const root = document.getElementById('admin-toast-root');
                if (!root) return;

                const normalizePayload = (payload) => {
                    if (!payload) return null;
                    if (Array.isArray(payload)) return normalizePayload(payload[0] ?? null);
                    if (typeof payload === 'string') return { type: 'success', message: payload };
                    if (typeof payload === 'object') return payload;
                    return null;
                };

                const showToast = (rawPayload) => {
                    const payload = normalizePayload(rawPayload);
                    if (!payload || !payload.message) return;

                    const rawType = String(payload.type ?? 'success').toLowerCase();
                    const type = rawType === 'error' ? 'danger' : rawType;
                    const message = String(payload.message);
                    const iconMap = { success: '✓', warning: '!', danger: '×', info: 'i' };
                    const safeType = ['success', 'warning', 'danger', 'info'].includes(type) ? type : 'info';
                    const icon = iconMap[safeType] ?? 'i';

                    const toast = document.createElement('div');
                    toast.className = 'admin-toast';
                    toast.dataset.type = safeType;

                    const iconEl = document.createElement('span');
                    iconEl.className = 'admin-toast-icon';
                    iconEl.textContent = icon;

                    const messageEl = document.createElement('p');
                    messageEl.className = 'admin-toast-message';
                    messageEl.textContent = message;

                    const closeButton = document.createElement('button');
                    closeButton.type = 'button';
                    closeButton.className = 'admin-toast-close';
                    closeButton.setAttribute('aria-label', 'Dismiss notification');
                    closeButton.textContent = '×';

                    toast.append(iconEl, messageEl, closeButton);

                    const close = () => {
                        toast.classList.remove('is-visible');
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateY(-10px)';
                        setTimeout(() => toast.remove(), 160);
                    };

                    closeButton.addEventListener('click', close);
                    root.appendChild(toast);
                    requestAnimationFrame(() => toast.classList.add('is-visible'));
                    setTimeout(close, 3600);
                };

                const initialNotify = @json(session('notify') ?? (session()->has('status') ? ['type' => 'success', 'message' => (string) session('status')] : null));
                if (initialNotify) showToast(initialNotify);

                window.addEventListener('admin:notify', (event) => showToast(event.detail ?? null));

                document.addEventListener('livewire:init', () => {
                    if (!window.Livewire || typeof window.Livewire.on !== 'function') return;
                    window.Livewire.on('notify', (payload) => showToast(payload));
                });
            })();

            (() => {
                const overlay = document.getElementById('admin-help-overlay');
                const openButton = document.getElementById('admin-help-open');
                const closeButton = document.getElementById('admin-help-close');
                const titleEl = document.getElementById('admin-help-title');
                const summaryEl = document.getElementById('admin-help-summary');
                const sectionsEl = document.getElementById('admin-help-sections');
                const listEl = document.getElementById('admin-help-list');
                const manualLinkEl = document.getElementById('admin-help-manual-link');

                if (!overlay || !openButton || !closeButton || !titleEl || !summaryEl || !sectionsEl || !listEl || !manualLinkEl) return;

                const payload = (() => {
                    const raw = overlay.dataset.help;
                    if (!raw) return {};
                    try {
                        return JSON.parse(raw);
                    } catch (error) {
                        return {};
                    }
                })();

                const bullets = Array.isArray(payload.bullets) ? payload.bullets : [];
                const sections = Array.isArray(payload.sections) ? payload.sections : [];
                titleEl.textContent = String(payload.title || 'Page Help');
                summaryEl.textContent = String(payload.summary || 'Use this panel to manage the current section.');

                sectionsEl.innerHTML = '';
                sections.forEach((sectionPayload) => {
                    if (!sectionPayload || typeof sectionPayload !== 'object') return;

                    const items = Array.isArray(sectionPayload.items) ? sectionPayload.items : [];
                    const subtitle = typeof sectionPayload.subtitle === 'string' ? sectionPayload.subtitle.trim() : '';
                    const explanationRaw = sectionPayload.explanation;
                    const explanation = Array.isArray(explanationRaw)
                        ? explanationRaw.map((value) => String(value)).filter((value) => value.trim() !== '')
                        : (typeof explanationRaw === 'string' && explanationRaw.trim() !== '' ? [explanationRaw] : []);
                    const params = Array.isArray(sectionPayload.params) ? sectionPayload.params : [];

                    if (!items.length && subtitle === '' && explanation.length === 0 && params.length === 0) return;

                    const section = document.createElement('section');
                    section.className = 'admin-help-section';

                    const sectionTitle = document.createElement('h3');
                    sectionTitle.className = 'admin-help-section-title';
                    sectionTitle.textContent = String(sectionPayload.title || 'Notes');
                    section.appendChild(sectionTitle);

                    if (subtitle !== '') {
                        const subtitleEl = document.createElement('p');
                        subtitleEl.className = 'admin-help-section-subtitle';
                        subtitleEl.textContent = subtitle;
                        section.appendChild(subtitleEl);
                    }

                    explanation.forEach((paragraph) => {
                        const paragraphEl = document.createElement('p');
                        paragraphEl.className = 'admin-help-paragraph';
                        paragraphEl.textContent = paragraph;
                        section.appendChild(paragraphEl);
                    });

                    if (params.length > 0) {
                        const paramsWrap = document.createElement('div');
                        paramsWrap.className = 'admin-help-params';

                        params.forEach((row) => {
                            if (!row || typeof row !== 'object') return;
                            const key = String(row.key ?? row.name ?? '').trim();
                            const value = String(row.value ?? row.description ?? '').trim();
                            if (key === '' && value === '') return;

                            const rowEl = document.createElement('div');
                            rowEl.className = 'admin-help-param-row';

                            const keyEl = document.createElement('div');
                            keyEl.className = 'admin-help-param-key';
                            keyEl.textContent = key || 'Parameter';

                            const valueEl = document.createElement('div');
                            valueEl.className = 'admin-help-param-value';
                            valueEl.textContent = value;

                            rowEl.append(keyEl, valueEl);
                            paramsWrap.appendChild(rowEl);
                        });

                        if (paramsWrap.childElementCount > 0) {
                            section.appendChild(paramsWrap);
                        }
                    }

                    if (items.length > 0) {
                        const sectionList = document.createElement('ul');
                        sectionList.className = 'admin-help-list';

                        items.forEach((item) => {
                            const li = document.createElement('li');
                            li.textContent = String(item);
                            sectionList.appendChild(li);
                        });

                        section.appendChild(sectionList);
                    }

                    sectionsEl.appendChild(section);
                });

                listEl.innerHTML = '';
                if (sectionsEl.childElementCount > 0) {
                    listEl.classList.add('hidden');
                } else {
                    bullets.forEach((item) => {
                        const li = document.createElement('li');
                        li.textContent = String(item);
                        listEl.appendChild(li);
                    });
                    listEl.classList.add('admin-help-list-flat');
                    listEl.classList.remove('hidden');
                }
                if (sectionsEl.childElementCount > 0) {
                    listEl.classList.remove('admin-help-list-flat');
                }

                if (payload.manual_url) {
                    manualLinkEl.href = String(payload.manual_url);
                    manualLinkEl.classList.remove('hidden');
                } else {
                    manualLinkEl.classList.add('hidden');
                }

                const open = () => {
                    overlay.classList.add('is-open');
                    overlay.setAttribute('aria-hidden', 'false');
                };

                const close = () => {
                    overlay.classList.remove('is-open');
                    overlay.setAttribute('aria-hidden', 'true');
                };

                openButton.addEventListener('click', open);
                closeButton.addEventListener('click', close);
                overlay.addEventListener('click', (event) => {
                    if (event.target === overlay) {
                        close();
                    }
                });

                window.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
                        close();
                    }
                });
            })();

            (() => {
                const overlay = document.getElementById('admin-ai-overlay');
                const openButton = document.getElementById('admin-ai-open');
                const closeButton = document.getElementById('admin-ai-close');
                const cancelButton = document.getElementById('admin-ai-cancel');
                const form = document.getElementById('admin-ai-form');
                const input = document.getElementById('admin-ai-input');
                const previewSubmitButton = document.getElementById('admin-ai-preview-submit');
                const confirmSubmitButton = document.getElementById('admin-ai-confirm-submit');
                const previewBox = document.getElementById('admin-ai-preview');
                const summaryEl = document.getElementById('admin-ai-summary');
                const actionsEl = document.getElementById('admin-ai-actions');
                const warningsEl = document.getElementById('admin-ai-warnings');
                const helpOverlay = document.getElementById('admin-help-overlay');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const previewUrl = @json(route('admin.ai.preview'));
                const executeUrl = @json(route('admin.ai.execute'));

                if (!overlay || !openButton || !closeButton || !cancelButton || !form || !input || !previewSubmitButton || !confirmSubmitButton || !previewBox || !summaryEl || !actionsEl || !warningsEl) return;

                let lastSpaceAt = 0;
                let currentPlanId = null;
                let busy = false;

                const isEditableTarget = (target) => {
                    if (!target || !(target instanceof HTMLElement)) return false;
                    if (target.isContentEditable) return true;
                    const tag = target.tagName.toLowerCase();
                    return tag === 'input' || tag === 'textarea' || tag === 'select';
                };

                const notify = (type, message) => {
                    window.dispatchEvent(new CustomEvent('admin:notify', {
                        detail: { type, message },
                    }));
                };

                const setBusy = (value) => {
                    busy = value;
                    previewSubmitButton.disabled = value;
                    confirmSubmitButton.disabled = value;
                    cancelButton.disabled = value;
                    closeButton.disabled = value;
                };

                const resetPreview = () => {
                    currentPlanId = null;
                    previewBox.classList.add('hidden');
                    summaryEl.textContent = '';
                    actionsEl.innerHTML = '';
                    warningsEl.innerHTML = '';
                    confirmSubmitButton.classList.add('hidden');
                    previewSubmitButton.textContent = 'Preview';
                };

                resetPreview();

                const renderPreview = (payload) => {
                    summaryEl.textContent = String(payload.summary || '');

                    actionsEl.innerHTML = '';
                    (Array.isArray(payload.actions) ? payload.actions : []).forEach((action) => {
                        const li = document.createElement('li');
                        li.textContent = String(action);
                        actionsEl.appendChild(li);
                    });

                    warningsEl.innerHTML = '';
                    (Array.isArray(payload.warnings) ? payload.warnings : []).forEach((warning) => {
                        const li = document.createElement('li');
                        li.textContent = String(warning);
                        warningsEl.appendChild(li);
                    });

                    previewBox.classList.remove('hidden');
                    confirmSubmitButton.classList.remove('hidden');
                };

                const requestJson = async (url, payload) => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(payload),
                    });

                    let data = null;
                    try {
                        data = await response.json();
                    } catch (error) {
                        data = null;
                    }

                    if (!response.ok) {
                        const message =
                            data?.message ||
                            (typeof data?.errors === 'object' ? Object.values(data.errors)[0]?.[0] : null) ||
                            'Request failed.';
                        const error = new Error(String(message));
                        error.payload = data || {};
                        throw error;
                    }

                    return data || {};
                };

                const open = () => {
                    overlay.classList.add('is-open');
                    overlay.setAttribute('aria-hidden', 'false');
                    setTimeout(() => input.focus(), 0);
                };

                const close = () => {
                    overlay.classList.remove('is-open');
                    overlay.setAttribute('aria-hidden', 'true');
                    resetPreview();
                };

                openButton.addEventListener('click', open);
                closeButton.addEventListener('click', close);
                cancelButton.addEventListener('click', close);

                overlay.addEventListener('click', (event) => {
                    if (event.target === overlay) {
                        close();
                    }
                });

                form.addEventListener('submit', (event) => {
                    event.preventDefault();

                    if (busy) return;

                    const prompt = String(input.value || '').trim();
                    if (!prompt) {
                        notify('warning', 'Enter your AI request first.');
                        input.focus();
                        return;
                    }

                    setBusy(true);
                    previewSubmitButton.textContent = 'Previewing...';

                    requestJson(previewUrl, { prompt })
                        .then((payload) => {
                            currentPlanId = String(payload.plan_id || '');
                            renderPreview(payload);
                            previewSubmitButton.textContent = 'Re-Preview';
                            notify('info', 'AI plan ready. Review summary and confirm to execute.');
                        })
                        .catch((error) => {
                            notify('danger', error.message || 'Failed to build AI preview.');
                            if (error?.payload?.developer_notice) {
                                notify('warning', String(error.payload.developer_notice));
                            }
                            resetPreview();
                        })
                        .finally(() => {
                            setBusy(false);
                        });
                });

                confirmSubmitButton.addEventListener('click', () => {
                    if (busy || !currentPlanId) return;

                    setBusy(true);
                    confirmSubmitButton.textContent = 'Executing...';

                    requestJson(executeUrl, { plan_id: currentPlanId })
                        .then((payload) => {
                            notify('success', `AI action completed for "${payload.category_name ?? 'category'}".`);
                            const redirectUrl = String(payload.redirect_url || '');
                            close();
                            if (redirectUrl) {
                                window.location.href = redirectUrl;
                            }
                        })
                        .catch((error) => {
                            notify('danger', error.message || 'Failed to execute AI action.');
                            if (error?.payload?.developer_notice) {
                                notify('warning', String(error.payload.developer_notice));
                            }
                        })
                        .finally(() => {
                            confirmSubmitButton.textContent = 'Confirm & Execute';
                            setBusy(false);
                        });
                });

                window.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
                        close();
                        return;
                    }

                    if (event.key !== ' ' || event.repeat || event.ctrlKey || event.metaKey || event.altKey) {
                        return;
                    }

                    if (overlay.classList.contains('is-open')) {
                        return;
                    }

                    if (helpOverlay && helpOverlay.classList.contains('is-open')) {
                        return;
                    }

                    if (isEditableTarget(event.target)) {
                        return;
                    }

                    const now = Date.now();
                    if ((now - lastSpaceAt) <= 420) {
                        event.preventDefault();
                        open();
                        lastSpaceAt = 0;
                        return;
                    }

                    lastSpaceAt = now;
                });
            })();
        </script>
    </body>
</html>
