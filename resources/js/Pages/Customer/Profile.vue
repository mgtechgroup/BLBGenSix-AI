<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useCustomerStore } from '@/stores/customerStore'
import { useFeatures } from '@/composables/useFeatures'
import { api, endpoints } from '@/lib/api'

const store = useCustomerStore()
const { hasFeature } = useFeatures()

// ─── State ────────────────────────────────────────────────
const loading = ref(false)
const error = ref(null)
const activeTab = ref('info')
const avatarPreview = ref(null)
const avatarFile = ref(null)

// User form
const userForm = ref({
    name: '',
    email: '',
    bio: '',
})

// Security
const twoFactorEnabled = ref(false)
const twoFactorSecret = ref('')
const twoFactorCode = ref('')
const passkeys = ref([])
const sessions = ref([])

// API Keys
const apiKeys = ref([])
const newKeyName = ref('')
const generatedKey = ref(null)

// Notification Preferences
const notifications = ref({
    email: { generations: true, billing: true, security: true, marketing: false },
    push: { generations: true, billing: false, security: true, marketing: false },
    sms: { security: true, billing: true, generations: false, marketing: false },
})

// GDPR
const exportStatus = ref('')
const deleteConfirm = ref('')
const deleteStep = ref(1)

// Wallet
const walletConnected = ref(false)
const walletAddress = ref('')
const walletChain = ref('')

