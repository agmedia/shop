@if (session('status'))
    <div class="mb-3 border border-success bg-green-light px-3 py-2" data-flash-message>
        <div class="d-flex align-items-start gap-2">
            <span class="d-inline-flex align-items-center justify-content-center mt-1 color-black" aria-hidden="true">
                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8">
                    <path d="m5 13 4 4L19 7"></path>
                </svg>
            </span>
            <p class="mb-0 font-600 color-black flex-fill">{{ session('status') }}</p>
            <button type="button" class="btn p-0 color-black opacity-70" aria-label="{{ __('ui.notifications.close') }}" onclick="this.closest('[data-flash-message]')?.remove()">
                <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18"></path>
                </svg>
            </button>
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="mb-3 border border-danger bg-red-light px-3 py-2" data-flash-message>
        <div class="d-flex align-items-start gap-2">
            <span class="d-inline-flex align-items-center justify-content-center mt-1 color-black" aria-hidden="true">
                <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 8v5"></path>
                    <path d="M12 16h.01"></path>
                </svg>
            </span>
            <div class="flex-fill">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li class="font-12 color-black">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn p-0 color-black opacity-70" aria-label="{{ __('ui.notifications.close') }}" onclick="this.closest('[data-flash-message]')?.remove()">
                <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18"></path>
                </svg>
            </button>
        </div>
    </div>
@endif
