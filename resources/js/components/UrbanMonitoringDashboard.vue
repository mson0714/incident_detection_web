<script setup>
import { computed, ref } from 'vue';
import CctvLiveViewer from './CctvLiveViewer.vue';
import CameraOverviewMap from './CameraOverviewMap.vue';
import { demoEvents, eventTypes, filterEvents, overviewCameras } from '../lib/monitoring';

defineEmits(['open-cctv', 'open-incident']);
const filter = ref('all');
const view = ref('camera');
const camera = ref(overviewCameras[0]);
const expanded = ref(null);
const events = computed(() => filterEvents(demoEvents, filter.value));
const typeOf = (id) => eventTypes.find((type) => type.id === id);
function selectCamera(id) {
    camera.value = overviewCameras.find((item) => item.id === id) ?? overviewCameras[0];
    view.value = 'camera';
}
const paths = {
    accident: 'M12 3 2 21h20L12 3Zm0 6v5m0 3v1',
    helmet: 'M4 14a8 8 0 0 1 16 0v3H4v-3Zm8-8v8M3 17h18M7 20h10',
    traffic: 'M8 2h8v20H8zM11 6h2m-2 6h2m-2 6h2M4 5l4 3m8 0 4-3M4 12l4 3m8 0 4-3',
    water: 'M3 16c3-4 3 4 6 0s3 4 6 0 3 4 6 0M3 21c3-4 3 4 6 0s3 4 6 0 3 4 6 0M12 2s-4 5-4 7a4 4 0 0 0 8 0c0-2-4-7-4-7Z',
};
</script>

