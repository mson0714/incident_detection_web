import { onScopeDispose, reactive } from 'vue';

export function useUrbanResource(initialUrl, validate = (payload) => Array.isArray(payload?.data)) {
    const state = reactive({ data: null, loading: false, error: false, updatedAt: null });
    let controller;
    let generation = 0;

    async function load(url = initialUrl, { clear = false } = {}) {
        controller?.abort();
        const request = new AbortController();
        controller = request;
        const current = ++generation;
        state.loading = true;
        state.error = false;
        if (clear) state.data = null;
        const timer = setTimeout(() => request.abort(), 15000);

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: request.signal, cache: 'no-store' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            if (!validate(payload)) throw new Error('Unexpected API response');
            // Ignore an older response when the selected station or filters changed.
            if (current === generation) {
                state.data = payload;
                state.updatedAt = new Date().toISOString();
            }
        } catch {
            if (current === generation) state.error = true;
        } finally {
            clearTimeout(timer);
            if (current === generation) state.loading = false;
        }
    }

    onScopeDispose(() => { generation += 1; controller?.abort(); });
    return { state, load };
}
