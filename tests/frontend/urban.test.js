import test from 'node:test';
import assert from 'node:assert/strict';
import { forecastFor, forecastAvailable, formatNumber, formatDate, readingValid, horizonMetrics, stationMetrics, pipelineFailures } from '../../resources/js/lib/urban.js';

test('the selected horizon uses its own forecast, never h6 as a substitute', () => {
    const station = { forecasts: [1, 6, 24].map((horizon) => ({ horizon_hour: horizon, predicted_pm25: 20 + horizon })) };
    assert.equal(forecastFor(station, 1).predicted_pm25, 21);
    assert.equal(forecastFor(station, 6).predicted_pm25, 26);
    assert.equal(forecastFor(station, 24).predicted_pm25, 44);
    assert.equal(forecastFor({ forecast: station.forecasts[1] }, 24), null);
    assert.equal(forecastFor({ forecasts: [], forecast: station.forecasts[1] }, 6), null);
});

test('null or invalid measurements are not rendered as zero', () => {
    for (const value of [null, undefined, '', '  ', 'bad', true, Infinity]) assert.equal(formatNumber(value), '\u2014');
    assert.equal(formatNumber(0), '0,0');
});

test('only valid available forecasts qualify for risk coloring', () => {
    const forecast = { predicted_pm25: 48, status: 'available', data_quality: 'synthetic' };
    assert.equal(forecastAvailable(forecast), true);
    assert.equal(forecastAvailable({ ...forecast, status: 'stale' }), false);
    assert.equal(forecastAvailable({ ...forecast, data_quality: 'invalid' }), false);
    assert.equal(forecastAvailable({ ...forecast, predicted_pm25: null }), false);
    assert.equal(forecastAvailable({ ...forecast, valid: false }), false);
});

test('quality and model helpers preserve explicit invalid states and optional breakdowns', () => {
    assert.equal(readingValid({ pm25: 28, quality_flag: 'ok', valid: true }), true);
    assert.equal(readingValid({ pm25: 501, quality_flag: 'ok', valid: false }), false);
    assert.equal(readingValid({ pm25: null, quality_flag: 'missing' }), false);
    const model = { metrics: { horizons: [{ horizon_hour: 6, mae: 5 }], by_horizon: {} }, station_metrics: { ST001: { mae: 3 }, ST002: { rmse: 4 } } };
    assert.deepEqual(horizonMetrics(model), [{ horizon_hour: 6, mae: 5 }]);
    assert.deepEqual(stationMetrics(model).map(({ stationId }) => stationId), ['ST001', 'ST002']);
    assert.equal(stationMetrics({ station_metrics: null }).length, 0);
});

test('pipeline failure helper distinguishes ingestion from downstream failures', () => {
    const runs = [{ status: 'failed', stage: 'ingestion' }, { status: 'error', stage: 'forecast' }, { status: 'success', stage: 'ingestion' }];
    assert.equal(pipelineFailures(runs).length, 2);
    assert.equal(pipelineFailures(runs, true).length, 1);
});

test('timestamps have an explicit display timezone and missing states', () => {
    assert.match(formatDate('2026-09-13T08:00:00Z'), /15:00/);
    assert.equal(formatDate(null), '\u2014');
    assert.equal(formatDate('bad'), '\u2014');
});
