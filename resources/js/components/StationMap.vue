<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
  stations: { type: Array, default: () => [] },
  selectedId: { type: [String, Number], default: null },
})

const emit = defineEmits(['select'])

const mapElement = ref(null)
const map = shallowRef(null)
const tileError = ref(false)
const markerLayer = shallowRef(null)
const markers = new Map()
let resizeObserver = null
let stationLayout = ''

const isFiniteNumber = (value) => (typeof value === 'number' || (typeof value === 'string' && value.trim() !== '')) && Number.isFinite(Number(value))
const validCoordinates = (station) => isFiniteNumber(station?.latitude) && isFiniteNumber(station?.longitude)
  && Math.abs(Number(station.latitude)) <= 90 && Math.abs(Number(station.longitude)) <= 180

function stationValue(station) {
  if (stationRisk(station) === 'unknown') return null
  const value = station?.forecast?.predicted_pm25
  return isFiniteNumber(value) ? Number(value) : null
}

function stationLabel(station) {
  const value = stationValue(station)
  return value === null ? '—' : value.toFixed(0)
}

function freshnessLabel(value) {
  return isFiniteNumber(value) ? `${Number(value).toFixed(0)} phút` : 'không có dữ liệu'
}

function stationRisk(station) {
  const forecast = station?.forecast
  if (forecast?.valid === false || !isFiniteNumber(forecast?.predicted_pm25) || Number(forecast.predicted_pm25) < 0 || forecast?.status !== 'available'
    || !['ok', 'valid', 'synthetic'].includes(String(forecast?.data_quality || '').toLowerCase())) return 'unknown'
  if (station?.risk === 'elevated') return 'elevated'
  if (station?.risk === 'normal') return 'normal'
  return 'unknown'
}

function markerClass(station) {
  return `urban-station-marker urban-station-marker--${stationRisk(station)}`
}

function createPopup(station) {
  const popup = document.createElement('div')
  popup.className = 'urban-station-popup'

  const title = document.createElement('strong')
  title.textContent = station?.name || String(station?.id ?? 'Trạm')
  popup.appendChild(title)

  const district = document.createElement('span')
  district.textContent = station?.district ? `Khu vực: ${station.district}` : ''
  popup.appendChild(district)

  const value = document.createElement('span')
  const pm25 = stationValue(station)
  value.textContent = pm25 === null ? 'Dự báo PM2.5: chưa có dữ liệu hợp lệ' : `Dự báo PM2.5: ${pm25.toFixed(1)} µg/m³`
  popup.appendChild(value)

  const current = document.createElement('span')
  const currentPm25 = station?.current?.pm25
  current.textContent = isFiniteNumber(currentPm25)
    ? `Hiện tại PM2.5: ${Number(currentPm25).toFixed(1)} µg/m³`
    : 'Hiện tại PM2.5: chưa có dữ liệu hợp lệ'
  popup.appendChild(current)

  const freshness = document.createElement('span')
  freshness.textContent = `Tuổi dữ liệu: ${freshnessLabel(station?.freshness_minutes)}`
  popup.appendChild(freshness)

  const quality = document.createElement('span')
  quality.textContent = `Cờ chất lượng: ${station?.current?.quality_flag || '—'}`
  popup.appendChild(quality)

  const status = document.createElement('span')
  const labels = { elevated: 'Vượt ngưỡng', normal: 'Dưới ngưỡng', unknown: 'Không rõ' }
  status.textContent = `Rủi ro: ${labels[stationRisk(station)] || labels.unknown}`
  popup.appendChild(status)
  return popup
}

function decorateMarker(marker, station) {
  const element = marker.getElement()
  if (!element) return

  // The icon starts empty; textContent keeps station data out of HTML parsing.
  element.textContent = stationLabel(station)
  element.tabIndex = 0
  element.setAttribute('role', 'button')
  element.setAttribute('aria-label', `${station?.name || station?.id || 'Trạm'}, dự báo PM2.5 ${stationLabel(station)} µg/m³`)
  element.setAttribute('aria-pressed', String(String(props.selectedId) === String(station?.id)))
  element.classList.toggle('is-selected', String(props.selectedId) === String(station?.id))
}

function selectStation(station) {
  emit('select', station.id)
}

function addStation(station) {
  if (!map.value || !markerLayer.value || !validCoordinates(station)) return

  const marker = L.marker([Number(station.latitude), Number(station.longitude)], {
    icon: L.divIcon({ className: markerClass(station), html: '', iconSize: [46, 46], iconAnchor: [23, 23] }),
    keyboard: false,
    title: station?.name || String(station?.id ?? 'Trạm'),
  })
  marker.addTo(markerLayer.value)
  marker.bindPopup(createPopup(station), { closeButton: true, autoPan: true })
  marker.on('click', () => selectStation(station))
  decorateMarker(marker, station)
  marker.getElement()?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      event.stopPropagation()
      marker.openPopup()
      selectStation(station)
    }
  })
  markers.set(String(station.id), marker)
}

function renderMarkers() {
  if (!markerLayer.value) return
  markers.forEach((marker) => marker.remove())
  markers.clear()
  props.stations.forEach(addStation)
  nextTick(() => {
    markers.forEach((marker, id) => {
      const station = props.stations.find((item) => String(item.id) === id)
      if (station) decorateMarker(marker, station)
    })
  })
}

function fitAll() {
  if (!map.value) return
  const points = props.stations
    .filter(validCoordinates)
    .map((station) => [Number(station.latitude), Number(station.longitude)])
  if (points.length === 0) {
    map.value.setView([0, 0], 2)
    return
  }
  map.value.fitBounds(L.latLngBounds(points), { padding: [26, 26], maxZoom: 14 })
}

