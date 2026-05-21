import { chromium } from 'playwright';
import * as fs from 'fs';
import * as path from 'path';

const OUT = '.wordpress-org';

interface AssetSpec {
	svgFile: string;
	outputs: Array<{ name: string; width: number; height: number }>;
}

const assets: AssetSpec[] = [
	{
		svgFile: `${ OUT }/icon.svg`,
		outputs: [
			{ name: 'icon-128x128.png', width: 128, height: 128 },
			{ name: 'icon-256x256.png', width: 256, height: 256 },
		],
	},
	{
		svgFile: `${ OUT }/banner-772x250.svg`,
		outputs: [
			{ name: 'banner-772x250.png', width: 772, height: 250 },
			{ name: 'banner-1544x500.png', width: 1544, height: 500 },
		],
	},
];

async function main() {
	const browser = await chromium.launch();

	for ( const asset of assets ) {
		const svgContent = fs.readFileSync( asset.svgFile, 'utf8' );

		for ( const output of asset.outputs ) {
			const page = await browser.newPage( {
				viewport: { width: output.width, height: output.height },
			} );

			const html = `<!DOCTYPE html>
<html><head><style>
	* { margin: 0; padding: 0; }
	body { width: ${ output.width }px; height: ${ output.height }px; overflow: hidden; }
	svg { width: 100%; height: 100%; display: block; }
</style></head><body>${ svgContent }</body></html>`;

			await page.setContent( html );
			await page.waitForTimeout( 500 );
			await page.screenshot( {
				path: path.join( OUT, output.name ),
				type: 'png',
			} );
			await page.close();
			console.log( `Generated ${ output.name } (${ output.width }x${ output.height })` );
		}
	}

	await browser.close();
	console.log( `\nDone! Assets saved to ${ OUT }/` );
}

main().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
