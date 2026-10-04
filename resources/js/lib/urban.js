export const hasNumber = (value) => (typeof value === 'number' || (typeof value === 'string' && value.trim() !== ''))
    && Number.isFinite(Number(value));

export function formatNumber(value, digits = 1) {
    return hasNumber(value) ? Number(value).toLocaleString('vi-VN', { minimumFractionDigits: digits, maximumFractionDigits: digits }) : '\u2014';
}

export function forecastFor(station, horizon) {
    const forecasts = station?.forecasts ?? (station?.forecast ? [station.forecast] : []);
    return forecasts.find((forecast) => Number(forecast.horizon_hour) === Number(horizon)) ?? null;
}

export function forecastAvailable(forecast) {
    return forecast?.valid !== false && forecast?.status === 'available' && hasNumber(forecast.predicted_pm25)
        && Number(forecast.predicted_pm25) >= 0 && Number(forecast.predicted_pm25) <= 500
        && ['ok', 'valid', 'synthetic'].includes(String(forecast.data_quality).toLowerCase());
}

export function readingValid(reading) {
    return reading?.valid !== false && hasNumber(reading?.pm25) && Number(reading.pm25) >= 0 && Number(reading.pm25) <= 500
        && ['ok', 'valid', 'synthetic'].includes(String(reading?.quality_flag).toLowerCase());
}

export function horizonMetrics(model) {
    const metrics = model?.metrics;
    let rows = Array.isArray(metrics) ? metrics : metrics?.horizons;
    if (!Array.isArray(rows) && metrics?.by_horizon && typeof metrics.by_horizon === 'object') {
        rows = Object.entries(metrics.by_horizon).map(([horizon_hour, metric]) => ({ ...metric, horizon_hour }));
    }
    return Array.isArray(rows) ? rows.filter((row) => row && [1, 6, 24].includes(Number(row.horizon_hour))) : [];
}

export function stationMetrics(model) {
    const metrics = model?.station_metrics;
    if (!metrics || typeof metrics !== 'object') return [];
    if (Array.isArray(metrics)) return metrics.flatMap((metric) => {
        if (!metric || typeof metric !== 'object' || metric.station_id == null) return [];
        return [{ ...metric, stationId: String(metric.station_id) }];
    });
    return Object.entries(metrics).flatMap(([id, metric]) => {
        if (!metric || typeof metric !== 'object' || Array.isArray(metric)) return [];
        const stationId = metric.station_id ?? id;
        return stationId == null ? [] : [{ ...metric, stationId: String(stationId) }];
    });
}

export function pipelineFailures(runs, ingestionOnly = false) {
    if (!Array.isArray(runs)) return [];
    return runs.filter((run) => ['failed', 'error'].includes(String(run?.status).toLowerCase())
        && (!ingestionOnly || ['ingest', 'ingestion', 'raw', 'bronze', 'silver'].includes(String(run.stage).toLowerCase())));
}

export function formatDate(value, timezone = 'Asia/Ho_Chi_Minh') {
    if (!value) return '\u2014';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '\u2014';
    return new Intl.DateTimeFormat('vi-VN', {
        timeZone: timezone, day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false,
    }).format(date);
}
