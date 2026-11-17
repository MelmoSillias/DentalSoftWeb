/* eslint-env node */
require('@rushstack/eslint-patch/modern-module-resolution');

module.exports = {
    root: true,
    env: {
        node: true,
        es2021: true
    },
    extends: ['plugin:vue/vue3-essential', 'eslint:recommended', '@vue/eslint-config-prettier'],
    parserOptions: {
        ecmaVersion: 'latest'
    },
    overrides: [
        {
            files: ['src/domains/billing/components/CaisseInvoiceDialogs.vue'],
            rules: {
                // The parent deliberately shares editable invoice/payment drafts with this dialog.
                'vue/no-mutating-props': ['error', { shallowOnly: true }]
            }
        }
    ],
    rules: {
        // Preserve deliberately inactive legacy handlers without hiding new unused bindings.
        'no-unused-vars': ['error', { varsIgnorePattern: '^_', argsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_' }],

        'vue/multi-word-component-names': 'off',
        'vue/no-reserved-component-names': 'off',
        'vue/no-v-model-argument': 'off',
        'vue/component-tags-order': [
            'error',
            {
                order: ['script', 'template', 'style']
            }
        ]
    }
};
