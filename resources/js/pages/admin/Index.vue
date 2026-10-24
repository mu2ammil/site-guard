<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { reactive } from 'vue';

type Scan = {
    id: number;
    created_at: string;
    target: string;
    host: string;
    ip_address: string;
    user_agent: string | null;
    user_id: number | null;
    status: string;
    http_status: number | null;
    grade: string | null;
    error: string | null;
};
type Event = {
    id: number;
    created_at: string;
    type: string;
    ip_address: string;
    user_agent: string | null;
    user_id: number | null;
    path: string;
    details: Record<string, unknown> | null;
};
type Contact = {
    id: number;
    created_at: string;
    name: string;
    email: string;
    site: string | null;
    message: string;
    ip_address: string;
};
type ActiveIp = { ip_address: string; events: number; flagged: number };
const props = defineProps<{
    scans: {
        data: Scan[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { host?: string; ip?: string; status?: string };
    events: {
        data: Event[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    contacts: {
        data: Contact[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    activeIps: ActiveIp[];
    counts: {
        total: number;
        today: number;
        failed: number;
        blocked: number;
        failed_logins: number;
        visitors: number;
    };
    settings: {
        scan_enabled: string;
        scan_limit: string;
        retention_days: string;
        site_name: string;
        site_tagline: string;
        contact_email: string;
    };
}>();
const filters = reactive({
    host: props.filters.host ?? '',
    ip: props.filters.ip ?? '',
    status: props.filters.status ?? '',
});
function search() {
    router.get('/admin', filters, { preserveState: true });
}
const form = useForm({
    scan_enabled: props.settings.scan_enabled === '1',
    scan_limit: Number(props.settings.scan_limit),
    retention_days: Number(props.settings.retention_days),
    site_name: props.settings.site_name,
    site_tagline: props.settings.site_tagline,
    contact_email: props.settings.contact_email,
});
function save() {
    form.put('/admin/settings');
}
</script>

<template>
    <Head title="Admin · SiteGuard" />
    <div class="sa-page">
        <header class="sa-header">
            <Link href="/"><AppLogoIcon />SiteGuard</Link
            ><span>ADMIN CONSOLE</span>
        </header>
        <main class="sa-wrap">
            <div class="sa-title">
                <div>
                    <span>OPERATIONS / OVERVIEW</span>
                    <h1>Scan activity</h1>
                    <p>
                        Every completed and failed scan is recorded with its
                        requester and target.
                    </p>
                </div>
                <Link href="/" class="inline-flex items-center gap-1.5">
                    Open scanner <ArrowUpRight :size="16" aria-hidden="true" />
                </Link>
            </div>
            <div class="sa-stats">
                <article>
                    <span>Total scans</span><strong>{{ counts.total }}</strong>
                </article>
                <article>
                    <span>Today</span><strong>{{ counts.today }}</strong>
                </article>
                <article>
                    <span>Failed</span><strong>{{ counts.failed }}</strong>
                </article>
                <article>
                    <span>Blocked in 24h</span
                    ><strong>{{ counts.blocked }}</strong>
                </article>
                <article>
                    <span>Failed sign-ins in 24h</span
                    ><strong>{{ counts.failed_logins }}</strong>
                </article>
                <article>
                    <span>Unique IPs · 24h</span
                    ><strong>{{ counts.visitors }}</strong>
                </article>
            </div>
            <section class="sa-card">
                <div class="sa-section-title">
                    <h2>Active requester IPs</h2>
                    <span>Last 24 hours</span>
                </div>
                <div class="sa-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>IP</th>
                                <th>Events</th>
                                <th>Blocked or failed sign-ins</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ip in activeIps" :key="ip.ip_address">
                                <td>
                                    <code>{{ ip.ip_address }}</code>
                                </td>
                                <td>{{ ip.events }}</td>
                                <td>{{ ip.flagged }}</td>
                            </tr>
                            <tr v-if="!activeIps.length">
                                <td colspan="3">No activity yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="sa-card">
                <div class="sa-section-title">
                    <h2>Recent scans</h2>
                    <span>Newest first</span>
                </div>
                <form class="sa-search" @submit.prevent="search">
                    <input
                        v-model="filters.host"
                        placeholder="Filter domain"
                        aria-label="Filter domain"
                    /><input
                        v-model="filters.ip"
                        placeholder="Filter IP"
                        aria-label="Filter IP"
                    /><select
                        v-model="filters.status"
                        aria-label="Filter status"
                    >
                        <option value="">All statuses</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="started">Started</option></select
                    ><button type="submit">Filter</button>
                </form>
                <div class="sa-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Target</th>
                                <th>Requester</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="scan in scans.data" :key="scan.id">
                                <td>
                                    {{
                                        new Date(
                                            scan.created_at,
                                        ).toLocaleString()
                                    }}
                                </td>
                                <td class="sa-target">{{ scan.host }}</td>
                                <td>
                                    <code>{{ scan.ip_address }}</code
                                    ><small v-if="scan.user_id"
                                        >User #{{ scan.user_id }}</small
                                    >
                                </td>
                                <td>
                                    <span
                                        class="sa-pill"
                                        :class="'sa-' + scan.status"
                                        >{{ scan.status }}</span
                                    >
                                </td>
                                <td>{{ scan.grade || '—' }}</td>
                                <td>
                                    <Link
                                        :href="'/admin/scans/' + scan.id"
                                        class="inline-flex items-center gap-1.5"
                                        >Details
                                        <ArrowRight :size="15" aria-hidden="true"
                                    /></Link>
                                </td>
                            </tr>
                            <tr v-if="!scans.data.length">
                                <td colspan="6">No scans match this filter.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav class="sa-pages" aria-label="Pagination">
                    <template v-for="link in scans.links" :key="link.label"
                        ><Link
                            v-if="link.url"
                            :href="link.url"
                            :class="{ active: link.active }"
                            v-html="link.label"
                    /></template>
                </nav>
            </section>
            <section class="sa-card">
                <div class="sa-section-title">
                    <h2>Activity log</h2>
                    <span>All recorded events</span>
                </div>
                <div class="sa-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event</th>
                                <th>Requester</th>
                                <th>Path</th>
                                <th>User agent</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="event in events.data" :key="event.id">
                                <td>
                                    {{
                                        new Date(
                                            event.created_at,
                                        ).toLocaleString()
                                    }}
                                </td>
                                <td>{{ event.type }}</td>
                                <td>
                                    <code>{{ event.ip_address }}</code
                                    ><small v-if="event.user_id"
                                        >User #{{ event.user_id }}</small
                                    >
                                </td>
                                <td>{{ event.path }}</td>
                                <td>{{ event.user_agent || 'Unknown' }}</td>
                                <td>
                                    <code>{{
                                        event.details
                                            ? JSON.stringify(event.details)
                                            : '—'
                                    }}</code>
                                </td>
                            </tr>
                            <tr v-if="!events.data.length">
                                <td colspan="6">No events recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav class="sa-pages" aria-label="Activity log pages">
                    <template v-for="link in events.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="{ active: link.active }"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </section>
            <section class="sa-card">
                <div class="sa-section-title">
                    <h2>Help requests</h2>
                    <span>All requests</span>
                </div>
                <div class="sa-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>From</th>
                                <th>Site</th>
                                <th>Message</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="contact in contacts.data"
                                :key="contact.id"
                            >
                                <td>
                                    {{
                                        new Date(
                                            contact.created_at,
                                        ).toLocaleString()
                                    }}
                                </td>
                                <td>
                                    {{ contact.name
                                    }}<small>{{ contact.email }}</small>
                                </td>
                                <td>{{ contact.site || '—' }}</td>
                                <td>{{ contact.message }}</td>
                                <td>
                                    <code>{{ contact.ip_address }}</code>
                                </td>
                            </tr>
                            <tr v-if="!contacts.data.length">
                                <td colspan="5">No help requests yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav class="sa-pages" aria-label="Help request pages">
                    <template v-for="link in contacts.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="{ active: link.active }"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </section>
            <section class="sa-card sa-settings">
                <div class="sa-section-title">
                    <h2>Site settings</h2>
                    <span>Applied to new requests</span>
                </div>
                <form @submit.prevent="save">
                    <label
                        ><input v-model="form.scan_enabled" type="checkbox" />
                        Allow public scans</label
                    ><label
                        >Scans per IP, per 10 minutes<input
                            v-model.number="form.scan_limit"
                            type="number"
                            min="1"
                            max="100" /></label
                    ><label
                        >Log retention in days<input
                            v-model.number="form.retention_days"
                            type="number"
                            min="1"
                            max="3650" /></label
                    ><label
                        >Site name<input
                            v-model="form.site_name"
                            type="text"
                            maxlength="60" /></label
                    ><label
                        >Homepage headline<input
                            v-model="form.site_tagline"
                            type="text"
                            maxlength="160" /></label
                    ><label
                        >Help request email<input
                            v-model="form.contact_email"
                            type="email"
                            maxlength="120" /></label
                    ><button :disabled="form.processing" type="submit">
                        Save settings</button
                    ><span v-if="form.recentlySuccessful">Saved.</span>
                    <p v-if="Object.keys(form.errors).length">
                        {{ Object.values(form.errors)[0] }}
                    </p>
                </form>
            </section>
        </main>
    </div>
</template>
