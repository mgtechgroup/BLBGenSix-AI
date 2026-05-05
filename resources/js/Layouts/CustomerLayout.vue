<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import { useCustomerStore } from '@/stores/customerStore'
import { useFeatures } from '@/composables/useFeatures'

const store = useCustomerStore()
const page = usePage()
const { planLabel, isActive, hasFeature } = useFeatures()

// ─── Sidebar State ─────────────────────────────────────────
const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)

// ─── User Menu ────────────────────────────────────────────
const userMenuOpen = ref(false)

// ─── Notifications ────────────────────────────────────────
const notificationsOpen = ref(false)
const notifications = ref([
    { id: 1, type: 'success', message: 'Image generation completed', time: Date.now() - 300000, read: false },
    { id: 2, type: 'info', message: 'New feature: Video upscaling now available', time: Date.now() - 3600000, read: false },
    { id: 3, type: 'warning', message: 'Storage at 85% capacity', time: Date.now() - 86400000, read: true },
])
const unreadCount = computed(() => notifications.value.filter(n => !n.read).length)

// ─── Navigation Items ──────────────────────────────────────
const navigationItems = computed(() => [
    {
        name: 'Dashboard',
        route: 'customer.dashboard',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z',
        active: page.component?.includes('Customer/Dashboard'),
    },
    {
        name: 'Billing',
        route: 'customer.billing',
        icon: 'M3 10h18M7 15h1m4 0h1m4 0h1M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        active: page.component?.includes('Customer/Billing'),
    },
    {
        name: 'Profile',
        route: 'customer.profile',
        icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        active: page.component?.includes('Customer/Profile'),
    },
    {
        name: 'Search',
        route: 'customer.search',
        icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
        active: page.component?.includes('Customer/Search'),
    },
    {
        name: 'Generate',
        route: 'customer.generate',
        icon: 'M12 4v16m8-8H4',
        active: page.component?.includes('Customer/Generate'),
    },
    {
        name: 'Music',
        route: 'customer.music',
        icon: 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z',
        active: page.component?.includes('Customer/Music'),
        requires: 'music:dashboard',
    },
    {
        name: 'Gallery',
        route: 'customer.gallery',
        icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        active: page.component?.includes('Customer/Gallery'),
    },
    {
        name: 'Revenue',
        route: 'customer.revenue',
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        active: page.component?.includes('Customer/Revenue'),
        requires: 'dashboard:revenue',
    },
    {
        name: 'Activity',
        route: 'customer.activity',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        active: page.component?.includes('Customer/Activity'),
        requires: 'dashboard:activity',
    },
    {
        name: 'Support',
        route: 'customer.support',
        icon: 'M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z',
        active: page.component?.includes('Customer/Support'),
    },
])

const visibleNavItems = computed(() =>
    navigationItems.value.filter(item => !item.requires || hasFeature(item.requires))
)

// ─── Mobile Detection ──────────────────────────────────────
const isMobile = ref(false)
function checkMobile() {
    isMobile.value = window.innerWidth < 768
    if (!isMobile.value) sidebarOpen.value = false
}

// ─── Close Dropdowns on Outside Click ──────────────────────
function handleClickOutside(e) {
    if (!e.target.closest('.user-menu-trigger') && !e.target.closest('.user-menu')) {
        userMenuOpen.value = false
    }
    if (!e.target.closest('.notifications-trigger') && !e.target.closest('.notifications-menu')) {
        notificationsOpen.value = false
    }
}

// ─── Actions ───────────────────────────────────────────────
function toggleSidebar() {
    if (isMobile.value) {
        sidebarOpen.value = !sidebarOpen.value
    } else {
        sidebarCollapsed.value = !sidebarCollapsed.value
    }
}

function closeSidebar() {
    sidebarOpen.value = false
}

function markAllRead() {
    notifications.value = notifications.value.map(n => ({ ...n, read: true }))
}

function logout() {
    router.post('/logout')
}

function navLinkClass(item) {
    const base = 'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 group'
    if (item.active) {
        return `${base} bg-violet-600/20 text-violet-400 border border-violet-500/30`
    }
    return `${base} text-gray-400 hover:text-white hover:bg-[#1a1a24]`
}

function sidebarWidth() {
    return sidebarCollapsed.value ? 'w-[72px]' : 'w-64'
}

function timeAgo(timestamp) {
    const seconds = Math.floor((Date.now() - timestamp) / 1000)
    if (seconds < 60) return 'just now'
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    return new Date(timestamp).toLocaleDateString()
}

