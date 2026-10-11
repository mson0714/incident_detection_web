<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { mappedCameras } from '../lib/monitoring';

const props = defineProps({ cameras: { type: Array, required: true } });
const emit = defineEmits(['select']);
const element = ref(null);
const tileError = ref(false);
let map;
let observer;
onMounted(() => {
    map = L.map(element.value).setView([10.785735, 106.7066], 14);
    const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>', maxZoom: 19,
    }).addTo(map);
    tiles.on('tileerror', () => { tileError.value = true; });
    for (const camera of mappedCameras(props.cameras)) {
        const marker = L.marker([camera.latitude, camera.longitude], {
            icon: L.divIcon({ className: 'camera-location-pin', html: '<span aria-hidden="true">C</span>', iconSize: [36, 36] }),
            title: `Xem camera ${camera.name}`, alt: `Xem camera ${camera.name}`,
        }).addTo(map);
        marker.on('click', () => emit('select', camera.id));
    }
    observer = new ResizeObserver(() => map.invalidateSize());
    observer.observe(element.value);
});
onBeforeUnmount(() => { observer?.disconnect(); map?.remove(); });
</script>

<template>
    <div class="camera-map-wrap">
        <div ref="element" class="camera-map" aria-label="Bản đồ vị trí camera công khai"></div>
        <p v-if="tileError" class="map-warning" role="status">Không tải được nền bản đồ. Điểm camera vẫn có thể chọn.</p>
        <p class="map-note">{{ mappedCameras(cameras).length }}/{{ cameras.length }} camera có tọa độ xác minh · Chọn điểm để xem ảnh.</p>
    </div>
</template>

<style scoped>
.camera-map-wrap{position:relative}.camera-map{height:360px;border-radius:8px;background:#192b35;isolation:isolate;z-index:0}
.map-note{font-size:11px;color:var(--muted);margin:12px 0 0}.map-warning{position:absolute;top:12px;left:12px;right:12px;z-index:1;background:#12202eed;color:#f2b866;padding:10px;border-radius:6px;font-size:12px;pointer-events:none}
:deep(.camera-location-pin){display:grid;place-items:center;background:#5eead4;color:#092522;border:3px solid white;border-radius:50%;font:700 15px 'DM Mono',monospace;box-shadow:0 3px 16px #0006}
:deep(.leaflet-tile-pane){filter:saturate(.35)}
@media(max-width:600px){.camera-map{height:290px}}
</style>
