<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$booking_json = wp_json_encode( $booking_data );
if ( false === $booking_json ) {
    $booking_json = wp_json_encode( array() );
}
?>
<section class="tq-booking-calendar rounded-3xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-4 shadow-xl sm:p-6" data-tq-booking-root data-tq-booking-data="<?php echo esc_attr( $booking_json ); ?>">
    <header class="grid gap-4 lg:grid-cols-[1.7fr,0.9fr]">
        <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-brand-blue">Class booking</p>
            <h2 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl"><?php echo esc_html( $booking_data['title'] ?? 'Book a class' ); ?></h2>
            <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600 sm:text-base"><?php echo esc_html( $booking_data['subtitle'] ?? '' ); ?></p>
        </div>
        <div class="rounded-2xl border border-brand-blue/25 bg-brand-blue/5 p-4">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.1em] text-brand-red">Three-month rolling view</p>
            <p class="text-sm leading-relaxed text-slate-700">Choose a school, review capacity, and move to checkout in one step.</p>
        </div>
    </header>

    <div class="mt-5 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4">
        <label class="grid min-w-[260px] gap-2">
            <span class="text-sm font-semibold text-slate-700"><?php echo esc_html__( 'Select school', 'techiquiz' ); ?></span>
            <select class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-slate-900 focus:border-brand-blue focus:outline-none" data-tq-booking-filter></select>
        </label>
        <div class="flex flex-wrap items-center gap-4 text-sm text-slate-700" aria-label="Legend">
            <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-emerald-400"></i> Open</span>
            <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-red-500"></i> Full</span>
        </div>
    </div>

    <div class="mt-5 grid gap-4" data-tq-booking-schedule>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-center text-slate-600">Loading schedule...</div>
    </div>

    <div class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3">
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-full border border-brand-blue/35 bg-brand-blue/10 px-3 py-2 text-sm font-semibold text-brand-blue hover:bg-brand-blue/20 disabled:cursor-not-allowed disabled:opacity-40"
            data-tq-booking-prev
        >
            Previous
        </button>
        <p class="text-sm font-bold text-slate-700" data-tq-booking-month-label>Month</p>
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-full border border-brand-blue/35 bg-brand-blue/10 px-3 py-2 text-sm font-semibold text-brand-blue hover:bg-brand-blue/20 disabled:cursor-not-allowed disabled:opacity-40"
            data-tq-booking-next
        >
            Next
        </button>
    </div>

    <noscript>
        <div class="mt-4 rounded-xl border border-brand-red/30 bg-brand-red/10 p-3 text-sm text-brand-red">
            JavaScript is required to browse and book classes online. Please contact us if you need help registering.
        </div>
    </noscript>
</section>