<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plugin Directory Paths
    |--------------------------------------------------------------------------
    |
    | Directories scanned for plugin.json manifests during discovery.
    | Plugins are loaded from these paths in priority order.
    |
    */
    'directories' => [
        'plugins'   => base_path('Plugins'),
        'modules'   => base_path('Modules'),
        'themes'    => base_path('Themes'),
        'packages'  => base_path('Packages'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Scan Directories
    |--------------------------------------------------------------------------
    |
    | Subset of directories above that are actively scanned for plugins.
    | Set to empty array to disable auto-discovery entirely.
    |
    */
    'scan_directories' => [
        base_path('Plugins'),
        base_path('Modules'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Load Plugins
    |--------------------------------------------------------------------------
    |
    | Plugins listed here are automatically loaded and enabled on boot.
    | These are essential plugins required for platform operation.
    |
    */
    'autoload' => [
        // 'Auth',
        // 'ImageGeneration',
        // 'VideoGeneration',
        // 'TextGeneration',
        // 'BodyMapping',
        // 'SaaS',
        // 'Payments',
        // 'IncomeAutomation',
        // 'AdMonetization',
        // 'MultiRevenue',
        // 'Analytics',
        // 'Admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled Plugins (Runtime)
    |--------------------------------------------------------------------------
    |
    | Additional plugins to enable at runtime beyond autoload.
    | Managed via admin panel / API — manual edits here may be overwritten.
    |
    */
    'enabled' => [],

    /*
    |--------------------------------------------------------------------------
    | Disabled Plugins
    |--------------------------------------------------------------------------
    |
    | Plugins explicitly disabled regardless of autoload status.
    |
    */
    'disabled' => [],

    /*
    |--------------------------------------------------------------------------
    | Plugin Manifest
    |--------------------------------------------------------------------------
    |
    | Path to the JSON manifest storing plugin state, metadata, and registry.
    |
    */
    'manifest_path' => storage_path('app/plugins/plugins.json'),

    /*
    |--------------------------------------------------------------------------
    | Plugin Cache Settings
    |--------------------------------------------------------------------------
    |
    | When enabled, the plugin registry is cached to reduce filesystem I/O.
    | Cache is invalidated automatically on plugin state changes.
    |
    */
    'cache' => [
        'enabled'  => env('PLUGIN_CACHE_ENABLED', true),
        'key'      => env('PLUGIN_CACHE_KEY', 'plugin_registry'),
        'ttl'      => env('PLUGIN_CACHE_TTL', 3600),
        'store'    => env('PLUGIN_CACHE_STORE', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Registration
    |--------------------------------------------------------------------------
    |
    | Controls how plugin routes are registered.
    |
    */
    'routes' => [
        'register_on_boot'  => true,
        'scan_subdirs'      => true,
        'cache_routes'      => env('PLUGIN_ROUTE_CACHE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | View Registration
    |--------------------------------------------------------------------------
    |
    | Plugins can register Blade view namespaces. When enabled, views in
    | plugin/views/ are registered as view::pluginname::viewname.
    |
    */
    'views' => [
        'register_on_boot' => true,
        'namespace_prefix' => 'plugin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Defaults
    |--------------------------------------------------------------------------
    |
    | Default middleware applied to plugin routes by type.
    | Override per-plugin via middleware in plugin.json.
    |
    */
    'default_middleware' => [
        'web'   => ['web'],
        'api'   => ['api', 'auth:sanctum', 'zero.trust', 'device.trusted'],
        'admin' => ['api', 'auth:sanctum', 'zero.trust', 'role:admin'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hook Classes
    |--------------------------------------------------------------------------
    |
    | Global hook classes that run for every plugin lifecycle event.
    | Each class must have methods matching hook names (install, activate, etc.)
    |
    */
    'global_hooks' => [
        // \App\Plugins\Hooks\AuditHook::class,
        // \App\Plugins\Hooks\CacheWarmHook::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin Signature Verification
    |--------------------------------------------------------------------------
    |
    | When enabled, plugin.json files require a valid SHA-256 signature
    | stored in plugin.json.sig to prevent tampering.
    |
    */
    'signature_verification' => [
        'enabled'       => env('PLUGIN_SIGNATURE_VERIFY', false),
        'public_key'    => env('PLUGIN_SIGNATURE_PUBLIC_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketplace / Remote Registry
    |--------------------------------------------------------------------------
    |
    | Optional remote plugin registry for discovering and installing plugins
    | from a central marketplace.
    |
    */
    'marketplace' => [
        'enabled'   => env('PLUGIN_MARKETPLACE_ENABLED', false),
        'url'       => env('PLUGIN_MARKETPLACE_URL'),
        'api_key'   => env('PLUGIN_MARKETPLACE_API_KEY'),
        'timeout'   => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox / Safe Mode
    |--------------------------------------------------------------------------
    |
    | When safe mode is enabled, only autoload plugins load.
    | All runtime-enabled plugins are ignored — useful for recovery.
    |
    */
    'safe_mode' => env('PLUGIN_SAFE_MODE', false),
];
