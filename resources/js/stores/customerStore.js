import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

/**
 * Feature flag definitions by plan tier.
 * Each flag maps to a list of plan IDs that have access.
 * Plans: 0=Free, 1=Starter, 2=Professional, 3=Enterprise
 */
const PLAN_FEATURES = {
    // Generation engines
    'generate:image':         [0, 1, 2, 3],
    'generate:image:upscale': [1, 2, 3],
    'generate:image:inpaint': [1, 2, 3],
    'generate:image:hd':      [2, 3],
    'generate:image:4k':      [3],
    'generate:video':         [0, 1, 2, 3],
    'generate:video:4k':      [2, 3],
    'generate:video:300s':    [3],
    'generate:text':          [0, 1, 2, 3],
    'generate:text:500k':     [1, 2, 3],
    'generate:text:unlimited':[3],
    'generate:body':          [0, 1, 2, 3],
    'generate:body:smplx':    [2, 3],
    'generate:body:animate':  [3],

    // Revenue streams
    'revenue:nft':            [2, 3],
    'revenue:marketplace':    [2, 3],
    'revenue:tipping':        [1, 2, 3],
    'revenue:affiliate':      [2, 3],
    'revenue:subscription':   [0, 1, 2, 3],

    // Account features
    'account:2fa':            [0, 1, 2, 3],
    'account:passkey':        [0, 1, 2, 3],
    'account:api':            [1, 2, 3],
    'account:api:priority':   [2, 3],
    'account:export':         [1, 2, 3],
    'account:team':           [3],

    // Dashboard widgets
    'dashboard:usage':        [0, 1, 2, 3],
    'dashboard:activity':     [0, 1, 2, 3],
    'dashboard:generation':   [0, 1, 2, 3],
    'dashboard:revenue':      [1, 2, 3],
    'dashboard:security':     [0, 1, 2, 3],
    'dashboard:quickactions': [0, 1, 2, 3],

    // Music Features
    'music:dashboard':      [1, 2, 3],
    'music:connect':        [1, 2, 3],
    'music:analytics':      [2, 3],
    'music:export':         [2, 3],
    'music:realtime':       [1, 2, 3],
    'music:achievements':   [0, 1, 2, 3],
}

const PLAN_LABELS = {
    0: 'Free',
    1: 'Starter',
    2: 'Professional',
    3: 'Enterprise',
}

const PLAN_LIMITS = {
    images:  { 0: 10,   1: 50,   2: 500,   3: Infinity },
    videos:  { 0: 2,    1: 5,    2: 50,    3: Infinity },
    tokens:  { 0: 10000, 1: 50000, 2: 500000, 3: Infinity },
}

/**
 * Feature registry entry schema:
 * {
 *   id: string           - unique feature identifier
 *   title: string         - display name
 *   description: string   - short description
 *   icon: string          - emoji or SVG
 *   requires: string[]    - feature flags needed to render
 *   color: string         - Tailwind color class (e.g. 'violet')
 *   route: string         - Inertia route name (optional)
 *   action: Function      - click handler (optional)
 *   type: string          - 'generation' | 'revenue' | 'security' | 'widget'
 *   generationType: string - 'image' | 'video' | 'text' | 'body' (if type=generation)
 *   stats: object         - live stats to display in the card
 *   badge: string         - optional badge text
 * }
 */

/**
 * Revenue stream registry entry schema:
 * {
 *   id: string
 *   title: string
 *   description: string
 *   icon: string
 *   amount: number
 *   currency: string
 *   trend: 'up' | 'down' | 'flat'
 *   change: number        - percentage change
 *   requires: string[]
 *   color: string
 * }
 */

