<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCustomerStore } from '@/stores/customerStore'

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    sources: { type: Array, default: () => [] },
    availableSources: { type: Object, default: () => ({}) },
    canConnect: { type: Boolean, default: false },
    reconnect: { type: String, default: null },
})

const store = useCustomerStore()

const toast = ref(null)
const connectingSource = ref(null)
const showHelpModal = ref(null)
const manualToken = ref('')
const manualServerUrl = ref('')
const manualApiKey = ref('')

const sourceList = computed(() => {
    return Object.entries(props.availableSources).map(([key, config]) => {
        const connected = props.sources.find(s => s.source_type === key)
        return {
            key,
            ...config,
            connected: connected ?? null,
            isConnected: connected?.is_connected ?? false,
        }
    })
})

const sourceColorClasses = {
    spotify: 'from-green-500/20 to-green-600/10 border-green-500/20 hover:border-green-500/40',
    lastfm: 'from-red-500/20 to-red-600/10 border-red-500/20 hover:border-red-500/40',
    listenbrainz: 'from-red-400/20 to-orange-500/10 border-red-400/20 hover:border-red-400/40',
    jellyfin: 'from-cyan-500/20 to-blue-500/10 border-cyan-500/20 hover:border-cyan-500/40',
    plex: 'from-yellow-500/20 to-amber-500/10 border-yellow-500/20 hover:border-yellow-500/40',
    subsonic: 'from-green-400/20 to-emerald-500/10 border-green-400/20 hover:border-green-400/40',
    youtube_music: 'from-red-600/20 to-red-500/10 border-red-600/20 hover:border-red-600/40',
    apple_music: 'from-pink-500/20 to-rose-500/10 border-pink-500/20 hover:border-pink-500/40',
}

const sourceIconMap = {
    spotify: '🟢',
    lastfm: '📡',
    listenbrainz: '🧠',
    jellyfin: '🪼',
    plex: '▶️',
    subsonic: '🎶',
    youtube_music: '▶️',
    apple_music: '🍎',
}

onMounted(() => {
    if (props.reconnect) {
        showHelpModal.value = props.reconnect
    }
})

