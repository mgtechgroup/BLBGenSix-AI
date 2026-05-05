<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    /**
     * Feature definition object.
     * {
     *   id: string,
     *   title: string,
     *   description: string,
     *   icon: string,
     *   color: string,        // 'violet' | 'pink' | 'green' | 'orange'
     *   route: string,        // Inertia route name (optional)
     *   action: Function,     // click handler (optional, overrides route)
     *   badge: string,        // optional badge text
     *   stats: {              // optional usage stats overlay
     *     label: string,
     *     value: string
     *   },
     *   requires: string[],   // feature flags needed
     *   type: string          // 'generation' | 'revenue' | 'security' | 'widget'
     * }
     */
    feature: {
        type: Object,
        required: true,
    },

    /**
     * Whether the current plan has access to this feature.
     * If false, the card shows a locked state with an upgrade prompt.
     */
    enabled: {
        type: Boolean,
        default: true,
    },

    /**
     * Whether to show the hover glow effect.
     */
    glow: {
        type: Boolean,
        default: true,
    },

    /**
     * Compact mode for grid layouts.
     */
    compact: {
        type: Boolean,
        default: false,
    },

    /**
     * Whether the card is currently selected/highlighted.
     */
    selected: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['click', 'upgrade'])

const colorMap = {
    violet: {
        border: 'border-violet-500/30 hover:border-violet-500',
        glow: 'hover:shadow-[0_0_25px_rgba(139,92,246,0.2)]',
        icon: 'text-violet-400',
        badge: 'bg-violet-600/20 text-violet-400 border-violet-500/30',
        iconBg: 'bg-violet-500/10',
        ring: 'ring-violet-500/50',
        progress: 'bg-violet-500',
    },
    pink: {
        border: 'border-pink-500/30 hover:border-pink-500',
        glow: 'hover:shadow-[0_0_25px_rgba(236,72,153,0.2)]',
        icon: 'text-pink-400',
        badge: 'bg-pink-600/20 text-pink-400 border-pink-500/30',
        iconBg: 'bg-pink-500/10',
        ring: 'ring-pink-500/50',
        progress: 'bg-pink-500',
    },
    green: {
        border: 'border-green-500/30 hover:border-green-500',
        glow: 'hover:shadow-[0_0_25px_rgba(16,185,129,0.2)]',
        icon: 'text-green-400',
        badge: 'bg-green-600/20 text-green-400 border-green-500/30',
        iconBg: 'bg-green-500/10',
        ring: 'ring-green-500/50',
        progress: 'bg-green-500',
    },
    orange: {
        border: 'border-orange-500/30 hover:border-orange-500',
        glow: 'hover:shadow-[0_0_25px_rgba(249,115,22,0.2)]',
        icon: 'text-orange-400',
        badge: 'bg-orange-600/20 text-orange-400 border-orange-500/30',
        iconBg: 'bg-orange-500/10',
        ring: 'ring-orange-500/50',
        progress: 'bg-orange-500',
    },
}

const colors = computed(() => colorMap[props.feature.color] || colorMap.violet)

const hasAction = computed(() => {
    return props.feature.route || typeof props.feature.action === 'function'
})

const isExternal = computed(() => {
    return typeof props.feature.action === 'function'
})

function handleClick() {
    if (!props.enabled) {
        emit('upgrade', props.feature)
        return
    }
    if (typeof props.feature.action === 'function') {
        props.feature.action()
    }
    emit('click', props.feature)
}
</script>

<template>
    <component
        :is="feature.route && enabled ? Link : 'div'"
        :href="feature.route && enabled ? route(feature.route) : undefined"
        @click="handleClick"
        class="group relative card transition-all duration-300 cursor-pointer overflow-hidden"
        :class="[
            enabled ? [
                colors.border,
                glow ? colors.glow : '',
                selected ? `ring-1 ${colors.ring}` : '',
                hasAction ? 'hover:-translate-y-1' : '',
            ] : 'border-gray-700/30 opacity-60',
            compact ? 'p-4' : 'p-6',
        ]"
    >
        <!-- Locked overlay -->
        <div
            v-if="!enabled"
            class="absolute inset-0 bg-[#0F0F0F]/60 backdrop-blur-[1px] flex items-center justify-center z-10 rounded-2xl"
        >
            <div class="text-center">
                <div class="text-3xl mb-2">🔒</div>
                <p class="text-xs text-gray-500 mb-3">Upgrade to unlock</p>
                <button
                    @click.stop="emit('upgrade', feature)"
                    class="px-3 py-1.5 text-xs bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors"
                >
                    View Plans
                </button>
            </div>
        </div>

        <!-- Card header -->
        <div class="flex items-start justify-between mb-3">
            <div
                class="w-10 h-10 rounded-xl flex items-center justify-center text-xl shrink-0"
                :class="colors.iconBg"
            >
                {{ feature.icon }}
            </div>
            <span
                v-if="feature.badge"
                class="px-2 py-0.5 text-[10px] font-medium rounded-full border"
                :class="colors.badge"
            >
                {{ feature.badge }}
            </span>
        </div>

        <!-- Title & description -->
        <h3
            class="font-bold mb-1"
            :class="[
                enabled ? 'text-white group-hover:text-gray-100' : 'text-gray-500',
                compact ? 'text-sm' : 'text-base',
            ]"
        >
            {{ feature.title }}
        </h3>
        <p
            class="line-clamp-2"
            :class="[
                enabled ? 'text-gray-400' : 'text-gray-600',
                compact ? 'text-xs' : 'text-sm',
            ]"
        >
            {{ feature.description }}
        </p>

        <!-- Stats bar (usage / progression) -->
        <div v-if="feature.stats && enabled" class="mt-4 space-y-1.5">
            <div class="flex items-center justify-between text-xs text-gray-500">
                <span>{{ feature.stats.label }}</span>
                <span :class="colors.icon">{{ feature.stats.value }}</span>
            </div>
            <div class="h-1 bg-[#242424] rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-700"
                    :class="colors.progress"
                    :style="{ width: (feature.stats.percent ?? 50) + '%' }"
                />
            </div>
        </div>

        <!-- Upgrade prompt for enabled cards with higher tiers -->
        <div
            v-if="enabled && feature.upgradeHint"
            class="mt-3 pt-3 border-t border-[#333]"
        >
            <p class="text-[10px] text-gray-600 flex items-center gap-1">
                <span>✨</span> {{ feature.upgradeHint }}
            </p>
        </div>

        <!-- Click hint -->
        <div
            v-if="hasAction && enabled && !compact"
            class="mt-3 flex items-center gap-1 text-xs opacity-0 group-hover:opacity-100 transition-opacity duration-200"
            :class="colors.icon"
        >
            <span>{{ isExternal ? 'Open' : 'Go to' }}</span>
            <span class="text-[10px]">→</span>
        </div>
    </component>
</template>
