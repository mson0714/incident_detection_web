export function catalogUrl(query = '', region = '', page = 1) {
    const params = new URLSearchParams({ page: String(page), per_page: '24' });
    if (query.trim()) params.set('q', query.trim());
    if (region) params.set('region', region);
    return `/api/cctv?${params}`;
}

export function isLocalVideo(file) {
    return Boolean(file && file.size > 0 && (
        ['video/mp4', 'video/webm', 'video/ogg'].includes(file.type)
        || (!file.type && /\.(mp4|webm|ogv)$/i.test(file.name))
    ));
}

export function snapshotUrl(url, timestamp = Date.now()) {
    if (typeof url !== 'string' || !/^https:\/\/giaothong\.hochiminhcity\.gov\.vn\/render\/ImageHandler\.ashx\?id=[a-f0-9]{24}$/i.test(url)) return null;
    return `${url}&_t=${encodeURIComponent(timestamp)}`;
}