<template>
    <div class="monitoring editorial-theme">
        <div class="monitoring-intro">
            <div><span class="control-kicker">URBAN OPERATIONS / CONTROL ROOM</span><p>Điều hành đô thị bằng dữ liệu CCTV và 4 mô hình AI chuyên trách.</p></div>
            <span class="workspace-tag"><span></span> LOCAL COMMAND CENTER</span>
        </div>
        <div class="demo-disclosure" role="note"><b>OPERATING MODE</b><span>Giao diện đã sẵn sàng cho vận hành. Hiện các model hiển thị trạng thái chờ kết nối; camera công khai đang cung cấp ảnh cập nhật, chưa phải luồng video liên tục.</span><strong>4 AI / CCTV INPUT</strong></div>

        <section class="event-metrics" aria-label="Tổng quan sự kiện minh họa">
            <button v-for="(type, index) in eventTypes" :key="type.id" class="event-metric" :style="{ '--event-color': type.color, '--reveal-order': index }" :aria-label="`Xem chi tiết ${type.label}`" @click="$emit('open-incident', type.id)">
                <div class="metric-top"><span>{{ type.label }}</span><svg viewBox="0 0 24 24" aria-hidden="true"><path :d="paths[type.icon]" /></svg></div>
                <div class="metric-number">{{ String(type.count).padStart(2, '0') }}<span>{{ type.unit }}</span></div>
                <div class="metric-bottom"><span>{{ type.description }}</span><span aria-hidden="true">↗</span></div>
            </button>
        </section>

        <section class="model-rail" aria-label="Bốn mô hình AI hỗ trợ quản lý đô thị">
            <div class="model-rail-heading"><div><span class="section-number">01 / AI CAPABILITY MAP</span><h2>4 mô hình AI · 1 trung tâm điều hành</h2></div><span class="model-rail-status"><i></i> Chờ kết nối inference</span></div>
            <div class="model-rail-grid">
                <button v-for="(type, index) in eventTypes" :key="type.id" class="model-card" :style="{ '--event-color': type.color, '--reveal-order': index }" @click="$emit('open-incident', type.id)">
                    <span class="model-card-index">0{{ index + 1 }}</span><span class="model-card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path :d="paths[type.icon]" /></svg></span><strong>{{ type.model }}</strong><small>INPUT · {{ type.input }}</small><em>OUTPUT · {{ type.output }}</em><span class="model-card-link">Mở workspace <b>↗</b></span>
                </button>
            </div>
        </section>

        <section class="monitor-panel overview-panel" aria-labelledby="camera-overview-title">
            <header class="monitor-panel-head">
                <div><span class="section-number">02 / CCTV OBSERVATION</span><h2 id="camera-overview-title">Nguồn quan sát đô thị</h2><p class="panel-heading-note">Chọn camera làm đầu vào để theo dõi, xác minh và chuyển sự kiện đến đúng model.</p></div>
                <div class="view-switch" role="group" aria-label="Chế độ quan sát">
                    <button :aria-pressed="view === 'camera'" @click="view = 'camera'">Camera</button>
                    <button :aria-pressed="view === 'map'" @click="view = 'map'">Map</button>
                </div>
            </header>
            <div class="observation-layout">
                <div class="observation-stage">
                    <div class="stage-heading"><strong>{{ view === 'camera' ? camera.name : 'TP. Hồ Chí Minh' }}</strong><span>{{ view === 'camera' ? `CAM / ${camera.id}` : 'CAMERA LOCATIONS' }}</span></div>
                    <CctvLiveViewer v-if="view === 'camera'" :key="camera.id" :camera="camera" />
                    <CameraOverviewMap v-else :cameras="overviewCameras" @select="selectCamera" />
                </div>
                <aside class="camera-directory" aria-label="Camera quan sát nhanh">
                    <div class="directory-heading">ĐIỂM QUAN SÁT <span>02</span></div>
                    <button v-for="(item, index) in overviewCameras" :key="item.id" :class="{ selected: camera.id === item.id }" :aria-pressed="camera.id === item.id" @click="selectCamera(item.id)">
                        <span class="camera-index">0{{ index + 1 }}</span><span><strong>{{ item.name }}</strong><small>{{ item.region }}</small><em>Ảnh cập nhật · 20 giây</em></span>
                    </button>
                    <div class="directory-note"><span class="directory-rule"></span><p>Quan sát tại nguồn.<br>Chưa chạy nhận diện AI trên ảnh camera.</p></div>
                    <button class="all-cameras" @click="$emit('open-cctv')">Mở danh mục CCTV <span aria-hidden="true">↗</span></button>
                </aside>
            </div>
        </section>

        <div class="monitoring-bottom">
            <section class="monitor-panel recent-events" aria-labelledby="recent-events-title">
                <header class="monitor-panel-head"><div><span class="section-number">03 / EVENT LOG</span><h2 id="recent-events-title">Luồng cảnh báo gần đây</h2></div><span class="demo-label">UI SAMPLE</span></header>
                <div class="event-filter-bar"><span>{{ filter === 'all' ? 'Tất cả loại sự kiện' : typeOf(filter).description }}</span><button v-if="filter !== 'all'" @click="filter = 'all'">Xóa bộ lọc ×</button><span v-else>4 bản ghi mẫu</span></div>
                <ul class="monitor-event-list">
                    <li v-for="event in events" :key="event.id" :style="{ '--event-color': typeOf(event.type).color }">
                        <button :aria-expanded="expanded === event.id" :aria-controls="`detail-${event.id}`" @click="expanded = expanded === event.id ? null : event.id">
                            <i aria-hidden="true"></i><span class="event-name">{{ typeOf(event.type).label }}<small>{{ event.camera }}</small></span><span class="event-camera">{{ event.camera }}</span><time>{{ event.time }}</time><span class="event-chevron" aria-hidden="true">{{ expanded === event.id ? '−' : '+' }}</span>
                        </button>
                        <div v-if="expanded === event.id" :id="`detail-${event.id}`" class="event-explanation">{{ event.note }} {{ event.id }} · Mã CAM trong mockup không liên kết với camera công khai phía trên.</div>
                    </li>
                </ul>
                <footer class="event-footer">Mốc giờ minh họa · Chọn sự kiện để xem chi tiết</footer>
            </section>

            <section class="monitor-panel system-status" aria-labelledby="system-status-title">
                <header class="monitor-panel-head"><div><span class="section-number">04 / AI SERVICES</span><h2 id="system-status-title">Trạng thái hệ thống</h2></div><span class="service-count">0/4</span></header>
                <ul class="service-list"><li v-for="type in eventTypes" :key="type.id" @click="$emit('open-incident', type.id)"><span><b class="service-index">{{ type.id.slice(0, 3).toUpperCase() }}</b>{{ type.model }}</span><span class="service-state">Chờ kết nối<i aria-hidden="true"></i></span></li></ul>
                <div class="system-note"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l3-7 4 14 3-7h4" /></svg><span>Luồng chuẩn: CCTV → AI inference → cảnh báo → xác minh → nhật ký điều hành.<br>Chưa hiển thị trạng thái online giả.</span></div>
            </section>
        </div>
        <footer class="monitoring-footer"><span>AIRSCOPE / URBAN INTELLIGENCE</span><span>Camera thật · Sự kiện minh họa</span></footer>
    </div>
