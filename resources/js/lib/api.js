/**
 * Typed API client wrapper for BLBGenSix AI Customer Portal.
 * Features: auth token attachment, retries, rate limit detection, error handling.
 */

const BASE_URL = window.location.origin
const MAX_RETRIES = 3
const RETRY_DELAY = 1000
const RATE_LIMIT_STATUS = 429

class ApiError extends Error {
    constructor(message, status, data) {
        super(message)
        this.name = 'ApiError'
        this.status = status
        this.data = data
    }
}

class RateLimitError extends ApiError {
    constructor(message, retryAfter) {
        super(message, RATE_LIMIT_STATUS, { retryAfter })
        this.name = 'RateLimitError'
        this.retryAfter = retryAfter
    }
}

/**
 * Get the current auth token from storage or meta tag.
 */
function getAuthToken() {
    return localStorage.getItem('auth_token')
        || document.querySelector('meta[name="csrf-token"]')?.content
        || ''
}

/**
 * Sleep utility for retry delays.
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms))
}

/**
 * Parse rate limit headers from response.
 */
function parseRateLimitHeaders(response) {
    return {
        limit: parseInt(response.headers.get('X-RateLimit-Limit') || '0',
        remaining: parseInt(response.headers.get('X-RateLimit-Remaining') || '0'),
        reset: parseInt(response.headers.get('X-RateLimit-Reset') || '0'),
        retryAfter: parseInt(response.headers.get('Retry-After') || '60'),
    }
}

/**
 * Core fetch wrapper with auth, retries, and error handling.
 */
async function fetchApi(endpoint, options = {}, retryCount = 0) {
    const url = endpoint.startsWith('http') ? endpoint : `${BASE_URL}${endpoint}`

    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...options.headers,
    }

    const token = getAuthToken()
    if (token) {
        headers['Authorization'] = `Bearer ${token}`
    }

    const config = {
        ...options,
        headers,
        credentials: 'include',
    }

    try {
        const response = await fetch(url, config)
        const rateLimit = parseRateLimitHeaders(response)

        if (response.status === RATE_LIMIT_STATUS) {
            if (retryCount < MAX_RETRIES) {
                const delay = rateLimit.retryAfter * 1000 || RETRY_DELAY * (retryCount + 1)
                await sleep(delay)
                return fetchApi(endpoint, options, retryCount + 1)
            }
            throw new RateLimitError('Rate limit exceeded. Please try again later.', rateLimit.retryAfter)
        }

        if (!response.ok) {
            let data = {}
            try {
                data = await response.json()
            } catch {
                // Response might not be JSON
            }

            // Retry on server errors (5xx)
            if (response.status >= 500 && retryCount < MAX_RETRIES) {
                await sleep(RETRY_DELAY * (retryCount + 1))
                return fetchApi(endpoint, options, retryCount + 1)
            }

            throw new ApiError(
                data.message || `Request failed with status ${response.status}`,
                response.status,
                data
            )
        }

        // Handle 204 No Content
        if (response.status === 204) {
            return { success: true }
        }

        return await response.json()
    } catch (error) {
        if (error instanceof ApiError) {
            throw error
        }

        // Network errors, retry
        if (retryCount < MAX_RETRIES) {
            await sleep(RETRY_DELAY * (retryCount + 1))
            return fetchApi(endpoint, options, retryCount + 1)
        }

        throw new ApiError(error.message || 'Network error', 0, { originalError: error })
    }
}

/**
 * Typed API client with method helpers.
 */
export const api = {
    /**
     * GET request.
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString()
        const url = queryString ? `${endpoint}?${queryString}` : endpoint
        return fetchApi(url, { method: 'GET' })
    },

    /**
     * POST request.
     */
    async post(endpoint, data = {}) {
        return fetchApi(endpoint, {
            method: 'POST',
            body: JSON.stringify(data),
        })
    },

    /**
     * PUT request.
     */
    async put(endpoint, data = {}) {
        return fetchApi(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
        })
    },

    /**
     * PATCH request.
     */
    async patch(endpoint, data = {}) {
        return fetchApi(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data),
        })
    },

    /**
     * DELETE request.
     */
    async delete(endpoint, data = {}) {
        return fetchApi(endpoint, {
            method: 'DELETE',
            body: data ? JSON.stringify(data) : undefined,
        })
    },

    /**
     * Upload file with multipart/form-data.
     */
    async upload(endpoint, formData, onProgress) {
        const token = getAuthToken()
        const headers = {}
        if (token) {
            headers['Authorization'] = `Bearer ${token}`
        }

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest()
            xhr.open('POST', endpoint.startsWith('http') ? endpoint : `${BASE_URL}${endpoint}`)

            if (token) {
                xhr.setRequestHeader('Authorization', `Bearer ${token}`)
            }

            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable && onProgress) {
                    const percent = Math.round((event.loaded / event.total) * 100)
                    onProgress(percent)
                }
            })

            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        resolve(JSON.parse(xhr.responseText))
                    } catch {
                        resolve({ success: true })
                    }
                } else {
                    reject(new ApiError(`Upload failed with status ${xhr.status}`, xhr.status))
                }
            })

            xhr.addEventListener('error', () => {
                reject(new ApiError('Upload failed: network error', 0))
            })

            xhr.send(formData)
        })
    },

    /**
     * Set auth token for subsequent requests.
     */
    setToken(token) {
        if (token) {
            localStorage.setItem('auth_token', token)
        } else {
            localStorage.removeItem('auth_token')
        }
    },

    /**
     * Clear auth token.
     */
    clearToken() {
        localStorage.removeItem('auth_token')
    },
}

