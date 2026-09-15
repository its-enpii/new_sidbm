<?php

declare(strict_types=1);

return [
    /**
     * Optional default persona slug when widget does not pass ?persona=.
     */
    'default_persona_slug' => (string) env('ASSISTANT_PERSONA_SLUG', ''),

    /**
     * System actor fallback — User row_id used when a tool is invoked without
     * an authenticated user (e.g. cron jobs). 0 → use first superadmin.
     */
    'system_actor_user_id' => (int) env('ASSISTANT_SYSTEM_ACTOR_USER_ID', 0),

    /**
     * Toggle the floating chat widget globally.
     */
    'widget_enabled' => filter_var(env('ASSISTANT_WIDGET_ENABLED', true), FILTER_VALIDATE_BOOL),

    /**
     * Izinkan rute native enpii/assistant (tanpa prefix: /chat, /persona,
     * /confirmations/*, /messages/*, /conversations/*) tetap terdaftar.
     *
     * Default false: host me-mount berkas rute yang sama di /assistant/* memakai
     * middleware auth + tenant + subscription.active + feature:ai, sehingga salinan
     * tanpa prefix hanya membuka LLM dan endpoint konfirmasi aksi untuk request
     * anonim. Jangan aktifkan kembali tanpa konsumen eksternal yang sah.
     */
    'register_native_routes' => filter_var(env('ASSISTANT_REGISTER_NATIVE_ROUTES', false), FILTER_VALIDATE_BOOL),
];
