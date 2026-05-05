<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCustomerStore } from '@/stores/customerStore'

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    subscription: { type: Object, default: () => ({}) },
    initialStats: { type: Object, default: () => ({}) },
    initialTrends: { type: Object, default: () => ({}) },
    initialGenres: { type: Array, default: () => [] },
    initialMoods: { type: Object, default: () => ({}) },
    initialTopArtists: { type: Array, default: () => [] },
    initialTopTracks: { type: Array, default: () => [] },
    initialTopAlbums: { type: Array, default: () => [] },
    initialSources: { type: Array, default: () => [] },
    initialAchievements: { type: Array, default: () => [] },
    initialRecentPlays: { type: Array, default: () => [] },
    nowPlaying: { type: Object, default: null },
    canViewMusic: { type: Boolean, default: false },
    canViewAnalytics: { type: Boolean, default: false },
    canExport: { type: Boolean, default: false },
})

const store = useCustomerStore()

const activeTab = ref('overview')
const selectedPeriod = ref('monthly')
const toast = ref(null)
const isLoading = ref(false)
const isSSEConnected = ref(false)

const stats = ref(props.initialStats)
const trends = ref(props.initialTrends)
const genres = ref(props.initialGenres)
const moods = ref(props.initialMoods)
const topArtists = ref(props.initialTopArtists)
const topTracks = ref(props.initialTopTracks)
const topAlbums = ref(props.initialTopAlbums)
const sources = ref(props.initialSources)
const achievements = ref(props.initialAchievements)
const recentPlays = ref(props.initialRecentPlays)
const nowPlaying = ref(props.nowPlaying)
const recentScrollContainer = ref(null)

let eventSource = null

onMounted(() => {
    if (props.canViewMusic) {
        loadDashboard()
        connectSSE()
    }
})

onUnmounted(() => {
    disconnectSSE()
})

const tabs = computed(() => {
    const all = [
        { id: 'overview', label: 'Overview', icon: '📊' },
        { id: 'artists', label: 'Top Artists', icon: '🎤' },
        { id: 'tracks', label: 'Top Tracks', icon: '🎵' },
        { id: 'albums', label: 'Albums', icon: '💿' },
        { id: 'genres', label: 'Genres', icon: '🎸' },
        { id: 'trends', label: 'Trends', icon: '📈' },
        { id: 'moods', label: 'Moods', icon: '🎭' },
        { id: 'sources', label: 'Sources', icon: '🔗' },
        { id: 'achievements', label: 'Achievements', icon: '🏆' },
    ]
    return all.filter(tab => {
        if (['trends', 'moods', 'genres'].includes(tab.id)) return props.canViewAnalytics
        return true
    })
})

const periodOptions = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'yearly', label: 'Yearly' },
]

const genreColors = [
    '#8B5CF6', '#EC4899', '#10B981', '#F59E0B', '#3B82F6',
    '#EF4444', '#06B6D4', '#84CC16', '#F97316', '#6366F1',
    '#14B8A6', '#E11D48', '#7C3AED', '#059669', '#D946EF',
    '#0EA5E9', '#F43F5E', '#A3E635', '#2563EB', '#FB923C',
]

const streakEmoji = computed(() => {
    const streak = stats.value.listening_streak ?? 0
    if (streak >= 365) return '🔥'
    if (streak >= 100) return '⚡'
    if (streak >= 30) return '🌟'
    if (streak >= 7) return '✨'
    if (streak >= 1) return '🎵'
    return '🎶'
})

function formatDuration(ms) {
    if (!ms || ms === 0) return '0s'
    const hours = Math.floor(ms / 3600000)
    const minutes = Math.floor((ms % 3600000) / 60000)
    const seconds = Math.floor((ms % 60000) / 1000)
    if (hours > 0) return `${hours}h ${minutes}m`
    if (minutes > 0) return `${minutes}m ${seconds}s`
    return `${seconds}s`
}

function formatNumber(n) {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M'
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K'
    return n?.toString() ?? '0'
}

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

