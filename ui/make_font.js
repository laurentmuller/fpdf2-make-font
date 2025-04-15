/*!
 * Color mode toggler for Bootstrap's docs (https://getbootstrap.com/)
 * Copyright 2011-2025 The Bootstrap Authors
 * Licensed under the Creative Commons Attribution 3.0 Un-ported License.
 */

(() => {
    'use strict'

    const getStoredTheme = () => localStorage.getItem('theme')
    const setStoredTheme = theme => localStorage.setItem('theme', theme)

    const getMediaTheme = () => {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    }
    const getPreferredTheme = () => {
        const storedTheme = getStoredTheme()
        if (storedTheme) {
            return storedTheme
        }

        return getMediaTheme()
    }

    const setTheme = theme => {
        if (theme === 'auto') {
            theme = getMediaTheme()
        }
        document.documentElement.setAttribute('data-bs-theme', theme)
    }

    setTheme(getPreferredTheme())

    const showActiveTheme = (theme, focus = false) => {
        const themeSwitcher = document.querySelector('#bd-theme')
        if (!themeSwitcher) {
            return
        }

        document.querySelectorAll('[data-theme]').forEach(element => {
            element.classList.remove('active')
            element.setAttribute('aria-pressed', 'false')
        })

        const selection = document.querySelector(`[data-theme="${theme}"]`)
        const selectionIcon = selection.querySelector('.theme-icon')
        const selectionText = selection.querySelector('.theme-text')
        selection.classList.add('active')
        selection.setAttribute('aria-pressed', 'true')

        const activeThemeIcon = themeSwitcher.querySelector('.theme-icon')
        const activeThemeText = themeSwitcher.querySelector('.theme-text')
        activeThemeText.textContent = selectionText.textContent
        activeThemeIcon.setAttribute('class', selectionIcon.getAttribute('class'));
        themeSwitcher.setAttribute('aria-label', selectionText.textContent)

        if (focus) {
            themeSwitcher.focus()
        }
    }

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        const storedTheme = getStoredTheme()
        if (storedTheme !== 'light' && storedTheme !== 'dark') {
            setTheme(getPreferredTheme())
        }
    })

    window.addEventListener('DOMContentLoaded', () => {
        showActiveTheme(getPreferredTheme())
        document.querySelectorAll('[data-theme]').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const theme = toggle.getAttribute('data-theme')
                setStoredTheme(theme)
                setTheme(theme)
                showActiveTheme(theme, true)
            })
        })
    })

    const form = document.getElementById('make-font')
    form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
        }
        form.classList.add('was-validated')
    }, false)

    /** @type {HTMLButtonElement} */
    const reset = document.querySelector('.btn-erase')
    reset.addEventListener('click', event => {
        form.reset()
    })

    const encoding = document.getElementById('encoding');
    encoding.addEventListener('change', () => {
        localStorage.setItem('encoding', encoding.value);
    })
    /** @type {HTMLInputElement} */
    const embed = document.getElementById('embed');
    embed.addEventListener('click', () => {
        localStorage.setItem('embed', JSON.stringify(embed.checked))
    })
    /** @type {HTMLInputElement} */
    const subset = document.getElementById('subset');
    subset.addEventListener('click', () => {
        localStorage.setItem('subset', JSON.stringify(subset.checked))
    })

    let value;
    value = localStorage.getItem('encoding');
    if (value) {
        encoding.value = value
    }
    value = localStorage.getItem('embed')
    if (value) {
        embed.checked = JSON.parse(value)
    }
    value = localStorage.getItem('subset')
    if (value) {
        subset.checked = JSON.parse(value)
    }
})()
