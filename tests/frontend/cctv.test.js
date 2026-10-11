import assert from 'node:assert/strict';
import { test } from 'node:test';
import { catalogUrl, isLocalVideo, snapshotUrl } from '../../resources/js/lib/cctv.js';

test('catalog query encodes names and regions without changing pagination', () => {
    const url = new URL(catalogUrl('  A&B #1  ', 'TP. Hồ Chí Minh', 3), 'http://localhost');
    assert.equal(url.pathname, '/api/cctv');
    assert.equal(url.searchParams.get('q'), 'A&B #1');
    assert.equal(url.searchParams.get('region'), 'TP. Hồ Chí Minh');
    assert.equal(url.searchParams.get('page'), '3');
    assert.equal(url.searchParams.get('per_page'), '24');
    assert.equal(catalogUrl(), '/api/cctv?page=1&per_page=24');
});

test('local video selection accepts supported containers and rejects empty or nonvideo files', () => {
    assert.equal(isLocalVideo({ name: 'camera.mp4', type: 'video/mp4', size: 42 }), true);
    assert.equal(isLocalVideo({ name: 'camera.WEBM', type: '', size: 42 }), true);
    assert.equal(isLocalVideo({ name: 'camera.mp4', type: 'text/html', size: 42 }), false);
    assert.equal(isLocalVideo({ name: 'camera.mp4', type: 'video/mp4', size: 0 }), false);
    assert.equal(isLocalVideo({ name: 'camera.exe', type: '', size: 42 }), false);
    assert.equal(isLocalVideo(null), false);
});

test('snapshot refresh accepts only the exact public image endpoint without credentials', () => {
    const url = 'https://giaothong.hochiminhcity.gov.vn/render/ImageHandler.ashx?id=662b7f651afb9c00172dc992';
    assert.equal(snapshotUrl(url, 123), `${url}&_t=123`);
    for (const unsafe of [null, '', 'javascript:alert(1)', 'http://127.0.0.1/image', url + '&token=secret', url + '#x', url.replace('.gov.vn/', '.gov.vn.evil.test/'), url.replace('https:', 'http:')]) {
        assert.equal(snapshotUrl(unsafe), null);
    }
});
