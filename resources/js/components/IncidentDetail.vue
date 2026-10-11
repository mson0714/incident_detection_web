<script setup>
import { computed, ref } from 'vue';
import { eventTypes } from '../lib/monitoring';
import { createIncidentFixtures, filterIncidents, incidentProfiles, reviewStates, severityLabels } from '../lib/incidentDetails';
import IncidentEvidence from './IncidentEvidence.vue';

const props = defineProps({ typeId: { type: String, required: true } });
defineEmits(['back', 'open-type']);
const type = computed(() => eventTypes.find((item) => item.id === props.typeId));
const profile = computed(() => incidentProfiles[props.typeId]);
const records = ref(createIncidentFixtures(props.typeId));
const selectedId = ref(records.value[0]?.id);
const query = ref('');
const status = ref('');
const severity = ref('');
const tab = ref('evidence');
const message = ref('');
const activities = ref([]);
const checks = ref({});
const notes = ref({});
const rows = computed(() => filterIncidents(records.value, { query: query.value, status: status.value, severity: severity.value }));
const selected = computed(() => records.value.find((row) => row.id === selectedId.value));
const pendingCount = computed(() => records.value.filter((row) => row.status === 'pending').length);
const resolvedCount = computed(() => records.value.filter((row) => row.status === 'resolved').length);
const currentActivities = computed(() => activities.value.filter((item) => item.id === selectedId.value));
const checkedCount = computed(() => (checks.value[selectedId.value] ?? []).length);

function choose(row) { selectedId.value = row.id; message.value = ''; }
function resetFilters() { query.value = ''; status.value = ''; severity.value = ''; }
function toggleCheck(index, checked) {
    const next = new Set(checks.value[selectedId.value] ?? []);
    if (checked) next.add(index); else next.delete(index);
    checks.value[selectedId.value] = [...next];
}
function updateStatus(next) {
    if (!selected.value || selected.value.status === next) return;
    if (next === 'resolved' && checkedCount.value !== profile.value.checklist.length) return;
    selected.value.status = next;
    activities.value.unshift({ id: selectedId.value, time: new Date().toLocaleTimeString('vi-VN'), text: `Đổi trạng thái mẫu: ${reviewStates[next]}` });
    message.value = 'Đã cập nhật trong phiên UI demo. Không gửi cảnh báo hay lưu vào hệ thống thật.';
}
function saveNote() {
    const value = notes.value[selectedId.value]?.trim();
    if (!value) return;
    activities.value.unshift({ id: selectedId.value, time: new Date().toLocaleTimeString('vi-VN'), text: `Ghi chú mẫu: ${value}` });
    notes.value[selectedId.value] = '';
    message.value = 'Đã thêm ghi chú vào nhật ký demo; dữ liệu sẽ mất khi rời trang.';
}
</script>