// ─── Computed ─────────────────────────────────────────────
const avatarUrl = computed(() => {
    if (avatarPreview.value) return avatarPreview.value
    return store.user?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(userForm.value.name || 'User')}&background=8b5cf6&color=fff`
})

// ─── Actions ──────────────────────────────────────────────
function handleAvatarUpload(event) {
    const file = event.target.files[0]
    if (!file) return
    avatarFile.value = file
    const reader = new FileReader()
    reader.onload = (e) => { avatarPreview.value = e.target.result }
    reader.readAsDataURL(file)
}

async function saveProfile() {
    loading.value = true
    try {
        const formData = new FormData()
        formData.append('name', userForm.value.name)
        formData.append('email', userForm.value.email)
        formData.append('bio', userForm.value.bio)
        if (avatarFile.value) formData.append('avatar', avatarFile.value)

        await api.upload('/api/v1/customer/profile', formData)
        window.$toast?.success('Profile updated successfully!')
        await store.fetchDashboard()
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to update profile')
    } finally {
        loading.value = false
    }
}

async function enable2FA() {
    try {
        const res = await endpoints.security.twoFactor.generate()
        twoFactorSecret.value = res.secret
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to generate 2FA secret')
    }
}

async function confirm2FA() {
    try {
        await endpoints.security.twoFactor.enable(twoFactorCode.value)
        twoFactorEnabled.value = true
        twoFactorSecret.value = ''
        twoFactorCode.value = ''
        window.$toast?.success('Two-factor authentication enabled!')
    } catch (e) {
        window.$toast?.error(e.message || 'Invalid 2FA code')
    }
}

async function disable2FA() {
    try {
        await endpoints.security.twoFactor.disable(twoFactorCode.value)
        twoFactorEnabled.value = false
        window.$toast?.success('Two-factor authentication disabled')
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to disable 2FA')
    }
}

async function registerPasskey() {
    try {
        // WebAuthn registration would go here
        await endpoints.security.passkeys.register({ name: 'New Passkey' })
        window.$toast?.success('Passkey registered!')
        fetchSecurityData()
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to register passkey')
    }
}

async function revokePasskey(id) {
    try {
        await endpoints.security.passkeys.delete(id)
        passkeys.value = passkeys.value.filter(p => p.id !== id)
        window.$toast?.success('Passkey revoked')
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to revoke passkey')
    }
}

async function revokeSession(id) {
    try {
        await endpoints.security.revokeSession(id)
        sessions.value = sessions.value.filter(s => s.id !== id)
        window.$toast?.success('Session revoked')
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to revoke session')
    }
}

async function createApiKey() {
    if (!newKeyName.value) return
    try {
        const res = await endpoints.apiKeys.create(newKeyName.value)
        generatedKey.value = res.key
        newKeyName.value = ''
        fetchApiKeys()
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to create API key')
    }
}

async function revokeApiKey(id) {
    try {
        await endpoints.apiKeys.revoke(id)
        apiKeys.value = apiKeys.value.filter(k => k.id !== id)
        window.$toast?.success('API key revoked')
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to revoke API key')
    }
}

function copyApiKey(key) {
    navigator.clipboard.writeText(key)
    window.$toast?.success('API key copied to clipboard!')
}

async function saveNotifications() {
    try {
        await endpoints.notifications.updatePreferences(notifications.value)
        window.$toast?.success('Notification preferences saved!')
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to save preferences')
    }
}

async function exportData() {
    exportStatus.value = 'processing'
    try {
        await endpoints.gdpr.export()
        exportStatus.value = 'completed'
        window.$toast?.success('Data export initiated! Check your email.')
    } catch (e) {
        exportStatus.value = 'error'
        window.$toast?.error(e.message || 'Failed to export data')
    }
}

async function deleteAccount() {
    if (deleteConfirm.value !== 'DELETE') return
    try {
        await endpoints.gdpr.delete(deleteConfirm.value)
        window.$toast?.success('Account deletion initiated')
        router.post('/logout')
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to delete account')
    }
}

async function connectWallet() {
    try {
        await endpoints.wallet.connect(walletAddress.value, walletChain.value)
        walletConnected.value = true
        window.$toast?.success('Wallet connected!')
    } catch (e) {
        window.$toast?.error(e.message || 'Failed to connect wallet')
    }
}

async function fetchProfileData() {
    loading.value = true
    try {
        const [profileRes, notifRes] = await Promise.all([
            endpoints.customer.profile(),
            endpoints.notifications.preferences(),
        ])
        userForm.value = {
            name: profileRes.name || store.user?.name || '',
            email: profileRes.email || store.user?.email || '',
            bio: profileRes.bio || '',
        }
        if (notifRes) notifications.value = notifRes
    } catch (e) {
        // Use store data as fallback
        userForm.value = {
            name: store.user?.name || '',
            email: store.user?.email || '',
            bio: '',
        }
    } finally {
        loading.value = false
    }
}

async function fetchSecurityData() {
    try {
        const [passkeysRes, sessionsRes, twoFactorRes] = await Promise.all([
            endpoints.security.passkeys.list(),
            endpoints.security.sessions(),
            api.get('/api/v1/account/2fa/status'),
        ])
        passkeys.value = passkeysRes || []
        sessions.value = sessionsRes || []
        twoFactorEnabled.value = twoFactorRes?.enabled || false
    } catch (e) {
        console.error('Failed to load security data', e)
    }
}

async function fetchApiKeys() {
    try {
        const res = await endpoints.apiKeys.list()
        apiKeys.value = res || []
    } catch (e) {
        console.error('Failed to load API keys', e)
    }
}

async function fetchWalletStatus() {
    try {
        const res = await endpoints.wallet.status()
        walletConnected.value = res.connected || false
        walletAddress.value = res.address || ''
        walletChain.value = res.chain || 'ETH'
    } catch (e) {
        console.error('Failed to load wallet status', e)
    }
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    })
}

// ─── Lifecycle ────────────────────────────────────────────
onMounted(() => {
    fetchProfileData()
    fetchSecurityData()
    fetchApiKeys()
    fetchWalletStatus()
})
</script>

<template>
    <Head title="Profile | BLBGenSix AI" />

    <div class="min-h-screen bg-[#0a0a12] text-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-white">Profile Settings</h1>
                    <p class="text-gray-400 text-sm mt-1">Manage your account, security, and preferences</p>
                </div>
                <Link href="/customer/dashboard" class="text-sm text-gray-400 hover:text-white transition-colors">
                    ← Back to Dashboard
                </Link>
            </div>

            <!-- Tab Navigation -->
            <div class="flex items-center gap-1 p-1 bg-[#111118] rounded-xl border border-[#1a1a24] w-fit mb-8 overflow-x-auto">
                <button
                    v-for="tab in ['info', 'security', 'api-keys', 'notifications', 'gdpr', 'wallet']"
                    :key="tab"
                    @click="activeTab = tab"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 whitespace-nowrap"
                    :class="activeTab === tab ? 'bg-[#1a1a24] text-white' : 'text-gray-500 hover:text-gray-300'"
                >
                    {{ tab.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                </button>
            </div>

            <!-- Tab: User Info -->
            <div v-if="activeTab === 'info'" class="space-y-6">
                <div class="card bg-[#111118] border-[#1a1a24] p-6">
                    <h3 class="text-lg font-bold text-white mb-6">User Information</h3>

                    <!-- Avatar -->
                    <div class="flex items-center gap-6 mb-6">
                        <div class="relative">
                            <img
                                :src="avatarUrl"
                                alt="Avatar"
                                class="w-24 h-24 rounded-full object-cover border-2 border-[#1a1a24]"
                            />
                            <label class="absolute bottom-0 right-0 w-8 h-8 bg-violet-600 hover:bg-violet-700 rounded-full flex items-center justify-center cursor-pointer transition-colors">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <input type="file" accept="image/*" class="hidden" @change="handleAvatarUpload" />
                            </label>
                        </div>
                        <div>
                            <p class="font-bold text-white">{{ userForm.name || 'Your Name' }}</p>
                            <p class="text-sm text-gray-400">{{ userForm.email || 'email@example.com' }}</p>
                            <p class="text-xs text-gray-500 mt-1">JPG, GIF or PNG. Max size 2MB.</p>
                        </div>
                    </div>

                    <!-- Form -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Display Name</label>
                            <input
                                v-model="userForm.name"
                                type="text"
                                class="input-dark bg-[#0a0a12] border-[#1a1a24] w-full"
                                placeholder="Your name"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Email Address</label>
                            <input
                                v-model="userForm.email"
                                type="email"
                                class="input-dark bg-[#0a0a12] border-[#1a1a24] w-full"
                                placeholder="email@example.com"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Bio</label>
                            <textarea
                                v-model="userForm.bio"
                                rows="3"
                                class="input-dark bg-[#0a0a12] border-[#1a1a24] w-full resize-none"
                                placeholder="Tell us about yourself..."
                            />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            @click="saveProfile"
                            :disabled="loading"
                            class="btn-primary text-sm"
                        >
                            {{ loading ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab: Security -->
            <div v-if="activeTab === 'security'" class="space-y-6">
                <!-- Two-Factor Authentication -->
                <div class="card bg-[#111118] border-[#1a1a24] p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-white">Two-Factor Authentication</h3>
                            <p class="text-sm text-gray-400 mt-1">Add an extra layer of security to your account</p>
                        </div>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="twoFactorEnabled ? 'bg-green-600/20 text-green-400' : 'bg-gray-700/50 text-gray-500'"
                        >
                            {{ twoFactorEnabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    <div v-if="!twoFactorEnabled" class="space-y-4">
                        <div v-if="!twoFactorSecret" class="text-center py-4">
                            <button @click="enable2FA" class="btn-primary text-sm">
                                Setup Two-Factor Auth
                            </button>
                        </div>
                        <div v-else class="space-y-4">
                            <div class="p-4 bg-[#0a0a12] rounded-xl border border-[#1a1a24] text-center">
                                <p class="text-xs text-gray-500 mb-2">Scan this QR code with your authenticator app</p>
                                <div class="w-32 h-32 bg-white rounded-lg mx-auto mb-3 flex items-center justify-center">
                                    <span class="text-gray-400 text-xs">QR Code</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-1">Or enter this code manually:</p>
                                <code class="text-sm text-violet-400 font-mono">{{ twoFactorSecret }}</code>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Verification Code</label>
                                <input
                                    v-model="twoFactorCode"
                                    type="text"
                                    class="input-dark bg-[#0a0a12] border-[#1a1a24] w-full"
                                    placeholder="000000"
                                    maxlength="6"
                                />
                            </div>
                            <button @click="confirm2FA" class="btn-primary text-sm">Verify & Enable</button>
                        </div>
                    </div>
                    <div v-else class="space-y-4">
                        <p class="text-sm text-gray-400">Two-factor authentication is enabled. Use your authenticator app to generate codes.</p>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Disable 2FA (requires code)</label>
                            <div class="flex gap-2">
                                <input
                                    v-model="twoFactorCode"
                                    type="text"
                                    class="input-dark bg-[#0a0a12] border-[#1a1a24] flex-1"
                                    placeholder="000000"
                                    maxlength="6"
                                />
                                <button @click="disable2FA" class="px-4 py-2 bg-red-500/10 text-red-400 border border-red-500/30 rounded-lg text-sm hover:bg-red-500/20 transition-colors">
                                    Disable
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Passkeys -->
                <div class="card bg-[#111118] border-[#1a1a24] p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-white">Passkeys & Biometrics</h3>
                            <p class="text-sm text-gray-400 mt-1">Use fingerprint, Face ID, or hardware keys to sign in</p>
                        </div>
                        <button @click="registerPasskey" class="btn-primary text-sm">
                            Add Passkey
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="passkey in passkeys"
                            :key="passkey.id"
                            class="flex items-center justify-between p-3 bg-[#0a0a12] rounded-xl border border-[#1a1a24]"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-violet-500/10 flex items-center justify-center">🔐</div>
                                <div>
                                    <p class="text-sm text-white">{{ passkey.name }}</p>
                                    <p class="text-xs text-gray-500">Added {{ formatDate(passkey.created_at) }}</p>
                                </div>
                            </div>
                            <button
                                @click="revokePasskey(passkey.id)"
                                class="text-xs text-red-400 hover:text-red-300 transition-colors"
                            >
                                Remove
                            </button>
                        </div>
                        <div v-if="!passkeys.length" class="text-center py-4 text-gray-500 text-sm">
                            No passkeys registered yet
                        </div>
                    </div>
                </div>

                <!-- Active Sessions -->
                <div class="card bg-[#111118] border-[#1a1a24] p-6">
                    <h3 class="font-bold text-white mb-4">Active Sessions</h3>
                    <p class="text-sm text-gray-400 mb-4">Manage devices that are currently logged in to your account</p>
                    <div class="space-y-2">
                        <div
                            v-for="session in sessions"
                            :key="session.id"
                            class="flex items-center justify-between p-3 bg-[#0a0a12] rounded-xl border border-[#1a1a24]"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center text-lg">
                                    {{ session.device?.includes('Mobile') ? '📱' : '💻' }}
                                </div>
                                <div>
                                    <p class="text-sm text-white">{{ session.device || 'Unknown Device' }}</p>
                                    <p class="text-xs text-gray-500">{{ session.ip }} · {{ formatDate(session.last_active) }}</p>
                                </div>
                            </div>
                            <button
                                @click="revokeSession(session.id)"
                                class="text-xs text-red-400 hover:text-red-300 transition-colors"
                            >
                                Revoke
                            </button>
                        </div>
                        <div v-if="!sessions.length" class="text-center py-4 text-gray-500 text-sm">
                            No active sessions
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: API Keys -->
            <div v-if="activeTab === 'api-keys'" class="space-y-6">
                <div class="card bg-[#111118] border-[#1a1a24] p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="font-bold text-white">API Keys</h3>
                            <p class="text-sm text-gray-400 mt-1">Create keys to access the BLBGenSix API programmatically</p>
                        </div>
                    </div>

                    <!-- New Key Form -->
                    <div class="flex gap-2 mb-6">
                        <input
                            v-model="newKeyName"
                            type="text"
                            class="input-dark bg-[#0a0a12] border-[#1a1a24] flex-1"
                            placeholder="Key name (e.g., 'Production', 'Development')"
                        />
                        <button @click="createApiKey" class="btn-primary text-sm whitespace-nowrap">
                            Create Key
                        </button>
                    </div>

                    <!-- Generated Key Display -->
                    <div v-if="generatedKey" class="mb-6 p-4 bg-yellow-900/10 border border-yellow-600/30 rounded-xl">
                        <p class="text-xs text-yellow-400 mb-2">⚠️ Copy this key now. You won't be able to see it again!</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 text-sm text-yellow-200 font-mono break-all">{{ generatedKey }}</code>
                            <button
                                @click="copyApiKey(generatedKey)"
                                class="px-3 py-1.5 text-xs bg-yellow-600/20 text-yellow-400 border border-yellow-500/30 rounded-lg hover:bg-yellow-600/30 transition-colors"
                            >
                                Copy
                            </button>
                        </div>
                    </div>

                    <!-- API Keys List -->
                    <div class="space-y-2">
                        <div
                            v-for="key in apiKeys"
                            :key="key.id"
                            class="flex items-center justify-between p-3 bg-[#0a0a12] rounded-xl border border-[#1a1a24]"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center text-lg">⚡</div>
                                <div>
                                    <p class="text-sm text-white">{{ key.name }}</p>
                                    <p class="text-xs text-gray-500 font-mono">{{ key.prefix }}················</p>
                                    <p class="text-xs text-gray-600">Created {{ formatDate(key.created_at) }}</p>
                                </div>
                            </div>
                            <button
                                @click="revokeApiKey(key.id)"
                                class="text-xs text-red-400 hover:text-red-300 transition-colors"
                            >
                                Revoke
                            </button>
                        </div>
                        <div v-if="!apiKeys.length" class="text-center py-8 text-gray-500 text-sm">
                            No API keys yet. Create one to get started!
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Notifications -->
            <div v-if="activeTab === 'notifications'" class="space-y-6">
                <div class="card bg-[#111118] border-[#1a1a24] p-6">
                    <h3 class="font-bold text-white mb-6">Notification Preferences</h3>

                    <div class="space-y-6">
                        <!-- Email Notifications -->
                        <div>
                            <h4 class="text-sm font-bold text-gray-300 mb-3 flex items-center gap-2">
                                <span>📧</span> Email Notifications
                            </h4>
                            <div class="space-y-2">
                                <label v-for="(value, key) in notifications.email" :key="'email-' + key" class="flex items-center justify-between p-3 bg-[#0a0a12] rounded-xl border border-[#1a1a24] cursor-pointer hover:border-violet-500/30 transition-colors">
                                    <span class="text-sm text-gray-300 capitalize">{{ key }}</span>
                                    <button
                                        @click="notifications.email[key] = !notifications.email[key]"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200"
                                        :class="value ? 'bg-violet-600' : 'bg-[#1a1a24]'"
                                    >
                                        <span
                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform duration-200"
                                            :class="value ? 'translate-x-4 ml-0.5' : 'translate-x-0.5'"
                                        />
                                    </button>
                                </label>
                            </div>
                        </div>

                        <!-- Push Notifications -->
                        <div>
                            <h4 class="text-sm font-bold text-gray-300 mb-3 flex items-center gap-2">
                                <span>🔔</span> Push Notifications
                            </h4>
                            <div class="space-y-2">
                                <label v-for="(value, key) in notifications.push" :key="'push-' + key" class="flex items-center justify-between p-3 bg-[#0a0a12] rounded-xl border border-[#1a1a24] cursor-pointer hover:border-pink-500/30 transition-colors">
                                    <span class="text-sm text-gray-300 capitalize">{{ key }}</span>
                                    <button
                                        @click="notifications.push[key] = !notifications.push[key]"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200"
                                        :class="value ? 'bg-pink-600' : 'bg-[#1a1a24]'"
                                    >
                                        <span
                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform duration-200"
                                            :class="value ? 'translate-x-4 ml-0.5' : 'translate-x-0.5'"
                                        />
                                    </button>
                                </label>
                            </div>
                        </div>

                        <!-- SMS Notifications -->
                        <div>
                            <h4 class="text-sm font-bold text-gray-300 mb-3 flex items-center gap-2">
                                <span>📱</span> SMS Notifications
                            </h4>
                            <div class="space-y-2">
                                <label v-for="(value, key) in notifications.sms" :key="'sms-' + key" class="flex items-center justify-between p-3 bg-[#0a0a12] rounded-xl border border-[#1a1a24] cursor-pointer hover:border-green-500/30 transition-colors">
                                    <span class="text-sm text-gray-300 capitalize">{{ key }}</span>
                                    <button
                                        @click="notifications.sms[key] = !notifications.sms[key]"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200"
                                        :class="value ? 'bg-green-600' : 'bg-[#1a1a24]'"
                                    >
                                        <span
                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform duration-200"
                                            :class="value ? 'translate-x-4 ml-0.5' : 'translate-x-0.5'"
                                        />
                                    </button>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button @click="saveNotifications" class="btn-primary text-sm">
                            Save Preferences
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab: GDPR -->
            <div v-if="activeTab === 'gdpr'" class="space-y-6">
                <!-- Data Export -->
                <div class="card bg-[#111118] border-[#1a1a24] p-6">
                    <h3 class="font-bold text-white mb-2">Export Your Data</h3>
                    <p class="text-sm text-gray-400 mb-4">
                        Download a copy of all your personal data, generations, and account information. This complies with GDPR and CCPA requirements.
                    </p>
                    <div v-if="exportStatus === 'completed'" class="p-3 bg-green-900/10 border border-green-600/30 rounded-xl mb-4">
                        <p class="text-sm text-green-400">Export initiated! Check your email for the download link.</p>
                    </div>
                    <button
                        @click="exportData"
                        :disabled="exportStatus === 'processing'"
                        class="btn-primary text-sm"
                    >
                        {{ exportStatus === 'processing' ? 'Processing...' : 'Request Data Export' }}
                    </button>
                </div>

                <!-- Delete Account -->
                <div class="card bg-[#1a0a0a] border-red-900/30 p-6">
                    <h3 class="font-bold text-red-400 mb-2">Delete Account</h3>
                    <p class="text-sm text-gray-400 mb-4">
                        Permanently delete your account and all associated data. This action cannot be undone.
                    </p>

                    <div v-if="deleteStep === 1" class="space-y-4">
                        <div class="p-3 bg-red-900/10 border border-red-600/30 rounded-xl">
                            <p class="text-xs text-red-400">⚠️ This will permanently delete:</p>
                            <ul class="text-xs text-gray-400 mt-2 space-y-1 ml-4 list-disc">
                                <li>All generated images, videos, and text</li>
                                <li>Account settings and profile data</li>
                                <li>API keys and access tokens</li>
                                <li>Billing history and invoices</li>
                            </ul>
                        </div>
                        <button
                            @click="deleteStep = 2"
                            class="px-4 py-2 bg-red-600/20 text-red-400 border border-red-500/30 rounded-lg text-sm hover:bg-red-600/30 transition-colors"
                        >
                            I understand, proceed
                        </button>
                    </div>

                    <div v-if="deleteStep === 2" class="space-y-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Type "DELETE" to confirm</label>
                            <input
                                v-model="deleteConfirm"
                                type="text"
                                class="input-dark bg-[#0a0a12] border-red-900/50 w-full"
                                placeholder="DELETE"
                            />
                        </div>
                        <div class="flex gap-3">
                            <button
                                @click="deleteStep = 1"
                                class="px-4 py-2 bg-[#1a1a24] text-gray-300 rounded-lg text-sm hover:bg-[#242430] transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                @click="deleteAccount"
                                :disabled="deleteConfirm !== 'DELETE'"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Permanently Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Wallet -->
            <div v-if="activeTab === 'wallet'" class="space-y-6">
                <div class="card bg-[#111118] border-[#1a1a24] p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="font-bold text-white">Cold Wallet Connection</h3>
                            <p class="text-sm text-gray-400 mt-1">Connect your crypto wallet for payments and withdrawals</p>
                        </div>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="walletConnected ? 'bg-green-600/20 text-green-400' : 'bg-gray-700/50 text-gray-500'"
                        >
                            {{ walletConnected ? 'Connected' : 'Not Connected' }}
                        </span>
                    </div>

                    <div v-if="walletConnected" class="space-y-4">
                        <div class="p-4 bg-[#0a0a12] rounded-xl border border-[#1a1a24]">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center text-lg">🔗</div>
                                <div>
                                    <p class="text-sm text-white">Connected Wallet</p>
                                    <p class="text-xs text-gray-500">{{ walletChain }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 text-xs text-gray-400 font-mono break-all">{{ walletAddress }}</code>
                                <button
                                    @click="navigator.clipboard.writeText(walletAddress); window.$toast?.success('Address copied!')"
                                    class="px-3 py-1.5 text-xs bg-violet-600/20 text-violet-400 border border-violet-500/30 rounded-lg hover:bg-violet-600/30 transition-colors"
                                >
                                    Copy
                                </button>
                            </div>
                        </div>
                        <button
                            @click="endpoints.wallet.disconnect(); walletConnected = false"
                            class="px-4 py-2 bg-red-500/10 text-red-400 border border-red-500/30 rounded-lg text-sm hover:bg-red-500/20 transition-colors"
                        >
                            Disconnect Wallet
                        </button>
                    </div>

                    <div v-else class="space-y-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Wallet Address</label>
                            <input
                                v-model="walletAddress"
                                type="text"
                                class="input-dark bg-[#0a0a12] border-[#1a1a24] w-full font-mono"
                                placeholder="0x... or your wallet address"
                            />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Chain</label>
                            <select v-model="walletChain" class="input-dark bg-[#0a0a12] border-[#1a1a24] w-full">
                                <option value="ETH">Ethereum (ETH)</option>
                                <option value="BTC">Bitcoin (BTC)</option>
                                <option value="SOL">Solana (SOL)</option>
                                <option value="ADA">Cardano (ADA)</option>
                                <option value="MATIC">Polygon (MATIC)</option>
                                <option value="AVAX">Avalanche (AVAX)</option>
                            </select>
                        </div>
                        <button @click="connectWallet" class="btn-primary text-sm">
                            Connect Wallet
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.btn-primary {
    @apply px-4 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl transition-all duration-200;
}

.input-dark {
    @apply rounded-xl px-4 py-2.5 text-sm text-gray-100 border focus:outline-none focus:ring-2 focus:ring-violet-500/50 transition-all;
}

.card {
    @apply rounded-2xl border;
}
</style>
