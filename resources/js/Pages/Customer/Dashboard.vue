<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCustomerStore } from '@/stores/customerStore'
import FeatureCard from '@/components/FeatureCard.vue'
import GenerateButton from '@/components/GenerateButton.vue'

// ─── Page Props (from Inertia / Laravel backend) ────────────
const props = defineProps({
    user: { type: Object, default: () => ({}) },
    subscription: { type: Object, default: () => ({}) },
    usage: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    activity: { type: Array, default: () => [] },
    revenue: { type: Array, default: () => [] },
})

// ─── Store ──────────────────────────────────────────────────
const store = useCustomerStore()

// ─── Reactive State ─────────────────────────────────────────
const activeTab = ref('image')
const selectedFeature = ref(null)
const showUpgradeModal = ref(false)
const upgradeTarget = ref(null)
const generationModal = ref(false)
const generationType = ref('image')
const toast = ref(null)

// ─── Initialize store with server data ──────────────────────
onMounted(() => {
    if (props.user && Object.keys(props.user).length) {
        store.user = props.user
        store.plan = props.user.plan ?? 0
    }
    if (props.usage) store.usage = props.usage
    if (props.stats) store.stats = props.stats
    if (props.activity) store.recentActivity = props.activity
    if (props.revenue) store.revenueStreams = props.revenue
    if (props.subscription) store.subscription = props.subscription
})

// ─── Computed ───────────────────────────────────────────────
const planClass = computed(() => {
    const classes = {
        0: 'bg-gray-700 text-gray-300',
        1: 'bg-violet-900/50 text-violet-400 border border-violet-700/50',
        2: 'bg-pink-900/50 text-pink-400 border border-pink-700/50',
        3: 'bg-gradient-to-r from-violet-900/50 to-pink-900/50 text-transparent bg-clip-text font-black',
    }
    return classes[store.plan] || classes[0]
})

const usageColor = (pct) => {
    if (pct >= 90) return 'bg-red-500'
    if (pct >= 70) return 'bg-yellow-500'
    return 'bg-violet-500'
}

const generationTabs = computed(() => {
    return [
        { id: 'image', label: 'Image', icon: '🎨', color: 'violet', flag: 'generate:image' },
        { id: 'video', label: 'Video', icon: '🎬', color: 'pink', flag: 'generate:video' },
        { id: 'text', label: 'Text', icon: '📖', color: 'green', flag: 'generate:text' },
        { id: 'body', label: 'Body', icon: '🧍', color: 'orange', flag: 'generate:body' },
    ].filter(tab => store.hasFeature([tab.flag]))
})

const dashboardSections = computed(() => [
    {
        id: 'generation',
        title: 'AI Generation',
        icon: '✨',
        component: 'generation',
        requires: ['dashboard:generation'],
    },
    {
        id: 'revenue',
        title: 'Revenue Streams',
        icon: '💸',
        component: 'revenue',
        requires: ['dashboard:revenue'],
    },
    {
        id: 'security',
        title: 'Account & Security',
        icon: '🔐',
        component: 'security',
        requires: ['dashboard:security'],
    },
    {
        id: 'quick-actions',
        title: 'Quick Actions',
        icon: '⚡',
        component: 'quickactions',
        requires: ['dashboard:quickactions'],
    },
].filter(s => store.hasFeature(s.requires)))

// ─── Time formatter ─────────────────────────────────────────
function timeAgo(timestamp) {
    const now = Date.now()
    const date = new Date(timestamp).getTime()
    const seconds = Math.floor((now - date) / 1000)
    if (seconds < 60) return 'just now'
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days < 7) return `${days}d ago`
    return new Date(timestamp).toLocaleDateString()
}

// ─── Actions ────────────────────────────────────────────────
function openGeneration(type) {
    generationType.value = type
    generationModal.value = true
}

function closeGeneration() {
    generationModal.value = false
}

function onGenerate(result) {
    showToast('Generation started!', 'success')
    closeGeneration()
}

function onGenerateError(err) {
    showToast(err.message || 'Generation failed', 'error')
}

