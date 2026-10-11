import assert from 'node:assert/strict';
import { test } from 'node:test';
import { demoEvents, eventTypes, filterEvents, mappedCameras, overviewCameras } from '../../resources/js/lib/monitoring.js';

test('monitoring fixtures reproduce the mockup and explicitly identify demo events', () => {
    assert.deepEqual(eventTypes.map(({ count }) => count), [3, 18, 7, 2]);
    assert.equal(demoEvents.length, 4);
    assert.ok(demoEvents.every((event) => event.id.startsWith('DEMO-')));
    assert.ok(demoEvents.every((event) => eventTypes.some((type) => type.id === event.type)));
});

test('type filters isolate event rows and all restores the list', () => {
    assert.equal(filterEvents(demoEvents).length, 4);
    for (const type of eventTypes) {
        assert.deepEqual(filterEvents(demoEvents, type.id).map((event) => event.type), [type.id]);
    }
    assert.deepEqual(filterEvents(demoEvents, 'unknown'), []);
});

test('camera map excludes missing and invalid coordinates rather than inventing positions', () => {
    assert.deepEqual(mappedCameras(overviewCameras).map((camera) => camera.id), ['209690']);
    assert.deepEqual(mappedCameras([
        { latitude: null, longitude: null }, { latitude: 91, longitude: 106 },
        { latitude: 10, longitude: 181 }, { latitude: NaN, longitude: 106 },
    ]), []);
    assert.ok(overviewCameras.every((camera) => !demoEvents.some((event) => event.camera === camera.id)));
});
