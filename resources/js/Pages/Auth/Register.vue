<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import BiometricAuth from '@/Components/BiometricAuth.vue'

const form = useForm({
    email: '',
    username: '',
    date_of_birth: '',
})

const step = ref(1)

const submit = () => {
    form.post('/register', {
        onSuccess: () => { step.value = 2 }
    })
}
</script>

<template>
    <Head title="Register - BLBGenSix AI" />
    <div class="min-h-screen bg-[#0F0F0F] flex items-center justify-center px-6">
        <div class="w-full max-w-md">
            <Link href="/" class="text-2xl font-bold gradient-text block text-center mb-8">BLBGenSix AI</Link>
            
            <div class="card">
                <h1 class="text-2xl font-bold mb-6">Create Account</h1>
                
                <div v-if="step === 1">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Email</label>
                            <input v-model="form.email" type="email" class="input-dark" required />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Username</label>
                            <input v-model="form.username" type="text" class="input-dark" required />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Date of Birth (18+)</label>
                            <input v-model="form.date_of_birth" type="date" class="input-dark" required />
                        </div>
                        <p class="text-xs text-gray-500">🔒 No passwords. You'll authenticate with biometrics.</p>
                        <button type="submit" class="btn-primary w-full" :disabled="form.processing">Continue</button>
                    </form>
                </div>

                <div v-else-if="step === 2">
                    <BiometricAuth mode="register" />
                </div>
            </div>
        </div>
    </div>
</template>