function handleUpgrade(feature) {
    upgradeTarget.value = feature
    showUpgradeModal.value = true
}

function closeUpgradeModal() {
    showUpgradeModal.value = false
    upgradeTarget.value = null
}

function scrollToSection(sectionId) {
    const el = document.getElementById(`section-${sectionId}`)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function showToast(message, type = 'info') {
    toast.value = { message, type }
    setTimeout(() => { toast.value = null }, 4000)
}

function logout() {
    router.post('/logout')
}

// ─── Revenue stream colors ──────────────────────────────────
function revenueColor(index) {
    const colors = ['violet', 'pink', 'green', 'orange']
    return colors[index % colors.length]
}

function trendIcon(trend) {
    if (trend === 'up') return '↑'
    if (trend === 'down') return '↓'
    return '→'
}

function trendColor(trend) {
    if (trend === 'up') return 'text-green-400'
    if (trend === 'down') return 'text-red-400'
    return 'text-gray-400'
}
</script>

<template>
    <Head title="Dashboard | BLBGenSix AI" />

    <div class="min-h-screen bg-[#0a0a12] text-gray-100">
        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- TOAST NOTIFICATIONS                                      -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <transition
            enter-active-class="transition-all duration-300"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-200"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div
                v-if="toast"
                class="fixed top-6 right-6 z-[100] px-5 py-3 rounded-xl shadow-2xl text-sm font-medium backdrop-blur-xl border"
                :class="{
                    'bg-green-900/80 border-green-600/50 text-green-100': toast.type === 'success',
                    'bg-red-900/80 border-red-600/50 text-red-100': toast.type === 'error',
                    'bg-violet-900/80 border-violet-600/50 text-violet-100': toast.type === 'info',
                }"
            >
                {{ toast.message }}
            </div>
        </transition>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- TOP NAVIGATION BAR                                      -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <nav class="sticky top-0 z-40 bg-[#0a0a12]/85 backdrop-blur-xl border-b border-[#1a1a24]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/dashboard" class="text-xl font-bold gradient-text">
                        BLBGenSix
                    </Link>
                    <span
                        class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                        :class="planClass"
                    >
                        {{ store.planLabel }}
                    </span>
                </div>

                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Section quick-links (desktop) -->
                    <div class="hidden md:flex items-center gap-1">
                        <button
                            v-for="section in dashboardSections"
                            :key="section.id"
                            @click="scrollToSection(section.id)"
                            class="px-3 py-1.5 text-xs text-gray-400 hover:text-white hover:bg-[#1a1a24] rounded-lg transition-colors"
                        >
                            {{ section.icon }} {{ section.title }}
                        </button>
                    </div>

                    <!-- User menu -->
                    <div class="flex items-center gap-3">
                        <Link
                            href="/account/settings"
                            class="text-sm text-gray-400 hover:text-white transition-colors hidden sm:block"
                        >
                            Settings
                        </Link>
                        <button
                            @click="logout"
                            class="text-sm text-gray-500 hover:text-red-400 transition-colors"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- MAIN CONTENT                                            -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-8">

            <!-- ─── WELCOME HEADER ─────────────────────────────── -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black">
                        Welcome back,
                        <span class="gradient-text">{{ store.user?.name || 'Creator' }}</span>
                    </h1>
                    <p class="text-gray-400 text-sm mt-1">
                        {{ store.isActive ? 'Subscription active' : 'No active subscription' }}
                        <span v-if="store.subscription.nextBilling">
                            · Next billing {{ new Date(store.subscription.nextBilling).toLocaleDateString() }}
                        </span>
                    </p>
                </div>
                <Link
                    v-if="store.plan < 3"
                    href="/pricing"
                    class="btn-primary text-sm sm:text-base whitespace-nowrap"
                >
                    Upgrade to Pro
                </Link>
            </div>

            <!-- ─── SUBSCRIPTION STATUS + USAGE BARS ───────────── -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Subscription card -->
                <div class="card bg-[#111118] border-[#1a1a24]">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs text-gray-500 uppercase tracking-wider">Subscription</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-medium uppercase"
                            :class="store.isActive ? 'bg-green-600/20 text-green-400 border border-green-600/30' : 'bg-gray-700/50 text-gray-500'"
                        >
                            {{ store.isActive ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="text-2xl font-black" :class="store.plan === 3 ? 'gradient-text' : 'text-white'">
                        {{ store.planLabel }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ store.isActive ? `$${store.subscription.plan === 0 ? '0' : store.subscription.plan === 1 ? '29.99' : store.subscription.plan === 2 ? '99.99' : '299.99'}/mo` : 'No active plan' }}
                    </p>
                    <div class="mt-3 flex items-center gap-2">
                        <div class="flex-1 h-1.5 bg-[#1a1a24] rounded-full overflow-hidden">
                            <div class="h-full bg-violet-500 rounded-full" :style="{ width: '100%' }" />
                        </div>
                        <span class="text-[10px] text-gray-600">100%</span>
                    </div>
                </div>

                <!-- Usage card -->
                <div class="card bg-[#111118] border-[#1a1a24] md:col-span-2">
                    <span class="text-xs text-gray-500 uppercase tracking-wider">Usage This Month</span>
                    <div class="mt-3 space-y-3">
                        <!-- Images -->
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-gray-400">Images</span>
                                <span :class="store.usagePercent.images >= 90 ? 'text-red-400' : 'text-gray-500'">
                                    {{ store.usage.images }} / {{ store.planLimits.images === Infinity ? '∞' : store.planLimits.images }}
                                </span>
                            </div>
                            <div class="h-1.5 bg-[#1a1a24] rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :class="usageColor(store.usagePercent.images)"
                                    :style="{ width: store.usagePercent.images + '%' }"
                                />
                            </div>
                        </div>
                        <!-- Videos -->
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-gray-400">Videos</span>
                                <span :class="store.usagePercent.videos >= 90 ? 'text-red-400' : 'text-gray-500'">
                                    {{ store.usage.videos }} / {{ store.planLimits.videos === Infinity ? '∞' : store.planLimits.videos }}
                                </span>
                            </div>
                            <div class="h-1.5 bg-[#1a1a24] rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :class="usageColor(store.usagePercent.videos)"
                                    :style="{ width: store.usagePercent.videos + '%' }"
                                />
                            </div>
                        </div>
                        <!-- Tokens -->
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-gray-400">Tokens</span>
                                <span :class="store.usagePercent.tokens >= 90 ? 'text-red-400' : 'text-gray-500'">
                                    {{ store.usage.tokens.toLocaleString() }} / {{ store.planLimits.tokens === Infinity ? '∞' : store.planLimits.tokens.toLocaleString() }}
                                </span>
                            </div>
                            <div class="h-1.5 bg-[#1a1a24] rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-700"
                                    :class="usageColor(store.usagePercent.tokens)"
                                    :style="{ width: store.usagePercent.tokens + '%' }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── QUICK STATS ROW ────────────────────────────── -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card bg-[#111118] border-[#1a1a24] p-4 text-center">
                    <p class="text-2xl font-black text-violet-400">{{ store.stats.totalGenerations.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500 mt-1">Generations</p>
                </div>
                <div class="card bg-[#111118] border-[#1a1a24] p-4 text-center">
                    <p class="text-2xl font-black text-pink-400">${{ store.stats.totalRevenue.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500 mt-1">Revenue</p>
                </div>
                <div class="card bg-[#111118] border-[#1a1a24] p-4 text-center">
                    <p class="text-2xl font-black text-green-400">{{ store.storageUsed }}</p>
                    <p class="text-xs text-gray-500 mt-1">Storage Used</p>
                </div>
                <div class="card bg-[#111118] border-[#1a1a24] p-4 text-center">
                    <p class="text-2xl font-black text-orange-400">{{ store.subscription.status === 'active' ? 'Active' : '--' }}</p>
                    <p class="text-xs text-gray-500 mt-1">Cold Wallet</p>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SECTION: AI GENERATION                                  -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <section id="section-generation">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <span>✨</span> AI Generation
                    </h2>
                    <Link
                        href="/generation"
                        class="text-xs text-violet-400 hover:text-violet-300 transition-colors"
                    >
                        View all →
                    </Link>
                </div>

                <!-- Generation Tabs -->
                <div class="flex items-center gap-1 mb-4 p-1 bg-[#111118] rounded-xl border border-[#1a1a24] w-fit">
                    <button
                        v-for="tab in generationTabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200"
                        :class="activeTab === tab.id
                            ? 'bg-[#1a1a24] text-white shadow-sm'
                            : 'text-gray-500 hover:text-gray-300'"
                    >
                        {{ tab.icon }} {{ tab.label }}
                    </button>
                </div>

                <!-- Generation Panel: IMAGE -->
                <div v-if="activeTab === 'image'" class="card bg-[#111118] border-[#1a1a24]">
                    <div class="flex flex-col lg:flex-row gap-6">
                        <div class="flex-1 space-y-4">
                            <h3 class="font-bold text-violet-400">Image Generation</h3>
                            <p class="text-sm text-gray-400">
                                SDXL-powered generation. Anime, realistic, artistic styles. Upscale, inpaint, and create variations.
                            </p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Prompt</label>
                                    <textarea
                                        rows="3"
                                        placeholder="A cyberpunk cityscape at night, neon reflections on wet pavement..."
                                        class="input-dark bg-[#0a0a12] border-[#1a1a24] resize-none"
                                    ></textarea>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>SDXL</option>
                                        <option>SDXL Turbo</option>
                                        <option>Anime v3</option>
                                    </select>
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>512×512</option>
                                        <option>768×768</option>
                                        <option>1024×1024</option>
                                    </select>
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>25 steps</option>
                                        <option>50 steps</option>
                                        <option>75 steps</option>
                                    </select>
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>CFG 7</option>
                                        <option>CFG 10</option>
                                        <option>CFG 14</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 pt-2">
                                <GenerateButton
                                    type="image"
                                    size="md"
                                    @success="onGenerate"
                                    @error="onGenerateError"
                                />
                                <button class="text-xs text-gray-500 hover:text-gray-300 transition-colors">
                                    Advanced Settings →
                                </button>
                            </div>
                        </div>
                        <div class="lg:w-48 shrink-0">
                            <div class="aspect-square bg-[#0a0a12] rounded-xl border border-[#1a1a24] flex items-center justify-center text-6xl text-gray-700">
                                🎨
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Generation Panel: VIDEO -->
                <div v-if="activeTab === 'video'" class="card bg-[#111118] border-[#1a1a24]">
                    <div class="flex flex-col lg:flex-row gap-6">
                        <div class="flex-1 space-y-4">
                            <h3 class="font-bold text-pink-400">Video Generation</h3>
                            <p class="text-sm text-gray-400">
                                AI video up to 300s 4K 60fps. Storyboard-to-video and script-to-video pipelines.
                            </p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Script / Prompt</label>
                                    <textarea
                                        rows="3"
                                        placeholder="Describe your scene or paste a script segment..."
                                        class="input-dark bg-[#0a0a12] border-[#1a1a24] resize-none"
                                    ></textarea>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>1080p</option>
                                        <option>4K</option>
                                    </select>
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>30fps</option>
                                        <option>60fps</option>
                                    </select>
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>5s</option>
                                        <option>30s</option>
                                        <option>60s</option>
                                        <option v-if="store.hasFeature(['generate:video:300s'])">300s</option>
                                    </select>
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>Standard</option>
                                        <option>Cinematic</option>
                                        <option>Anime</option>
                                    </select>
                                </div>
                            </div>
                            <GenerateButton
                                type="video"
                                size="md"
                                variant="secondary"
                                @success="onGenerate"
                                @error="onGenerateError"
                            />
                        </div>
                        <div class="lg:w-48 shrink-0">
                            <div class="aspect-video bg-[#0a0a12] rounded-xl border border-[#1a1a24] flex items-center justify-center text-6xl text-gray-700">
                                🎬
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Generation Panel: TEXT -->
                <div v-if="activeTab === 'text'" class="card bg-[#111118] border-[#1a1a24]">
                    <div class="flex flex-col lg:flex-row gap-6">
                        <div class="flex-1 space-y-4">
                            <h3 class="font-bold text-green-400">Text & Novel Generation</h3>
                            <p class="text-sm text-gray-400">
                                Full novels, storyboards, scripts. Up to 500K token context windows.
                            </p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Outline / First Chapter</label>
                                    <textarea
                                        rows="5"
                                        placeholder="Chapter 1: The rain had been falling for three days straight when the first ghost appeared..."
                                        class="input-dark bg-[#0a0a12] border-[#1a1a24] resize-none"
                                    ></textarea>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>Novel</option>
                                        <option>Short Story</option>
                                        <option>Screenplay</option>
                                        <option>Poetry</option>
                                    </select>
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>First Person</option>
                                        <option>Third Person</option>
                                        <option>Omniscient</option>
                                    </select>
                                    <input
                                        type="number"
                                        placeholder="Word count (1000-50000)"
                                        class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm"
                                    />
                                </div>
                            </div>
                            <GenerateButton
                                type="text"
                                size="md"
                                variant="ghost"
                                @success="onGenerate"
                                @error="onGenerateError"
                            />
                        </div>
                    </div>
                </div>

                <!-- Generation Panel: BODY -->
                <div v-if="activeTab === 'body'" class="card bg-[#111118] border-[#1a1a24]">
                    <div class="flex flex-col lg:flex-row gap-6">
                        <div class="flex-1 space-y-4">
                            <h3 class="font-bold text-orange-400">Body Mapping & 3D</h3>
                            <p class="text-sm text-gray-400">
                                SMPL-X body models, face reconstruction, pose estimation, and animation.
                            </p>
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>SMPL-X</option>
                                        <option>SMPL</option>
                                        <option>FLAME</option>
                                        <option>MANO</option>
                                    </select>
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>Full Body</option>
                                        <option>Face Only</option>
                                        <option>Hands</option>
                                    </select>
                                    <select class="input-dark bg-[#0a0a12] border-[#1a1a24] text-sm">
                                        <option>Static</option>
                                        <option v-if="store.hasFeature(['generate:body:animate'])">Animated</option>
                                    </select>
                                </div>
                                <label class="flex items-center gap-3 p-3 bg-[#0a0a12] rounded-xl border border-[#1a1a24] cursor-pointer hover:border-orange-500/30 transition-colors">
                                    <span class="text-2xl">📤</span>
                                    <div>
                                        <p class="text-sm text-gray-300">Upload Reference Image</p>
                                        <p class="text-xs text-gray-600">Drag & drop or click to browse</p>
                                    </div>
                                </label>
                            </div>
                            <GenerateButton
                                type="body"
                                size="md"
                                variant="primary"
                                @success="onGenerate"
                                @error="onGenerateError"
                            />
                        </div>
                        <div class="lg:w-48 shrink-0">
                            <div class="aspect-square bg-[#0a0a12] rounded-xl border border-[#1a1a24] flex items-center justify-center text-6xl text-gray-700">
                                🧍
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SECTION: REVENUE STREAMS                                -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <section
                v-if="store.hasFeature(['dashboard:revenue'])"
                id="section-revenue"
            >
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <span>💸</span> Revenue Streams
                    </h2>
                    <Link
                        href="/revenue"
                        class="text-xs text-pink-400 hover:text-pink-300 transition-colors"
                    >
                        Revenue Dashboard →
                    </Link>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Revenue stream cards from store -->
                    <template v-if="store.revenueStreams.length">
                        <div
                            v-for="(stream, i) in store.revenueStreams"
                            :key="stream.id || i"
                            class="card bg-[#111118] border-[#1a1a24]"
                        >
                            <div class="flex items-start justify-between mb-2">
                                <div
                                    class="w-9 h-9 rounded-lg flex items-center justify-center text-lg"
                                    :class="{
                                        'bg-violet-500/10': revenueColor(i) === 'violet',
                                        'bg-pink-500/10': revenueColor(i) === 'pink',
                                        'bg-green-500/10': revenueColor(i) === 'green',
                                        'bg-orange-500/10': revenueColor(i) === 'orange',
                                    }"
                                >
                                    {{ stream.icon || '💰' }}
                                </div>
                                <span class="text-xs font-medium" :class="trendColor(stream.trend)">
                                    {{ trendIcon(stream.trend) }} {{ stream.change ?? 0 }}%
                                </span>
                            </div>
                            <p class="text-sm text-gray-400">{{ stream.title }}</p>
                            <p class="text-xl font-black mt-1 text-white">
                                {{ stream.currency || '$' }}{{ (stream.amount ?? 0).toLocaleString() }}
                            </p>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div v-else class="col-span-full card bg-[#111118] border-[#1a1a24] text-center py-10">
                        <p class="text-4xl mb-3">💰</p>
                        <p class="text-gray-400 text-sm">No revenue data yet. Start generating and selling!</p>
                        <Link href="/pricing" class="inline-block mt-3 text-xs text-violet-400 hover:text-violet-300">
                            Explore monetization →
                        </Link>
                    </div>
                </div>

                <!-- Revenue feature cards (monetization tools) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <FeatureCard
                        v-for="feature in store.availableFeatures.revenue"
                        :key="feature.id"
                        :feature="feature"
                        :enabled="store.hasFeature(feature.requires)"
                        :compact="true"
                        @upgrade="handleUpgrade"
                    />
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SECTION: ACCOUNT & SECURITY                             -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <section
                v-if="store.hasFeature(['dashboard:security'])"
                id="section-security"
            >
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <span>🔐</span> Account & Security
                    </h2>
                    <Link
                        href="/account/security"
                        class="text-xs text-green-400 hover:text-green-300 transition-colors"
                    >
                        Security Center →
                    </Link>
                </div>

                <!-- Profile card -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="card bg-[#111118] border-[#1a1a24] lg:col-span-1">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-500 to-pink-500 flex items-center justify-center text-lg font-bold text-white shrink-0">
                                {{ store.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                            </div>
                            <div>
                                <p class="font-bold text-white">{{ store.user?.name || 'Creator' }}</p>
                                <p class="text-xs text-gray-500">{{ store.user?.email || '—' }}</p>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Member since</span>
                                <span class="text-gray-300">{{ store.user?.created_at ? new Date(store.user.created_at).toLocaleDateString() : '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Plan</span>
                                <span class="font-medium" :class="store.plan === 3 ? 'gradient-text' : 'text-white'">{{ store.planLabel }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Cold Wallet</span>
                                <span class="text-green-400 text-xs">{{ store.subscription.walletAddress ? store.subscription.walletAddress.slice(0, 8) + '...' : 'Not connected' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Storage</span>
                                <span class="text-gray-300">{{ store.storageUsed }} / {{ store.storageLimit }}</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-[#1a1a24] flex gap-2">
                            <Link
                                href="/account/settings"
                                class="flex-1 text-center py-2 text-xs bg-[#1a1a24] hover:bg-[#242430] text-gray-300 rounded-lg transition-colors"
                            >
                                Edit Profile
                            </Link>
                            <button class="flex-1 text-center py-2 text-xs bg-[#1a1a24] hover:bg-[#242430] text-gray-300 rounded-lg transition-colors">
                                Connect Wallet
                            </button>
                        </div>
                    </div>

                    <!-- Security feature cards -->
                    <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FeatureCard
                            v-for="feature in store.availableFeatures.security"
                            :key="feature.id"
                            :feature="feature"
                            :enabled="store.hasFeature(feature.requires)"
                            :compact="true"
                            @upgrade="handleUpgrade"
                        />
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SECTION: RECENT ACTIVITY                                -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <section
                v-if="store.hasFeature(['dashboard:activity'])"
                id="section-activity"
            >
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold flex items-center gap-2">
                        <span>📋</span> Recent Activity
                    </h2>
                    <Link
                        href="/activity"
                        class="text-xs text-gray-400 hover:text-gray-300 transition-colors"
                    >
                        View all →
                    </Link>
                </div>

                <div class="card bg-[#111118] border-[#1a1a24] divide-y divide-[#1a1a24]">
                    <!-- Activity items -->
                    <template v-if="store.recentActivity.length">
                        <div
                            v-for="(item, i) in store.recentActivity.slice(0, 8)"
                            :key="item.id || i"
                            class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                :class="{
                                    'bg-violet-500/10 text-violet-400': item.type === 'generation',
                                    'bg-green-500/10 text-green-400': item.type === 'revenue',
                                    'bg-pink-500/10 text-pink-400': item.type === 'security',
                                    'bg-gray-700/30 text-gray-400': !item.type,
                                }"
                            >
                                <span class="text-xs">
                                    {{ item.type === 'generation' ? '🎨' : item.type === 'revenue' ? '💰' : item.type === 'security' ? '🔐' : '📌' }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-300 truncate">{{ item.action || item.description || 'Activity' }}</p>
                                <p class="text-xs text-gray-600">{{ item.details || '' }}</p>
                            </div>
                            <span class="text-xs text-gray-600 whitespace-nowrap">{{ timeAgo(item.timestamp) }}</span>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div v-else class="py-10 text-center">
                        <p class="text-4xl mb-3">📋</p>
                        <p class="text-gray-400 text-sm">No recent activity. Start generating to see your history here!</p>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- SECTION: QUICK ACTIONS                                  -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <section
                v-if="store.hasFeature(['dashboard:quickactions'])"
                id="section-quick-actions"
            >
                <h2 class="text-lg font-bold flex items-center gap-2 mb-4">
                    <span>⚡</span> Quick Actions
                </h2>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <FeatureCard
                        v-for="feature in store.availableFeatures.widgets"
                        :key="feature.id"
                        :feature="feature"
                        :enabled="store.hasFeature(feature.requires)"
                        :compact="true"
                        @upgrade="handleUpgrade"
                    />

                    <!-- Static quick actions -->
                    <button
                        @click="router.visit('/generation')"
                        class="card bg-[#111118] border-[#1a1a24] p-4 text-left hover:border-violet-500/50 transition-all group"
                    >
                        <span class="text-2xl">🚀</span>
                        <p class="text-sm font-bold mt-2 text-white group-hover:text-violet-300">New Generation</p>
                        <p class="text-xs text-gray-500 mt-1">Start creating</p>
                    </button>

                    <button
                        @click="router.visit('/gallery')"
                        class="card bg-[#111118] border-[#1a1a24] p-4 text-left hover:border-pink-500/50 transition-all group"
                    >
                        <span class="text-2xl">🖼️</span>
                        <p class="text-sm font-bold mt-2 text-white group-hover:text-pink-300">My Gallery</p>
                        <p class="text-xs text-gray-500 mt-1">Browse creations</p>
                    </button>

                    <button
                        @click="router.visit('/marketplace')"
                        class="card bg-[#111118] border-[#1a1a24] p-4 text-left hover:border-green-500/50 transition-all group"
                    >
                        <span class="text-2xl">🏪</span>
                        <p class="text-sm font-bold mt-2 text-white group-hover:text-green-300">Marketplace</p>
                        <p class="text-xs text-gray-500 mt-1">Buy & sell assets</p>
                    </button>

                    <button
                        @click="router.visit('/support')"
                        class="card bg-[#111118] border-[#1a1a24] p-4 text-left hover:border-orange-500/50 transition-all group"
                    >
                        <span class="text-2xl">💬</span>
                        <p class="text-sm font-bold mt-2 text-white group-hover:text-orange-300">Support</p>
                        <p class="text-xs text-gray-500 mt-1">Get help</p>
                    </button>
                </div>
            </section>

        </main>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- UPGRADE MODAL                                             -->
        <!-- ═══════════════════════════════════════════════════════════ -->
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
                    @click.self="closeUpgradeModal"
                >
                    <div class="card bg-[#111118] border-[#1a1a24] max-w-md w-full">
                        <div class="text-center mb-4">
                            <span class="text-4xl">🔒</span>
                            <h3 class="text-lg font-bold mt-2 text-white">Upgrade Required</h3>
                            <p class="text-sm text-gray-400 mt-1">
                                <template v-if="upgradeTarget">
                                    "{{ upgradeTarget.title }}" is available on higher plans.
                                </template>
                                <template v-else>
                                    This feature is not available on your current plan.
                                </template>
                            </p>
                        </div>

                        <div class="space-y-3 mb-6">
                            <div class="p-3 bg-[#1a1a24] rounded-xl">
                                <p class="text-xs text-gray-500 mb-1">Your Plan</p>
                                <p class="font-bold text-white">{{ store.planLabel }}</p>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div
                                    class="p-3 rounded-xl border text-center cursor-pointer transition-all hover:border-violet-500/50"
                                    :class="store.plan === 1 ? 'border-violet-500 bg-violet-500/10' : 'border-[#1a1a24]'"
                                >
                                    <p class="text-xs font-bold text-white">Starter</p>
                                    <p class="text-lg font-black text-violet-400">$29</p>
                                    <p class="text-[10px] text-gray-500">/mo</p>
                                </div>
                                <div
                                    class="p-3 rounded-xl border text-center cursor-pointer transition-all hover:border-pink-500/50"
                                    :class="store.plan === 2 ? 'border-pink-500 bg-pink-500/10' : 'border-[#1a1a24]'"
                                >
                                    <p class="text-xs font-bold text-white">Pro</p>
                                    <p class="text-lg font-black text-pink-400">$99</p>
                                    <p class="text-[10px] text-gray-500">/mo</p>
                                </div>
                                <div
                                    class="p-3 rounded-xl border text-center cursor-pointer transition-all hover:border-violet-500/50"
                                    :class="store.plan === 3 ? 'border-violet-500 bg-gradient-to-b from-violet-500/10 to-pink-500/10' : 'border-[#1a1a24]'"
                                >
                                    <p class="text-xs font-bold text-white">Enterprise</p>
                                    <p class="text-lg font-black gradient-text">$299</p>
                                    <p class="text-[10px] text-gray-500">/mo</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                @click="closeUpgradeModal"
                                class="flex-1 py-2.5 text-sm bg-[#1a1a24] hover:bg-[#242430] text-gray-300 rounded-xl transition-colors"
                            >
                                Maybe Later
                            </button>
                            <Link
                                href="/pricing"
                                class="flex-1 py-2.5 text-sm bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl transition-colors text-center"
                            >
                                View Plans →
                            </Link>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- GENERATION MODAL                                          -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <transition
                enter-active-class="transition-all duration-300"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition-all duration-200"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="generationModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
                    @click.self="closeGeneration"
                >
                    <div class="card bg-[#111118] border-[#1a1a24] max-w-lg w-full">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-bold text-white">Generate {{ generationType }}</h3>
                            <button
                                @click="closeGeneration"
                                class="text-gray-500 hover:text-white transition-colors text-xl leading-none"
                            >
                                ×
                            </button>
                        </div>

                        <div class="space-y-4">
                            <textarea
                                rows="4"
                                placeholder="Describe what you want to generate..."
                                class="input-dark bg-[#0a0a12] border-[#1a1a24] resize-none"
                            ></textarea>

                            <GenerateButton
                                :type="generationType"
                                size="lg"
                                @success="(r) => { onGenerate(r); closeGeneration() }"
                                @error="onGenerateError"
                            />
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!-- FOOTER                                                    -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <footer class="border-t border-[#1a1a24] py-8 mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 text-center text-xs text-gray-600 space-y-1">
                <p>© 2026 BLBGenSix AI — blbgensixai.club — Adults Only, 18+</p>
                <p>
                    <Link href="/terms" class="hover:text-gray-400 transition-colors">Terms</Link>
                    <span class="mx-2">·</span>
                    <Link href="/privacy" class="hover:text-gray-400 transition-colors">Privacy</Link>
                    <span class="mx-2">·</span>
                    <Link href="/support" class="hover:text-gray-400 transition-colors">Support</Link>
                </p>
            </div>
        </footer>
    </div>
</template>
