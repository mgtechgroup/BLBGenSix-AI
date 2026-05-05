<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCustomerStore } from '@/stores/customerStore'
import { useFeatures } from '@/composables/useFeatures'
import { api, endpoints } from '@/lib/api'

const store = useCustomerStore()
const { planLabel, plan, isActive } = useFeatures()

// ─── State ────────────────────────────────────────────────
const loading = ref(false)
const error = ref(null)
const showUpgradeModal = ref(false)
const showDowngradeModal = ref(false)
const targetPlan = ref(null)
const paymentMethod = ref('crypto')
const selectedCrypto = ref('BTC')
const autoRenew = ref(true)
const activeTab = ref('overview')
const invoices = ref([])
const refunds = ref([])
const cryptoAddresses = ref({})
const walletConnected = ref(false)
const walletAddress = ref('')

// ─── Plan Definitions ─────────────────────────────────────
const plans = ref([
    {
        id: 0,
        name: 'Free',
        price: 0,
        interval: 'forever',
        features: ['10 images/mo', '2 videos/mo', '10K tokens', 'Basic support'],
        limits: { images: 10, videos: 2, tokens: 10000 },
        color: 'gray',
        popular: false,
    },
    {
        id: 1,
        name: 'Starter',
        price: 29.99,
        interval: 'month',
        features: ['50 images/mo', '5 videos/mo', '50K tokens', 'Email support', 'API access', 'Up to 1080p video'],
        limits: { images: 50, videos: 5, tokens: 50000 },
        color: 'violet',
        popular: false,
    },
    {
        id: 2,
        name: 'Professional',
        price: 99.99,
        interval: 'month',
        features: ['500 images/mo', '50 videos/mo', '500K tokens', 'Priority support', 'NFT minting', '4K video', 'Marketplace'],
        limits: { images: 500, videos: 50, tokens: 500000 },
        color: 'pink',
        popular: true,
    },
    {
        id: 3,
        name: 'Enterprise',
        price: 299.99,
        interval: 'month',
        features: ['Unlimited images', 'Unlimited videos', 'Unlimited tokens', 'Dedicated support', 'Custom models', 'Team access', 'SLA guarantee'],
        limits: { images: Infinity, videos: Infinity, tokens: Infinity },
        color: 'gradient',
        popular: false,
    },
])

const cryptoOptions = [
    { id: 'BTC', name: 'Bitcoin', symbol: '₿', address: '' },
    { id: 'ETH', name: 'Ethereum', symbol: 'Ξ', address: '' },
    { id: 'SOL', name: 'Solana', symbol: '◎', address: '' },
    { id: 'ADA', name: 'Cardano', symbol: '₳', address: '' },
    { id: 'DOGE', name: 'Dogecoin', symbol: 'Ð', address: '' },
    { id: 'MATIC', name: 'Polygon', symbol: '⬡', address: '' },
    { id: 'AVAX', name: 'Avalanche', symbol: '🔺', address: '' },
]

// ─── Computed ─────────────────────────────────────────────
const currentPlan = computed(() =>
    plans.value.find(p => p.id === store.plan) || plans.value[0]
)

const usagePercent = computed(() => store.usagePercent)

const planColorClass = (plan) => {
    const map = {
        gray: 'border-gray-700 bg-gray-800/30',
        violet: 'border-violet-500/50 bg-violet-900/20',
        pink: 'border-pink-500/50 bg-pink-900/20',
        gradient: 'border-gradient bg-gradient-to-br from-violet-900/20 to-pink-900/20',
    }
    return map[plan.color] || map.gray
}

const planButtonClass = (plan) => {
    if (plan.id === store.plan) return 'bg-[#1a1a24] text-gray-500 cursor-default'
    if (plan.id < store.plan) return 'bg-red-600/20 text-red-400 border border-red-500/30 hover:bg-red-600/30'
    return 'bg-violet-600 hover:bg-violet-700 text-white'
}

