<script setup>
defineProps({ type: { type: String, required: true }, camera: { type: String, required: true } });
const redLightVideoUrl = '/media/demo_vuot_den_do.mp4';
</script>

<template>
    <div class="evidence-scene">
        <div class="evidence-overlay"><span>{{ camera }} / SCHEMATIC</span><b>MINH HỌA · KHÔNG PHẢI ẢNH THẬT</b></div>
        <div v-if="type === 'redlight'" class="evidence-video-block">
            <div class="evidence-video-heading"><div><span>03 / MEDIA EVIDENCE</span><strong>Video minh họa vượt đèn đỏ</strong></div><b>UI DEMO</b></div>
            <div class="evidence-video-frame">
                <video class="evidence-video" :src="redLightVideoUrl" controls playsinline preload="metadata" aria-label="Video minh họa vượt đèn đỏ"></video>
            </div>
            <p class="evidence-video-note">Video cục bộ <strong>demo_vuot_den_do.mp4</strong> · H.264 + AAC · dài khoảng 7,5 giây. Đây là tư liệu minh họa, chưa phải bằng chứng vận hành.</p>
        </div>
        <svg viewBox="0 0 720 360" role="img" :aria-label="`Sơ đồ minh họa ${type}, không phải bằng chứng camera`">
            <defs><pattern id="incident-grid" width="30" height="30" patternUnits="userSpaceOnUse"><path d="M30 0H0V30" fill="none" stroke="#6b8b9f" stroke-opacity=".1" /></pattern></defs>
            <rect width="720" height="360" fill="#0b151f" />
            <rect width="720" height="360" fill="url(#incident-grid)" />
            <path d="M0 138H720V298H0Z" fill="#1a2b37" />
            <path d="M318 0H475V360H318Z" fill="#1a2b37" />
            <path d="M0 128H305V0M488 0V128H720M0 309H305V360M488 360V309H720" fill="none" stroke="#456071" stroke-width="3" />
            <path d="M0 219H310M490 219H720M395 0V128M395 310V360" stroke="#7590a0" stroke-dasharray="24 20" stroke-opacity=".6" stroke-width="2" />
            <g fill="#4b6070" opacity=".5"><rect x="42" y="48" width="106" height="54" rx="5"/><rect x="177" y="35" width="85" height="66" rx="5"/><rect x="523" y="43" width="156" height="59" rx="5"/></g>
            <g v-if="type === 'accident'">
                <path d="M170 172H326L361 206" fill="none" stroke="var(--accent)" stroke-dasharray="6 7" stroke-width="2" />
                <rect x="322" y="155" width="80" height="39" rx="8" fill="#788d9a" transform="rotate(24 362 175)" />
                <rect x="374" y="202" width="42" height="17" rx="7" fill="#99acb7" transform="rotate(-35 395 210)" />
                <rect x="303" y="131" width="134" height="113" rx="5" fill="var(--accent)" fill-opacity=".08" stroke="var(--accent)" stroke-dasharray="8 4" />
                <circle cx="378" cy="196" r="20" fill="none" stroke="var(--accent)"/><path d="m371 189 14 14m0-14-14 14" stroke="var(--accent)" stroke-width="2" />
                <text x="305" y="119">COLLISION / ROI A</text>
            </g>
            <g v-else-if="type === 'helmet'">
                <rect x="314" y="169" width="91" height="41" rx="20" fill="#637b89" />
                <circle cx="358" cy="188" r="17" fill="#aec2cb" />
                <rect x="328" y="156" width="61" height="64" rx="3" fill="var(--accent)" fill-opacity=".08" stroke="var(--accent)" stroke-width="2" />
                <path d="M391 157 462 99H585" fill="none" stroke="var(--accent)" />
                <text x="468" y="87">HEAD / ROI B</text>
                <rect x="511" y="253" width="82" height="32" rx="6" fill="#4a626f" />
            </g>
            <g v-else-if="type === 'redlight'">
                <path d="M290 145V211" stroke="var(--accent)" stroke-width="5" />
                <path d="M145 177H478" stroke="var(--accent)" stroke-dasharray="8 7" stroke-width="2" /><path d="m464 167 14 10-14 10" fill="none" stroke="var(--accent)" stroke-width="2" />
                <rect x="351" y="157" width="80" height="41" rx="6" fill="#899faa" />
                <rect x="339" y="146" width="103" height="65" rx="3" stroke="var(--accent)" fill="none" />
                <rect x="249" y="51" width="28" height="71" rx="8" fill="#0b1118" stroke="#49616e" />
                <circle cx="263" cy="66" r="7" fill="#ff756f"/><circle cx="263" cy="86" r="7" fill="#334550"/><circle cx="263" cy="105" r="7" fill="#334550"/>
                <text x="202" y="248">STOP LINE 01</text><text x="460" y="143">+2.4s / DEMO</text>
            </g>
            <g v-else>
                <path d="M153 161Q223 129 293 160T443 169Q521 182 564 249L521 288Q407 267 316 295T144 258Z" fill="var(--accent)" fill-opacity=".23" stroke="var(--accent)" stroke-dasharray="7 5" stroke-width="2" />
                <path d="M192 219q34-20 68 0t68 0t68 0t68 0M231 251q34-20 68 0t68 0t68 0" fill="none" stroke="var(--accent)" stroke-opacity=".6" />
                <text x="252" y="121">WATER REGION / 36% ROI</text>
            </g>
            <text x="25" y="343" class="scene-footer">UI DEMO / NO INFERENCE / NO RECORDING</text>
        </svg>
        <div class="evidence-caption">{{ type === 'redlight' ? 'Sơ đồ giải thích vạch dừng và hướng di chuyển. Video bên trên chỉ là nguồn minh họa cục bộ, không tự tạo kết luận AI.' : 'Sơ đồ giải thích vùng quan sát. Chưa có ảnh hoặc video bằng chứng gắn với bản ghi này.' }}</div>
    </div>
