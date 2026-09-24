<?php
/**
 * Build the email signature: a PNG of the logo and the HTML that uses it.
 *
 * Email clients don't render SVG, and Gmail drops images embedded as data
 * URIs, so the logo goes out as a PNG hosted on the site. It is rendered at
 * three times its display size so it stays sharp on high-density screens,
 * on a white ground rather than transparency: a transparent logo in Steel Ink
 * disappears in a dark-mode inbox.
 *
 * The signature is set in tables with inline styles, because that is all
 * Outlook reliably honours. Archivo won't be installed on most readers'
 * machines, so every font stack falls back to Arial or a system monospace.
 * Two details carry over from the site: mono caps labels, and the dimension
 * rule, drawn here with table borders.
 *
 * Usage: php brand/email-signature.php
 * Output: brand/email/lifttables-logo-email.png (630 x 120)
 *         brand/email/signature.html (preview with a copy button)
 *
 * The PNG has to be uploaded to LOGO_URL before the signature is pasted into
 * a mail client, since the signature points there.
 */

require dirname( __DIR__ ) . '/generators/lib/render-png.php';

const LOGO_URL = 'https://lifttables.us/wp-content/uploads/brand/lifttables-logo-email.png';
const SITE_URL = 'https://lifttables.us/';
const SCALE    = 3;

$person = array(
	'name'  => 'Tim Statler',
	'title' => 'Founder, LiftTables.us',
	'email' => 'tim@lifttables.us',
);

$root = __DIR__;
$out  = $root . '/email';

if ( ! is_dir( $out ) ) {
	mkdir( $out, 0777, true );
}

/* ------------------------------------------------------------------ */
/* The logo                                                           */
/* ------------------------------------------------------------------ */

$logo = $root . '/logo.svg';
$png  = $out . '/lifttables-logo-email.png';
$tmp  = sys_get_temp_dir() . '/edc-email-logo';

if ( ! is_dir( $tmp ) ) {
	mkdir( $tmp, 0777, true );
}

$page = $tmp . '/logo.html';

file_put_contents(
	$page,
	'<!doctype html><html><head><meta charset="utf-8"><style>'
	. 'html,body{margin:0;width:210px;height:40px;overflow:hidden;background:#fff}'
	. 'img{display:block;width:210px;height:40px}'
	. '</style></head><body><img src="data:image/svg+xml;base64,' . base64_encode( file_get_contents( $logo ) ) . '" alt=""></body></html>'
);

$command = sprintf(
	'%s --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=%d --window-size=210,40 --screenshot=%s %s 2>&1',
	escapeshellarg( edc_find_chrome() ),
	SCALE,
	escapeshellarg( $png ),
	escapeshellarg( 'file:///' . str_replace( '\\', '/', ltrim( realpath( $page ), '/' ) ) )
);

