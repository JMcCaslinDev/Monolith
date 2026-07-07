<?php

// Shared controls + per-tool panels (x-show by activeTool slug)
?>
<div class="mt-4 space-y-4">
    <!-- Converters -->
    <div x-show="isActive('cron-parser')" class="space-y-3">
        <textarea x-model="input" rows="3" class="devtools-input" placeholder="0 9 * * 1-5"></textarea>
        <button type="button" @click="run('describe')" class="devtools-btn" :disabled="loading">Parse</button>
    </div>

    <div x-show="isActive('date')" class="space-y-3">
        <input type="text" x-model="extra.tz" class="devtools-input-sm" placeholder="Timezone (UTC)">
        <input type="text" x-model="extra.format" class="devtools-input-sm" placeholder="PHP date format">
        <textarea x-model="input" rows="3" class="devtools-input" placeholder="Unix timestamp or ISO date"></textarea>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="run('from_unix')" class="devtools-btn">From Unix</button>
            <button type="button" @click="run('to_unix')" class="devtools-btn">To Unix</button>
            <button type="button" @click="run('format')" class="devtools-btn">Format</button>
        </div>
    </div>

    <div x-show="isActive('json-table')" class="space-y-3">
        <textarea x-model="input" rows="8" class="devtools-input" placeholder='[{"id":1,"name":"a"}]'></textarea>
        <div class="flex gap-2">
            <button type="button" @click="run('html')" class="devtools-btn">To HTML table</button>
            <button type="button" @click="run('csv')" class="devtools-btn">To CSV</button>
        </div>
        <div x-show="output && isActive('json-table') && outputIsHtml" class="overflow-x-auto rounded border border-slate-800 p-3" x-html="output"></div>
    </div>

    <div x-show="isActive('json-yaml')" class="space-y-3">
        <textarea x-model="input" rows="10" class="devtools-input" placeholder="JSON or YAML"></textarea>
        <div class="flex gap-2">
            <button type="button" @click="run('to_yaml')" class="devtools-btn">JSON → YAML</button>
            <button type="button" @click="run('to_json')" class="devtools-btn">YAML → JSON</button>
        </div>
    </div>

    <div x-show="isActive('number-base')" class="space-y-3">
        <div class="flex gap-2">
            <input type="number" x-model.number="extra.from_base" min="2" max="36" class="devtools-input-sm w-24" placeholder="From">
            <input type="number" x-model.number="extra.to_base" min="2" max="36" class="devtools-input-sm w-24" placeholder="To">
        </div>
        <textarea x-model="input" rows="2" class="devtools-input" placeholder="Number"></textarea>
        <button type="button" @click="run('convert')" class="devtools-btn">Convert</button>
    </div>

    <!-- Encoders -->
    <div x-show="isActive('base64-text')" class="space-y-3">
        <textarea x-model="input" rows="6" class="devtools-input"></textarea>
        <div class="flex gap-2">
            <button type="button" @click="run('encode')" class="devtools-btn">Encode</button>
            <button type="button" @click="run('decode')" class="devtools-btn">Decode</button>
        </div>
    </div>

    <div x-show="isActive('base64-image')" class="space-y-3">
        <input type="file" accept="image/*" @change="onImageUpload($event)" class="text-sm">
        <textarea x-model="input" rows="4" class="devtools-input" placeholder="Or paste data:image/... base64"></textarea>
        <button type="button" @click="run('inspect')" class="devtools-btn">Inspect</button>
    </div>

    <div x-show="isActive('certificate')" class="space-y-3">
        <textarea x-model="input" rows="10" class="devtools-input" placeholder="PEM certificate"></textarea>
        <button type="button" @click="run('run')" class="devtools-btn">Parse</button>
    </div>

    <div x-show="isActive('gzip')" class="space-y-3">
        <textarea x-model="input" rows="6" class="devtools-input"></textarea>
        <div class="flex gap-2">
            <button type="button" @click="run('compress')" class="devtools-btn">Compress (base64)</button>
            <button type="button" @click="run('decompress')" class="devtools-btn">Decompress</button>
        </div>
    </div>

    <div x-show="isActive('html')" class="space-y-3">
        <textarea x-model="input" rows="6" class="devtools-input" placeholder="<div>Hello</div>"></textarea>
        <div class="flex gap-2">
            <button type="button" @click="run('encode')" class="devtools-btn">Encode entities</button>
            <button type="button" @click="run('decode')" class="devtools-btn">Decode entities</button>
        </div>
    </div>

    <div x-show="isActive('jwt')" class="space-y-3">
        <textarea x-model="input" rows="4" class="devtools-input" placeholder="eyJ..."></textarea>
        <button type="button" @click="run('run')" class="devtools-btn">Decode</button>
    </div>

    <div x-show="isActive('qr-code')" class="space-y-3">
        <textarea x-model="input" rows="3" class="devtools-input" placeholder="Text or URL"></textarea>
        <button type="button" @click="run('generate')" class="devtools-btn">Generate</button>
        <canvas x-ref="qrCanvas" class="mt-2 rounded border border-slate-700 bg-white"></canvas>
    </div>

    <div x-show="isActive('url')" class="space-y-3">
        <textarea x-model="input" rows="4" class="devtools-input"></textarea>
        <div class="flex gap-2">
            <button type="button" @click="run('encode')" class="devtools-btn">Encode</button>
            <button type="button" @click="run('decode')" class="devtools-btn">Decode</button>
        </div>
    </div>

    <!-- Formatters -->
    <div x-show="isActive('json')" class="space-y-3">
        <textarea x-model="input" rows="12" class="devtools-input font-mono" placeholder='{"hello":"world"}'></textarea>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="run('format')" class="devtools-btn">Format</button>
            <button type="button" @click="run('minify')" class="devtools-btn">Minify</button>
            <button type="button" @click="run('validate')" class="devtools-btn">Validate</button>
        </div>
    </div>

    <div x-show="isActive('sql')" class="space-y-3">
        <textarea x-model="input" rows="10" class="devtools-input font-mono" placeholder="SELECT * FROM users"></textarea>
        <div class="flex gap-2">
            <button type="button" @click="run('format')" class="devtools-btn">Format</button>
            <button type="button" @click="run('minify')" class="devtools-btn">Minify</button>
        </div>
    </div>

    <div x-show="isActive('xml')" class="space-y-3">
        <textarea x-model="input" rows="10" class="devtools-input font-mono" placeholder="<root/>"></textarea>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="run('format')" class="devtools-btn">Format</button>
            <button type="button" @click="run('minify')" class="devtools-btn">Minify</button>
            <button type="button" @click="run('validate')" class="devtools-btn">Validate</button>
        </div>
    </div>

    <!-- Generators -->
    <div x-show="isActive('hash')" class="space-y-3">
        <select x-model="extra.algorithm" class="devtools-input-sm">
            <option value="md5">MD5</option>
            <option value="sha1">SHA-1</option>
            <option value="sha256">SHA-256</option>
            <option value="sha512">SHA-512</option>
        </select>
        <textarea x-model="input" rows="4" class="devtools-input"></textarea>
        <button type="button" @click="run('run')" class="devtools-btn">Hash</button>
    </div>

    <div x-show="isActive('lorem-ipsum')" class="space-y-3">
        <input type="number" x-model.number="extra.paragraphs" min="1" max="20" class="devtools-input-sm w-32" placeholder="Paragraphs">
        <button type="button" @click="run('run')" class="devtools-btn">Generate</button>
    </div>

    <div x-show="isActive('password')" class="space-y-3">
        <input type="number" x-model.number="extra.length" min="8" max="128" class="devtools-input-sm w-32">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="extra.symbols"> Symbols</label>
        <button type="button" @click="run('run')" class="devtools-btn">Generate</button>
    </div>

    <div x-show="isActive('uuid')" class="mt-3">
        <button
            type="button"
            @click="run('run')"
            class="devtools-btn-icon"
            aria-label="Refresh UUIDs"
            title="Refresh"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                <path d="M21 3v5h-5"/>
                <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                <path d="M8 16H3v5"/>
            </svg>
        </button>
        <textarea
            x-model="output"
            rows="5"
            readonly
            class="devtools-input font-mono mt-4 max-w-xl resize-none"
            placeholder="Generating…"
        ></textarea>
    </div>

    <!-- Graphic -->
    <div x-show="isActive('color-blindness')" class="space-y-3">
        <select x-model="extra.mode" class="devtools-input-sm">
            <option value="protanopia">Protanopia</option>
            <option value="deuteranopia">Deuteranopia</option>
            <option value="tritanopia">Tritanopia</option>
        </select>
        <input type="file" accept="image/*" @change="onImageUpload($event)" class="text-sm">
        <img x-ref="cbImage" class="hidden" alt="">
        <button type="button" @click="run('simulate')" class="devtools-btn">Simulate</button>
        <canvas x-ref="cbCanvas" class="max-w-full rounded border border-slate-700"></canvas>
    </div>

    <div x-show="isActive('image-converter')" class="space-y-3">
        <div class="devtools-toolbar">
            <input type="file" accept="image/*" @change="onImageUpload($event)" class="text-sm">
            <select x-show="imageReady" x-model="extra.format" class="devtools-input-sm">
                <template x-for="fmt in convFormats" :key="fmt">
                    <option :value="fmt" x-text="imageFormatLabel(fmt)"></option>
                </template>
            </select>
            <button type="button" x-show="imageReady" @click="run('convert')" class="devtools-btn" :disabled="!canConvertImage()">Convert</button>
            <button
                type="button"
                x-show="imageReady"
                @click="downloadConverted()"
                class="devtools-btn-icon"
                :disabled="!output"
                aria-label="Download converted image"
                title="Download"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
            </button>
        </div>
        <img x-show="imageReady" :src="output || convSource" class="devtools-img-preview" alt="Preview">
        <img x-ref="convImage" class="hidden" alt="">
        <canvas x-ref="convCanvas" class="hidden"></canvas>
    </div>

    <!-- Testers -->
    <div x-show="isActive('jsonpath')" class="space-y-3">
        <input type="text" x-model="extra.path" class="devtools-input" placeholder="$.store.book[*].title">
        <textarea x-model="input" rows="8" class="devtools-input font-mono"></textarea>
        <button type="button" @click="run('run')" class="devtools-btn">Evaluate</button>
    </div>

    <div x-show="isActive('regex')" class="space-y-3">
        <input type="text" x-model="extra.pattern" class="devtools-input" placeholder="Pattern (no delimiters)">
        <input type="text" x-model="extra.flags" class="devtools-input-sm w-24" placeholder="Flags (i,m)">
        <textarea x-model="input" rows="6" class="devtools-input"></textarea>
        <button type="button" @click="run('run')" class="devtools-btn">Test</button>
    </div>

    <div x-show="isActive('xml-tester')" class="space-y-3">
        <textarea x-model="input" rows="8" class="devtools-input font-mono"></textarea>
        <input type="text" x-model="extra.xpath" class="devtools-input" placeholder="XPath (optional)">
        <div class="flex gap-2">
            <button type="button" @click="run('validate')" class="devtools-btn">Validate</button>
            <button type="button" @click="run('xpath')" class="devtools-btn">XPath</button>
        </div>
    </div>

    <!-- Text -->
    <div x-show="isActive('escape-unescape')" class="space-y-3">
        <select x-model="extra.mode" class="devtools-input-sm">
            <option value="json">JSON</option>
            <option value="html">HTML</option>
            <option value="url">URL</option>
            <option value="sql">SQL</option>
        </select>
        <textarea x-model="input" rows="6" class="devtools-input"></textarea>
        <div class="flex gap-2">
            <button type="button" @click="run('escape')" class="devtools-btn">Escape</button>
            <button type="button" @click="run('unescape')" class="devtools-btn">Unescape</button>
        </div>
    </div>

    <div x-show="isActive('list-compare')" class="space-y-3">
        <label class="text-sm text-muted">List A</label>
        <textarea x-model="input" rows="6" class="devtools-input" placeholder="One item per line"></textarea>
        <label class="text-sm text-muted">List B</label>
        <textarea x-model="inputB" rows="6" class="devtools-input"></textarea>
        <button type="button" @click="run('run')" class="devtools-btn">Compare</button>
    </div>

    <div x-show="isActive('markdown-preview')" class="space-y-3">
        <textarea x-model="input" rows="8" class="devtools-input font-mono" placeholder="# Heading"></textarea>
        <button type="button" @click="run('run')" class="devtools-btn">Preview</button>
        <div x-show="output && isActive('markdown-preview') && outputIsHtml" class="rounded border border-slate-800 p-4" x-html="output"></div>
    </div>

    <div x-show="isActive('text-analyzer')" class="space-y-3">
        <textarea x-model="input" rows="6" class="devtools-input"></textarea>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="run('stats')" class="devtools-btn">Stats</button>
            <button type="button" @click="run('upper')" class="devtools-btn">UPPER</button>
            <button type="button" @click="run('lower')" class="devtools-btn">lower</button>
            <button type="button" @click="run('title')" class="devtools-btn">Title Case</button>
            <button type="button" @click="run('trim')" class="devtools-btn">Trim</button>
        </div>
    </div>

    <div x-show="isActive('text-compare')" class="space-y-3">
        <textarea x-model="input" rows="6" class="devtools-input" placeholder="Text A"></textarea>
        <textarea x-model="inputB" rows="6" class="devtools-input" placeholder="Text B"></textarea>
        <button type="button" @click="run('run')" class="devtools-btn">Diff</button>
    </div>

    <!-- Output (text tools) -->
    <textarea
        x-show="(!outputIsHtml && !isActive('image-converter') && !isActive('uuid') && (output || isActive('lorem-ipsum')))"
        x-model="output"
        :rows="outputRows()"
        readonly
        class="devtools-input font-mono"
        placeholder="Generated text appears here…"
    ></textarea>
