import { computed } from 'vue'
import { useCustomerStore, PLAN_FEATURES } from '@/stores/customerStore'

/**
 * Composable for checking feature flags and plan-based access.
 *
 * Usage:
 *   const { hasFeature, canGenerate, planLabel, isEnterprise } = useFeatures()
 *   if (hasFeature('generate:image:4k')) { ... }
 *   if (canGenerate('image')) { ... }
 *
 * The composable is reactive — it re-evaluates when the plan or usage changes.
 */
export function useFeatures() {
    const store = useCustomerStore()

    /**
     * Check if a single feature flag is available for the current plan.
     */
    const hasFeature = (flag) => store.hasFeature([flag])

    /**
     * Check if any of the given feature flags are available.
     */
    const hasAnyFeature = (flags) => store.hasAnyFeature(flags)

    /**
     * Check if ALL the given feature flags are available.
     */
    const hasAllFeatures = (flags) => store.hasFeature(flags)

    /**
     * Check if the user has remaining quota for a generation type.
     */
    const canGenerate = (type) => store.canGenerate(type)

    /**
     * Get remaining quota count for a generation type.
     */
    const remainingQuota = (type) => store.remainingQuota(type)

    /**
     * Get the current plan tier number.
     */
    const plan = computed(() => store.plan)

    /**
     * Human-readable plan name.
     */
    const planLabel = computed(() => store.planLabel)

    /**
     * Boolean shortcuts for common plan checks.
     */
    const isFree = computed(() => store.plan === 0)
    const isStarter = computed(() => store.plan >= 1)
    const isProfessional = computed(() => store.plan >= 2)
    const isEnterprise = computed(() => store.plan === 3)
    const isActive = computed(() => store.isActive)

    /**
     * Reactive usage data.
     */
    const usage = computed(() => store.usage)
    const usagePercent = computed(() => store.usagePercent)
    const planLimits = computed(() => store.planLimits)

    /**
     * Get all feature flags available for the current plan as a flat object.
     * { 'generate:image': true, 'generate:image:4k': false, ... }
     */
    const featureFlags = computed(() => {
        const currentPlan = store.plan
        const flags = {}
        for (const [flag, plans] of Object.entries(PLAN_FEATURES)) {
            flags[flag] = plans.includes(currentPlan)
        }
        return flags
    })

    /**
     * The feature flags by plan tier. Useful for plan comparison.
     */
    const featureFlagsByPlan = computed(() => {
        const result = {}
        for (const [flag, plans] of Object.entries(PLAN_FEATURES)) {
            result[flag] = plans
        }
        return result
    })

    /**
     * Filter a list of features by their required flags.
     * Each item in the list should have a `requires` array of flag strings.
     */
    function filterByFeatures(items) {
        return items.filter(item => {
            if (!item.requires || item.requires.length === 0) return true
            return store.hasFeature(item.requires)
        })
    }

    /**
     * Build a feature gate proxy for conditional rendering.
     * Usage: v-if="gate['generate:image:4k']"
     */
    const gate = computed(() => {
        const currentPlan = store.plan
        return new Proxy({}, {
            get(_, flag) {
                const plans = PLAN_FEATURES[flag]
                return plans ? plans.includes(currentPlan) : false
            }
        })
    })

    return {
        // Checks
        hasFeature,
        hasAnyFeature,
        hasAllFeatures,
        canGenerate,
        remainingQuota,

        // Plan info
        plan,
        planLabel,
        isFree,
        isStarter,
        isProfessional,
        isEnterprise,
        isActive,

        // Usage
        usage,
        usagePercent,
        planLimits,

        // Feature flags
        featureFlags,
        featureFlagsByPlan,
        gate,

        // Utilities
        filterByFeatures,
    }
}
