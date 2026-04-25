<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';

const STORAGE_KEY = 'google_translate_lang';
const LANGUAGES = [
    { code: 'en', label: 'English', flag: 'us' },
    { code: 'fr', label: 'French', flag: 'fr' },
    { code: 'es', label: 'Spanish', flag: 'mx' },
    { code: 'it', label: 'Italian', flag: 'it' },
    { code: 'af', label: 'Afrikaans', flag: 'za' },
    { code: 'zh-CN', label: 'Chinese (Simplified)', flag: 'cn' },
    { code: 'nl', label: 'Dutch', flag: 'nl' },
    { code: 'de', label: 'German', flag: 'de' },
    { code: 'ko', label: 'Korean', flag: 'kr' },
    { code: 'pt', label: 'Portuguese', flag: 'br' },
    { code: 'ru', label: 'Russian', flag: 'ru' },
    { code: 'sv', label: 'Swedish', flag: 'se' },
];

const isOpen = ref(false);
const selectedLanguage = ref(localStorage.getItem(STORAGE_KEY) || 'en');
let closeMenuHandler = null;
let comboObserver = null;

const selectedLanguageMeta = () => LANGUAGES.find((language) => language.code === selectedLanguage.value) || LANGUAGES[0];
const selectedLanguageCode = () => selectedLanguageMeta().code.toUpperCase();
const LOG_PREFIX = '[GlobalTranslateFab]';
const RELOAD_ONCE_KEY = 'google_translate_reload_once';
const logDebug = (message, payload = null) => {
    if (payload === null) {
        console.log(`${LOG_PREFIX} ${message}`);
        return;
    }

    console.log(`${LOG_PREFIX} ${message}`, payload);
};

const setGoogTransCookie = (value) => {
    const domainParts = window.location.hostname.split('.');
    const domain = domainParts.length > 1 ? `.${domainParts.slice(-2).join('.')}` : window.location.hostname;
    const cookieValue = `googtrans=${encodeURIComponent(value)};path=/;max-age=31536000`;

    document.cookie = cookieValue;
    document.cookie = `${cookieValue};domain=${domain}`;
    logDebug('Set googtrans cookie', { value, domain, hostname: window.location.hostname });
};

const getTranslateSelect = () => document.querySelector('.goog-te-combo');

const triggerHtmlChange = (element) => {
    if (!element) {
        logDebug('Skipped change dispatch because combo element is missing');
        return;
    }

    const event = document.createEvent('HTMLEvents');
    event.initEvent('change', true, true);
    element.dispatchEvent(event);
    logDebug('Dispatched native change event on Google combo', { value: element.value });
};

const waitForTranslateSelect = (attempt = 0) => new Promise((resolve) => {
    const combo = getTranslateSelect();
    if (combo) {
        logDebug('Found Google combo select', { attempt });
        resolve(combo);
        return;
    }

    if (attempt >= 12) {
        logDebug('Google combo select not found after max attempts');
        resolve(null);
        return;
    }

    setTimeout(() => resolve(waitForTranslateSelect(attempt + 1)), 250);
});

const waitForGoogleReady = (attempt = 0) => new Promise((resolve) => {
    const isReady = !!window.google?.translate?.TranslateElement;
    if (isReady) {
        logDebug('Google Translate runtime is ready', { attempt });
        resolve(true);
        return;
    }

    if (attempt >= 24) {
        logDebug('Google Translate runtime not ready after max attempts');
        resolve(false);
        return;
    }

    if (attempt === 0 || attempt % 6 === 0) {
        logDebug('Waiting for Google Translate runtime', { attempt });
    }
    setTimeout(() => resolve(waitForGoogleReady(attempt + 1)), 250);
});

const initGoogleTranslateElement = () => {
    if (!window.google?.translate?.TranslateElement) {
        logDebug('TranslateElement missing during init');
        return;
    }

    const container = document.getElementById('google_translate_element');
    if (!container) {
        logDebug('Missing translate container in DOM');
        return;
    }

    if (container.dataset.initialized === 'true') {
        logDebug('Translate container already initialized');
        return;
    }

    new window.google.translate.TranslateElement({
        pageLanguage: 'en',
        autoDisplay: false,
        includedLanguages: LANGUAGES.map((language) => language.code).join(','),
        layout: window.google.translate.TranslateElement.InlineLayout.SIMPLE,
    }, 'google_translate_element');

    container.dataset.initialized = 'true';
    logDebug('Initialized Google TranslateElement');
};