</div>

<style>
.devtools-input { width: 100%; border-radius: 0.5rem; border: 1px solid rgb(30 41 59); background: rgb(15 23 42); padding: 0.75rem; font-size: 0.875rem; color: rgb(241 245 249); }
.devtools-input:focus { border-color: rgb(99 102 241); outline: none; }
.devtools-input-sm { border-radius: 0.25rem; border: 1px solid rgb(30 41 59); background: rgb(15 23 42); padding: 0.25rem 0.5rem; font-size: 0.875rem; color: rgb(241 245 249); }
.devtools-btn { border-radius: 0.25rem; background: rgb(30 41 59); padding: 0.375rem 0.75rem; font-size: 0.875rem; }
.devtools-btn:hover { background: rgb(51 65 85); }
.devtools-btn:disabled { opacity: 0.5; }
.devtools-btn-icon { display: inline-flex; align-items: center; justify-content: center; border-radius: 0.375rem; background: rgb(30 41 59); padding: 0.5rem; color: rgb(203 213 225); }
.devtools-btn-icon:hover { background: rgb(51 65 85); color: rgb(241 245 249); }
.devtools-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }
.devtools-img-preview { display: block; max-width: 36rem; max-height: 20rem; width: auto; height: auto; object-fit: contain; border-radius: 0.5rem; border: 1px solid rgb(30 41 59); }
[x-cloak] { display: none !important; }
</style>
