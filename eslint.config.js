const wordpress = require( '@wordpress/eslint-plugin' );

module.exports = [
	...wordpress.configs.recommended,
	{
		languageOptions: {
			globals: {
				gcepAdmin: 'readonly',
				gcepMergeTags: 'readonly',
				NodeFilter: 'readonly',
			},
		},
		settings: {
			'import/resolver': {
				node: {
					extensions: [ '.js' ],
				},
			},
			'import/core-modules': [
				'@wordpress/dom-ready',
				'jquery',
			],
		},
		rules: {
			'import/no-extraneous-dependencies': [
				'error',
				{
					peerDependencies: true,
					devDependencies: [
						'**/*.config.js',
						'**/*.test.js',
					],
					packageDir: '.',
				},
			],
			'import/no-unresolved': [
				'error',
				{ ignore: [ '^@wordpress/', '^jquery$' ] },
			],
			'@wordpress/no-global-active-element': 'off',
			'@wordpress/no-global-get-selection': 'off',
			'no-nested-ternary': 'off',
			'no-bitwise': [ 'error', { allow: [ '|' ] } ],
		},
	},
];