export const useCustomerStore = defineStore('customer', () => {
    // ─── State ────────────────────────────────────────────────
    const user = ref(null)
    const plan = ref(0)
    const loading = ref(false)
    const error = ref(null)

    const usage = ref({
        images: 0,
        videos: 0,
        tokens: 0,
    })

    const stats = ref({
        totalGenerations: 0,
        totalRevenue: 0,
        activeSubscriptions: 0,
        storageUsed: '0 GB',
        storageLimit: '10 GB',
    })

    const recentActivity = ref([])

    const revenueStreams = ref([])

    const subscription = ref({
        id: null,
        plan: 0,
        status: 'inactive',
        nextBilling: null,
        paymentMethod: 'cold_wallet',
        walletAddress: null,
        autoRenew: true,
        startedAt: null,
        canceledAt: null,
    })

    // ─── Feature Registry ────────────────────────────────────
    const features = ref([
        // ── Generation Section ──
        {
            id: 'gen-image',
            title: 'Image Generation',
            description: 'SDXL-powered artistic, realistic, and anime imagery',
            icon: '🎨',
            requires: ['generate:image'],
            color: 'violet',
            type: 'generation',
            generationType: 'image',
            route: 'generation.image',
            stats: { label: 'Remaining', value: '10/10', key: 'images' },
        },
        {
            id: 'gen-video',
            title: 'Video Generation',
            description: 'AI video up to 4K 60fps with storyboard support',
            icon: '🎬',
            requires: ['generate:video'],
            color: 'pink',
            type: 'generation',
            generationType: 'video',
            route: 'generation.video',
            stats: { label: 'Remaining', value: '2/2', key: 'videos' },
        },
        {
            id: 'gen-text',
            title: 'Text & Novel Generation',
            description: 'Full novels, scripts, and long-form content',
            icon: '📖',
            requires: ['generate:text'],
            color: 'green',
            type: 'generation',
            generationType: 'text',
            route: 'generation.text',
            stats: { label: 'Tokens', value: '10K/10K', key: 'tokens' },
        },
        {
            id: 'gen-body',
            title: 'Body Mapping & 3D',
            description: 'SMPL-X models, face recon, pose estimation',
            icon: '🧍',
            requires: ['generate:body'],
            color: 'orange',
            type: 'generation',
            generationType: 'body',
            route: 'generation.body',
            stats: { label: 'Models', value: '5/day', key: 'bodies' },
        },

        // ── Revenue Section ──
        {
            id: 'rev-nft',
            title: 'NFT Minting Rewards',
            description: 'Mint your generations as NFTs and earn royalties',
            icon: '💎',
            requires: ['revenue:nft'],
            color: 'violet',
            type: 'revenue',
            route: 'revenue.nft',
        },
        {
            id: 'rev-marketplace',
            title: 'Creator Marketplace',
            description: 'Sell prompts, models, and assets in the marketplace',
            icon: '🏪',
            requires: ['revenue:marketplace'],
            color: 'pink',
            type: 'revenue',
            route: 'revenue.marketplace',
        },
        {
            id: 'rev-tipping',
            title: 'Tipping & Donations',
            description: 'Receive crypto tips from your followers',
            icon: '💰',
            requires: ['revenue:tipping'],
            color: 'green',
            type: 'revenue',
            route: 'revenue.tipping',
        },
        {
            id: 'rev-affiliate',
            title: 'Affiliate Program',
            description: 'Earn 20% recurring commission on referrals',
            icon: '🤝',
            requires: ['revenue:affiliate'],
            color: 'orange',
            type: 'revenue',
            route: 'revenue.affiliate',
        },

        // ── Account & Security Section ──
        {
            id: 'sec-passkey',
            title: 'Biometric Passkeys',
            description: 'WebAuthn fingerprint and face recognition',
            icon: '🔐',
            requires: ['account:passkey'],
            color: 'violet',
            type: 'security',
            badge: 'Active',
            route: 'account.passkeys',
        },
        {
            id: 'sec-2fa',
            title: 'Two-Factor Authentication',
            description: 'Hardware key and authenticator app support',
            icon: '🛡️',
            requires: ['account:2fa'],
            color: 'pink',
            type: 'security',
            badge: 'Setup',
            route: 'account.2fa',
        },
        {
            id: 'sec-api',
            title: 'API Access',
            description: 'REST and WebSocket API with documentation',
            icon: '⚡',
            requires: ['account:api'],
            color: 'green',
            type: 'security',
            route: 'account.api',
        },
        {
            id: 'sec-export',
            title: 'Data Export',
            description: 'Export your generations and account data',
            icon: '📦',
            requires: ['account:export'],
            color: 'orange',
            type: 'security',
            route: 'account.export',
        },

        // ── Quick Actions ──
        {
            id: 'quick-upgrade',
            title: 'Upgrade Plan',
            description: 'Unlock more features and higher limits',
            icon: '⬆️',
            requires: ['dashboard:quickactions'],
            color: 'violet',
            type: 'widget',
            route: 'pricing',
            badge: 'Pro Tip',
        },
        {
            id: 'quick-docs',
            title: 'Documentation',
            description: 'API docs, tutorials, and best practices',
            icon: '📚',
            requires: ['dashboard:quickactions'],
            color: 'pink',
            type: 'widget',
            action: () => window.open('https://docs.blbgensixai.club', '_blank'),
        },
    ])

    /**
     * Register additional features dynamically.
     * Call this from any component or plugin to extend the dashboard.
     */
    const registeredExtensions = ref([])

    // ─── Computed ─────────────────────────────────────────────
    const planLabel = computed(() => PLAN_LABELS[plan.value] || 'Free')
    const planLimits = computed(() => ({
        images: PLAN_LIMITS.images[plan.value] || 0,
        videos: PLAN_LIMITS.videos[plan.value] || 0,
        tokens: PLAN_LIMITS.tokens[plan.value] || 0,
    }))

    const usagePercent = computed(() => {
        const limits = planLimits.value
        return {
            images: limits.images === Infinity ? 0 : Math.round((usage.value.images / limits.images) * 100),
            videos: limits.videos === Infinity ? 0 : Math.round((usage.value.videos / limits.videos) * 100),
            tokens: limits.tokens === Infinity ? 0 : Math.round((usage.value.tokens / limits.tokens) * 100),
        }
    })

    const isActive = computed(() => subscription.value.status === 'active')

    const allFeatures = computed(() => [
        ...features.value,
        ...registeredExtensions.value,
    ])

    const availableFeatures = computed(() => ({
        generation: allFeatures.value.filter(f => hasFeature(f.requires)),
        revenue: allFeatures.value.filter(f => f.type === 'revenue' && hasFeature(f.requires)),
        security: allFeatures.value.filter(f => f.type === 'security' && hasFeature(f.requires)),
        widgets: allFeatures.value.filter(f => f.type === 'widget' && hasFeature(f.requires)),
    }))

    // ─── Actions ──────────────────────────────────────────────
    function hasFeature(flags) {
        if (!flags || flags.length === 0) return true
        return flags.every(flag => {
            const allowed = PLAN_FEATURES[flag]
            if (!allowed) return false
            return allowed.includes(plan.value)
        })
    }

    function hasAnyFeature(flags) {
        if (!flags || flags.length === 0) return true
        return flags.some(flag => {
            const allowed = PLAN_FEATURES[flag]
            if (!allowed) return false
            return allowed.includes(plan.value)
        })
    }

    function canGenerate(type) {
        const limits = planLimits.value
        switch (type) {
            case 'image': return usage.value.images < limits.images
            case 'video': return usage.value.videos < limits.videos
            case 'text':  return usage.value.tokens < limits.tokens
            default:      return true
        }
    }

    function remainingQuota(type) {
        const limits = planLimits.value
        switch (type) {
            case 'image': return limits.images === Infinity ? '∞' : limits.images - usage.value.images
            case 'video': return limits.videos === Infinity ? '∞' : limits.videos - usage.value.videos
            case 'text':  return limits.tokens === Infinity ? '∞' : limits.tokens - usage.value.tokens
            default:      return 0
        }
    }

    async function fetchDashboard() {
        loading.value = true
        error.value = null
        try {
            const res = await fetch('/api/v1/customer/dashboard')
            if (!res.ok) throw new Error('Failed to load dashboard')
            const data = await res.json()
            user.value = data.user
            plan.value = data.user?.plan ?? 0
            usage.value = data.usage ?? { images: 0, videos: 0, tokens: 0 }
            stats.value = data.stats ?? stats.value
            recentActivity.value = data.activity ?? []
            revenueStreams.value = data.revenue ?? []
            subscription.value = data.subscription ?? subscription.value
        } catch (e) {
            error.value = e.message
        } finally {
            loading.value = false
        }
    }

    async function generate(type, params = {}) {
        try {
            const res = await fetch('/api/v1/generate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, ...params }),
            })
            const data = await res.json()
            if (!res.ok) throw new Error(data.message || 'Generation failed')
            incrementUsage(type)
            addActivity({
                type: 'generation',
                action: `Generated ${type}`,
                timestamp: new Date().toISOString(),
            })
            return data
        } catch (e) {
            error.value = e.message
            throw e
        }
    }

    function incrementUsage(type) {
        const keyMap = { image: 'images', video: 'videos', text: 'tokens', body: 'bodies' }
        const key = keyMap[type]
        if (key && usage.value[key] !== undefined) {
            usage.value[key]++
        }
        stats.value.totalGenerations++
    }

    function addActivity(entry) {
        recentActivity.value.unshift({
            id: Date.now(),
            ...entry,
        })
        if (recentActivity.value.length > 50) {
            recentActivity.value = recentActivity.value.slice(0, 50)
        }
    }

    /**
     * Register an extension feature dynamically.
     * This allows third-party modules to add cards to the dashboard.
     */
    function registerFeature(feature) {
        if (!feature.id) {
            console.warn('[customerStore] Feature must have an id:', feature)
            return
        }
        const exists = registeredExtensions.value.find(f => f.id === feature.id)
        if (exists) {
            Object.assign(exists, feature)
        } else {
            registeredExtensions.value.push(feature)
        }
    }

    function unregisterFeature(featureId) {
        registeredExtensions.value = registeredExtensions.value.filter(f => f.id !== featureId)
    }

    return {
        // State
        user,
        plan,
        loading,
        error,
        usage,
        stats,
        recentActivity,
        revenueStreams,
        subscription,
        features,
        registeredExtensions,

        // Computed
        planLabel,
        planLimits,
        usagePercent,
        isActive,
        allFeatures,
        availableFeatures,

        // Actions
        hasFeature,
        hasAnyFeature,
        canGenerate,
        remainingQuota,
        fetchDashboard,
        generate,
        incrementUsage,
        addActivity,
        registerFeature,
        unregisterFeature,
    }
})

// Export constants for external use
export { PLAN_FEATURES, PLAN_LABELS, PLAN_LIMITS }
