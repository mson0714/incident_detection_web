<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import StationMap from './components/StationMap.vue';
import ForecastChart from './components/ForecastChart.vue';
import { useUrbanResource } from './composables/useUrbanResource';
import { forecastAvailable, forecastFor, formatDate, formatNumber, hasNumber, horizonMetrics, stationMetrics, pipelineFailures } from './lib/urban';

const overview = useUrbanResource('/api/dashboard', (payload) => Array.isArray(payload?.stations) && payload?.summary && payload?.rule);
const currentAlerts = useUrbanResource('/api/alerts?status=open&current=1');
const alertHistory = useUrbanResource('/api/alerts');
const models = useUrbanResource('/api/model/metrics');
const pipeline = useUrbanResource('/api/pipeline/status');
const hotspots = useUrbanResource('/api/hotspots?horizon=6');
const detail = useUrbanResource(null, (payload) => payload?.data?.id && Array.isArray(payload?.forecasts));

const activeView = ref('overview');
const selectedStationId = ref(null);
const selectedHorizon = ref(6);
const selectedModelVersion = ref('');
const showMobileNav = ref(false);
const alertStatus = ref('');
const alertStation = ref('');
const alertDate = ref('');
let refreshTimer = null;

const navigation = [
    { id: 'overview', label: 'Tổng quan', icon: 'grid' },
    { id: 'map', label: 'Bản đồ trạm', icon: 'map' },
    { id: 'alerts', label: 'Cảnh báo', icon: 'bell' },
    { id: 'model', label: 'Mô hình & chỉ số', icon: 'chart' },
    { id: 'pipeline', label: 'Trạng thái pipeline', icon: 'pulse' },
];
const icon = (name) => ({
    grid: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    map: '<svg viewBox="0 0 24 24"><path d="M9 18 3 21V6l6-3 6 3 6-3v15l-6 3-6-3Z"/><path d="M9 3v15M15 6v15"/></svg>',
    bell: '<svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>',
    chart: '<svg viewBox="0 0 24 24"><path d="M4 19V5M4 19h17"/><path d="m7 15 3-4 3 2 5-7"/></svg>',
    pulse: '<svg viewBox="0 0 24 24"><path d="M3 12h4l2-7 4 14 2-7h6"/></svg>',
    refresh: '<svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 0-14.9-3M4 5v5h5M4 13a8 8 0 0 0 14.9 3M20 19v-5h-5"/></svg>',
    arrow: '<svg viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>',
}[name] || '');

const dashboard = computed(() => overview.state.data);
const stations = computed(() => dashboard.value?.stations ?? []);
const summary = computed(() => dashboard.value?.summary ?? {});
const rule = computed(() => dashboard.value?.rule ?? {});
const timezone = computed(() => dashboard.value?.meta?.timezone ?? 'Asia/Ho_Chi_Minh');
const synthetic = computed(() => dashboard.value?.meta?.mode === 'synthetic');
const selectedStation = computed(() => detail.state.data?.data?.id === selectedStationId.value
    ? detail.state.data.data : stations.value.find((station) => station.id === selectedStationId.value));