<template>
    <div class="incident-page" :style="{ '--accent': type.color }">
        <div class="incident-breadcrumb"><button @click="$emit('back')">← Tổng quan</button><span>/</span><span>Chi tiết cảnh báo</span><span>/</span><strong>{{ type.label }}</strong></div>
        <header class="incident-hero"><div><span class="incident-kicker">{{ profile.code }} / INCIDENT WORKSPACE</span><h2>{{ type.description }}<span>.</span></h2><p>{{ profile.intro }}</p></div><div class="hero-model"><i></i><span>{{ type.model }}<small>Chưa kết nối suy luận</small></span></div></header>
        <nav class="incident-type-tabs" aria-label="Nhóm cảnh báo"><button v-for="item in eventTypes" :key="item.id" :aria-current="item.id === typeId ? 'page' : undefined" :style="{ '--type-color': item.color }" @click="$emit('open-type', item.id)"><i></i>{{ item.label }}<span>{{ String(item.count).padStart(2, '0') }}</span></button></nav>
        <p class="incident-demo" role="note"><b>UI DEMO</b> Toàn bộ bản ghi, thời gian, mức độ và sơ đồ bên dưới là minh họa. Thao tác chỉ tồn tại trên trang này, sẽ đặt lại khi rời trang hoặc tải lại. Không phải kết luận AI hay bằng chứng vi phạm.</p>
        <section class="incident-stats" aria-label="Thống kê cảnh báo mẫu"><div><span>{{ typeId === 'flood' ? 'Vị trí theo dõi' : 'Tổng sự kiện' }}</span><strong>{{ String(records.length).padStart(2, '0') }}<small>MẪU</small></strong></div><div><span>Chờ xác minh</span><strong>{{ String(pendingCount).padStart(2, '0') }}</strong></div><div><span>Đã xử lý</span><strong>{{ String(resolvedCount).padStart(2, '0') }}</strong></div><div><span>Bằng chứng thực tế</span><strong>—<small>CHƯA KẾT NỐI</small></strong></div></section>

        <div class="incident-workspace">
            <section class="incident-list-panel" aria-labelledby="incident-list-title">
                <div class="incident-panel-heading"><div><span class="incident-kicker">01 / RECORDS</span><h3 id="incident-list-title">{{ typeId === 'flood' ? 'Danh sách điểm ngập' : 'Danh sách sự kiện' }}</h3></div><span class="small-count">{{ rows.length }}/{{ records.length }}</span></div>
                <form class="incident-filters" @submit.prevent><label>Tìm cảnh báo<input v-model="query" type="search" placeholder="Mã, camera, vị trí…" maxlength="100"></label><div><label>Trạng thái<select v-model="status"><option value="">Tất cả</option><option v-for="(label, key) in reviewStates" :key="key" :value="key">{{ label }}</option></select></label><label>Mức độ<select v-model="severity"><option value="">Tất cả</option><option v-for="(label, key) in severityLabels" :key="key" :value="key">{{ label }}</option></select></label></div></form>
                <div class="incident-list-summary" role="status">{{ rows.length }} kết quả · 05/10/2026 (mẫu)<button v-if="query || status || severity" @click="resetFilters">Xóa lọc</button></div>
                <ul class="incident-records"><li v-for="row in rows" :key="row.id"><button :aria-pressed="selectedId === row.id" @click="choose(row)"><div><span class="record-id">{{ row.id }}</span><time>{{ row.time }}</time></div><strong>{{ row.location }}</strong><div><span>{{ row.camera }} · {{ severityLabels[row.severity] }}</span><span class="record-state" :class="row.status">{{ reviewStates[row.status] }}</span></div></button></li></ul>
                <div v-if="!rows.length" class="incident-empty"><strong>Không có cảnh báo phù hợp</strong><p>Thử tên ngắn hơn hoặc xóa bộ lọc.</p><button @click="resetFilters">Xóa bộ lọc</button></div>
                <p class="list-footnote">Mã CAM là mã giả lập, không ánh xạ tới camera công khai.</p>
            </section>

            <section v-if="selected" class="incident-inspector" aria-labelledby="incident-detail-title">
                <header class="inspector-heading"><div><span class="incident-kicker">02 / EVENT DETAIL</span><h3 id="incident-detail-title">{{ selected.id }}</h3><p>{{ selected.location }} · {{ selected.camera }} · {{ selected.time }}</p></div><span class="record-state" :class="selected.status">{{ reviewStates[selected.status] }}</span></header>
                <p v-if="!rows.some((row) => row.id === selectedId)" class="selection-note">Bản ghi đang xem nằm ngoài bộ lọc hiện tại.</p>
                <div class="inspector-tabs" role="group" aria-label="Nội dung chi tiết"><button :aria-pressed="tab === 'evidence'" @click="tab = 'evidence'">Quan sát & xác minh</button><button :aria-pressed="tab === 'history'" @click="tab = 'history'">Nhật ký <span>{{ currentActivities.length + 1 }}</span></button></div>
                <div v-if="tab === 'evidence'" class="inspector-body">
                    <IncidentEvidence :type="typeId" :camera="selected.camera" />
                    <div class="incident-scenario"><div><h4>{{ profile.evidenceLabel }}</h4><p>{{ profile.scenario }}</p></div><div class="scenario-measurement"><span>{{ profile.measurement }}</span><strong>{{ profile.value }}</strong><small>{{ profile.unit }}</small></div></div>
                    <dl class="incident-facts"><div v-for="[label, value] in profile.facts" :key="label"><dt>{{ label }}</dt><dd>{{ value }}</dd></div><div><dt>Độ tin cậy AI</dt><dd>Chưa có kết quả</dd></div><div><dt>Thời gian mẫu</dt><dd>05/10/2026 · {{ selected.time }}</dd></div></dl>
                    <section class="verification"><header><h4>Checklist xác minh</h4><span>{{ checkedCount }}/{{ profile.checklist.length }}</span></header><label v-for="(item, index) in profile.checklist" :key="item"><input type="checkbox" :checked="(checks[selectedId] ?? []).includes(index)" @change="toggleCheck(index, $event.target.checked)"><span>{{ item }}</span></label></section>
                </div>
                <div v-else class="inspector-body history-panel"><h4>Nhật ký trong phiên demo</h4><p class="history-disclaimer">Không phải nhật ký vận hành thật. Các thao tác không được lưu trên máy chủ.</p><ol><li v-for="(entry, index) in currentActivities" :key="index"><time>{{ entry.time }} · giờ trên máy</time><p>{{ entry.text }}</p></li><li><time>{{ selected.time }} · thời gian mẫu</time><p>Bản ghi {{ selected.id }} được tạo từ dữ liệu minh họa giao diện.</p></li></ol></div>
                <div class="incident-review"><label :for="`note-${selectedId}`">Ghi chú xác minh <span>Chỉ lưu trong phiên demo</span></label><textarea :id="`note-${selectedId}`" v-model="notes[selectedId]" rows="2" maxlength="500" placeholder="Nhập nhận xét kiểm tra…"></textarea><button class="note-save" :disabled="!notes[selectedId]?.trim()" @click="saveNote">Thêm ghi chú</button><div class="review-actions"><button :disabled="selected.status === 'reviewing'" @click="updateStatus('reviewing')">Đánh dấu đang xem xét</button><button class="resolve-button" :disabled="selected.status === 'resolved' || checkedCount !== profile.checklist.length" @click="updateStatus('resolved')">Hoàn tất kiểm tra</button></div><p class="review-help">Hoàn thành checklist để thử thao tác xử lý. Không gửi thông báo bên ngoài.</p><p v-if="message" class="action-message" role="status">{{ message }}</p></div>
            </section>
        </div>
        <footer class="incident-footer"><span>AIRSCOPE / {{ profile.code }} WORKSPACE</span><span>GIAO DIỆN MINH HỌA · AI CHƯA KẾT NỐI</span></footer>
    </div>
