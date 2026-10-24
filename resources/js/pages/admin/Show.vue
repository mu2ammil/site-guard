<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
defineProps<{
    scan: {
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
        report: unknown;
    };
}>();
</script>
<template>
    <Head title="Scan details · SiteGuard" />
    <div class="sa-page">
        <header class="sa-header">
            <Link href="/"><AppLogoIcon />SiteGuard</Link
            ><span>ADMIN CONSOLE</span>
        </header>
        <main class="sa-wrap">
            <div class="sa-title">
                <div>
                    <span>OPERATIONS / SCAN #{{ scan.id }}</span>
                    <h1>Scan details</h1>
                    <p>{{ scan.host }}</p>
                </div>
                <Link href="/admin" class="inline-flex items-center gap-1.5">
                    <ArrowLeft :size="16" aria-hidden="true" /> All scans
                </Link>
            </div>
            <section class="sa-card">
                <dl class="sa-details">
                    <div>
                        <dt>Requested at</dt>
                        <dd>
                            {{ new Date(scan.created_at).toLocaleString() }}
                        </dd>
                    </div>
                    <div>
                        <dt>Target</dt>
                        <dd>{{ scan.target }}</dd>
                    </div>
                    <div>
                        <dt>IP address</dt>
                        <dd>{{ scan.ip_address }}</dd>
                    </div>
                    <div>
                        <dt>User</dt>
                        <dd>{{ scan.user_id || 'Guest' }}</dd>
                    </div>
                    <div>
                        <dt>User agent</dt>
                        <dd>{{ scan.user_agent || 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>
                            {{ scan.status }} · HTTP
                            {{ scan.http_status || '—' }} · Grade
                            {{ scan.grade || '—' }}
                        </dd>
                    </div>
                    <div v-if="scan.error">
                        <dt>Error</dt>
                        <dd>{{ scan.error }}</dd>
                    </div>
                </dl>
            </section>
            <section v-if="scan.report" class="sa-card">
                <h2>Full report</h2>
                <pre>{{ JSON.stringify(scan.report, null, 2) }}</pre>
            </section>
        </main>
    </div>
</template>
