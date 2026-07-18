<?php
/**
 * The printable brand pack: a print-optimised HTML page at /brand-pack/
 * that the club supplies to printers, press, broadcasters, and sponsors.
 * Print-to-PDF from the browser produces the distributable document.
 *
 * Contents: cover (badge, name, tagline), logo suite on light and dark
 * with download links, colour swatches with hex/RGB and the print specs
 * (CMYK/Pantone), typography specimen of the uploaded brand font,
 * naming rules, usage notes, social handles, and the brand contact.
 *
 * @package FanOwnershipPlatform
 */

defined( 'ABSPATH' ) || exit;

class PRX3_Brand {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function endpoint() {
		add_rewrite_rule( '^brand-pack/?$', 'index.php?prx3_brand_pack=1', 'top' );
		add_rewrite_tag( '%prx3_brand_pack%', '1' );
	}

	/**
	 * Hex to "R G B" for the printable colour table.
	 *
	 * @param string $hex #rrggbb.
	 * @return string
	 */
	public static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '';
		}
		return sprintf( '%d %d %d', hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	public static function maybe_render() {
		if ( ! get_query_var( 'prx3_brand_pack' ) ) {
			return;
		}
		status_header( 200 );
		nocache_headers();
		$brand   = prx3_brand_pack();
		$club    = prx3_club_name();
		$tagline = prx3_setting( 'club_tagline', '' );

		$colours = array(
			array( __( 'Primary', 'fan-ownership' ), $brand['primary'], prx3_setting( 'club_primary_print', '' ) ),
			array( __( 'Secondary', 'fan-ownership' ), $brand['secondary'], prx3_setting( 'club_secondary_print', '' ) ),
			array( __( 'Third colour', 'fan-ownership' ), $brand['tertiary'], prx3_setting( 'club_tertiary_print', '' ) ),
		);
		$logos   = array(
			array( __( 'Badge / crest', 'fan-ownership' ), $brand['badge'], 'light' ),
			array( __( 'Inverted badge', 'fan-ownership' ), $brand['badge_inverted'], 'dark' ),
			array( __( 'Monochrome (dark)', 'fan-ownership' ), prx3_brand_asset( 'badge_mono_dark' ), 'light' ),
			array( __( 'Monochrome (light)', 'fan-ownership' ), prx3_brand_asset( 'badge_mono_light' ), 'dark' ),
			array( __( 'Social media badge', 'fan-ownership' ), $brand['badge_social'], 'light' ),
			array( __( 'SVG badge (vector master)', 'fan-ownership' ), $brand['badge_svg'], 'light' ),
			array( __( 'Wordmark', 'fan-ownership' ), $brand['wordmark'], 'light' ),
			array( __( 'App icon', 'fan-ownership' ), $brand['app_icon'], 'light' ),
		);

		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $club . ' — ' . __( 'Brand Pack', 'fan-ownership' ) ); ?></title>
<style>
		<?php if ( $brand['font_file'] && $brand['font_name'] ) : ?>
	@font-face { font-family: "<?php echo esc_attr( $brand['font_name'] ); ?>"; src: url("<?php echo esc_url( $brand['font_file'] ); ?>"); font-display: swap; }
	<?php endif; ?>
	:root { --primary: <?php echo esc_html( $brand['primary'] ); ?>; --secondary: <?php echo esc_html( $brand['secondary'] ); ?>; --tertiary: <?php echo esc_html( $brand['tertiary'] ); ?>; }
	* { box-sizing: border-box; }
	body { margin: 0; font-family: <?php echo $brand['font_name'] ? '"' . esc_attr( $brand['font_name'] ) . '",' : ''; ?> system-ui, sans-serif; color: #111; }
	.page { max-width: 60rem; margin: 0 auto; padding: 3rem 2rem; page-break-after: always; }
	.cover { background: var(--primary); color: #fff; min-height: 60vh; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
	.cover img { max-height: 12rem; margin-bottom: 2rem; }
	h1 { font-size: 2.6rem; margin: 0 0 .5rem; }
	h2 { border-bottom: 3px solid var(--tertiary); padding-bottom: .35rem; margin-top: 0; }
	.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: 1.25rem; }
	.tile { border: 1px solid #ccc; border-radius: 8px; overflow: hidden; }
	.tile .swatch-area { display: flex; align-items: center; justify-content: center; height: 9rem; }
	.tile.on-light .swatch-area { background: #fff; }
	.tile.on-dark .swatch-area { background: var(--primary); }
	.tile img { max-height: 7rem; max-width: 90%; }
	.tile figcaption { padding: .6rem .8rem; font-size: .85rem; border-top: 1px solid #ccc; }
	table { border-collapse: collapse; width: 100%; }
	th, td { border: 1px solid #bbb; padding: .5rem .8rem; text-align: left; font-size: .95rem; }
	.chip { display: inline-block; width: 3.2rem; height: 2rem; border-radius: 4px; border: 1px solid #999; vertical-align: middle; }
	.specimen { font-size: 2rem; line-height: 1.3; margin: .8rem 0; }
	.muted { color: #555; font-size: .9rem; }
	.noprint { position: fixed; top: 1rem; right: 1rem; }
	.noprint button { padding: .6rem 1.2rem; font: inherit; cursor: pointer; }
	@media print { .noprint { display: none; } .page { padding: 1.5rem 0; } a { color: inherit; text-decoration: none; } }
</style>
</head>
<body>
<p class="noprint"><button onclick="window.print()"><?php esc_html_e( 'Print / save as PDF', 'fan-ownership' ); ?></button></p>

<section class="page cover">
		<?php if ( $brand['badge_inverted'] ? true : (bool) $brand['badge'] ) : ?>
		<img src="<?php echo esc_url( $brand['badge_inverted'] ? $brand['badge_inverted'] : $brand['badge'] ); ?>" alt="<?php echo esc_attr( $club ); ?>">
	<?php endif; ?>
	<h1><?php echo esc_html( $club ); ?></h1>
		<?php
		if ( $tagline ) :
			?>
			<p style="font-size:1.3rem;"><?php echo esc_html( $tagline ); ?></p><?php endif; ?>
	<p><?php esc_html_e( 'Brand Pack', 'fan-ownership' ); ?> — <?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></p>
</section>

<section class="page">
	<h2><?php esc_html_e( 'Our name', 'fan-ownership' ); ?></h2>
	<table>
		<tr><th scope="row"><?php esc_html_e( 'Full name', 'fan-ownership' ); ?></th><td><?php echo esc_html( $club ); ?></td></tr>
		<?php
		if ( prx3_setting( 'club_legal_name', '' ) ) :
			?>
			<tr><th scope="row"><?php esc_html_e( 'Legal name', 'fan-ownership' ); ?></th><td><?php echo esc_html( prx3_setting( 'club_legal_name', '' ) ); ?></td></tr><?php endif; ?>
		<?php
		if ( prx3_setting( 'club_short_name', '' ) ) :
			?>
			<tr><th scope="row"><?php esc_html_e( 'Short name', 'fan-ownership' ); ?></th><td><?php echo esc_html( prx3_setting( 'club_short_name', '' ) ); ?></td></tr><?php endif; ?>
		<?php
		if ( prx3_setting( 'club_abbreviation', '' ) ) :
			?>
			<tr><th scope="row"><?php esc_html_e( 'Abbreviation', 'fan-ownership' ); ?></th><td><?php echo esc_html( prx3_setting( 'club_abbreviation', '' ) ); ?></td></tr><?php endif; ?>
		<?php
		if ( prx3_setting( 'club_founded', '' ) ) :
			?>
			<tr><th scope="row"><?php esc_html_e( 'Founded', 'fan-ownership' ); ?></th><td><?php echo esc_html( prx3_setting( 'club_founded', '' ) ); ?></td></tr><?php endif; ?>
	</table>

	<h2 style="margin-top:2.5rem;"><?php esc_html_e( 'Logo suite', 'fan-ownership' ); ?></h2>
	<div class="grid">
		<?php foreach ( $logos as $logo ) : ?>
			<?php if ( $logo[1] ) : ?>
			<figure class="tile on-<?php echo esc_attr( $logo[2] ); ?>" style="margin:0;">
				<div class="swatch-area"><img src="<?php echo esc_url( $logo[1] ); ?>" alt="<?php echo esc_attr( $logo[0] ); ?>"></div>
				<figcaption><?php echo esc_html( $logo[0] ); ?><br><a href="<?php echo esc_url( $logo[1] ); ?>" class="muted"><?php esc_html_e( 'Download', 'fan-ownership' ); ?></a></figcaption>
			</figure>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</section>

<section class="page">
	<h2><?php esc_html_e( 'Colours', 'fan-ownership' ); ?></h2>
	<table>
		<thead><tr><th scope="col"><?php esc_html_e( 'Colour', 'fan-ownership' ); ?></th><th scope="col"><?php esc_html_e( 'Swatch', 'fan-ownership' ); ?></th><th scope="col">Hex</th><th scope="col">RGB</th><th scope="col"><?php esc_html_e( 'Print (CMYK / Pantone)', 'fan-ownership' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $colours as $colour ) : ?>
			<tr>
				<td><?php echo esc_html( $colour[0] ); ?></td>
				<td><span class="chip" style="background: <?php echo esc_attr( $colour[1] ); ?>;"></span></td>
				<td><?php echo esc_html( strtoupper( $colour[1] ) ); ?></td>
				<td><?php echo esc_html( self::hex_to_rgb( $colour[1] ) ); ?></td>
				<td><?php echo esc_html( $colour[2] ? $colour[2] : __( '— supply before print use', 'fan-ownership' ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2 style="margin-top:2.5rem;"><?php esc_html_e( 'Typography', 'fan-ownership' ); ?></h2>
		<?php if ( $brand['font_name'] ) : ?>
		<p><strong><?php echo esc_html( $brand['font_name'] ); ?></strong> — <?php esc_html_e( 'primary brand typeface', 'fan-ownership' ); ?><?php echo $brand['font_file'] ? ' (<a href="' . esc_url( $brand['font_file'] ) . '">' . esc_html__( 'download font file', 'fan-ownership' ) . '</a>)' : ''; ?></p>
		<p class="specimen">ABCDEFGHIJKLMNOPQRSTUVWXYZ<br>abcdefghijklmnopqrstuvwxyz 0123456789</p>
	<?php endif; ?>
		<?php if ( prx3_setting( 'brand_secondary_font_name', '' ) ) : ?>
		<p><strong><?php echo esc_html( prx3_setting( 'brand_secondary_font_name', '' ) ); ?></strong> — <?php esc_html_e( 'secondary / body typeface', 'fan-ownership' ); ?></p>
	<?php endif; ?>
	<p class="muted"><?php esc_html_e( 'Digital fallback stack: system-ui, sans-serif.', 'fan-ownership' ); ?></p>
</section>

<section class="page">
	<h2><?php esc_html_e( 'Usage', 'fan-ownership' ); ?></h2>
		<?php $notes = prx3_setting( 'brand_usage_notes', '' ); ?>
		<?php if ( $notes ) : ?>
			<?php echo wp_kses_post( wpautop( esc_html( $notes ) ) ); ?>
	<?php else : ?>
		<p class="muted"><?php esc_html_e( 'No usage notes supplied yet. Typical contents: clear space, minimum sizes, backgrounds to avoid, and do/do-not examples.', 'fan-ownership' ); ?></p>
	<?php endif; ?>

		<?php if ( prx3_setting( 'brand_social_handles', '' ) ) : ?>
		<h2 style="margin-top:2.5rem;"><?php esc_html_e( 'Official channels', 'fan-ownership' ); ?></h2>
			<?php echo wp_kses_post( wpautop( esc_html( prx3_setting( 'brand_social_handles', '' ) ) ) ); ?>
	<?php endif; ?>

	<h2 style="margin-top:2.5rem;"><?php esc_html_e( 'Brand queries', 'fan-ownership' ); ?></h2>
	<p><?php echo esc_html( prx3_setting( 'brand_contact', get_option( 'admin_email' ) ) ); ?></p>
	<p class="muted"><?php echo esc_html( sprintf( /* translators: 1: club, 2: date. */ __( 'This pack is issued by %1$s and is current as of %2$s. Assets may not be altered, recoloured, or distorted.', 'fan-ownership' ), $club, date_i18n( get_option( 'date_format' ) ) ) ); ?></p>
</section>
</body>
</html>
		<?php
		exit;
	}
}
