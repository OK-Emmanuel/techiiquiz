<?php
// Prevent direct access to the file for security.
// If WordPress is not loaded, exit immediately.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<!--
Main Quiz Wrapper
- Contains the entire quiz UI
- data-set-id: identifies which quiz set to load
- data-mode: determines quiz mode (exam, practice, etc.)
-->
<div
    class="tq-quiz-wrapper min-h-screen px-4 py-8 sm:px-6 lg:px-8"
    data-set-id="<?php echo esc_attr( $set_id ); ?>"
    data-mode="<?php echo esc_attr( $mode ); ?>"
>

    <!--
    Loading State
    Displayed while quiz data (questions/session) is being fetched
    -->
    <div data-tq-loading class="mx-auto flex max-w-3xl flex-col items-center gap-4 rounded-[2rem] border border-slate-200 bg-white/90 px-8 py-16 text-slate-500 shadow-[0_20px_60px_rgba(15,23,42,0.08)] backdrop-blur">
        
        <!-- Spinner icon -->
        <div class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-brand-blue/10 text-brand-blue">
            <svg class="tq-spin h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
        </div>

        <!-- Loading message -->
        <div class="space-y-1 text-center">
            <p class="text-lg font-semibold text-slate-800">Preparing your quiz</p>
            <p class="text-sm">Loading questions and restoring your session&hellip;</p>
        </div>
    </div>

    <!--
    Error Message Container
    Hidden by default. Displayed if quiz loading fails or API returns an error.
    -->
    <div data-tq-error class="mx-auto hidden max-w-3xl rounded-3xl border border-red-200 bg-red-50 px-6 py-5 text-sm font-medium text-red-700 shadow-sm"></div>

    <!--
    Main Quiz Card
    Hidden until the quiz successfully loads
    -->
    <div data-tq-card class="mx-auto hidden max-w-4xl overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_28px_80px_rgba(15,23,42,0.10)]">

        <!--
        Quiz Header
        Displays quiz title, subtitle, mode badge, and progress bar
        -->
        <div class="relative overflow-hidden border-b border-slate-200 bg-slate-50 px-6 py-6 sm:px-8">

            <!-- Decorative gradient background -->
            <div class="absolute inset-y-0 right-0 hidden w-40 bg-gradient-to-l from-brand-blue/10 to-transparent sm:block"></div>

            <!-- Quiz Title + Mode Badge -->
            <div class="relative flex items-start justify-between gap-4">
                <div class="space-y-2">

                    <!-- Platform/Brand label -->
                    <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-400">TechiQuiz</p>

                    <!-- Quiz Title -->
                    <h2 data-tq-set-title class="text-xl font-bold leading-tight text-slate-900 sm:text-2xl"></h2>

                    <!-- Quiz subtitle / description -->
                    <p data-tq-set-sub class="text-sm text-slate-500"></p>
                </div>

                <!-- Quiz mode badge (Practice / Exam / Timed etc.) -->
                <span data-tq-mode-badge class="flex-shrink-0 rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.22em]"></span>
            </div>

            <!--
            Progress Bar Section
            Shows quiz completion progress
            -->
            <div class="relative mt-5 flex items-center gap-4">

                <!-- Progress bar background -->
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200">

                    <!-- Actual progress indicator -->
                    <div data-tq-progress-bar class="h-full rounded-full bg-brand-blue transition-all duration-500" style="width:0%"></div>
                </div>

                <!-- Progress text (e.g., Question 3 of 10) -->
                <span data-tq-progress-text class="whitespace-nowrap text-xs font-medium text-slate-500"></span>
            </div>
        </div>

        <!--
        Question Area
        Displays current question and answer choices
        -->
        <div data-tq-question class="px-6 py-8 sm:px-8">

            <!-- Question number + question text -->
            <div class="mb-8 flex gap-4">

                <!-- Question number badge -->
                <div data-tq-q-num
                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-indigo-900 text-sm font-bold text-white shadow-sm">
                </div>

                <!-- Question prompt -->
                <p data-tq-prompt class="pt-1 text-lg font-medium leading-relaxed text-slate-900 sm:text-xl"></p>
            </div>

            <!-- Multiple choice answers container -->
            <div data-tq-choices class="space-y-2.5"></div>

            <!--
            Feedback message
            Shows whether the selected answer was correct or incorrect
            -->
            <div data-tq-feedback class="hidden mt-5 rounded-2xl px-4 py-3 text-sm font-medium"></div>
        </div>

        <!--
        Action Buttons
        - Submit: check selected answer
        - Next: move to next question
        -->
        <div data-tq-actions class="flex items-center justify-between gap-4 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:px-8">

            <!-- Instruction text -->
            <span class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">Select one answer, then submit</span>

            <!-- Action buttons -->
            <div class="flex gap-2">

                <!-- Submit answer button -->
                <button type="button" data-tq-submit
                    class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    Submit
                </button>

                <!-- Next question button (hidden until answer submitted) -->
                <button type="button" data-tq-next
                    class="hidden rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">
                    Next &rarr;
                </button>
            </div>
        </div>

        <!--
        Quiz Completion Screen
        Displayed when the user finishes all questions
        -->
        <div data-tq-finish class="hidden">

            <!-- Final score summary -->
            <div class="border-b border-slate-200 px-6 py-10 text-center sm:px-8">

                <!-- Score circle indicator -->
                <div data-tq-score-circle
                    class="mb-4 inline-flex h-28 w-28 items-center justify-center rounded-full text-3xl font-extrabold">
                </div>

                <!-- Finish title (e.g., "Quiz Completed!") -->
                <h3 data-tq-finish-title class="text-2xl font-bold text-slate-900"></h3>

                <!-- Score label / description -->
                <p data-tq-score-label class="mt-2 text-sm text-slate-500"></p>
            </div>

            <!-- List of missed questions / explanations -->
            <div data-tq-missed class="px-6 py-8 sm:px-8"></div>
        </div>

    </div>
</div>