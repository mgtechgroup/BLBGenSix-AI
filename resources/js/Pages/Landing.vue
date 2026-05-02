<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
    <div class="min-h-screen bg-[#0F0F0F]">
        <Head title="BLBGenSix AI - Uncensored Generative AI Platform" />
        
        <nav class="fixed top-0 w-full z-50 bg-[#0F0F0F]/80 backdrop-blur-xl border-b border-[#333]">
            <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                <Link href="/" class="text-2xl font-bold gradient-text">BLBGenSix AI</Link>
                <div class="hidden md:flex items-center gap-6">
                    <a href="#features" class="text-gray-400 hover:text-white transition">Features</a>
                    <a href="#pricing" class="text-gray-400 hover:text-white transition">Pricing</a>
                    <a href="#security" class="text-gray-400 hover:text-white transition">Security</a>
                    <Link href="/login" class="text-gray-400 hover:text-white transition">Login</Link>
                    <Link href="/register" class="btn-primary">Get Started</Link>
                </div>
            </div>
        </nav>

        <main class="pt-24">
            <section class="max-w-7xl mx-auto px-6 py-32 text-center">
                <h1 class="text-6xl md:text-8xl font-black mb-6">
                    <span class="gradient-text">Uncensored AI</span><br />
                    Generation Platform
                </h1>
                <p class="text-xl text-gray-400 max-w-2xl mx-auto mb-12">
                    Generate images, videos, novels, and 3D body models. 
                    Zero-trust passwordless security. Cold-wallet crypto payments. 
                    Adults only, 18+ verified.
                </p>
                <div class="flex justify-center gap-4">
                    <Link href="/register" class="btn-primary glow-violet">Start Free Trial</Link>
                    <a href="#features" class="px-6 py-3 border border-[#333] text-white rounded-xl hover:border-violet-500 transition">
                        Learn More
                    </a>
                </div>
                <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                    <div><span class="text-3xl font-bold text-violet-400">4</span><p class="text-gray-500">AI Engines</p></div>
                    <div><span class="text-3xl font-bold text-pink-400">10+</span><p class="text-gray-500">Revenue Streams</p></div>
                    <div><span class="text-3xl font-bold text-green-400">7</span><p class="text-gray-500">Crypto Networks</p></div>
                    <div><span class="text-3xl font-bold text-orange-400">0</span><p class="text-gray-500">Passwords</p></div>
                </div>
            </section>

            <section id="features" class="max-w-7xl mx-auto px-6 py-24">
                <h2 class="text-4xl font-bold text-center mb-16">Generation Engines</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="card hover:border-violet-500 transition group">
                        <div class="text-4xl mb-4">🎨</div>
                        <h3 class="text-xl font-bold mb-2">Image Generation</h3>
                        <p class="text-gray-400 text-sm">SDXL-powered anime, realistic, artistic. Upscale, inpaint, variations.</p>
                    </div>
                    <div class="card hover:border-pink-500 transition">
                        <div class="text-4xl mb-4">🎬</div>
                        <h3 class="text-xl font-bold mb-2">Video Generation</h3>
                        <p class="text-gray-400 text-sm">Up to 300s 4K 60fps. Storyboard-to-video, script-to-video.</p>
                    </div>
                    <div class="card hover:border-green-500 transition">
                        <div class="text-4xl mb-4">📖</div>
                        <h3 class="text-xl font-bold mb-2">Novel Generation</h3>
                        <p class="text-gray-400 text-sm">Full novels, storyboards, scripts. Up to 500K tokens.</p>
                    </div>
                    <div class="card hover:border-orange-500 transition">
                        <div class="text-4xl mb-4">🧍</div>
                        <h3 class="text-xl font-bold mb-2">Body Mapping</h3>
                        <p class="text-gray-400 text-sm">SMPL-X 3D models, face reconstruction, animation.</p>
                    </div>
                </div>
            </section>

            <section id="security" class="max-w-7xl mx-auto px-6 py-24">
                <h2 class="text-4xl font-bold text-center mb-16">Zero-Trust Security</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="card">
                        <h3 class="text-lg font-bold text-violet-400 mb-2">🔐 Passwordless</h3>
                        <p class="text-gray-400 text-sm">WebAuthn biometric auth. Fingerprint, Face ID, Windows Hello. No passwords stored.</p>
                    </div>
                    <div class="card">
                        <h3 class="text-lg font-bold text-pink-400 mb-2">🛡️ Zero-Trust</h3>
                        <p class="text-gray-400 text-sm">Every request verified. Device fingerprinting. 15-min session rotation. IP validation.</p>
                    </div>
                    <div class="card">
                        <h3 class="text-lg font-bold text-green-400 mb-2">❄️ Cold Wallet Only</h3>
                        <p class="text-gray-400 text-sm">Only Ledger, Trezor, Keystone, and device-bound passkeys. No hot wallets allowed.</p>
                    </div>
                </div>
            </section>

            <section id="pricing" class="max-w-7xl mx-auto px-6 py-24">
                <h2 class="text-4xl font-bold text-center mb-16">Pricing</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="card">
                        <h3 class="text-2xl font-bold">Starter</h3>
                        <p class="text-4xl font-bold mt-4">$29<span class="text-lg text-gray-500">.99/mo</span></p>
                        <ul class="mt-6 space-y-2 text-gray-400 text-sm">
                            <li>50 images/day</li>
                            <li>5 videos/day</li>
                            <li>50K tokens/day</li>
                        </ul>
                        <Link href="/register" class="btn-primary w-full mt-6">Start Trial</Link>
                    </div>
                    <div class="card border-violet-500 glow-violet">
                        <span class="text-xs bg-violet-600 px-3 py-1 rounded-full">POPULAR</span>
                        <h3 class="text-2xl font-bold mt-2">Professional</h3>
                        <p class="text-4xl font-bold mt-4">$99<span class="text-lg text-gray-500">.99/mo</span></p>
                        <ul class="mt-6 space-y-2 text-gray-400 text-sm">
                            <li>500 images/day</li>
                            <li>50 videos/day</li>
                            <li>500K tokens/day</li>
                            <li>Priority queue</li>
                        </ul>
                        <Link href="/register" class="btn-primary w-full mt-6">Start Trial</Link>
                    </div>
                    <div class="card">
                        <h3 class="text-2xl font-bold">Enterprise</h3>
                        <p class="text-4xl font-bold mt-4">$299<span class="text-lg text-gray-500">.99/mo</span></p>
                        <ul class="mt-6 space-y-2 text-gray-400 text-sm">
                            <li>Unlimited everything</li>
                            <li>4K resolution</li>
                            <li>Custom models</li>
                            <li>Dedicated support</li>
                        </ul>
                        <Link href="/register" class="btn-primary w-full mt-6">Contact Sales</Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-[#333] py-12 text-center text-gray-500 text-sm">
            <p>© 2026 BLBGenSix AI — blbgensixai.club — Adults Only, 18+</p>
        </footer>
    </div>
</template>
