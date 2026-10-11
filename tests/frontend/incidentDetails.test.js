import assert from 'node:assert/strict';
import { test } from 'node:test';
import { eventTypes } from '../../resources/js/lib/monitoring.js';
import { createIncidentFixtures, filterIncidents, incidentProfiles, incidentTypeFromView, reviewStates } from '../../resources/js/lib/incidentDetails.js';

test('each detail dataset matches its overview count and has unique explicit demo records', () => {
    const ids = [];
    for (const type of eventTypes) {
        const records = createIncidentFixtures(type.id);
        assert.equal(records.length, type.count);
        assert.equal(incidentProfiles[type.id].checklist.length, 3);
        assert.ok(records.every((row) => row.id.startsWith('DEMO-') && row.type === type.id && reviewStates[row.status]));
        assert.ok(records.every((row) => /^\d{2}:\d{2}$/.test(row.time)));
        ids.push(...records.map((row) => row.id));
    }
    assert.equal(new Set(ids).size, ids.length);
    assert.deepEqual(createIncidentFixtures('unknown'), []);
});

test('detail filters combine status, severity and accent-insensitive query', () => {
    const rows = createIncidentFixtures('helmet');
    const found = filterIncidents(rows, { query: 'NUT GIAO MO PHONG', status: 'pending', severity: 'medium' });
    assert.ok(found.length > 0);
    assert.ok(found.every((row) => row.status === 'pending' && row.severity === 'medium'));
    assert.equal(filterIncidents(rows, { query: rows[0].id }).length, 1);
    assert.equal(filterIncidents(rows, { query: 'does-not-exist' }).length, 0);
    assert.equal(filterIncidents(rows).length, 18);
});

test('only known detail routes resolve and fixture mutations never affect a fresh page', () => {
    for (const type of eventTypes) assert.equal(incidentTypeFromView(`incident-${type.id}`), type.id);
    for (const route of ['overview', 'alerts', 'incident-unknown', 'incident-flood/extra', '__proto__']) {
        assert.equal(incidentTypeFromView(route), null);
    }
    const rows = createIncidentFixtures('accident');
    rows[0].status = 'resolved';
    assert.equal(createIncidentFixtures('accident')[0].status, 'pending');
});
