import React from 'react'
import ReactDOM from 'react-dom/client'
import { createInertiaApp } from '@inertiajs/react'

import '../css/app.css'

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true })
        return pages[`./Pages/${name}.jsx`].default
    },
    setup({ el, App, props }) {
        const root = ReactDOM.createRoot(el)
        root.render(<App {...props} />)
    },
})