</template>

<style scoped>
.evidence-scene{font-weight:var(--font-weight-body)}
.evidence-scene{border:1px solid #314453;border-radius:9px;overflow:hidden;background:#0b151f}.evidence-overlay{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;padding:13px 16px;border-bottom:1px solid #263b4a;font:10px 'DM Mono',monospace;color:#91aaba}.evidence-overlay b{font-weight:400;color:var(--accent);font-size:9px}.evidence-scene svg{width:100%;height:auto;display:block}.evidence-scene text{font:11px 'DM Mono',monospace;fill:var(--accent);letter-spacing:.05em}.evidence-scene .scene-footer{fill:#748e9e;font-size:9px}.evidence-caption{padding:12px 16px;color:#8fa6b6;font-size:11px;line-height:1.7;border-top:1px solid #263b4a}
.evidence-video-block{border-bottom:1px solid #263b4a;background:#07111a}.evidence-video-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-bottom:1px solid #263b4a}.evidence-video-heading span{display:block;color:var(--accent);font:9px 'DM Mono',monospace;letter-spacing:.1em}.evidence-video-heading strong{display:block;margin-top:5px;color:#d7e5eb;font-size:13px}.evidence-video-heading>b{color:var(--accent);font:9px 'DM Mono',monospace}.evidence-video-frame{display:grid;place-items:center;min-height:220px;padding:16px;background:#050a10}.evidence-video{display:block;width:auto;max-width:100%;height:min(520px,65vh);background:#050a10;object-fit:contain}.evidence-video-note{margin:0;padding:11px 16px;color:#8fa6b6;font-size:11px;line-height:1.7}.evidence-video-note strong{color:#c6d8e2;font-weight:600}
@media(max-width:600px){.evidence-video{height:min(420px,65vh)}.evidence-video-heading{align-items:flex-start;flex-direction:column;gap:7px}}
</style>
