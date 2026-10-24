<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, LoaderCircle, Moon, Sun } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useAppearance } from '@/composables/useAppearance';
import { animate, stagger } from 'motion';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

type Check = {
    id: string;
    group: string;
    title: string;
    state: 'pass' | 'warn' | 'fail' | 'info';
    detail: string;
    evidence?: string | null;
    impact?: string;
};
type Report = {
    final_url: string;
    status: number;
    latency_ms: number;
    score: { value: number; grade: string };
    checks: Check[];
    php: { version?: string; reason: string };
    node: { version?: string; reason: string };
    fingerprints: { category: string; name: string; evidence: string }[];
    observed_headers?: Record<string, string>;
    limitations: string;
};
defineProps<{ site: { name: string; tagline: string } }>();
const { resolvedAppearance, updateAppearance } = useAppearance();
function toggleAppearance() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
const url = ref('');
const loading = ref(false);
const error = ref('');
const report = ref<Report | null>(null);
const contact = ref({
    name: '',
    email: '',
    site: '',
    message: '',
    website: '',
});
const contactMessage = ref('');
const contactSending = ref(false);
let featureObserver: IntersectionObserver | null = null;
const groups = computed(() =>
    report.value
        ? Object.groupBy(report.value.checks, (check) => check.group)
        : {},
);
const counts = computed(() => ({
    pass: report.value?.checks.filter((c) => c.state === 'pass').length ?? 0,
    warn: report.value?.checks.filter((c) => c.state === 'warn').length ?? 0,
    fail: report.value?.checks.filter((c) => c.state === 'fail').length ?? 0,
}));
const motionAllowed = () =>
    !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function scrollToHowItWorks(event: MouseEvent) {
    event.preventDefault();
    window.history.pushState(null, '', '#how-it-works');
    document.getElementById('how-it-works')?.scrollIntoView({
        behavior: motionAllowed() ? 'smooth' : 'auto',
        block: 'start',
    });
}

onMounted(() => {
    if (!motionAllowed()) return;

    animate(
        Array.from(
            document.querySelectorAll(
                '.sg-eyebrow, .sg-hero h1, .sg-lead, .sg-scan-form, .sg-hero-visual',
            ),
        ),
        { opacity: [0, 1], y: [14, 0] },
        { duration: 0.48, delay: stagger(0.07), ease: 'easeOut' },
    );

    if ('IntersectionObserver' in window) {
        featureObserver = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    animate(
                        entry.target,
                        { opacity: [0, 1], y: [14, 0] },
                        { duration: 0.4, ease: 'easeOut' },
                    );
                    featureObserver?.unobserve(entry.target);
                }
            },
            { threshold: 0.15 },
        );
        document.querySelectorAll('.sg-feature-grid article').forEach((card) =>
            featureObserver?.observe(card),
        );
    }
});

onUnmounted(() => featureObserver?.disconnect());

async function solveChallenge(): Promise<{ token: string; nonce: string }> {
    const response = await fetch('/challenge', {
        headers: { Accept: 'application/json' },
    });
    const challenge = await response.json();
    if (!response.ok)
        throw new Error(challenge.error ?? 'Could not start browser check.');
    const encoded = new TextEncoder();
    for (let nonce = 0; nonce < 1_000_000_000; nonce++) {
        const bytes = new Uint8Array(
            await crypto.subtle.digest(
                'SHA-256',
                encoded.encode(challenge.token + ':' + nonce),
            ),
        );
        const full = challenge.bits >> 3;
        const remaining = challenge.bits & 7;
        if (
            bytes.slice(0, full).every((value) => value === 0) &&
            (!remaining || bytes[full] >> (8 - remaining) === 0)
        ) {
            return { token: challenge.token, nonce: String(nonce) };
        }
        if (nonce % 3000 === 2999)
            await new Promise((resolve) => setTimeout(resolve, 0));
    }
    throw new Error('Browser check could not be completed.');
}

async function scan() {
    if (!url.value.trim() || loading.value) return;
    loading.value = true;
    error.value = '';
    report.value = null;
    try {
        const proof = await solveChallenge();
        const response = await fetch('/scans', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
            },
            body: JSON.stringify({
                url: url.value.trim(),
                ...proof,
                website: '',
            }),
        });
        const data = await response.json();
        if (!response.ok)
            throw new Error(data.error ?? data.message ?? 'Scan failed.');
        report.value = data.report;
        contact.value.site = hostname(data.report.final_url);
        await nextTick();
        if (motionAllowed()) {
            const element = document.getElementById('report');
            if (element)
                animate(
                    element,
                    { opacity: [0, 1], y: [14, 0] },
                    { duration: 0.42, ease: 'easeOut' },
                );
        }
        requestAnimationFrame(() =>
            document
                .getElementById('report')
                ?.scrollIntoView({
                    behavior: motionAllowed() ? 'smooth' : 'auto',
                }),
        );
    } catch (cause) {
        error.value = cause instanceof Error ? cause.message : 'Scan failed.';
    } finally {
        loading.value = false;
    }
}
function saveReport() {
    if (!report.value) return;
    const blob = new Blob([JSON.stringify(report.value, null, 2)], {
        type: 'application/json',
    });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download =
        'siteguard-' + new URL(report.value.final_url).hostname + '.json';
    link.click();
    URL.revokeObjectURL(link.href);
}
function printReport() {
    window.print();
}
function hostname(value: string) {
    return new URL(value).hostname;
}
async function sendContact() {
    if (contactSending.value) return;
    contactSending.value = true;
    contactMessage.value = 'Sending…';
    try {
        const response = await fetch('/contact', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
            },
            body: JSON.stringify(contact.value),
        });
        const data = await response.json();
        if (!response.ok)
            throw new Error(
                data.error ?? data.message ?? 'Could not send the request.',
            );
        contactMessage.value = 'Request sent. We will reply by email.';
        contact.value.message = '';
    } catch (cause) {
        contactMessage.value =
            cause instanceof Error
                ? cause.message
                : 'Could not send the request.';
    } finally {
        contactSending.value = false;
    }
}
</script>

