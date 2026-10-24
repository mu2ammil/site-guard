<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
type Site = {
    id: number;
    domain: string;
    verification_token: string;
    verified_at: string | null;
    alerts_enabled: boolean;
};
defineProps<{ sites: Site[] }>();
const form = useForm({ domain: '' });
function add() {
    form.post('/monitored-sites', { onSuccess: () => form.reset() });
}
function verify(site: Site) {
    router.post('/monitored-sites/' + site.id + '/verify');
}
function toggle(site: Site) {
    router.put('/monitored-sites/' + site.id, {
        alerts_enabled: !site.alerts_enabled,
    });
}
function remove(site: Site) {
    if (confirm('Remove ' + site.domain + ' from monitoring?'))
        router.delete('/monitored-sites/' + site.id);
}
</script>
<template>
    <Head title="My monitored sites" />
    <div class="sm-page">
        <header class="sm-header">
            <Link href="/"><AppLogoIcon />SiteGuard</Link
            ><Link href="/dashboard">Dashboard</Link>
        </header>
        <main class="sm-wrap">
            <span class="sg-kicker">OWNER ALERTS</span>
            <h1>Know when your site is scanned.</h1>
            <p class="sm-intro">
                Prove ownership with a DNS TXT record. Once verified, we email
                you when someone else scans that exact domain.
            </p>
            <form class="sm-add" @submit.prevent="add">
                <label for="domain">Domain</label>
                <div>
                    <input
                        id="domain"
                        v-model="form.domain"
                        placeholder="example.com"
                        required
                    /><button :disabled="form.processing">Add site</button>
                </div>
                <p v-if="form.errors.domain" role="alert">
                    {{ form.errors.domain }}
                </p>
            </form>
            <p
                v-if="$page.props.errors?.verification"
                class="sm-error"
                role="alert"
            >
                {{ $page.props.errors.verification }}
            </p>
            <section v-for="site in sites" :key="site.id" class="sm-site">
                <div class="sm-site-head">
                    <div>
                        <h2>{{ site.domain }}</h2>
                        <span
                            :class="
                                site.verified_at ? 'sm-verified' : 'sm-pending'
                            "
                            >{{
                                site.verified_at
                                    ? 'Verified'
                                    : 'Awaiting DNS verification'
                            }}</span
                        >
                    </div>
                    <button type="button" @click="remove(site)">Remove</button>
                </div>
                <template v-if="!site.verified_at"
                    ><p>
                        Add this TXT record to your DNS, then check
                        verification:
                    </p>
                    <dl>
                        <dt>Host</dt>
                        <dd>
                            <code>_siteguard.{{ site.domain }}</code>
                        </dd>
                        <dt>Value</dt>
                        <dd>
                            <code>{{ site.verification_token }}</code>
                        </dd>
                    </dl>
                    <button
                        class="sm-action"
                        type="button"
                        @click="verify(site)"
                    >
                        Check DNS record
                    </button></template
                >
                <div v-else class="sm-alerts">
                    <span>Email me when another visitor scans this domain</span
                    ><button
                        class="sm-action"
                        type="button"
                        @click="toggle(site)"
                    >
                        {{ site.alerts_enabled ? 'Alerts on' : 'Alerts off' }}
                    </button>
                </div>
            </section>
            <p v-if="!sites.length" class="sm-empty">
                No sites added yet. Add a domain above to start verification.
            </p>
        </main>
    </div>
</template>
