import { toDataURL } from 'qrcode';

export async function createQrDataUrl(text, size = 260) {
    const value = String(text || '').trim();
    if (!value) {
        return '';
    }

    try {
        return await toDataURL(value, {
            width: Number(size) > 0 ? Number(size) : 260,
            margin: 1,
            errorCorrectionLevel: 'M'
        });
    } catch {
        return '';
    }
}