const mapSelection = computed(() => stations.value.find((station) => station.id === selectedStationId.value));
const tableStations = computed(() => stations.value.map((station) => ({ ...station, displayedForecast: forecastFor(station, selectedHorizon.value) })));
const hotspotRows = computed(() => hotspots.state.data?.data ?? []);
const stationForecasts = computed(() => detail.state.data?.forecasts ?? selectedStation.value?.forecasts ?? []);
const selectedForecast = computed(() => forecastFor({ forecasts: stationForecasts.value }, selectedHorizon.value));
const explanations = computed(() => detail.state.data?.explanations ?? []);
const maxContribution = computed(() => Math.max(1, ...explanations.value.map((entry) => Math.abs(Number(entry.contribution) || 0))));
const qualityRows = computed(() => detail.state.data?.history ?? []);
const stationMetricRows = computed(() => stationMetrics(selectedModel.value));
const pipelineRuns = computed(() => pipeline.state.data?.data ?? []);
const pipelineErrors = computed(() => pipelineFailures(pipelineRuns.value));
const ingestionErrors = computed(() => pipelineFailures(pipelineRuns.value, true));
const selectedModel = computed(() => {
    const allModels = models.state.data?.data ?? [];
    if (selectedModelVersion.value) return allModels.find((model) => model.version === selectedModelVersion.value) ?? allModels[0];
    const servingVersion = stationForecasts.value.find((forecast) => forecast?.model_version)?.model_version;
    return allModels.find((model) => model.version === servingVersion) ?? allModels[0];
});
const horizonMetricRows = computed(() => horizonMetrics(selectedModel.value));
const sourceState = computed(() => ({ alerts: alertHistory.state, model: models.state, pipeline: pipeline.state, station: detail.state }[activeView.value] ?? overview.state));
const busy = computed(() => overview.state.loading || sourceState.value.loading || hotspots.state.loading || (activeView.value === 'overview' && currentAlerts.state.loading));
const title = computed(() => activeView.value === 'station' ? 'Chi tiết trạm' : navigation.find((view) => view.id === activeView.value)?.label);
const riskLabel = (risk) => ({ elevated: 'Vượt ngưỡng', normal: 'Dưới ngưỡng', unknown: 'Không rõ' }[risk] ?? 'Không rõ');
const statusLabel = (status) => ({ open: 'Đang mở', closed: 'Đã đóng', unknown: 'Không rõ', success: 'Thành công', failed: 'Lỗi', error: 'Lỗi', running: 'Đang chạy', pending: 'Chờ xử lý', ok: 'Hợp lệ', illustrative: 'Minh họa', active: 'Đang dùng', baseline: 'Baseline' }[status] ?? status ?? 'Chưa có dữ liệu');
const dateLabel = (value) => formatDate(value, timezone.value);
const freshnessLabel = (value) => hasNumber(value) ? `${formatNumber(value, 0)} phút` : 'Không có dữ liệu';
const forecastState = (forecast) => !forecast || forecast.predicted_pm25 === null ? 'Chưa có dự báo' : forecastAvailable(forecast) ? 'Khả dụng' : 'Cũ / không hợp lệ';
const eventHorizonLabel = (event) => event?.horizon_hour ? `h=${event.horizon_hour}` : 'horizon không rõ';
const highForecast = (forecast) => forecastAvailable(forecast) && selectedHorizon.value === 6 && Number(forecast.predicted_pm25) > Number(rule.value.threshold);
const signedContribution = (value) => hasNumber(value) ? `${Number(value) > 0 ? '+' : ''}${formatNumber(value)}` : '—';

function navigate(view) {
    activeView.value = view;
    showMobileNav.value = false;
}

function loadHistory(page = 1) {
    const params = new URLSearchParams({ page: String(page) });
    if (alertStatus.value) params.set('status', alertStatus.value);
    if (alertStation.value) params.set('station_id', alertStation.value);
    if (alertDate.value) params.set('date', alertDate.value);
    return alertHistory.load(`/api/alerts?${params}`, { clear: true });
}

function chooseStation(id) {
    selectedStationId.value = id;
    navigate('station');
    return detail.load(`/api/stations/${encodeURIComponent(id)}`, { clear: true });
}

async function refresh() {
    const tasks = [overview.load(), currentAlerts.load('/api/alerts?status=open&current=1'), hotspots.load()];
    if (activeView.value === 'alerts') tasks.push(loadHistory(alertHistory.state.data?.meta?.current_page ?? 1));
    if (activeView.value === 'model') tasks.push(models.load());
    if (activeView.value === 'pipeline') tasks.push(pipeline.load());
    if (activeView.value === 'station' && selectedStationId.value) tasks.push(detail.load(`/api/stations/${encodeURIComponent(selectedStationId.value)}`));
    await Promise.all(tasks);
}

watch(activeView, (view) => {
    if (view === 'alerts') loadHistory();
    if (view === 'model') models.load();
    if (view === 'pipeline') pipeline.load();
});
onMounted(() => {
    refresh();
    refreshTimer = window.setInterval(() => {
        if (!busy.value) refresh();
    }, 60000);
});
onBeforeUnmount(() => {
    if (refreshTimer) window.clearInterval(refreshTimer);
});
</script>

