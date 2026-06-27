<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site promotion (staging → production)
    |--------------------------------------------------------------------------
    |
    | Staging:  dnyanda.ac.in  (103.217.253.53) — test updates here first
    | Production: dnyanda.vss.ac (27.100.38.196) — receives promoted builds only
    |
    */

    'enabled' => env('SITE_PROMOTION_ENABLED', false),

    /** staging | production — controls which UI/actions are available */
    'site_role' => env('SITE_PROMOTION_ROLE', 'production'),

    'staging' => [
        'host' => env('SITE_PROMOTION_STAGING_HOST', '103.217.253.53'),
        'path' => env('SITE_PROMOTION_STAGING_PATH', '/home/dnyanda.ac.in/public_html'),
        'url' => env('SITE_PROMOTION_STAGING_URL', 'https://dnyanda.ac.in'),
    ],

    'production' => [
        'host' => env('SITE_PROMOTION_PROD_HOST', '27.100.38.196'),
        'path' => env('SITE_PROMOTION_PROD_PATH', '/home/dnyanda.vss.ac/public_html'),
        'url' => env('SITE_PROMOTION_PROD_URL', 'https://dnyanda.vss.ac'),
        'deploy_user' => env('SITE_PROMOTION_PROD_USER', 'dnyan5592'),
    ],

    /** Shared secret for staging → production API trigger (set on BOTH servers) */
    'api_secret' => env('SITE_PROMOTION_API_SECRET'),

    /** Path to server-side credentials (chmod 600, not in git) */
    'server_config' => env('SITE_PROMOTION_SERVER_CONFIG', '/home/dnyanda.vss.ac/.site-promotion/config'),

    /** Derived from server_config directory unless overridden */
    'promotion_state_dir' => env('SITE_PROMOTION_STATE_DIR', dirname(
        env('SITE_PROMOTION_SERVER_CONFIG', '/home/dnyanda.vss.ac/.site-promotion/config')
    )),

    /** Bash script executed on production */
    'promote_script' => env('SITE_PROMOTION_SCRIPT', base_path('scripts/site-promotion/promote-pull.sh')),

    'preview_script' => env('SITE_PROMOTION_PREVIEW_SCRIPT', base_path('scripts/site-promotion/promote-preview.sh')),

    /** Sudo wrapper for apply-from-web (see scripts/site-promotion/install-sudoers.sh) */
    'root_wrapper_script' => env('SITE_PROMOTION_ROOT_WRAPPER', base_path('scripts/site-promotion/promote-as-root.sh')),

    /** When true, full promote runs via passwordless sudo as root from the web user */
    'sudo_promote' => env('SITE_PROMOTION_SUDO', true),

    'backup_dir' => env('SITE_PROMOTION_BACKUP_DIR', '/home/dnyanda.vss.ac/backup/code-promotions'),

    'log_dir' => storage_path('logs/site-promotion'),

    'lock_file' => env('SITE_PROMOTION_LOCK', rtrim(dirname(
        env('SITE_PROMOTION_SERVER_CONFIG', '/home/dnyanda.vss.ac/.site-promotion/config')
    ), '/').'/promote.lock'),

    /** Written on production when staging pushes an update notification */
    'pending_file' => env('SITE_PROMOTION_PENDING_FILE', rtrim(dirname(
        env('SITE_PROMOTION_SERVER_CONFIG', '/home/dnyanda.vss.ac/.site-promotion/config')
    ), '/').'/pending-update.json'),

    'maintenance' => [
        'enabled' => env('SITE_PROMOTION_MAINTENANCE', true),
        'secret' => env('SITE_PROMOTION_MAINTENANCE_SECRET', 'promote-in-progress'),
    ],

    'post_migrate_commands' => [
        'optimize:clear',
        'config:cache',
        'route:cache',
        'view:cache',
    ],

];