</template>

<style scoped>
.monitoring{--monitor-surface:#111c27;--monitor-border:#293a49;animation:monitor-reveal .45s ease both}.monitoring-intro{display:flex;justify-content:space-between;gap:16px;align-items:center;margin:-12px 0 20px}.monitoring-intro p{color:var(--muted);margin:0;font-size:13px}.workspace-tag{font:10px 'DM Mono',monospace;letter-spacing:.08em;display:flex;align-items:center;gap:8px;color:#9cb1c1}.workspace-tag>span{height:6px;width:6px;border-radius:50%;background:var(--cyan)}
.control-kicker{display:block;color:var(--cyan);font:10px 'DM Mono',monospace;letter-spacing:.13em;margin-bottom:7px}.demo-disclosure{display:flex;align-items:center;gap:12px;padding:12px 15px;margin-bottom:20px;border:1px solid #3f504c;border-radius:8px;background:linear-gradient(90deg,#172a2c,#18231f);color:#b8caca;font-size:11px;line-height:1.7}.demo-disclosure b{flex-shrink:0;font:500 10px 'DM Mono',monospace;color:#8fe5d3;border-right:1px solid #3d665e;padding-right:12px}.demo-disclosure strong{margin-left:auto;white-space:nowrap;color:#89cfc1;font:10px 'DM Mono',monospace;letter-spacing:.06em}
.event-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}.event-metric{--event-color:var(--cyan);position:relative;text-align:left;color:var(--ink);background:linear-gradient(120deg,#182532,#101922);border:1px solid var(--monitor-border);border-top:2px solid var(--event-color);border-radius:10px;padding:20px;min-width:0;animation:monitor-reveal .5s ease both;animation-delay:calc(var(--reveal-order)*60ms)}.event-metric:hover,.event-metric.chosen{border-color:var(--event-color);background:linear-gradient(120deg,#223342,#14212c)}.metric-top{display:flex;align-items:center;justify-content:space-between;font-size:14px;font-weight:600;gap:8px}.metric-top svg{width:23px;height:23px;fill:none;stroke:var(--event-color);stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}.metric-number{font:500 clamp(32px,3.5vw,46px) 'DM Mono',monospace;letter-spacing:-.07em;color:var(--event-color);margin:17px 0 14px;line-height:1}.metric-number>span{font:12px 'Plus Jakarta Sans',sans-serif;color:#8ea2b2;letter-spacing:0;margin-left:12px}.metric-bottom{display:flex;justify-content:space-between;align-items:center;gap:6px;font-size:11px;color:#91a5b6}.metric-bottom>span:last-child{color:var(--event-color);font-size:17px}
.model-rail{border:1px solid var(--monitor-border);border-radius:12px;background:#0f1b25;margin-bottom:22px;overflow:hidden}.model-rail-heading{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:17px 20px;border-bottom:1px solid var(--monitor-border)}.model-rail-heading h2{font-size:15px;margin:5px 0 0;letter-spacing:-.02em}.model-rail-status{color:#94a9b7;font:10px 'DM Mono',monospace;white-space:nowrap}.model-rail-status i{display:inline-block;width:6px;height:6px;border-radius:50%;background:#d2a563;margin-right:5px}.model-rail-grid{display:grid;grid-template-columns:repeat(4,1fr)}.model-card{--event-color:var(--cyan);position:relative;text-align:left;padding:17px 18px 15px;border:0;border-right:1px solid var(--monitor-border);background:linear-gradient(140deg,#142531,#101b25);color:var(--ink);min-width:0;animation:monitor-reveal .45s ease both;animation-delay:calc(var(--reveal-order)*50ms)}.model-card:last-child{border-right:0}.model-card:hover{background:linear-gradient(140deg,#1a3438,#12232d)}.model-card-index{position:absolute;right:15px;top:14px;color:#5e7486;font:10px 'DM Mono',monospace}.model-card-icon{display:grid;place-items:center;width:30px;height:30px;border:1px solid color-mix(in srgb,var(--event-color),transparent 50%);border-radius:7px;color:var(--event-color);margin-bottom:12px}.model-card-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}.model-card strong{display:block;font-size:12px;font-weight:600}.model-card small,.model-card em{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.model-card small{color:#88a0af;font:9px 'DM Mono',monospace;margin-top:12px}.model-card em{font-style:normal;color:#bdd0d9;font-size:10px;margin-top:7px}.model-card-link{display:block;color:var(--event-color);font-size:10px;margin-top:14px}.model-card-link b{font-size:14px;font-weight:400;margin-left:4px}.panel-heading-note{color:#89a0b0;font-size:10px;margin:6px 0 0;line-height:1.6}
.monitor-panel{border:1px solid var(--monitor-border);border-radius:12px;background:linear-gradient(130deg,#14212dcc,#0f1822f5);overflow:hidden;min-width:0}.monitor-panel-head{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:20px 22px;border-bottom:1px solid var(--monitor-border)}.section-number{font:9px 'DM Mono',monospace;letter-spacing:.12em;color:#7e96aa}.monitor-panel-head h2{margin:5px 0 0;font-size:17px;font-weight:600;letter-spacing:-.03em}.view-switch{display:flex;padding:4px;background:#0a141e;border:1px solid #253847;border-radius:8px;gap:3px}.view-switch button{min-height:36px;border:0;border-radius:5px;background:none;padding:6px 17px;font-size:12px;color:#91a5b6}.view-switch button[aria-pressed=true]{background:#28423f;color:#9aefdf}
.observation-layout{display:grid;grid-template-columns:minmax(0,1fr) 245px}.observation-stage{padding:18px 22px;min-width:0}.stage-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:5px}.stage-heading strong{font-weight:500;font-size:13px}.stage-heading>span{font:10px 'DM Mono',monospace;color:#8097ab}.observation-stage :deep(.camera-frame){aspect-ratio:16/9;max-height:370px;background:#070e15}.observation-stage :deep(.camera-caption){font-size:10px;line-height:1.6}.observation-stage :deep(.camera-status){font-size:11px;min-height:30px}.observation-stage :deep(.camera-controls){margin-top:10px}.observation-stage :deep(.camera-controls .select){font-size:11px;min-height:36px;padding:6px 10px}
.camera-directory{display:flex;flex-direction:column;gap:10px;padding:22px 16px;background:#0b151e80;border-left:1px solid var(--monitor-border)}.directory-heading{display:flex;justify-content:space-between;font:10px 'DM Mono',monospace;letter-spacing:.05em;color:#91a5b6;margin-bottom:8px}.directory-heading>span{color:var(--cyan)}.camera-directory>button{display:flex;gap:10px;text-align:left;border:1px solid #283847;border-radius:8px;padding:14px 10px;background:#14202b;color:var(--ink)}.camera-directory>button.selected{border-color:#4c8b83;background:linear-gradient(120deg,#1a3938,#12242e)}.camera-index{font:10px 'DM Mono',monospace;color:#84a6b5;padding-top:3px}.camera-directory strong{display:block;font-size:12px;font-weight:500;line-height:1.7}.camera-directory small{display:block;font-size:10px;color:#8fa3b3;margin-top:4px}.camera-directory em{display:block;font-size:10px;color:#8bb8b4;font-style:normal;margin-top:12px}.directory-note{margin-top:auto;padding-top:28px}.directory-rule{display:block;width:25px;height:2px;background:#496473}.directory-note p{font-size:11px;color:#7e96a8;line-height:1.9}.camera-directory>.all-cameras{justify-content:space-between;align-items:center;color:var(--cyan);font-size:11px;background:transparent;border-color:#335653;min-height:44px}
.monitoring-bottom{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,1fr);gap:20px;margin-top:22px}.demo-label{font:9px 'DM Mono',monospace;color:#8fe5d3;border:1px solid #38655f;border-radius:4px;padding:4px 7px}.event-filter-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 22px;color:#889dac;font-size:11px;border-bottom:1px solid #243442}.event-filter-bar button{border:0;background:none;color:var(--cyan);font-size:11px;min-height:24px}.monitor-event-list,.service-list{list-style:none;margin:0;padding:0 22px}.monitor-event-list li{border-bottom:1px solid #243442}.monitor-event-list li:last-child{border:0}.monitor-event-list li>button{width:100%;display:grid;grid-template-columns:8px 1fr 70px 52px 14px;align-items:center;gap:13px;padding:16px 0;color:var(--ink);border:0;background:none;text-align:left}.monitor-event-list li>button:hover{color:var(--event-color)}.monitor-event-list i{display:block;width:7px;height:7px;border-radius:50%;background:var(--event-color);box-shadow:0 0 12px color-mix(in srgb,var(--event-color),transparent 65%)}.event-name{font-size:13px;font-weight:500}.event-name small{display:none}.event-camera,.monitor-event-list time{font:11px 'DM Mono',monospace;color:#98aaba}.event-chevron{color:#6c8295}.event-explanation{font-size:11px;line-height:1.8;color:#a2b5c3;background:#0b1721;padding:10px 12px;margin-bottom:12px;border-radius:5px}.event-footer{border-top:1px solid #243442;padding:12px 22px;font-size:10px;color:#8197a8}.service-count{font:14px 'DM Mono',monospace;color:#8399aa}.service-list li{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:16px 0;border-bottom:1px solid #243442;font-size:12px;cursor:pointer}.service-list li:last-child{border-bottom:0}.service-list li:hover{color:var(--cyan)}.service-index{display:inline-grid;place-items:center;width:27px;height:19px;margin-right:9px;border:1px solid #334c5c;border-radius:4px;color:#91aabb;font:9px 'DM Mono',monospace}.service-state{display:flex;align-items:center;gap:10px;font-size:10px;color:#92a5b4}.service-state i{display:block;width:7px;height:7px;background:#d2a563;border-radius:50%}.system-note{display:flex;gap:12px;align-items:center;border-top:1px solid #243442;padding:14px 22px;color:#8299aa;font-size:10px;line-height:1.8}.system-note svg{width:24px;height:24px;flex-shrink:0;stroke:#6d98a2;fill:none;stroke-width:1.5}.monitoring-footer{display:flex;justify-content:space-between;gap:15px;margin-top:20px;color:#6e8799;font:9px 'DM Mono',monospace;letter-spacing:.06em}
@keyframes monitor-reveal{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@media(prefers-reduced-motion:reduce){.monitoring,.event-metric{animation:none}}
@media(max-width:1200px){.observation-layout{grid-template-columns:minmax(0,1fr) 210px}.event-metric{padding:16px}.monitoring-intro{flex-wrap:wrap}.workspace-tag{font-size:9px}}
@media(max-width:1000px){.event-metrics,.model-rail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.model-card:nth-child(2){border-right:0}.model-card:nth-child(-n+2){border-bottom:1px solid var(--monitor-border)}.observation-layout{grid-template-columns:1fr}.camera-directory{border-left:0;border-top:1px solid var(--monitor-border);display:grid;grid-template-columns:1fr 1fr}.directory-heading,.all-cameras{grid-column:1/-1}.directory-note{display:none}.monitoring-bottom{grid-template-columns:1fr}.metric-number{font-size:38px}}
@media(max-width:600px){.monitor-panel-head{padding:16px;flex-wrap:wrap}.monitor-panel-head h2{font-size:16px}.observation-stage{padding:14px}.stage-heading{flex-wrap:wrap;gap:4px}.event-metrics{gap:10px}.event-metric{padding:14px}.metric-top{font-size:12px}.metric-top svg{width:19px;height:19px}.metric-number{font-size:34px}.metric-number>span{font-size:10px;margin-left:7px}.metric-bottom{font-size:10px}.demo-disclosure{align-items:start;flex-wrap:wrap}.demo-disclosure strong{margin-left:0}.workspace-tag{display:none}.camera-directory{padding:14px}.monitor-event-list,.service-list{padding:0 16px}.event-filter-bar{padding:12px 16px}.monitor-event-list li>button{grid-template-columns:8px 1fr 48px 14px;gap:10px}.event-camera{display:none}.event-name small{display:block;font:9px 'DM Mono',monospace;color:#879cae;margin-top:4px}.monitoring-footer{flex-wrap:wrap;font-size:8px}.monitoring-intro p{font-size:11px}.model-rail-heading{align-items:flex-start;flex-direction:column;gap:8px}.model-rail-grid{grid-template-columns:1fr}.model-card,.model-card:nth-child(2){border-right:0;border-bottom:1px solid var(--monitor-border)}.model-card:last-child{border-bottom:0}}

/* Reference treatment: the dashboard reads like an editorial operations journal. */
.monitoring.editorial-theme { color: #2c2c2c; }
.monitoring.editorial-theme .monitoring-intro p { color: #646464; }
.monitoring.editorial-theme .control-kicker,
.monitoring.editorial-theme .section-number { color: #0081c0; }
.monitoring.editorial-theme .workspace-tag { color: #646464; }
.monitoring.editorial-theme .workspace-tag > span { background: #41a1cf; }
.monitoring.editorial-theme .demo-disclosure { background: #f9faf7; border-color: #dee2de; color: #444141; }
.monitoring.editorial-theme .demo-disclosure b { color: #0081c0; border-color: #dee2de; }
.monitoring.editorial-theme .demo-disclosure strong { color: #0081c0; }
.monitoring.editorial-theme .event-metric,
.monitoring.editorial-theme .model-rail,
.monitoring.editorial-theme .monitor-panel { background: #ffffff; border-color: #dee2de; box-shadow: 0 1px 1px rgba(0,0,0,.08), 0 4px 5px rgba(0,0,0,.04); }
.monitoring.editorial-theme .event-metric:hover { background: #f9faf7; }
.monitoring.editorial-theme .metric-top,
.monitoring.editorial-theme .model-card strong,
.monitoring.editorial-theme .monitor-panel-head h2,
.monitoring.editorial-theme .stage-heading strong { color: #2c2c2c; }
.monitoring.editorial-theme .metric-bottom,
.monitoring.editorial-theme .panel-heading-note,
.monitoring.editorial-theme .model-card small,
.monitoring.editorial-theme .model-card em,
.monitoring.editorial-theme .model-rail-status,
.monitoring.editorial-theme .event-filter-bar,
.monitoring.editorial-theme .event-camera,
.monitoring.editorial-theme .monitor-event-list time,
.monitoring.editorial-theme .event-footer,
.monitoring.editorial-theme .service-state,
.monitoring.editorial-theme .system-note,
.monitoring.editorial-theme .monitoring-footer { color: #646464; }
.monitoring.editorial-theme .model-card { background: #ffffff; border-color: #dee2de; }
.monitoring.editorial-theme .model-card:hover { background: #f9faf7; }
.monitoring.editorial-theme .model-card-index { color: #b4b8b4; }
.monitoring.editorial-theme .model-card-link { color: #0081c0; }
.monitoring.editorial-theme .model-rail-heading,
.monitoring.editorial-theme .monitor-panel-head,
.monitoring.editorial-theme .event-filter-bar,
.monitoring.editorial-theme .system-note { border-color: #dee2de; }
.monitoring.editorial-theme .view-switch { background: #f9faf7; border-color: #dee2de; }
.monitoring.editorial-theme .view-switch button { color: #646464; }
.monitoring.editorial-theme .view-switch button[aria-pressed=true] { background: #ffffff; color: #0081c0; border: 1px solid #41a1cf; }
.monitoring.editorial-theme .camera-directory { background: #f9faf7; border-color: #dee2de; }
.monitoring.editorial-theme .camera-directory > button { background: #ffffff; color: #2c2c2c; border-color: #dee2de; }
.monitoring.editorial-theme .camera-directory > button.selected { background: #ffffff; border-color: #41a1cf; }
.monitoring.editorial-theme .camera-directory small,
.monitoring.editorial-theme .camera-directory em,
.monitoring.editorial-theme .directory-note p { color: #646464; }
.monitoring.editorial-theme .camera-directory > .all-cameras { color: #0081c0; border-color: #41a1cf; }
.monitoring.editorial-theme .monitor-event-list li { border-color: #dee2de; }
.monitoring.editorial-theme .monitor-event-list li > button { color: #2c2c2c; }
.monitoring.editorial-theme .event-explanation { background: #f9faf7; color: #444141; }
.monitoring.editorial-theme .service-list li { border-color: #dee2de; }
.monitoring.editorial-theme .service-list li:hover { color: #0081c0; }
.monitoring.editorial-theme .service-index { border-color: #b4b8b4; color: #646464; }
.monitoring.editorial-theme .system-note svg { stroke: #0081c0; }
.monitoring.editorial-theme .observation-stage :deep(.camera-status),
.monitoring.editorial-theme .observation-stage :deep(.camera-caption) { color: #646464; }

.monitoring.editorial-theme :is(.metric-top, .model-card strong, .model-rail-heading h2, .monitor-panel-head h2, .stage-heading strong, .camera-directory strong, .event-name) {
  font-weight: var(--font-weight-heading);
}
.monitoring.editorial-theme :is(.view-switch button, .all-cameras, .event-filter-bar button, .service-list li, .model-card-link) {
  font-weight: var(--font-weight-label);
}
.monitoring.editorial-theme :is(.workspace-tag, .control-kicker, .section-number, .demo-disclosure b, .demo-disclosure strong, .metric-number > span, .model-rail-status, .model-card-index, .model-card small, .stage-heading > span, .directory-heading, .camera-index, .demo-label, .event-name small, .event-camera, .monitor-event-list time, .service-count, .service-index, .monitoring-footer) {
  font-weight: var(--font-weight-body);
}
</style>
