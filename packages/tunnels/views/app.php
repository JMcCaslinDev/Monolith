<?php

/** @var string $hubUrl */
/** @var string $appUrl */
/** @var string $downloadCommand */
/** @var string $scriptUrl */
$init = [
    'hubUrl' => $hubUrl,
    'appUrl' => $appUrl,
    'downloadCommand' => $downloadCommand,
    'scriptUrl' => $scriptUrl,
];
?>
<style>
.tunnels-shell { display: flex; height: calc(100vh - 3.5rem); overflow: hidden; }
.tunnels-sidebar { width: 20rem; flex-shrink: 0; border-right: 1px solid rgb(229 231 235); overflow-y: auto; }
.dark .tunnels-sidebar { border-color: rgb(55 65 81); }
.tunnels-main { flex: 1; overflow-y: auto; min-width: 0; }
.tunnels-method { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em; padding: 0.125rem 0.375rem; border-radius: 0.25rem; }
</style>

<div
    class="tunnels-shell page-bg"
    x-data="tunnelsApp"
    data-tunnels-init="<?= htmlspecialchars(json_encode($init, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES) ?>"
    x-cloak
>
    <aside class="tunnels-sidebar p-4 space-y-4">
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Tunnels</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Expose localhost with a public URL.</p>
        </div>

        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-3 space-y-2">
            <p class="text-xs font-medium text-amber-900 dark:text-amber-200">Get the client</p>
            <p class="text-[11px] text-amber-800 dark:text-amber-300/80">
                <a href="https://nodejs.org" class="underline" target="_blank" rel="noopener">Node.js 21+</a> required. No repo or npm.
            </p>
            <code class="block text-[10px] font-mono bg-white dark:bg-gray-900 rounded p-2 overflow-x-auto whitespace-pre-wrap break-all" x-text="downloadCommand"></code>
            <div class="flex gap-3 text-[11px]">
                <button type="button" @click="copy(downloadCommand)" class="text-amber-700 dark:text-amber-300 underline">Copy curl</button>
                <a :href="scriptUrl" class="text-amber-700 dark:text-amber-300 underline" download="tunnel-client.mjs">Download file</a>
            </div>
        </div>

        <template x-if="canCreate">
            <form @submit.prevent="createTunnel" class="space-y-2 rounded-lg border border-gray-200 dark:border-gray-700 p-3 bg-white/50 dark:bg-gray-900/50">
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">New tunnel</p>
                <input type="text" x-model="form.label" placeholder="Label (optional)" class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                <div class="flex gap-2">
                    <input type="number" x-model.number="form.local_port" min="1" max="65535" class="w-24 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm" title="Local port">
                    <select x-model.number="form.ttl_minutes" class="flex-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                        <option value="60">1 hour</option>
                        <option value="240">4 hours</option>
                        <option value="480">8 hours</option>
                        <option value="1440">24 hours</option>
                    </select>
                </div>
                <button type="submit" :disabled="creating" class="w-full rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                    <span x-text="creating ? 'Creating…' : 'Create tunnel'"></span>
                </button>
            </form>
        </template>

        <div class="space-y-1">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Your tunnels</p>
            <template x-if="tunnels.length === 0">
                <p class="text-sm text-gray-400 dark:text-gray-500 py-2">No tunnels yet.</p>
            </template>
            <template x-for="t in tunnels" :key="t.id">
                <button
                    type="button"
                    @click="selectTunnel(t.id)"
                    class="w-full text-left rounded-lg px-3 py-2 text-sm transition"
                    :class="selectedId === t.id ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-100' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-800 dark:text-gray-200'"
                >
                    <span class="font-mono text-xs" x-text="t.slug"></span>
                    <span class="ml-2 text-xs" :class="statusClass(t.status)" x-text="t.status"></span>
                    <span class="block text-xs text-gray-500 truncate" x-text="t.label || ('port ' + t.local_port)"></span>
                </button>
            </template>
        </div>
    </aside>

    <div class="tunnels-main p-6 space-y-6">
        <template x-if="!selected">
            <div class="text-center py-16 text-gray-500 dark:text-gray-400">
                <p class="text-lg">Select a tunnel or create one.</p>
                <p class="text-sm mt-2 max-w-md mx-auto">Download the client from the sidebar, then run the connect command after you pick a tunnel.</p>
            </div>
        </template>

        <template x-if="selected">
            <div class="space-y-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 font-mono" x-text="selected.slug"></h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" x-text="selected.label || 'No label'"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-1 rounded-full" :class="statusClass(selected.status)" x-text="selected.status"></span>
                        <template x-if="canManage && selected.status !== 'stopped' && selected.status !== 'expired'">
                            <button type="button" @click="stopTunnel" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400">Stop</button>
                        </template>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Public URL</p>
                        <div class="flex items-center gap-2 mt-1">
                            <code class="text-sm font-mono text-indigo-600 dark:text-indigo-400 break-all" x-text="selected.public_url"></code>
                            <button type="button" @click="copy(selected.public_url)" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 shrink-0">Copy</button>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Forwarding to</p>
                        <code class="text-sm font-mono mt-1 block" x-text="'http://127.0.0.1:' + selected.local_port"></code>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-4 space-y-3">
                    <div x-show="hubIsLocal">
                        <p class="text-sm font-medium text-amber-900 dark:text-amber-200">1. Start the tunnel hub (local dev only)</p>
                        <code class="block mt-2 text-xs font-mono bg-white dark:bg-gray-900 rounded p-2 overflow-x-auto">pnpm tunnel:hub</code>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-amber-900 dark:text-amber-200" x-text="hubIsLocal ? '2. Connect this tunnel (keep terminal open)' : 'Connect this tunnel (keep terminal open)'"></p>
                        <div class="flex items-start gap-2 mt-2">
                            <code class="flex-1 text-xs font-mono bg-white dark:bg-gray-900 rounded p-2 overflow-x-auto whitespace-pre-wrap break-all" x-text="clientCommand"></code>
                            <button type="button" @click="copy(clientCommand)" class="text-xs text-amber-700 dark:text-amber-300 shrink-0">Copy</button>
                        </div>
                    </div>
                    <p class="text-xs text-amber-800 dark:text-amber-300/80">
                        Run from the folder where you saved <code class="font-mono">tunnel-client.mjs</code>.
                        Requests forward only while the client is connected.
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Requests</h3>
                        <span class="text-xs text-gray-500" x-text="requests.length + ' captured'"></span>
                    </div>
                    <template x-if="requests.length === 0">
                        <p class="text-sm text-gray-400 py-8 text-center border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                            Waiting for requests… Send traffic to the public URL.
                        </p>
                    </template>
                    <div class="space-y-2">
                        <template x-for="req in requests.slice().reverse()" :key="req.id">
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <button
                                    type="button"
                                    @click="toggleRequest(req.id)"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                >
                                    <span class="tunnels-method" :class="methodClass(req.request_method)" x-text="req.request_method"></span>
                                    <span class="font-mono text-sm truncate flex-1" x-text="req.request_path + (req.query_string ? '?' + req.query_string : '')"></span>
                                    <span class="text-xs text-gray-500" x-text="req.response_status || (req.error_message ? 'err' : '—')"></span>
                                    <span class="text-xs text-gray-400" x-text="req.duration_ms ? req.duration_ms + 'ms' : ''"></span>
                                    <span class="text-xs" :class="req.forwarded ? 'text-green-600' : 'text-amber-600'" x-text="req.forwarded ? 'fwd' : 'log'"></span>
                                </button>
                                <div x-show="openRequestId === req.id" class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 text-xs space-y-3 bg-gray-50 dark:bg-gray-900/50">
                                    <div class="grid grid-cols-2 gap-4 text-gray-500">
                                        <span>From: <span class="text-gray-800 dark:text-gray-200" x-text="req.client_ip || '—'"></span></span>
                                        <span>At: <span class="text-gray-800 dark:text-gray-200" x-text="req.created_at"></span></span>
                                    </div>
                                    <template x-if="req.error_message">
                                        <p class="text-red-600 dark:text-red-400" x-text="req.error_message"></p>
                                    </template>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-gray-400 mb-1">Request headers</p>
                                        <pre class="font-mono text-[11px] overflow-x-auto whitespace-pre-wrap" x-text="formatJson(req.request_headers)"></pre>
                                    </div>
                                    <template x-if="req.request_body">
                                        <div>
                                            <p class="font-medium text-gray-600 dark:text-gray-400 mb-1">Request body (<span x-text="req.request_body_bytes"></span> bytes)</p>
                                            <pre class="font-mono text-[11px] overflow-x-auto whitespace-pre-wrap max-h-48" x-text="req.request_body"></pre>
                                        </div>
                                    </template>
                                    <template x-if="req.response_status">
                                        <div>
                                            <p class="font-medium text-gray-600 dark:text-gray-400 mb-1">Response <span x-text="req.response_status"></span></p>
                                            <pre class="font-mono text-[11px] overflow-x-auto whitespace-pre-wrap" x-text="formatJson(req.response_headers)"></pre>
                                        </div>
                                    </template>
                                    <template x-if="req.response_body">
                                        <div>
                                            <p class="font-medium text-gray-600 dark:text-gray-400 mb-1">Response body (<span x-text="req.response_body_bytes"></span> bytes)</p>
                                            <pre class="font-mono text-[11px] overflow-x-auto whitespace-pre-wrap max-h-48" x-text="req.response_body"></pre>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
