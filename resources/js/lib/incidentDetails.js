import { eventTypes } from './monitoring.js';

export const reviewStates = { pending: 'Chờ xác minh', reviewing: 'Đang xem xét', resolved: 'Đã xử lý' };
export const severityLabels = { high: 'Cao', medium: 'Trung bình', low: 'Thấp' };

export const incidentProfiles = {
    accident: {
        code: 'ACC', intro: 'Theo dõi va chạm, xác minh tình huống và ghi nhận xử lý.',
        scenario: 'Hai phương tiện có quỹ đạo giao nhau và dừng trong vùng quan sát minh họa.',
        evidenceLabel: 'Vùng nghi vấn va chạm', measurement: 'Phương tiện liên quan', value: '02', unit: 'phương tiện mẫu',
        facts: [['Loại tình huống', 'Nghi vấn va chạm'], ['Đối tượng mẫu', 'Ô tô / xe máy'], ['Vùng quan sát', 'Làn 02 · ROI A'], ['Mức ảnh hưởng', 'Cần người vận hành xác minh']],
        checklist: ['Kiểm tra diễn biến trước và sau thời điểm nghi vấn.', 'Xác minh có va chạm hay chỉ dừng xe.', 'Ghi nhận ảnh hưởng giao thông trước khi chuyển xử lý.'],
    },
    helmet: {
        code: 'HLM', intro: 'Rà soát người đi xe máy và tình huống nghi thiếu mũ bảo hiểm.',
        scenario: 'Một vùng đầu người trên xe máy được đánh dấu để minh họa bước xác minh.',
        evidenceLabel: 'Vùng đầu người cần kiểm tra', measurement: 'Đối tượng cần kiểm tra', value: '01', unit: 'người mẫu',
        facts: [['Đối tượng mẫu', 'Người điều khiển xe máy'], ['Vùng quan sát', 'Đầu người · ROI B'], ['Biển số', 'Chưa thu thập'], ['Kết luận vi phạm', 'Chưa xác minh']],
        checklist: ['Kiểm tra vùng đầu có bị che khuất hoặc nhòe.', 'Phân biệt mũ bảo hiểm với mũ thông thường.', 'Không kết luận vi phạm khi thiếu bằng chứng.'],
    },
    redlight: {
        code: 'RLT', intro: 'Đối chiếu tín hiệu đèn, vạch dừng và hướng di chuyển.',
        scenario: 'Quỹ đạo vượt vạch dừng khi tín hiệu mô phỏng đang ở pha đỏ.',
        evidenceLabel: 'Vạch dừng & quỹ đạo mẫu', measurement: 'Thời gian sau pha đỏ', value: '02,4', unit: 'giây mô phỏng',
        facts: [['Pha đèn mẫu', 'Đỏ'], ['Đối tượng mẫu', 'Ô tô'], ['Vạch tham chiếu', 'STOP LINE 01'], ['Đồng bộ tín hiệu', 'Chưa kết nối nguồn thật']],
        checklist: ['Đối chiếu thời điểm chuyển pha với thời điểm qua vạch.', 'Kiểm tra hướng rẽ và phạm vi tín hiệu áp dụng.', 'Loại trừ tình huống được điều tiết hoặc ưu tiên.'],
    },
    flood: {
        code: 'FLD', intro: 'Quan sát vùng nước trên mặt đường và mức ảnh hưởng lưu thông.',
        scenario: 'Vùng nước minh họa được khoanh trên mặt đường; không phải kết quả đo độ sâu.',
        evidenceLabel: 'Vùng ngập minh họa', measurement: 'Diện tích vùng ảnh', value: '36', unit: '% ROI mô phỏng',
        facts: [['Phân loại mẫu', 'Nước trên mặt đường'], ['Độ sâu ngập', 'Chưa có cảm biến'], ['Vùng quan sát', 'Mặt đường · ROI C'], ['Khả năng lưu thông', 'Chưa xác minh']],
        checklist: ['Phân biệt nước ngập với bóng đổ và phản chiếu.', 'So sánh nhiều thời điểm để xác minh vùng nước.', 'Không suy ra độ sâu hoặc an toàn lưu thông từ ảnh đơn.'],
    },
};

// Deterministic UI fixtures, separate from public cameras and all inference APIs.
export function createIncidentFixtures(typeId) {
    const type = eventTypes.find((item) => item.id === typeId);
    const profile = incidentProfiles[typeId];
    if (!type || !profile) return [];
    const firstCamera = { accident: 3, helmet: 1, redlight: 7, flood: 12 }[typeId];
    const firstMinute = { accident: 24, helmet: 20, redlight: 18, flood: 15 }[typeId];
    return Array.from({ length: type.count }, (_, index) => {
        const minute = 21 * 60 + firstMinute - index * 7;
        return {
            id: `DEMO-${profile.code}-${String(index + 1).padStart(3, '0')}`,
            type: typeId, camera: `CAM ${String(firstCamera + index % 3).padStart(2, '0')}`,
            location: `${typeId === 'flood' ? 'Điểm trũng' : 'Nút giao'} mô phỏng ${String(index % 3 + 1).padStart(2, '0')}`,
            date: '2026-10-05', time: `${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(minute % 60).padStart(2, '0')}`,
            status: index % 4 === 2 ? 'resolved' : index % 4 === 1 ? 'reviewing' : 'pending',
            severity: typeId === 'accident' || typeId === 'flood' ? 'high' : index % 3 === 2 ? 'low' : 'medium',
        };
    });
}

export function filterIncidents(rows, { query = '', status = '', severity = '' } = {}) {
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi, 'd').toLowerCase();
    const needle = normalize(query.trim());
    return rows.filter((row) => (!status || row.status === status) && (!severity || row.severity === severity)
        && normalize(`${row.id} ${row.camera} ${row.location}`).includes(needle));
}

export function incidentTypeFromView(view) {
    return eventTypes.find((type) => view === `incident-${type.id}`)?.id ?? null;
}