async function loadDashboard() {
    isLoading.value = true
    try {
        const period = selectedPeriod.value
        const res = await fetch(`/api/v1/music/stats?period=${period}`, {
            headers: { 'Accept': 'application/json' },
        })
        if (res.ok) {
            const data = await res.json()
            stats.value = data.stats ?? stats.value
            trends.value = data.trends ?? trends.value
            genres.value = data.genres ?? genres.value
            moods.value = data.moods ?? moods.value
            topArtists.value = data.top_artists ?? topArtists.value
            topTracks.value = data.top_tracks ?? topTracks.value
            topAlbums.value = data.top_albums ?? topAlbums.value
            recentPlays.value = data.recent_plays ?? recentPlays.value
        }
    } catch (e) {
        showToast('Failed to load music stats', 'error')
    } finally {
        isLoading.value = false
    }
}

function connectSSE() {
    if (!props.canViewMusic || eventSource) return

    try {
        eventSource = new EventSource('/api/v1/music/sse')

        eventSource.onopen = () => {
            isSSEConnected.value = true
        }

        eventSource.addEventListener('scrobble', (e) => {
            try {
                const data = JSON.parse(e.data)
                recentPlays.value.unshift(data)
                if (recentPlays.value.length > 100) {
                    recentPlays.value = recentPlays.value.slice(0, 100)
                }
                nowPlaying.value = {
                    track_name: data.track_name,
                    artist_name: data.artist_name,
                    album_name: data.album_name,
                    source_type: data.source_type,
                    played_at: data.played_at,
                    is_playing: true,
                }
            } catch (e) {
                console.error('SSE parse error', e)
            }
        })

        eventSource.addEventListener('now_playing', (e) => {
            try {
                nowPlaying.value = JSON.parse(e.data)
            } catch (e) {
                console.error('SSE parse error', e)
            }
        })

        eventSource.onerror = () => {
            isSSEConnected.value = false
            eventSource.close()
            eventSource = null
            setTimeout(connectSSE, 30000)
        }
    } catch (e) {
        console.error('SSE connection failed', e)
    }
}

function disconnectSSE() {
    if (eventSource) {
        eventSource.close()
        eventSource = null
        isSSEConnected.value = false
    }
}

async function exportData(format = 'json') {
    if (!props.canExport) {
        showToast('Export is available on Pro and Enterprise plans', 'error')
        return
    }

    try {
        const period = selectedPeriod.value
        const res = await fetch(`/api/v1/music/export?period=${period}&format=${format}`, {
            headers: { 'Accept': format === 'csv' ? 'text/csv' : 'application/json' },
        })
        if (res.ok) {
            const blob = await res.blob()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = `music-stats-${period}-${new Date().toISOString().split('T')[0]}.${format}`
            a.click()
            URL.revokeObjectURL(url)
            showToast(`Exported as ${format.toUpperCase()}`, 'success')
        }
    } catch (e) {
        showToast('Export failed', 'error')
    }
}

function showToast(message, type = 'info') {
    toast.value = { message, type }
    setTimeout(() => { toast.value = null }, 4000)
}

function scrollRecentToBottom() {
    if (recentScrollContainer.value) {
        recentScrollContainer.value.scrollTop = 0
    }
}

const totalPlays = computed(() => stats.value.total_plays ?? 0)
const totalHours = computed(() => stats.value.total_hours ?? 0)
const streak = computed(() => stats.value.listening_streak ?? 0)
const uniqueArtists = computed(() => stats.value.unique_artists ?? 0)
const trendPct = computed(() => stats.value.trend_percentage ?? 0)

const pieChartData = computed(() => {
    return genres.value.slice(0, 8).map((g, i) => ({
        ...g,
        color: genreColors[i % genreColors.length],
    }))
})

const pieChartTotal = computed(() => {
    return pieChartData.value.reduce((sum, g) => sum + g.count, 0) || 1
})

function pieSliceStyle(genre) {
    const pct = (genre.count / pieChartTotal.value) * 100
    return { width: `${pct}%`, backgroundColor: genre.color }
}

function trendColor(pct) {
    if (pct > 0) return 'text-green-400'
    if (pct < 0) return 'text-red-400'
    return 'text-gray-400'
}

function trendArrow(pct) {
    if (pct > 0) return '↑'
    if (pct < 0) return '↓'
    return '→'
}

function moodBarWidth(confidence) {
    return `${Math.min(100, confidence * 100)}%`
}