exec( $command, $output, $status );
unlink( $page );
@rmdir( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

if ( 0 !== $status || ! is_file( $png ) || filemtime( $png ) < time() - 60 ) {
	fwrite( STDERR, 'Failed to render the logo: ' . implode( "\n", $output ) . "\n" );
	exit( 1 );
}

/* ------------------------------------------------------------------ */
/* The signature                                                      */
/* ------------------------------------------------------------------ */

$sans = "Archivo,'Helvetica Neue',Arial,sans-serif";
$mono = "'IBM Plex Mono',Consolas,Menlo,'Courier New',monospace";
$ink  = '#18212A';
$mute = '#5C6975';
$rule = '#AAB4BD';

// Clicks from the signature show up as their own source in analytics.
$track = '?utm_source=email&utm_medium=signature';

$e = static function ( $s ) {
	return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
};

$label = static function ( $text ) use ( $mono, $mute ) {
	return '<td style="padding:0 12px 4px 0;width:44px;font-family:' . $mono . ';font-size:10px;line-height:18px;letter-spacing:1px;color:' . $mute . ';vertical-align:top;">' . $text . '</td>';
};

$link = static function ( $href, $text ) use ( $sans, $ink ) {
	return '<a href="' . $href . '" style="font-family:' . $sans . ';font-size:13px;line-height:18px;color:' . $ink . ';text-decoration:underline;text-decoration-color:#CE9B00;">' . $text . '</a>';
};

$tick = 'height:4px;line-height:4px;font-size:0;border-left:1px solid ' . $rule . ';border-right:1px solid ' . $rule . ';';

$signature = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-family:' . $sans . ';color:' . $ink . ';">'
	. '<tr><td style="padding:0 0 14px 0;">'
	. '<a href="' . SITE_URL . $track . '" style="text-decoration:none;"><img src="' . LOGO_URL . '" width="210" height="40" alt="Lift Tables" style="display:block;border:0;width:210px;height:40px;"></a>'
	. '</td></tr>'
	. '<tr><td style="font-family:' . $sans . ';font-size:16px;line-height:20px;font-weight:bold;color:' . $ink . ';">' . $e( $person['name'] ) . '</td></tr>'
	. '<tr><td style="padding-top:3px;font-family:' . $mono . ';font-size:11px;line-height:16px;letter-spacing:1px;color:' . $mute . ';">' . $e( strtoupper( $person['title'] ) ) . '</td></tr>'

	// The dimension rule: a hairline between two end ticks.
	. '<tr><td style="padding:10px 0 10px 0;">'
	. '<table role="presentation" width="300" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;width:300px;">'
	. '<tr><td height="4" style="' . $tick . 'border-bottom:1px solid ' . $rule . ';">&nbsp;</td></tr>'
	. '<tr><td height="4" style="' . $tick . '">&nbsp;</td></tr>'
	. '</table>'
	. '</td></tr>'

	. '<tr><td>'
	. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">'
	. '<tr>' . $label( 'EMAIL' ) . '<td style="padding:0 0 4px 0;">' . $link( 'mailto:' . $person['email'], $e( $person['email'] ) ) . '</td></tr>'
	. '<tr>' . $label( 'WEB' ) . '<td style="padding:0 0 4px 0;">' . $link( SITE_URL . $track, 'lifttables.us' ) . '</td></tr>'
	. '</table>'
	. '</td></tr>'

	// Safety Yellow is for quote buttons, and this is one.
	. '<tr><td style="padding:12px 0 0 0;">'
	. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;"><tr>'
	. '<td bgcolor="#F5B800" style="background:#F5B800;padding:8px 14px;">'
	. '<a href="' . SITE_URL . 'request-a-quote/' . $track . '" style="font-family:' . $sans . ';font-size:12px;line-height:16px;font-weight:bold;letter-spacing:1px;color:' . $ink . ';text-decoration:none;">REQUEST A QUOTE &rarr;</a>'
	. '</td></tr></table>'
	. '</td></tr>'

	. '<tr><td style="padding:10px 0 0 0;font-family:' . $sans . ';font-size:12px;line-height:17px;color:' . $mute . ';">Compare lift tables by capacity, height and manufacturer.</td></tr>'
	. '</table>';

/* ------------------------------------------------------------------ */
/* The preview page                                                   */
/* ------------------------------------------------------------------ */

$preview = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lift Tables Email Signature</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,400..800&family=IBM+Plex+Mono:wght@400;500&display=swap">
<style>
:root { --ink:#18212a; --muted:#5c6975; --line:#d6dce1; --surface:#f3f5f7; --grid:#e8ecef; --yellow:#f5b800; }
* { box-sizing: border-box; }
body { margin:0; padding:48px 16px; background:var(--surface); color:var(--ink); font:16px/1.55 Archivo, Arial, sans-serif; }
main { max-width:760px; margin:0 auto; display:grid; gap:28px; }
h1 { margin:0; font-size:2rem; font-stretch:118%; font-weight:800; line-height:1.1; text-wrap:balance; }
.label { margin:0 0 6px; font:500 .72rem/1 "IBM Plex Mono", Consolas, monospace; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); }
.stage { background:#fff; border:1px solid var(--line); padding:36px 32px; background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px); background-size:24px 24px; }
.stage__inner { background:#fff; display:inline-block; padding:20px 24px; outline:1px dashed var(--line); }
.actions { display:flex; flex-wrap:wrap; gap:12px; align-items:center; }
button { font:700 .8rem/1 Archivo, Arial, sans-serif; letter-spacing:.08em; text-transform:uppercase; padding:12px 18px; border:1px solid var(--ink); background:#fff; color:var(--ink); cursor:pointer; }
button.primary { background:var(--yellow); border-color:var(--yellow); }
button:focus-visible { outline:3px solid var(--ink); outline-offset:2px; }
#status { font:500 .8rem "IBM Plex Mono", Consolas, monospace; color:var(--muted); }
ol { margin:0; padding-left:1.2em; max-width:65ch; }
li + li { margin-top:6px; }
h2 { margin:0 0 8px; font-size:1.05rem; }
textarea { width:100%; height:160px; font:12px/1.5 "IBM Plex Mono", Consolas, monospace; border:1px solid var(--line); padding:12px; background:#fff; color:var(--ink); }
</style>
</head>
<body>
<main>
<div>
<p class="label">LiftTables.us &middot; Brand</p>
<h1>Email signature</h1>
</div>

<div class="stage"><div class="stage__inner" id="signature">{$signature}</div></div>

<div class="actions">
<button class="primary" id="copy" type="button">Copy signature</button>
<button id="copy-html" type="button">Copy HTML source</button>
<span id="status" role="status"></span>
</div>

<section>
<h2>Gmail</h2>
<ol>
<li>Click <strong>Copy signature</strong> above.</li>
<li>In Gmail, open Settings &rarr; See all settings &rarr; General &rarr; Signature, and create a new one.</li>
<li>Paste into the signature box, then set it as the default for new emails and replies.</li>
<li>Scroll down and click <strong>Save changes</strong>.</li>
</ol>
</section>

<section>
<h2>Outlook and Apple Mail</h2>
<ol>
<li>Outlook: Settings &rarr; Mail &rarr; Compose and reply &rarr; Email signature, then paste with <strong>Copy signature</strong>.</li>
<li>Apple Mail: paste into a new signature, and untick &ldquo;Always match my default message font&rdquo;.</li>
<li>For a client that takes raw HTML, use <strong>Copy HTML source</strong>.</li>
</ol>
</section>

<section>
<h2>HTML source</h2>
<textarea id="source" readonly></textarea>
</section>
</main>
<script>
(function () {
	var sig = document.getElementById('signature');
	var src = document.getElementById('source');
	var status = document.getElementById('status');
	src.value = sig.innerHTML;

	function say(text) { status.textContent = text; }

	// A selection copy carries the formatting in every browser, including
	// from a file:// page where the async clipboard may be refused.
	document.getElementById('copy').addEventListener('click', function () {
		var range = document.createRange();
		range.selectNodeContents(sig);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
		var ok = false;
		try { ok = document.execCommand('copy'); } catch (e) {}
		sel.removeAllRanges();
		say(ok ? 'Signature copied. Paste it into your mail settings.' : 'Copy was blocked. Select the signature and press Ctrl+C.');
	});

	document.getElementById('copy-html').addEventListener('click', function () {
		src.select();
		var ok = false;
		try { ok = document.execCommand('copy'); } catch (e) {}
		say(ok ? 'HTML source copied.' : 'Copy was blocked. Select the source and press Ctrl+C.');
	});
})();
</script>
</body>
</html>
HTML;

file_put_contents( $out . '/signature.html', $preview );

$size = getimagesize( $png );
echo "brand/email/lifttables-logo-email.png written ({$size[0]} x {$size[1]})\n";
echo "brand/email/signature.html written\n";