const applyLanguage = async (lang) => {
    logDebug('Language selection requested', {
        lang,
        previous: selectedLanguage.value,
        hasGoogleRuntime: !!window.google?.translate?.TranslateElement,
    });
    selectedLanguage.value = lang;
    localStorage.setItem(STORAGE_KEY, lang);
    isOpen.value = false;
    logDebug('Saved selected language to localStorage', { key: STORAGE_KEY, value: lang });

    setGoogTransCookie(lang === 'en' ? '/en/en' : `/en/${lang}`);
    const runtimeReady = await waitForGoogleReady();
    if (!runtimeReady) {
        logDebug('Google runtime unavailable');
    }

    initGoogleTranslateElement();
    const combo = await waitForTranslateSelect();
    if (combo) {
        const hasOption = Array.from(combo.options || []).some((option) => option.value === lang);
        if (hasOption) {
            combo.value = lang;
            triggerHtmlChange(combo);
            sessionStorage.removeItem(RELOAD_ONCE_KEY);
            logDebug('Applied language via combo fallback', { lang });
            return;
        }
        logDebug('Combo exists but requested option missing', { lang });
    }

    // One-time hard fallback: allow Google to apply cookie on fresh load.
    const reloadMarker = sessionStorage.getItem(RELOAD_ONCE_KEY);
    if (reloadMarker !== lang) {
        sessionStorage.setItem(RELOAD_ONCE_KEY, lang);
        logDebug('Triggering one-time reload fallback', { lang });
        window.location.reload();
        return;
    }

    logDebug('Skipped reload fallback to avoid loops', { lang, reloadMarker });
};

const loadGoogleTranslateScript = () => {
    logDebug('Loading Google Translate script', {
        hasGoogleRuntime: !!window.google?.translate?.TranslateElement,
    });
    if (window.google?.translate?.TranslateElement) {
        logDebug('Google runtime already present');
        initGoogleTranslateElement();
        return;
    }

    window.googleTranslateElementInit = () => {
        logDebug('googleTranslateElementInit callback fired');
        initGoogleTranslateElement();
    };

    if (document.getElementById('google-translate-script')) {
        logDebug('Script tag already exists; waiting for runtime');
        return;
    }

    const script = document.createElement('script');
    script.id = 'google-translate-script';
    script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
    script.async = true;
    script.onload = () => {
        logDebug('Google Translate script loaded');
    };
    script.onerror = () => {
        logDebug('Google Translate script failed to load');
    };
    document.body.appendChild(script);
    logDebug('Google Translate script appended to document');
};

onMounted(async () => {
    logDebug('Component mounted', {
        selectedLanguage: selectedLanguage.value,
        currentUrl: window.location.href,
    });
    loadGoogleTranslateScript();
    comboObserver = new MutationObserver(() => {
        const combo = getTranslateSelect();
        if (combo) {
            logDebug('MutationObserver detected Google combo in DOM');
        }
    });
    comboObserver.observe(document.body, { childList: true, subtree: true });

    closeMenuHandler = (event) => {
        if (!event.target.closest('.translate-fab')) {
            isOpen.value = false;
        }
    };
    document.addEventListener('click', closeMenuHandler);

    if (selectedLanguage.value !== 'en') {
        // Restore previously selected language without refreshing the page.
        logDebug('Restoring previously selected language on mount', { lang: selectedLanguage.value });
        await applyLanguage(selectedLanguage.value);
    }
});

onBeforeUnmount(() => {
    logDebug('Component unmounting');
    if (closeMenuHandler) {
        document.removeEventListener('click', closeMenuHandler);
        logDebug('Removed document click handler');
    }
    if (comboObserver) {
        comboObserver.disconnect();
        comboObserver = null;
        logDebug('Disconnected combo observer');
    }
});
</script>

<template>
    <div class="translate-fab">
        <div id="google_translate_element" class="translate-fab__google-root" />

        <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-3.5 py-3 text-sm font-semibold text-slate-700 shadow-soft transition-all duration-200 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
            aria-label="Language selector"
            @click="isOpen = !isOpen"
        >
            <span
                :class="`fi fi-${selectedLanguageMeta().flag}`"
                class="h-3.5 w-5 overflow-hidden rounded-sm border border-slate-200"
                aria-hidden="true"
            />
            <span>{{ selectedLanguageCode() }}</span>
        </button>

        <div
            v-if="isOpen"
            class="absolute right-0 bottom-[calc(100%+0.5rem)] w-60 max-h-80 overflow-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-soft-lg"
        >
            <button
                v-for="language in LANGUAGES"
                :key="language.code"
                type="button"
                class="w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-100"
                :class="selectedLanguage === language.code
                    ? 'bg-blue-50 text-blue-700'
                    : ''"
                @click="applyLanguage(language.code)"
            >
                <span class="inline-flex items-center gap-2">
                    <span
                        :class="`fi fi-${language.flag}`"
                        class="h-3.5 w-5 overflow-hidden rounded-sm border border-slate-200"
                        aria-hidden="true"
                    />
                    <span class="min-w-7 uppercase">{{ language.code }}</span>
                </span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.translate-fab {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 10050;
}

.translate-fab__google-root {
    position: fixed;
    left: -9999px;
    top: -9999px;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
    overflow: hidden;
}
</style>
