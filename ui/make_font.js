(() => {
    'use strict'

    const THEME_AUTO = 'auto';
    const THEME_LIGHT = 'light';
    const THEME_DARK = 'dark';

    const getStoredTheme = () => localStorage.getItem('make-font-theme')

    const setStoredTheme = theme => localStorage.setItem('make-font-theme', theme)

    const getMediaTheme = () => window.matchMedia('(prefers-color-scheme: dark)').matches
        ? THEME_DARK
        : THEME_LIGHT

    const getPreferredTheme = () => getStoredTheme() || getMediaTheme()

    const setTheme = theme => {
        if (theme === THEME_AUTO) {
            theme = getMediaTheme()
        }
        document.documentElement.setAttribute('data-bs-theme', theme)
    }

    setTheme(getPreferredTheme())

    const showActiveTheme = (theme, focus = false) => {
        document.querySelectorAll('button[data-theme]').forEach(element => {
            element.classList.remove('active')
            element.setAttribute('aria-pressed', 'false')
        })

        const source = document.querySelector(`button[data-theme="${theme}"]`)
        const sourceIcon = source.querySelector('.theme-icon')
        const sourceText = source.querySelector('.theme-text')
        source.setAttribute('aria-pressed', 'true')
        source.classList.add('active')

        const target = document.getElementById('theme-switcher')
        const targetIcon = target.querySelector('.theme-icon')
        const targetText = target.querySelector('.theme-text')
        targetText.textContent = sourceText.textContent
        targetIcon.setAttribute('class', sourceIcon.getAttribute('class'));
        target.setAttribute('aria-label', sourceText.textContent)

        if (focus) {
            target.focus()
        }
    }

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        const storedTheme = getStoredTheme()
        if (storedTheme !== THEME_LIGHT && storedTheme !== THEME_DARK) {
            setTheme(getPreferredTheme())
        }
    })

    window.addEventListener('DOMContentLoaded', () => {
        showActiveTheme(getPreferredTheme())
        document.querySelectorAll('button[data-theme]').forEach(element => {
            element.addEventListener('click', () => {
                const theme = element.getAttribute('data-theme')
                showActiveTheme(theme, true)
                setStoredTheme(theme)
                setTheme(theme)
            })
        })
        document.querySelectorAll('.form-help.form-text').forEach(element => {
            element.addEventListener('click', () => {
                const input = element.parentElement.querySelector('input,select,textarea')
                if (input) {
                    input.focus()
                }
            })
        })

        const fontFile = document.getElementById('fontFile');
        const afmFile = document.getElementById('afmFile');
        fontFile.addEventListener('change', () => {
            const required = fontFile.files.length !== 0
                && fontFile.files[0].name.endsWith('.pfb');
            afmFile.required = required;
            afmFile.disabled = !required
        });
    })

    const form = document.getElementById('make-font')
    form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
            let skip = false;
            form.querySelectorAll('input,select,textarea').forEach(element => {
                if (!skip && !element.validity.valid) {
                    element.focus()
                    skip = true;
                }
                // <div class="invalid-feedback">
                //       Please enter a message in the textarea.
                //     </div>
            })
        }
        form.classList.add('was-validated')
    }, false)

    const reset = document.querySelector('.btn-erase')
    reset.addEventListener('click', () => {
        localStorage.removeItem('make-font-encoding');
        localStorage.removeItem('make-font-embed');
        localStorage.removeItem('make-font-subset');
        form.reset();
        document.getElementById('fontFile').dispatchEvent(new Event('change'));
    })

    const encoding = document.getElementById('encoding');
    encoding.addEventListener('change', () => {
        localStorage.setItem('make-font-encoding', encoding.value);
    })
    const embed = document.getElementById('embed');
    embed.addEventListener('click', () => {
        localStorage.setItem('make-font-embed', JSON.stringify(embed.checked))
    })
    const subset = document.getElementById('subset');
    subset.addEventListener('click', () => {
        localStorage.setItem('make-font-subset', JSON.stringify(subset.checked))
    })

    let value = localStorage.getItem('make-font-encoding');
    if (value) {
        encoding.value = value
    }
    value = localStorage.getItem('make-font-embed')
    if (value) {
        embed.checked = JSON.parse(value)
    }
    value = localStorage.getItem('make-font-subset')
    if (value) {
        subset.checked = JSON.parse(value)
    }
})()
