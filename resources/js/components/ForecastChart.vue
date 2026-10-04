<script setup>
import { computed, useId } from 'vue'
import { readingValid } from '../lib/urban'

const props = defineProps({
  history: { type: Array, default: () => [] },
  forecasts: { type: Array, default: () => [] },
  threshold: { type: Number, default: null },
  selectedHorizon: { type: Number, default: 6 },
})

const WIDTH = 920
const HEIGHT = 360
const MARGIN = { top: 26, right: 22, bottom: 58, left: 58 }
const PLOT_WIDTH = WIDTH - MARGIN.left - MARGIN.right
const PLOT_HEIGHT = HEIGHT - MARGIN.top - MARGIN.bottom
const HOUR = 60 * 60 * 1000
const chartId = useId()
const VALID_QUALITY = ['ok', 'valid', 'synthetic']

const isFiniteValue = (value) => (typeof value === 'number' || (typeof value === 'string' && value.trim() !== ''))
  && Number.isFinite(Number(value)) && Number(value) >= 0

const parseDate = (value) => {
  if (value === null || value === undefined || value === '') return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

const isUsableReading = readingValid

const sortedHistory = computed(() => props.history
  .map((reading) => ({ ...reading, date: parseDate(reading.timestamp), value: Number(reading.pm25) }))
  .filter((reading) => reading.date)
  .sort((a, b) => a.date - b.date))

const latestForecastGroup = computed(() => {
  const byOrigin = new Map()
  props.forecasts
    .filter((forecast) => [1, 6, 24].includes(Number(forecast?.horizon_hour)))
    .forEach((forecast) => {
      const horizon = Number(forecast.horizon_hour)
      const originDate = parseDate(forecast.forecast_origin)
      if (!originDate) return
      const origin = originDate.getTime()
      const group = byOrigin.get(origin) || new Map()
      const previous = group.get(horizon)
      if (!previous || (parseDate(previous.generated_at)?.getTime() || 0) < (parseDate(forecast.generated_at)?.getTime() || 0)) {
        group.set(horizon, { ...forecast, originDate, horizon })
      }
      byOrigin.set(origin, group)
    })
  // Keep the newest origin even when it contains missing data; never substitute an older forecast.
  const latestOrigin = [...byOrigin.keys()].sort((a, b) => b - a)[0]
  return { origin: latestOrigin === undefined ? null : new Date(latestOrigin), forecasts: byOrigin.get(latestOrigin) || new Map() }
})

const chartForecasts = computed(() => [1, 6, 24]
  .map((horizon) => latestForecastGroup.value.forecasts.get(horizon))
  .filter(Boolean)
  .map((forecast) => {
    const date = parseDate(forecast.target_timestamp)
    const quality = String(forecast.data_quality || '').toLowerCase()
    const status = String(forecast.status || '').toLowerCase()
    const usable = forecast.valid !== false && isFiniteValue(forecast.predicted_pm25) && date
      && date.getTime() - forecast.originDate.getTime() === forecast.horizon * HOUR
      && ['available', 'stale'].includes(status)
      && [...VALID_QUALITY, 'stale'].includes(quality)
    return usable ? { ...forecast, date, value: Number(forecast.predicted_pm25), stale: status === 'stale' || quality === 'stale' } : null
  })
  .filter(Boolean))

const selectedForecast = computed(() => chartForecasts.value.find((forecast) => forecast.horizon === Number(props.selectedHorizon)) || null)
const selectedForecastMessage = computed(() => {
  const forecast = selectedForecast.value
  if (forecast?.stale) return `Dự báo h=${props.selectedHorizon} đã cũ, chỉ dùng để tham khảo.`
  if (forecast) return `Dự báo h=${props.selectedHorizon} tại ${localTime(forecast.date, true)}`
  return latestForecastGroup.value.forecasts.has(Number(props.selectedHorizon))
    ? `Dự báo h=${props.selectedHorizon} thiếu hoặc không hợp lệ, không hiển thị trên biểu đồ.`
    : `Chưa có dự báo h=${props.selectedHorizon} tại lần phát hành mới nhất.`
})

const anchorDate = computed(() => {
  const dates = sortedHistory.value.map((point) => point.date.getTime())
  if (latestForecastGroup.value.origin) dates.push(latestForecastGroup.value.origin.getTime())
  return dates.length ? new Date(Math.max(...dates)) : null
})

const range = computed(() => {
  if (!anchorDate.value) return { start: 0, end: 1 }
  const start = anchorDate.value.getTime() - 24 * HOUR
  const forecastEnd = Math.max(...chartForecasts.value.map((point) => point.date.getTime()), start + 48 * HOUR)
  return { start, end: Math.max(start + 48 * HOUR, forecastEnd) }
})

const timelineHistory = computed(() => sortedHistory.value.filter((point) => {
  const timestamp = point.date.getTime()
  return timestamp >= range.value.start && timestamp <= range.value.end
}))

const values = computed(() => [
  ...timelineHistory.value.filter(isUsableReading).map((point) => point.value),
  ...chartForecasts.value.map((point) => point.value),
  ...(Number.isFinite(props.threshold) ? [Number(props.threshold)] : []),
].filter(Number.isFinite))

const yScale = computed(() => {
  if (!values.value.length) return { min: 0, max: 100 }
  const minValue = Math.min(...values.value)
  const maxValue = Math.max(...values.value)
  const padding = Math.max(5, (maxValue - minValue) * 0.14)
  return { min: Math.max(0, minValue - padding), max: maxValue + padding }
})

function xPosition(date) {
  const denominator = range.value.end - range.value.start || 1
  return MARGIN.left + ((date.getTime() - range.value.start) / denominator) * PLOT_WIDTH
}

function yPosition(value) {
  const denominator = yScale.value.max - yScale.value.min || 1
  return MARGIN.top + (1 - (value - yScale.value.min) / denominator) * PLOT_HEIGHT
}

function makePath(points) {
  return points.map((point, index) => `${index ? 'L' : 'M'} ${xPosition(point.date).toFixed(2)} ${yPosition(point.value).toFixed(2)}`).join(' ')
}

const observedSegments = computed(() => {
  const segments = []
  let segment = []
  let previousTimestamp = null
  timelineHistory.value.forEach((point) => {
    const valid = isUsableReading(point)
    // Hourly observations with an omitted hour must start a new segment.
    const gap = previousTimestamp !== null && point.date.getTime() - previousTimestamp > 1.5 * HOUR
    if (!valid || gap) {
      if (segment.length) segments.push(segment)
      segment = []
    }
    if (valid) segment.push(point)
    previousTimestamp = point.date.getTime()
  })
  if (segment.length) segments.push(segment)
  return segments
})

const thresholdY = computed(() => Number.isFinite(props.threshold) ? yPosition(Number(props.threshold)) : null)

const yTicks = computed(() => {
  const count = 4
  return Array.from({ length: count + 1 }, (_, index) => {
    const value = yScale.value.min + ((yScale.value.max - yScale.value.min) * index) / count
    return { value, y: yPosition(value) }
  }).reverse()
})

const xTicks = computed(() => {
  const count = 4
  const span = range.value.end - range.value.start
  return Array.from({ length: count + 1 }, (_, index) => {
    const date = new Date(range.value.start + (span * index) / count)
    return { date, x: xPosition(date) }
  })
})

const hasData = computed(() => timelineHistory.value.some(isUsableReading) || chartForecasts.value.length > 0)

function localTime(date, includeDate = false) {
  if (!date) return ''
  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
    hour: '2-digit',
    minute: '2-digit',
    ...(includeDate ? { day: '2-digit', month: '2-digit' } : {}),
    hour12: false,
  }).format(date)
}

