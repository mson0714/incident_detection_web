<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useUrbanResource } from '../composables/useUrbanResource';
import { catalogUrl, isLocalVideo } from '../lib/cctv';
import CctvLiveViewer from './CctvLiveViewer.vue';

const catalog = useUrbanResource(null, (payload) => Array.isArray(payload?.data) && payload?.meta && Array.isArray(payload?.regions));
const query = ref('');
const region = ref('');
const applied = ref({ query: '', region: '' });
const regions = ref([]);
const selected = ref(null);
const mode = ref('source');
const videoUrl = ref('');
const videoName = ref('');
const videoError = ref('');
const rows = computed(() => catalog.state.data?.data ?? []);
const meta = computed(() => catalog.state.data?.meta ?? {});
const count = (value) => Number(value ?? 0).toLocaleString('vi-VN');

async function load(page = 1) {
    selected.value = null;
    await catalog.load(catalogUrl(applied.value.query, applied.value.region, page), { clear: true });
}

function search() {
    applied.value = { query: query.value, region: region.value };
    load();
}

function reset() {
    query.value = '';
    region.value = '';
    search();
}

function choose(camera) {
    selected.value = camera;
    mode.value = 'source';
}

function clearVideo() {
    if (videoUrl.value) URL.revokeObjectURL(videoUrl.value);
    videoUrl.value = '';
    videoName.value = '';
    videoError.value = '';
}

function openVideo(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    clearVideo();
    mode.value = 'local';
    if (isLocalVideo(file)) {
        videoUrl.value = URL.createObjectURL(file);
        videoName.value = file.name;
    } else {
        videoError.value = 'Hãy chọn video MP4, WebM hoặc OGV không rỗng.';
    }
    event.target.value = '';
}

watch(() => catalog.state.data, (payload) => {
    if (!payload) return;
    regions.value = payload.regions;
    selected.value = payload.data.find((camera) => camera.id === '209690') ?? payload.data[0] ?? null;
});
onMounted(() => load());
onBeforeUnmount(clearVideo);
</script>