function updateSelectedMarker() {
  markers.forEach((marker, id) => {
    marker.getElement()?.classList.toggle('is-selected', String(props.selectedId) === id)
    marker.getElement()?.setAttribute('aria-pressed', String(String(props.selectedId) === id))
  })
}

defineExpose({ fitAll })

onMounted(() => {
  if (!mapElement.value) return
  map.value = L.map(mapElement.value, { zoomControl: true, attributionControl: true, worldCopyJump: true })
  const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  })
  tiles.on('tileerror', () => { tileError.value = true })
  tiles.on('tileload', () => { tileError.value = false })
  tiles.addTo(map.value)
  markerLayer.value = L.layerGroup().addTo(map.value)
  renderMarkers()
  fitAll()
  stationLayout = props.stations.filter(validCoordinates).map((station) => `${station.id}:${station.latitude},${station.longitude}`).join('|')

  resizeObserver = new ResizeObserver(() => map.value?.invalidateSize({ pan: false }))
  resizeObserver.observe(mapElement.value)
})

watch(() => props.stations, () => {
  renderMarkers()
  const layout = props.stations.filter(validCoordinates).map((station) => `${station.id}:${station.latitude},${station.longitude}`).join('|')
  if (layout !== stationLayout) fitAll()
  stationLayout = layout
}, { deep: true })

watch(() => props.selectedId, updateSelectedMarker)

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  markers.clear()
  map.value?.remove()
  map.value = null
})
</script>

<template>
  <section class="station-map-panel" aria-label="Bản đồ trạm quan trắc chất lượng không khí">
    <div ref="mapElement" class="station-map" aria-label="Bản đồ OpenStreetMap và vị trí các trạm" />
      <div v-if="tileError" class="map-fallback" role="status">
        Không tải được nền bản đồ. Các điểm trạm vẫn có thể tương tác.
      </div>
      <div v-if="!stations.length" class="map-empty" role="status">Chưa có dữ liệu trạm.</div>
      <div class="map-legend" aria-label="Chú giải rủi ro">
        <span><i class="legend-dot legend-dot--normal" aria-hidden="true" />Dưới ngưỡng</span>
        <span><i class="legend-dot legend-dot--elevated" aria-hidden="true" />Vượt ngưỡng</span>
        <span><i class="legend-dot legend-dot--unknown" aria-hidden="true" />Không rõ</span>
      </div>
  </section>
</template>

<style scoped>
.station-map-panel {
  position: relative;
  width: 100%;
  min-width: 0;
}

.station-map {
  position: relative;
  width: 100%;
  min-height: 430px;
  overflow: hidden;
  background: #e9efee;
  border: 1px solid #d3dfdd;
  border-radius: 14px;
  isolation: isolate;
}

.map-fallback,
.map-empty {
  position: absolute;
  z-index: 1000;
  top: 14px;
  left: 50%;
  max-width: calc(100% - 28px);
  padding: 8px 12px;
  color: #153a40;
  font-size: 0.8rem;
  line-height: 1.35;
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid #d3dfdd;
  border-radius: 8px;
  box-shadow: 0 5px 18px rgba(21, 58, 64, 0.12);
  transform: translateX(-50%);
  pointer-events: none;
}

.map-legend {
  position: absolute;
  z-index: 1000;
  right: 14px;
  left: auto;
  bottom: 14px;
  display: flex;
  flex-wrap: wrap;
  gap: 7px 12px;
  max-width: calc(100% - 28px);
  padding: 7px 9px;
  color: #c6d2df;
  font-size: 0.72rem;
  line-height: 1.3;
  background: rgba(11, 17, 24, 0.88);
  border: 1px solid #253444;
  border-radius: 7px;
  box-shadow: 0 5px 18px rgba(0, 0, 0, 0.2);
  pointer-events: none;
}

.map-legend span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.legend-dot {
  width: 8px;
  height: 8px;
  flex: 0 0 auto;
  border-radius: 50%;
}

.legend-dot--normal { background: #087f76; }
.legend-dot--elevated { background: #c87725; }
.legend-dot--unknown { background: #879595; }

.map-empty {
  top: 50%;
  color: #637779;
  transform: translate(-50%, -50%);
}

:deep(.leaflet-container) {
  min-height: 430px;
  width: 100%;
  font: inherit;
  color: #153a40;
  background: #e9efee;
}

:deep(.leaflet-tile-pane) {
  filter: saturate(0.36) contrast(0.96);
}

:deep(.leaflet-control-attribution) {
  color: #637779;
  background: rgba(255, 255, 255, 0.88);
}

:deep(.leaflet-control-attribution a) {
  color: #087f76;
}

:deep(.urban-station-marker) {
  display: grid;
  place-items: center;
  width: 42px !important;
  height: 42px !important;
  color: #fff;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  border: 3px solid rgba(255, 255, 255, 0.95);
  border-radius: 50%;
  box-shadow: 0 3px 10px rgba(21, 58, 64, 0.26);
  transition: transform 140ms ease, box-shadow 140ms ease;
}

:deep(.urban-station-marker--elevated) { background: #c87725; }
:deep(.urban-station-marker--normal) { background: #087f76; }
:deep(.urban-station-marker--unknown) { background: #879595; }

:deep(.urban-station-marker:hover),
:deep(.urban-station-marker:focus-visible),
:deep(.urban-station-marker.is-selected) {
  outline: none;
  box-shadow: 0 0 0 4px rgba(8, 127, 118, 0.24), 0 5px 14px rgba(21, 58, 64, 0.3);
}

:deep(.urban-station-popup) {
  display: grid;
  gap: 4px;
  min-width: 170px;
  color: #153a40;
  font-size: 0.78rem;
}

:deep(.urban-station-popup strong) { font-size: 0.86rem; }
</style>