function forecastClass(forecast) {
  return { 'forecast-dot': true, 'forecast-dot--stale': forecast.stale, 'forecast-dot--selected': forecast.horizon === Number(props.selectedHorizon) }
}

function forecastAriaLabel(forecast) {
  const stale = forecast.stale ? ', dữ liệu đã cũ' : ''
  const selected = forecast.horizon === Number(props.selectedHorizon) ? ', đang chọn' : ''
  return `Dự báo ${forecast.horizon} giờ, ${forecast.value.toFixed(1)} µg/m³ lúc ${localTime(forecast.date, true)}${stale}${selected}`
}
</script>

<template>
  <section class="forecast-chart" aria-label="Biểu đồ quan trắc và dự báo PM2.5">
    <div class="selected-forecast" :class="{ 'selected-forecast--unavailable': !selectedForecast || selectedForecast.stale }" role="status" aria-live="polite">
      <span>{{ selectedForecastMessage }}</span>
      <strong v-if="selectedForecast">{{ selectedForecast.value.toFixed(1) }} <small>µg/m³</small></strong>
    </div>
    <div v-if="!hasData" class="chart-empty" role="status">Chưa có dữ liệu quan trắc hoặc dự báo PM2.5 hợp lệ.</div>
    <template v-else>
      <svg class="chart-svg" :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" role="img" :aria-labelledby="`${chartId}-title ${chartId}-description`">
        <title :id="`${chartId}-title`">Lịch sử và dự báo PM2.5</title>
        <desc :id="`${chartId}-description`">Quan trắc 24 giờ gần nhất và ba điểm dự báo 1, 6, 24 giờ không nối với nhau. Điểm h={{ selectedHorizon }} đang chọn có vòng viền. Dự báo đã cũ có màu xám.</desc>

        <g class="grid-lines" aria-hidden="true">
          <line v-for="tick in yTicks" :key="`y-${tick.value}`" :x1="MARGIN.left" :x2="WIDTH - MARGIN.right" :y1="tick.y" :y2="tick.y" />
          <line v-for="tick in xTicks" :key="`x-${tick.date.toISOString()}`" :x1="tick.x" :x2="tick.x" :y1="MARGIN.top" :y2="HEIGHT - MARGIN.bottom" />
        </g>

        <g class="axis-labels" aria-hidden="true">
          <text v-for="tick in yTicks" :key="`yl-${tick.value}`" :x="MARGIN.left - 10" :y="tick.y + 4" text-anchor="end">{{ tick.value.toFixed(0) }}</text>
          <text v-for="tick in xTicks" :key="`xl-${tick.date.toISOString()}`" :x="tick.x" :y="HEIGHT - MARGIN.bottom + 24" text-anchor="middle">{{ localTime(tick.date, true) }}</text>
          <text :x="MARGIN.left" y="15">PM2.5 (µg/m³)</text>
        </g>

        <line v-if="thresholdY !== null" class="threshold-line" :x1="MARGIN.left" :x2="WIDTH - MARGIN.right" :y1="thresholdY" :y2="thresholdY" />
        <text v-if="thresholdY !== null" class="threshold-label" :x="WIDTH - MARGIN.right" :y="thresholdY - 7" text-anchor="end">Ngưỡng nội bộ {{ Number(threshold).toFixed(1) }}</text>

        <g class="observed-series" aria-label="PM2.5 quan trắc">
          <path v-for="(segment, index) in observedSegments" :key="`segment-${index}`" :d="makePath(segment)" />
          <circle v-for="point in timelineHistory.filter(isUsableReading)" :key="`observed-${point.date.toISOString()}`" :cx="xPosition(point.date)" :cy="yPosition(point.value)" r="3.2">
            <title>{{ `Quan trắc ${point.value.toFixed(1)} µg/m³ lúc ${localTime(point.date, true)}` }}</title>
          </circle>
        </g>

        <g class="forecast-series" aria-label="Các điểm dự báo PM2.5">
          <g v-for="forecast in chartForecasts" :key="`forecast-${forecast.horizon}`">
            <circle v-if="forecast.horizon === Number(selectedHorizon)" class="selected-ring" :class="{ 'selected-ring--stale': forecast.stale }" :cx="xPosition(forecast.date)" :cy="yPosition(forecast.value)" r="13" aria-hidden="true" />
            <circle :class="forecastClass(forecast)" :cx="xPosition(forecast.date)" :cy="yPosition(forecast.value)" :r="forecast.horizon === Number(selectedHorizon) ? 8 : 5.5" tabindex="0" :aria-label="forecastAriaLabel(forecast)">
              <title>{{ forecastAriaLabel(forecast) }}</title>
            </circle>
            <text v-if="forecast.horizon === Number(selectedHorizon)" class="selected-point-label" :x="xPosition(forecast.date)" :y="yPosition(forecast.value) - 21" :text-anchor="forecast.horizon === 24 ? 'end' : 'middle'" aria-hidden="true">h={{ forecast.horizon }} · {{ forecast.value.toFixed(1) }}</text>
          </g>
        </g>
      </svg>

      <div class="chart-legend" aria-label="Chú giải biểu đồ">
        <span><i class="legend-swatch legend-swatch--observed" aria-hidden="true" />Quan trắc</span>
        <span><i class="legend-swatch legend-swatch--forecast" aria-hidden="true" />Dự báo 1 / 6 / 24 giờ</span>
        <span><i class="legend-swatch legend-swatch--selected" aria-hidden="true" />Đang chọn h={{ selectedHorizon }}</span>
        <span v-if="thresholdY !== null"><i class="legend-swatch legend-swatch--threshold" aria-hidden="true" />Ngưỡng cảnh báo</span>
        <span v-if="chartForecasts.some(forecast => forecast.stale)"><i class="legend-swatch legend-swatch--stale" aria-hidden="true" />Đã cũ</span>
      </div>
    </template>
  </section>
