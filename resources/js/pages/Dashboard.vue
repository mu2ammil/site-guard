<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';

type Scan = {
    id: number;
    host: string;
    target: string;
    grade: string | null;
    status: string;
    created_at: string;
};
type Site = {
    id: number;
    domain: string;
    verified_at: string | null;
    alerts_enabled: boolean;
};
const props = defineProps<{ scans: Scan[]; sites: Site[]; isAdmin: boolean }>();
const verified = computed(
    () => props.sites.filter((site) => site.verified_at).length,
);
</script>

<template>
    <Head title="Dashboard" />
    <main class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="text-xs font-bold tracking-[.2em] text-neutral-600 dark:text-neutral-400"
                >
                    YOUR WORKSPACE
                </p>
                <h1 class="mt-2 text-3xl font-bold">Dashboard</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Your recent checks and verified site alerts.
                </p>
            </div>
            <Link
                href="/"
                class="rounded-md bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-neutral-700 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-300"
                >Scan a website</Link
            >
        </header>
        <div class="grid gap-4 sm:grid-cols-3">
            <article class="rounded-xl border p-5">
                <p class="text-sm text-muted-foreground">Recent scans</p>
                <strong class="mt-2 block text-3xl">{{ scans.length }}</strong>
            </article>
            <article class="rounded-xl border p-5">
                <p class="text-sm text-muted-foreground">Verified sites</p>
                <strong class="mt-2 block text-3xl">{{ verified }}</strong>
            </article>
            <article class="rounded-xl border p-5">
                <p class="text-sm text-muted-foreground">Alerts enabled</p>
                <strong class="mt-2 block text-3xl">{{
                    sites.filter(
                        (site) => site.verified_at && site.alerts_enabled,
                    ).length
                }}</strong>
            </article>
        </div>
        <section class="rounded-xl border p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold">Your scans</h2>
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-800 dark:text-neutral-200"
                    >New scan <ArrowRight class="h-4 w-4" aria-hidden="true" /></Link
                >
            </div>
            <div v-if="scans.length" class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[550px] text-left text-sm">
                    <thead class="border-b text-muted-foreground">
                        <tr>
                            <th class="py-3">Website</th>
                            <th>When</th>
                            <th>Status</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="scan in scans"
                            :key="scan.id"
                            class="border-b last:border-0"
                        >
                            <td
                                class="max-w-64 truncate py-3"
                                :title="scan.target"
                            >
                                {{ scan.host }}
                            </td>
                            <td>
                                {{ new Date(scan.created_at).toLocaleString() }}
                            </td>
                            <td class="capitalize">{{ scan.status }}</td>
                            <td class="font-bold">{{ scan.grade ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="mt-4 text-sm text-muted-foreground">
                No scans on this account yet. Run one while signed in to see it
                here.
            </p>
        </section>
        <section class="rounded-xl border p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold">Site owner alerts</h2>
                <Link
                    href="/monitored-sites"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-800 dark:text-neutral-200"
                    >Manage sites <ArrowRight class="h-4 w-4" aria-hidden="true" /></Link
                >
            </div>
            <div v-if="sites.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                <article
                    v-for="site in sites"
                    :key="site.id"
                    class="rounded-lg border p-4"
                >
                    <h3 class="font-semibold">{{ site.domain }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            site.verified_at
                                ? site.alerts_enabled
                                    ? 'Verified · Alerts on'
                                    : 'Verified · Alerts paused'
                                : 'Awaiting DNS verification'
                        }}
                    </p>
                </article>
            </div>
            <p v-else class="mt-4 text-sm text-muted-foreground">
                Add and verify a domain to receive an email when someone else
                scans it.
            </p>
        </section>
        <Link
            v-if="isAdmin"
            href="/admin"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-800 dark:text-neutral-200"
            >Open admin console <ArrowRight class="h-4 w-4" aria-hidden="true" /></Link
        >
    </main>
</template>
