// UI fixtures are not inference results and must always be labeled as demo data.
export const eventTypes = [
    { id: 'accident', label: 'Accident', description: 'Tai nạn giao thông', count: 3, unit: 'events', color: '#ff756f', model: 'Accident AI', input: 'CCTV giao thông', output: 'Va chạm / dừng bất thường', icon: 'accident' },
    { id: 'helmet', label: 'No Helmet', description: 'Không đội mũ bảo hiểm', count: 18, unit: 'events', color: '#f4a261', model: 'Helmet AI', input: 'CCTV làn xe máy', output: 'Đối tượng cần xác minh', icon: 'helmet' },
    { id: 'redlight', label: 'Red Light', description: 'Vượt đèn đỏ', count: 7, unit: 'events', color: '#e8cd74', model: 'Red Light AI', input: 'CCTV nút giao', output: 'Quỹ đạo qua vạch dừng', icon: 'traffic' },
    { id: 'flood', label: 'Flood', description: 'Điểm ngập nước', count: 2, unit: 'locations', color: '#75b9f3', model: 'Flood AI', input: 'CCTV mặt đường', output: 'Vùng nước / lưu thông', icon: 'water' },
];

export const demoEvents = [
    { id: 'DEMO-001', type: 'accident', camera: 'CAM 03', time: '21:24', note: 'Tình huống tai nạn minh họa cho bố cục cảnh báo.' },
    { id: 'DEMO-002', type: 'helmet', camera: 'CAM 01', time: '21:20', note: 'Tình huống không đội mũ bảo hiểm minh họa.' },
    { id: 'DEMO-003', type: 'redlight', camera: 'CAM 07', time: '21:18', note: 'Tình huống vượt đèn đỏ minh họa.' },
    { id: 'DEMO-004', type: 'flood', camera: 'CAM 12', time: '21:15', note: 'Vị trí ngập minh họa, chưa có kết quả nhận diện.' },
];

export const overviewCameras = [
    {
        id: '209690', name: '11B Nguyễn Bỉnh Khiêm', region: 'TP. Hồ Chí Minh',
        page_url: 'https://opencctv.org/cameras/vietnam/ho-chi-minh-city/11b-nguyen-binh-khiem-209690',
        latitude: 10.7857353555811, longitude: 106.706600189209,
    },
    {
        id: '210152', name: '255 Hoàng Văn Thụ', region: 'TP. Hồ Chí Minh',
        page_url: 'https://opencctv.org/cameras/vietnam/ho-chi-minh-city/255-hoang-van-thu-210152',
    },
];

export function filterEvents(events, type = 'all') {
    return type === 'all' ? events : events.filter((event) => event.type === type);
}

export function mappedCameras(cameras) {
    return cameras.filter((camera) => Number.isFinite(camera.latitude) && Number.isFinite(camera.longitude)
        && Math.abs(camera.latitude) <= 90 && Math.abs(camera.longitude) <= 180);
}
