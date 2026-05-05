/**
 * Vue 3 Application Entry Point for BLBGenSix AI Customer Portal.
 * Sets up: Pinia, Router, API client, error boundaries, global components.
 */

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import { api, endpoints } from './lib/api'

// ─── App Component ──────────────────────────────────────────
import App from './App.vue'

// ─── Global Components ──────────────────────────────────────
import FeatureCard from './components/FeatureCard.vue'
import GenerateButton from './components/GenerateButton.vue'

// ─── Error Boundary Component ───────────────────────────────
const ErrorBoundary = {
    name: 'ErrorBoundary',
    data() {
        return {
            hasError: false,
            error: null,
            errorInfo: null,
        }
    },
    errorCaptured(err, instance, info) {
        this.hasError = true
        this.error = err
        this.errorInfo = info

        // Log to error tracking service
        console.error('[ErrorBoundary]', err, info)

        // Send to API if available
        try {
            fetch('/api/v1/errors', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: err.message,
                    stack: err.stack,
                    info,
                    url: window.location.href,
                    userAgent: navigator.userAgent,
                }),
            }).catch(() => {})
        } catch (e) {
            // Silently fail
        }

        return false
    },
    render() {
        if (this.hasError) {
            return (
                <div class="min-h-screen bg-[#0a0a12] flex items-center justify-center p-4">
                    <div class="max-w-md w-full card bg-[#111118] border-[#1a1a24] p-8 text-center">
                        <div class="text-6xl mb-4">⚠️</div>
                        <h2 class="text-xl font-bold text-white mb-2">Something went wrong</h2>
                        <p class="text-sm text-gray-400 mb-6">
                            {this.error?.message || 'An unexpected error occurred'}
                        </p>
                        <div class="flex gap-3 justify-center">
                            <button
                                onClick={() => {
                                    this.hasError = false
                                    this.error = null
                                    window.location.reload()
                                }}
                                class="btn-primary text-sm"
                            >
                                Reload Page
                            </button>
                            <button
                                onClick={() => window.location.href = '/customer/dashboard'}
                                class="px-4 py-2 text-sm bg-[#1a1a24] hover:bg-[#242430] text-gray-300 rounded-lg transition-colors"
                            >
                                Go to Dashboard
                            </button>
                        </div>
                        {this.errorInfo && (
                            <details class="mt-6 text-left">
                                <summary class="text-xs text-gray-600 cursor-pointer">Technical Details</summary>
                                <pre class="mt-2 p-3 bg-[#0a0a12] rounded-lg text-xs text-gray-500 overflow-auto">
                                    {this.error?.stack || 'No stack trace available'}
                                </pre>
                            </details>
                        )}
                    </div>
                </div>
            )
        }
        return this.$slots.default?.()
    },
}

// ─── Create Vue App ─────────────────────────────────────────
const app = createApp(App)

// ─── Pinia (State Management) ──────────────────────────────
const pinia = createPinia()

// Pinia plugin: persist store to localStorage
pinia.use(({ store }) => {
    const stored = localStorage.getItem(`pinia_${store.$id}`)
    if (stored) {
        try {
            store.$patch(JSON.parse(stored))
        } catch (e) {
            console.warn(`Failed to restore store ${store.$id}:`, e)
        }
    }

    store.$subscribe((mutation, state) => {
        try {
            localStorage.setItem(`pinia_${store.$id}`, JSON.stringify(state))
        } catch (e) {
            // Storage might be full
        }
    })
})

app.use(pinia)

// ─── Router ─────────────────────────────────────────────────
app.use(router)

// ─── Provide API client globally ───────────────────────────
app.provide('api', api)
app.provide('endpoints', endpoints)

// ─── Global Properties ──────────────────────────────────────
app.config.globalProperties.$api = api
app.config.globalProperties.$endpoints = endpoints

// ─── Global Error Handler ───────────────────────────────────
app.config.errorHandler = (err, instance, info) => {
    console.error('[Global Error Handler]', err, info)

    // Show user-friendly toast notification
    const event = new CustomEvent('app-error', {
        detail: {
            message: err.message || 'An unexpected error occurred',
            info,
        },
    })
    window.dispatchEvent(event)
}

