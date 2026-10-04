import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { effectScope } from 'vue';
import { useUrbanResource } from '../../resources/js/composables/useUrbanResource.js';

const originalFetch = globalThis.fetch;

afterEach(() => {
    globalThis.fetch = originalFetch;
});

function response(payload, ok = true) {
    return {
        ok,
        async json() {
            return payload;
        },
    };
}

function deferred() {
    let resolve;
    const promise = new Promise((done) => {
        resolve = done;
    });
    return { promise, resolve };
}

function createResource(initialUrl = '/api/resource') {
    const scope = effectScope();
    let resource;
    scope.run(() => {
        resource = useUrbanResource(initialUrl);
    });
    return { scope, ...resource };
}

test('successful response stores data and clears loading/error state', async () => {
    globalThis.fetch = async (url, options) => {
        assert.equal(url, '/api/resource');
        assert.equal(options.headers.Accept, 'application/json');
        return response({ data: [{ id: 'ST001' }] });
    };

    const resource = createResource();
    try {
        await resource.load();

        assert.deepEqual(resource.state.data, { data: [{ id: 'ST001' }] });
        assert.equal(resource.state.error, false);
        assert.equal(resource.state.loading, false);
        assert.ok(resource.state.updatedAt);
    } finally {
        resource.scope.stop();
    }
});

test('a failed refresh preserves the previous payload and exposes an error', async () => {
    let shouldFail = false;
    globalThis.fetch = async () => {
        if (shouldFail) throw new Error('network unavailable');
        return response({ data: [{ id: 'ST001', status: 'active' }] });
    };

    const resource = createResource();
    try {
        await resource.load();
        const previous = resource.state.data;
        shouldFail = true;

        await resource.load();

        assert.deepEqual(resource.state.data, previous);
        assert.equal(resource.state.error, true);
        assert.equal(resource.state.loading, false);
    } finally {
        resource.scope.stop();
    }
});

test('an out-of-order response cannot overwrite the latest request', async () => {
    const first = deferred();
    const latest = deferred();
    globalThis.fetch = (url) => url === '/api/first' ? first.promise : latest.promise;

    const resource = createResource();
    try {
        const firstLoad = resource.load('/api/first');
        const latestLoad = resource.load('/api/latest');

        latest.resolve(response({ data: [{ id: 'latest' }] }));
        await latestLoad;
        first.resolve(response({ data: [{ id: 'first' }] }));
        await firstLoad;

        assert.deepEqual(resource.state.data, { data: [{ id: 'latest' }] });
        assert.equal(resource.state.error, false);
        assert.equal(resource.state.loading, false);
    } finally {
        resource.scope.stop();
    }
});

test('clear refresh removes the previous station payload before loading the replacement', async () => {
    const replacement = deferred();
    let requestCount = 0;
    globalThis.fetch = () => {
        requestCount += 1;
        return requestCount === 1
            ? Promise.resolve(response({ data: [{ id: 'ST001' }] }))
            : replacement.promise;
    };

    const resource = createResource('/api/stations/ST001');
    try {
        await resource.load();
        assert.deepEqual(resource.state.data, { data: [{ id: 'ST001' }] });

        const replacementLoad = resource.load('/api/stations/ST002', { clear: true });
        assert.equal(resource.state.data, null);

        replacement.resolve(response({ data: [{ id: 'ST002' }] }));
        await replacementLoad;
        assert.deepEqual(resource.state.data, { data: [{ id: 'ST002' }] });
        assert.equal(resource.state.error, false);
    } finally {
        resource.scope.stop();
    }
});