// ─── Actions ──────────────────────────────────────────────
async function fetchBillingData() {
    loading.value = true
    error.value = null
    try {
        const [invoicesRes, refundsRes, cryptoRes, walletRes] = await Promise.all([
            endpoints.billing.invoices(),
            endpoints.billing.refunds(),
            endpoints.billing.cryptoAddresses(),
            endpoints.wallet.status(),
        ])
        invoices.value = invoicesRes.data || invoicesRes || []
        refunds.value = refundsRes.data || refundsRes || []
        cryptoAddresses.value = cryptoRes.data || cryptoRes || {}
        walletConnected.value = walletRes.connected || false
        walletAddress.value = walletRes.address || ''
        autoRenew.value = store.subscription.autoRenew ?? true

        // Update crypto options with addresses
        cryptoOptions.forEach(opt => {
            opt.address = cryptoAddresses.value[opt.id] || ''
        })
    } catch (e) {
        error.value = e.message || 'Failed to load billing data'
    } finally {
        loading.value = false
    }
}

async function handleUpgrade(plan) {
    targetPlan.value = plan
    if (plan.price === 0) {
        showDowngradeModal.value = true
        return
    }
    showUpgradeModal.value = true
}

async function confirmUpgrade() {
    if (!targetPlan.value) return
    loading.value = true
    try {
        await endpoints.billing.subscribe(targetPlan.value.id, paymentMethod.value)
        showUpgradeModal.value = false
        await store.fetchDashboard()
        window.$toast?.success(`Upgraded to ${targetPlan.value.name}!`)
        router.reload()
    } catch (e) {
        window.$toast?.error(e.message || 'Upgrade failed')
    } finally {
        loading.value = false
        targetPlan.value = null
    }
}

async function confirmDowngrade() {
    if (!targetPlan.value) return
    loading.value = true
    try {
        await endpoints.billing.subscribe(targetPlan.value.id)
        showDowngradeModal.value = false
        await store.fetchDashboard()
        window.$toast?.success(`Downgraded to ${targetPlan.value.name}`)
        router.reload()
    } catch (e) {
        window.$toast?.error(e.message || 'Downgrade failed')
    } finally {
        loading.value = false
        targetPlan.value = null
    }
}

async function toggleAutoRenew() {
    try {
        await endpoints.billing.toggleAutoRenew(autoRenew.value)
        store.subscription.autoRenew = autoRenew.value
        window.$toast?.success(`Auto-renewal ${autoRenew.value ? 'enabled' : 'disabled'}`)
    } catch (e) {
        autoRenew.value = !autoRenew.value
        window.$toast?.error(e.message || 'Failed to update auto-renewal')
    }
}

function copyAddress(cryptoId) {
    const addr = cryptoAddresses.value[cryptoId] || cryptoOptions.find(c => c.id === cryptoId)?.address
    if (addr) {
        navigator.clipboard.writeText(addr)
        window.$toast?.success('Address copied to clipboard!')
    }
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric'
    })
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount)
}

// ─── Lifecycle ────────────────────────────────────────────
onMounted(() => {
    fetchBillingData()
})
</script>