</template>

<style scoped>
.forecast-chart {
  display: block;
  width: 100%;
  height: auto;
  min-width: 0;
  padding: 0;
  color: var(--ink, #e9eef4);
  font-size: 12px;
  border: 0;
  background: none;
}

.selected-forecast {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
  padding: 12px 14px;
  color: #c8d3df;
  font-size: 12px;
  line-height: 1.5;
  background: rgba(242, 184, 102, 0.07);
  border: 1px solid rgba(242, 184, 102, 0.25);
  border-radius: 8px;
}

.selected-forecast strong {
  flex-shrink: 0;
  color: var(--amber, #f2b866);
  font-size: 18px;
}

.selected-forecast small {
  font-size: 11px;
  font-weight: 500;
}

.selected-forecast--unavailable {
  color: #acb9c7;
  background: rgba(130, 144, 161, 0.07);
  border-color: var(--line, #253444);
}

.selected-forecast--unavailable strong {
  color: #acb9c7;
}

.chart-svg {
  display: block;
  width: 100%;
  height: auto;
  min-height: 220px;
  overflow: visible;
}

.grid-lines line {
  stroke: var(--line, #253444);
  stroke-width: 1;
}

.axis-labels text {
  fill: #a8b6c6;
  font-size: 13px;
}

.axis-labels text:first-child {
  font-weight: 700;
}

.threshold-line {
  stroke: var(--amber, #f2b866);
  stroke-width: 1.5;
  stroke-dasharray: 7 6;
}

.threshold-label {
  fill: var(--amber, #f2b866);
  font-size: 12px;
  font-weight: 700;
}

.observed-series path {
  fill: none;
  stroke: var(--cyan, #5eead4);
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.observed-series circle {
  fill: var(--panel, #121a24);
  stroke: var(--cyan, #5eead4);
  stroke-width: 2;
}

.forecast-dot {
  fill: var(--amber, #f2b866);
  stroke: var(--panel, #121a24);
  stroke-width: 2.5;
  outline: none;
}

.forecast-dot--stale {
  fill: #94a3b8;
}

.selected-ring {
  fill: rgba(242, 184, 102, 0.1);
  stroke: var(--amber, #f2b866);
  stroke-width: 2;
}

.selected-ring--stale {
  fill: rgba(148, 163, 184, 0.1);
  stroke: #94a3b8;
  stroke-dasharray: 4 3;
}

.selected-point-label {
  fill: var(--ink, #e9eef4);
  font-size: 15px;
  font-weight: 700;
  paint-order: stroke;
  stroke: var(--panel, #121a24);
  stroke-width: 5px;
  stroke-linejoin: round;
}

.forecast-dot:hover,
.forecast-dot:focus-visible {
  stroke: var(--ink, #e9eef4);
  stroke-width: 3;
}

.chart-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 18px;
  margin-top: 8px;
  color: #acb9c7;
  font-size: 11px;
}

.chart-legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.legend-swatch {
  display: inline-block;
  width: 18px;
  height: 3px;
  border-radius: 2px;
}

.legend-swatch--observed { background: var(--cyan, #5eead4); }
.legend-swatch--forecast { width: 10px; height: 10px; border-radius: 50%; background: var(--amber, #f2b866); }
.legend-swatch--selected { width: 13px; height: 13px; border: 2px solid var(--amber, #f2b866); border-radius: 50%; }
.legend-swatch--threshold { height: 0; border-top: 2px dashed var(--amber, #f2b866); }
.legend-swatch--stale { width: 10px; height: 10px; border-radius: 50%; background: #94a3b8; }

.chart-empty {
  display: grid;
  min-height: 300px;
  place-items: center;
  padding: 24px;
  color: #acb9c7;
  text-align: center;
  background: rgba(11, 17, 24, 0.4);
  border: 1px dashed var(--line, #253444);
  border-radius: 12px;
}

@media (max-width: 640px) {
  .chart-svg { min-height: 170px; }
  .selected-forecast { align-items: flex-start; flex-direction: column; gap: 4px; }
  .chart-legend { gap: 8px 12px; }
}
</style>
