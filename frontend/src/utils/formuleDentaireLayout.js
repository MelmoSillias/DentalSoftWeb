export const DENTITION_ADULTE = 'adulte';
export const DENTITION_ENFANT = 'enfant';

export const DENTITION_OPTIONS = [
    { label: 'Adulte', value: DENTITION_ADULTE },
    { label: 'Enfant', value: DENTITION_ENFANT }
];

/** Colonnes par hémimaxillaire (8 positions FDI) */
export const ARCH_COLUMNS = 8;
/** Colonne de la ligne médiane (1-based) */
export const MIDLINE_COLUMN = 9;
/** Grille totale : 8 + midline + 8 */
export const GRID_COLUMNS = 17;

const ADULT_UPPER_RIGHT = [18, 17, 16, 15, 14, 13, 12, 11];
const ADULT_UPPER_LEFT = [21, 22, 23, 24, 25, 26, 27, 28];
const ADULT_LOWER_RIGHT = [48, 47, 46, 45, 44, 43, 42, 41];
const ADULT_LOWER_LEFT = [31, 32, 33, 34, 35, 36, 37, 38];

const CHILD_UPPER_RIGHT = [55, 54, 53, 52, 51];
const CHILD_UPPER_LEFT = [61, 62, 63, 64, 65];
const CHILD_LOWER_RIGHT = [85, 84, 83, 82, 81];
const CHILD_LOWER_LEFT = [71, 72, 73, 74, 75];

/**
 * Place des dents sur l'hémimaxillaire gauche (côté patient droit à l'écran).
 * startCol : 1 pour adulte (8 dents), 3 pour enfant (5 dents centrées).
 */
function placeRightQuadrant(teeth, startCol = 1) {
    return teeth.map((tooth, index) => ({
        tooth,
        col: startCol + index
    }));
}

/** Place des dents sur l'hémimaxillaire droit (côté patient gauche à l'écran). */
function placeLeftQuadrant(teeth, startCol = 10) {
    return teeth.map((tooth, index) => ({
        tooth,
        col: startCol + index
    }));
}

function buildMatrixRow(role, rightTeeth, leftTeeth, startColRight = 1, startColLeft = 10) {
    return {
        role,
        cells: [...placeRightQuadrant(rightTeeth, startColRight), { col: MIDLINE_COLUMN, type: 'midline' }, ...placeLeftQuadrant(leftTeeth, startColLeft)]
    };
}

function buildDentitionMatrix(rightUpper, leftUpper, rightLower, leftLower, startColRight, startColLeft) {
    return {
        gridColumns: GRID_COLUMNS,
        midlineColumn: MIDLINE_COLUMN,
        rows: [buildMatrixRow('upper', rightUpper, leftUpper, startColRight, startColLeft), buildMatrixRow('lower', rightLower, leftLower, startColRight, startColLeft)]
    };
}

export const ADULTE_MATRIX = buildDentitionMatrix(ADULT_UPPER_RIGHT, ADULT_UPPER_LEFT, ADULT_LOWER_RIGHT, ADULT_LOWER_LEFT, 1, 10);

export const ENFANT_MATRIX = buildDentitionMatrix(CHILD_UPPER_RIGHT, CHILD_UPPER_LEFT, CHILD_LOWER_RIGHT, CHILD_LOWER_LEFT, 3, 11);

/** @deprecated Utiliser getMatrixForDentition */
export const ADULTE_ROWS = [
    { left: ADULT_UPPER_RIGHT, right: ADULT_UPPER_LEFT },
    { left: ADULT_LOWER_RIGHT, right: ADULT_LOWER_LEFT }
];

/** @deprecated Utiliser getMatrixForDentition */
export const ENFANT_ROWS = [
    { left: CHILD_UPPER_RIGHT, right: CHILD_UPPER_LEFT, compact: true },
    { left: CHILD_LOWER_RIGHT, right: CHILD_LOWER_LEFT, compact: true }
];

