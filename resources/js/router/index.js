/**
 * Vue Router configuration for BLBGenSix AI Customer Portal.
 * Features: lazy loading, auth guards, role-based access, transition animations.
 */

import { createRouter, createWebHistory } from 'vue-router'
import { useCustomerStore } from '@/stores/customerStore'
import { useFeatures } from '@/composables/useFeatures'

/**
 * Route meta configuration:
 * - requiresAuth: boolean (default: true for customer routes)
 * - requiresPlan: number (minimum plan level required)
 * - requiresFeature: string | string[] (feature flag(s) required)
 * - guest: boolean (only accessible when NOT authenticated)
 * - title: string (page title)
 */

const routes = [
    // ─── Public Routes ────────────────────────────────────────
    {
        path: '/',
        name: 'home',
        component: () => import('@/Pages/Welcome.vue'),
        meta: { requiresAuth: false, title: 'Welcome | BLBGenSix AI' },
    },
    {
        path: '/pricing',
        name: 'pricing',
        component: () => import('@/Pages/Pricing.vue'),
        meta: { requiresAuth: false, title: 'Pricing | BLBGenSix AI' },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/Pages/Auth/Login.vue'),
        meta: { requiresAuth: false, guest: true, title: 'Login | BLBGenSix AI' },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/Pages/Auth/Register.vue'),
        meta: { requiresAuth: false, guest: true, title: 'Register | BLBGenSix AI' },
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: () => import('@/Pages/Auth/ForgotPassword.vue'),
        meta: { requiresAuth: false, guest: true, title: 'Forgot Password | BLBGenSix AI' },
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: () => import('@/Pages/Auth/ResetPassword.vue'),
        meta: { requiresAuth: false, guest: true, title: 'Reset Password | BLBGenSix AI' },
    },

    // ─── Customer Routes (Nested under CustomerLayout) ────────
    {
        path: '/customer',
        component: () => import('@/layouts/CustomerLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            // Dashboard
            {
                path: 'dashboard',
                name: 'customer.dashboard',
                component: () => import('@/Pages/Customer/Dashboard.vue'),
                meta: { title: 'Dashboard | BLBGenSix AI' },
            },

            // Music
            {
                path: 'music',
                name: 'customer.music',
                component: () => import('@/Pages/Customer/MusicDashboard.vue'),
                meta: { requiresFeature: 'music:dashboard', title: 'Music Dashboard | BLBGenSix AI' },
            },
            {
                path: 'music/connect',
                name: 'customer.music.connect',
                component: () => import('@/Pages/Customer/MusicConnect.vue'),
                meta: { requiresFeature: 'music:connect', title: 'Connect Music | BLBGenSix AI' },
            },

            // Billing
            {
                path: 'billing',
                name: 'customer.billing',
                component: () => import('@/Pages/Customer/Billing.vue'),
                meta: { title: 'Billing | BLBGenSix AI' },
            },

            // Profile
            {
                path: 'profile',
                name: 'customer.profile',
                component: () => import('@/Pages/Customer/Profile.vue'),
                meta: { title: 'Profile | BLBGenSix AI' },
            },

            // Search
            {
                path: 'search',
                name: 'customer.search',
                component: () => import('@/Pages/Customer/Search.vue'),
                meta: { title: 'Search | BLBGenSix AI' },
            },

            // Generate
            {
                path: 'generate',
                name: 'customer.generate',
                component: () => import('@/Pages/Customer/Generate.vue'),
                meta: { title: 'AI Generation | BLBGenSix AI' },
            },
            {
                path: 'generate/image',
                name: 'customer.generate.image',
                component: () => import('@/Pages/Customer/Generate.vue'),
                meta: { requiresFeature: 'generate:image', title: 'Image Generation | BLBGenSix AI' },
            },
            {
                path: 'generate/video',
                name: 'customer.generate.video',
                component: () => import('@/Pages/Customer/Generate.vue'),
                meta: { requiresFeature: 'generate:video', title: 'Video Generation | BLBGenSix AI' },
            },
            {
                path: 'generate/text',
                name: 'customer.generate.text',
                component: () => import('@/Pages/Customer/Generate.vue'),
                meta: { requiresFeature: 'generate:text', title: 'Text Generation | BLBGenSix AI' },
            },
            {
                path: 'generate/body',
                name: 'customer.generate.body',
                component: () => import('@/Pages/Customer/Generate.vue'),
                meta: { requiresFeature: 'generate:body', title: 'Body Mapping | BLBGenSix AI' },
            },

            // Gallery
            {
                path: 'gallery',
                name: 'customer.gallery',
                component: () => import('@/Pages/Customer/Gallery.vue'),
                meta: { title: 'Gallery | BLBGenSix AI' },
            },

            // Activity
            {
                path: 'activity',
                name: 'customer.activity',
                component: () => import('@/Pages/Customer/Activity.vue'),
                meta: { requiresFeature: 'dashboard:activity', title: 'Activity | BLBGenSix AI' },
            },

            // Revenue
            {
                path: 'revenue',
                name: 'customer.revenue',
                component: () => import('@/Pages/Customer/Revenue.vue'),
                meta: { requiresFeature: 'dashboard:revenue', title: 'Revenue | BLBGenSix AI' },
            },

            // Support
            {
                path: 'support',
                name: 'customer.support',
                component: () => import('@/Pages/Customer/Support.vue'),
                meta: { title: 'Support | BLBGenSix AI' },
            },

            // Catch-all for customer routes
            {
                path: ':pathMatch(.*)*',
                redirect: '/customer/dashboard',
            },
        ],
    },

    // ─── Admin Routes (Placeholder) ──────────────────────────
    {
        path: '/admin',
        component: () => import('@/layouts/AdminLayout.vue'),
        meta: { requiresAuth: true, requiresPlan: 3 },
        children: [
            {
                path: 'dashboard',
                name: 'admin.dashboard',
                component: () => import('@/Pages/Admin/Dashboard.vue'),
                meta: { title: 'Admin Dashboard | BLBGenSix AI' },
            },
        ],
    },

    // ─── 404 ────────────────────────────────────────────────
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/Pages/Errors/404.vue'),
        meta: { title: '404 Not Found | BLBGenSix AI' },
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition
        if (to.hash) return { el: to.hash, behavior: 'smooth' }
        return { top: 0, behavior: 'smooth' }
    },
})