/**
 * API endpoint modules for type-safe access.
 */
export const endpoints = {
    // Auth
    auth: {
        login: (credentials) => api.post('/api/v1/auth/login', credentials),
        logout: () => api.post('/api/v1/auth/logout'),
        refresh: () => api.post('/api/v1/auth/refresh'),
        me: () => api.get('/api/v1/auth/me'),
    },

    // Customer
    customer: {
        dashboard: () => api.get('/api/v1/customer/dashboard'),
        profile: () => api.get('/api/v1/customer/profile'),
        updateProfile: (data) => api.put('/api/v1/customer/profile', data),
        usage: () => api.get('/api/v1/customer/usage'),
    },

    // Billing
    billing: {
        plans: () => api.get('/api/v1/billing/plans'),
        subscription: () => api.get('/api/v1/billing/subscription'),
        subscribe: (planId, paymentMethod) => api.post('/api/v1/billing/subscribe', { planId, paymentMethod }),
        cancel: () => api.post('/api/v1/billing/cancel'),
        invoices: (params) => api.get('/api/v1/billing/invoices', params),
        cryptoAddresses: () => api.get('/api/v1/billing/crypto-addresses'),
        toggleAutoRenew: (enabled) => api.post('/api/v1/billing/auto-renew', { enabled }),
        refunds: (params) => api.get('/api/v1/billing/refunds', params),
    },

    // Generation
    generation: {
        generate: (type, params) => api.post('/api/v1/generate', { type, ...params }),
        history: (params) => api.get('/api/v1/generation/history', params),
        status: (id) => api.get(`/api/v1/generation/${id}/status`),
        cancel: (id) => api.delete(`/api/v1/generation/${id}`),
    },

    // Search
    search: {
        query: (params) => api.get('/api/v1/search', params),
        recent: () => api.get('/api/v1/search/recent'),
        save: (query, name) => api.post('/api/v1/search/save', { query, name }),
        saved: () => api.get('/api/v1/search/saved'),
        deleteSaved: (id) => api.delete(`/api/v1/search/saved/${id}`),
    },

    // API Keys
    apiKeys: {
        list: () => api.get('/api/v1/account/api-keys'),
        create: (name) => api.post('/api/v1/account/api-keys', { name }),
        revoke: (id) => api.delete(`/api/v1/account/api-keys/${id}`),
    },

    // Security
    security: {
        passkeys: {
            register: (data) => api.post('/api/v1/account/passkeys/register', data),
            list: () => api.get('/api/v1/account/passkeys'),
            delete: (id) => api.delete(`/api/v1/account/passkeys/${id}`),
        },
        twoFactor: {
            enable: (secret) => api.post('/api/v1/account/2fa/enable', { secret }),
            disable: (code) => api.post('/api/v1/account/2fa/disable', { code }),
            generate: () => api.get('/api/v1/account/2fa/generate'),
        },
        sessions: () => api.get('/api/v1/account/sessions'),
        revokeSession: (id) => api.delete(`/api/v1/account/sessions/${id}`),
    },

    // Notifications
    notifications: {
        preferences: () => api.get('/api/v1/account/notifications/preferences'),
        updatePreferences: (prefs) => api.put('/api/v1/account/notifications/preferences', prefs),
    },

    // GDPR
    gdpr: {
        export: () => api.post('/api/v1/account/export'),
        delete: (confirmation) => api.post('/api/v1/account/delete', { confirmation }),
    },

    // Wallet
    wallet: {
        connect: (address, chain) => api.post('/api/v1/wallet/connect', { address, chain }),
        disconnect: () => api.post('/api/v1/wallet/disconnect'),
        status: () => api.get('/api/v1/wallet/status'),
    },
}

export { ApiError, RateLimitError }
