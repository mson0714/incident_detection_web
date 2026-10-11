<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { snapshotUrl } from '../lib/cctv';

const props = defineProps({ camera: { type: Object, required: true } });
const source = ref(null);
const frame = ref('');
const receivedAt = ref('');
const state = ref('resolving');
const paused = ref(false);
let request;
let pendingImage;
let refreshTimer;
let timeoutTimer;
let generation = 0;
let disposed = false;

function cancelImage() {
    generation++;
    clearTimeout(refreshTimer);
    clearTimeout(timeoutTimer);
    if (pendingImage) {
        pendingImage.onload = pendingImage.onerror = null;
        pendingImage.src = '';
        pendingImage = null;
    }
}

function refresh() {
    cancelImage();
    if (disposed || paused.value || document.hidden) return;
    const url = snapshotUrl(source.value?.image_url);
    if (!url) return;
    state.value = 'loading';
    const current = generation;
    const image = new Image();
    pendingImage = image;
    image.referrerPolicy = 'no-referrer';
    const finish = (ok) => {
        if (disposed || current !== generation) return;
        clearTimeout(timeoutTimer);
        image.onload = image.onerror = null;
        pendingImage = null;
        if (ok) {
            frame.value = url;
            receivedAt.value = new Date().toLocaleTimeString('vi-VN');
            state.value = 'ready';
        } else {
            state.value = 'offline';
            image.src = '';
        }
        refreshTimer = setTimeout(refresh, 20000);
    };
    image.onload = () => finish(true);
    image.onerror = () => finish(false);
    timeoutTimer = setTimeout(() => finish(false), 12000);
    image.src = url;
}

async function resolve() {
    request?.abort();
    cancelImage();
    source.value = null;
    state.value = 'resolving';
    const controller = new AbortController();
    request = controller;
    const timeout = setTimeout(() => controller.abort(), 12000);
    try {
        const response = await fetch(`/api/cctv/${encodeURIComponent(props.camera.id)}/playback`, {
            signal: controller.signal, headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('Source unavailable');
        const payload = await response.json();
        if (disposed || request !== controller) return;
        source.value = payload.data;
        if (payload.data?.type === 'snapshot' && snapshotUrl(payload.data.image_url)) {
            state.value = 'waiting';
            refresh();
        } else {
            state.value = 'unsupported';
        }
    } catch {
        if (!disposed && request === controller) state.value = 'error';
    } finally {
        clearTimeout(timeout);
    }
}

function togglePause() {
    paused.value = !paused.value;
    if (paused.value) cancelImage();
    else refresh();
}

function visibilityChanged() {
    if (document.hidden) cancelImage();
    else refresh();
}

onMounted(() => {
    document.addEventListener('visibilitychange', visibilityChanged);
    resolve();
});
onBeforeUnmount(() => {
    disposed = true;
    request?.abort();
    cancelImage();
    document.removeEventListener('visibilitychange', visibilityChanged);
});
</script>

<template>
    <div class="camera-view">
        <div class="camera-status" role="status" aria-live="polite">
            <span class="camera-dot" :class="{ active: state === 'ready' && !paused }"></span>
            <span v-if="paused">Đã tạm dừng cập nhật</span>
            <span v-else-if="state === 'resolving'">Đang tìm nguồn ảnh camera…</span>
            <span v-else-if="state === 'loading'">Đang nhận ảnh…</span>
            <span v-else-if="state === 'ready'">Ảnh camera · Cập nhật mỗi 20 giây</span>
            <span v-else-if="state === 'offline'">Không nhận được ảnh mới · Tự thử lại sau 20 giây</span>
            <span v-else-if="state === 'error'">Chưa kết nối được trang nguồn</span>
            <span v-else-if="state === 'unsupported'">Chưa có nguồn ảnh để xem trong web</span>
            <span v-else>Đang chờ cập nhật</span>
        </div>
        <div class="camera-frame">
            <img v-if="frame" :src="frame" :alt="`Camera ${camera.name}`" referrerpolicy="no-referrer">
            <div v-else class="camera-placeholder">
                <span class="camera-mark">CCTV / {{ camera.id }}</span>
                <p v-if="state === 'offline'">Camera có thể tạm ngừng hoặc nguồn ảnh không truy cập được.</p>
                <p v-else-if="state === 'unsupported'">Camera này chưa có liên kết ảnh gốc được hỗ trợ. Bạn vẫn có thể xem trên trang nguồn.</p>
                <p v-else-if="state === 'error'">Nguồn phản hồi lỗi hoặc quá thời gian chờ. Hãy thử lại.</p>
                <p v-else>Đang kết nối {{ camera.name }}…</p>
            </div>
            <span v-if="frame && (state === 'offline' || paused)" class="camera-stale">Ảnh cũ · {{ paused ? 'Đã tạm dừng' : 'Mất kết nối' }}</span>
        </div>
        <div class="camera-controls">
            <template v-if="source?.type === 'snapshot'">
                <button class="select" @click="togglePause">{{ paused ? 'Tiếp tục' : 'Tạm dừng' }}</button>
                <button class="select" :disabled="paused || state === 'loading'" @click="refresh">Làm mới ảnh</button>
            </template>
            <button v-if="state === 'error'" class="select" @click="resolve">Thử kết nối lại</button>
            <a :href="camera.page_url" target="_blank" rel="noopener noreferrer">Trang nguồn ↗</a>
        </div>
        <p v-if="source?.type === 'snapshot'" class="camera-caption">
            Nguồn ảnh: {{ source.source }}. Đây là ảnh chụp cập nhật, không phải video liên tục.
            <span v-if="receivedAt">Nhận ảnh lúc {{ receivedAt }} (giờ trên máy); không phải thời gian camera chụp ảnh.</span>
        </p>
    </div>
</template>

<style scoped>
.camera-status{display:flex;align-items:center;gap:8px;min-height:40px;font-size:12px;color:var(--muted);line-height:1.6}
.camera-dot{width:7px;height:7px;border-radius:50%;background:#e9b35f;flex-shrink:0}.camera-dot.active{background:var(--cyan);box-shadow:0 0 10px #5eead44d}
.camera-frame{position:relative;background:#070e15;border:1px solid var(--line);border-radius:10px;overflow:hidden;aspect-ratio:4/3;display:grid;align-items:center}
.camera-frame img{display:block;width:100%;height:100%;max-height:100%;object-fit:contain;min-height:0}
.camera-placeholder{text-align:center;padding:24px;color:var(--muted);font-size:13px;line-height:1.8}.camera-mark{font:12px 'DM Mono',monospace;letter-spacing:.15em;color:var(--cyan)}
.camera-stale{position:absolute;bottom:10px;left:10px;background:#0b1118e8;color:#f4c175;padding:7px 10px;border-radius:5px;font-size:12px}
.camera-controls{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-top:14px}.camera-controls button{min-height:44px}.camera-controls a{color:var(--cyan);font-size:12px;margin-left:auto}
.camera-caption{font-size:12px;color:var(--muted);line-height:1.8;margin-bottom:0}.camera-caption span{display:block}
.camera-mark{font-weight:var(--font-weight-body)}
.camera-status,.camera-stale,.camera-controls a{font-weight:var(--font-weight-label)}
</style>
