export const DEFAULT_FORM_TEMPLATE_KEY = 'fiche_medicale_v2';

export const FORM_TEMPLATE_TO_VERSION = {
    fiche_observation_v1: 'v1',
    fiche_medicale_v2: 'v2'
};

export const resolveFormVersion = (formTemplateKey) => {
    if (!formTemplateKey || typeof formTemplateKey !== 'string') {
        return FORM_TEMPLATE_TO_VERSION[DEFAULT_FORM_TEMPLATE_KEY];
    }

    return FORM_TEMPLATE_TO_VERSION[formTemplateKey] || FORM_TEMPLATE_TO_VERSION[DEFAULT_FORM_TEMPLATE_KEY];
};

export const isV1Template = (formTemplateKey) => resolveFormVersion(formTemplateKey) === 'v1';
export const isV2Template = (formTemplateKey) => resolveFormVersion(formTemplateKey) === 'v2';