</template>

<style scoped>
.incident-page{--accent:#ff756f;--edge:#293c4c;animation:incident-in .35s ease both}.incident-breadcrumb{display:flex;gap:12px;align-items:center;flex-wrap:wrap;font-size:11px;color:#829aac;margin-bottom:22px}.incident-breadcrumb button{border:0;background:none;color:#c3d3df;padding:5px 0}.incident-breadcrumb strong{font-weight:500;color:var(--accent)}.incident-hero{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:24px}.incident-kicker{font:9px 'DM Mono',monospace;letter-spacing:.12em;color:#8ca4b5}.incident-hero h2{font-size:clamp(25px,3vw,38px);letter-spacing:-.05em;line-height:1.25;margin:8px 0 10px}.incident-hero h2>span{color:var(--accent)}.incident-hero p{font-size:12px;color:#9aafbe;margin:0}.hero-model{display:flex;gap:12px;align-items:center;border-left:1px solid var(--edge);padding-left:24px;font-size:12px;flex-shrink:0}.hero-model i{height:8px;width:8px;border-radius:50%;background:#788c9b}.hero-model small{display:block;color:#8ba0b1;font-size:10px;margin-top:5px}
.incident-type-tabs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border:1px solid var(--edge);border-radius:9px;overflow:hidden;margin-bottom:16px;background:#0c1721}.incident-type-tabs button{display:flex;align-items:center;gap:9px;min-height:48px;padding:12px 18px;background:none;border:0;border-bottom:2px solid transparent;color:#94a9b8;font-size:12px}.incident-type-tabs button+button{border-left:1px solid var(--edge)}.incident-type-tabs i{height:6px;width:6px;background:var(--type-color);border-radius:50%;flex-shrink:0}.incident-type-tabs button>span{margin-left:auto;font:11px 'DM Mono',monospace}.incident-type-tabs button[aria-current=page]{color:var(--type-color);border-bottom-color:var(--type-color);background:linear-gradient(130deg,#20313d,#12222d)}.incident-demo{font-size:11px;color:#b6b5a8;background:#24251e;border:1px solid #494b38;border-radius:7px;padding:12px 15px;line-height:1.8;margin:0 0 22px}.incident-demo b{font:10px 'DM Mono',monospace;color:#e6c78e;margin-right:10px}
.incident-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:22px}.incident-stats>div{padding:17px 20px;border:1px solid var(--edge);border-radius:9px;background:linear-gradient(135deg,#182733,#101b25)}.incident-stats>div>span{font-size:11px;color:#9bb0bf}.incident-stats strong{display:flex;flex-wrap:wrap;align-items:baseline;gap:12px;font:500 30px 'DM Mono',monospace;margin-top:10px}.incident-stats>div:first-child strong{color:var(--accent)}.incident-stats small{font-size:8px;letter-spacing:.05em;color:#829cab}
.incident-workspace{display:grid;grid-template-columns:340px minmax(0,1fr);gap:20px;align-items:start}.incident-list-panel,.incident-inspector{border:1px solid var(--edge);border-radius:11px;background:linear-gradient(135deg,#14222e,#0e1923);min-width:0;overflow:hidden}.incident-panel-heading,.inspector-heading{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:20px;border-bottom:1px solid var(--edge)}.incident-panel-heading h3,.inspector-heading h3{font-size:15px;margin:5px 0 0;letter-spacing:-.02em}.small-count{font:11px 'DM Mono',monospace;color:var(--accent)}.incident-filters{display:grid;gap:12px;padding:18px 16px 12px}.incident-filters>div{display:grid;grid-template-columns:1fr 1fr;gap:10px}.incident-filters label{display:grid;gap:7px;font-size:10px;color:#98afbf;min-width:0}.incident-page input:not([type=checkbox]),.incident-page select,.incident-page textarea{width:100%;min-width:0;background:#0b1721;border:1px solid #354a5a;border-radius:6px;color:#d9e5ed;font:12px 'Plus Jakarta Sans',sans-serif;padding:11px 10px;min-height:42px}.incident-page textarea{resize:vertical}.incident-page input::placeholder,.incident-page textarea::placeholder{color:#738b9d}.incident-page input:focus-visible,.incident-page textarea:focus-visible{outline:2px solid var(--accent);outline-offset:2px}.incident-list-summary{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:0 16px 14px;font-size:10px;color:#879ead}.incident-list-summary button{background:none;border:0;color:var(--accent);font-size:10px;min-height:28px}.incident-records{list-style:none;padding:0 10px;margin:0;max-height:680px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#385063 transparent}.incident-records li{margin-bottom:7px}.incident-records button{display:block;width:100%;text-align:left;border:1px solid transparent;border-radius:7px;background:#0e1b26;color:#d6e3ec;padding:15px 12px}.incident-records button:hover{border-color:#446071}.incident-records button[aria-pressed=true]{border-color:var(--accent);background:linear-gradient(115deg,color-mix(in srgb,var(--accent) 10%,#14212c),#14212c)}.incident-records button>div{display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:10px;color:#8fa5b6}.record-id,.incident-records time{font:10px 'DM Mono',monospace}.incident-records button[aria-pressed=true] .record-id{color:var(--accent)}.incident-records strong{display:block;font-size:12px;font-weight:500;margin:10px 0 12px}.record-state{display:inline-flex;align-items:center;gap:5px;font-size:9px;white-space:nowrap;padding:4px 6px;border:1px solid #46505b;border-radius:4px;color:#a6bac7}.record-state:before{content:'';width:4px;height:4px;border-radius:50%;background:currentColor}.record-state.pending{color:#e5bd7b;border-color:#5b503c}.record-state.reviewing{color:#89bde4;border-color:#3b5870}.record-state.resolved{color:#8bccba;border-color:#3b5c57}.list-footnote{font-size:10px;color:#8ea4b4;line-height:1.7;margin:0;padding:16px;border-top:1px solid var(--edge)}.incident-empty{padding:30px 20px;text-align:center;color:#97adbd;font-size:12px}.incident-empty button{color:var(--accent);background:none;border:1px solid var(--edge);border-radius:5px;padding:10px}
.inspector-heading p{font-size:11px;color:#8ca4b5;margin:7px 0 0}.inspector-heading h3{font-family:'DM Mono',monospace;font-weight:500;color:var(--accent)}.selection-note{padding:10px 20px;background:#342a1b;color:#e4bd7e;font-size:11px;margin:0}.inspector-tabs{display:flex;gap:22px;padding:0 20px;border-bottom:1px solid var(--edge)}.inspector-tabs button{padding:14px 0;background:none;border:0;border-bottom:2px solid transparent;color:#8ea6b8;font-size:12px}.inspector-tabs button[aria-pressed=true]{border-bottom-color:var(--accent);color:var(--accent)}.inspector-tabs button span{font:9px 'DM Mono',monospace;padding:3px 5px;background:#283b4a;border-radius:4px;margin-left:6px}.inspector-body{padding:20px}.incident-scenario{display:grid;grid-template-columns:1fr 180px;gap:20px;margin-top:20px;padding-bottom:20px;border-bottom:1px solid var(--edge)}.incident-scenario h4,.verification h4,.history-panel h4{font-size:13px;margin:0 0 9px}.incident-scenario p{color:#9bb0bf;font-size:11px;line-height:1.8;margin:0}.scenario-measurement{border-left:1px solid var(--edge);padding-left:20px;display:grid;gap:5px}.scenario-measurement>span,.scenario-measurement small{color:#92a9ba;font-size:10px}.scenario-measurement strong{font:28px 'DM Mono',monospace;color:var(--accent)}.incident-facts{display:grid;grid-template-columns:1fr 1fr;gap:18px 24px;margin:20px 0 24px}.incident-facts dt{color:#809bad;font-size:10px;margin-bottom:5px}.incident-facts dd{margin:0;color:#c4d6e2;font-size:12px;overflow-wrap:anywhere}.verification{padding:16px;border:1px solid var(--edge);border-radius:8px;background:#0c1822}.verification header{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:6px}.verification header>span{color:var(--accent);font:11px 'DM Mono',monospace}.verification label{display:flex;align-items:flex-start;gap:10px;padding:9px 0;color:#a9becd;font-size:11px;line-height:1.7;cursor:pointer}.verification input{accent-color:var(--accent);flex-shrink:0;margin-top:3px;width:15px;height:15px}.incident-review{padding:20px;border-top:1px solid var(--edge);background:#0c172180}.incident-review>label{display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px;font-size:12px;margin-bottom:10px}.incident-review>label span{font-size:10px;color:#7e9bad}.note-save{display:block;margin:10px 0 20px auto;padding:8px 12px;background:#1b2e3c;border:1px solid #344e60;color:#bbd0df;border-radius:5px;font-size:11px;min-height:38px}.review-actions{display:flex;flex-wrap:wrap;gap:10px}.review-actions button{flex:1;min-height:44px;border:1px solid #3c5365;color:#bccedb;border-radius:6px;background:#192a37;padding:10px;font-size:11px}.review-actions .resolve-button{color:#0b1520;background:var(--accent);border-color:var(--accent);font-weight:700}.incident-page button:disabled{cursor:not-allowed;opacity:.45}.review-help,.history-disclaimer{font-size:10px;color:#8aa4b7;line-height:1.7;margin:12px 0 0}.action-message{font-size:11px;color:#a5dfca;background:#123027;padding:12px;border-radius:5px;line-height:1.8}.history-panel{min-height:350px}.history-panel ol{list-style:none;margin:22px 0 0;padding-left:16px;border-left:1px solid #3a5263}.history-panel li{position:relative;padding:0 0 22px 8px}.history-panel li:before{content:'';position:absolute;width:7px;height:7px;border-radius:50%;background:var(--accent);left:-20px;top:4px}.history-panel time{font:10px 'DM Mono',monospace;color:var(--accent)}.history-panel li p{font-size:12px;line-height:1.8;color:#b5cad8;overflow-wrap:anywhere}.incident-footer{display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;font:9px 'DM Mono',monospace;color:#758fa3;letter-spacing:.06em;margin-top:22px}
@keyframes incident-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}@media(prefers-reduced-motion:reduce){.incident-page{animation:none}}
@media(min-width:1400px){.incident-workspace{grid-template-columns:370px minmax(0,1fr)}}
@media(max-width:1150px){.incident-workspace{grid-template-columns:1fr}.incident-records{max-height:290px}.incident-stats{gap:10px}.incident-stats>div{padding:14px}.incident-type-tabs button{padding:12px}.hero-model{padding-left:14px}}
@media(max-width:600px){.incident-hero{align-items:start;flex-direction:column;gap:16px}.hero-model{border-left:0;padding-left:0}.incident-type-tabs{grid-template-columns:1fr 1fr}.incident-type-tabs button{border:1px solid var(--edge);font-size:11px;min-height:46px}.incident-stats{grid-template-columns:1fr 1fr}.incident-stats strong{font-size:26px}.incident-stats small{font-size:8px}.incident-breadcrumb{gap:8px;font-size:10px}.incident-hero p{line-height:1.8}.inspector-heading{flex-wrap:wrap;padding:16px}.inspector-body,.incident-review{padding:14px}.incident-scenario{grid-template-columns:1fr;gap:15px}.scenario-measurement{border-left:0;border-top:1px solid var(--edge);padding:14px 0 0;grid-template-columns:1fr auto;align-items:center}.scenario-measurement small{grid-column:1/-1}.incident-facts{gap:16px}.incident-facts dd{font-size:11px}.incident-footer{font-size:8px}.review-actions{flex-direction:column}}
</style>

<style scoped>
/* Editorial light surfaces keep the detail view calm while the model color remains a signal. */
.incident-page { color: #2c2c2c; }
.incident-page .incident-breadcrumb { color: #646464; }
.incident-page .incident-breadcrumb button { color: #444141; }
.incident-page .incident-kicker { color: #0081c0; }
.incident-page .incident-hero h2 { color: #2c2c2c; font-family: Georgia, 'Times New Roman', serif; font-weight: 400; }
.incident-page .incident-hero p,
.incident-page .hero-model small,
.incident-page .incident-scenario p,
.incident-page .incident-facts dt,
.incident-page .verification label,
.incident-page .list-footnote,
.incident-page .incident-footer { color: #646464; }
.incident-page .hero-model { border-color: #dee2de; }
.incident-page .hero-model i { background: #b4b8b4; }
.incident-page .incident-type-tabs,
.incident-page .incident-list-panel,
.incident-page .incident-inspector,
.incident-page .incident-stats > div { background: #ffffff; border-color: #dee2de; box-shadow: 0 1px 1px rgba(0,0,0,.08), 0 4px 5px rgba(0,0,0,.04); }
.incident-page .incident-type-tabs button { color: #646464; border-color: #dee2de; }
.incident-page .incident-type-tabs button[aria-current=page] { background: #f9faf7; }
.incident-page .incident-demo { color: #6f573a; background: #fffaf0; border-color: #dfcda9; }
.incident-page .incident-stats > div > span,
.incident-page .incident-list-summary,
.incident-page .incident-records button > div,
.incident-page .inspector-heading p,
.incident-page .selection-note,
.incident-page .history-disclaimer,
.incident-page .review-help { color: #646464; }
.incident-page .incident-stats > div { background: #ffffff; }
.incident-page .incident-panel-heading,
.incident-page .inspector-heading,
.incident-page .inspector-tabs,
.incident-page .incident-scenario,
.incident-page .incident-review { border-color: #dee2de; }
.incident-page .incident-filters input,
.incident-page .incident-filters select,
.incident-page .incident-review textarea { background: #f9faf7; color: #444141; border-color: #b4b8b4; }
.incident-page .incident-records button { background: #ffffff; color: #2c2c2c; }
.incident-page .incident-records button:hover { border-color: #b4b8b4; }
.incident-page .incident-records button[aria-pressed=true] { background: #f9faf7; }
.incident-page .inspector-tabs button { color: #646464; }
.incident-page .inspector-tabs button[aria-pressed=true] { color: #0081c0; }
.incident-page .verification { background: #f9faf7; border-color: #dee2de; }
.incident-page .incident-review { background: #f9faf7; }
.incident-page .note-save,
.incident-page .review-actions button { background: #ffffff; color: #282834; border-color: #282834; }
.incident-page .review-actions .resolve-button { color: #0081c0; background: transparent; border-color: #41a1cf; }
.incident-page .action-message { background: #f1f8f5; color: #2f6a58; }
.incident-page .history-panel ol { border-color: #dee2de; }
</style>
