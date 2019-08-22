module.exports = {
    printWidth: 95,
    tabWidth: 4,
    useTabs: false,
    semi: false,
    singleQuote: true,
    trailingComma: 'es5',
    bracketSpacing: true,
    jsxBracketSameLine: false,
    arrowParens: 'avoid',
    proseWrap: 'never',
    overrides: [
        {
            files: '*.php',
            options: {
                trailingComma: 'all',
            },
        },
    ],
}