// ─── Lifecycle ─────────────────────────────────────────────
onMounted(() => {
    checkMobile()
    window.addEventListener('resize', checkMobile)
    document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-[#0a0a12] text-gray-100 flex">
        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- MOBILE SIDEBAR OVERLAY                                  -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen && isMobile"
                class="fixed inset-0 bg-black/70 backdrop-blur-sm z-40 md:hidden"
                @click="closeSidebar"
            />
        </transition>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- SIDEBAR NAVIGATION                                      -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <aside
            class="fixed md:sticky top-0 left-0 h-screen bg-[#0f0f18] border-r border-[#1a1a24] flex flex-col z-50 transition-all duration-300 ease-in-out"
            :class="[
                sidebarWidth(),
                isMobile ? (sidebarOpen ? 'translate-x-0' : '-translate-x-full') : 'translate-x-0'
            ]"
        >
            <!-- Logo -->
            <div class="p-4 border-b border-[#1a1a24] flex items-center justify-between">
                <Link href="/customer/dashboard" class="flex items-center gap-2">
                    <span v-if="!sidebarCollapsed" class="text-xl font-bold gradient-text">BLBGenSix</span>
                    <span v-else class="text-xl font-bold gradient-text">B</span>
                </Link>
                <button
                    @click="toggleSidebar"
                    class="text-gray-500 hover:text-white transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="sidebarCollapsed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
            </div>

            <!-- Plan Badge -->
            <div class="px-4 py-3 border-b border-[#1a1a24]">
                <div
                    class="flex items-center gap-2 px-3 py-2 rounded-xl bg-[#1a1a24]"
                    :class="{ 'justify-center': sidebarCollapsed }"
                >
                    <div class="w-2 h-2 rounded-full animate-pulse" :class="isActive ? 'bg-green-400' : 'bg-gray-500'" />
                    <span v-if="!sidebarCollapsed" class="text-xs font-medium text-gray-300">
                        {{ planLabel }}
                        <span v-if="isActive" class="text-green-400">· Active</span>
                    </span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1 scrollbar-thin">
                <Link
                    v-for="item in visibleNavItems"
                    :key="item.route"
                    :href="route(item.route)"
                    :class="navLinkClass(item)"
                    @click="closeSidebar"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="item.icon" />
                    </svg>
                    <span v-if="!sidebarCollapsed" class="truncate">{{ item.name }}</span>
                    <span
                        v-if="!sidebarCollapsed && item.name === 'Generate'"
                        class="ml-auto px-1.5 py-0.5 text-[10px] font-bold bg-violet-600/30 text-violet-400 rounded"
                    >
                        AI
                    </span>
                </Link>
            </nav>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-[#1a1a24]">
                <button
                    @click="logout"
                    class="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-medium text-red-400 hover:bg-red-500/10 transition-colors"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span v-if="!sidebarCollapsed">Logout</span>
                </button>
            </div>
        </aside>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- MAIN CONTENT AREA                                        -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="flex-1 flex flex-col min-h-screen overflow-x-hidden">
            <!-- ─── TOP HEADER BAR ──────────────────────────── -->
            <header class="sticky top-0 z-30 bg-[#0a0a12]/85 backdrop-blur-xl border-b border-[#1a1a24]">
                <div class="flex items-center justify-between px-4 sm:px-6 py-3">
                    <!-- Left: Hamburger + Page Title -->
                    <div class="flex items-center gap-3">
                        <button
                            @click="toggleSidebar"
                            class="md:hidden text-gray-400 hover:text-white transition-colors"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <h1 class="text-lg font-bold text-white hidden sm:block">
                            {{ $page.props.title || 'Dashboard' }}
                        </h1>
                    </div>

                    <!-- Right: Actions -->
                    <div class="flex items-center gap-2 sm:gap-4">
                        <!-- Search shortcut -->
                        <button
                            @click="router.visit('/customer/search')"
                            class="p-2 text-gray-400 hover:text-white hover:bg-[#1a1a24] rounded-lg transition-colors"
                            title="Search"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>

                        <!-- Notifications -->
                        <div class="relative notifications-trigger">
                            <button
                                @click="notificationsOpen = !notificationsOpen"
                                class="p-2 text-gray-400 hover:text-white hover:bg-[#1a1a24] rounded-lg transition-colors relative"
                                title="Notifications"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span
                                    v-if="unreadCount > 0"
                                    class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"
                                >
                                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                                </span>
                            </button>

                            <!-- Notifications Dropdown -->
                            <transition
                                enter-active-class="transition-all duration-200"
                                enter-from-class="opacity-0 translate-y-2"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition-all duration-150"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 translate-y-2"
                            >
                                <div
                                    v-if="notificationsOpen"
                                    class="notifications-menu absolute right-0 mt-2 w-80 bg-[#111118] border border-[#1a1a24] rounded-2xl shadow-2xl overflow-hidden z-50"
                                >
                                    <div class="flex items-center justify-between px-4 py-3 border-b border-[#1a1a24]">
                                        <h3 class="font-bold text-white text-sm">Notifications</h3>
                                        <button
                                            @click="markAllRead"
                                            class="text-xs text-violet-400 hover:text-violet-300 transition-colors"
                                        >
                                            Mark all read
                                        </button>
                                    </div>
                                    <div class="max-h-80 overflow-y-auto">
                                        <div
                                            v-for="notification in notifications"
                                            :key="notification.id"
                                            class="px-4 py-3 border-b border-[#1a1a24] last:border-0 hover:bg-[#1a1a24] transition-colors"
                                            :class="{ 'opacity-60': notification.read }"
                                        >
                                            <div class="flex items-start gap-3">
                                                <div
                                                    class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-sm"
                                                    :class="{
                                                        'bg-green-500/10 text-green-400': notification.type === 'success',
                                                        'bg-violet-500/10 text-violet-400': notification.type === 'info',
                                                        'bg-yellow-500/10 text-yellow-400': notification.type === 'warning',
                                                        'bg-red-500/10 text-red-400': notification.type === 'error',
                                                    }"
                                                >
                                                    {{ notification.type === 'success' ? '✓' : notification.type === 'info' ? 'ℹ' : notification.type === 'warning' ? '⚠' : '✕' }}
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm text-gray-300">{{ notification.message }}</p>
                                                    <p class="text-xs text-gray-600 mt-1">{{ timeAgo(notification.time) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- User Menu -->
                        <div class="relative user-menu-trigger">
                            <button
                                @click="userMenuOpen = !userMenuOpen"
                                class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-[#1a1a24] transition-colors"
                            >
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-pink-500 flex items-center justify-center text-xs font-bold text-white">
                                    {{ store.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                                </div>
                                <svg class="w-4 h-4 text-gray-500 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- User Dropdown -->
                            <transition
                                enter-active-class="transition-all duration-200"
                                enter-from-class="opacity-0 translate-y-2"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition-all duration-150"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 translate-y-2"
                            >
                                <div
                                    v-if="userMenuOpen"
                                    class="user-menu absolute right-0 mt-2 w-56 bg-[#111118] border border-[#1a1a24] rounded-2xl shadow-2xl overflow-hidden z-50"
                                >
                                    <div class="px-4 py-3 border-b border-[#1a1a24]">
                                        <p class="text-sm font-bold text-white">{{ store.user?.name || 'User' }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ store.user?.email || '—' }}</p>
                                    </div>
                                    <div class="py-1">
                                        <Link
                                            href="/customer/profile"
                                            class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-[#1a1a24] hover:text-white transition-colors"
                                        >
                                            Profile Settings
                                        </Link>
                                        <Link
                                            href="/customer/billing"
                                            class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-[#1a1a24] hover:text-white transition-colors"
                                        >
                                            Billing & Plans
                                        </Link>
                                        <Link
                                            href="/customer/search"
                                            class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-[#1a1a24] hover:text-white transition-colors"
                                        >
                                            Search
                                        </Link>
                                    </div>
                                    <div class="py-1 border-t border-[#1a1a24]">
                                        <button
                                            @click="logout"
                                            class="w-full text-left px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 transition-colors"
                                        >
                                            Logout
                                        </button>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ─── PAGE CONTENT ────────────────────────────── -->
            <main class="flex-1">
                <slot />
            </main>

            <!-- ─── FOOTER ─────────────────────────────────── -->
            <footer class="border-t border-[#1a1a24] py-4 px-6">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-gray-600">
                    <span>© 2026 BLBGenSix AI. All rights reserved.</span>
                    <div class="flex items-center gap-4">
                        <Link href="/privacy" class="hover:text-gray-400 transition-colors">Privacy</Link>
                        <Link href="/terms" class="hover:text-gray-400 transition-colors">Terms</Link>
                        <Link href="/support" class="hover:text-gray-400 transition-colors">Support</Link>
                    </div>
                </div>
            </footer>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- TOAST NOTIFICATION CONTAINER                              -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <ToastContainer />
    </div>
</template>

<style scoped>
.gradient-text {
    background: linear-gradient(135deg, #8b5cf6, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #1a1a24;
    border-radius: 2px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #242430;
}
</style>
