import { chromium, type Page, type BrowserContext } from 'playwright';

const BASE = 'http://localhost:8888';
const USER = 'admin';
const PASS = 'password';
const OUT = '.wordpress-org';

async function hideWpNoise( page: Page ) {
	await page.evaluate( () => {
		const style = document.createElement( 'style' );
		style.textContent = `
			#wpadminbar { display: none !important; }
			html.wp-toolbar { padding-top: 0 !important; }
			#adminmenuwrap { margin-top: 0 !important; }
			#wpfooter { display: none !important; }
			.update-nag, .notice:not(.gcep-notice), .updated { display: none !important; }
			#screen-meta, #screen-meta-links { display: none !important; }
		`;
		document.head.appendChild( style );
	} );
}

async function settle( page: Page, ms = 1500 ) {
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( ms );
}

async function getPreviewNonce( page: Page ): Promise<string> {
	return page.evaluate( () => {
		const el = document.getElementById( 'gcep-admin-js-extra' );
		if ( el ) {
			const match = el.textContent?.match( /"previewNonce":"([^"]+)"/ );
			if ( match ) {
				return match[ 1 ];
			}
		}
		// Fallback: look in all inline scripts.
		const scripts = document.querySelectorAll( 'script' );
		for ( const s of scripts ) {
			const m = s.textContent?.match( /"previewNonce":"([^"]+)"/ );
			if ( m ) {
				return m[ 1 ];
			}
		}
		return '';
	} );
}

async function screenshotPreview(
	context: BrowserContext,
	nonce: string,
	template: string,
	outFile: string,
	overrides: Record<string, string> = {}
) {
	const params = new URLSearchParams( {
		action: 'gcep_preview',
		_ajax_nonce: nonce,
		gcep_template: template,
		gcep_site_name: 'Acme Corp',
		gcep_primary_btn_text: 'Go to Homepage',
		gcep_primary_btn_url: '#',
		gcep_secondary_btn_text: 'Go Back',
		gcep_secondary_btn_url: '#',
		gcep_copyright: '© 2026 Acme Corp. All rights reserved.',
		...overrides,
	} );

	const previewPage = await context.newPage();
	await previewPage.goto( `${ BASE }/wp-admin/admin-ajax.php?${ params }` );
	await previewPage.waitForLoadState( 'networkidle' );
	await previewPage.waitForTimeout( 1000 );
	await previewPage.screenshot( {
		path: `${ OUT }/${ outFile }`,
		fullPage: false,
	} );
	await previewPage.close();
}

async function main() {
	const browser = await chromium.launch();
	const context = await browser.newContext( {
		viewport: { width: 1440, height: 900 },
	} );
	const page = await context.newPage();

	// Login.
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'networkidle' );
	console.log( 'Logged in.' );

	// --- 1. Minimal template (default error page) ---
	// Navigate to the settings page first to get the preview nonce.
	await page.goto( `${ BASE }/wp-admin/options-general.php?page=gcep-settings` );
	await settle( page );
	const nonce = await getPreviewNonce( page );
	if ( ! nonce ) {
		console.error( 'Could not find preview nonce. Aborting.' );
		await browser.close();
		process.exit( 1 );
	}
	console.log( `Got preview nonce: ${ nonce.slice( 0, 4 ) }...` );

	await screenshotPreview( context, nonce, 'minimal', 'screenshot-1.png' );
	console.log( '1. Minimal template (hero)' );

	// --- 2. Settings — Design tab ---
	await page.goto(
		`${ BASE }/wp-admin/options-general.php?page=gcep-settings&tab=design`
	);
	await settle( page );
	await hideWpNoise( page );
	await page.screenshot( {
		path: `${ OUT }/screenshot-2.png`,
		fullPage: false,
	} );
	console.log( '2. Settings — Design tab' );

	// --- 3. Settings — Content tab ---
	await page.goto(
		`${ BASE }/wp-admin/options-general.php?page=gcep-settings&tab=content`
	);
	await settle( page );
	await hideWpNoise( page );
	await page.screenshot( {
		path: `${ OUT }/screenshot-3.png`,
		fullPage: false,
	} );
	console.log( '3. Settings — Content tab' );

	// --- 4. Corporate template ---
	await screenshotPreview( context, nonce, 'corporate', 'screenshot-4.png' );
	console.log( '4. Corporate template' );

	// --- 5. Dark template ---
	await screenshotPreview( context, nonce, 'dark', 'screenshot-5.png', {
		gcep_dark_mode: 'on',
	} );
	console.log( '5. Dark template' );

	await browser.close();
	console.log( `\nDone! Screenshots saved to ${ OUT }/` );
}

main().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
