<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useCustomerStore } from '@/stores/customerStore'

const props = defineProps({
    /**
     * Generation type: 'image' | 'video' | 'text' | 'body'
     */
    type: {
        type: String,
        required: true,
        validator: v => ['image', 'video', 'text', 'body'].includes(v),
    },

    /**
     * Button label override. Falls back to a sensible default.
     */
    label: {
        type: String,
        default: '',
    },

    /**
     * Button variant: 'primary' | 'secondary' | 'ghost'
     */
    variant: {
        type: String,
        default: 'primary',
        validator: v => ['primary', 'secondary', 'ghost'].includes(v),
    },

    /**
     * Button size: 'sm' | 'md' | 'lg'
     */
    size: {
        type: String,
        default: 'md',
        validator: v => ['sm', 'md', 'lg'].includes(v),
    },

    /**
     * Whether to show the loading spinner when generating.
     */
    showSpinner: {
        type: Boolean,
        default: true,
    },

    /**
     * Whether to show quota info next to the button.
     */
    showQuota: {
        type: Boolean,
        default: true,
    },

    /**
     * Extra generation params to send to the API.
     */
    extraParams: {
        type: Object,
        default: () => ({}),
    },

    /**
     * Disable the button even if quota allows generation.
     */
    disabled: {
        type: Boolean,
        default: false,
    },

    /**
     * Redirect route after successful generation.
     */
    redirectTo: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['generate', 'success', 'error', 'start', 'complete'])

const store = useCustomerStore()

const isGenerating = ref(false)
const lastError = ref(null)

const typeMeta = computed(() => {
    const map = {
        image: { label: 'Generate Image', icon: '🎨', color: 'violet', route: 'generation.image' },
        video: { label: 'Generate Video', icon: '🎬', color: 'pink', route: 'generation.video' },
        text:  { label: 'Generate Text', icon: '📖', color: 'green', route: 'generation.text' },
        body:  { label: 'Body Mapping', icon: '🧍', color: 'orange', route: 'generation.body' },
    }
    return map[props.type]
})

const displayLabel = computed(() => props.label || typeMeta.value.label)

const canGen = computed(() => store.canGenerate(props.type) && !props.disabled)

const remaining = computed(() => store.remainingQuota(props.type))

const quotaLabel = computed(() => {
    if (remaining.value === '∞') return 'Unlimited'
    return `${remaining.value} left`
})

const variantClasses = computed(() => {
    const base = {
        primary: 'bg-violet-600 hover:bg-violet-700 text-white shadow-lg shadow-violet-500/25',
        secondary: 'bg-pink-600 hover:bg-pink-700 text-white shadow-lg shadow-pink-500/25',
        ghost: 'bg-transparent hover:bg-[#242424] text-gray-300 border border-[#333]',
    }
    return base[props.variant]
})

const sizeClasses = computed(() => ({
    sm: 'px-3 py-1.5 text-xs rounded-lg gap-1.5',
    md: 'px-5 py-2.5 text-sm rounded-xl gap-2',
    lg: 'px-6 py-3.5 text-base rounded-xl gap-2.5',
})[props.size])

const isLoading = computed(() => isGenerating.value && props.showSpinner)

const isDone = computed(() => !isGenerating.value && !lastError.value && !props.disabled)

async function handleGenerate() {
    if (!canGen.value || isGenerating.value) return

    isGenerating.value = true
    lastError.value = null
    emit('start')

    try {
        const result = await store.generate(props.type, props.extraParams)
        emit('generate', result)
        emit('success', result)
        emit('complete')

        if (props.redirectTo) {
            router.visit(props.redirectTo)
        }
    } catch (e) {
        lastError.value = e.message || 'Generation failed'
        emit('error', e)
        emit('complete')
    } finally {
        isGenerating.value = false
    }
}
</script>

<template>
    <div class="inline-flex flex-col items-center gap-1.5">
        <button
            @click="handleGenerate"
            :disabled="!canGen || isGenerating"
            class="inline-flex items-center justify-center font-semibold transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-[#0F0F0F]"
            :class="[
                variantClasses,
                sizeClasses,
                isLoading ? 'cursor-wait' : canGen ? 'cursor-pointer active:scale-95' : 'cursor-not-allowed',
                typeMeta.color === 'violet' ? 'focus:ring-violet-500' : '',
                typeMeta.color === 'pink' ? 'focus:ring-pink-500' : '',
                typeMeta.color === 'green' ? 'focus:ring-green-500' : '',
                typeMeta.color === 'orange' ? 'focus:ring-orange-500' : '',
            ]"
        >
            <!-- Spinner -->
            <svg
                v-if="isLoading"
                class="animate-spin shrink-0"
                :class="size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4'"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>

            <!-- Done check -->
            <svg
                v-else-if="isDone && !canGen"
                class="shrink-0"
                :class="size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4'"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>

            <!-- Icon -->
            <span v-else class="shrink-0">{{ typeMeta.icon }}</span>

            <span>{{ isLoading ? 'Generating...' : displayLabel }}</span>
        </button>

        <!-- Quota indicator -->
        <span
            v-if="showQuota"
            class="text-[10px]"
            :class="store.usagePercent[`${type}s`] > 80 ? 'text-red-400' : 'text-gray-500'"
        >
            {{ quotaLabel }}
        </span>

        <!-- Error message -->
        <transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-200"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <p v-if="lastError" class="text-xs text-red-400 text-center max-w-[200px]">
                {{ lastError }}
            </p>
        </transition>
    </div>
</template>