<template>
    <Head title="Billing | BLBGenSix AI" />

    <div class="min-h-screen bg-[#0a0a12] text-gray-100">
        <!-- ═════════════════════════════════════════════════════ -->
        <!-- PAGE HEADER                                           -->
        <!-- ═════════════════════════════════════════════════════ -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-white">Billing & Plans</h1>
                    <p class="text-gray-400 text-sm mt-1">
                        Manage your subscription, payment methods, and billing history
                    </p>
                </div>
                <Link
                    href="/customer/dashboard"
                    class="text-sm text-gray-400 hover:text-white transition-colors"
                >
                    ← Back to Dashboard
                </Link>
            </div>

            <!-- ═════════════════════════════════════════════════════ -->
            <!-- TAB NAVIGATION                                        -->
            <!-- ═════════════════════════════════════════════════════ -->
            <div class="flex items-center gap-1 p-1 bg-[#111118] rounded-xl border border-[#1a1a24] w-fit mb-8">
                <button
                    v-for="tab in ['overview', 'invoices', 'crypto', 'refunds']"
                    :key="tab"
                    @click="activeTab = tab"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 capitalize"
                    :class="activeTab === tab ? 'bg-[#1a1a24] text-white' : 'text-gray-500 hover:text-gray-300'"
                >
                    {{ tab }}
                </button>
            </div>

            <!-- ═════════════════════════════════════════════════════ -->
            <!-- TAB: OVERVIEW                                          -->
            <!-- ═════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'overview'" class="space-y-8">
                <!-- Current Plan Card -->
                <section class="card bg-[#111118] border-[#1a1a24] p-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-4">
                                <span
                                    class="px-3 py-1 rounded-full text-xs font-bold"
                                    :class="isActive ? 'bg-green-600/20 text-green-400 border border-green-600/30' : 'bg-gray-700/50 text-gray-500'"
                                >
                                    {{ isActive ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="text-2xl font-black" :class="store.plan === 3 ? 'gradient-text' : 'text-white'">
                                    {{ planLabel }}
                                </span>
                            </div>
                            <p class="text-3xl font-black text-white mb-1">
                                ${{ currentPlan.price }}<span class="text-base font-normal text-gray-500">/{{ currentPlan.interval }}</span>
                            </p>
                            <p v-if="store.subscription.nextBilling" class="text-sm text-gray-400">
                                Next billing: {{ formatDate(store.subscription.nextBilling) }}
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex items-center gap-3 p-3 bg-[#0a0a12] rounded-xl border border-[#1a1a24]">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-lg bg-violet-500/10 text-violet-400">
                                    ⚡
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Auto-Renewal</p>
                                    <button
                                        @click="autoRenew = !autoRenew; toggleAutoRenew()"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200 focus:outline-none"
                                        :class="autoRenew ? 'bg-violet-600' : 'bg-[#1a1a24]'"
                                    >
                                        <span
                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform duration-200"
                                            :class="autoRenew ? 'translate-x-4 ml-0.5' : 'translate-x-0.5'"
                                        />
                                    </button>
                                </div>
                            </div>

                            <button
                                v-if="store.plan < 3"
                                @click="handleUpgrade(plans[store.plan + 1])"
                                class="btn-primary text-sm whitespace-nowrap"
                            >
                                Upgrade to {{ plans[store.plan + 1]?.name || 'Pro' }}
                            </button>
                        </div>
                    </div>

                    <!-- Usage Meters -->
                    <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">Images</span>
                                <span class="text-sm" :class="usagePercent.images >= 90 ? 'text-red-400' : 'text-gray-300'">
                                    {{ store.usage.images }} / {{ store.planLimits.images === Infinity ? '∞' : store.planLimits.images }}
                                </span>
                            </div>
                            <div class="h-2 bg-[#0a0a12] rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :class="usagePercent.images >= 90 ? 'bg-red-500' : usagePercent.images >= 70 ? 'bg-yellow-500' : 'bg-violet-500'"
                                    :style="{ width: Math.min(usagePercent.images, 100) + '%' }"
                                />
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">Videos</span>
                                <span class="text-sm" :class="usagePercent.videos >= 90 ? 'text-red-400' : 'text-gray-300'">
                                    {{ store.usage.videos }} / {{ store.planLimits.videos === Infinity ? '∞' : store.planLimits.videos }}
                                </span>
                            </div>
                            <div class="h-2 bg-[#0a0a12] rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :class="usagePercent.videos >= 90 ? 'bg-red-500' : usagePercent.videos >= 70 ? 'bg-yellow-500' : 'bg-pink-500'"
                                    :style="{ width: Math.min(usagePercent.videos, 100) + '%' }"
                                />
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">Tokens</span>
                                <span class="text-sm" :class="usagePercent.tokens >= 90 ? 'text-red-400' : 'text-gray-300'">
                                    {{ store.usage.tokens.toLocaleString() }} / {{ store.planLimits.tokens === Infinity ? '∞' : store.planLimits.tokens.toLocaleString() }}
                                </span>
                            </div>
                            <div class="h-2 bg-[#0a0a12] rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :class="usagePercent.tokens >= 90 ? 'bg-red-500' : usagePercent.tokens >= 70 ? 'bg-yellow-500' : 'bg-green-500'"
                                    :style="{ width: Math.min(usagePercent.tokens, 100) + '%' }"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Plan Comparison -->
                <section>
                    <h2 class="text-xl font-bold text-white mb-6">Compare Plans</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div
                            v-for="plan in plans"
                            :key="plan.id"
                            class="card bg-[#111118] border-[#1a1a24] p-6 relative"
                            :class="[
                                plan.id === store.plan ? 'ring-2 ring-violet-500/50' : '',
                                plan.popular ? 'border-pink-500/50' : '',
                            ]"
                        >
                            <div v-if="plan.popular" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 bg-pink-600 text-white text-xs font-bold rounded-full">
                                POPULAR
                            </div>
                            <div v-if="plan.id === store.plan" class="absolute -top-3 right-4 px-3 py-0.5 bg-violet-600 text-white text-xs font-bold rounded-full">
                                CURRENT
                            </div>

                            <h3 class="text-lg font-bold text-white mb-1">{{ plan.name }}</h3>
                            <p class="text-3xl font-black text-white mb-4">
                                ${{ plan.price }}<span class="text-sm font-normal text-gray-500">/mo</span>
                            </p>

                            <ul class="space-y-2 mb-6">
                                <li v-for="feature in plan.features" :key="feature" class="flex items-center gap-2 text-sm text-gray-300">
                                    <svg class="w-4 h-4 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ feature }}
                                </li>
                            </ul>

                            <button
                                @click="handleUpgrade(plan)"
                                class="w-full py-2.5 text-sm font-semibold rounded-xl transition-all duration-200"
                                :class="planButtonClass(plan)"
                            >
                                {{ plan.id === store.plan ? 'Current Plan' : plan.id < store.plan ? 'Downgrade' : 'Upgrade' }}
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Wallet Connection Status -->
                <section class="card bg-[#111118] border-[#1a1a24] p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-white">Cold Wallet Connection</h3>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="walletConnected ? 'bg-green-600/20 text-green-400' : 'bg-gray-700/50 text-gray-500'"
                        >
                            {{ walletConnected ? 'Connected' : 'Not Connected' }}
                        </span>
                    </div>

                    <div v-if="walletConnected" class="space-y-3">
                        <div class="flex items-center gap-3 p-3 bg-[#0a0a12] rounded-xl border border-[#1a1a24]">
                            <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center text-lg">🔗</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-300">Connected Address</p>
                                <p class="text-xs text-gray-500 font-mono truncate">{{ walletAddress || store.subscription.walletAddress || '0x...' }}</p>
                            </div>
                            <button
                                @click="endpoints.wallet.disconnect()"
                                class="px-3 py-1.5 text-xs bg-red-500/10 text-red-400 border border-red-500/30 rounded-lg hover:bg-red-500/20 transition-colors"
                            >
                                Disconnect
                            </button>
                        </div>
                    </div>

                    <div v-else class="text-center py-6">
                        <p class="text-4xl mb-3">🔗</p>
                        <p class="text-gray-400 text-sm mb-4">Connect your cold wallet for crypto payments</p>
                        <button
                            @click="endpoints.wallet.connect('', 'ETH')"
                            class="btn-primary text-sm"
                        >
                            Connect Wallet
                        </button>
                    </div>
                </section>
            </div>

            <!-- ═════════════════════════════════════════════════════ -->
            <!-- TAB: INVOICES                                          -->
            <!-- ═════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'invoices'" class="space-y-6">
                <div class="card bg-[#111118] border-[#1a1a24] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[#1a1a24]">
                        <h3 class="font-bold text-white">Payment History</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase tracking-wider">
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-6 py-3">Invoice</th>
                                    <th class="px-6 py-3">Amount</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">Method</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#1a1a24]">
                                <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-[#0a0a12] transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-300">{{ formatDate(invoice.date) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-300 font-mono">{{ invoice.id }}</td>
                                    <td class="px-6 py-4 text-sm text-white font-medium">{{ formatCurrency(invoice.amount) }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[10px] font-medium"
                                            :class="invoice.status === 'paid' ? 'bg-green-600/20 text-green-400' : invoice.status === 'pending' ? 'bg-yellow-600/20 text-yellow-400' : 'bg-red-600/20 text-red-400'"
                                        >
                                            {{ invoice.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-400">{{ invoice.method || 'Crypto' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-xs text-violet-400 hover:text-violet-300 transition-colors">
                                            Download
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!invoices.length">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        No invoice history yet
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═════════════════════════════════════════════════════ -->
            <!-- TAB: CRYPTO PAYMENTS                                    -->
            <!-- ═════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'crypto'" class="space-y-6">
                <div class="card bg-[#111118] border-[#1a1a24] p-6">
                    <h3 class="font-bold text-white mb-6">Crypto Payment Addresses</h3>
                    <p class="text-sm text-gray-400 mb-6">Send payments to these addresses. Always verify the address before sending.</p>

                    <!-- Crypto Selector -->
                    <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
                        <button
                            v-for="crypto in cryptoOptions"
                            :key="crypto.id"
                            @click="selectedCrypto = crypto.id"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 whitespace-nowrap"
                            :class="selectedCrypto === crypto.id ? 'bg-violet-600 text-white' : 'bg-[#0a0a12] text-gray-400 hover:text-white hover:bg-[#1a1a24]'"
                        >
                            <span>{{ crypto.symbol }}</span>
                            <span>{{ crypto.name }}</span>
                        </button>
                    </div>

                    <!-- Selected Crypto Address -->
                    <div
                        v-for="crypto in cryptoOptions"
                        :key="crypto.id"
                        v-show="selectedCrypto === crypto.id"
                        class="space-y-4"
                    >
                        <div class="p-4 bg-[#0a0a12] rounded-xl border border-[#1a1a24]">
                            <p class="text-xs text-gray-500 mb-2">{{ crypto.name }} Address</p>
                            <div class="flex items-center gap-3">
                                <code class="flex-1 text-sm text-gray-300 font-mono break-all">{{ crypto.address || cryptoAddresses[crypto.id] || 'Loading...' }}</code>
                                <button
                                    @click="copyAddress(crypto.id)"
                                    class="shrink-0 px-3 py-1.5 text-xs bg-violet-600/20 text-violet-400 border border-violet-500/30 rounded-lg hover:bg-violet-600/30 transition-colors"
                                >
                                    Copy
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-yellow-900/10 border border-yellow-600/20 rounded-xl">
                            <span class="text-yellow-400 text-lg">⚠️</span>
                            <p class="text-xs text-yellow-200">
                                Only send {{ crypto.name }} ({{ crypto.id }}) to this address. Sending other assets may result in permanent loss.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═════════════════════════════════════════════════════ -->
            <!-- TAB: REFUNDS                                            -->
            <!-- ═════════════════════════════════════════════════════ -->
            <div v-if="activeTab === 'refunds'" class="space-y-6">
                <div class="card bg-[#111118] border-[#1a1a24] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[#1a1a24]">
                        <h3 class="font-bold text-white">Refund History</h3>
                    </div>
                    <div v-if="refunds.length" class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase tracking-wider">
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-6 py-3">Refund ID</th>
                                    <th class="px-6 py-3">Amount</th>
                                    <th class="px-6 py-3">Reason</th>
                                    <th class="px-6 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#1a1a24]">
                                <tr v-for="refund in refunds" :key="refund.id" class="hover:bg-[#0a0a12] transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-300">{{ formatDate(refund.date) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-300 font-mono">{{ refund.id }}</td>
                                    <td class="px-6 py-4 text-sm text-white font-medium">{{ formatCurrency(refund.amount) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-400">{{ refund.reason || '—' }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-[10px] font-medium"
                                            :class="refund.status === 'completed' ? 'bg-green-600/20 text-green-400' : 'bg-yellow-600/20 text-yellow-400'"
                                        >
                                            {{ refund.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="px-6 py-12 text-center text-gray-500">
                        No refund history
                    </div>
                </div>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════ -->
        <!-- UPGRADE MODAL                                            -->
        <!-- ═════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <transition
                enter-active-class="transition-all duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showUpgradeModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
                    @click.self="showUpgradeModal = false"
                >
                    <div class="card bg-[#111118] border-[#1a1a24] max-w-md w-full p-6">
                        <div class="text-center mb-6">
                            <span class="text-4xl">🚀</span>
                            <h3 class="text-xl font-bold text-white mt-2">Upgrade to {{ targetPlan?.name }}</h3>
                            <p class="text-sm text-gray-400 mt-1">
                                Unlock premium features and higher limits
                            </p>
                        </div>

                        <div class="bg-[#0a0a12] rounded-xl p-4 mb-6 border border-[#1a1a24]">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">Plan</span>
                                <span class="font-bold text-white">{{ targetPlan?.name }}</span>
                            </div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-400">Price</span>
                                <span class="font-bold text-white">${{ targetPlan?.price }}/month</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-400">Billing</span>
                                <span class="font-bold text-white">Monthly</span>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-6">
                            <label class="block text-xs text-gray-500 mb-2">Payment Method</label>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="paymentMethod = 'crypto'"
                                    class="flex-1 py-2 text-sm rounded-lg transition-colors"
                                    :class="paymentMethod === 'crypto' ? 'bg-violet-600 text-white' : 'bg-[#0a0a12] text-gray-400 hover:text-white'"
                                >
                                    Crypto
                                </button>
                                <button
                                    @click="paymentMethod = 'card'"
                                    class="flex-1 py-2 text-sm rounded-lg transition-colors"
                                    :class="paymentMethod === 'card' ? 'bg-violet-600 text-white' : 'bg-[#0a0a12] text-gray-400 hover:text-white'"
                                >
                                    Card
                                </button>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button
                                @click="showUpgradeModal = false"
                                class="flex-1 py-2.5 text-sm bg-[#1a1a24] hover:bg-[#242430] text-gray-300 rounded-xl transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                @click="confirmUpgrade"
                                :disabled="loading"
                                class="flex-1 py-2.5 text-sm bg-violet-600 hover:bg-violet-700 text-white rounded-xl transition-colors disabled:opacity-50"
                            >
                                {{ loading ? 'Processing...' : 'Confirm Upgrade' }}
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- ═════════════════════════════════════════════════════ -->
        <!-- DOWNGRADE MODAL                                          -->
        <!-- ═════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <transition
                enter-active-class="transition-all duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showDowngradeModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
                    @click.self="showDowngradeModal = false"
                >
                    <div class="card bg-[#111118] border-[#1a1a24] max-w-md w-full p-6">
                        <div class="text-center mb-6">
                            <span class="text-4xl">⚠️</span>
                            <h3 class="text-xl font-bold text-white mt-2">Downgrade to {{ targetPlan?.name }}</h3>
                            <p class="text-sm text-gray-400 mt-1">
                                Your usage limits will be reduced. Existing generations will remain accessible.
                            </p>
                        </div>

                        <div class="flex gap-3">
                            <button
                                @click="showDowngradeModal = false"
                                class="flex-1 py-2.5 text-sm bg-[#1a1a24] hover:bg-[#242430] text-gray-300 rounded-xl transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                @click="confirmDowngrade"
                                :disabled="loading"
                                class="flex-1 py-2.5 text-sm bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors disabled:opacity-50"
                            >
                                {{ loading ? 'Processing...' : 'Confirm Downgrade' }}
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>

<style scoped>
.gradient-text {
    background: linear-gradient(135deg, #8b5cf6, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.btn-primary {
    @apply px-4 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl transition-all duration-200;
}

.card {
    @apply rounded-2xl border;
}
</style>
