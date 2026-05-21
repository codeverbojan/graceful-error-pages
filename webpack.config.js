const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const { BannerPlugin } = require( 'webpack' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const CopyPlugin = require( 'copy-webpack-plugin' );
const cssnano = require( 'cssnano' );
const postcss = require( 'postcss' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'assets/src/js/admin.js' ),
		'merge-tags': path.resolve( __dirname, 'assets/src/js/merge-tags.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
	optimization: {
		...defaultConfig.optimization,
		minimizer: [
			new TerserPlugin( {
				parallel: true,
				terserOptions: {
					output: {
						comments: /translators:|^!/i,
					},
					compress: {
						passes: 2,
					},
					mangle: {
						reserved: [ '__', '_n', '_nx', '_x' ],
					},
				},
				extractComments: false,
			} ),
		],
	},
	plugins: [
		...( defaultConfig.plugins || [] ),
		new CopyPlugin( {
			patterns: [
				{
					from: path.resolve(
						__dirname,
						'assets/src/css/admin.css'
					),
					to: path.resolve(
						__dirname,
						'assets/build/css/admin.css'
					),
					transform: ( content ) =>
						postcss( [ cssnano( { preset: 'default' } ) ] )
							.process( content, { from: undefined } )
							.then( ( result ) => result.css ),
				},
				{
					from: path.resolve(
						__dirname,
						'assets/src/css/error-page.css'
					),
					to: path.resolve(
						__dirname,
						'assets/build/css/error-page.css'
					),
					transform: ( content ) =>
						postcss( [ cssnano( { preset: 'default' } ) ] )
							.process( content, { from: undefined } )
							.then( ( result ) => result.css ),
				},
			],
		} ),
		new BannerPlugin( {
			banner:
				'/*! Graceful Error Pages | Source: https://github.com/codeverbojan/graceful-error-pages | License: GPLv2+ */',
			raw: true,
		} ),
	],
};
