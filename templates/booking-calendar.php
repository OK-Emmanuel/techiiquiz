<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$booking_json = wp_json_encode( $booking_data );
if ( false === $booking_json ) {
    $booking_json = wp_json_encode( array() );
}
?>
<section class="tq-booking-calendar rounded-3xl border border-slate-700/60 bg-gradient-to-b from-slate-950 via-slate-900 to-slate-800 p-4 shadow-2xl sm:p-6" data-tq-booking-root data-tq-booking-data="<?php echo esc_attr( $booking_json ); ?>">
    <header class="grid gap-4 lg:grid-cols-[1.7fr,0.9fr]">
        <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-slate-300">Class booking</p>
            <h2 class="text-3xl font-black tracking-tight text-white sm:text-4xl"><?php echo esc_html( $booking_data['title'] ?? 'Book a class' ); ?></h2>
            <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-300 sm:text-base"><?php echo esc_html( $booking_data['subtitle'] ?? '' ); ?></p>
        </div>
        <div class="rounded-2xl border border-slate-600/70 bg-white/5 p-4">
            <p class="mb-2 text-xs font-bold uppercase tracking-[0.1em] text-amber-300">Three-month rolling view</p>
            <p class="text-sm leading-relaxed text-slate-300">Choose a school, review capacity, and move to checkout in one step.</p>
        </div>
    </header>

    <div class="mt-5 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-slate-600/70 bg-white/5 p-4">
        <label class="grid min-w-[260px] gap-2">
            <span class="text-sm font-semibold text-slate-300"><?php echo esc_html__( 'Select school', 'techiquiz' ); ?></span>
            <select class="min-h-11 rounded-xl border border-slate-500 bg-slate-900 px-3 text-slate-100 focus:border-amber-400 focus:outline-none" data-tq-booking-filter></select>
        </label>
        <div class="flex flex-wrap items-center gap-4 text-sm text-slate-300" aria-label="Legend">
            <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-emerald-400"></i> Open</span>
            <span class="inline-flex items-center gap-2"><i class="h-3 w-3 rounded-full bg-orange-400"></i> Full</span>
        </div>
    </div>

    <div class="mt-5 grid gap-4" data-tq-booking-schedule>
        <div class="rounded-2xl border border-slate-600/70 bg-white/5 p-5 text-center text-slate-300">Loading schedule...</div>
    </div>

    <noscript>
        <div class="mt-4 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-100">
            JavaScript is required to browse and book classes online. Please contact us if you need help registering.
        </div>
    </noscript>
</section>