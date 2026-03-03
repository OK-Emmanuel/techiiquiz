<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div
    class="tq-quiz-wrapper max-w-3xl mx-auto p-4 sm:p-6 lg:p-8"
    data-set-id="<?php echo esc_attr( $set_id ); ?>"
    data-mode="<?php echo esc_attr( $mode ); ?>"
>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-slate-900">TechiQuiz</h2>
            <span class="text-sm text-slate-600 uppercase tracking-wide"><?php echo esc_html( $mode ); ?> mode</span>
        </div>

        <p class="text-sm text-slate-600 mb-4" data-tq-status>
            Loading quiz...
        </p>

        <div data-tq-question class="space-y-4 hidden">
            <div class="text-sm text-slate-500" data-tq-progress></div>
            <h3 class="text-lg font-medium text-slate-900" data-tq-prompt></h3>
            <form class="space-y-3" data-tq-choices></form>
            <div class="flex gap-3 pt-2">
                <button type="button" data-tq-submit class="px-4 py-2 rounded-md bg-slate-900 text-white text-sm font-medium">Submit</button>
                <button type="button" data-tq-next class="px-4 py-2 rounded-md border border-slate-300 text-slate-800 text-sm font-medium hidden">Next</button>
            </div>
            <p class="text-sm" data-tq-feedback></p>
        </div>

        <div data-tq-finish class="hidden space-y-3">
            <h3 class="text-lg font-semibold text-slate-900">Practice Test Complete</h3>
            <p class="text-slate-700" data-tq-score></p>
            <div data-tq-missed class="space-y-2"></div>
        </div>
    </div>
</div>
