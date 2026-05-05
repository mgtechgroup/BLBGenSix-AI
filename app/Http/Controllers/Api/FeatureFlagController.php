<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FeatureFlagController extends Controller
{
    protected AuditLogger $auditLogger;

    protected array $planHierarchy = [
        'free'       => 0,
        'starter'    => 10,
        'pro'        => 20,
        'enterprise' => 30,
    ];

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeFeatureAccess();

        $perPage = min((int) ($request->query('per_page', 50)), 100);
        $category = $request->query('category');

        $query = FeatureFlag::query();

        if ($category) {
            $query->where('category', $category);
        }

        $features = $query->orderBy('category')->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $features->map(fn(FeatureFlag $f) => $this->formatFeature($f, $request->user())),
            'meta'    => [
                'total'        => $features->total(),
                'per_page'     => $features->perPage(),
                'current_page' => $features->currentPage(),
                'last_page'    => $features->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $name): JsonResponse
    {
        $this->authorizeFeatureAccess();

        $feature = FeatureFlag::where('name', $name)->first();

        if (!$feature) {
            return response()->json([
                'success' => false,
                'message' => "Feature '{$name}' not found.",
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatFeature($feature, $request->user()),
        ]);
    }

    public function update(Request $request, string $name): JsonResponse
    {
        $this->authorizeFeatureAccess();

        $feature = FeatureFlag::where('name', $name)->first();

        if (!$feature) {
            return response()->json([
                'success' => false,
                'message' => "Feature '{$name}' not found.",
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'enabled'            => ['sometimes', 'boolean'],
            'rollout_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'min_plan'           => ['sometimes', 'string', Rule::in(array_keys($this->planHierarchy))],
            'description'        => ['sometimes', 'string', 'max:500'],
            'category'           => ['sometimes', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors()->toArray(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $changes = [];
        $before = $feature->only(['enabled', 'rollout_percentage', 'min_plan', 'description', 'category']);

        if ($request->has('enabled')) {
            $feature->enabled = (bool) $request->input('enabled');
            $changes[] = 'enabled';
        }

        if ($request->has('rollout_percentage')) {
            $feature->rollout_percentage = (int) $request->input('rollout_percentage');
            $changes[] = 'rollout_percentage';
        }

        if ($request->has('min_plan')) {
            $feature->min_plan = $request->input('min_plan');
            $changes[] = 'min_plan';
        }

        if ($request->has('description')) {
            $feature->description = $request->input('description');
            $changes[] = 'description';
        }

        if ($request->has('category')) {
            $feature->category = $request->input('category');
            $changes[] = 'category';
        }

        if (empty($changes)) {
            return response()->json([
                'success' => true,
                'message' => 'No changes provided.',
                'data'    => $this->formatFeature($feature, $request->user()),
            ]);
        }

        $feature->save();

        $this->clearFeatureCache();
        $this->auditFeatureChange($feature->name, $before, $feature->only(array_keys($before)), $request);

        return response()->json([
            'success' => true,
            'message' => "Feature '{$name}' updated.",
            'changes' => $changes,
            'data'    => $this->formatFeature($feature->fresh(), $request->user()),
        ]);
    }

    public function toggle(Request $request, string $name): JsonResponse
    {
        $this->authorizeFeatureAccess();

        $feature = FeatureFlag::where('name', $name)->first();

        if (!$feature) {
            return response()->json([
                'success' => false,
                'message' => "Feature '{$name}' not found.",
            ], Response::HTTP_NOT_FOUND);
        }

        $before = $feature->enabled;
        $feature->enabled = !$feature->enabled;
        $feature->save();

        $this->clearFeatureCache();
        $this->auditFeatureChange(
            $feature->name,
            ['enabled' => $before],
            ['enabled' => $feature->enabled],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => "Feature '{$name}' " . ($feature->enabled ? 'enabled' : 'disabled') . '.',
            'data'    => $this->formatFeature($feature->fresh(), $request->user()),
        ]);
    }

    public function forUser(Request $request): JsonResponse
    {
        $user = $request->user();
        $userPlan = $user->subscription_plan ?? 'free';

        $features = FeatureFlag::all()->mapWithKeys(function (FeatureFlag $feature) use ($user, $userPlan) {
            $enabled = $this->isFeatureEnabledForUser($feature, $user, $userPlan);

            return [$feature->name => [
                'enabled'      => $enabled,
                'min_plan'     => $feature->min_plan,
                'description'  => $feature->description,
                'category'     => $feature->category,
            ]];
        });

        return response()->json([
            'success'   => true,
            'user_plan' => $userPlan,
            'features'  => $features,
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $this->authorizeFeatureAccess();

        $categories = FeatureFlag::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $result = [];

        foreach ($categories as $category) {
            $result[$category] = FeatureFlag::where('category', $category)
                ->pluck('name')
                ->toArray();
        }

        return response()->json([
            'success'    => true,
            'categories' => $result,
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorizeFeatureAccess();

        $validator = Validator::make($request->all(), [
            'features'                => ['required', 'array', 'min:1'],
            'features.*.name'        => ['required', 'string'],
            'features.*.enabled'     => ['sometimes', 'boolean'],
            'features.*.rollout_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors()->toArray(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updated = [];
        $notFound = [];

        foreach ($request->input('features') as $input) {
            $feature = FeatureFlag::where('name', $input['name'])->first();

            if (!$feature) {
                $notFound[] = $input['name'];
                continue;
            }

            $before = $feature->only(['enabled', 'rollout_percentage']);

            if (array_key_exists('enabled', $input)) {
                $feature->enabled = (bool) $input['enabled'];
            }

            if (array_key_exists('rollout_percentage', $input)) {
                $feature->rollout_percentage = (int) $input['rollout_percentage'];
            }

            $feature->save();

            $updated[] = $feature->name;
            $this->auditFeatureChange(
                $feature->name,
                $before,
                $feature->only(['enabled', 'rollout_percentage']),
                $request
            );
        }

        $this->clearFeatureCache();

        return response()->json([
            'success'   => true,
            'message'   => count($updated) . ' feature(s) updated.',
            'updated'   => $updated,
            'not_found' => $notFound,
        ]);
    }

    public function audit(Request $request, ?string $name = null): JsonResponse
    {
        $this->authorizeFeatureAccess();

        $logPath = storage_path('logs/security/audit-' . now()->format('Y-m') . '.log');

        if (!file_exists($logPath)) {
            return response()->json([
                'success' => true,
                'data'    => [],
                'message' => 'No audit entries found for this month.',
            ]);
        }

        $entries = [];
        $handle = fopen($logPath, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $entry = json_decode($line, true);

                if (!$entry || ($entry['action'] ?? '') !== 'feature_flag.changed') {
                    continue;
                }

                if ($name && ($entry['context']['feature'] ?? '') !== $name) {
                    continue;
                }

                $entries[] = $entry;
            }
            fclose($handle);
        }

        $perPage = min((int) ($request->query('per_page', 50)), 100);
        $page = (int) $request->query('page', 1);
        $entries = array_reverse($entries);
        $total = count($entries);
        $entries = array_slice($entries, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'success' => true,
            'data'    => $entries,
            'meta'    => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
                'feature'      => $name,
            ],
        ]);
    }

    protected function isFeatureEnabledForUser(FeatureFlag $feature, $user, string $userPlan): bool
    {
        if (!$feature->enabled) {
            return false;
        }

        if ($feature->rollout_percentage < 100) {
            $seed = crc32($feature->name . ':' . ($user->id ?? 'global'));
            $bucket = abs($seed) % 100;
            if ($bucket >= $feature->rollout_percentage) {
                return false;
            }
        }

        $userLevel = $this->planHierarchy[$userPlan] ?? 0;
        $requiredLevel = $this->planHierarchy[$feature->min_plan] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    protected function formatFeature(FeatureFlag $feature, $user = null): array
    {
        $userPlan = $user->subscription_plan ?? 'free';

        return [
            'id'                 => $feature->id,
            'name'               => $feature->name,
            'enabled'            => (bool) $feature->enabled,
            'rollout_percentage' => (int) $feature->rollout_percentage,
            'min_plan'           => $feature->min_plan,
            'description'        => $feature->description,
            'category'           => $feature->category,
            'enabled_for_user'   => $user ? $this->isFeatureEnabledForUser($feature, $user, $userPlan) : null,
            'created_at'         => $feature->created_at?->toIso8601String(),
            'updated_at'         => $feature->updated_at?->toIso8601String(),
        ];
    }

    protected function auditFeatureChange(string $feature, array $before, array $after, Request $request): void
    {
        $this->auditLogger->log('feature_flag.changed', 'feature_flags', [
            'feature'    => $feature,
            'before'     => $before,
            'after'      => $after,
            'changed_by' => $request->user()?->id,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    protected function clearFeatureCache(): void
    {
        Cache::forget('feature_flags:all');
        Cache::forget('feature_flags:enabled');
    }

    protected function authorizeFeatureAccess(): void
    {
        if (!request()->user() || !request()->user()->hasRole('admin')) {
            abort(Response::HTTP_FORBIDDEN, 'Admin role required to manage feature flags.');
        }
    }
}
