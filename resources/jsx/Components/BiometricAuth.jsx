import React, { useState, useCallback } from 'react'
import { Head, Link, router } from '@inertiajs/react'

export default function BiometricAuth({ mode = 'login' }) {
    const [status, setStatus] = useState('idle')
    const [message, setMessage] = useState('')

    const authenticate = useCallback(async () => {
        setStatus('authenticating')
        setMessage('Touch your fingerprint sensor or use Face ID...')
        try {
            const options = await fetch('/api/v1/auth/webauthn/options').then(r => r.json())
            const credential = await navigator.credentials.get({ publicKey: options })
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
            setStatus('error')
            setMessage('Biometric authentication failed.')
        }
    }, [])

    if (mode === 'login' && status === 'idle') authenticate()

    return (
        <div className="card text-center">
            <Head title={`${mode === 'login' ? 'Login' : 'Register'} - BLBGenSix AI`} />
            <div className="text-6xl mb-6">🔐</div>
            <h2 className="text-xl font-bold mb-2">{mode === 'login' ? 'Biometric Login' : 'Register Passkey'}</h2>
            <p className="text-gray-400 mb-6">No passwords. Just your fingerprint or face.</p>
            {status === 'idle' && <button onClick={authenticate} className="btn-primary w-full">{mode === 'login' ? 'Use Biometrics' : 'Setup Passkey'}</button>}
            {status === 'authenticating' && <p className="text-violet-400 animate-pulse">{message}</p>}
            {status === 'error' && <div><p className="text-red-400">{message}</p><button onClick={authenticate} className="btn-primary w-full mt-4">Retry</button></div>}
        </div>
    )
}
