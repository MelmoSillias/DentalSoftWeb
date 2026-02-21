// Calculs topographiques extraits pour réutilisation et tests
export function computeCalculations(geo) {
    if (!geo || !geo.points || geo.points.length < 3) {
        return { points: [], dxdy: [], dist: [], gis: [], shoelace: [], sumD: 0, sumE: 0, area: 0, ares: '00', ca: '00' };
    }

    const pts = geo.points;
    const n = pts.length;
    const result = {
        points: [...pts, pts[0]],
        dxdy: [],
        dist: [],
        gis: [],
        shoelace: [],
        sumD: 0,
        sumE: 0,
        area: 0
    };

    for (let i = 0; i < n; i++) {
        const p1 = pts[i];
        const p2 = pts[(i + 1) % n];
        const p3 = pts[(i + 2) % n];

        const dx = p2.x - p1.x;
        const dy = p2.y - p1.y;
        const dist = Math.hypot(dx, dy);
        const gis = ((Math.atan2(dx, dy) * 200) / Math.PI + 400) % 400;

        const valD = (p1.y - p3.y) * p2.x;
        const valE = (p1.x - p3.x) * p2.y;
        result.sumD += valD;
        result.sumE += valE;

        result.dxdy.push({ dx: dx.toFixed(3), dy: dy.toFixed(3) });
        result.dist.push(dist.toFixed(2));
        result.gis.push(gis);
        result.shoelace.push({ valD: valD.toFixed(6), valE: valE.toFixed(6) });
    }

    result.area = Math.abs(result.sumD + result.sumE) / 2;
    const ares = Math.floor(result.area / 100);
    const ca = Math.round(result.area % 100);
    result.ares = ares.toString().padStart(2, '0');
    result.ca = ca.toString().padStart(2, '0');
    // Champ lisible pour l'affichage (ex: '12a 34ca' ou '3ha 12a 34ca' si ares >= 100)
    result.readableArea = formatArea(result);

    return result;
}

export function formatArea(result) {
    if (!result || typeof result.area !== 'number') return '';
    const ares = Math.floor(result.area / 100);
    const ca = Math.round(result.area % 100);
    if (ares >= 100) {
        const hectares = Math.trunc(ares / 100);
        const remAres = ares % 100;
        return `${hectares}ha ${remAres}a ${ca}ca`;
    }
    return `${ares}a ${ca}ca`;
}

export function formatVersionLabel(v) {
    if (!v) return '';
    const d = v.createdAt || v.updatedAt;
    const dateStr = d ? new Date(d).toLocaleString() : '';
    return `v${v.id}${dateStr ? ' — ' + dateStr : ''}`;
}
