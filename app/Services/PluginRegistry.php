<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PluginRegistry
{
    protected string $manifestPath;

    protected array $plugins = [];

    protected array $booted = [];

    protected array $activated = [];

    protected Collection $dependencies;

    public function __construct()
    {
        $this->manifestPath = config('plugin.manifest_path', storage_path('app/plugins/plugins.json'));
        $this->dependencies = collect();
        $this->ensureManifestDirectory();
        $this->loadManifest();
    }

    public function discover(): array
    {
        $directories = config('plugin.scan_directories', []);
        $discovered = [];

        foreach ($directories as $directory) {
            if (!File::isDirectory($directory)) {
                continue;
            }

            $pluginDirs = File::directories($directory);

            foreach ($pluginDirs as $pluginDir) {
                $manifestFile = $pluginDir . DIRECTORY_SEPARATOR . 'plugin.json';

                if (!File::exists($manifestFile)) {
                    continue;
                }

                try {
                    $manifest = json_decode(File::get($manifestFile), true, 512, JSON_THROW_ON_ERROR);

                    if (!$this->validateManifest($manifest)) {
                        Log::warning('PluginRegistry: Invalid manifest in ' . $pluginDir);
                        continue;
                    }

                    $pluginName = $manifest['name'];
                    $manifest['path'] = $pluginDir;
                    $manifest['installed'] = $this->plugins[$pluginName]['installed'] ?? false;
                    $manifest['enabled'] = $this->plugins[$pluginName]['enabled'] ?? false;

                    $discovered[$pluginName] = $manifest;
                } catch (\JsonException $e) {
                    Log::error('PluginRegistry: JSON parse error in ' . $manifestFile . ': ' . $e->getMessage());
                }
            }
        }

        $this->plugins = array_merge($this->plugins, $discovered);
        $this->saveManifest();

        return $discovered;
    }

    public function register(string $name): bool
    {
        if (!isset($this->plugins[$name])) {
            throw new RuntimeException("Plugin '{$name}' not found in registry.");
        }

        $plugin = $this->plugins[$name];

        if (!$this->checkDependencies($name)) {
            Log::warning("PluginRegistry: Cannot register '{$name}' — unmet dependencies.");
            return false;
        }

        $this->executeHook($name, 'register');

        $this->loadRoutes($name);
        $this->loadViews($name);

        $this->plugins[$name]['registered'] = true;
        $this->saveManifest();

        Log::info("PluginRegistry: Plugin '{$name}' registered.");
        return true;
    }

    public function boot(string $name): bool
    {
        if (!isset($this->plugins[$name])) {
            return false;
        }

        if (in_array($name, $this->booted, true)) {
            return true;
        }

        $this->executeHook($name, 'boot');
        $this->booted[] = $name;

        Log::info("PluginRegistry: Plugin '{$name}' booted.");
        return true;
    }

    public function activate(string $name): bool
    {
        if (!isset($this->plugins[$name])) {
            throw new RuntimeException("Plugin '{$name}' not found.");
        }

        $plugin = &$this->plugins[$name];

        if ($plugin['enabled']) {
            return true;
        }

        foreach ($plugin['dependencies'] ?? [] as $dep) {
            if (!$this->isEnabled($dep)) {
                $this->activate($dep);
            }
        }

        $this->executeHook($name, 'activate');
        $plugin['enabled'] = true;
        $plugin['installed'] = true;
        $this->activated[] = $name;

        $this->clearCache();
        $this->saveManifest();

        Log::info("PluginRegistry: Plugin '{$name}' activated.");
        return true;
    }

    public function deactivate(string $name): bool
    {
        if (!isset($this->plugins[$name])) {
            throw new RuntimeException("Plugin '{$name}' not found.");
        }

        $dependents = $this->getDependents($name);
        foreach ($dependents as $dependent) {
            if ($this->isEnabled($dependent)) {
                throw new RuntimeException(
                    "Cannot deactivate '{$name}' — required by active plugin '{$dependent}'."
                );
            }
        }

        $this->executeHook($name, 'deactivate');

        $this->plugins[$name]['enabled'] = false;
        $this->activated = array_values(array_diff($this->activated, [$name]));

        $this->clearCache();
        $this->saveManifest();

        Log::info("PluginRegistry: Plugin '{$name}' deactivated.");
        return true;
    }

    public function uninstall(string $name): bool
    {
        if (!isset($this->plugins[$name])) {
            throw new RuntimeException("Plugin '{$name}' not found.");
        }

        if ($this->isEnabled($name)) {
            $this->deactivate($name);
        }

        $this->executeHook($name, 'uninstall');

        unset($this->plugins[$name]);
        $this->booted = array_values(array_diff($this->booted, [$name]));
        $this->activated = array_values(array_diff($this->activated, [$name]));

        $this->clearCache();
        $this->saveManifest();

        Log::info("PluginRegistry: Plugin '{$name}' uninstalled.");
        return true;
    }

    public function install(string $name): bool
    {
        if (isset($this->plugins[$name]['installed']) && $this->plugins[$name]['installed']) {
            return true;
        }

        $discovered = $this->discover();

        if (!isset($discovered[$name])) {
            throw new RuntimeException("Plugin '{$name}' not found during discovery.");
        }

        $this->executeHook($name, 'install');

        $this->plugins[$name]['installed'] = true;
        $this->saveManifest();

        Log::info("PluginRegistry: Plugin '{$name}' installed.");
        return true;
    }

    public function isEnabled(string $name): bool
    {
        return $this->plugins[$name]['enabled'] ?? false;
    }

    public function isRegistered(string $name): bool
    {
        return $this->plugins[$name]['registered'] ?? false;
    }

    public function getPlugin(string $name): ?array
    {
        return $this->plugins[$name] ?? null;
    }

    public function getAll(): array
    {
        return $this->plugins;
    }

    public function getEnabled(): array
    {
        return array_filter($this->plugins, fn(array $p): bool => $p['enabled'] ?? false);
    }

    public function getDisabled(): array
    {
        return array_filter($this->plugins, fn(array $p): bool => !($p['enabled'] ?? false));
    }

    public function checkDependencies(string $name): bool
    {
        $plugin = $this->plugins[$name] ?? null;
        if (!$plugin) {
            return false;
        }

        foreach ($plugin['dependencies'] ?? [] as $dep) {
            $constraint = null;
            $depName = $dep;

            if (str_contains($dep, ':')) {
                [$depName, $constraint] = explode(':', $dep, 2);
            }

            if (!isset($this->plugins[$depName]) || !$this->isEnabled($depName)) {
                Log::warning("PluginRegistry: Dependency '{$depName}' not satisfied for '{$name}'.");
                return false;
            }

            if ($constraint && !$this->versionSatisfies($this->plugins[$depName]['version'], $constraint)) {
                Log::warning("PluginRegistry: Version constraint '{$constraint}' not met for dependency '{$depName}'.");
                return false;
            }
        }

        return true;
    }

    public function getDependents(string $name): array
    {
        $dependents = [];

        foreach ($this->plugins as $pluginName => $plugin) {
            foreach ($plugin['dependencies'] ?? [] as $dep) {
                $depName = str_contains($dep, ':') ? explode(':', $dep, 2)[0] : $dep;
                if ($depName === $name) {
                    $dependents[] = $pluginName;
                    break;
                }
            }
        }

        return $dependents;
    }

    public function resolveDependencyOrder(): array
    {
        $resolved = [];
        $unresolved = [];

        foreach ($this->plugins as $name => $plugin) {
            $this->resolvePlugin($name, $resolved, $unresolved);
        }

        return $resolved;
    }

    public function bootAll(): void
    {
        $order = $this->resolveDependencyOrder();

        foreach ($order as $name) {
            if ($this->isEnabled($name)) {
                $this->register($name);
                $this->boot($name);
            }
        }
    }

    public function getRoutesFor(string $name): array
    {
        $plugin = $this->plugins[$name] ?? null;
        if (!$plugin) {
            return [];
        }

        return $plugin['routes'] ?? [];
    }

    public function getViewsFor(string $name): array
    {
        $plugin = $this->plugins[$name] ?? null;
        if (!$plugin) {
            return [];
        }

        $viewsPath = ($plugin['path'] ?? '') . DIRECTORY_SEPARATOR . 'views';
        if (!File::isDirectory($viewsPath)) {
            return [];
        }

        return [$name => $viewsPath];
    }

    public function syncFromModules(): int
    {
        $modulesPath = config('modules.paths.modules', base_path('Modules'));
        $count = 0;

        if (!File::isDirectory($modulesPath)) {
            return 0;
        }

        $moduleDirs = File::directories($modulesPath);

        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            $manifestFile = $moduleDir . DIRECTORY_SEPARATOR . 'plugin.json';

            if (File::exists($manifestFile)) {
                continue;
            }

            $manifest = [
                'name'         => $moduleName,
                'version'      => '1.0.0',
                'description'  => "Module: {$moduleName}",
                'dependencies' => [],
                'routes'       => [
                    'api'   => "Modules\\{$moduleName}\\routes\\api.php",
                    'web'   => "Modules\\{$moduleName}\\routes\\web.php",
                ],
                'autoload'     => false,
                'enabled'      => true,
                'installed'    => true,
            ];

            File::put($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $count++;
        }

        return $count;
    }

    protected function executeHook(string $name, string $hook): void
    {
        $plugin = $this->plugins[$name] ?? null;
        if (!$plugin) {
            return;
        }

        $hooks = $plugin['hooks'] ?? [];
        $callback = $hooks[$hook] ?? null;

        if ($callback === null) {
            return;
        }

        if (is_string($callback) && class_exists($callback)) {
            $instance = app($callback);
            if (method_exists($instance, $hook)) {
                app()->call([$instance, $hook], ['plugin' => $plugin]);
            }
        } elseif (is_callable($callback)) {
            app()->call($callback, ['plugin' => $plugin]);
        }
    }

    protected function loadRoutes(string $name): void
    {
        $plugin = $this->plugins[$name] ?? null;
        if (!$plugin) {
            return;
        }

        $path = $plugin['path'] ?? '';

        foreach (($plugin['routes'] ?? []) as $type => $routeFile) {
            $routePath = $path . DIRECTORY_SEPARATOR . $routeFile;

            if (!File::exists($routePath)) {
                $routePath = base_path(ltrim($routeFile, DIRECTORY_SEPARATOR));
            }

            if (!File::exists($routePath)) {
                continue;
            }

            try {
                $prefix = $plugin['route_prefix'] ?? $name;
                $middleware = $plugin['route_middleware'] ?? $this->getDefaultMiddleware($type);

                require $routePath;
            } catch (\Throwable $e) {
                Log::error("PluginRegistry: Failed to load routes for '{$name}' ({$type}): " . $e->getMessage());
            }
        }
    }

    protected function loadViews(string $name): void
    {
        $viewsPath = ($this->plugins[$name]['path'] ?? '') . DIRECTORY_SEPARATOR . 'views';

        if (File::isDirectory($viewsPath)) {
            $hints = config('view.hints', []);
            $hints[$name] = $viewsPath;
            config(['view.hints' => $hints]);
        }
    }

    protected function getDefaultMiddleware(string $type): array
    {
        return match ($type) {
            'api'   => ['api', 'auth:sanctum', 'zero.trust', 'device.trusted'],
            'web'   => ['web'],
            'admin' => ['api', 'auth:sanctum', 'zero.trust', 'role:admin'],
            default => [],
        };
    }

    protected function validateManifest(array $manifest): bool
    {
        $required = ['name', 'version'];

        foreach ($required as $key) {
            if (empty($manifest[$key])) {
                return false;
            }
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $manifest['name'])) {
            return false;
        }

        if (!preg_match('/^\d+\.\d+\.\d+/', $manifest['version'])) {
            return false;
        }

        return true;
    }

    protected function versionSatisfies(string $version, string $constraint): bool
    {
        $version = ltrim($version, 'vV');
        $constraint = trim($constraint);

        if ($constraint === '*' || $constraint === 'any') {
            return true;
        }

        if (preg_match('/^>=?\s*(\d+\.\d+\.\d+)/', $constraint, $m)) {
            $op = str_contains($constraint, '>=') ? '>=' : '>';
            return version_compare($version, $m[1], $op);
        }

        if (preg_match('/^<=?\s*(\d+\.\d+\.\d+)/', $constraint, $m)) {
            $op = str_contains($constraint, '<=') ? '<=' : '<';
            return version_compare($version, $m[1], $op);
        }

        if (preg_match('/^(\d+\.\d+\.\d+)\s*-\s*(\d+\.\d+\.\d+)/', $constraint, $m)) {
            return version_compare($version, $m[1], '>=') && version_compare($version, $m[2], '<=');
        }

        if (preg_match('/^~(\d+\.\d+)/', $constraint, $m)) {
            $lower = $m[1] . '.0';
            $upper = $m[1] . '.999';
            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<=');
        }

        if (preg_match('/^\^(\d+)\.(\d+)\.(\d+)/', $constraint, $m)) {
            $lower = $m[1] . '.' . $m[2] . '.' . $m[3];
            $upper = ((int)$m[1] + 1) . '.0.0';
            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }

        return $version === $constraint;
    }

    protected function resolvePlugin(string $name, array &$resolved, array &$unresolved): void
    {
        if (in_array($name, $resolved, true)) {
            return;
        }

        if (in_array($name, $unresolved, true)) {
            throw new RuntimeException("Circular dependency detected for plugin '{$name}'.");
        }

        $unresolved[] = $name;

        foreach ($this->plugins[$name]['dependencies'] ?? [] as $dep) {
            $depName = str_contains($dep, ':') ? explode(':', $dep, 2)[0] : $dep;
            $this->resolvePlugin($depName, $resolved, $unresolved);
        }

        $resolved[] = $name;
        $unresolved = array_values(array_diff($unresolved, [$name]));
    }

    protected function loadManifest(): void
    {
        if (File::exists($this->manifestPath)) {
            try {
                $data = json_decode(File::get($this->manifestPath), true, 512, JSON_THROW_ON_ERROR);
                $this->plugins = $data['plugins'] ?? [];
            } catch (\JsonException $e) {
                Log::error('PluginRegistry: Failed to parse manifest: ' . $e->getMessage());
                $this->plugins = [];
            }
        }
    }

    protected function saveManifest(): void
    {
        $data = [
            'version'     => '1.0.0',
            'updated_at'  => now()->toIso8601String(),
            'plugins'     => $this->plugins,
        ];

        try {
            File::put(
                $this->manifestPath,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                LOCK_EX
            );
        } catch (\JsonException $e) {
            Log::error('PluginRegistry: Failed to save manifest: ' . $e->getMessage());
        }

        $this->clearCache();
    }

    protected function ensureManifestDirectory(): void
    {
        $directory = dirname($this->manifestPath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    protected function clearCache(): void
    {
        if (config('plugin.cache.enabled', false)) {
            Cache::forget(config('plugin.cache.key', 'plugin_registry'));
        }
    }

    public function warmCache(): void
    {
        if (config('plugin.cache.enabled', false)) {
            Cache::put(
                config('plugin.cache.key', 'plugin_registry'),
                $this->plugins,
                config('plugin.cache.ttl', 3600)
            );
        }
    }

    public function getFromCache(): ?array
    {
        if (!config('plugin.cache.enabled', false)) {
            return null;
        }

        return Cache::get(config('plugin.cache.key', 'plugin_registry'));
    }
}
