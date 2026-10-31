<?php
/**
 * Ayar ekranının görünümü (RBN Kit → Çerez ve Onay). Yazma işleri Admin sınıfındadır.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

defined( 'ABSPATH' ) || exit;

final class AdminPage {

	private const OPT = Settings::OPTION;

	/** @var array<string,mixed> */
	private static array $s = array();

	/** @var array<string,string> Alan kimliği => hata iletisi. */
	private static array $errs = array();

	/** Sekme adı → görünen ad. @return array<string,string> */
	private static function tab_names(): array {
		return array(
			'genel'       => __( 'General', 'cookiekit' ),
			'gorunum'     => __( 'Appearance', 'cookiekit' ),
			'metinler'    => __( 'Texts', 'cookiekit' ),
			'cerezler'    => __( 'Cookies', 'cookiekit' ),
			'kayitlar'    => __( 'Consent log', 'cookiekit' ),
			'entegrasyon' => __( 'Integration', 'cookiekit' ),
			'yardim'      => __( 'Help', 'cookiekit' ),
		);
	}

	private static function ico( string $id ): string {
		return \AdminKit\Helpers\Icons::icon( $id );
	}

	private static function sprite(): void {
		\AdminKit\Helpers\Icons::sprite();
	}

	/** Koyu ton: WP "Gece yarısı" şeması ya da koyu mod eklentisi (kit `data-tone="koyu"` ile koyulaşır). */
	private static function tone_attr(): string {
		return \AdminKit\Helpers\Icons::tone_attr();
	}

	/** Alan hatası: <p> iletisi + aria bağı için kimlik. */
	private static function err_msg( string $id ): string {
		return isset( self::$errs[ $id ] ) ? \AdminKit\Core\Settings\Form::error( $id, self::$errs[ $id ], self::ico( 'alert' ) ) : '';
	}

	private static function inv( string $id ): string {
		return \AdminKit\Core\Settings\Form::invalid_attr( $id, self::$errs );
	}

	private static function field_class( string $id ): string {
		return \AdminKit\Core\Settings\Form::field_class( $id, self::$errs );
	}

	/** Anahtar satırı (role=switch onay kutusu). */
	private static function sw( string $key, string $title, string $help, string $hid ): string {
		return \AdminKit\Core\Settings\Form::switch(
			array(
				'name'        => self::OPT . '[' . $key . ']',
				'on'          => ! empty( self::$s[ $key ] ),
				'title'       => $title,
				'help'        => $help,
				'help_id'     => $hid,
				'label_class' => 'rbn-switch ck-sw',
				'text_class'  => 'ck-sw__t',
				// Anında anahtar: yol şemada bool/flag ise kendiliğinden işaretlenir (Form::instant).
				'kit'         => Settings::SLUG,
				'path'        => $key,
			)
		);
	}

	/** @return array<string,string> yol => başlık. */
	private static function pages( string $current ): array {
		$out = array();
		foreach ( get_pages( array( 'post_status' => 'publish', 'number' => 200, 'sort_column' => 'post_title' ) ) as $page ) {
			$path = wp_make_link_relative( (string) get_permalink( $page ) );
			if ( '' !== $path ) {
				$out[ $path ] = get_the_title( $page );
			}
		}
		if ( '' !== $current && ! isset( $out[ $current ] ) ) {
			$out[ $current ] = $current;
		}
		return $out;
	}

	private static function page_select( string $id, string $name, string $current ): string {
		$options = array();
		foreach ( self::pages( $current ) as $path => $title ) {
			$options[ $path ] = $title . ' (' . $path . ')';
		}
		return \AdminKit\Core\Settings\Form::select(
			array(
				'class'   => 'rbn-select',
				'id'      => $id,
				'name'    => $name,
				'value'   => $current,
				'empty'   => __( '— Select —', 'cookiekit' ),
				'options' => $options,
			)
		);
	}

	private static function is_wizard(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnız görünüm seçimi, durum değiştirmez.
		if ( isset( $_GET['wizard'] ) ) {
			return true;
		}
		$raw = get_option( self::OPT, array() );
		// Alan hiç yazılmamışsa (0.1.0'dan yükseltme) kurulum zaten yapılmış sayılır.
		if ( is_array( $raw ) && $raw && ! array_key_exists( 'wizard_done', $raw ) ) {
			return false;
		}
		return empty( self::$s['wizard_done'] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have access to this page.', 'cookiekit' ), '', array( 'response' => 403 ) );
		}
		self::$s = Settings::all();
		foreach ( get_settings_errors( self::OPT ) as $e ) {
			if ( 'error' === $e['type'] ) {
				self::$errs[ (string) $e['code'] ] = (string) $e['message'];
			}
		}
		$names = self::tab_names();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnız görünüm seçimi; beyaz liste.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'genel';
		if ( ! isset( $names[ $tab ] ) ) {
			$tab = 'genel';
		}
		$stats  = Log::stats( 30 );
		$total  = Log::count();
		$wizard = self::is_wizard();
		$on     = ! empty( self::$s['enabled'] );
		$cfg    = array(
			'preview'  => wp_nonce_url( admin_url( 'admin-post.php?action=cookiekit_preview' ), 'cookiekit_preview' ),
			'postUrl'  => admin_url( 'admin-post.php' ),
			'nonces'   => array(
				'cookie_remove' => wp_create_nonce( 'cookiekit_cookie_remove' ),
				'purge_log'     => wp_create_nonce( 'cookiekit_purge_log' ),
			),
			'defaults' => array_intersect_key( Settings::defaults(), array_flip( array( 'title', 'desc', 'accept', 'reject', 'prefs', 'save' ) ) ),
			'i18n'     => array(
				'unsaved'       => __( 'There are unsaved changes', 'cookiekit' ),
				'copied'        => __( 'Copied', 'cookiekit' ),
				'copyfail'      => __( 'Selected — copy with Ctrl+C', 'cookiekit' ),
				'copy'          => __( 'Copy', 'cookiekit' ),
				'contrast'      => __( 'Text / background contrast', 'cookiekit' ),
				'aa_ok'         => __( 'Passes AA', 'cookiekit' ),
				'aa_fail'       => __( 'AA needs at least 4.5:1', 'cookiekit' ),
				'nocalc'        => __( 'Contrast could not be calculated: enter the colours in #rrggbb format.', 'cookiekit' ),
				'step'          => __( 'Step', 'cookiekit' ),
				'next'          => __( 'Continue', 'cookiekit' ),
				'panel_on'      => __( 'Show the preferences panel', 'cookiekit' ),
				'panel_off'     => __( 'Show the banner', 'cookiekit' ),
				'sum_ads'       => __( 'Analytics and ad cookies are asked about', 'cookiekit' ),
				'sum_an'        => __( 'Only analytics cookies are asked about', 'cookiekit' ),
				'rm_title'      => __( 'Remove the “%s” cookie from the list?', 'cookiekit' ),
				'rm_desc'       => __( 'The cookie is removed from the preferences panel and the policy table. The cookie itself on your site is not deleted.', 'cookiekit' ),
				'rm_confirm'    => __( 'Remove from list', 'cookiekit' ),
				'purge_title'   => __( 'Delete all consent records?', 'cookiekit' ),
				'purge_desc'    => __( 'This cannot be undone. If you have records you must keep as proof under KVKK, download them as CSV first.', 'cookiekit' ),
				'purge_confirm' => __( 'Delete all records', 'cookiekit' ),
				'cancel'        => __( 'Cancel', 'cookiekit' ),
				'confirm'       => __( 'DELETE', 'cookiekit' ),
			),
		);
		?>
<div class="rbn-wrap" id="ck" role="region" aria-label="<?php echo esc_attr__( 'Cookies and Consent', 'cookiekit' ); ?>"<?php echo self::tone_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-cfg="<?php echo esc_attr( (string) wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) ); ?>">
		<?php
		self::sprite();
		?>
	<?php
		echo \AdminKit\Helpers\Header::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Header::render() kaçışlar.
			array(
				'name'    => 'CookieKit',
				'version' => COOKIEKIT_VERSION,
				'status'  => $on
					? array( 'label' => __( 'Active', 'cookiekit' ), 'tone' => 'ok' )
					: array( 'label' => __( 'Off — the banner is not shown', 'cookiekit' ), 'tone' => 'warn' ),
				'actions' => $wizard ? array() : array( array( 'label' => __( 'Preview the banner', 'cookiekit' ), 'icon' => 'eye', 'data' => array( 'dialog' => 'dlg-prev' ) ) ),
			)
		);
		?>
		<?php
		self::notices();
		if ( $wizard ) {
			self::wizard();
		} else {
			self::stats_block( $stats, $total );
			self::tabs_nav( $names, $tab, $total );
			echo '<div class="ck-panel" data-panel="' . esc_attr( $tab ) . '">';
			call_user_func( array( self::class, 'tab_' . $tab ), $total );
			echo '</div>';
		}
		?>