function moodBarColor(name) {
    const colors = {
        'Energetic': 'bg-orange-500',
        'Happy': 'bg-yellow-400',
        'Melancholic': 'bg-blue-500',
        'Calm': 'bg-teal-400',
        'Danceable': 'bg-pink-500',
        'Intense': 'bg-red-600',
        'Acoustic': 'bg-green-400',
        'Instrumental': 'bg-purple-400',
        'Live': 'bg-cyan-500',
        'Neutral': 'bg-gray-400',
    }
    return colors[name] ?? 'bg-gray-500'
}
</script>

<template>
    <Head title="Music Dashboard | BLBGenSix AI" />

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
                    <Link href="/dashboard" class="text-lg text-gray-400 hover:text-white transition-colors">
                        ← Dashboard
                    </Link>
                    <span class="text-xl font-bold gradient-text">Music</span>
                    <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-green-600/20 text-green-400 border border-green-600/30" v-if="isSSEConnected">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse" /> Live
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        v-if="canViewMusic"
                        href="/music/connect"
                        class="text-xs text-gray-400 hover:text-white transition-colors"
                    >
                        🔗 Connect Sources
                    </Link>
                    <div v-if="canExport" class="flex items-center gap-1">
                        <button @click="exportData('json')" class="px-2 py-1 text-xs bg-[#1a1a24] hover:bg-[#242430] rounded-lg text-gray-400 hover:text-white transition-colors">
                            JSON
                        </button>
                        <button @click="exportData('csv')" class="px-2 py-1 text-xs bg-[#1a1a24] hover:bg-[#242430] rounded-lg text-gray-400 hover:text-white transition-colors">
                            CSV
                        </button>
                    </div>
                    <select
                        v-model="selectedPeriod"
                        @change="loadDashboard"
                        class="px-2 py-1 text-xs bg-[#1a1a24] border border-[#242430] rounded-lg text-gray-300 focus:outline-none focus:border-violet-500"
                    >
                        <option v-for="p in periodOptions" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
            </div>
        </nav>

        <!-- Main -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
            <!-- Not available state -->
            <div v-if="!canViewMusic" class="text-center py-20">
                <span class="text-6xl">🎵</span>
                <h2 class="text-2xl font-bold mt-4 text-white">Music Features</h2>
                <p class="text-gray-400 mt-2 max-w-md mx-auto">
                    Connect your music sources and unlock listening analytics. Available on Starter plans and above.
                </p>
                <Link href="/pricing" class="btn-primary mt-6 inline-block">Upgrade Plan</Link>
            </div>

            <template v-if="canViewMusic">
                <!-- Loading -->
                <div v-if="isLoading" class="flex items-center justify-center py-20">
                    <div class="animate-spin w-8 h-8 border-2 border-violet-500 border-t-transparent rounded-full" />
                </div>

                <template v-if="!isLoading">
                    <!-- Tabs -->
                    <div class="flex items-center gap-1 mb-6 p-1 bg-[#111118] rounded-xl border border-[#1a1a24] w-fit overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            class="px-3 py-2 text-xs font-medium rounded-lg transition-all whitespace-nowrap"
                            :class="activeTab === tab.id
                                ? 'bg-[#1a1a24] text-white'
                                : 'text-gray-500 hover:text-gray-300'"
                        >
                            {{ tab.icon }} {{ tab.label }}
                        </button>
                    </div>

                    <!-- ─── Now Playing ─── -->
                    <div v-if="nowPlaying && nowPlaying.is_playing" class="card bg-[#111118] border border-violet-500/30 p-4 mb-6">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-violet-500/20 to-pink-500/20 flex items-center justify-center text-3xl shrink-0">
                                🎵
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse" />
                                    <span class="text-xs text-green-400 font-medium">NOW PLAYING</span>
                                    <span v-if="nowPlaying.source_type" class="text-xs text-gray-600">via {{ nowPlaying.source_type }}</span>
                                </div>
                                <p class="text-lg font-bold text-white truncate mt-1">{{ nowPlaying.track_name }}</p>
                                <p class="text-sm text-gray-400 truncate">{{ nowPlaying.artist_name }}</p>
                                <p v-if="nowPlaying.album_name" class="text-xs text-gray-600 truncate">{{ nowPlaying.album_name }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- ─── Overview Tab ─── -->
                    <div v-if="activeTab === 'overview'" class="space-y-6">
                        <!-- Stats Grid -->
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="card bg-[#111118] border-[#1a1a24] p-4 text-center">
                                <p class="text-2xl font-black text-violet-400">{{ formatNumber(totalPlays) }}</p>
                                <p class="text-xs text-gray-500 mt-1">Total Plays</p>
                                <span class="text-xs mt-1" :class="trendColor(trendPct)">
                                    {{ trendArrow(trendPct) }} {{ Math.abs(trendPct) }}% vs last period
                                </span>
                            </div>
                            <div class="card bg-[#111118] border-[#1a1a24] p-4 text-center">
                                <p class="text-2xl font-black text-pink-400">{{ totalHours }}</p>
                                <p class="text-xs text-gray-500 mt-1">Hours</p>
                            </div>
                            <div class="card bg-[#111118] border-[#1a1a24] p-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="text-2xl font-black text-orange-400">{{ streakEmoji }}</span>
                                    <span class="text-2xl font-black text-orange-400">{{ streak }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Day Streak</p>
                            </div>
                            <div class="card bg-[#111118] border-[#1a1a24] p-4 text-center">
                                <p class="text-2xl font-black text-green-400">{{ formatNumber(uniqueArtists) }}</p>
                                <p class="text-xs text-gray-500 mt-1">Artists</p>
                            </div>
                        </div>

                        <!-- Genre Distribution + Recent Plays -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Genre Pie Chart -->
                            <div v-if="genres.length" class="card bg-[#111118] border-[#1a1a24] p-5">
                                <h3 class="text-sm font-bold text-gray-300 mb-4">Genre Distribution</h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="genre in pieChartData"
                                        :key="genre.slug"
                                        class="flex items-center gap-3"
                                    >
                                        <div class="w-3 h-3 rounded-sm shrink-0" :style="{ backgroundColor: genre.color }" />
                                        <span class="text-xs text-gray-400 w-24 truncate">{{ genre.name }}</span>
                                        <div class="flex-1 h-2 bg-[#0a0a12] rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all duration-500"
                                                :style="pieSliceStyle(genre)"
                                            />
                                        </div>
                                        <span class="text-xs text-gray-500 w-10 text-right">{{ genre.percentage }}%</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="card bg-[#111118] border-[#1a1a24] p-5 text-center py-10">
                                <p class="text-3xl mb-2">🎸</p>
                                <p class="text-sm text-gray-500">Genre data will appear after listening</p>
                            </div>

                            <!-- Recent Plays -->
                            <div class="card bg-[#111118] border-[#1a1a24] p-5">
                                <h3 class="text-sm font-bold text-gray-300 mb-4">Recent Plays</h3>
                                <div
                                    ref="recentScrollContainer"
                                    class="space-y-2 max-h-80 overflow-y-auto"
                                >
                                    <div
                                        v-for="play in recentPlays.slice(0, 20)"
                                        :key="play.id || play.track_name + play.played_at"
                                        class="flex items-center gap-3 py-2 border-b border-[#1a1a24] last:border-0"
                                    >
                                        <div class="w-10 h-10 rounded-lg bg-[#0a0a12] flex items-center justify-center text-lg shrink-0">
                                            🎵
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-300 truncate">{{ play.track_name }}</p>
                                            <p class="text-xs text-gray-600 truncate">{{ play.artist_name }}</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-xs text-gray-600">{{ timeAgo(play.played_at) }}</p>
                                            <p v-if="play.source_type" class="text-[10px] text-gray-700">{{ play.source_type }}</p>
                                        </div>
                                    </div>
                                    <div v-if="!recentPlays.length" class="text-center py-8">
                                        <p class="text-2xl mb-2">🎧</p>
                                        <p class="text-xs text-gray-600">No plays yet. Connect a source to start tracking!</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Connected Sources Overview -->
                        <div v-if="canViewAnalytics && sources.length" class="card bg-[#111118] border-[#1a1a24] p-5">
                            <h3 class="text-sm font-bold text-gray-300 mb-4">Connected Sources</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                <div
                                    v-for="source in sources"
                                    :key="source.id"
                                    class="flex items-center gap-3 p-3 bg-[#0a0a12] rounded-xl border"
                                    :class="source.is_connected ? 'border-green-600/30' : 'border-red-600/30'"
                                >
                                    <span class="w-2 h-2 rounded-full" :class="source.is_connected ? 'bg-green-400' : 'bg-red-400'" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-300">{{ source.source_label }}</p>
                                        <p class="text-xs text-gray-600">{{ source.is_connected ? 'Connected' : 'Disconnected' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ─── Artists Tab ─── -->
                    <div v-if="activeTab === 'artists'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="(artist, i) in topArtists"
                                :key="artist.name"
                                class="card bg-[#111118] border-[#1a1a24] p-4 hover:border-violet-500/30 transition-colors"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="text-lg font-black text-gray-700 w-6 text-center">{{ i + 1 }}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-white truncate">{{ artist.name }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ formatNumber(artist.play_count) }} plays</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!topArtists.length" class="text-center py-20">
                            <span class="text-4xl">🎤</span>
                            <p class="text-gray-500 mt-3">Top artists will appear here</p>
                        </div>
                    </div>

                    <!-- ─── Tracks Tab ─── -->
                    <div v-if="activeTab === 'tracks'" class="space-y-4">
                        <div class="card bg-[#111118] border-[#1a1a24] divide-y divide-[#1a1a24]">
                            <div
                                v-for="(track, i) in topTracks"
                                :key="track.track_name + track.artist_name"
                                class="flex items-center gap-4 p-4 hover:bg-[#1a1a24]/50 transition-colors"
                            >
                                <span class="text-lg font-black text-gray-700 w-8 text-center">{{ i + 1 }}</span>
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-violet-500/20 to-pink-500/20 flex items-center justify-center text-lg shrink-0">
                                    🎵
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-white truncate">{{ track.track_name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ track.artist_name }} · {{ track.album_name ?? 'Unknown Album' }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm text-gray-400">{{ formatNumber(track.play_count) }}×</p>
                                    <p class="text-xs text-gray-700">{{ formatDuration(track.duration_ms) }}</p>
                                </div>
                            </div>
                        </div>
                        <div v-if="!topTracks.length" class="text-center py-20">
                            <span class="text-4xl">🎵</span>
                            <p class="text-gray-500 mt-3">Top tracks will appear here</p>
                        </div>
                    </div>

                    <!-- ─── Albums Tab ─── -->
                    <div v-if="activeTab === 'albums'" class="space-y-4">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                            <div
                                v-for="(album, i) in topAlbums"
                                :key="album.album_name"
                                class="card bg-[#111118] border-[#1a1a24] p-4 hover:border-pink-500/30 transition-colors"
                            >
                                <div class="aspect-square bg-gradient-to-br from-[#1a1a24] to-[#0a0a12] rounded-xl flex items-center justify-center text-4xl mb-3">
                                    💿
                                </div>
                                <p class="text-sm font-bold text-white truncate">{{ album.album_name }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ album.artist_name }}</p>
                                <p class="text-xs text-gray-700 mt-1">{{ formatNumber(album.play_count) }} plays</p>
                            </div>
                        </div>
                        <div v-if="!topAlbums.length" class="text-center py-20">
                            <span class="text-4xl">💿</span>
                            <p class="text-gray-500 mt-3">Top albums will appear here</p>
                        </div>
                    </div>

                    <!-- ─── Genres Tab ─── -->
                    <div v-if="activeTab === 'genres'" class="space-y-6">
                        <div v-if="genres.length" class="card bg-[#111118] border-[#1a1a24] p-6">
                            <h3 class="text-sm font-bold text-gray-300 mb-6">Genre Breakdown</h3>
                            <div class="space-y-4">
                                <div
                                    v-for="(genre, i) in genres"
                                    :key="genre.slug"
                                    class="flex items-center gap-4"
                                >
                                    <div class="w-4 h-4 rounded" :style="{ backgroundColor: genreColors[i % genreColors.length] }" />
                                    <span class="text-sm text-gray-300 w-32 truncate">{{ genre.name }}</span>
                                    <div class="flex-1 h-3 bg-[#0a0a12] rounded-full overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all duration-700"
                                            :style="{ width: genre.percentage + '%', backgroundColor: genreColors[i % genreColors.length] }"
                                        />
                                    </div>
                                    <span class="text-sm text-gray-400 w-16 text-right">{{ genre.count }}</span>
                                    <span class="text-xs text-gray-600 w-12 text-right">{{ genre.percentage }}%</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20">
                            <span class="text-4xl">🎸</span>
                            <p class="text-gray-500 mt-3">Genre analysis requires connected music sources</p>
                        </div>
                    </div>

                    <!-- ─── Trends Tab ─── -->
                    <div v-if="activeTab === 'trends'" class="space-y-6">
                        <div v-if="trends.timeline?.length" class="card bg-[#111118] border-[#1a1a24] p-6">
                            <h3 class="text-sm font-bold text-gray-300 mb-4">Listening Timeline</h3>
                            <div class="flex items-end gap-1 h-40">
                                <div
                                    v-for="(point, i) in trends.timeline"
                                    :key="point.period"
                                    class="flex-1 bg-violet-500/20 rounded-t-sm hover:bg-violet-500/40 transition-colors relative group"
                                    :style="{ height: Math.max(2, (point.plays / Math.max(...trends.timeline.map(t => t.plays))) * 100) + '%' }"
                                >
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-[#1a1a24] rounded text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                        {{ point.plays }} plays · {{ point.period }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between mt-2 text-[10px] text-gray-700">
                                <span>{{ trends.timeline[0]?.period }}</span>
                                <span>{{ trends.timeline[trends.timeline.length - 1]?.period }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Hourly -->
                            <div class="card bg-[#111118] border-[#1a1a24] p-5">
                                <h3 class="text-sm font-bold text-gray-300 mb-4">Listening by Hour</h3>
                                <div class="flex items-end gap-0.5 h-32">
                                    <div
                                        v-for="h in trends.hourly_distribution"
                                        :key="h.hour ?? 'unknown'"
                                        class="flex-1 bg-pink-500/20 rounded-t-sm"
                                        :style="{ height: Math.max(2, (h.plays / Math.max(1, ...trends.hourly_distribution.map(d => d.plays))) * 100) + '%' }"
                                    />
                                </div>
                                <div class="flex justify-between mt-1 text-[9px] text-gray-700">
                                    <span>0h</span><span>12h</span><span>23h</span>
                                </div>
                                <p v-if="trends.peak_listening_hour !== null" class="text-xs text-gray-500 mt-2">
                                    Peak hour: {{ trends.peak_listening_hour }}:00
                                </p>
                            </div>

                            <!-- Weekly -->
                            <div class="card bg-[#111118] border-[#1a1a24] p-5">
                                <h3 class="text-sm font-bold text-gray-300 mb-4">Listening by Day</h3>
                                <div class="flex items-end gap-2 h-32">
                                    <div
                                        v-for="d in trends.weekly_distribution"
                                        :key="d.day"
                                        class="flex-1 bg-green-500/20 rounded-t-sm"
                                        :style="{ height: Math.max(2, (d.plays / Math.max(1, ...trends.weekly_distribution.map(dd => dd.plays))) * 100) + '%' }"
                                    />
                                </div>
                                <div class="flex justify-between mt-1 text-[9px] text-gray-700">
                                    <span v-for="d in trends.weekly_distribution" :key="d.day">{{ d.day.slice(0, 2) }}</span>
                                </div>
                                <p v-if="trends.peak_listening_day" class="text-xs text-gray-500 mt-2">
                                    Peak day: {{ trends.peak_listening_day }}
                                </p>
                            </div>
                        </div>

                        <div v-if="!trends.timeline?.length" class="text-center py-20">
                            <span class="text-4xl">📈</span>
                            <p class="text-gray-500 mt-3">Trend data will appear after listening</p>
                        </div>
                    </div>

                    <!-- ─── Moods Tab ─── -->
                    <div v-if="activeTab === 'moods'" class="space-y-6">
                        <div v-if="moods.moods?.length" class="card bg-[#111118] border-[#1a1a24] p-6">
                            <h3 class="text-sm font-bold text-gray-300 mb-2">Mood Analysis</h3>
                            <p class="text-lg font-bold gradient-text mb-6">
                                {{ moods.primary_mood }}
                            </p>
                            <div class="space-y-3">
                                <div
                                    v-for="mood in moods.moods"
                                    :key="mood.name"
                                >
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="text-gray-300">{{ mood.name }}</span>
                                        <span class="text-gray-500">{{ Math.round(mood.confidence * 100) }}%</span>
                                    </div>
                                    <div class="h-2.5 bg-[#0a0a12] rounded-full overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all duration-700"
                                            :class="moodBarColor(mood.name)"
                                            :style="{ width: moodBarWidth(mood.confidence) }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Feature Scores -->
                        <div v-if="moods.features" class="card bg-[#111118] border-[#1a1a24] p-6">
                            <h3 class="text-sm font-bold text-gray-300 mb-4">Audio Features</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <div
                                    v-for="(value, key) in moods.features"
                                    :key="key"
                                    class="text-center"
                                >
                                    <p class="text-xl font-bold" :class="value > 0.6 ? 'text-green-400' : value > 0.4 ? 'text-yellow-400' : 'text-gray-500'">
                                        {{ (value * 100).toFixed(0) }}%
                                    </p>
                                    <p class="text-xs text-gray-600 mt-1 capitalize">{{ key }}</p>
                                </div>
                            </div>
                            <p v-if="moods.sample_size > 0" class="text-xs text-gray-700 mt-4">Based on {{ moods.sample_size }} tracks</p>
                        </div>

                        <div v-if="!moods.moods?.length" class="text-center py-20">
                            <span class="text-4xl">🎭</span>
                            <p class="text-gray-500 mt-3">Mood analysis requires tracks with audio features</p>
                        </div>
                    </div>

                    <!-- ─── Sources Tab ─── -->
                    <div v-if="activeTab === 'sources'" class="space-y-4">
                        <div v-if="sources.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="source in sources"
                                :key="source.id"
                                class="card bg-[#111118] border-[#1a1a24] p-5"
                            >
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <p class="text-sm font-bold text-white">{{ source.source_label }}</p>
                                        <p v-if="source.external_username" class="text-xs text-gray-500">@{{ source.external_username }}</p>
                                    </div>
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-medium"
                                        :class="source.is_connected
                                            ? 'bg-green-600/20 text-green-400 border border-green-600/30'
                                            : 'bg-red-600/20 text-red-400 border border-red-600/30'"
                                    >
                                        {{ source.is_connected ? 'Connected' : 'Disconnected' }}
                                    </span>
                                </div>
                                <div class="space-y-1 text-xs text-gray-600">
                                    <p>Last sync: {{ timeAgo(source.last_sync_at) }}</p>
                                    <p v-if="source.last_scrobble_at">Last play: {{ timeAgo(source.last_scrobble_at) }}</p>
                                </div>
                                <div class="mt-3 pt-3 border-t border-[#1a1a24]">
                                    <Link
                                        :href="`/music/connect?reconnect=${source.source_type}`"
                                        class="text-xs text-violet-400 hover:text-violet-300"
                                    >
                                        {{ source.is_connected ? 'Manage' : 'Reconnect' }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-20">
                            <span class="text-4xl">🔗</span>
                            <p class="text-gray-500 mt-3">No sources connected yet</p>
                            <Link href="/music/connect" class="btn-primary mt-4 inline-block">Connect a Source</Link>
                        </div>
                    </div>

                    <!-- ─── Achievements Tab ─── -->
                    <div v-if="activeTab === 'achievements'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="ach in achievements"
                                :key="ach.key"
                                class="card p-5 transition-colors"
                                :class="ach.is_unlocked
                                    ? 'bg-[#111118] border border-violet-500/30'
                                    : 'bg-[#0a0a12] border border-[#1a1a24] opacity-60'"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="text-2xl">{{ ach.is_unlocked ? '🏆' : '🔒' }}</span>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold" :class="ach.is_unlocked ? 'text-white' : 'text-gray-600'">
                                            {{ ach.name }}
                                        </p>
                                        <p v-if="ach.description" class="text-xs text-gray-600 mt-1">{{ ach.description }}</p>
                                        <div class="mt-2">
                                            <div class="h-1.5 bg-[#1a1a24] rounded-full overflow-hidden">
                                                <div
                                                    class="h-full bg-violet-500 rounded-full transition-all"
                                                    :style="{ width: Math.min(100, (ach.progress / Math.max(1, ach.threshold)) * 100) + '%' }"
                                                />
                                            </div>
                                            <p class="text-[10px] text-gray-700 mt-1">
                                                {{ ach.progress }} / {{ ach.threshold }}
                                            </p>
                                        </div>
                                        <p v-if="ach.unlocked_at" class="text-[10px] text-violet-500 mt-1">
                                            Unlocked {{ timeAgo(ach.unlocked_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!achievements.length" class="text-center py-20">
                            <span class="text-4xl">🏆</span>
                            <p class="text-gray-500 mt-3">Start listening to unlock achievements!</p>
                        </div>
                    </div>
                </template>
            </template>
        </main>
    </div>
</template>
