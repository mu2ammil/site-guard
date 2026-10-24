<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
defineProps<{ retentionDays: string; contactEmail: string }>();
</script>
<template>
    <Head title="Privacy" />
    <div class="sm-page">
        <header class="sm-header">
            <Link href="/"><AppLogoIcon />SiteGuard</Link
            ><Link href="/">Scanner</Link>
        </header>
        <main class="sm-wrap">
            <span class="sg-kicker">SITEGUARD</span>
            <h1>Privacy</h1>
            <p class="sm-intro">
                SiteGuard performs passive requests to public websites. We keep
                records to show scan activity, prevent abuse, and help site
                owners understand when their domain is scanned.
            </p>
            <section class="sm-site">
                <h2>What we keep</h2>
                <p>
                    Scanned URLs and results, request time, requester IP
                    address, user agent, account ID when signed in, site
                    verification records, and any contact request you submit.
                    Reports contain public response headers and technology
                    clues; response bodies and cookie values are not saved.
                </p>
            </section>
            <section class="sm-site">
                <h2>Alerts</h2>
                <p>
                    A signed-in owner can verify control of an exact domain with
                    a DNS TXT record and enable email alerts. Alerts identify
                    the scanned URL and report grade. Owners can turn them off
                    or remove the site at any time.
                </p>
            </section>
            <section class="sm-site">
                <h2>Retention</h2>
                <p>
                    Scan activity, logs, and contact requests are scheduled for
                    deletion after {{ retentionDays }} days. Account and domain
                    verification records remain until removed. If you need help,
                    contact {{ contactEmail || 'the SiteGuard operator' }}.
                </p>
            </section>
        </main>
    </div>
</template>