</div>
		<?php
		self::dialogs( $wizard );
	}

	/** Kaydedildi / hata / eylem bildirimleri. */
	private static function notices(): void {
		if ( self::$errs ) {
			$h = '<div class="rbn-note rbn-note--err" id="ck-err" role="alert" tabindex="-1">' . self::ico( 'alert' ) . '<div><p><strong>'
				. esc_html( sprintf( /* translators: %d: alan sayısı */ _n( 'Some changes were not saved: %d field needs fixing.', 'Some changes were not saved: %d fields need fixing.', count( self::$errs ), 'cookiekit' ), count( self::$errs ) ) )
				. '</strong></p><ul>';
			foreach ( self::$errs as $id => $msg ) {
				$h .= '<li>' . ( 'ck-tab' === $id ? '' : '<a href="#' . esc_attr( $id ) . '">' . esc_html( self::label_for( $id ) ) . '</a>: ' ) . esc_html( $msg ) . '</li>';
			}
			echo $h . '</ul></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private static function label_for( string $id ): string {
		$map = array(
			'f-days' => __( 'Consent validity period', 'cookiekit' ),
			'c-ac'   => __( 'Accent colour', 'cookiekit' ),
			'c-bg'   => __( 'Background colour', 'cookiekit' ),
			'c-fg'   => __( 'Text colour', 'cookiekit' ),
			't-priv' => __( 'Privacy Policy', 'cookiekit' ),
			't-cook' => __( 'Cookie Policy', 'cookiekit' ),
		);
		return $map[ $id ] ?? $id;
	}

	/** @param array{total:int,a:int,r:int,c:int,ok:bool} $st */
	private static function stats_block( array $st, ?int $all ): void {
		$ok    = $st['ok'];
		$t     = $st['total'];
		$pct   = static function ( int $n ) use ( $t ): float {
			return $t > 0 ? round( $n / $t * 100, 1 ) : 0.0;
		};
		$cards = array(
			array( '', __( 'Total decisions', 'cookiekit' ), (string) $t, false, __( 'last 30 days', 'cookiekit' ) ),
			array( 'a', __( 'Accept all', 'cookiekit' ), (string) round( $pct( $st['a'] ) ), true, sprintf( /* translators: %d: karar sayısı */ __( '%d decisions', 'cookiekit' ), $st['a'] ) ),
			array( 'r', __( 'Reject', 'cookiekit' ), (string) round( $pct( $st['r'] ) ), true, sprintf( __( '%d decisions · necessary only', 'cookiekit' ), $st['r'] ) ),
			array( 'c', __( 'Custom choice', 'cookiekit' ), (string) round( $pct( $st['c'] ) ), true, sprintf( __( '%d decisions', 'cookiekit' ), $st['c'] ) ),
		);
		if ( ! $ok ) {
			echo '<div class="rbn-note rbn-note--err" role="alert">' . self::ico( 'alert' ) . '<div><p><strong>' . esc_html__( 'The consent log table could not be read.', 'cookiekit' ) . '</strong> ' . esc_html__( 'The summary numbers cannot be shown; the visitor side is not affected and the banner keeps working.', 'cookiekit' ) . '</p><p><a href="' . esc_url( Admin::page_url( array( 'tab' => 'yardim' ) ) ) . '">' . esc_html__( 'Help → Troubleshooting', 'cookiekit' ) . '</a> · ' . esc_html__( 'To recreate the table, deactivate and reactivate the plugin.', 'cookiekit' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<ul class="rbn-stats" aria-label="' . esc_attr__( 'Summary of the last 30 days', 'cookiekit' ) . '">';
		foreach ( $cards as $i => $c ) {
			echo '<li class="rbn-stat"><p class="rbn-stat__k">' . ( '' !== $c[0] ? '<span class="ck-key ck-key--' . esc_attr( $c[0] ) . '" aria-hidden="true"></span>' : '' ) . esc_html( $c[1] ) . '</p>';
			if ( $ok ) {
				echo '<p class="rbn-stat__v">' . esc_html( $c[2] ) . ( $c[3] ? '<span class="ck-stat__u">%</span>' : '' ) . '</p><p class="rbn-stat__s">' . esc_html( $t > 0 || 0 === $i ? $c[4] : __( 'no decisions yet', 'cookiekit' ) ) . '</p>';
			} else {
				echo '<p class="rbn-stat__v">—</p><p class="rbn-stat__s">' . esc_html( 0 === $i ? __( 'could not be read', 'cookiekit' ) : '' ) . '</p>';
			}
			echo '</li>';
		}
		echo '</ul>';
		if ( $ok && $t > 0 ) {
			$label = sprintf( /* translators: 1-3: yüzde */ __( 'Decision breakdown: %1$s percent accept, %2$s percent reject, %3$s percent custom choice', 'cookiekit' ), round( $pct( $st['a'] ) ), round( $pct( $st['r'] ) ), round( $pct( $st['c'] ) ) );
			echo '<div class="ck-dist" role="img" aria-label="' . esc_attr( $label ) . '"><i style="width:' . esc_attr( (string) $pct( $st['a'] ) ) . '%"></i><i style="width:' . esc_attr( (string) $pct( $st['r'] ) ) . '%"></i><i style="width:' . esc_attr( (string) $pct( $st['c'] ) ) . '%"></i></div>';
		}
	}

	/** @param array<string,string> $names */
	private static function tabs_nav( array $names, string $tab, ?int $total ): void {
		echo '<nav aria-label="' . esc_attr__( 'CookieKit sections', 'cookiekit' ) . '"><ul class="rbn-tabs">';
		foreach ( $names as $id => $name ) {
			$count = '';
			if ( 'cerezler' === $id ) {
				$count = (string) array_sum( array_map( 'count', Cookies::catalog() ) );
			} elseif ( 'kayitlar' === $id ) {
				$count = null === $total ? '!' : (string) $total;
			}
			echo '<li><a class="rbn-tabs__tab" href="' . esc_url( Admin::page_url( array( 'tab' => $id ) ) ) . '"' . ( $id === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $name )
				. ( '' !== $count ? ' <span class="rbn-count">' . esc_html( $count ) . '</span>' : '' ) . '</a></li>';
		}
		echo '</ul></nav>';
	}

	/** Form açılışı: seçenek grubu + gizli sekme alanı. */
	private static function form_open( string $tab ): void {
		echo \AdminKit\Core\Settings\Form::open( Settings::GROUP, array( 'form_field' => self::OPT . '[_tab]', 'form' => $tab ) );
	}

	private static function form_close(): void {
		echo '<div class="rbn-form-actions"><button type="submit" class="rbn-btn rbn-btn--pri">' . esc_html__( 'Save changes', 'cookiekit' ) . '</button><button type="button" class="rbn-btn" data-revert hidden>' . esc_html__( 'Cancel', 'cookiekit' ) . '</button><p class="ck-dirty" data-bar role="status"><span class="rbn-dot" aria-hidden="true"></span><span data-bar-t>' . esc_html__( 'There are unsaved changes', 'cookiekit' ) . '</span></p></div></form>';
	}

	private static function card_open( string $id, string $title, string $desc_html = '' ): void {
		echo '<section class="rbn-card" aria-labelledby="' . esc_attr( $id ) . '"><div class="rbn-card__h"><h2 id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</h2>' . ( '' !== $desc_html ? '<p>' . wp_kses( $desc_html, array( 'strong' => array(), 'code' => array() ) ) . '</p>' : '' ) . '</div>';
	}

	private static function prev_slot( string $id ): void {
		echo '<aside class="ck-split__side" aria-label="' . esc_attr__( 'Live preview', 'cookiekit' ) . '"><div data-prev-slot="' . esc_attr( $id ) . '"></div></aside>';
	}

	// ───────────────────────── GENEL ─────────────────────────
	private static function tab_genel(): void {
		$s = self::$s;
		self::form_open( 'genel' );
		self::card_open( 'g1', __( 'Status', 'cookiekit' ), esc_html__( 'Turn the banner on or off across the whole site.', 'cookiekit' ) );
		echo '<div class="rbn-card__b"><div class="rbn-field"><div class="ck-l"><span>' . esc_html__( 'Cookie banner', 'cookiekit' ) . '</span></div><div class="ck-c">'
			. self::sw( 'enabled', __( 'Show to visitors', 'cookiekit' ), __( 'When off, the banner and the Consent Mode script are removed from the page.', 'cookiekit' ), 'h-en' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</div></div></div></section>';

		self::card_open( 'g2', __( 'Cookie categories', 'cookiekit' ), esc_html__( 'Which categories should visitors be asked about? Turn off a category you do not use.', 'cookiekit' ) );
		echo '<div class="rbn-card__b">'
			. '<div class="rbn-field"><div class="ck-l"><span>' . esc_html__( 'Necessary', 'cookiekit' ) . '</span></div><div class="ck-c">'
			. \AdminKit\Core\Settings\Form::switch(
				array(
					'on'          => true,
					'disabled'    => true,
					'title'       => __( 'Always on', 'cookiekit' ),
					'help'        => __( 'Needed for the site to work and to remember the choice; cannot be turned off.', 'cookiekit' ),
					'help_id'     => 'h-nec',
					'label_class' => 'rbn-switch ck-sw',
					'text_class'  => 'ck-sw__t',
				)
			) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</div></div>'
			. '<div class="rbn-field"><div class="ck-l"><span>' . esc_html__( 'Analytics', 'cookiekit' ) . '</span></div><div class="ck-c">' . self::sw( 'show_analytics', __( 'Ask about analytics cookies', 'cookiekit' ), __( 'For measurement tools such as Google Analytics.', 'cookiekit' ), 'h-an' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<div class="rbn-field"><div class="ck-l"><span>' . esc_html__( 'Advertising', 'cookiekit' ) . '</span></div><div class="ck-c">' . self::sw( 'show_ads', __( 'Ask about advertising cookies', 'cookiekit' ), __( 'For AdSense and Google advertising partners. Turn off if the site has no ads.', 'cookiekit' ), 'h-ad' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</div></section>';

		self::card_open( 'g3', __( 'Consent duration and re-consent', 'cookiekit' ), sprintf( /* translators: %d: revizyon */ esc_html__( 'Current revision: %s. When the revision changes, all visitors are asked for consent again.', 'cookiekit' ), '<strong>' . (int) $s['revision'] . '</strong>' ) );
		echo '<div class="rbn-card__b"><div class="' . esc_attr( self::field_class( 'f-days' ) ) . '"><div class="ck-l"><label for="f-days">' . esc_html__( 'Consent validity period', 'cookiekit' ) . '</label><p class="rbn-help">' . esc_html__( 'Between 30 and 395 days. Default 180.', 'cookiekit' ) . '</p></div>'
			. '<div class="ck-c"><span class="ck-unit">' . \AdminKit\Core\Settings\Form::input(
				array(
					'type'      => 'number',
					'class'     => 'rbn-input rbn-input--s',
					'id'        => 'f-days',
					'name'      => self::OPT . '[days]',
					'value'     => (string) (int) $s['days'],
					'min'       => 30,
					'max'       => 395,
					'inputmode' => 'numeric',
					'invalid'   => isset( self::$errs['f-days'] ),
				)
			) . '<span>' . esc_html__( 'days', 'cookiekit' ) . '</span></span>' . self::err_msg( 'f-days' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<div class="rbn-field"><div class="ck-l"><span>' . esc_html__( 'Re-consent', 'cookiekit' ) . '</span></div><div class="ck-c">'
			. \AdminKit\Core\Settings\Form::switch(
				array(
					'name'        => self::OPT . '[bump_revision]',
					'on'          => false,
					'title'       => __( 'Increase the revision on save', 'cookiekit' ),
					'help'        => __( 'Turn on if you changed the categories or the cookie list. If you use a cache, clear it afterwards.', 'cookiekit' ),
					'help_id'     => 'h-rev',
					'label_class' => 'rbn-switch ck-sw',
					'text_class'  => 'ck-sw__t',
				)
			) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</div></div>'
			. '</div></section>';

		self::card_open( 'g4', __( 'Records and data', 'cookiekit' ), esc_html__( 'Keep consent decisions as proof (KVKK). The raw IP is not stored; records are deleted automatically after 12 months.', 'cookiekit' ) );
		echo '<div class="rbn-card__b"><div class="rbn-field"><div class="ck-l"><span>' . esc_html__( 'Consent logging', 'cookiekit' ) . '</span></div><div class="ck-c">' . self::sw( 'log', __( 'Record consent decisions', 'cookiekit' ), __( 'The decision, category, page and an IP hashed with a daily-rotating salt are stored.', 'cookiekit' ), 'h-log' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<div class="rbn-field"><div class="ck-l"><span>' . esc_html__( 'When the plugin is deleted', 'cookiekit' ) . '</span></div><div class="ck-c">' . self::sw( 'delete_on_uninstall', __( 'Also delete the settings and the consent log', 'cookiekit' ), __( 'When on, deleting the plugin irreversibly removes the settings, custom cookies and the log table.', 'cookiekit' ), 'h-del' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</div></section>';
		self::form_close();
	}

	// ───────────────────────── GÖRÜNÜM ─────────────────────────
	private static function tab_gorunum(): void {
		$s   = self::$s;
		$pos = array(
			'bottom-left'   => array( 'bl', __( 'Bottom left', 'cookiekit' ), '' ),
			'bottom-center' => array( 'bc', __( 'Bottom centre', 'cookiekit' ), __( 'Full width', 'cookiekit' ) ),
			'bottom-right'  => array( 'br', __( 'Bottom right', 'cookiekit' ), '' ),
			'box'           => array( 'box', __( 'Box in the centre', 'cookiekit' ), '' ),
		);
		$own = '' !== (string) $s['color_accent'] || '' !== (string) $s['color_bg'] || '' !== (string) $s['color_text'] || isset( self::$errs['c-ac'] ) || isset( self::$errs['c-bg'] ) || isset( self::$errs['c-fg'] );
		self::form_open( 'gorunum' );
		echo '<div class="ck-split"><div>';
		self::card_open( 'v1', __( 'Position', 'cookiekit' ), esc_html__( 'Where on the screen should the banner appear? The preview updates instantly.', 'cookiekit' ) );
		echo '<div class="rbn-card__b"><fieldset class="ck-opts"><legend class="rbn-sr">' . esc_html__( 'Banner position', 'cookiekit' ) . '</legend>';
		foreach ( $pos as $val => $p ) {
			echo '<label class="ck-opt"><input type="radio" name="' . esc_attr( self::OPT . '[position]' ) . '" value="' . esc_attr( $val ) . '"' . checked( $s['position'], $val, false ) . '><span class="ck-opt__b"><span class="ck-pos ck-pos--' . esc_attr( $p[0] ) . '" aria-hidden="true"><i></i></span><span class="ck-opt__t">' . esc_html( $p[1] ) . '</span>' . ( '' !== $p[2] ? '<span class="ck-opt__d">' . esc_html( $p[2] ) . '</span>' : '' ) . '</span><span class="ck-opt__tick">' . self::ico( 'check' ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</fieldset><p class="rbn-help ck-hint">' . esc_html__( 'On phones (below 640 px) the banner is always full width at the bottom.', 'cookiekit' ) . '</p></div></section>';

		self::card_open( 'v2', __( 'Colours', 'cookiekit' ), esc_html__( 'Theme colours are used by default so the banner stays consistent with your theme.', 'cookiekit' ) );
		echo '<div class="rbn-card__b"><div class="rbn-field"><div class="ck-l"><span id="l-tema">' . esc_html__( 'Colour source', 'cookiekit' ) . '</span></div><div class="ck-c"><div class="rbn-seg" role="group" aria-labelledby="l-tema" data-src>'
			. '<button type="button" data-src-v="tema" aria-pressed="' . ( $own ? 'false' : 'true' ) . '">' . esc_html__( 'Theme colours', 'cookiekit' ) . '</button>'
			. '<button type="button" data-src-v="ozel" aria-pressed="' . ( $own ? 'true' : 'false' ) . '">' . esc_html__( 'Custom colours', 'cookiekit' ) . '</button></div>'
			. '<p class="rbn-help">' . esc_html__( '“Theme colours”: the site\'s --fm-* variables or the light/dark default are used (the three colour fields stay empty).', 'cookiekit' ) . '</p></div></div>';
		echo '<div class="rbn-field" id="colors"' . ( $own ? '' : ' hidden' ) . '><div class="ck-l"><span>' . esc_html__( 'Custom colours', 'cookiekit' ) . '</span><p class="rbn-help">' . esc_html__( 'The contrast between text and background must be at least 4.5:1.', 'cookiekit' ) . '</p></div><div class="ck-c"><div class="ck-colors">';
		foreach ( array(
			array( 'c-ac', 'color_accent', __( 'Accent', 'cookiekit' ), '#0b5fff' ),
			array( 'c-bg', 'color_bg', __( 'Background', 'cookiekit' ), '#ffffff' ),
			array( 'c-fg', 'color_text', __( 'Text', 'cookiekit' ), '#1a1a1a' ),
		) as $c ) {
			$v   = (string) $s[ $c[1] ];
			$pic = sanitize_hex_color( $v ) ? $v : $c[3];
			echo '<div class="' . esc_attr( self::field_class( $c[0] ) ) . ' ck-color-i"><label for="' . esc_attr( $c[0] ) . '">' . esc_html( $c[2] ) . '</label><div class="ck-color">'
				. '<input type="color" value="' . esc_attr( $pic ) . '" data-pair="' . esc_attr( $c[0] ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: renk adı */ __( '%s colour picker', 'cookiekit' ), $c[2] ) ) . '">'
				. \AdminKit\Core\Settings\Form::input(
					array(
						'type'        => 'text',
						'class'       => 'rbn-input rbn-input--mono',
						'id'          => $c[0],
						'name'        => self::OPT . '[' . $c[1] . ']',
						'value'       => $v,
						'placeholder' => $c[3],
						'maxlength'   => 7,
						'invalid'     => isset( self::$errs[ $c[0] ] ),
						'attrs'       => array( 'data-hex' => true ),
					)
				) . '</div>' . self::err_msg( $c[0] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><div class="rbn-note rbn-note--ok" id="contrast" role="status">' . self::ico( 'check' ) . '<div><p>' . esc_html__( 'Text / background contrast: —', 'cookiekit' ) . '</p></div></div></div></div></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		self::prev_slot( 'gorunum' );
		echo '</div>';
		self::form_close();
	}

	// ───────────────────────── METİNLER ─────────────────────────
	private static function tab_metinler(): void {
		$s = self::$s;
		self::form_open( 'metinler' );
		echo '<div class="ck-split"><div>';
		self::card_open( 'm1', __( 'Banner text', 'cookiekit' ), esc_html__( 'Write short and clear: visitors should understand what is asked at a glance.', 'cookiekit' ) );
		echo '<div class="rbn-card__b"><div class="rbn-field"><div class="ck-l"><label for="t-title">' . esc_html__( 'Title', 'cookiekit' ) . '</label><p class="rbn-help">' . esc_html__( 'At most 80 characters.', 'cookiekit' ) . '</p></div><div class="ck-c">'
			. \AdminKit\Core\Settings\Form::input(
				array(
					'type'      => 'text',
					'class'     => 'rbn-input',
					'id'        => 't-title',
					'name'      => self::OPT . '[title]',
					'value'     => (string) $s['title'],
					'maxlength' => 80,
					'attrs'     => array( 'data-count' => '80' ),
				)
			) . '<p class="ck-counter" aria-hidden="true"></p></div></div>'
			. '<div class="rbn-field"><div class="ck-l"><label for="t-desc">' . esc_html__( 'Description', 'cookiekit' ) . '</label><p class="rbn-help">' . esc_html__( 'At most 500 characters. Say which cookies are used and why.', 'cookiekit' ) . '</p></div><div class="ck-c">'
			. \AdminKit\Core\Settings\Form::textarea(
				array(
					'class'     => 'rbn-textarea',
					'id'        => 't-desc',
					'name'      => self::OPT . '[desc]',
					'value'     => (string) $s['desc'],
					'rows'      => 4,
					'maxlength' => 500,
					'attrs'     => array( 'data-count' => '500' ),
				)
			) . '<p class="ck-counter" aria-hidden="true"></p></div></div></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		self::card_open( 'm2', __( 'Button labels', 'cookiekit' ), esc_html__( 'Accept and Reject keep equal visibility; only their text changes.', 'cookiekit' ) );
		echo '<div class="rbn-card__b">';
		foreach ( array(
			array( 't-accept', 'accept', __( 'Accept button', 'cookiekit' ), __( 'At most 30 characters.', 'cookiekit' ), 30 ),
			array( 't-reject', 'reject', __( 'Reject button', 'cookiekit' ), __( 'At most 30 characters.', 'cookiekit' ), 30 ),
			array( 't-prefs', 'prefs', __( 'Preferences button', 'cookiekit' ), __( 'Opens the category panel. At most 30 characters.', 'cookiekit' ), 30 ),
			array( 't-save', 'save', __( 'Save choice button', 'cookiekit' ), __( 'The button at the bottom of the panel. At most 40 characters.', 'cookiekit' ), 40 ),
		) as $f ) {
			echo '<div class="rbn-field"><div class="ck-l"><label for="' . esc_attr( $f[0] ) . '">' . esc_html( $f[2] ) . '</label><p class="rbn-help">' . esc_html( $f[3] ) . '</p></div><div class="ck-c">'
				. \AdminKit\Core\Settings\Form::input(
					array(
						'type'      => 'text',
						'class'     => 'rbn-input',
						'id'        => $f[0],
						'name'      => self::OPT . '[' . $f[1] . ']',
						'value'     => (string) $s[ $f[1] ],
						'maxlength' => (int) $f[4],
					)
				) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></section>';

		self::card_open( 'm3', __( 'Policy links', 'cookiekit' ), esc_html__( 'The banner and the preferences panel link to these pages. If left empty, no link is shown.', 'cookiekit' ) );
		echo '<div class="rbn-card__b">';
		foreach ( array(
			array( 't-priv', 'privacy_url', __( 'Privacy Policy', 'cookiekit' ), __( 'A path on this site (/privacy-policy/) or the full address of this site.', 'cookiekit' ) ),
			array( 't-cook', 'cookie_url', __( 'Cookie Policy', 'cookiekit' ), __( 'The page that uses [cookiekit_cerez_listesi] for the cookie table.', 'cookiekit' ) ),
		) as $f ) {
			$v = (string) $s[ $f[1] ];
			echo '<div class="' . esc_attr( self::field_class( $f[0] ) ) . '"><div class="ck-l"><label for="' . esc_attr( $f[0] ) . '">' . esc_html( $f[2] ) . '</label><p class="rbn-help">' . esc_html( $f[3] ) . '</p></div><div class="ck-c">'
				. '<input class="rbn-input" id="' . esc_attr( $f[0] ) . '" name="' . esc_attr( self::OPT . '[' . $f[1] . ']' ) . '" value="' . esc_attr( $v ) . '" maxlength="300"' . self::inv( $f[0] ) . '>' . self::err_msg( $f[0] )
				. '<p class="rbn-help"><label for="' . esc_attr( $f[0] ) . 'p" class="rbn-sr">' . esc_html__( 'Pick from the page list', 'cookiekit' ) . '</label>'
				. str_replace( '<option value="">' . esc_html__( '— Select —', 'cookiekit' ) . '</option>', '<option value="">' . esc_html__( 'Pick from the page list…', 'cookiekit' ) . '</option>', str_replace( 'class="rbn-select"', 'class="rbn-select ck-pick" data-fill="' . esc_attr( $f[0] ) . '"', self::page_select( $f[0] . 'p', '', '' ) ) )
				. '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></section>';
		echo '<p><button type="button" class="rbn-btn rbn-btn--sm" data-dialog="dlg-reset">' . self::ico( 'rot' ) . esc_html__( 'Reset texts to default', 'cookiekit' ) . '</button></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		self::prev_slot( 'metinler' );
		echo '</div>';
		self::form_close();
	}

	// ───────────────────────── ÇEREZLER ─────────────────────────
	private static function tab_cerezler(): void {
		$cats   = array();
		foreach ( Cookies::categories( true ) as $c ) {
			$cats[ $c['id'] ] = $c['name'];
		}
		$custom = Cookies::custom();
		$by_cat = array();
		foreach ( $custom as $row ) {
			$by_cat[ $row['cat'] ][] = $row;
		}
		self::card_open( 'c1', __( 'Cookie list', 'cookiekit' ), '' );
		echo '<div class="rbn-card__b ck-row"><p>' . esc_html__( 'Shown in the preferences panel and in the [cookiekit_cerez_listesi] shortcode. List only the cookies that are really set on your site.', 'cookiekit' ) . '</p><button type="button" class="rbn-btn rbn-btn--pri" data-dialog="dlg-cookie">' . self::ico( 'plus' ) . esc_html__( 'Add cookie', 'cookiekit' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="rbn-tw" role="region" aria-label="' . esc_attr__( 'Cookie table', 'cookiekit' ) . '" tabindex="0"><table class="rbn-table ck-table-min"><caption class="rbn-sr">' . esc_html__( 'Cookies by category', 'cookiekit' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Cookie', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Provider', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Purpose', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Duration', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Source', 'cookiekit' ) . '</th><th scope="col"><span class="rbn-sr">' . esc_html__( 'Action', 'cookiekit' ) . '</span></th></tr></thead><tbody>';
		foreach ( Cookies::builtin() as $cat => $rows ) {
			echo '<tr class="ck-table__grp"><th colspan="6" scope="colgroup">' . esc_html( $cats[ $cat ] ?? $cat ) . '</th></tr>';
			foreach ( $rows as $r ) {
				echo '<tr><th scope="row"><code>' . esc_html( $r[0] ) . '</code></th><td>' . esc_html( $r[1] ) . '</td><td>' . esc_html( $r[2] ) . '</td><td>' . esc_html( $r[3] ) . '</td><td><span class="rbn-chip">' . esc_html__( 'Built-in', 'cookiekit' ) . '</span></td><td></td></tr>';
			}
			foreach ( $by_cat[ $cat ] ?? array() as $r ) {
				echo '<tr><th scope="row"><code>' . esc_html( $r['name'] ) . '</code></th><td>' . esc_html( $r['provider'] ) . '</td><td>' . esc_html( $r['purpose'] ) . '</td><td>' . esc_html( $r['duration'] ) . '</td><td><span class="rbn-chip rbn-chip--ok">' . esc_html__( 'Custom', 'cookiekit' ) . '</span></td><td class="rbn-num"><button type="button" class="rbn-btn rbn-btn--sm rbn-btn--danger" data-rm="' . esc_attr( $r['id'] ) . '" data-rm-name="' . esc_attr( $r['name'] ) . '">' . self::ico( 'trash' ) . esc_html__( 'Remove', 'cookiekit' ) . '<span class="rbn-sr"> ' . esc_html( $r['name'] ) . '</span></button></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '</tbody></table></div>';
		if ( ! $custom ) {
			echo '<div class="rbn-empty"><span class="rbn-empty__i">' . self::ico( 'list' ) . '</span><h3>' . esc_html__( 'No custom cookies added', 'cookiekit' ) . '</h3><p>' . esc_html__( 'The built-in list above covers Google and WordPress cookies. If your site sets its own cookie, add it; the visitor panel and the policy page update automatically.', 'cookiekit' ) . '</p><button type="button" class="rbn-btn" data-dialog="dlg-cookie">' . esc_html__( 'Add cookie', 'cookiekit' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<p class="rbn-card__b rbn-help ck-hint">' . esc_html__( 'Built-in rows are only cookies documented in the Google cookie reference; they cannot be changed. If you add a new cookie, consider turning on General → “Re-consent”.', 'cookiekit' ) . '</p></section>';
	}

	// ───────────────────────── ONAY KAYITLARI ─────────────────────────
	private static function tab_kayitlar( ?int $total ): void {
		$per = 20;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnız sayfa numarası.
		$page = isset( $_GET['kp'] ) ? max( 1, absint( wp_unslash( $_GET['kp'] ) ) ) : 1;
		if ( null !== $total && $total > 0 ) {
			$page = min( $page, (int) ceil( $total / $per ) );
		}
		if ( empty( self::$s['log'] ) ) {
			echo '<div class="rbn-note rbn-note--warn">' . self::ico( 'alert' ) . '<div><p><strong>' . esc_html__( 'The consent log is off.', 'cookiekit' ) . '</strong> ' . esc_html__( 'New decisions are not stored; to keep proof (KVKK), turn it on under', 'cookiekit' ) . ' <a href="' . esc_url( Admin::page_url( array( 'tab' => 'genel' ) ) ) . '">' . esc_html__( 'General → Records and data', 'cookiekit' ) . '</a> ' . esc_html__( 'section.', 'cookiekit' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		self::card_open( 'k1', __( 'Consent decisions', 'cookiekit' ), '' );
		$csv = wp_nonce_url( admin_url( 'admin-post.php?action=cookiekit_csv' ), 'cookiekit_csv' );
		echo '<div class="rbn-card__b ck-row"><p>' . esc_html__( 'Records are deleted automatically after 12 months. The raw IP is not stored; only a hash with a daily-rotating salt is kept.', 'cookiekit' ) . '</p><div class="ck-row__act"><a class="rbn-btn" href="' . esc_url( $csv ) . '">' . self::ico( 'down' ) . esc_html__( 'Export CSV', 'cookiekit' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $total ) {
			echo '<button type="button" class="rbn-btn rbn-btn--danger" id="btn-purge-records">' . self::ico( 'trash' ) . esc_html__( 'Delete records', 'cookiekit' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></div>';
		if ( null === $total ) {
			echo '<div class="rbn-empty"><span class="rbn-empty__i">' . self::ico( 'chart' ) . '</span><h3>' . esc_html__( 'Records cannot be shown', 'cookiekit' ) . '</h3><p>' . esc_html__( 'The consent log table could not be read. The visitor side is not affected; writing records may also have paused.', 'cookiekit' ) . '</p></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		if ( 0 === $total ) {
			echo '<div class="rbn-empty"><span class="rbn-empty__i">' . self::ico( 'chart' ) . '</span><h3>' . esc_html__( 'No consent decisions yet', 'cookiekit' ) . '</h3><p>' . esc_html__( 'It appears here when a visitor makes a choice on the banner. If you just installed it, open the site in a logged-out browser and try the banner.', 'cookiekit' ) . '</p><a class="rbn-btn" href="' . esc_url( Admin::page_url( array( 'tab' => 'gorunum' ) ) ) . '">' . esc_html__( 'Preview the banner', 'cookiekit' ) . '</a></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$act = array(
			'a' => array( __( 'Accept all', 'cookiekit' ), ' rbn-chip--ok' ),
			'r' => array( __( 'Reject', 'cookiekit' ), '' ),
			'c' => array( __( 'Custom choice', 'cookiekit' ), '' ),
		);
		echo '<div class="rbn-tw" role="region" aria-label="' . esc_attr__( 'Consent log table', 'cookiekit' ) . '" tabindex="0"><table class="rbn-table ck-table-min"><caption class="rbn-sr">' . esc_html__( 'Latest consent decisions', 'cookiekit' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Time', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Decision', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Allowed', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Rev.', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Page', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'IP hash', 'cookiekit' ) . '</th></tr></thead><tbody>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( Log::recent( $per, ( $page - 1 ) * $per ) as $r ) {
			$mask  = (int) $r['categories'];
			$allow = array( __( 'Necessary', 'cookiekit' ) );
			if ( $mask & Settings::BIT_ANALYTICS ) {
				$allow[] = __( 'Analytics', 'cookiekit' );
			}
			if ( $mask & Settings::BIT_ADS ) {
				$allow[] = __( 'Advertising', 'cookiekit' );
			}
			$a = $act[ $r['action'] ] ?? $act['c'];
			echo '<tr><td>' . esc_html( get_date_from_gmt( (string) $r['created_at'], 'd.m.Y H:i' ) ) . '</td><td><span class="rbn-chip' . esc_attr( $a[1] ) . '">' . esc_html( $a[0] ) . '</span></td><td>' . esc_html( implode( ', ', $allow ) ) . '</td><td>' . (int) $r['revision'] . '</td><td><code>' . esc_html( (string) $r['path'] ) . '</code></td><td><code>' . esc_html( substr( (string) $r['ip_hash'], 0, 8 ) ) . '</code></td></tr>';
		}
		echo '</tbody></table></div>';
		$pages = (int) ceil( $total / $per );
		$from  = ( $page - 1 ) * $per + 1;
		$to    = min( $total, $page * $per );
		echo '<nav class="rbn-pager" aria-label="' . esc_attr__( 'Pagination', 'cookiekit' ) . '"><p class="rbn-pager__n">' . esc_html( sprintf( /* translators: 1: toplam 2: başlangıç 3: bitiş */ __( 'Showing %2$d–%3$d of %1$d records', 'cookiekit' ), $total, $from, $to ) ) . '</p>';
		echo $page > 1 ? '<a class="rbn-btn rbn-btn--sm" href="' . esc_url( Admin::page_url( array( 'tab' => 'kayitlar', 'kp' => (string) ( $page - 1 ) ) ) ) . '">' . esc_html__( 'Previous', 'cookiekit' ) . '</a>' : '<span class="rbn-btn rbn-btn--sm" aria-disabled="true">' . esc_html__( 'Previous', 'cookiekit' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $page < $pages ? '<a class="rbn-btn rbn-btn--sm" href="' . esc_url( Admin::page_url( array( 'tab' => 'kayitlar', 'kp' => (string) ( $page + 1 ) ) ) ) . '">' . esc_html__( 'Next', 'cookiekit' ) . '</a>' : '<span class="rbn-btn rbn-btn--sm" aria-disabled="true">' . esc_html__( 'Next', 'cookiekit' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</nav></section>';
	}

	// ───────────────────────── ENTEGRASYON ─────────────────────────
	private static function status_row( bool $ok, string $title, string $small_html, string $chip ): string {
		return '<li><span class="ck-status__i ' . ( $ok ? 'ck-status__i--ok' : 'ck-status__i--no' ) . '">' . self::ico( $ok ? 'check' : 'info' ) . '</span><span><b>' . esc_html( $title ) . '</b><small>' . wp_kses( $small_html, array( 'code' => array() ) ) . '</small></span><span class="rbn-chip' . ( $ok ? ' rbn-chip--ok' : ' rbn-chip--warn' ) . '">' . esc_html( $chip ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function tab_entegrasyon(): void {
		$on  = ! empty( self::$s['enabled'] );
		$chp = $on ? __( 'Active', 'cookiekit' ) : __( 'Off', 'cookiekit' );
		self::card_open( 'e1', __( 'Google Consent Mode v2', 'cookiekit' ), esc_html__( 'No setting needed; the plugin runs automatically at the start of the page. The status is for information only.', 'cookiekit' ) );
		echo '<div class="rbn-card__b"><ul class="ck-status">'
			. self::status_row( $on, __( 'Default: denied', 'cookiekit' ), '<code>ad_storage</code>, <code>analytics_storage</code>, <code>ad_user_data</code>, <code>ad_personalization</code> — ' . esc_html__( 'at the start of the page, with a 500 ms update wait', 'cookiekit' ), $chp ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::status_row( $on, __( 'Update on choice', 'cookiekit' ), esc_html__( 'When the visitor decides,', 'cookiekit' ) . ' <code>consent update</code> ' . esc_html__( 'is sent; it is applied instantly for a returning visitor.', 'cookiekit' ), $chp ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::status_row( $on, __( 'dataLayer event', 'cookiekit' ), '<code>cookiekit_consent_update</code> ' . esc_html__( 'event can be used as a Google Tag Manager trigger.', 'cookiekit' ), $chp ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</ul></div></section>';

		$sitekit = defined( 'GOOGLESITEKIT_VERSION' );
		$monster = class_exists( 'MonsterInsights' ) || function_exists( 'MonsterInsights' );
		self::card_open( 'e2', __( 'Detected plugins', 'cookiekit' ), esc_html__( 'Checked so that two plugins do not load the same tag.', 'cookiekit' ) );
		echo '<div class="rbn-card__b"><ul class="ck-status">'
			. self::status_row( $sitekit, __( 'Google Site Kit', 'cookiekit' ), $sitekit ? esc_html__( 'Detected. Analytics and AdSense tags are restricted by Consent Mode until consent is given.', 'cookiekit' ) : esc_html__( 'Not active.', 'cookiekit' ), $sitekit ? __( 'Detected', 'cookiekit' ) : __( 'Not found', 'cookiekit' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::status_row( $monster, __( 'MonsterInsights', 'cookiekit' ), $monster ? esc_html__( 'Detected. If the Analytics tag is not tied to consent, load the script with cookiekit_script().', 'cookiekit' ) : esc_html__( 'Not active.', 'cookiekit' ), $monster ? __( 'Detected', 'cookiekit' ) : __( 'Not found', 'cookiekit' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::status_row( ! empty( self::$s['show_ads'] ), __( 'Advertising category', 'cookiekit' ), ! empty( self::$s['show_ads'] ) ? esc_html__( 'On. Load your ad code with', 'cookiekit' ) . ' <code>cookiekit_script()</code> ' . esc_html__( '(below).', 'cookiekit' ) : esc_html__( 'Off: the advertising category is not asked about on the banner.', 'cookiekit' ), ! empty( self::$s['show_ads'] ) ? __( 'On', 'cookiekit' ) : __( 'Off', 'cookiekit' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</ul></div></section>';

		self::card_open( 'e3', __( 'Shortcodes and code snippets', 'cookiekit' ), esc_html__( 'Copy and paste into your page, theme or button.', 'cookiekit' ) );
		$snips = array(
			array( 's1', __( 'Preferences button (shortcode)', 'cookiekit' ), '[cookiekit_tercihler label="' . esc_attr__( 'Manage cookie preferences', 'cookiekit' ) . '"]' ),
			array( 's2', __( 'Cookie table (for the Cookie Policy page)', 'cookiekit' ), '[cookiekit_cerez_listesi]' ),
			array( 's3', __( 'Open the panel from any element', 'cookiekit' ), '<a href="#" data-cookiekit-open>' . __( 'Cookie settings', 'cookiekit' ) . '</a>' ),
			array( 's4', __( 'Tie a script to consent (PHP)', 'cookiekit' ), "<?php echo cookiekit_script( 'ads', '" . _x( 'https://example.com/ads.js', 'Example script URL', 'cookiekit' ) . "' ); ?>" ),
			array( 's5', 'JavaScript', "Cookiekit.open();            // " . __( 'open the panel', 'cookiekit' ) . "\nCookiekit.consent();         // " . __( 'mask: null | 1 | 3 | 5 | 7', 'cookiekit' ) . "\ndocument.addEventListener('cookiekit:consent', e => e.detail.mask);" ),
		);
		echo '<div class="rbn-card__b">';
		foreach ( $snips as $i => $sn ) {
			echo '<p class="ck-snip-l">' . esc_html( $sn[1] ) . '</p><div class="ck-snip"><pre id="' . esc_attr( $sn[0] ) . '">' . esc_html( $sn[2] ) . '</pre><button type="button" class="rbn-btn" data-copy="' . esc_attr( $sn[0] ) . '">' . self::ico( 'copy' ) . '<span>' . esc_html__( 'Copy', 'cookiekit' ) . '</span></button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<p class="rbn-sr" id="copy-live" role="status" aria-live="polite"></p></div></section>';
	}

	// ───────────────────────── YARDIM ─────────────────────────
	/** readme.txt "Changelog" bölümü: sürüm => madde listesi. @return array<string,array<int,string>> */
	private static function changelog(): array {
		$txt = is_readable( COOKIEKIT_DIR . 'readme.txt' ) ? (string) file_get_contents( COOKIEKIT_DIR . 'readme.txt' ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- yerel dosya.
		$pos = strpos( $txt, '== Changelog ==' );
		if ( false === $pos ) {
			return array();
		}
		$out = array();
		$ver = '';
		foreach ( preg_split( '/\R/', substr( $txt, $pos + 15 ) ) ?: array() as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^=\s*([0-9][^=]*?)\s*=$/', $line, $m ) ) {
				$ver         = $m[1];
				$out[ $ver ] = array();
			} elseif ( '' !== $ver && 0 === strpos( $line, '* ' ) ) {
				// Release notes are written in English in readme.txt; the translations live in the .po file.
				$out[ $ver ][] = _x( substr( $line, 2 ), 'Release note', 'cookiekit' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			} elseif ( 0 === strpos( $line, '==' ) ) {
				break;
			}
		}
		return $out;
	}

	private static function tab_yardim(): void {
		self::card_open( 'y1', __( 'Quick start', 'cookiekit' ), esc_html__( 'The right setup in 2 minutes after installation:', 'cookiekit' ) );
		echo '<div class="rbn-card__b"><ol class="ck-steps"><li><b>' . esc_html__( 'Choose the categories', 'cookiekit' ) . '</b><p>' . esc_html__( 'General → leave on only the categories you use (turn off advertising if there are no ads).', 'cookiekit' ) . '</p></li><li><b>' . esc_html__( 'Link the pages', 'cookiekit' ) . '</b><p>' . esc_html__( 'Texts → choose your Privacy Policy and Cookie Policy pages.', 'cookiekit' ) . '</p></li><li><b>' . esc_html__( 'Preview, then activate', 'cookiekit' ) . '</b><p>' . esc_html__( 'Appearance → fit the banner to its surroundings; General → turn on “Show to visitors”.', 'cookiekit' ) . '</p></li></ol><p class="ck-hint"><a class="rbn-btn" href="' . esc_url( Admin::page_url( array( 'wizard' => '1' ) ) ) . '">' . esc_html__( 'Reopen the setup wizard', 'cookiekit' ) . '</a></p></div></section>';

		self::card_open( 'y2', __( 'Frequently asked questions', 'cookiekit' ), '' );
		$faq = array(
			array( __( 'The banner is not showing; what should I do?', 'cookiekit' ), __( 'Clear your cache plugin and open the site in a logged-out (private window) browser. If you decided earlier, the banner will not appear again: raise the revision with General → Re-consent.', 'cookiekit' ) ),
			array( __( 'What happens to Google tags if the visitor chooses “Reject”?', 'cookiekit' ), __( 'The Consent Mode v2 default stays “denied”; Analytics and advertising cookies are not set.', 'cookiekit' ) ),
			array( __( 'Is personal data stored in the consent log?', 'cookiekit' ), __( 'The raw IP and full browser information are not stored; only a hash with a daily-rotating salt, the decision and the page path are kept. Records are deleted after 12 months.', 'cookiekit' ) ),
			array( __( 'How do I add my own cookie?', 'cookiekit' ), __( 'Cookies tab → “Add cookie”. The cookie you add appears in the preferences panel and in the table on the policy page.', 'cookiekit' ) ),
			array( __( 'Does it conflict with another plugin?', 'cookiekit' ), __( 'If two plugins load the same tag, check the detection status in the Integration tab.', 'cookiekit' ) ),
			array( __( 'Troubleshooting: the summary cards say “could not be read”', 'cookiekit' ), __( 'It means the consent log table cannot be read. The visitor side is not affected. Deactivating and reactivating the plugin recreates the table; existing records are not deleted.', 'cookiekit' ) ),
		);
		echo '<div class="rbn-card__b ck-faq">';
		foreach ( $faq as $q ) {
			echo '<details><summary>' . esc_html( $q[0] ) . '</summary><p>' . esc_html( $q[1] ) . '</p></details>';
		}
		echo '</div></section>';

		$hdr = get_file_data( COOKIEKIT_FILE, array( 'wp' => 'Requires at least', 'php' => 'Requires PHP', 'lic' => 'License' ) );
		self::card_open( 'y3', __( 'Version and source', 'cookiekit' ), '' );
		echo '<div class="rbn-card__b"><dl class="ck-dl"><dt>' . esc_html__( 'Plugin', 'cookiekit' ) . '</dt><dd>CookieKit</dd><dt>' . esc_html__( 'Version', 'cookiekit' ) . '</dt><dd>' . esc_html( COOKIEKIT_VERSION ) . '</dd><dt>' . esc_html__( 'Requirements', 'cookiekit' ) . '</dt><dd>WordPress ' . esc_html( $hdr['wp'] ) . ' · PHP ' . esc_html( $hdr['php'] ) . '</dd><dt>' . esc_html__( 'License', 'cookiekit' ) . '</dt><dd>' . esc_html( $hdr['lic'] ) . '</dd><dt>' . esc_html__( 'Source', 'cookiekit' ) . '</dt><dd><a href="https://github.com/RbnCore" rel="noopener" target="_blank">github.com/RbnCore<span class="rbn-sr"> ' . esc_html__( '(opens in a new tab)', 'cookiekit' ) . '</span></a></dd></dl>';
		echo '<h3 class="ck-h3">' . esc_html__( 'Release notes', 'cookiekit' ) . '</h3><ul class="ck-log">';
		foreach ( self::changelog() as $ver => $items ) {
			echo '<li><b>' . esc_html( $ver ) . '</b> — ' . esc_html( implode( ' ', $items ) ) . '</li>';
		}
		echo '</ul></div></section>';

		self::card_open( 'y4', __( 'Updates', 'cookiekit' ), esc_html__( 'New versions come from the official releases on GitHub.', 'cookiekit' ) );
		echo '<div class="rbn-card__b">' . \AdminKit\Kit\UpdateCard::render( 'cookiekit', array( 'return_url' => Admin::page_url( array( 'tab' => 'yardim' ) ) ) ) . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UpdateCard kaçışlı çıktı verir.
	}

	// ───────────────────────── SİHİRBAZ ─────────────────────────
	private static function wizard(): void {
		$s    = self::$s;
		$skip = wp_nonce_url( admin_url( 'admin-post.php?action=cookiekit_wizard_skip' ), 'cookiekit_wizard_skip' );
		$ads  = ! empty( $s['show_ads'] ) || empty( $s['wizard_done'] );
		echo '<section id="wizard" aria-labelledby="wiz-h"><div class="ck-wiz"><div class="ck-wiz__top"><p id="wiz-n">' . esc_html__( 'Setup', 'cookiekit' ) . '</p><a class="rbn-btn rbn-btn--link" href="' . esc_url( $skip ) . '">' . esc_html__( 'Skip for now', 'cookiekit' ) . '</a></div>';
		echo '<ol class="ck-stepper" aria-label="' . esc_attr__( 'Setup steps', 'cookiekit' ) . '"><li data-st="1"><b>1</b><span>' . esc_html__( 'Site type', 'cookiekit' ) . '</span></li><li data-st="2"><b>2</b><span>' . esc_html__( 'Text and links', 'cookiekit' ) . '</span></li><li data-st="3"><b>3</b><span>' . esc_html__( 'Preview and activate', 'cookiekit' ) . '</span></li></ol>';
		echo \AdminKit\Core\Settings\Form::open(
			Settings::GROUP,
			array(
				'class'     => 'rbn-card',
				'id'        => 'wiz-form',
				'savebar'   => false,
				'novalidate' => false,
				'form_field' => self::OPT . '[_tab]',
				'form'      => 'kurulum',
			)
		);
		// 1
		echo '<div data-wstep="1"><div class="rbn-card__h"><h2 id="wiz-h">' . esc_html__( 'Which cookies does your site use?', 'cookiekit' ) . '</h2><p>' . esc_html__( 'Show only the categories you really use; an unused category is an unnecessary question for visitors.', 'cookiekit' ) . '</p></div><div class="rbn-card__b"><fieldset class="ck-opts ck-opts--wide"><legend class="rbn-sr">' . esc_html__( 'Site type', 'cookiekit' ) . '</legend>';
		foreach ( array(
			array( 'reklam', __( 'Analytics + Advertising', 'cookiekit' ), __( 'I use Google Analytics and AdSense (or other Google advertising).', 'cookiekit' ), $ads ),
			array( 'analiz', __( 'Analytics only', 'cookiekit' ), __( 'I only measure visitors and show no ads.', 'cookiekit' ), ! $ads ),
		) as $o ) {
			echo '<label class="ck-opt"><input type="radio" name="' . esc_attr( self::OPT . '[_tur]' ) . '" value="' . esc_attr( $o[0] ) . '"' . checked( $o[3], true, false ) . '><span class="ck-opt__b"><span class="ck-opt__t">' . esc_html( $o[1] ) . '</span><span class="ck-opt__d">' . esc_html( $o[2] ) . '</span></span><span class="ck-opt__tick">' . self::ico( 'check' ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</fieldset><p class="rbn-help ck-hint">' . esc_html__( 'The necessary-cookie category is always shown. You can change this later in the General tab.', 'cookiekit' ) . '</p></div></div>';
		// 2
		echo '<div data-wstep="2"><div class="rbn-card__h"><h2>' . esc_html__( 'Choose your policy pages', 'cookiekit' ) . '</h2><p>' . esc_html__( 'The banner links to these pages. If you do not have the page yet, leave it empty; no link is shown.', 'cookiekit' ) . '</p></div><div class="rbn-card__b">'
			. '<div class="rbn-field"><div class="ck-l"><label for="t-priv">' . esc_html__( 'Privacy Policy page', 'cookiekit' ) . '</label></div><div class="ck-c">' . self::page_select( 't-priv', self::OPT . '[privacy_url]', (string) $s['privacy_url'] ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<div class="rbn-field"><div class="ck-l"><label for="t-cook">' . esc_html__( 'Cookie Policy page', 'cookiekit' ) . '</label></div><div class="ck-c">' . self::page_select( 't-cook', self::OPT . '[cookie_url]', (string) $s['cookie_url'] ) . '<p class="rbn-help">' . esc_html__( 'Tip: show the cookie table by adding the [cookiekit_cerez_listesi] shortcode to the Cookie Policy page.', 'cookiekit' ) . '</p></div></div></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		// 3
		echo '<div data-wstep="3"><div class="rbn-card__h"><h2>' . esc_html__( 'Preview and go live', 'cookiekit' ) . '</h2><p>' . esc_html__( 'The banner goes live when you activate it.', 'cookiekit' ) . '</p></div><div class="rbn-card__b"><div data-prev-slot="wiz" data-compact></div><ul class="ck-sum"><li>' . self::ico( 'check' ) . '<span id="w-sum1">' . esc_html__( 'Analytics and ad cookies are asked about', 'cookiekit' ) . '</span></li><li>' . self::ico( 'check' ) . '<span>' . esc_html__( 'Google Consent Mode v2: default “denied”', 'cookiekit' ) . '</span></li><li>' . self::ico( 'check' ) . '<span>' . ( ! empty( $s['log'] ) ? esc_html__( 'Consent decisions are recorded (the raw IP is not stored)', 'cookiekit' ) : esc_html__( 'Consent decisions are not recorded (log is off)', 'cookiekit' ) ) . '</span></li></ul></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="rbn-card__foot rbn-card__foot--split ck-wiz__f"><button type="button" class="rbn-btn" id="wiz-back" hidden>' . esc_html__( 'Back', 'cookiekit' ) . '</button><div><button type="button" class="rbn-btn rbn-btn--pri" id="wiz-next" hidden>' . esc_html__( 'Continue', 'cookiekit' ) . '</button><button type="submit" class="rbn-btn rbn-btn--pri" id="wiz-finish">' . esc_html__( 'Activate and finish', 'cookiekit' ) . '</button></div></div></form></div></section>';
	}

	// ───────────────────────── PENCERELER ─────────────────────────
	private static function dialogs( bool $wizard ): void {
		$t = self::tone_attr();
		?>
<template id="ck-tpl-prev">
	<section class="ck-prev" aria-labelledby="pv-h-{id}">
		<div class="ck-prev__bar">
			<h2 id="pv-h-{id}"><?php esc_html_e( 'Live preview', 'cookiekit' ); ?></h2>
			<div class="ck-prev__ctl">
				<div class="rbn-seg" role="group" aria-label="<?php esc_attr_e( 'Device', 'cookiekit' ); ?>" data-seg="dev">
					<button type="button" data-v="desk" aria-pressed="true"><?php echo self::ico( 'desk' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="rbn-sr"><?php esc_html_e( 'Desktop', 'cookiekit' ); ?></span></button>
					<button type="button" data-v="mob" aria-pressed="false"><?php echo self::ico( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="rbn-sr"><?php esc_html_e( 'Mobile', 'cookiekit' ); ?></span></button></div>
				<div class="rbn-seg" role="group" aria-label="<?php esc_attr_e( 'Page theme', 'cookiekit' ); ?>" data-seg="th">
					<button type="button" data-v="light" aria-pressed="true"><?php echo self::ico( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="rbn-sr"><?php esc_html_e( 'On', 'cookiekit' ); ?></span></button>
					<button type="button" data-v="dark" aria-pressed="false"><?php echo self::ico( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="rbn-sr"><?php esc_html_e( 'Dark', 'cookiekit' ); ?></span></button></div>
			</div>
		</div>
		<div class="ck-prev__stage" data-stage><iframe class="ck-prev__frame" title="<?php esc_attr_e( 'Banner preview', 'cookiekit' ); ?>" tabindex="-1"></iframe></div>
		<div class="ck-prev__foot"><p><?php esc_html_e( 'Drawn with the real banner style; the site does not change until you save.', 'cookiekit' ); ?></p><button type="button" class="rbn-btn rbn-btn--sm" data-panel-toggle aria-pressed="false"><?php esc_html_e( 'Show the preferences panel', 'cookiekit' ); ?></button></div>
	</section>
</template>
		<?php
		if ( $wizard ) {
			return;
		}
		$post = esc_url( admin_url( 'admin-post.php' ) );
		?>
<dialog class="rbn-dlg ck-dlg--wide" id="dlg-prev"<?php echo $t; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="dp-h"><form method="dialog"><div class="rbn-dlg__b"><h2 id="dp-h"><?php esc_html_e( 'Banner preview', 'cookiekit' ); ?></h2><div data-prev-slot="dialog"></div></div><div class="rbn-dlg__f"><button class="rbn-btn rbn-btn--pri"><?php esc_html_e( 'Close', 'cookiekit' ); ?></button></div></form></dialog>
<dialog class="rbn-dlg" id="dlg-reset"<?php echo $t; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="dr-h"><form method="dialog"><div class="rbn-dlg__b"><h2 id="dr-h"><?php esc_html_e( 'Reset the texts to default?', 'cookiekit' ); ?></h2><p><?php esc_html_e( 'The title, description and button labels return to the defaults. The site does not change until you save.', 'cookiekit' ); ?></p></div><div class="rbn-dlg__f"><button class="rbn-btn" value="iptal"><?php esc_html_e( 'Cancel', 'cookiekit' ); ?></button><button class="rbn-btn rbn-btn--pri" value="tamam" data-do="reset-texts"><?php esc_html_e( 'Reset to default', 'cookiekit' ); ?></button></div></form></dialog>
<dialog class="rbn-dlg" id="dlg-cookie"<?php echo $t; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="dk-h"><form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><input type="hidden" name="action" value="cookiekit_cookie_add"><?php wp_nonce_field( 'cookiekit_cookie_add' ); ?><div class="rbn-dlg__b"><h2 id="dk-h"><?php esc_html_e( 'Add cookie', 'cookiekit' ); ?></h2>
		<?php
		$rows = array(
			array( 'k-ad', 'name', __( 'Cookie name', 'cookiekit' ), 'ornek_cerez', 64, true ),
			array( 'k-sg', 'provider', __( 'Provider', 'cookiekit' ), __( 'This site', 'cookiekit' ), 80, false ),
			array( 'k-am', 'purpose', __( 'Purpose', 'cookiekit' ), __( 'What is it used for?', 'cookiekit' ), 200, false ),
			array( 'k-su', 'duration', __( 'Duration', 'cookiekit' ), __( '1 year', 'cookiekit' ), 40, false ),
		);
		echo '<div class="rbn-field"><div class="ck-l"><label for="k-cat">' . esc_html__( 'Category', 'cookiekit' ) . '</label></div><div class="ck-c"><select class="rbn-select" id="k-cat" name="cat"><option value="necessary">' . esc_html__( 'Necessary', 'cookiekit' ) . '</option><option value="analytics">' . esc_html__( 'Analytics', 'cookiekit' ) . '</option><option value="ads">' . esc_html__( 'Advertising', 'cookiekit' ) . '</option></select></div></div>';
		foreach ( $rows as $r ) {
			echo '<div class="rbn-field"><div class="ck-l"><label for="' . esc_attr( $r[0] ) . '">' . esc_html( $r[2] ) . '</label></div><div class="ck-c"><input class="rbn-input" id="' . esc_attr( $r[0] ) . '" name="' . esc_attr( $r[1] ) . '" placeholder="' . esc_attr( $r[3] ) . '" maxlength="' . (int) $r[4] . '"' . ( $r[5] ? ' required' : '' ) . '></div></div>';
		}
		?>
	</div><div class="rbn-dlg__f"><button type="button" class="rbn-btn" data-close><?php esc_html_e( 'Cancel', 'cookiekit' ); ?></button><button type="submit" class="rbn-btn rbn-btn--pri"><?php esc_html_e( 'Add the cookie', 'cookiekit' ); ?></button></div></form></dialog>
		<?php
	}
}