// ─── Register Global Components ─────────────────────────────
app.component('FeatureCard', FeatureCard)
app.component('GenerateButton', GenerateButton)
app.component('ErrorBoundary', ErrorBoundary)

// ─── Global Toast Notification System ──────────────────────
const toastState = {
    toasts: [],
    nextId: 1,
}

app.component('ToastContainer', {
    name: 'ToastContainer',
    setup() {
        const toasts = Vue.ref(toastsState?.toasts || [])

        const removeToast = (id) => {
            const index = toasts.value.findIndex(t => t.id === id)
            if (index !== -1) {
                toasts.value.splice(index, 1)
            }
        }

        const getToastClass = (type) => {
            const classes = {
                success: 'bg-green-900/80 border-green-600/50 text-green-100',
                error: 'bg-red-900/80 border-red-600/50 text-red-100',
                warning: 'bg-yellow-900/80 border-yellow-600/50 text-yellow-100',
                info: 'bg-violet-900/80 border-violet-600/50 text-violet-100',
            }
            return classes[type] || classes.info
        }

        const getIcon = (type) => {
            const icons = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ',
            }
            return icons[type] || icons.info
        }

        return { toasts, removeToast, getToastClass, getIcon }
    },
    template: `
        <Teleport to="body">
            <div class="fixed top-6 right-6 z-[100] flex flex-col gap-3 w-full max-w-sm">
                <transition-group
                    enter-active-class="transition-all duration-300"
                    enter-from-class="opacity-0 translate-x-12"
                    enter-to-class="opacity-100 translate-x-0"
                    leave-active-class="transition-all duration-200"
                    leave-from-class="opacity-100 translate-x-0"
                    leave-to-class="opacity-0 translate-x-12"
                >
                    <div
                        v-for="toast in toasts"
                        :key="toast.id"
                        class="px-4 py-3 rounded-xl shadow-2xl text-sm font-medium backdrop-blur-xl border flex items-center gap-3"
                        :class="getToastClass(toast.type)"
                    >
                        <span class="shrink-0 text-base">{{ getIcon(toast.type) }}</span>
                        <span class="flex-1">{{ toast.message }}</span>
                        <button
                            @click="removeToast(toast.id)"
                            class="shrink-0 opacity-60 hover:opacity-100 transition-opacity"
                        >
                            ✕
                        </button>
                    </div>
                </transition-group>
            </div>
        </Teleport>
    `,
})

// ─── Global Toast Function ─────────────────────────────────
window.$toast = {
    success(message, duration = 4000) {
        showToast(message, 'success', duration)
    },
    error(message, duration = 6000) {
        showToast(message, 'error', duration)
    },
    warning(message, duration = 5000) {
        showToast(message, 'warning', duration)
    },
    info(message, duration = 4000) {
        showToast(message, 'info', duration)
    },
}

function showToast(message, type = 'info', duration = 4000) {
    if (!window.__toasts) window.__toasts = []
    const id = Date.now() + Math.random()
    window.__toasts.push({ id, message, type })

    // Dispatch event for reactive update
    window.dispatchEvent(new CustomEvent('toast-add', {
        detail: { id, message, type }
    }))

    if (duration > 0) {
        setTimeout(() => {
            window.dispatchEvent(new CustomEvent('toast-remove', { detail: id }))
        }, duration)
    }
}

// ─── Listen for Toast Events ───────────────────────────────
if (typeof window !== 'undefined') {
    window.addEventListener('toast-add', (e) => {
        // Toast state is managed by the ToastContainer component
    })
    window.addEventListener('toast-remove', (e) => {
        // Toast removal is handled by the ToastContainer component
    })
    window.addEventListener('app-error', (e) => {
        window.$toast?.error(e.detail.message)
    })
}

// ─── Mount App ──────────────────────────────────────────────
const mountPoint = document.getElementById('app')

if (mountPoint) {
    app.mount('#app')
    console.log('[BLBGenSix AI] Customer Portal mounted successfully')
} else {
    console.error('[BLBGenSix AI] Mount point #app not found')
}

export default app
export { ErrorBoundary }