<template>
    <Head title="Website security scan" />
    <div class="sg-page">
        <header class="sg-nav sg-wrap">
            <a href="/" class="sg-brand">
                <AppLogoIcon class="sg-logo" />
                {{ site.name }}
            </a>
            <nav aria-label="Main navigation">
                <a href="#how-it-works" @click="scrollToHowItWorks">How it works</a
                ><Link href="/monitored-sites">Site alerts</Link
                ><button
                    type="button"
                    class="sg-theme-toggle"
                    :aria-label="
                        resolvedAppearance === 'dark'
                            ? 'Switch to light mode'
                            : 'Switch to dark mode'
                    "
                    @click="toggleAppearance"
                >
                    <Sun v-if="resolvedAppearance === 'dark'" :size="17" />
                    <Moon v-else :size="17" />
                </button>
                <Link href="/login">Sign in</Link>
            </nav>
        </header>
        <main>
            <section class="sg-hero sg-wrap">
                <div class="sg-hero-main">
                    <div class="sg-hero-copy">
                        <div class="sg-eyebrow">
                            PASSIVE WEBSITE SECURITY INTELLIGENCE
                        </div>
                        <h1>{{ site.tagline }}</h1>
                        <p class="sg-lead">
                            Check public security headers, HTTPS, TLS, disclosed
                            runtimes, and technology signals in one clear
                            report.
                        </p>
                        <form
                            class="sg-scan-form"
                            :class="{ 'sg-is-loading': loading }"
                            :aria-busy="loading"
                            @submit.prevent="scan"
                        >
                            <label for="scan-url">Website to scan</label>
                            <div class="sg-input-row">
                                <input
                                    id="scan-url"
                                    v-model="url"
                                    type="text"
                                    inputmode="url"
                                    autocomplete="url"
                                    placeholder="example.com"
                                    required
                                    maxlength="2048"
                                /><button :disabled="loading" type="submit">
                                    {{ loading ? 'Scanning…' : 'Scan website' }}
                                    <LoaderCircle
                                        v-if="loading"
                                        class="sg-spinner"
                                        :size="16"
                                        aria-hidden="true"
                                    />
                                    <ArrowRight
                                        v-else
                                        :size="16"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                            <p class="sg-form-note">
                                Public websites only · Passive checks · No
                                exploit traffic
                            </p>
                        </form>
                        <p v-if="error" class="sg-error" role="alert">
                            {{ error }}
                        </p>
                    </div>
                    <figure class="sg-hero-visual">
                        <img
                            src="/siteguard-paper-shield.png"
                            alt="Abstract folded white paper shield"
                            width="1456"
                            height="1094"
                            fetchpriority="high"
                        />
                        <figcaption>
                            <span>SITEGUARD / PUBLIC SIGNALS</span>
                            <span>PASSIVE INSPECTION</span>
                        </figcaption>
                    </figure>
                </div>
                <div class="sg-hero-stats">
                    <div>
                        <strong>01</strong><span>Enter a public URL</span>
                    </div>
                    <div>
                        <strong>02</strong><span>Inspect public signals</span>
                    </div>
                    <div><strong>03</strong><span>Get clear actions</span></div>
                </div>
            </section>
            <section
                v-if="report"
                id="report"
                class="sg-report sg-wrap"
                aria-live="polite"
            >
                <div class="sg-report-head">
                    <div>
                        <span class="sg-kicker">SCAN REPORT</span>
                        <h2>{{ hostname(report.final_url) }}</h2>
                        <p>
                            {{ report.final_url }} · HTTP {{ report.status }} ·
                            {{ report.latency_ms }} ms
                        </p>
                    </div>
                    <div class="sg-grade">
                        <strong>{{ report.score.grade }}</strong
                        ><span>{{ report.score.value }}/100</span>
                    </div>
                </div>
                <div class="sg-report-actions">
                    <span
                        >{{ counts.pass }} passed · {{ counts.warn }} review ·
                        {{ counts.fail }} missing</span
                    ><button type="button" @click="saveReport">
                        Download JSON</button
                    ><button type="button" @click="printReport">
                        Print report
                    </button>
                </div>
                <p class="sg-caveat">
                    This score covers visible HTTPS and browser protections. It
                    does not prove a website is free of vulnerabilities.
                </p>
                <div class="sg-runtime-grid">
                    <article>
                        <span class="sg-kicker">RUNTIME SIGNAL</span>
                        <h3>PHP</h3>
                        <strong>{{
                            report.php.version || 'Not disclosed'
                        }}</strong>
                        <p>{{ report.php.reason }}</p>
                    </article>
                    <article>
                        <span class="sg-kicker">RUNTIME SIGNAL</span>
                        <h3>Node.js</h3>
                        <strong>{{
                            report.node.version || 'Not disclosed'
                        }}</strong>
                        <p>{{ report.node.reason }}</p>
                    </article>
                </div>
                <div class="sg-groups">
                    <section
                        v-for="(checks, group) in groups"
                        :key="group"
                        class="sg-group"
                    >
                        <h3>{{ group }}</h3>
                        <div
                            v-for="check in checks"
                            :key="check.id"
                            class="sg-check"
                            :class="'sg-' + check.state"
                        >
                            <span class="sg-state">{{ check.state }}</span>
                            <div>
                                <h4>{{ check.title }}</h4>
                                <p>{{ check.detail }}</p>
                                <p v-if="check.impact" class="sg-impact">
                                    <strong>Why it matters:</strong>
                                    {{ check.impact }}
                                </p>
                                <code v-if="check.evidence">{{
                                    check.evidence
                                }}</code>
                            </div>
                        </div>
                    </section>
                </div>
                <details class="sg-group sg-headers">
                    <summary>Observed security headers</summary>
                    <p>
                        Values from the final response. Cookie values and
                        unrelated headers are excluded.
                    </p>
                    <dl
                        v-if="Object.keys(report.observed_headers ?? {}).length"
                    >
                        <div
                            v-for="(value, name) in report.observed_headers"
                            :key="name"
                        >
                            <dt>{{ name }}</dt>
                            <dd>{{ value }}</dd>
                        </div>
                    </dl>
                    <p v-else>
                        No security headers from this list were observed.
                    </p>
                </details>
                <section v-if="report.fingerprints.length" class="sg-group">
                    <h3>Technology signals</h3>
                    <div
                        v-for="fingerprint in report.fingerprints"
                        :key="fingerprint.name"
                        class="sg-fingerprint"
                    >
                        <strong>{{ fingerprint.name }}</strong
                        ><span
                            >{{ fingerprint.category }} ·
                            {{ fingerprint.evidence }}</span
                        >
                    </div>
                </section>
                <section class="sg-group sg-help">
                    <h3>Need help with this report?</h3>
                    <p>
                        Send a request to the SiteGuard team. Your contact
                        details and message will be stored for follow-up.
                    </p>
                    <form @submit.prevent="sendContact">
                        <input
                            v-model="contact.website"
                            class="sg-honeypot"
                            type="text"
                            tabindex="-1"
                            autocomplete="off"
                            aria-hidden="true"
                        /><label
                            >Name<input
                                v-model="contact.name"
                                required
                                maxlength="80"
                                autocomplete="name" /></label
                        ><label
                            >Email<input
                                v-model="contact.email"
                                type="email"
                                required
                                maxlength="120"
                                autocomplete="email" /></label
                        ><label
                            >Website<input
                                v-model="contact.site"
                                maxlength="200" /></label
                        ><label
                            >Message<textarea
                                v-model="contact.message"
                                required
                                maxlength="1000"
                                rows="4"
                            ></textarea></label
                        ><button type="submit" :disabled="contactSending">
                            {{ contactSending ? 'Sending…' : 'Send request' }}</button
                        ><span role="status">{{ contactMessage }}</span>
                    </form>
                </section>
                <p class="sg-caveat">{{ report.limitations }}</p>
            </section>
            <section id="how-it-works" class="sg-about sg-wrap">
                <span class="sg-kicker">A CLEARER PICTURE</span>
                <h2>Security signals, explained.</h2>
                <div class="sg-feature-grid">
                    <article>
                        <span>01 / TRANSPORT</span>
                        <h3>Encrypted from the start</h3>
                        <p>
                            See HTTPS, certificate validity, TLS version,
                            redirects, and HSTS together.
                        </p>
                    </article>
                    <article>
                        <span>02 / BROWSER</span>
                        <h3>Protections that matter</h3>
                        <p>
                            Understand which headers limit injection,
                            clickjacking, referrer leakage, and browser
                            permissions.
                        </p>
                    </article>
                    <article>
                        <span>03 / EXPOSURE</span>
                        <h3>Know what is visible</h3>
                        <p>
                            Review disclosed versions and technology clues with
                            honest confidence and limitations.
                        </p>
                    </article>
                </div>
            </section>
        </main>
        <footer id="about" class="sg-footer sg-wrap">
            <span>© {{ site.name }} · Passive website inspection</span
            ><span
                >Evidence, not a guarantee. ·
                <Link href="/privacy">Privacy</Link></span
            >
        </footer>
    </div>
</template>
