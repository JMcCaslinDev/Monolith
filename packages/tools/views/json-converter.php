<div x-data="jsonTool()">
    <h1 class="text-2xl font-semibold">JSON Converter</h1>
    <p class="mt-1 text-sm text-muted">Runs in your browser — content stays local; button clicks are logged.</p>

    <div class="mt-6 flex flex-wrap gap-2">
        <button type="button" @click="format()" class="rounded bg-slate-800 px-3 py-1.5 text-sm hover:bg-slate-700">Format</button>
        <button type="button" @click="minify()" class="rounded bg-slate-800 px-3 py-1.5 text-sm hover:bg-slate-700">Minify</button>
        <button type="button" @click="validate()" class="rounded bg-slate-800 px-3 py-1.5 text-sm hover:bg-slate-700">Validate</button>
        <button type="button" @click="clear()" class="rounded bg-slate-800 px-3 py-1.5 text-sm hover:bg-slate-700">Clear</button>
    </div>

    <p class="mt-3 text-sm" :class="error ? 'text-red-400' : 'text-emerald-400'" x-text="status"></p>

    <textarea
        x-model="input"
        rows="16"
        class="mt-4 w-full rounded-lg border border-slate-800 bg-slate-900 p-3 font-mono text-sm text-slate-100 focus:border-indigo-500 focus:outline-none"
        placeholder='{"hello": "world"}'
        spellcheck="false"
    ></textarea>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('jsonTool', () => ({
        input: '',
        status: '',
        error: false,
        parse() {
            return JSON.parse(this.input);
        },
        format() {
            try {
                this.input = JSON.stringify(this.parse(), null, 2);
                this.error = false;
                this.status = 'Formatted.';
                window.logAction?.('json.format', { input_bytes: this.input.length });
            } catch (e) {
                this.error = true;
                this.status = e.message;
            }
        },
        minify() {
            try {
                this.input = JSON.stringify(this.parse());
                this.error = false;
                this.status = 'Minified.';
                window.logAction?.('json.minify', { input_bytes: this.input.length });
            } catch (e) {
                this.error = true;
                this.status = e.message;
            }
        },
        validate() {
            try {
                this.parse();
                this.error = false;
                this.status = 'Valid JSON.';
                window.logAction?.('json.validate', { input_bytes: this.input.length });
            } catch (e) {
                this.error = true;
                this.status = e.message;
            }
        },
        clear() {
            this.input = '';
            this.status = '';
            this.error = false;
            window.logAction?.('json.clear', {});
        },
    }));
});
</script>
