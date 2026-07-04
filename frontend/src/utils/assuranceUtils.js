import { filePrefix } from '@/config';

export const resolveAssuranceLogoUrl = (logoPath) => {
    if (!logoPath) {
        return null;
    }

    if (/^https?:\/\//i.test(logoPath)) {
        return logoPath;
    }

    const prefix = filePrefix.replace(/\/$/, '');
    return `${prefix}${logoPath.startsWith('/') ? logoPath : `/${logoPath}`}`;
};
