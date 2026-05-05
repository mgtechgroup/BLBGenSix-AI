<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;

class ModularRouteService
{
    protected PluginRegistry $registry;

    protected array $loadedRoutes = [];

    protected array $routeMappings = [];

    public function __construct(PluginRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function registerAllPluginRoutes(): int
    {
        $plugins = $this->registry->getEnabled();
        $count = 0;

        foreach ($plugins as $name => $plugin) {
            if ($this->registerPluginRoutes($name)) {
                $count++;
            }
        }

        return $count;
    }

    public function registerPluginRoutes(string $pluginName): bool
    {
        $plugin = $this->registry->getPlugin($pluginName);
        if (!$plugin || !($plugin['enabled'] ?? false)) {
            return false;
        }

        $routesPath = ($plugin['path'] ?? '') . DIRECTORY_SEPARATOR . 'routes';
        $routeConfig = $plugin['routes'] ?? [];

        $prefix = $plugin['route_prefix'] ?? $this->slugify($pluginName);
        $middleware = $plugin['route_middleware'] ?? [];

        $types = ['web', 'api', 'admin'];
        $registered = 0;

        foreach ($types as $type) {
            $typeMiddleware = $middleware[$type] ?? $this->defaultMiddleware($type);
            $typePrefix = is_array($prefix) ? ($prefix[$type] ?? $prefix['default'] ?? $pluginName) : $prefix;

            $routeFile = null;

            if (isset($routeConfig[$type])) {
                $routeFile = $this->resolveRoutePath($routesPath, $routeConfig[$type]);
            } elseif (File::isDirectory($routesPath)) {
                $routeFile = $this->findRouteFile($routesPath, $type);
            }

            if ($routeFile && File::exists($routeFile)) {
                try {
                    $this->loadRouteGroup($type, $typePrefix, $typeMiddleware, $routeFile, $pluginName);
                    $this->loadedRoutes[$pluginName][$type] = $routeFile;
                    $registered++;
                } catch (\Throwable $e) {
                    Log::error("ModularRouteService: Failed to load {$type} routes for '{$pluginName}': " . $e->getMessage());
                }
            }
        }

        return $registered > 0;
    }

    public function registerWebRoutes(string $pluginName, ?string $prefix = null, array $middleware = []): void
    {
        $plugin = $this->registry->getPlugin($pluginName);
        if (!$plugin) {
            throw new RuntimeException("Plugin '{$pluginName}' not found.");
        }

        $routesPath = ($plugin['path'] ?? '') . DIRECTORY_SEPARATOR . 'routes';
        $prefix ??= $plugin['route_prefix'] ?? $this->slugify($pluginName);
        $middleware = $middleware ?: $this->defaultMiddleware('web');

        $routeFile = $this->findRouteFile($routesPath, 'web');

        if (!$routeFile) {
            Log::warning("ModularRouteService: No web routes found for '{$pluginName}'.");
            return;
        }

        $this->loadRouteGroup('web', $prefix, $middleware, $routeFile, $pluginName);
    }

    public function registerApiRoutes(string $pluginName, ?string $prefix = null, array $middleware = []): void
    {
        $plugin = $this->registry->getPlugin($pluginName);
        if (!$plugin) {
            throw new RuntimeException("Plugin '{$pluginName}' not found.");
        }

        $routesPath = ($plugin['path'] ?? '') . DIRECTORY_SEPARATOR . 'routes';
        $prefix ??= $plugin['route_prefix'] ?? 'api/v1/' . $this->slugify($pluginName);
        $middleware = $middleware ?: $this->defaultMiddleware('api');

        $routeFile = $this->findRouteFile($routesPath, 'api');

        if (!$routeFile) {
            Log::warning("ModularRouteService: No API routes found for '{$pluginName}'.");
            return;
        }

        $this->loadRouteGroup('api', $prefix, $middleware, $routeFile, $pluginName);
    }

    public function registerAdminRoutes(string $pluginName, ?string $prefix = null, array $middleware = []): void
    {
        $plugin = $this->registry->getPlugin($pluginName);
        if (!$plugin) {
            throw new RuntimeException("Plugin '{$pluginName}' not found.");
        }

        $routesPath = ($plugin['path'] ?? '') . DIRECTORY_SEPARATOR . 'routes';
        $prefix ??= $plugin['route_prefix'] ?? 'admin/' . $this->slugify($pluginName);
        $middleware = $middleware ?: $this->defaultMiddleware('admin');

        $routeFile = $this->findRouteFile($routesPath, 'admin');

        if (!$routeFile) {
            Log::warning("ModularRouteService: No admin routes found for '{$pluginName}'.");
            return;
        }

        $this->loadRouteGroup('admin', $prefix, $middleware, $routeFile, $pluginName);
    }

    public function unregisterPluginRoutes(string $pluginName): void
    {
        unset($this->loadedRoutes[$pluginName]);
        unset($this->routeMappings[$pluginName]);
    }

    public function getLoadedRoutes(): array
    {
        return $this->loadedRoutes;
    }

    public function getPluginRoutes(string $pluginName): array
    {
        return $this->loadedRoutes[$pluginName] ?? [];
    }

    public function getRouteMappings(): array
    {
        return $this->routeMappings;
    }

    public function scanForRouteFiles(string $basePath): array
    {
        $found = [
            'web'   => [],
            'api'   => [],
            'admin' => [],
        ];

        if (!File::isDirectory($basePath)) {
            return $found;
        }

        $files = File::allFiles($basePath);

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $relativePath = $file->getRelativePathname();

            if (str_contains($filename, 'web') && $file->getExtension() === 'php') {
                $found['web'][] = $relativePath;
            } elseif (str_contains($filename, 'api') && $file->getExtension() === 'php') {
                $found['api'][] = $relativePath;
            } elseif (str_contains($filename, 'admin') && $file->getExtension() === 'php') {
                $found['admin'][] = $relativePath;
            }
        }

        return $found;
    }

