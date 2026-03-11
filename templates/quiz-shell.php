<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div
    class="tq-quiz-wrapper"
    data-set-id="<?php echo esc_attr( $set_id ); ?>"
    data-mode="<?php echo esc_attr( $mode ); ?>"
>
    <div data-tq-loading class="max-w-2xl mx-auto my-10 flex flex-col items-center gap-3 text-slate-500 py-16">
        <svg class="tq-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
        <p class="text-sm">Loading quiz&hellip;</p>
    </div>

    <div data-tq-error class="hidden max-w-2xl mx-auto my-10 bg-red-50 border border-red-200 rounded-lg px-5 py-4 text-sm text-red-700 font-medium"></div>

    <div data-tq-card class="hidden max-w-2xl mx-auto my-8 rounded-xl border border-slate-200 shadow-sm overflow-hidden bg-white">

        <div class="px-6 py-4 border-b border-slate-100">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 data-tq-set-title class="text-base font-bold text-slate-900 leading-tight"></h2>
                    <p data-tq-set-sub class="text-xs text-slate-500 mt-0.5"></p>
                </div>
                <span data-tq-mode-badge class="flex-shrink-0 text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full"></span>
            </div>
            <div class="mt-3 flex items-center gap-3">
                <div class="flex-1 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div data-tq-progress-bar class="bg-blue-600 h-full rounded-full transition-all duration-500" style="width:0%"></div>
                </div>
                <span data-tq-progress-text class="text-xs text-slate-400 whitespace-nowrap"></span>
            </div>
        </div>

        <div data-tq-question class="px-6 py-6">
            <div class="flex gap-4 mb-5">
                <div data-tq-q-num
                    class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center">
                </div>
                <p data-tq-prompt class="text-slate-900 text-base font-medium leading-relaxed pt-0.5"></p>
            </div>
            <div data-tq-choices class="space-y-2.5"></div>
            <div data-tq-feedback class="hidden mt-4 text-sm font-medium px-3 py-2.5 rounded-md"></div>
        </div>

        <div data-tq-actions class="bg-slate-50 border-t border-slate-100 px-6 py-3 flex items-center justify-between">
            <span class="text-xs text-slate-400">Select one answer, then click Submit</span>
            <div class="flex gap-2">
                <button type="button" data-tq-submit
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
                    Submit
                </button>
                <button type="button" data-tq-next
                    class="hidden px-4 py-2 border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 text-sm font-semibold rounded-lg transition-colors">
                    Next &rarr;
                </button>
            </div>
        </div>

        <div data-tq-finish class="hidden">
            <div class="px-6 py-8 text-center border-b border-slate-100">
                <div data-tq-score-circle
                    class="inline-flex items-center justify-center w-28 h-28 rounded-full text-3xl font-extrabold mb-4">
                </div>
                <h3 data-tq-finish-title class="text-xl font-bold text-slate-900"></h3>
                <p data-tq-score-label class="text-slate-500 text-sm mt-1"></p>
            </div>
            <div data-tq-missed class="px-6 py-6"></div>
        </div>

    </div>
</div>

        <svg class="tq-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
        <p class="text-sm">Loading quiz&hellip;</p>
    </div>

    {{-- Error --}}
    <div data-tq-error class="hidden max-w-2xl mx-auto my-10 bg-red-50 border border-red-200 rounded-lg px-5 py-4 text-sm text-red-700 font-medium"></div>

    {{-- Main quiz card --}}
    <div data-tq-card class="hidden max-w-2xl mx-auto my-8 rounded-xl border border-slate-200 shadow-sm overflow-hidden bg-white">

        {{-- Card header ─ always visible --}}
        <div class="px-6 py-4 border-b border-slate-100">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 data-tq-set-title class="text-base font-bold text-slate-900 leading-tight"></h2>
                    <p data-tq-set-sub class="text-xs text-slate-500 mt-0.5"></p>
                </div>
                <span data-tq-mode-badge class="flex-shrink-0 text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full"></span>
            </div>
            <div class="mt-3 flex items-center gap-3">
                <div class="flex-1 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div data-tq-progress-bar class="bg-blue-600 h-full rounded-full transition-all duration-500" style="width:0%"></div>
                </div>
                <span data-tq-progress-text class="text-xs text-slate-400 whitespace-nowrap"></span>
            </div>
        </div>

        {{-- Question region --}}
        <div data-tq-question class="px-6 py-6">
            <div class="flex gap-4 mb-5">
                <div data-tq-q-num
                    class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center">
                </div>
                <p data-tq-prompt class="text-slate-900 text-base font-medium leading-relaxed pt-0.5"></p>
            </div>
            <div data-tq-choices class="space-y-2.5"></div>
            <div data-tq-feedback class="hidden mt-4 text-sm font-medium px-3 py-2.5 rounded-md"></div>
        </div>

        {{-- Action bar --}}
        <div data-tq-actions class="bg-slate-50 border-t border-slate-100 px-6 py-3 flex items-center justify-between">
            <span class="text-xs text-slate-400">Select one answer, then click Submit</span>
            <div class="flex gap-2">
                <button type="button" data-tq-submit
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
                    Submit
                </button>
                <button type="button" data-tq-next
                    class="hidden px-4 py-2 border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 text-sm font-semibold rounded-lg transition-colors">
                    Next &rarr;
                </button>
            </div>
        </div>

        {{-- Completion panel --}}
        <div data-tq-finish class="hidden">
            <div class="px-6 py-8 text-center border-b border-slate-100">
                <div data-tq-score-circle
                    class="inline-flex items-center justify-center w-28 h-28 rounded-full text-3xl font-extrabold mb-4">
                </div>
                <h3 data-tq-finish-title class="text-xl font-bold text-slate-900"></h3>
                <p data-tq-score-label class="text-slate-500 text-sm mt-1"></p>
            </div>
            <div data-tq-missed class="px-6 py-6"></div>
        </div>

    </div>{{-- /data-tq-card --}}
</div>{{-- /tq-quiz-wrapper --}}