function timeAgo(timestamp) {
    if (!timestamp) return 'Never'
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

async function initiateOAuth(sourceKey) {
    if (!props.canConnect) {
        showToast('Connect music sources on Starter plans and above', 'error')
        return
    }

    connectingSource.value = sourceKey
    try {
        const res = await fetch(`/api/v1/music/connect/${sourceKey}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        })

        if (res.ok) {
            const data = await res.json()
            if (data.redirect_url) {
                window.location.href = data.redirect_url
            } else {
                showToast(`Connected to ${sourceKey}!`, 'success')
                setTimeout(() => window.location.reload(), 1000)
            }
        } else {
            const error = await res.json()
            showToast(error.message || `Failed to connect ${sourceKey}`, 'error')
        }
    } catch (e) {
        showToast(`Connection failed: ${e.message}`, 'error')
    } finally {
        connectingSource.value = null
    }
}

async function connectManual(sourceKey) {
    if (!props.canConnect) {
        showToast('Connect music sources on Starter plans and above', 'error')
        return
    }

    connectingSource.value = sourceKey
    try {
        const body = {}
        if (sourceKey === 'listenbrainz') {
            body.user_token = manualToken.value
        } else if (sourceKey === 'jellyfin') {
            body.server_url = manualServerUrl.value
            body.api_key = manualApiKey.value
        }

        const res = await fetch(`/api/v1/music/connect/${sourceKey}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        })

        if (res.ok) {
            showToast(`Connected to ${sourceKey}!`, 'success')
            closeHelpModal()
            setTimeout(() => window.location.reload(), 1000)
        } else {
            const error = await res.json()
            showToast(error.message || `Failed to connect ${sourceKey}`, 'error')
        }
    } catch (e) {
        showToast(`Connection failed: ${e.message}`, 'error`)
    } finally {
        connectingSource.value = null
    }
}

async function disconnect(sourceKey) {
    try {
        const res = await fetch(`/api/v1/music/disconnect/${sourceKey}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
        })
        if (res.ok) {
            showToast(`Disconnected ${sourceKey}`, 'success')
            setTimeout(() => window.location.reload(), 1000)
        }
    } catch (e) {
        showToast('Failed to disconnect', 'error')
    }
}

function openHelpModal(sourceKey) {
    showHelpModal.value = sourceKey
}

function closeHelpModal() {
    showHelpModal.value = null
    manualToken.value = ''
    manualServerUrl.value = ''
    manualApiKey.value = ''
}

function showToast(message, type = 'info') {
    toast.value = { message, type }
    setTimeout(() => { toast.value = null }, 4000)
}

const connectedCount = computed(() => props.sources.filter(s => s.is_connected).length)
</script>

<template>
    <Head title="Connect Music Sources | BLBGenSix AI" />

    <div class="min-h-screen bg-[#0a0a12] text-gray-100">
        <!-- Toast -->
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

        <!-- Nav -->
        <nav class="sticky top-0 z-40 bg-[#0a0a12]/85 backdrop-blur-xl border-b border-[#1a1a24]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/music/dashboard" class="text-lg text-gray-400 hover:text-white transition-colors">
                        ← Music
                    </Link>
                    <span class="text-xl font-bold gradient-text">Connect Sources</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500">
                        {{ connectedCount }} / {{ sourceList.length }} connected
                    </span>
                </div>
            </div>
        </nav>

        <!-- Main -->
        <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
            <!-- Not available -->
            <div v-if="!canConnect" class="text-center py-20">
                <span class="text-6xl">🔗</span>
                <h2 class="text-2xl font-bold mt-4 text-white">Connect Music Sources</h2>
                <p class="text-gray-400 mt-2 max-w-md mx-auto">
                    Link your music services to automatically track your listening history. Available on Starter plans and above.
                </p>
                <Link href="/pricing" class="btn-primary mt-6 inline-block">Upgrade Plan</Link>
            </div>

            <template v-if="canConnect">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-white">Connect Your Music Services</h1>
                    <p class="text-gray-400 mt-1">
                        Link your music accounts to track listening history, discover patterns, and unlock analytics.
                    </p>
                </div>

                <!-- Source Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="source in sourceList"
                        :key="source.key"
                        class="card border p-5 transition-all duration-200"
                        :class="[
                            sourceColorClasses[source.key] ?? 'from-gray-500/20 to-gray-600/10 border-gray-500/20 hover:border-gray-500/40',
                            source.isConnected ? 'bg-[#111118]' : 'bg-[#0a0a12]',
                        ]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br flex items-center justify-center text-2xl shrink-0"
                                    :class="sourceColorClasses[source.key]?.split(' ')[0] ?? 'bg-gray-700/30'"
                                >
                                    {{ sourceIconMap[source.key] ?? '🎵' }}
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-white">{{ source.label }}</h3>
                                    <p v-if="source.connected?.external_username" class="text-xs text-gray-500 mt-0.5">
                                        @{{ source.connected.external_username }}
                                    </p>
                                    <p class="text-xs text-gray-600 mt-1 leading-relaxed">{{ source.help_text }}</p>
                                </div>
                            </div>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-medium shrink-0"
                                :class="source.isConnected
                                    ? 'bg-green-600/20 text-green-400 border border-green-600/30'
                                    : 'bg-gray-700/50 text-gray-500'"
                            >
                                {{ source.isConnected ? 'Connected' : 'Not Connected' }}
                            </span>
                        </div>

                        <!-- Connected Details -->
                        <div v-if="source.isConnected" class="mt-4 pt-4 border-t border-[#1a1a24]">
                            <div class="flex items-center gap-4 text-xs text-gray-600 mb-3">
                                <span>Synced: {{ timeAgo(source.connected.last_sync_at) }}</span>
                                <span v-if="source.connected.last_scrobble_at">Last play: {{ timeAgo(source.connected.last_scrobble_at) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="disconnect(source.key)"
                                    class="px-3 py-1.5 text-xs bg-red-500/10 hover:bg-red-500/20 text-red-400 rounded-lg transition-colors border border-red-500/20"
                                >
                                    Disconnect
                                </button>
                                <button
                                    @click="openHelpModal(source.key)"
                                    class="px-3 py-1.5 text-xs bg-[#1a1a24] hover:bg-[#242430] text-gray-400 rounded-lg transition-colors"
                                >
                                    Reconnect
                                </button>
                                <Link
                                    href="/music/dashboard"
                                    class="px-3 py-1.5 text-xs bg-violet-500/10 hover:bg-violet-500/20 text-violet-400 rounded-lg transition-colors border border-violet-500/20 ml-auto"
                                >
                                    View Dashboard →
                                </Link>
                            </div>
                        </div>

                        <!-- Connect Button -->
                        <div v-else class="mt-4 pt-4 border-t border-[#1a1a24] flex items-center gap-2">
                            <button
                                v-if="source.oauth"
                                @click="initiateOAuth(source.key)"
                                :disabled="connectingSource === source.key"
                                class="flex-1 py-2 text-sm font-medium rounded-lg transition-all bg-gradient-to-r hover:opacity-90 disabled:opacity-50"
                                :class="[
                                    source.key === 'spotify' ? 'from-green-500 to-green-600 text-white' :
                                    source.key === 'lastfm' ? 'from-red-500 to-red-600 text-white' :
                                    source.key === 'listenbrainz' ? 'from-red-400 to-orange-500 text-white' :
                                    source.key === 'jellyfin' ? 'from-cyan-500 to-blue-500 text-white' :
                                    source.key === 'plex' ? 'from-yellow-500 to-amber-500 text-white' :
                                    'from-violet-500 to-pink-500 text-white',
                                ]"
                            >
                                <span v-if="connectingSource === source.key" class="flex items-center justify-center gap-2">
                                    <span class="w-3 h-3 border border-white/50 border-t-white rounded-full animate-spin" />
                                    Connecting...
                                </span>
                                <span v-else>Connect {{ source.label }}</span>
                            </button>
                            <button
                                v-else
                                @click="openHelpModal(source.key)"
                                class="flex-1 py-2 text-sm font-medium rounded-lg transition-all bg-[#1a1a24] hover:bg-[#242430] text-gray-300"
                            >
                                Connect {{ source.label }}
                            </button>
                            <button
                                @click="openHelpModal(source.key)"
                                class="px-3 py-2 text-xs text-gray-600 hover:text-gray-400 transition-colors"
                                title="Help"
                            >
                                ?
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="mt-12 card bg-[#111118] border-[#1a1a24] p-6">
                    <h3 class="text-sm font-bold text-gray-300 mb-4">How It Works</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div class="text-center">
                            <span class="text-3xl">1️⃣</span>
                            <p class="text-sm font-medium text-white mt-2">Connect</p>
                            <p class="text-xs text-gray-500 mt-1">Link your music services using OAuth or API tokens</p>
                        </div>
                        <div class="text-center">
                            <span class="text-3xl">2️⃣</span>
                            <p class="text-sm font-medium text-white mt-2">Sync</p>
                            <p class="text-xs text-gray-500 mt-1">Your listening history is automatically imported</p>
                        </div>
                        <div class="text-center">
                            <span class="text-3xl">3️⃣</span>
                            <p class="text-sm font-medium text-white mt-2">Analyze</p>
                            <p class="text-xs text-gray-500 mt-1">View stats, trends, and insights in your dashboard</p>
                        </div>
                    </div>
                </div>
            </template>
        </main>

        <!-- Help Modal -->
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
                    v-if="showHelpModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
                    @click.self="closeHelpModal"
                >
                    <div class="card bg-[#111118] border-[#1a1a24] max-w-lg w-full max-h-[90vh] overflow-y-auto">
                        <div class="flex items-start justify-between p-5 border-b border-[#1a1a24]">
                            <h3 class="text-lg font-bold text-white">
                                Connect {{ availableSources[showHelpModal]?.label ?? showHelpModal }}
                            </h3>
                            <button @click="closeHelpModal" class="text-gray-500 hover:text-white transition-colors">
                                ✕
                            </button>
                        </div>

                        <div class="p-5">
                            <p class="text-sm text-gray-400 mb-4">
                                {{ availableSources[showHelpModal]?.help_text }}
                            </p>

                            <!-- OAuth Sources -->
                            <div v-if="availableSources[showHelpModal]?.oauth">
                                <button
                                    @click="initiateOAuth(showHelpModal)"
                                    :disabled="connectingSource === showHelpModal"
                                    class="w-full py-3 text-sm font-medium rounded-xl transition-all bg-gradient-to-r hover:opacity-90 disabled:opacity-50"
                                    :class="[
                                        showHelpModal === 'spotify' ? 'from-green-500 to-green-600 text-white' :
                                        showHelpModal === 'lastfm' ? 'from-red-500 to-red-600 text-white' :
                                        showHelpModal === 'plex' ? 'from-yellow-500 to-amber-500 text-white' :
                                        'from-violet-500 to-pink-500 text-white',
                                    ]"
                                >
                                    <span v-if="connectingSource === showHelpModal" class="flex items-center justify-center gap-2">
                                        <span class="w-4 h-4 border border-white/50 border-t-white rounded-full animate-spin" />
                                        Connecting...
                                    </span>
                                    <span v-else>Connect via {{ availableSources[showHelpModal]?.label }}</span>
                                </button>
                            </div>

                            <!-- ListenBrainz Token -->
                            <div v-else-if="showHelpModal === 'listenbrainz'" class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">User Token</label>
                                    <input
                                        v-model="manualToken"
                                        type="text"
                                        placeholder="Enter your ListenBrainz user token"
                                        class="input-dark bg-[#0a0a12] border-[#1a1a24] w-full"
                                    />
                                    <p class="text-[10px] text-gray-700 mt-1">
                                        Find your token at
                                        <a href="https://listenbrainz.org/profile/" target="_blank" class="text-violet-400 hover:underline">listenbrainz.org/profile/</a>
                                    </p>
                                </div>
                                <button
                                    @click="connectManual('listenbrainz')"
                                    :disabled="!manualToken || connectingSource === 'listenbrainz'"
                                    class="w-full py-3 text-sm font-medium rounded-xl bg-gradient-to-r from-red-400 to-orange-500 text-white hover:opacity-90 disabled:opacity-50 transition-all"
                                >
                                    <span v-if="connectingSource === 'listenbrainz'" class="flex items-center justify-center gap-2">
                                        <span class="w-4 h-4 border border-white/50 border-t-white rounded-full animate-spin" />
                                        Connecting...
                                    </span>
                                    <span v-else>Connect ListenBrainz</span>
                                </button>
                            </div>

                            <!-- Jellyfin -->
                            <div v-else-if="showHelpModal === 'jellyfin'" class="space-y-3">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Server URL</label>
                                    <input
                                        v-model="manualServerUrl"
                                        type="url"
                                        placeholder="https://your-jellyfin-server.com"
                                        class="input-dark bg-[#0a0a12] border-[#1a1a24] w-full"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">API Key</label>
                                    <input
                                        v-model="manualApiKey"
                                        type="password"
                                        placeholder="Your Jellyfin API key"
                                        class="input-dark bg-[#0a0a12] border-[#1a1a24] w-full"
                                    />
                                    <p class="text-[10px] text-gray-700 mt-1">
                                        Find API keys in Jellyfin Dashboard → Advanced → API Keys
                                    </p>
                                </div>
                                <button
                                    @click="connectManual('jellyfin')"
                                    :disabled="!manualServerUrl || !manualApiKey || connectingSource === 'jellyfin'"
                                    class="w-full py-3 text-sm font-medium rounded-xl bg-gradient-to-r from-cyan-500 to-blue-500 text-white hover:opacity-90 disabled:opacity-50 transition-all"
                                >
                                    <span v-if="connectingSource === 'jellyfin'" class="flex items-center justify-center gap-2">
                                        <span class="w-4 h-4 border border-white/50 border-t-white rounded-full animate-spin" />
                                        Connecting...
                                    </span>
                                    <span v-else>Connect Jellyfin</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
