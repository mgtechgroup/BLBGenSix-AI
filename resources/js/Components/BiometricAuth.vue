<script setup>
import { ref, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ mode: { type: String, default: 'login' } })
const status = ref('idle')
const message = ref('')

onMounted(async () => {
    if (props.mode === 'login') {
        await authenticate()
    }
})

const authenticate = async () => {
    status.value = 'authenticating'
    message.value = 'Touch your fingerprint sensor or use Face ID...'
    
    try {
        const options = await fetch('/api/v1/auth/webauthn/options').then(r => r.json())
        const credential = await navigator.credentials.get({
            publicKey: options,
        })
        
        const response = await fetch('/api/v1/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ credential }),
        })
        
        const data = await response.json()
        if (data.token) {
            localStorage.setItem('auth_token', data.token)
            router.visit('/dashboard')
        }
    } catch (e) {
        status.value = 'error'
        message.value = 'Biometric authentication failed. Try again.'
    }
}

const register = async () => {
    status.value = 'registering'
    message.value = 'Setting up your biometric passkey...'
    
    try {
        const options = await fetch('/api/v1/auth/webauthn/options').then(r => r.json())
        const credential = await navigator.credentials.create({
            publicKey: options,
        })
        
        await fetch('/api/v1/auth/biometric/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ credential }),
        })
        
        status.value = 'success'
        message.value = 'Biometric registered! Redirecting...'
        setTimeout(() => router.visit('/dashboard'), 2000)
    } catch (e) {
        status.value = 'error'
        message.value = 'Failed to register biometric.'
    }
}
</script>

<template>
    <div class="card text-center">
        <div class="text-6xl mb-6">🔐</div>
        <h2 class="text-xl font-bold mb-2">
            {{ mode === 'login' ? 'Biometric Login' : 'Register Passkey' }}
        </h2>
        <p class="text-gray-400 mb-6">No passwords. Just your fingerprint or face.</p>
        
        <div v-if="status === 'idle'" class="space-y-4">
            <button @click="mode === 'login' ? authenticate() : register()" class="btn-primary w-full">
                {{ mode === 'login' ? 'Use Biometrics' : 'Setup Passkey' }}
            </button>
        </div>
        
        <div v-else-if="status === 'authenticating' || status === 'registering'" class="text-violet-400">
            <p class="animate-pulse">{{ message }}</p>
        </div>
        
        <div v-else-if="status === 'error'" class="text-red-400">
            <p>{{ message }}</p>
            <button @click="mode === 'login' ? authenticate() : register()" class="btn-primary w-full mt-4">Retry</button>
        </div>
        
        <div v-else-if="status === 'success'" class="text-green-400">
            <p>{{ message }}</p>
        </div>
    </div>
</template>