<template>
    <div class="app-shell" @keydown.esc="showMobileNav = false">
        <button v-if="showMobileNav" class="nav-backdrop" aria-label="Đóng menu" @click="showMobileNav = false"></button>
        <aside id="sidebar" class="sidebar" :class="{ open: showMobileNav }">
            <div class="brand"><div class="brand-mark" aria-hidden="true"></div><div class="brand-name">air<span>scope</span></div></div>
            <div class="eyebrow nav-label">Không gian làm việc</div>
            <nav class="nav" aria-label="Điều hướng chính">
                <button v-for="view in navigation" :key="view.id" :class="{ active: activeView === view.id }" :aria-current="activeView === view.id ? 'page' : undefined" @click="navigate(view.id)">
                    <span aria-hidden="true" v-html="icon(view.icon)"></span>{{ view.label }}
                    <b v-if="view.id === 'alerts' && summary.open_alerts" class="nav-count">{{ summary.open_alerts }}</b>
                </button>
            </nav>
            <div class="sidebar-foot">
                <div class="eyebrow">Nguồn dữ liệu</div>
                <div class="status-line"><i class="dot" :class="{ warn: overview.state.error || !dashboard }"></i>{{ overview.state.error ? 'Mất kết nối API' : dashboard ? 'Đã kết nối API' : 'Đang kết nối' }}</div>
                <div class="status-line"><i class="dot warn"></i>{{ synthetic ? 'Dữ liệu mô phỏng' : dashboard?.meta?.mode ?? 'Chưa xác định chế độ' }}</div>
            </div>
        </aside>

        <main class="main" :aria-busy="busy">
            <header class="topbar">
                <div class="head-left">
                    <button class="mobile-menu" aria-label="Mở menu điều hướng" aria-controls="sidebar" :aria-expanded="showMobileNav" @click="showMobileNav = !showMobileNav"><span aria-hidden="true" v-html="icon('grid')"></span></button>
                    <div><div class="eyebrow">Theo dõi đô thị / {{ dashboard?.meta?.city ?? 'PM2.5' }}</div><h1 class="page-title">{{ title }}</h1></div>
                </div>
                <div class="top-actions">
                    <span class="timestamp">TẢI LÚC {{ dateLabel(sourceState.updatedAt) }}</span>
                    <button class="refresh" :class="{ spinning: busy }" :disabled="busy" aria-label="Làm mới dữ liệu" @click="refresh"><span aria-hidden="true" v-html="icon('refresh')"></span></button>
                </div>
            </header>
            <div v-if="busy" class="loading-bar" role="status" aria-label="Đang tải dữ liệu"></div>
            <div v-if="overview.state.error" class="notice error" role="alert">Không tải được dashboard. {{ dashboard ? 'Đang giữ bản tải gần nhất; dữ liệu có thể đã cũ.' : 'Kiểm tra Laravel và kết nối MySQL, rồi nhấn làm mới.' }}</div>
            <div v-if="sourceState !== overview.state && sourceState.error" class="notice error" role="alert">Không tải được dữ liệu cho trang này. {{ sourceState.data ? 'Nội dung đang hiển thị là bản tải trước.' : 'Vui lòng thử làm mới.' }}</div>
            <div v-if="synthetic" class="data-notice" role="note"><span class="pill warn">DEMO</span> Dữ liệu trạm, dự báo và chỉ số đều được mô phỏng. Không phải kết quả huấn luyện, khuyến cáo y tế hoặc bản đồ phủ toàn thành phố.</div>

            <template v-if="activeView === 'overview' || activeView === 'map'">
                <div v-if="activeView === 'overview'" class="grid-kpi">
                    <div class="card kpi"><div class="kpi-label">PM2.5 trung bình</div><div class="kpi-value cyan">{{ formatNumber(summary.mean_pm25) }} <small>µg/m³</small></div><div class="kpi-meta">Từ quan trắc gần nhất có sẵn</div></div>
                    <div class="card kpi"><div class="kpi-label">Trạm đang hoạt động</div><div class="kpi-value">{{ formatNumber(summary.online_count, 0) }}<small> / {{ formatNumber(summary.station_count, 0) }}</small></div><div class="kpi-meta">Tuổi dữ liệu lớn nhất: {{ freshnessLabel(summary.data_freshness_minutes) }}</div></div>
                    <div class="card kpi"><div class="kpi-label">Cảnh báo đang mở</div><div class="kpi-value amber">{{ formatNumber(summary.open_alerts, 0) }}</div><div class="kpi-meta">{{ rule.open_count ?? '—' }} dự báo h=6 liên tiếp vượt ngưỡng</div></div>
                    <div class="card kpi"><div class="kpi-label">Ngưỡng cảnh báo nội bộ</div><div class="kpi-value">{{ formatNumber(rule.threshold) }} <small>µg/m³</small></div><div class="kpi-meta">{{ rule.rule_version ?? 'Chưa có cấu hình' }}</div></div>
                </div>

                <div :class="{ 'section-grid': activeView === 'overview' }">
                    <section class="card panel">
                        <div class="panel-head"><div><h2 class="panel-title">Bản đồ điểm quan trắc</h2><div class="panel-subtitle">Rủi ro theo dự báo h=6; một điểm vượt ngưỡng chưa đồng nghĩa có event mở.</div></div><span class="pill">{{ stations.length }} TRẠM</span></div>
                        <StationMap :stations="stations" :selected-id="selectedStationId" @select="selectedStationId = $event" />
                        <div v-if="mapSelection" class="map-selection"><span>{{ mapSelection.name }} · {{ forecastState(mapSelection.forecast) }}</span><button class="link-button" @click="chooseStation(mapSelection.id)">Mở chi tiết trạm <span aria-hidden="true" v-html="icon('arrow')"></span></button></div>
                    </section>
                    <section v-if="activeView === 'overview'" class="card panel">
                        <div class="panel-head"><div><h2 class="panel-title">Cảnh báo cần chú ý</h2><div class="panel-subtitle">Sự kiện từ alert engine, sau bước xác nhận liên tiếp</div></div><span class="pill warn">{{ formatNumber(summary.open_alerts, 0) }} ĐANG MỞ</span></div>
                        <p v-if="currentAlerts.state.error" class="notice error" role="alert">Không tải được danh sách event. {{ currentAlerts.state.data ? 'Đang hiển thị bản tải trước.' : '' }}</p>
                        <div class="alert-list">
                            <button v-for="event in currentAlerts.state.data?.data ?? []" :key="event.id" class="alert-item list-button" @click="chooseStation(event.station_id)"><i class="alert-marker"></i><span><span class="alert-name">{{ event.station_name ?? event.station_id }}</span><span class="alert-place">EVT-{{ event.id }} · ghi nhận {{ dateLabel(event.last_seen_at) }}</span></span><span class="alert-value">{{ formatNumber(event.predicted_pm25) }}<small>µg/m³ · h=6</small></span></button>
                            <p v-if="currentAlerts.state.data && !currentAlerts.state.data.data.length" class="empty">Chưa có cảnh báo đang mở.</p>
                            <p v-else-if="currentAlerts.state.loading && !currentAlerts.state.data" class="empty">Đang tải cảnh báo…</p>
                        </div>
                        <button class="link-button" @click="navigate('alerts')">Xem lịch sử cảnh báo <span aria-hidden="true" v-html="icon('arrow')"></span></button>
                    </section>
                </div>

                <section v-if="activeView === 'overview' || activeView === 'map'" class="card panel table-panel">
                    <div class="panel-head"><div><h2 class="panel-title">Điểm nóng dự báo</h2><div class="panel-subtitle">Xếp hạng các trạm có dự báo h=6 vượt ngưỡng; không nội suy ngoài vị trí trạm.</div></div><span class="pill warn">{{ hotspotRows.length }} TRẠM</span></div>
                    <div v-if="hotspots.state.error" class="notice error" role="alert">Không tải được bảng điểm nóng.</div>
                    <div class="table-scroll" tabindex="0" role="region" aria-label="Bảng xếp hạng điểm nóng">
                        <table class="station-table"><thead><tr><th scope="col">Hạng</th><th scope="col">Trạm</th><th scope="col">Dự báo h=6</th><th scope="col">Tuổi dữ liệu</th><th scope="col">Trạng thái</th></tr></thead><tbody>
                            <tr v-for="(station, index) in hotspotRows" :key="station.id"><td class="metric">#{{ index + 1 }}</td><td><button class="station-link" @click="chooseStation(station.id)"><span class="station-id">{{ station.id }}</span><span class="station-name">{{ station.name }}</span><span class="station-district">{{ station.district }}</span></button></td><td class="metric hot">{{ formatNumber(station.forecast?.predicted_pm25) }}<small> µg/m³</small></td><td class="metric">{{ freshnessLabel(station.freshness_minutes) }}</td><td><span class="risk-label elevated">{{ riskLabel(station.risk) }}</span></td></tr>
                        </tbody></table>
                    </div>
                    <p v-if="hotspots.state.data && !hotspotRows.length" class="empty">Chưa có trạm vượt ngưỡng trong snapshot hiện tại.</p>
                </section>

                <section class="card panel table-panel">
                    <div class="panel-head"><div><h2 class="panel-title">Tình trạng các trạm</h2><div class="panel-subtitle">Dự báo theo cùng lần phát hành mới nhất; rủi ro luôn được xác định tại h=6.</div></div><select v-model="selectedHorizon" class="select" aria-label="Horizon dự báo"><option :value="1">Dự báo 1 giờ</option><option :value="6">Dự báo 6 giờ</option><option :value="24">Dự báo 24 giờ</option></select></div>
                    <div class="table-scroll" tabindex="0" role="region" aria-label="Bảng trạm, có thể cuộn ngang">
                        <table class="station-table"><thead><tr><th scope="col">Trạm</th><th scope="col">Hiện tại</th><th scope="col">Dự báo h={{ selectedHorizon }}</th><th scope="col">Tuổi dữ liệu</th><th scope="col">Rủi ro h=6</th></tr></thead><tbody>
                            <tr v-for="station in tableStations" :key="station.id"><td><button class="station-link" @click="chooseStation(station.id)"><span class="station-id">{{ station.id }}</span><span class="station-name">{{ station.name }}</span><span class="station-district">{{ station.district }}</span></button></td><td class="metric">{{ formatNumber(station.current?.pm25) }}<small> µg/m³</small></td><td class="metric" :class="{ hot: highForecast(station.displayedForecast) }">{{ formatNumber(station.displayedForecast?.predicted_pm25) }}<small> µg/m³</small><span class="forecast-state">{{ forecastState(station.displayedForecast) }}</span></td><td class="metric">{{ freshnessLabel(station.freshness_minutes) }}<span class="forecast-state">{{ station.status === 'active' ? 'Đang hoạt động' : 'Thiếu / cũ / ngừng hoạt động' }}</span></td><td><span class="risk-label" :class="station.risk">{{ riskLabel(station.risk) }}</span></td></tr>
                        </tbody></table>
                    </div>
                    <p v-if="!stations.length" class="empty">{{ overview.state.loading ? 'Đang tải danh sách trạm…' : 'Chưa có dữ liệu trạm.' }}</p>
                </section>
            </template>

            <template v-else-if="activeView === 'station'">
                <button class="back-btn" @click="navigate('overview')">← Quay lại tổng quan</button>
                <template v-if="selectedStation">
                    <div class="detail-head"><div><div class="eyebrow">{{ selectedStation.id }} · {{ selectedStation.district }}</div><h2 class="station-heading">{{ selectedStation.name }}</h2></div><span class="risk-label" :class="selectedStation.risk">{{ riskLabel(selectedStation.risk) }} · h=6</span></div>
                    <div class="detail-kpis"><div class="card mini-kpi"><div class="mini-label">PM2.5 hiện tại</div><div class="mini-value">{{ formatNumber(selectedStation.current?.pm25) }} <small>µg/m³</small></div><div class="panel-subtitle">{{ freshnessLabel(selectedStation.freshness_minutes) }} · thiếu {{ formatNumber((selectedStation.missing_rate ?? 0) * 100) }}%</div></div><div class="card mini-kpi"><div class="mini-label">Độ ẩm</div><div class="mini-value">{{ formatNumber(selectedStation.current?.humidity) }} <small>%</small></div></div><div class="card mini-kpi"><div class="mini-label">Gió</div><div class="mini-value">{{ formatNumber(selectedStation.current?.wind_speed) }} <small>m/s</small></div></div></div>
                    <div class="detail-view">
                        <section class="card panel"><div class="panel-head"><div><h2 class="panel-title">Lịch sử và dự báo</h2><div class="panel-subtitle">24 giờ quan trắc và các mốc dự báo rời rạc 1/6/24 giờ</div></div><select v-model="selectedHorizon" class="select" aria-label="Mốc dự báo cần xem"><option :value="1">1 giờ</option><option :value="6">6 giờ</option><option :value="24">24 giờ</option></select></div>
                            <ForecastChart :history="detail.state.data?.history ?? []" :forecasts="stationForecasts" :threshold="hasNumber(rule.threshold) ? Number(rule.threshold) : null" :selected-horizon="selectedHorizon" />
                            <div class="detail-facts"><h3 class="card-title">Dự báo h={{ selectedHorizon }} · {{ forecastState(selectedForecast) }}</h3><dl><div><dt>PM2.5 dự báo</dt><dd>{{ formatNumber(selectedForecast?.predicted_pm25) }} µg/m³</dd></div><div><dt>Thời điểm phát hành</dt><dd>{{ dateLabel(selectedForecast?.forecast_origin) }}</dd></div><div><dt>Thời điểm sinh kết quả</dt><dd>{{ dateLabel(selectedForecast?.generated_at) }}</dd></div><div><dt>Thời điểm được dự báo</dt><dd>{{ dateLabel(selectedForecast?.target_timestamp) }}</dd></div><div><dt>Phiên bản mô hình</dt><dd>{{ selectedForecast?.model_version ?? '—' }}</dd></div><div><dt>Snapshot · chất lượng</dt><dd>{{ selectedForecast?.data_snapshot_id ?? '—' }} · {{ selectedForecast?.data_quality ?? '—' }}</dd></div></dl></div>
                        </section>
                        <section class="card panel"><div class="panel-head"><div><h2 class="panel-title">Yếu tố đóng góp</h2><div class="panel-subtitle">Giải thích h=6; không suy ra quan hệ nhân quả</div></div><span class="pill">{{ synthetic ? 'MINH HỌA' : 'GIẢI THÍCH' }}</span></div>
                            <div class="insight-list"><div v-for="entry in explanations" :key="entry.feature" class="insight"><div>{{ entry.feature }}<div class="panel-subtitle">Giá trị: {{ formatNumber(entry.value) }}</div><div class="insight-bar"><span :class="{ negative: Number(entry.contribution) < 0 }" :style="{ width: `${Math.abs(Number(entry.contribution) || 0) / maxContribution * 100}%` }"></span></div></div><div class="insight-value">{{ signedContribution(entry.contribution) }}</div></div></div>
                            <p v-if="!explanations.length" class="empty">{{ detail.state.loading ? 'Đang tải giải thích…' : 'Chưa có giải thích cho dự báo h=6.' }}</p>
                            <p v-if="synthetic" class="panel-subtitle">Các đóng góp demo chỉ minh họa cấu trúc lưu trữ. Chưa chạy SHAP từ mô hình huấn luyện.</p>
                        </section>
                        <section class="card panel table-panel"><div class="panel-head"><div><h2 class="panel-title">Chất lượng dữ liệu quan trắc</h2><div class="panel-subtitle">Cờ chất lượng được giữ nguyên từ ingestion; giá trị thiếu/bất thường không dùng làm bằng chứng cảnh báo.</div></div><span class="pill">{{ qualityRows.length }} MỐC</span></div><div class="table-scroll" tabindex="0" role="region" aria-label="Bảng chất lượng dữ liệu"><table class="station-table"><thead><tr><th scope="col">Thời điểm</th><th scope="col">PM2.5</th><th scope="col">Cờ chất lượng</th><th scope="col">Đánh giá</th></tr></thead><tbody><tr v-for="row in qualityRows" :key="row.timestamp"><td>{{ dateLabel(row.timestamp) }}</td><td class="metric">{{ formatNumber(row.pm25) }}<small> µg/m³</small></td><td>{{ row.quality_flag ?? '—' }}</td><td><span class="risk-label" :class="row.valid ? 'normal' : 'unknown'">{{ row.valid ? 'Hợp lệ' : 'Thiếu / bất thường' }}</span></td></tr></tbody></table></div><p v-if="!qualityRows.length" class="empty">Chưa có lịch sử quan trắc.</p></section>
                    </div>
                    <section class="card panel table-panel"><div class="panel-head"><h2 class="panel-title">Sự kiện tại trạm</h2></div><div v-for="event in detail.state.data?.events ?? []" :key="event.id" class="alert-item"><i class="alert-marker" :class="{ resolved: event.status !== 'open' }"></i><div><div class="alert-name">EVT-{{ event.id }} · {{ statusLabel(event.status) }} · {{ eventHorizonLabel(event) }}</div><div class="alert-place">Mở {{ dateLabel(event.opened_at) }} · đóng {{ dateLabel(event.closed_at) }}</div><div class="alert-place">{{ event.rule_version }} · {{ event.model_version }}</div></div><div class="alert-value">{{ formatNumber(event.predicted_pm25) }}<small>Ngưỡng {{ formatNumber(event.threshold) }}</small></div></div><p v-if="!detail.state.data?.events?.length" class="empty">Chưa có sự kiện được ghi nhận.</p></section>
                </template>
                <p v-else class="empty">{{ detail.state.loading ? 'Đang tải chi tiết trạm…' : 'Không tìm thấy dữ liệu trạm.' }}</p>
            </template>

            <template v-else-if="activeView === 'alerts'">
                <section class="card panel"><div class="panel-head"><div><h2 class="panel-title">Lịch sử cảnh báo</h2><div class="panel-subtitle">Tất cả horizon và phiên bản rule · cảnh báo chính h=6 mở / đóng sau {{ rule.open_count ?? '—' }} / {{ rule.close_count ?? '—' }} dự báo hợp lệ liên tiếp · cooldown {{ rule.cooldown_hours ?? '—' }} giờ</div></div></div>
                    <form class="filter-bar" @submit.prevent="loadHistory()"><label>Trạng thái<select v-model="alertStatus" class="select"><option value="">Tất cả</option><option value="open">Đang mở</option><option value="closed">Đã đóng</option><option value="unknown">Không rõ</option></select></label><label>Trạm<select v-model="alertStation" class="select"><option value="">Tất cả trạm</option><option v-for="station in stations" :key="station.id" :value="station.id">{{ station.name }}</option></select></label><label>Ngày mở (giờ Việt Nam)<input v-model="alertDate" type="date" class="select"></label><button type="submit" class="select" :disabled="alertHistory.state.loading">Áp dụng</button></form>
                    <div class="table-scroll" tabindex="0" role="region" aria-label="Lịch sử event, có thể cuộn ngang"><table class="station-table"><thead><tr><th scope="col">Event / trạm</th><th scope="col">Trạng thái</th><th scope="col">Thời gian</th><th scope="col">Dự báo / ngưỡng</th><th scope="col">Phiên bản</th></tr></thead><tbody><tr v-for="event in alertHistory.state.data?.data ?? []" :key="event.id"><td><button class="station-link" @click="chooseStation(event.station_id)"><span class="station-id">EVT-{{ event.id }}</span><span class="station-name">{{ event.station_name ?? event.station_id }}</span></button></td><td><span class="risk-label" :class="event.status === 'open' ? 'elevated' : event.status === 'closed' ? 'normal' : 'unknown'">{{ statusLabel(event.status) }}</span><span class="forecast-state">{{ eventHorizonLabel(event) }} · {{ event.notification_count }} lần thông báo</span></td><td>Mở {{ dateLabel(event.opened_at) }}<span class="forecast-state">Cập nhật {{ dateLabel(event.last_seen_at) }}</span><span v-if="event.closed_at" class="forecast-state">Đóng {{ dateLabel(event.closed_at) }} · {{ event.close_reason }}</span><span class="forecast-state">Dự báo đến {{ dateLabel(event.target_timestamp) }}</span></td><td class="metric">{{ formatNumber(event.predicted_pm25) }} / {{ formatNumber(event.threshold) }} µg/m³</td><td>{{ event.model_version }}<span class="forecast-state">{{ event.rule_version }}</span></td></tr></tbody></table></div>
                    <p v-if="!alertHistory.state.data?.data?.length" class="empty">{{ alertHistory.state.loading ? 'Đang tải lịch sử…' : alertHistory.state.error ? 'Lịch sử chưa tải được.' : 'Không có event phù hợp bộ lọc.' }}</p>
                    <div v-if="alertHistory.state.data?.meta" class="pager"><button class="select" :disabled="alertHistory.state.loading || alertHistory.state.data.meta.current_page <= 1" @click="loadHistory(alertHistory.state.data.meta.current_page - 1)">Trang trước</button><span>Trang {{ alertHistory.state.data.meta.current_page }} / {{ alertHistory.state.data.meta.last_page }} · {{ alertHistory.state.data.meta.total }} event</span><button class="select" :disabled="alertHistory.state.loading || alertHistory.state.data.meta.current_page >= alertHistory.state.data.meta.last_page" @click="loadHistory(alertHistory.state.data.meta.current_page + 1)">Trang sau</button></div>
                </section>
            </template>

            <template v-else-if="activeView === 'model'">
                <div class="section-grid"><section class="card panel"><div class="panel-head"><div><h2 class="panel-title">Danh mục mô hình</h2><div class="panel-subtitle">Phiên bản và kết quả được lưu trong hệ thống</div></div></div><div class="alert-list"><button v-for="model in models.state.data?.data ?? []" :key="model.version" class="alert-item list-button" :aria-pressed="selectedModel?.version === model.version" @click="selectedModelVersion = model.version"><i class="alert-marker resolved"></i><span><span class="alert-name">{{ model.version }}</span><span class="alert-place">{{ model.algorithm }} · {{ statusLabel(model.status) }}</span></span><span class="pill" :class="{ warn: model.is_synthetic }">{{ model.is_synthetic ? 'DEMO' : 'ĐÃ LƯU' }}</span></button></div><p v-if="!models.state.data?.data?.length" class="empty">{{ models.state.loading ? 'Đang tải mô hình…' : 'Chưa có mô hình được đăng ký.' }}</p></section>
                    <section class="card panel"><div class="panel-head"><div><h2 class="panel-title">Đánh giá theo horizon</h2><div class="panel-subtitle">{{ selectedModel?.version ?? 'Chưa chọn mô hình' }}</div></div></div><template v-if="selectedModel"><p v-if="selectedModel.is_synthetic" class="notice warn">Chỉ số mô phỏng, chưa phải kết quả thực nghiệm.</p><div class="table-scroll" tabindex="0" role="region" aria-label="Chỉ số đánh giá mô hình"><table class="station-table"><thead><tr><th scope="col">Horizon</th><th scope="col">MAE</th><th scope="col">RMSE</th><th scope="col">sMAPE</th></tr></thead><tbody><tr v-for="metric in horizonMetricRows" :key="metric.horizon_hour"><td>h={{ metric.horizon_hour }}</td><td>{{ formatNumber(metric.mae, 2) }}</td><td>{{ formatNumber(metric.rmse, 2) }}</td><td>{{ formatNumber(metric.smape, 2) }}%</td></tr></tbody></table></div><h3 class="card-title">Lỗi theo trạm</h3><div v-if="stationMetricRows.length" class="table-scroll" tabindex="0" role="region" aria-label="Lỗi mô hình theo trạm"><table class="station-table"><thead><tr><th scope="col">Trạm</th><th scope="col">MAE</th><th scope="col">RMSE</th><th scope="col">sMAPE</th></tr></thead><tbody><tr v-for="row in stationMetricRows" :key="row.stationId"><td>{{ row.stationId }}</td><td>{{ formatNumber(row.mae, 2) }}</td><td>{{ formatNumber(row.rmse, 2) }}</td><td>{{ formatNumber(row.smape, 2) }}%</td></tr></tbody></table></div><p v-else class="panel-subtitle">API chưa cung cấp breakdown theo trạm cho phiên bản này.</p><div class="detail-facts"><dl><div><dt>Feature version</dt><dd>{{ selectedModel.feature_version }}</dd></div><div><dt>Mốc kết thúc train</dt><dd>{{ dateLabel(selectedModel.train_cutoff) }}</dd></div></dl></div><p class="panel-subtitle">{{ selectedModel.description }}</p></template><p v-else class="empty">Chưa có chỉ số để hiển thị.</p></section></div>
            </template>

            <template v-else-if="activeView === 'pipeline'">
                <section class="card panel"><div class="panel-head"><div><h2 class="panel-title">Trạng thái xử lý dữ liệu</h2><div class="panel-subtitle">Bronze → Silver → Gold → Forecast → Alert engine</div></div><span class="pill" :class="{ warn: pipeline.state.data?.summary?.status !== 'success' }">{{ statusLabel(pipeline.state.data?.summary?.status) }}</span></div><p v-if="synthetic" class="notice warn">Đây là snapshot minh họa của pipeline. Chưa kết nối tác vụ Python hoặc luồng dữ liệu thật.</p><div class="detail-facts"><dl><div><dt>Thành công gần nhất</dt><dd>{{ dateLabel(pipeline.state.data?.summary?.last_success_at) }}</dd></div><div><dt>Số bản ghi đã xử lý</dt><dd>{{ formatNumber(pipeline.state.data?.summary?.records_processed, 0) }}</dd></div><div><dt>Freshness lần chạy</dt><dd>{{ freshnessLabel(pipeline.state.data?.summary?.freshness_minutes) }}</dd></div><div><dt>Lỗi pipeline</dt><dd>{{ pipelineErrors.length ? `${pipelineErrors.length} batch lỗi` : 'Không ghi nhận' }}</dd></div></dl></div><div v-if="pipelineErrors.length" class="notice error" role="alert">Có batch pipeline lỗi gần đây; xem công đoạn và thông báo lỗi trong nhật ký bên dưới.</div><div v-if="ingestionErrors.length" class="notice error" role="alert">Có {{ ingestionErrors.length }} batch ingestion lỗi.</div><div class="pipeline-steps"><div v-for="(layer, index) in pipeline.state.data?.layers ?? []" :key="layer.name" class="pipeline-step"><span class="step-number">0{{ index + 1 }}</span><div><div class="alert-name">{{ layer.name }} · {{ formatNumber(layer.record_count, 0) }} bản ghi</div><div class="alert-place">{{ layer.description }}</div></div><span class="pill">{{ statusLabel(layer.status) }}</span></div></div></section>
                <section class="card panel table-panel"><div class="panel-head"><h2 class="panel-title">Nhật ký batch gần nhất</h2></div><div class="table-scroll" tabindex="0" role="region" aria-label="Nhật ký pipeline"><table class="station-table"><thead><tr><th scope="col">Công đoạn</th><th scope="col">Trạng thái</th><th scope="col">Bắt đầu / hoàn tất</th><th scope="col">Bản ghi / thời gian</th><th scope="col">Snapshot / lỗi</th></tr></thead><tbody><tr v-for="run in pipelineRuns" :key="run.id"><td>{{ run.stage }}</td><td><span class="risk-label" :class="['failed','error'].includes(String(run.status || '').toLowerCase()) ? 'elevated' : 'normal'">{{ statusLabel(run.status) }}</span></td><td>{{ dateLabel(run.started_at) }}<span class="forecast-state">{{ dateLabel(run.finished_at) }}</span></td><td>{{ formatNumber(run.records_processed, 0) }}<span class="forecast-state">{{ formatNumber(run.duration_ms, 0) }} ms</span></td><td>{{ run.data_snapshot_id ?? '—' }}<span v-if="run.error_message" class="forecast-state">{{ run.error_message }}</span></td></tr></tbody></table></div><p v-if="!pipelineRuns.length" class="empty">{{ pipeline.state.loading ? 'Đang tải nhật ký…' : 'Chưa có batch được ghi nhận.' }}</p></section>
            </template>
        </main>
    </div>
</template>