    protected function loadRouteGroup(string $type, string $prefix, array $middleware, string $routeFile, string $pluginName): void
    {
        $prefix = trim($prefix, '/');

        Route::middleware($middleware)
            ->prefix($prefix)
            ->name($pluginName . '.')
            ->group($routeFile);

        $this->routeMappings[$pluginName][$type] = [
            'prefix'     => $prefix,
            'middleware' => $middleware,
            'file'       => $routeFile,
        ];

        Log::info("ModularRouteService: Loaded {$type} routes for '{$pluginName}' [/{$prefix}]");
    }

    protected function findRouteFile(string $routesPath, string $type): ?string
    {
        $patterns = [
            $routesPath . DIRECTORY_SEPARATOR . $type . '.php',
            $routesPath . DIRECTORY_SEPARATOR . $type . '_routes.php',
            $routesPath . DIRECTORY_SEPARATOR . 'routes_' . $type . '.php',
        ];

        foreach ($patterns as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        if (File::isDirectory($routesPath)) {
            foreach (File::files($routesPath) as $file) {
                $name = $file->getFilenameWithoutExtension();
                if (stripos($name, $type) !== false) {
                    return $file->getPathname();
                }
            }
        }

        return null;
    }

    protected function resolveRoutePath(string $routesPath, string $configPath): string
    {
        if (File::exists($configPath)) {
            return $configPath;
        }

        $relativePath = $routesPath . DIRECTORY_SEPARATOR . ltrim($configPath, DIRECTORY_SEPARATOR);
        if (File::exists($relativePath)) {
            return $relativePath;
        }

        $absolutePath = base_path(ltrim($configPath, DIRECTORY_SEPARATOR));
        if (File::exists($absolutePath)) {
            return $absolutePath;
        }

        return $relativePath;
    }

    protected function defaultMiddleware(string $type): array
    {
        return match ($type) {
            'api'   => ['api', 'auth:sanctum', 'zero.trust', 'device.trusted'],
            'web'   => ['web'],
            'admin' => ['api', 'auth:sanctum', 'zero.trust', 'role:admin'],
            default => [],
        };
    }

    protected function slugify(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    }
}