<template>
    <div class="cctv-workspace">
        <section class="cctv-intro">
            <div>
                <div class="eyebrow">CAMERA EXPLORER / VIỆT NAM</div>
                <h2>Góc nhìn đường phố<span>.</span></h2>
                <p>Chọn camera để xem ảnh đường phố cập nhật ngay trong web.</p>
            </div>
            <div class="cctv-total" aria-label="Quy mô danh mục">
                <strong>{{ catalog.state.data ? count(meta.catalog_total) : '—' }}</strong>
                <span>trang camera trong danh mục</span>
                <small>Không phải số camera đang online</small>
            </div>
        </section>

        <p class="notice warn cctv-disclosure" role="note">
            <strong>Xem ảnh camera trực tiếp từ nguồn công khai.</strong>
            Camera có liên kết ảnh gốc TP.HCM được cập nhật mỗi 20 giây; không phải video liên tục.
            Chỉ camera đang chọn được tải. Một số điểm có thể offline hoặc chưa hỗ trợ xem trong web.
        </p>

        <div class="cctv-layout">
            <section class="card panel cctv-catalog" :aria-busy="catalog.state.loading" aria-label="Danh mục CCTV">
                <div class="panel-head"><div><h3 class="panel-title">Chọn điểm quan sát</h3><p class="panel-subtitle">Nguồn: OpenCCTV · Catalog {{ meta.catalog_updated_at ?? '2026-10-04' }}</p></div></div>
                <form class="cctv-filters" @submit.prevent="search">
                    <label class="cctv-search">Tìm tên đường hoặc mã camera
                        <input v-model="query" type="search" class="select" placeholder="Ví dụ: nguyen binh khiem" maxlength="120">
                    </label>
                    <label>Khu vực theo nguồn
                        <select v-model="region" class="select"><option value="">Tất cả khu vực</option><option v-for="item in regions" :key="item" :value="item">{{ item }}</option></select>
                    </label>
                    <button class="select cctv-primary" type="submit">Tìm camera</button>
                </form>
                <div class="cctv-result-line" role="status" aria-live="polite">
                    <span>{{ catalog.state.loading ? 'Đang tải danh mục…' : catalog.state.error ? 'Chưa tải được danh mục' : `${count(meta.total)} kết quả` }}</span>
                    <button v-if="applied.query || applied.region" class="link-button" @click="reset">Xóa bộ lọc</button>
                </div>
                <div v-if="catalog.state.error" class="notice error" role="alert">
                    Không tải được danh mục CCTV. Kiểm tra Laravel và file catalog.
                    <button class="link-button" @click="load()">Thử lại</button>
                </div>
                <div v-else-if="catalog.state.loading" class="cctv-empty">Đang đọc các điểm camera công khai…</div>
                <div v-else-if="!rows.length" class="cctv-empty">Không tìm thấy camera phù hợp. Thử tên đường ngắn hơn hoặc chọn tất cả khu vực.</div>
                <div v-else class="cctv-grid">
                    <button v-for="camera in rows" :key="camera.id" class="cctv-camera" :class="{ selected: selected?.id === camera.id }" :aria-pressed="selected?.id === camera.id" @click="choose(camera)">
                        <span class="cctv-camera-code">CAM / {{ camera.id }}</span>
                        <strong>{{ camera.name }}</strong>
                        <span class="cctv-camera-region">{{ camera.region }}</span>
                        <span class="cctv-camera-state">Mở camera <span aria-hidden="true">→</span></span>
                    </button>
                </div>
                <nav v-if="catalog.state.data" class="pager cctv-pager" aria-label="Phân trang CCTV">
                    <button :disabled="catalog.state.loading || meta.current_page <= 1" @click="load(meta.current_page - 1)">Trước</button>
                    <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
                    <button :disabled="catalog.state.loading || meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Sau</button>
                </nav>
            </section>

            <section class="card panel cctv-viewer" aria-label="Chi tiết camera và trình xem video">
                <div class="cctv-tabs" role="group" aria-label="Nguồn xem">
                    <button :aria-pressed="mode === 'source'" @click="mode = 'source'">Camera công khai</button>
                    <button :aria-pressed="mode === 'local'" @click="mode = 'local'">Video của bạn</button>
                </div>
                <template v-if="mode === 'source'">
                    <CctvLiveViewer v-if="selected" :key="selected.id" :camera="selected" />
                    <div v-else class="cctv-screen">
                        <div class="cctv-screen-mark" aria-hidden="true">CCTV</div>
                        <h3>Chọn một camera</h3>
                        <p>Chọn điểm quan sát trong danh mục để kết nối nguồn ảnh.</p>
                    </div>
                    <div v-if="selected" class="cctv-detail" aria-live="polite">
                        <div class="eyebrow">CAM / {{ selected.id }}</div>
                        <h3>{{ selected.name }}</h3>
                        <dl><div><dt>Khu vực theo catalog</dt><dd>{{ selected.region }}</dd></div><div><dt>Danh mục</dt><dd>{{ selected.source }}</dd></div></dl>
                    </div>
                </template>
                <template v-else>
                    <div class="cctv-local-header"><h3 class="panel-title">Xem video ngay trên local</h3><p class="panel-subtitle">Chọn video bạn sở hữu hoặc được phép sử dụng. File chỉ được phát trong trình duyệt, không upload.</p></div>
                    <video v-if="videoUrl" :key="videoUrl" :src="videoUrl" class="cctv-video" controls playsinline preload="metadata" aria-label="Video nghiên cứu cục bộ" @error="videoError = 'Trình duyệt không đọc được video này. Thử MP4 H.264 hoặc WebM.'"></video>
                    <div v-else class="cctv-screen cctv-local-empty"><div class="cctv-screen-mark" aria-hidden="true">LOCAL</div><h3>Video của bạn, trên máy bạn.</h3><p>MP4 · WebM · OGV<br>Không tự tải, lưu frame hay gửi đến AI.</p></div>
                    <label class="cctv-file">Chọn video cục bộ<input type="file" accept="video/mp4,video/webm,video/ogg,.mp4,.webm,.ogv" @change="openVideo"></label>
                    <p v-if="videoName" class="cctv-filename">{{ videoName }}</p>
                    <p v-if="videoError" class="notice error" role="alert">{{ videoError }}</p>
                    <button v-if="videoUrl" class="link-button" @click="clearVideo">Đóng video</button>
                </template>
                <div class="cctv-footnote">Dùng cho nghiên cứu có trách nhiệm. Xem công khai không đồng nghĩa được tải, huấn luyện hoặc phân phối lại. Tên khu vực giữ theo nguồn catalog.</div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.cctv-intro{display:flex;justify-content:space-between;align-items:center;gap:24px;padding:12px 0 24px}