/**
 * Navigation guard: authentication, authorization, feature flags.
 */
router.beforeEach(async (to, from, next) => {
    const store = useCustomerStore()
    const { hasFeature, plan } = useFeatures()

    // Set page title
    if (to.meta?.title) {
        document.title = to.meta.title
    }

    // Check if route requires authentication
    const requiresAuth = to.meta?.requiresAuth !== false
    const isGuestRoute = to.meta?.guest === true
    const isAuthenticated = !!store.user?.id || localStorage.getItem('auth_token')

    // Guest-only routes (login, register, etc.)
    if (isGuestRoute && isAuthenticated) {
        return next({ name: 'customer.dashboard' })
    }

    // Auth-required routes
    if (requiresAuth && !isAuthenticated) {
        return next({
            name: 'login',
            query: { redirect: to.fullPath },
        })
    }

    // Check plan requirement
    if (to.meta?.requiresPlan !== undefined) {
        const minPlan = to.meta.requiresPlan
        if (plan.value < minPlan) {
            return next({
                name: 'pricing',
                query: { reason: 'plan_required', minPlan },
            })
        }
    }

    // Check feature flag requirement
    if (to.meta?.requiresFeature) {
        const features = Array.isArray(to.meta.requiresFeature)
            ? to.meta.requiresFeature
            : [to.meta.requiresFeature]

        const hasAccess = features.every(f => hasFeature(f))
        if (!hasAccess) {
            return next({
                name: 'customer.dashboard',
                query: { error: 'feature_required' },
            })
        }
    }

    next()
})

/**
 * After navigation: track page views (optional analytics).
 */
router.afterEach((to) => {
    // Analytics or logging could go here
    if (typeof window !== 'undefined' && window.gtag) {
        window.gtag('config', window.GA_MEASUREMENT_ID, {
            page_path: to.fullPath,
        })
    }
})

export default router
export { routes }