export function getMatrixForDentition(type) {
    return type === DENTITION_ENFANT ? ENFANT_MATRIX : ADULTE_MATRIX;
}

/** @deprecated Utiliser getMatrixForDentition */
export function getRowsForDentition(type) {
    return type === DENTITION_ENFANT ? ENFANT_ROWS : ADULTE_ROWS;
}

export function defaultDentitionFromAge(age) {
    const years = Number(age);
    if (!Number.isFinite(years) || years <= 0) {
        return DENTITION_ADULTE;
    }
    return years > 5 ? DENTITION_ADULTE : DENTITION_ENFANT;
}

export function computeAgeYears(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const numericAge = Number(value);
    if (Number.isFinite(numericAge) && numericAge > 0) {
        return Math.floor(numericAge);
    }

    const birth = new Date(value);
    if (Number.isNaN(birth.getTime())) {
        return 0;
    }

    const today = new Date();
    let years = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        years -= 1;
    }
    return Math.max(years, 0);
}

export function hasToothData(entry) {
    if (!entry) {
        return false;
    }
    if (entry.etat && entry.etat.length) {
        return true;
    }
    if (entry.estCausale) {
        return true;
    }
    if (entry.diagnosticSuppose) {
        return true;
    }
    if (entry.examensComplementaires && entry.examensComplementaires.length) {
        return true;
    }
    return Object.values(entry.siCausale || {}).some((value) => value);
}

export function toothSummary(form, tooth) {
    const entry = form?.[tooth];
    if (!entry?.etat || entry.etat.length === 0) {
        return '';
    }
    return Array.isArray(entry.etat) ? entry.etat.join('-') : String(entry.etat);
}

export function isRootRow(role) {
    return role === 'upper-roots' || role === 'lower-roots';
}

export function isCrownRow(role) {
    return role === 'upper-crowns' || role === 'lower-crowns';
}

/**
 * Catégorie anatomique de la dent d'après sa notation FDI.
 * @returns {'incisor'|'canine'|'premolar'|'molar'}
 */
export function toothCategory(tooth) {
    const n = Number(tooth);
    if (!Number.isFinite(n)) return 'incisor';
    const quadrant = Math.floor(n / 10);
    const position = n % 10;
    const isDeciduous = quadrant >= 5;

    if (position === 1 || position === 2) return 'incisor';
    if (position === 3) return 'canine';
    if (isDeciduous) return 'molar';
    if (position === 4 || position === 5) return 'premolar';
    return 'molar';
}

/** True si la dent appartient à l'arcade supérieure (maxillaire). */
export function isUpperTooth(tooth) {
    const quadrant = Math.floor(Number(tooth) / 10);
    return quadrant === 1 || quadrant === 2 || quadrant === 5 || quadrant === 6;
}

/**
 * Modèle anatomique détaillé d'après la notation FDI.
 * Encode le type ET la configuration radiculaire (nombre de racines)
 * qui dépend de l'arcade (ex : molaire maxillaire = 3 racines, mandibulaire = 2).
 * @returns {'incisor-central'|'incisor-lateral'|'canine'|'premolar-2root'|'premolar-1root'|'molar-3root'|'molar-2root'}
 */
export function toothModel(tooth) {
    const n = Number(tooth);
    if (!Number.isFinite(n)) return 'incisor-central';
    const quadrant = Math.floor(n / 10);
    const position = n % 10;
    const deciduous = quadrant >= 5;
    const upper = isUpperTooth(n);

    if (position === 1) return 'incisor-central';
    if (position === 2) return 'incisor-lateral';
    if (position === 3) return 'canine';

    // Prémolaires permanentes (positions 4 et 5 en denture définitive)
    if (!deciduous && (position === 4 || position === 5)) {
        if (upper && position === 4) return 'premolar-2root';
        return 'premolar-1root';
    }

    // Molaires (permanentes 6/7/8 + molaires de lait 4/5)
    return upper ? 'molar-3root' : 'molar-2root';
}