.cctv-intro h2{font-size:clamp(26px,3.3vw,40px);letter-spacing:-.045em;line-height:1.2;margin:12px 0}
.cctv-intro h2 span{color:var(--cyan)}
.cctv-intro p{margin:0;color:var(--muted);max-width:42rem}
.cctv-total{flex-shrink:0;display:grid;gap:4px;border-left:1px solid var(--line);padding-left:26px}
.cctv-total strong{font:500 36px 'DM Mono',monospace;color:var(--cyan)}
.cctv-total span{font-size:13px}.cctv-total small{color:var(--muted);font-size:12px}
.cctv-disclosure{line-height:1.7}.cctv-disclosure strong{display:block}
.cctv-layout{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(310px,.85fr);gap:18px;align-items:start}
.cctv-catalog,.cctv-viewer{min-width:0}
.cctv-filters{display:flex;flex-wrap:wrap;align-items:end;gap:10px}
.cctv-filters label{display:grid;gap:7px;font-size:12px;color:var(--muted);min-width:0;flex:1 1 170px}
.cctv-filters .cctv-search{flex-basis:100%}.cctv-filters .select{width:100%}
.cctv-filters button{flex:0 0 auto;width:auto!important}
.cctv-primary{display:inline-flex;align-items:center;justify-content:center;color:#0b1118;background:var(--cyan);border-color:var(--cyan);font-weight:700;text-decoration:none;text-align:center}
.cctv-primary:hover{background:#a1f4e5}.cctv-primary:focus-visible{outline:3px solid var(--blue);outline-offset:4px}
.cctv-result-line{display:flex;justify-content:space-between;align-items:center;gap:12px;min-height:54px;color:var(--muted);font-size:12px}
.cctv-result-line .link-button{padding:0}
.cctv-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.cctv-camera{display:flex;flex-direction:column;align-items:start;gap:8px;text-align:left;padding:15px;border-radius:9px;border:1px solid var(--line);background:#0e1822;color:var(--ink);min-width:0;overflow-wrap:anywhere}
.cctv-camera:hover{border-color:#4d7c80}.cctv-camera.selected{border-color:var(--cyan);background:linear-gradient(135deg,#133637,#111d27)}
.cctv-camera strong{font-size:14px;line-height:1.5;font-weight:600}.cctv-camera-code{font:11px 'DM Mono',monospace;color:#9aadbd}
.cctv-camera-region{color:var(--muted);font-size:12px}.cctv-camera-state{font-size:12px;color:var(--cyan);margin-top:auto;padding-top:6px;width:100%;display:flex;justify-content:space-between}
.cctv-pager{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;font-size:12px}
.cctv-empty{padding:36px 14px;color:var(--muted);line-height:1.7;text-align:center}
.cctv-tabs{display:flex;gap:6px;background:#0a131c;padding:5px;border-radius:9px;margin-bottom:18px}
.cctv-tabs button{flex:1;border:0;border-radius:6px;padding:11px 6px;background:transparent;color:var(--muted);font-size:13px;min-height:44px}
.cctv-tabs button[aria-pressed=true]{background:#243d43;color:var(--cyan)}
.cctv-screen{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:280px;padding:28px 18px;text-align:center;background:radial-gradient(ellipse at top,#173f48,transparent 75%),repeating-linear-gradient(0deg,transparent,transparent 23px,#ffffff04 24px),#0b141d;border:1px solid var(--line);border-radius:10px;gap:12px}
.cctv-screen-mark{font:500 15px 'DM Mono',monospace;letter-spacing:.2em;color:#99b3bf;border:1px solid #3d5a68;border-radius:5px;padding:12px}
.cctv-screen h3{font-size:18px;line-height:1.5;margin:6px 0 0}.cctv-screen p{color:#a2b4c0;font-size:13px;line-height:1.7;margin:0 0 6px;max-width:28rem}
.cctv-detail{padding-top:24px}.cctv-detail h3{font-size:21px;letter-spacing:-.03em;margin:8px 0 18px;overflow-wrap:anywhere}
.cctv-detail dl{margin:0;display:grid;gap:12px}.cctv-detail dl>div{display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);padding-bottom:10px;font-size:12px}
.cctv-detail dt{color:var(--muted)}.cctv-detail dd{margin:0;text-align:right}
.cctv-footnote{margin-top:24px;padding-top:16px;border-top:1px solid var(--line);font-size:12px;color:var(--muted);line-height:1.7}
.cctv-local-header{margin-bottom:16px}.cctv-video{width:100%;aspect-ratio:16/9;background:#050a10;border-radius:8px}
.cctv-file{display:grid;gap:10px;font-size:13px;margin-top:18px}.cctv-file input{width:100%;color:var(--muted);font:inherit;min-height:44px}
.cctv-file input::file-selector-button{border:1px solid #3d556b;background:#163234;color:var(--cyan);padding:12px;margin-right:12px;border-radius:6px;cursor:pointer}
.cctv-filename{overflow-wrap:anywhere;color:var(--cyan);font-size:13px}
@media(min-width:1350px){.cctv-viewer{position:sticky;top:20px}}
@media(max-width:1100px){.cctv-layout{grid-template-columns:1fr}.cctv-viewer{grid-row:1}.cctv-intro{flex-wrap:wrap}.cctv-total{border-left:0;padding-left:0}.cctv-screen{min-height:240px}}
@media(max-width:480px){.cctv-grid{grid-template-columns:1fr}.cctv-tabs button{font-size:12px}.cctv-intro{gap:20px}.cctv-total strong{font-size:28px}.cctv-screen{padding:22px 12px}.cctv-detail dl>div{flex-wrap:wrap}.cctv-pager{justify-content:center}}

.cctv-intro h2,
.cctv-screen h3,
.cctv-detail h3,
.cctv-camera strong {
  font-weight: var(--font-weight-heading);
}
.cctv-filters label,
.cctv-tabs button,
.cctv-detail dt,
.cctv-file {
  font-weight: var(--font-weight-label);
}
.cctv-camera-code {
  font-weight: var(--font-weight-body);
}
</style>
