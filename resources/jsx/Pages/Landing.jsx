import React, { useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'

export default function Landing() {
    return (
        <div className="min-h-screen bg-[#0F0F0F]">
            <Head title="BLBGenSix AI" />
            <nav className="fixed top-0 w-full z-50 bg-[#0F0F0F]/80 backdrop-blur-xl border-b border-[#333]">
                <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                    <Link href="/" className="text-2xl font-bold bg-gradient-to-r from-violet-400 via-pink-500 to-orange-400 bg-clip-text text-transparent">BLBGenSix AI</Link>
                    <div className="hidden md:flex items-center gap-6">
                        <a href="#features" className="text-gray-400 hover:text-white transition">Features</a>
                        <a href="#pricing" className="text-gray-400 hover:text-white transition">Pricing</a>
                        <Link href="/login" className="text-gray-400 hover:text-white transition">Login</Link>
                        <Link href="/register" className="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl transition">Get Started</Link>
                    </div>
                </div>
            </nav>
            <main className="pt-24">
                <section className="max-w-7xl mx-auto px-6 py-32 text-center">
                    <h1 className="text-6xl md:text-8xl font-black mb-6">
                        <span className="bg-gradient-to-r from-violet-400 via-pink-500 to-orange-400 bg-clip-text text-transparent">Uncensored AI</span><br />Generation Platform
                    </h1>
                    <p className="text-xl text-gray-400 max-w-2xl mx-auto mb-12">Generate images, videos, novels, and 3D body models. Zero-trust passwordless. Cold-wallet crypto. Adults only.</p>
                    <div className="flex justify-center gap-4">
                        <Link href="/register" className="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl shadow-[0_0_30px_rgba(139,92,246,0.3)] transition">Start Free Trial</Link>
                    </div>
                </section>
                <footer className="border-t border-[#333] py-12 text-center text-gray-500 text-sm">© 2026 BLBGenSix AI — blbgensixai.club — Adults Only, 18+</footer>
            </main>
        </div>
    )
}
