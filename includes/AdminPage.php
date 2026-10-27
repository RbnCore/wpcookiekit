<?php
/**
 * Ayar ekranının görünümü (Ayarlar → CookieKit). Yazma işleri Admin sınıfındadır.
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
			'genel'       => __( 'Genel', 'cookiekit' ),
			'gorunum'     => __( 'Görünüm', 'cookiekit' ),
			'metinler'    => __( 'Metinler', 'cookiekit' ),
			'cerezler'    => __( 'Çerezler', 'cookiekit' ),
			'kayitlar'    => __( 'Onay kayıtları', 'cookiekit' ),
			'entegrasyon' => __( 'Entegrasyon', 'cookiekit' ),
			'yardim'      => __( 'Yardım', 'cookiekit' ),
		);
	}

	private static function ico( string $id ): string {
		return '<svg class="ck-ico" aria-hidden="true"><use href="#i-' . esc_attr( $id ) . '"/></svg>';
	}

	private static function sprite(): string {
		$paths = array(
			'eye'   => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
			'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
			'alert' => '<path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 10v4.5M12 17.5v.01"/>',
			'info'  => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.5v.01"/>',
			'copy'  => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>',
			'plus'  => '<path d="M12 5v14M5 12h14"/>',
			'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
			'down'  => '<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>',
			'desk'  => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
			'phone' => '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/>',
			'sun'   => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>',
			'moon'  => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/>',
			'list'  => '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
			'chart' => '<path d="M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-8"/>',
			'rot'   => '<path d="M4 12a8 8 0 1 1 2.5 5.8M4 19v-5h5"/>',
		);
		$out = '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">';
		foreach ( $paths as $id => $d ) {
			$out .= '<symbol id="i-' . $id . '" viewBox="0 0 24 24">' . $d . '</symbol>';
		}
		return $out . '</svg>';
	}

	/** Alan hatası: <p> iletisi + aria bağı için kimlik. */
	private static function err_msg( string $id ): string {
		if ( ! isset( self::$errs[ $id ] ) ) {
			return '';
		}
		return '<p class="ck-err-msg" id="' . esc_attr( $id ) . '-e">' . self::ico( 'alert' ) . '<span>' . esc_html( self::$errs[ $id ] ) . '</span></p>';
	}

	private static function inv( string $id ): string {
		return isset( self::$errs[ $id ] ) ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-e"' : '';
	}

	private static function field_class( string $id ): string {
		return 'ck-field' . ( isset( self::$errs[ $id ] ) ? ' is-invalid' : '' );
	}

	/** Anahtar satırı (role=switch onay kutusu). */
	private static function sw( string $key, string $title, string $help, string $hid, string $extra = '' ): string {
		$on = ! empty( self::$s[ $key ] );
		return '<label class="ck-switch"><input type="checkbox" role="switch" name="' . esc_attr( self::OPT . '[' . $key . ']' ) . '" value="1"' . checked( $on, true, false ) . ' aria-describedby="' . esc_attr( $hid ) . '"' . $extra . '>'
			. '<span class="ck-switch__t" aria-hidden="true"></span><span class="ck-switch__x"><b>' . esc_html( $title ) . '</b><span class="ck-help" id="' . esc_attr( $hid ) . '">' . esc_html( $help ) . '</span></span></label>';
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

	private static function page_select( string $id, string $name, string $current, string $label ): string {
		$h = '<select class="ck-select" id="' . esc_attr( $id ) . '"' . ( '' !== $name ? ' name="' . esc_attr( $name ) . '"' : '' ) . '><option value="">' . esc_html__( '— Seçin —', 'cookiekit' ) . '</option>';
		foreach ( self::pages( $current ) as $path => $title ) {
			$h .= '<option value="' . esc_attr( $path ) . '"' . selected( $current, $path, false ) . '>' . esc_html( $title . ' (' . $path . ')' ) . '</option>';
		}
		return $h . '</select>';
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
			wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'cookiekit' ), '', array( 'response' => 403 ) );
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
			'defaults' => array_intersect_key( Settings::defaults(), array_flip( array( 'title', 'desc', 'accept', 'reject', 'prefs', 'save' ) ) ),
			'i18n'     => array(
				'unsaved'   => __( 'Kaydedilmemiş değişiklikler var', 'cookiekit' ),
				'copied'    => __( 'Kopyalandı', 'cookiekit' ),
				'copyfail'  => __( 'Seçildi — Ctrl+C ile kopyalayın', 'cookiekit' ),
				'copy'      => __( 'Kopyala', 'cookiekit' ),
				'contrast'  => __( 'Metin / zemin kontrastı', 'cookiekit' ),
				'aa_ok'     => __( 'AA geçer', 'cookiekit' ),
				'aa_fail'   => __( 'AA için en az 4,5:1 gerekir', 'cookiekit' ),
				'nocalc'    => __( 'Kontrast hesaplanamadı: renkleri #rrggbb biçiminde girin.', 'cookiekit' ),
				'step'      => __( 'Adım', 'cookiekit' ),
				'next'      => __( 'Devam', 'cookiekit' ),
				'panel_on'  => __( 'Tercih panelini göster', 'cookiekit' ),
				'panel_off' => __( 'Banner’ı göster', 'cookiekit' ),
				'sum_ads'   => __( 'Analiz ve reklam çerezleri sorulur', 'cookiekit' ),
				'sum_an'    => __( 'Yalnız analiz çerezleri sorulur', 'cookiekit' ),
				'rm_title'  => __( '“%s” çerezi listeden kaldırılsın mı?', 'cookiekit' ),
				'confirm'   => 'SİL',
			),
		);
		?>
<div class="wrap ck-wrap" id="ck" data-tone="" data-cfg="<?php echo esc_attr( (string) wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ) ); ?>">
		<?php
		echo self::sprite(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sabit SVG.
		?>
	<div class="ck-head">
		<div class="ck-head__id">
			<h1>CookieKit</h1>
			<span class="ck-chip"><?php echo esc_html( sprintf( /* translators: %s: sürüm */ __( 'Sürüm %s', 'cookiekit' ), COOKIEKIT_VERSION ) ); ?></span>
			<?php if ( $on ) : ?>
				<span class="ck-chip ck-chip--ok"><span class="ck-dot" aria-hidden="true"></span><?php esc_html_e( 'Etkin', 'cookiekit' ); ?></span>
			<?php else : ?>
				<span class="ck-chip ck-chip--off"><span class="ck-dot" aria-hidden="true"></span><?php esc_html_e( 'Kapalı — banner gösterilmiyor', 'cookiekit' ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( ! $wizard ) : ?>
		<div class="ck-head__act">
			<button type="button" class="ck-btn" data-dialog="dlg-prev"><?php echo self::ico( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Banner’ı önizle', 'cookiekit' ); ?></button>
		</div>
		<?php endif; ?>
	</div>
	<hr class="wp-header-end" style="display:none">
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
		$notes = array(
			'cookie_added'   => array( 'ok', __( 'Çerez listeye eklendi.', 'cookiekit' ) ),
			'cookie_removed' => array( 'ok', __( 'Çerez listeden kaldırıldı.', 'cookiekit' ) ),
			'cookie_invalid' => array( 'err', __( 'Çerez eklenemedi: ad zorunlu, kategori geçerli olmalı.', 'cookiekit' ) ),
			'cookie_limit'   => array( 'err', __( 'En çok 40 özel çerez eklenebilir.', 'cookiekit' ) ),
			'purged'         => array( 'ok', __( 'Tüm onay kayıtları silindi.', 'cookiekit' ) ),
			'purge_fail'     => array( 'err', __( 'Onay kayıtları silinemedi: tablo okunamıyor.', 'cookiekit' ) ),
			'update_checked' => array( 'ok', __( 'Güncelleme kontrolü yapıldı.', 'cookiekit' ) ),
			'update_is_off'  => array( 'err', __( 'Güncelleme kontrolü kapalı; hiçbir bağlantı kurulmadı.', 'cookiekit' ) ),
			'update_off'     => array( 'ok', __( 'Güncelleme kontrolü kapatıldı. GitHub’a artık bağlanılmaz.', 'cookiekit' ) ),
			'update_on'      => array( 'ok', __( 'Güncelleme kontrolü açıldı.', 'cookiekit' ) ),
			'purge_confirm'  => array( 'err', __( 'Silme onaylanmadı: kutuya SİL yazmanız gerekir. Hiçbir kayıt silinmedi.', 'cookiekit' ) ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnız iletiyi seçer, beyaz liste.
		$note = isset( $_GET['ck_note'] ) ? sanitize_key( (string) wp_unslash( $_GET['ck_note'] ) ) : '';
		if ( isset( $notes[ $note ] ) ) {
			$kind = 'ok' === $notes[ $note ][0] ? 'ck-note--ok' : 'ck-note--err';
			$role = 'ok' === $notes[ $note ][0] ? 'status' : 'alert';
			echo '<div class="ck-note ' . esc_attr( $kind ) . '" role="' . esc_attr( $role ) . '">' . self::ico( 'ok' === $notes[ $note ][0] ? 'check' : 'alert' ) . '<div><p>' . esc_html( $notes[ $note ][1] ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$updated = ! empty( $_GET['settings-updated'] );
		if ( self::$errs ) {
			$h = '<div class="ck-note ck-note--err" id="ck-err" role="alert" tabindex="-1">' . self::ico( 'alert' ) . '<div><p><strong>'
				. esc_html( sprintf( /* translators: %d: alan sayısı */ _n( 'Bazı değişiklikler kaydedilmedi: %d alan düzeltilmeli.', 'Bazı değişiklikler kaydedilmedi: %d alan düzeltilmeli.', count( self::$errs ), 'cookiekit' ), count( self::$errs ) ) )
				. '</strong></p><ul>';
			foreach ( self::$errs as $id => $msg ) {
				$h .= '<li>' . ( 'ck-tab' === $id ? '' : '<a href="#' . esc_attr( $id ) . '">' . esc_html( self::label_for( $id ) ) . '</a>: ' ) . esc_html( $msg ) . '</li>';
			}
			echo $h . '</ul></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( $updated ) {
			echo '<div class="ck-note ck-note--ok" role="status">' . self::ico( 'check' ) . '<div><p><strong>' . esc_html__( 'Ayarlar kaydedildi.', 'cookiekit' ) . '</strong> ' . esc_html__( 'Önbellekli sayfa kullanıyorsanız önbelleği temizleyin.', 'cookiekit' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private static function label_for( string $id ): string {
		$map = array(
			'f-days' => __( 'Onay geçerlilik süresi', 'cookiekit' ),
			'c-ac'   => __( 'Vurgu rengi', 'cookiekit' ),
			'c-bg'   => __( 'Zemin rengi', 'cookiekit' ),
			'c-fg'   => __( 'Metin rengi', 'cookiekit' ),
			't-priv' => __( 'Gizlilik Politikası', 'cookiekit' ),
			't-cook' => __( 'Çerez Politikası', 'cookiekit' ),
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
			array( '', __( 'Toplam karar', 'cookiekit' ), (string) $t, false, __( 'son 30 gün', 'cookiekit' ) ),
			array( 'a', __( 'Hepsini kabul', 'cookiekit' ), (string) round( $pct( $st['a'] ) ), true, sprintf( /* translators: %d: karar sayısı */ __( '%d karar', 'cookiekit' ), $st['a'] ) ),
			array( 'r', __( 'Reddet', 'cookiekit' ), (string) round( $pct( $st['r'] ) ), true, sprintf( __( '%d karar · yalnız zorunlu', 'cookiekit' ), $st['r'] ) ),
			array( 'c', __( 'Özel tercih', 'cookiekit' ), (string) round( $pct( $st['c'] ) ), true, sprintf( __( '%d karar', 'cookiekit' ), $st['c'] ) ),
		);
		if ( ! $ok ) {
			echo '<div class="ck-note ck-note--err" role="alert">' . self::ico( 'alert' ) . '<div><p><strong>' . esc_html__( 'Onay kaydı tablosu okunamadı.', 'cookiekit' ) . '</strong> ' . esc_html__( 'Özet sayıları gösterilemiyor; ziyaretçi tarafı etkilenmez, banner çalışmaya devam eder.', 'cookiekit' ) . '</p><p><a href="' . esc_url( Admin::page_url( array( 'tab' => 'yardim' ) ) ) . '">' . esc_html__( 'Yardım → Sorun giderme', 'cookiekit' ) . '</a> · ' . esc_html__( 'Tabloyu yeniden oluşturmak için eklentiyi kapatıp açın.', 'cookiekit' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<ul class="ck-stats" aria-label="' . esc_attr__( 'Son 30 günün özeti', 'cookiekit' ) . '">';
		foreach ( $cards as $i => $c ) {
			echo '<li class="ck-stat"><p class="ck-stat__k">' . ( '' !== $c[0] ? '<span class="ck-key ck-key--' . esc_attr( $c[0] ) . '" aria-hidden="true"></span>' : '' ) . esc_html( $c[1] ) . '</p>';
			if ( $ok ) {
				echo '<p class="ck-stat__v">' . esc_html( $c[2] ) . ( $c[3] ? '<small>%</small>' : '' ) . '</p><p class="ck-stat__s">' . esc_html( $t > 0 || 0 === $i ? $c[4] : __( 'henüz karar yok', 'cookiekit' ) ) . '</p>';
			} else {
				echo '<p class="ck-stat__v">—</p><p class="ck-stat__s">' . esc_html( 0 === $i ? __( 'okunamadı', 'cookiekit' ) : '' ) . '</p>';
			}
			echo '</li>';
		}
		echo '</ul>';
		if ( $ok && $t > 0 ) {
			$label = sprintf( /* translators: 1-3: yüzde */ __( 'Karar dağılımı: yüzde %1$s kabul, yüzde %2$s reddet, yüzde %3$s özel tercih', 'cookiekit' ), round( $pct( $st['a'] ) ), round( $pct( $st['r'] ) ), round( $pct( $st['c'] ) ) );
			echo '<div class="ck-dist" role="img" aria-label="' . esc_attr( $label ) . '"><i style="width:' . esc_attr( (string) $pct( $st['a'] ) ) . '%"></i><i style="width:' . esc_attr( (string) $pct( $st['r'] ) ) . '%"></i><i style="width:' . esc_attr( (string) $pct( $st['c'] ) ) . '%"></i></div>';
		}
	}

	/** @param array<string,string> $names */
	private static function tabs_nav( array $names, string $tab, ?int $total ): void {
		echo '<nav aria-label="' . esc_attr__( 'CookieKit bölümleri', 'cookiekit' ) . '"><ul class="ck-tabs">';
		foreach ( $names as $id => $name ) {
			$count = '';
			if ( 'cerezler' === $id ) {
				$count = (string) array_sum( array_map( 'count', Cookies::catalog() ) );
			} elseif ( 'kayitlar' === $id ) {
				$count = null === $total ? '!' : (string) $total;
			}
			echo '<li><a href="' . esc_url( Admin::page_url( array( 'tab' => $id ) ) ) . '"' . ( $id === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $name )
				. ( '' !== $count ? ' <span class="ck-count">' . esc_html( $count ) . '</span>' : '' ) . '</a></li>';
		}
		echo '</ul></nav>';
	}

	/** Form açılışı: seçenek grubu + gizli sekme alanı. */
	private static function form_open( string $tab ): void {
		echo '<form method="post" action="options.php" data-savebar novalidate>';
		settings_fields( 'cookiekit_group' );
		echo '<input type="hidden" name="' . esc_attr( self::OPT . '[_tab]' ) . '" value="' . esc_attr( $tab ) . '">';
	}

	private static function form_close(): void {
		echo '<div class="ck-bar" data-bar><p><span class="ck-dot" aria-hidden="true"></span><span data-bar-t>' . esc_html__( 'Kaydedilmemiş değişiklikler var', 'cookiekit' ) . '</span></p><div class="ck-bar__act"><button type="button" class="ck-btn" data-revert>' . esc_html__( 'Vazgeç', 'cookiekit' ) . '</button><button type="submit" class="ck-btn ck-btn--pri">' . esc_html__( 'Değişiklikleri kaydet', 'cookiekit' ) . '</button></div></div></form>';
	}

	private static function card_open( string $id, string $title, string $desc_html = '' ): void {
		echo '<section class="ck-card" aria-labelledby="' . esc_attr( $id ) . '"><div class="ck-card__h"><h2 id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</h2>' . ( '' !== $desc_html ? '<p>' . wp_kses( $desc_html, array( 'strong' => array(), 'code' => array() ) ) . '</p>' : '' ) . '</div>';
	}

	private static function prev_slot( string $id ): void {
		echo '<aside class="ck-split__side" aria-label="' . esc_attr__( 'Canlı önizleme', 'cookiekit' ) . '"><div data-prev-slot="' . esc_attr( $id ) . '"></div></aside>';
	}

	// ───────────────────────── GENEL ─────────────────────────
	private static function tab_genel(): void {
		$s = self::$s;
		self::form_open( 'genel' );
		self::card_open( 'g1', __( 'Durum', 'cookiekit' ), esc_html__( 'Banner’ı tüm sitede açın ya da kapatın.', 'cookiekit' ) );
		echo '<div class="ck-card__b"><div class="ck-field"><div class="ck-field__l"><span>' . esc_html__( 'Çerez bandı', 'cookiekit' ) . '</span></div><div class="ck-field__c">'
			. self::sw( 'enabled', __( 'Ziyaretçilere göster', 'cookiekit' ), __( 'Kapatınca banner ve Consent Mode betiği yayından kalkar.', 'cookiekit' ), 'h-en' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</div></div></div></section>';

		self::card_open( 'g2', __( 'Çerez kategorileri', 'cookiekit' ), esc_html__( 'Ziyaretçiden hangi kategoriler için izin istenecek? Kullanmadığınız kategoriyi kapatın.', 'cookiekit' ) );
		echo '<div class="ck-card__b">'
			. '<div class="ck-field"><div class="ck-field__l"><span>' . esc_html__( 'Zorunlu', 'cookiekit' ) . '</span></div><div class="ck-field__c"><label class="ck-switch"><input type="checkbox" role="switch" checked disabled aria-describedby="h-nec"><span class="ck-switch__t" aria-hidden="true"></span><span class="ck-switch__x"><b>' . esc_html__( 'Her zaman açık', 'cookiekit' ) . '</b><span class="ck-help" id="h-nec">' . esc_html__( 'Sitenin çalışması ve tercihin hatırlanması için gerekir; kapatılamaz.', 'cookiekit' ) . '</span></span></label></div></div>'
			. '<div class="ck-field"><div class="ck-field__l"><span>' . esc_html__( 'Analiz', 'cookiekit' ) . '</span></div><div class="ck-field__c">' . self::sw( 'show_analytics', __( 'Analiz çerezlerini sor', 'cookiekit' ), __( 'Google Analytics gibi ölçüm araçları için.', 'cookiekit' ), 'h-an' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<div class="ck-field"><div class="ck-field__l"><span>' . esc_html__( 'Reklam', 'cookiekit' ) . '</span></div><div class="ck-field__c">' . self::sw( 'show_ads', __( 'Reklam çerezlerini sor', 'cookiekit' ), __( 'AdSense ve Google reklam ortakları için. Sitede reklam yoksa kapatın.', 'cookiekit' ), 'h-ad' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</div></section>';

		self::card_open( 'g3', __( 'Onay süresi ve yeniden onay', 'cookiekit' ), sprintf( /* translators: %d: revizyon */ esc_html__( 'Geçerli revizyon: %s. Revizyon değişince tüm ziyaretçilerden yeniden onay istenir.', 'cookiekit' ), '<strong>' . (int) $s['revision'] . '</strong>' ) );
		echo '<div class="ck-card__b"><div class="' . esc_attr( self::field_class( 'f-days' ) ) . '"><div class="ck-field__l"><label for="f-days">' . esc_html__( 'Onay geçerlilik süresi', 'cookiekit' ) . '</label><p class="ck-help">' . esc_html__( '30–395 gün arası. Varsayılan 180.', 'cookiekit' ) . '</p></div>'
			. '<div class="ck-field__c"><span class="ck-unit"><input class="ck-input ck-input--s" type="number" inputmode="numeric" id="f-days" name="' . esc_attr( self::OPT . '[days]' ) . '" value="' . esc_attr( (string) (int) $s['days'] ) . '" min="30" max="395"' . self::inv( 'f-days' ) . '><span>' . esc_html__( 'gün', 'cookiekit' ) . '</span></span>' . self::err_msg( 'f-days' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<div class="ck-field"><div class="ck-field__l"><span>' . esc_html__( 'Yeniden onay', 'cookiekit' ) . '</span></div><div class="ck-field__c"><label class="ck-switch"><input type="checkbox" role="switch" name="' . esc_attr( self::OPT . '[bump_revision]' ) . '" value="1" aria-describedby="h-rev"><span class="ck-switch__t" aria-hidden="true"></span><span class="ck-switch__x"><b>' . esc_html__( 'Kaydederken revizyonu artır', 'cookiekit' ) . '</b><span class="ck-help" id="h-rev">' . esc_html__( 'Kategori ya da çerez listesini değiştirdiyseniz açın. Önbellek kullanıyorsanız ardından temizleyin.', 'cookiekit' ) . '</span></span></label></div></div>'
			. '</div></section>';

		self::card_open( 'g4', __( 'Kayıt ve veri', 'cookiekit' ), esc_html__( 'KVKK ispatı için onay kararlarını saklayın. Ham IP kaydedilmez, 12 ay sonra otomatik silinir.', 'cookiekit' ) );
		echo '<div class="ck-card__b"><div class="ck-field"><div class="ck-field__l"><span>' . esc_html__( 'Onay kaydı', 'cookiekit' ) . '</span></div><div class="ck-field__c">' . self::sw( 'log', __( 'Onay kararlarını kaydet', 'cookiekit' ), __( 'Karar, kategori, sayfa ve günlük dönen tuzla özetlenmiş IP saklanır.', 'cookiekit' ), 'h-log' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<div class="ck-field"><div class="ck-field__l"><span>' . esc_html__( 'Eklenti silinirken', 'cookiekit' ) . '</span></div><div class="ck-field__c">' . self::sw( 'delete_on_uninstall', __( 'Ayarları ve onay kaydını da sil', 'cookiekit' ), __( 'Açıksa eklentiyi silmek ayarları, özel çerezleri ve kayıt tablosunu geri alınamaz biçimde kaldırır.', 'cookiekit' ), 'h-del' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</div></section>';
		self::form_close();
	}

	// ───────────────────────── GÖRÜNÜM ─────────────────────────
	private static function tab_gorunum(): void {
		$s   = self::$s;
		$pos = array(
			'bottom-left'   => array( 'bl', __( 'Alt sol', 'cookiekit' ), '' ),
			'bottom-center' => array( 'bc', __( 'Alt orta', 'cookiekit' ), __( 'Tam genişlik', 'cookiekit' ) ),
			'bottom-right'  => array( 'br', __( 'Alt sağ', 'cookiekit' ), '' ),
			'box'           => array( 'box', __( 'Ortada kutu', 'cookiekit' ), '' ),
		);
		$own = '' !== (string) $s['color_accent'] || '' !== (string) $s['color_bg'] || '' !== (string) $s['color_text'] || isset( self::$errs['c-ac'] ) || isset( self::$errs['c-bg'] ) || isset( self::$errs['c-fg'] );
		self::form_open( 'gorunum' );
		echo '<div class="ck-split"><div>';
		self::card_open( 'v1', __( 'Konum', 'cookiekit' ), esc_html__( 'Banner ekranın neresinde görünsün? Önizleme anında güncellenir.', 'cookiekit' ) );
		echo '<div class="ck-card__b ck-card__b--pad"><fieldset class="ck-opts" style="grid-template-columns:repeat(auto-fit,minmax(6.5rem,1fr))"><legend class="ck-sr">' . esc_html__( 'Banner konumu', 'cookiekit' ) . '</legend>';
		foreach ( $pos as $val => $p ) {
			echo '<label class="ck-opt"><input type="radio" name="' . esc_attr( self::OPT . '[position]' ) . '" value="' . esc_attr( $val ) . '"' . checked( $s['position'], $val, false ) . '><span class="ck-opt__b"><span class="ck-pos ck-pos--' . esc_attr( $p[0] ) . '" aria-hidden="true"><i></i></span><span class="ck-opt__t">' . esc_html( $p[1] ) . '</span>' . ( '' !== $p[2] ? '<span class="ck-opt__d">' . esc_html( $p[2] ) . '</span>' : '' ) . '</span><span class="ck-opt__tick">' . self::ico( 'check' ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</fieldset><p class="ck-help" style="margin-top:var(--ck-s3)">' . esc_html__( 'Telefonda (640 px altı) banner her zaman alt tam genişlik olur.', 'cookiekit' ) . '</p></div></section>';

		self::card_open( 'v2', __( 'Renkler', 'cookiekit' ), esc_html__( 'Temanızla uyumlu kalmak için varsayılan olarak tema renkleri kullanılır.', 'cookiekit' ) );
		echo '<div class="ck-card__b"><div class="ck-field"><div class="ck-field__l"><span id="l-tema">' . esc_html__( 'Renk kaynağı', 'cookiekit' ) . '</span></div><div class="ck-field__c"><fieldset class="ck-seg" aria-labelledby="l-tema">'
			. '<label><input type="radio" name="renk_kaynak" value="tema"' . checked( $own, false, false ) . '><span>' . esc_html__( 'Tema renkleri', 'cookiekit' ) . '</span></label>'
			. '<label><input type="radio" name="renk_kaynak" value="ozel"' . checked( $own, true, false ) . '><span>' . esc_html__( 'Özel renkler', 'cookiekit' ) . '</span></label></fieldset>'
			. '<p class="ck-help">' . esc_html__( '“Tema renkleri”: sitenin --fm-* değişkenleri ya da açık/koyu varsayılan kullanılır (üç renk alanı boş kalır).', 'cookiekit' ) . '</p></div></div>';
		echo '<div class="ck-field" id="colors"' . ( $own ? '' : ' hidden' ) . '><div class="ck-field__l"><span>' . esc_html__( 'Özel renkler', 'cookiekit' ) . '</span><p class="ck-help">' . esc_html__( 'Metin ve zemin arasındaki kontrast en az 4,5:1 olmalı.', 'cookiekit' ) . '</p></div><div class="ck-field__c"><div class="ck-colors">';
		foreach ( array(
			array( 'c-ac', 'color_accent', __( 'Vurgu', 'cookiekit' ), '#0b5fff' ),
			array( 'c-bg', 'color_bg', __( 'Zemin', 'cookiekit' ), '#ffffff' ),
			array( 'c-fg', 'color_text', __( 'Metin', 'cookiekit' ), '#1a1a1a' ),
		) as $c ) {
			$v   = (string) $s[ $c[1] ];
			$pic = sanitize_hex_color( $v ) ? $v : $c[3];
			echo '<div class="' . esc_attr( self::field_class( $c[0] ) ) . ' ck-color-i"><label for="' . esc_attr( $c[0] ) . '">' . esc_html( $c[2] ) . '</label><div class="ck-color">'
				. '<input type="color" value="' . esc_attr( $pic ) . '" data-pair="' . esc_attr( $c[0] ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: renk adı */ __( '%s rengi seçici', 'cookiekit' ), $c[2] ) ) . '">'
				. '<input class="ck-input ck-input--mono" id="' . esc_attr( $c[0] ) . '" type="text" name="' . esc_attr( self::OPT . '[' . $c[1] . ']' ) . '" value="' . esc_attr( $v ) . '" placeholder="' . esc_attr( $c[3] ) . '" maxlength="7" data-hex' . self::inv( $c[0] ) . '></div>' . self::err_msg( $c[0] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><p class="ck-note ck-note--ok" id="contrast" style="margin:var(--ck-s3) 0 0;max-width:34rem" role="status">' . self::ico( 'check' ) . '<span>' . esc_html__( 'Metin / zemin kontrastı: —', 'cookiekit' ) . '</span></p></div></div></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		self::card_open( 'm1', __( 'Banner metni', 'cookiekit' ), esc_html__( 'Kısa ve net yazın: ziyaretçi ne istendiğini tek bakışta anlamalı.', 'cookiekit' ) );
		echo '<div class="ck-card__b"><div class="ck-field"><div class="ck-field__l"><label for="t-title">' . esc_html__( 'Başlık', 'cookiekit' ) . '</label><p class="ck-help">' . esc_html__( 'En çok 80 karakter.', 'cookiekit' ) . '</p></div><div class="ck-field__c"><input class="ck-input" style="max-width:none" id="t-title" name="' . esc_attr( self::OPT . '[title]' ) . '" value="' . esc_attr( (string) $s['title'] ) . '" maxlength="80" data-count="80"><p class="ck-counter" aria-hidden="true"></p></div></div>'
			. '<div class="ck-field"><div class="ck-field__l"><label for="t-desc">' . esc_html__( 'Açıklama', 'cookiekit' ) . '</label><p class="ck-help">' . esc_html__( 'En çok 500 karakter. Hangi çerezlerin neden kullanıldığını söyleyin.', 'cookiekit' ) . '</p></div><div class="ck-field__c"><textarea class="ck-textarea" id="t-desc" name="' . esc_attr( self::OPT . '[desc]' ) . '" rows="4" maxlength="500" data-count="500">' . esc_textarea( (string) $s['desc'] ) . '</textarea><p class="ck-counter" aria-hidden="true"></p></div></div></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		self::card_open( 'm2', __( 'Düğme etiketleri', 'cookiekit' ), esc_html__( 'Kabul et ve Reddet eşit görünürlükte kalır; yalnız yazıları değişir.', 'cookiekit' ) );
		echo '<div class="ck-card__b">';
		foreach ( array(
			array( 't-accept', 'accept', __( 'Kabul düğmesi', 'cookiekit' ), __( 'En çok 30 karakter.', 'cookiekit' ), 30 ),
			array( 't-reject', 'reject', __( 'Red düğmesi', 'cookiekit' ), __( 'En çok 30 karakter.', 'cookiekit' ), 30 ),
			array( 't-prefs', 'prefs', __( 'Tercihler düğmesi', 'cookiekit' ), __( 'Kategori panelini açar. En çok 30 karakter.', 'cookiekit' ), 30 ),
			array( 't-save', 'save', __( 'Seçimi kaydet düğmesi', 'cookiekit' ), __( 'Panelin altındaki düğme. En çok 40 karakter.', 'cookiekit' ), 40 ),
		) as $f ) {
			echo '<div class="ck-field"><div class="ck-field__l"><label for="' . esc_attr( $f[0] ) . '">' . esc_html( $f[2] ) . '</label><p class="ck-help">' . esc_html( $f[3] ) . '</p></div><div class="ck-field__c"><input class="ck-input" id="' . esc_attr( $f[0] ) . '" name="' . esc_attr( self::OPT . '[' . $f[1] . ']' ) . '" value="' . esc_attr( (string) $s[ $f[1] ] ) . '" maxlength="' . (int) $f[4] . '"></div></div>';
		}
		echo '</div></section>';

		self::card_open( 'm3', __( 'Politika bağlantıları', 'cookiekit' ), esc_html__( 'Banner ve tercih paneli bu sayfalara bağlanır. Boş bırakırsanız bağlantı gösterilmez.', 'cookiekit' ) );
		echo '<div class="ck-card__b">';
		foreach ( array(
			array( 't-priv', 'privacy_url', __( 'Gizlilik Politikası', 'cookiekit' ), __( 'Site içi yol (/gizlilik-politikasi/) ya da bu sitenin tam adresi.', 'cookiekit' ) ),
			array( 't-cook', 'cookie_url', __( 'Çerez Politikası', 'cookiekit' ), __( 'Çerez tablosu için [cookiekit_cerez_listesi] kullanan sayfa.', 'cookiekit' ) ),
		) as $f ) {
			$v = (string) $s[ $f[1] ];
			echo '<div class="' . esc_attr( self::field_class( $f[0] ) ) . '"><div class="ck-field__l"><label for="' . esc_attr( $f[0] ) . '">' . esc_html( $f[2] ) . '</label><p class="ck-help">' . esc_html( $f[3] ) . '</p></div><div class="ck-field__c">'
				. '<input class="ck-input" style="max-width:none" id="' . esc_attr( $f[0] ) . '" name="' . esc_attr( self::OPT . '[' . $f[1] . ']' ) . '" value="' . esc_attr( $v ) . '" maxlength="300"' . self::inv( $f[0] ) . '>' . self::err_msg( $f[0] )
				. '<p class="ck-help"><label for="' . esc_attr( $f[0] ) . 'p" class="ck-sr">' . esc_html__( 'Sayfa listesinden seç', 'cookiekit' ) . '</label>'
				. str_replace( '<option value="">' . esc_html__( '— Seçin —', 'cookiekit' ) . '</option>', '<option value="">' . esc_html__( 'Sayfa listesinden seç…', 'cookiekit' ) . '</option>', str_replace( 'class="ck-select"', 'class="ck-select" data-fill="' . esc_attr( $f[0] ) . '" style="max-width:20rem"', self::page_select( $f[0] . 'p', '', '', $f[2] ) ) )
				. '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></section>';
		echo '<p><button type="button" class="ck-btn ck-btn--sm" data-dialog="dlg-reset">' . self::ico( 'rot' ) . esc_html__( 'Metinleri varsayılana sıfırla', 'cookiekit' ) . '</button></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		self::card_open( 'c1', __( 'Çerez listesi', 'cookiekit' ), '' );
		echo '<div style="display:flex;flex-wrap:wrap;gap:var(--ck-s3);justify-content:space-between;align-items:flex-start;padding:0 var(--ck-s5) var(--ck-s3)"><p style="margin:0;color:var(--ck-muted);max-width:42rem">' . esc_html__( 'Tercih panelinde ve [cookiekit_cerez_listesi] kısa kodunda gösterilir. Yalnız sitenizde gerçekten kurulanları listeleyin.', 'cookiekit' ) . '</p><button type="button" class="ck-btn ck-btn--pri" data-dialog="dlg-cookie">' . self::ico( 'plus' ) . esc_html__( 'Çerez ekle', 'cookiekit' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ck-tw" role="region" aria-label="' . esc_attr__( 'Çerez tablosu', 'cookiekit' ) . '" tabindex="0"><table class="ck-table"><caption class="ck-sr">' . esc_html__( 'Kategoriye göre çerezler', 'cookiekit' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Çerez', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Sağlayıcı', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Amaç', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Süre', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Kaynak', 'cookiekit' ) . '</th><th scope="col"><span class="ck-sr">' . esc_html__( 'İşlem', 'cookiekit' ) . '</span></th></tr></thead><tbody>';
		foreach ( Cookies::builtin() as $cat => $rows ) {
			echo '<tr class="ck-table__grp"><th colspan="6" scope="colgroup">' . esc_html( $cats[ $cat ] ?? $cat ) . '</th></tr>';
			foreach ( $rows as $r ) {
				echo '<tr><th scope="row"><code>' . esc_html( $r[0] ) . '</code></th><td>' . esc_html( $r[1] ) . '</td><td>' . esc_html( $r[2] ) . '</td><td>' . esc_html( $r[3] ) . '</td><td><span class="ck-chip">' . esc_html__( 'Yerleşik', 'cookiekit' ) . '</span></td><td></td></tr>';
			}
			foreach ( $by_cat[ $cat ] ?? array() as $r ) {
				echo '<tr><th scope="row"><code>' . esc_html( $r['name'] ) . '</code></th><td>' . esc_html( $r['provider'] ) . '</td><td>' . esc_html( $r['purpose'] ) . '</td><td>' . esc_html( $r['duration'] ) . '</td><td><span class="ck-chip ck-chip--ok">' . esc_html__( 'Özel', 'cookiekit' ) . '</span></td><td class="ck-num"><button type="button" class="ck-btn ck-btn--sm ck-btn--danger" data-rm="' . esc_attr( $r['id'] ) . '" data-rm-name="' . esc_attr( $r['name'] ) . '">' . self::ico( 'trash' ) . esc_html__( 'Kaldır', 'cookiekit' ) . '<span class="ck-sr"> ' . esc_html( $r['name'] ) . '</span></button></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '</tbody></table></div>';
		if ( ! $custom ) {
			echo '<div class="ck-empty"><span class="ck-empty__i">' . self::ico( 'list' ) . '</span><h3>' . esc_html__( 'Özel çerez eklenmedi', 'cookiekit' ) . '</h3><p>' . esc_html__( 'Yukarıdaki yerleşik liste Google ve WordPress çerezlerini kapsar. Sitenize özel bir çerez kuruyorsanız ekleyin; ziyaretçi paneli ve politika sayfası otomatik güncellenir.', 'cookiekit' ) . '</p><button type="button" class="ck-btn" data-dialog="dlg-cookie">' . esc_html__( 'Çerez ekle', 'cookiekit' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<p class="ck-help" style="padding:var(--ck-s3) var(--ck-s5) var(--ck-s4);margin:0">' . esc_html__( 'Yerleşik satırlar yalnız Google çerez referansında belgelenmiş çerezlerdir; değiştirilemez. Yeni çerez eklerseniz Genel → “Yeniden onay”ı açmayı düşünün.', 'cookiekit' ) . '</p></section>';
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
			echo '<div class="ck-note ck-note--warn">' . self::ico( 'alert' ) . '<div><p><strong>' . esc_html__( 'Onay kaydı kapalı.', 'cookiekit' ) . '</strong> ' . esc_html__( 'Yeni kararlar saklanmıyor; KVKK ispatı için', 'cookiekit' ) . ' <a href="' . esc_url( Admin::page_url( array( 'tab' => 'genel' ) ) ) . '">' . esc_html__( 'Genel → Kayıt ve veri', 'cookiekit' ) . '</a> ' . esc_html__( 'bölümünden açın.', 'cookiekit' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		self::card_open( 'k1', __( 'Onay kararları', 'cookiekit' ), '' );
		$csv = wp_nonce_url( admin_url( 'admin-post.php?action=cookiekit_csv' ), 'cookiekit_csv' );
		echo '<div style="display:flex;flex-wrap:wrap;gap:var(--ck-s3);justify-content:space-between;align-items:flex-start;padding:0 var(--ck-s5) var(--ck-s3)"><p style="margin:0;color:var(--ck-muted);max-width:42rem">' . esc_html__( 'Kayıtlar 12 ay sonra otomatik silinir. Ham IP saklanmaz; yalnız günlük dönen tuzla özet tutulur.', 'cookiekit' ) . '</p><div class="ck-head__act"><a class="ck-btn" href="' . esc_url( $csv ) . '">' . self::ico( 'down' ) . esc_html__( 'CSV dışa aktar', 'cookiekit' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $total ) {
			echo '<button type="button" class="ck-btn ck-btn--danger" data-dialog="dlg-purge">' . self::ico( 'trash' ) . esc_html__( 'Kayıtları sil', 'cookiekit' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div></div>';
		if ( null === $total ) {
			echo '<div class="ck-empty"><span class="ck-empty__i">' . self::ico( 'chart' ) . '</span><h3>' . esc_html__( 'Kayıtlar gösterilemiyor', 'cookiekit' ) . '</h3><p>' . esc_html__( 'Onay kaydı tablosu okunamadı. Ziyaretçi tarafı etkilenmez; kayıt yazma da duraklamış olabilir.', 'cookiekit' ) . '</p></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		if ( 0 === $total ) {
			echo '<div class="ck-empty"><span class="ck-empty__i">' . self::ico( 'chart' ) . '</span><h3>' . esc_html__( 'Henüz onay kararı yok', 'cookiekit' ) . '</h3><p>' . esc_html__( 'Bir ziyaretçi bannerda seçim yapınca burada görünür. Yeni kurduysanız, çıkış yapmış bir tarayıcıda siteyi açıp bannerı deneyin.', 'cookiekit' ) . '</p><a class="ck-btn" href="' . esc_url( Admin::page_url( array( 'tab' => 'gorunum' ) ) ) . '">' . esc_html__( 'Banner’ı önizle', 'cookiekit' ) . '</a></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$act = array(
			'a' => array( __( 'Hepsini kabul', 'cookiekit' ), ' ck-chip--ok' ),
			'r' => array( __( 'Reddet', 'cookiekit' ), '' ),
			'c' => array( __( 'Özel tercih', 'cookiekit' ), '' ),
		);
		echo '<div class="ck-tw" role="region" aria-label="' . esc_attr__( 'Onay kaydı tablosu', 'cookiekit' ) . '" tabindex="0"><table class="ck-table"><caption class="ck-sr">' . esc_html__( 'Son onay kararları', 'cookiekit' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Zaman', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Karar', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'İzin verilen', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Rev.', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'Sayfa', 'cookiekit' ) . '</th><th scope="col">' . esc_html__( 'IP özeti', 'cookiekit' ) . '</th></tr></thead><tbody>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( Log::recent( $per, ( $page - 1 ) * $per ) as $r ) {
			$mask  = (int) $r['categories'];
			$allow = array( __( 'Zorunlu', 'cookiekit' ) );
			if ( $mask & Settings::BIT_ANALYTICS ) {
				$allow[] = __( 'Analiz', 'cookiekit' );
			}
			if ( $mask & Settings::BIT_ADS ) {
				$allow[] = __( 'Reklam', 'cookiekit' );
			}
			$a = $act[ $r['action'] ] ?? $act['c'];
			echo '<tr><td>' . esc_html( get_date_from_gmt( (string) $r['created_at'], 'd.m.Y H:i' ) ) . '</td><td><span class="ck-chip' . esc_attr( $a[1] ) . '">' . esc_html( $a[0] ) . '</span></td><td>' . esc_html( implode( ', ', $allow ) ) . '</td><td>' . (int) $r['revision'] . '</td><td><code>' . esc_html( (string) $r['path'] ) . '</code></td><td><code>' . esc_html( substr( (string) $r['ip_hash'], 0, 8 ) ) . '</code></td></tr>';
		}
		echo '</tbody></table></div>';
		$pages = (int) ceil( $total / $per );
		$from  = ( $page - 1 ) * $per + 1;
		$to    = min( $total, $page * $per );
		echo '<div class="ck-pager"><span>' . esc_html( sprintf( /* translators: 1: toplam 2: başlangıç 3: bitiş */ __( '%1$d kayıttan %2$d–%3$d gösteriliyor', 'cookiekit' ), $total, $from, $to ) ) . '</span><span class="ck-pager__b">';
		echo $page > 1 ? '<a class="ck-btn ck-btn--sm" href="' . esc_url( Admin::page_url( array( 'tab' => 'kayitlar', 'kp' => (string) ( $page - 1 ) ) ) ) . '">' . esc_html__( 'Önceki', 'cookiekit' ) . '</a>' : '<span class="ck-btn ck-btn--sm" aria-disabled="true">' . esc_html__( 'Önceki', 'cookiekit' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $page < $pages ? '<a class="ck-btn ck-btn--sm" href="' . esc_url( Admin::page_url( array( 'tab' => 'kayitlar', 'kp' => (string) ( $page + 1 ) ) ) ) . '">' . esc_html__( 'Sonraki', 'cookiekit' ) . '</a>' : '<span class="ck-btn ck-btn--sm" aria-disabled="true">' . esc_html__( 'Sonraki', 'cookiekit' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</span></div></section>';
	}

	// ───────────────────────── ENTEGRASYON ─────────────────────────
	private static function status_row( bool $ok, string $title, string $small_html, string $chip ): string {
		return '<li><span class="ck-status__i ' . ( $ok ? 'ck-status__i--ok' : 'ck-status__i--no' ) . '">' . self::ico( $ok ? 'check' : 'info' ) . '</span><span><b>' . esc_html( $title ) . '</b><small>' . wp_kses( $small_html, array( 'code' => array() ) ) . '</small></span><span class="ck-chip' . ( $ok ? ' ck-chip--ok' : ' ck-chip--off' ) . '">' . esc_html( $chip ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function tab_entegrasyon(): void {
		$on  = ! empty( self::$s['enabled'] );
		$chp = $on ? __( 'Etkin', 'cookiekit' ) : __( 'Kapalı', 'cookiekit' );
		self::card_open( 'e1', __( 'Google Consent Mode v2', 'cookiekit' ), esc_html__( 'Ayar gerekmez; eklenti sayfa başında otomatik çalışır. Durum bilgi amaçlıdır.', 'cookiekit' ) );
		echo '<div class="ck-card__b"><ul class="ck-status">'
			. self::status_row( $on, __( 'Varsayılan: reddedildi', 'cookiekit' ), '<code>ad_storage</code>, <code>analytics_storage</code>, <code>ad_user_data</code>, <code>ad_personalization</code> — ' . esc_html__( 'sayfa başında, 500 ms güncelleme beklemesiyle', 'cookiekit' ), $chp ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::status_row( $on, __( 'Seçimde güncelleme', 'cookiekit' ), esc_html__( 'Ziyaretçi karar verince', 'cookiekit' ) . ' <code>consent update</code> ' . esc_html__( 'gönderilir; geri gelen ziyaretçide anında uygulanır.', 'cookiekit' ), $chp ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::status_row( $on, __( 'dataLayer olayı', 'cookiekit' ), '<code>cookiekit_consent_update</code> ' . esc_html__( 'olayı Google Etiket Yöneticisi tetikleyicisi olarak kullanılabilir.', 'cookiekit' ), $chp ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</ul></div></section>';

		$sitekit = defined( 'GOOGLESITEKIT_VERSION' );
		$monster = class_exists( 'MonsterInsights' ) || function_exists( 'MonsterInsights' );
		self::card_open( 'e2', __( 'Algılanan eklentiler', 'cookiekit' ), esc_html__( 'Aynı etiketi iki eklenti yüklemesin diye kontrol edilir.', 'cookiekit' ) );
		echo '<div class="ck-card__b"><ul class="ck-status">'
			. self::status_row( $sitekit, __( 'Google Site Kit', 'cookiekit' ), $sitekit ? esc_html__( 'Algılandı. Analytics ve AdSense etiketleri onay verilene kadar Consent Mode ile kısıtlanır.', 'cookiekit' ) : esc_html__( 'Etkin değil.', 'cookiekit' ), $sitekit ? __( 'Algılandı', 'cookiekit' ) : __( 'Bulunamadı', 'cookiekit' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::status_row( $monster, __( 'MonsterInsights', 'cookiekit' ), $monster ? esc_html__( 'Algılandı. Analytics etiketi onaya bağlanmıyorsa betiği cookiekit_script() ile yükleyin.', 'cookiekit' ) : esc_html__( 'Etkin değil.', 'cookiekit' ), $monster ? __( 'Algılandı', 'cookiekit' ) : __( 'Bulunamadı', 'cookiekit' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::status_row( ! empty( self::$s['show_ads'] ), __( 'Reklam kategorisi', 'cookiekit' ), ! empty( self::$s['show_ads'] ) ? esc_html__( 'Açık. Reklam kodunuzu', 'cookiekit' ) . ' <code>cookiekit_script()</code> ' . esc_html__( 'ile yükleyin (aşağıda).', 'cookiekit' ) : esc_html__( 'Kapalı: bannerda reklam kategorisi sorulmuyor.', 'cookiekit' ), ! empty( self::$s['show_ads'] ) ? __( 'Açık', 'cookiekit' ) : __( 'Kapalı', 'cookiekit' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '</ul></div></section>';

		self::card_open( 'e3', __( 'Kısa kodlar ve kod parçaları', 'cookiekit' ), esc_html__( 'Kopyalayıp sayfanıza, temanıza ya da düğmenize yapıştırın.', 'cookiekit' ) );
		$snips = array(
			array( 's1', __( 'Tercih düğmesi (kısa kod)', 'cookiekit' ), '[cookiekit_tercihler label="Çerez tercihlerini yönet"]' ),
			array( 's2', __( 'Çerez tablosu (Çerez Politikası sayfasına)', 'cookiekit' ), '[cookiekit_cerez_listesi]' ),
			array( 's3', __( 'Herhangi bir öğeden paneli aç', 'cookiekit' ), '<a href="#" data-cookiekit-open>Çerez ayarları</a>' ),
			array( 's4', __( 'Betiği onaya bağla (PHP)', 'cookiekit' ), "<?php echo cookiekit_script( 'ads', 'https://ornek.com/reklam.js' ); ?>" ),
			array( 's5', 'JavaScript', "Cookiekit.open();            // paneli aç\nCookiekit.consent();         // maske: null | 1 | 3 | 5 | 7\ndocument.addEventListener('cookiekit:consent', e => e.detail.mask);" ),
		);
		echo '<div class="ck-card__b ck-card__b--pad">';
		foreach ( $snips as $i => $sn ) {
			echo '<p class="ck-snip-l"' . ( 0 === $i ? ' style="margin-top:0"' : '' ) . '>' . esc_html( $sn[1] ) . '</p><div class="ck-snip"><pre id="' . esc_attr( $sn[0] ) . '">' . esc_html( $sn[2] ) . '</pre><button type="button" class="ck-btn" data-copy="' . esc_attr( $sn[0] ) . '">' . self::ico( 'copy' ) . '<span>' . esc_html__( 'Kopyala', 'cookiekit' ) . '</span></button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<p class="ck-sr" id="copy-live" role="status" aria-live="polite"></p></div></section>';
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
				$out[ $ver ][] = substr( $line, 2 );
			} elseif ( 0 === strpos( $line, '==' ) ) {
				break;
			}
		}
		return $out;
	}

	private static function tab_yardim(): void {
		self::card_open( 'y1', __( 'Hızlı başlangıç', 'cookiekit' ), esc_html__( 'Kurulumdan sonra 2 dakikada doğru ayar:', 'cookiekit' ) );
		echo '<div class="ck-card__b ck-card__b--pad"><ol class="ck-steps"><li><b>' . esc_html__( 'Kategorileri seçin', 'cookiekit' ) . '</b><p>' . esc_html__( 'Genel → yalnız kullandığınız kategorileri açık bırakın (reklam yoksa kapatın).', 'cookiekit' ) . '</p></li><li><b>' . esc_html__( 'Sayfaları bağlayın', 'cookiekit' ) . '</b><p>' . esc_html__( 'Metinler → Gizlilik ve Çerez Politikası sayfalarınızı seçin.', 'cookiekit' ) . '</p></li><li><b>' . esc_html__( 'Önizleyip etkinleştirin', 'cookiekit' ) . '</b><p>' . esc_html__( 'Görünüm → banner’ı çevresine uydurun; Genel → “Ziyaretçilere göster”i açın.', 'cookiekit' ) . '</p></li></ol><p style="margin:var(--ck-s4) 0 0"><a class="ck-btn" href="' . esc_url( Admin::page_url( array( 'wizard' => '1' ) ) ) . '">' . esc_html__( 'Kurulum sihirbazını yeniden aç', 'cookiekit' ) . '</a></p></div></section>';

		self::card_open( 'y2', __( 'Sık sorulanlar', 'cookiekit' ), '' );
		$faq = array(
			array( __( 'Banner görünmüyor, ne yapmalıyım?', 'cookiekit' ), __( 'Önbellek eklentinizi temizleyin ve siteyi giriş yapmamış (gizli pencere) bir tarayıcıda açın. Daha önce karar verdiyseniz banner tekrar çıkmaz: Genel → Yeniden onay ile revizyonu artırın.', 'cookiekit' ) ),
			array( __( 'Ziyaretçi “Reddet” derse Google etiketleri ne olur?', 'cookiekit' ), __( 'Consent Mode v2 varsayılanı “reddedildi” kalır; Analytics ve reklam çerezleri kurulmaz.', 'cookiekit' ) ),
			array( __( 'Onay kayıtlarında kişisel veri saklanıyor mu?', 'cookiekit' ), __( 'Ham IP ve tam tarayıcı bilgisi saklanmaz; yalnız günlük dönen tuzla özet, karar ve sayfa yolu tutulur. Kayıtlar 12 ay sonra silinir.', 'cookiekit' ) ),
			array( __( 'Kendi çerezimi nasıl eklerim?', 'cookiekit' ), __( 'Çerezler sekmesi → “Çerez ekle”. Eklediğiniz çerez tercih panelinde ve politika sayfasındaki tabloda görünür.', 'cookiekit' ) ),
			array( __( 'Başka bir eklentiyle çakışır mı?', 'cookiekit' ), __( 'Aynı etiketi iki eklenti yüklüyorsa Entegrasyon sekmesindeki algılama durumuna bakın.', 'cookiekit' ) ),
			array( __( 'Sorun giderme: özet kartları “okunamadı” diyor', 'cookiekit' ), __( 'Onay kaydı tablosu okunamıyor demektir. Ziyaretçi tarafı etkilenmez. Eklentiyi kapatıp yeniden etkinleştirmek tabloyu yeniden oluşturur; mevcut kayıtlar silinmez.', 'cookiekit' ) ),
		);
		echo '<div class="ck-card__b ck-faq">';
		foreach ( $faq as $q ) {
			echo '<details><summary>' . esc_html( $q[0] ) . '</summary><p>' . esc_html( $q[1] ) . '</p></details>';
		}
		echo '</div></section>';

		$hdr = get_file_data( COOKIEKIT_FILE, array( 'wp' => 'Requires at least', 'php' => 'Requires PHP', 'lic' => 'License' ) );
		self::card_open( 'y3', __( 'Sürüm ve kaynak', 'cookiekit' ), '' );
		echo '<div class="ck-card__b ck-card__b--pad"><dl class="ck-dl"><dt>' . esc_html__( 'Eklenti', 'cookiekit' ) . '</dt><dd>CookieKit</dd><dt>' . esc_html__( 'Sürüm', 'cookiekit' ) . '</dt><dd>' . esc_html( COOKIEKIT_VERSION ) . '</dd><dt>' . esc_html__( 'Gereksinim', 'cookiekit' ) . '</dt><dd>WordPress ' . esc_html( $hdr['wp'] ) . ' · PHP ' . esc_html( $hdr['php'] ) . '</dd><dt>' . esc_html__( 'Lisans', 'cookiekit' ) . '</dt><dd>' . esc_html( $hdr['lic'] ) . '</dd><dt>' . esc_html__( 'Kaynak', 'cookiekit' ) . '</dt><dd><a href="https://github.com/RbnCore" rel="noopener" target="_blank">github.com/RbnCore<span class="ck-sr"> ' . esc_html__( '(yeni sekmede açılır)', 'cookiekit' ) . '</span></a></dd></dl>';
		echo '<h3 style="margin:var(--ck-s5) 0 var(--ck-s2);font-size:.9375rem">' . esc_html__( 'Sürüm notları', 'cookiekit' ) . '</h3><ul class="ck-log">';
		foreach ( self::changelog() as $ver => $items ) {
			echo '<li><b>' . esc_html( $ver ) . '</b> — ' . esc_html( implode( ' ', $items ) ) . '</li>';
		}
		echo '</ul></div></section>';

		self::card_open( 'y4', __( 'Güncelleme', 'cookiekit' ), esc_html__( 'Yeni sürümler GitHub’daki resmî yayınlardan gelir.', 'cookiekit' ) );
		echo '<div class="ck-card__b ck-card__b--pad">' . Updater::render_card() . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Updater::render_card() çıktısı kaçışlıdır.
	}

	// ───────────────────────── SİHİRBAZ ─────────────────────────
	private static function wizard(): void {
		$s    = self::$s;
		$skip = wp_nonce_url( admin_url( 'admin-post.php?action=cookiekit_wizard_skip' ), 'cookiekit_wizard_skip' );
		$ads  = ! empty( $s['show_ads'] ) || empty( $s['wizard_done'] );
		echo '<section id="wizard" aria-labelledby="wiz-h"><div class="ck-wiz"><div class="ck-wiz__top"><p id="wiz-n">' . esc_html__( 'Kurulum', 'cookiekit' ) . '</p><a class="ck-btn ck-btn--link" href="' . esc_url( $skip ) . '">' . esc_html__( 'Şimdilik atla', 'cookiekit' ) . '</a></div>';
		echo '<ol class="ck-stepper" aria-label="' . esc_attr__( 'Kurulum adımları', 'cookiekit' ) . '"><li data-st="1"><b>1</b><span>' . esc_html__( 'Site türü', 'cookiekit' ) . '</span></li><li data-st="2"><b>2</b><span>' . esc_html__( 'Metin ve bağlantılar', 'cookiekit' ) . '</span></li><li data-st="3"><b>3</b><span>' . esc_html__( 'Önizle ve etkinleştir', 'cookiekit' ) . '</span></li></ol>';
		echo '<form class="ck-card" id="wiz-form" method="post" action="options.php">';
		settings_fields( 'cookiekit_group' );
		echo '<input type="hidden" name="' . esc_attr( self::OPT . '[_tab]' ) . '" value="kurulum">';
		// 1
		echo '<div data-wstep="1"><div class="ck-card__h"><h2 id="wiz-h">' . esc_html__( 'Sitenizde hangi çerezler kullanılıyor?', 'cookiekit' ) . '</h2><p>' . esc_html__( 'Yalnız gerçekten kullandığınız kategoriyi gösterin; kullanılmayan kategori ziyaretçiye gereksiz soru olur.', 'cookiekit' ) . '</p></div><div class="ck-card__b ck-card__b--pad"><fieldset class="ck-opts" style="grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))"><legend class="ck-sr">' . esc_html__( 'Site türü', 'cookiekit' ) . '</legend>';
		foreach ( array(
			array( 'reklam', __( 'Analiz + Reklam', 'cookiekit' ), __( 'Google Analytics ve AdSense (ya da başka Google reklamı) kullanıyorum.', 'cookiekit' ), $ads ),
			array( 'analiz', __( 'Yalnız Analiz', 'cookiekit' ), __( 'Sadece ziyaretçi ölçümü yapıyorum, reklam göstermiyorum.', 'cookiekit' ), ! $ads ),
		) as $o ) {
			echo '<label class="ck-opt"><input type="radio" name="' . esc_attr( self::OPT . '[_tur]' ) . '" value="' . esc_attr( $o[0] ) . '"' . checked( $o[3], true, false ) . '><span class="ck-opt__b"><span class="ck-opt__t">' . esc_html( $o[1] ) . '</span><span class="ck-opt__d">' . esc_html( $o[2] ) . '</span></span><span class="ck-opt__tick">' . self::ico( 'check' ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</fieldset><p class="ck-help" style="margin-top:var(--ck-s3)">' . esc_html__( 'Zorunlu çerez kategorisi her zaman görünür. Sonradan Genel sekmesinden değiştirebilirsiniz.', 'cookiekit' ) . '</p></div></div>';
		// 2
		echo '<div data-wstep="2"><div class="ck-card__h"><h2>' . esc_html__( 'Politika sayfalarınızı seçin', 'cookiekit' ) . '</h2><p>' . esc_html__( 'Banner bu sayfalara bağlanır. Sayfanız henüz yoksa boş bırakın; bağlantı gösterilmez.', 'cookiekit' ) . '</p></div><div class="ck-card__b">'
			. '<div class="ck-field"><div class="ck-field__l"><label for="t-priv">' . esc_html__( 'Gizlilik Politikası sayfası', 'cookiekit' ) . '</label></div><div class="ck-field__c">' . self::page_select( 't-priv', self::OPT . '[privacy_url]', (string) $s['privacy_url'], '' ) . '</div></div>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<div class="ck-field"><div class="ck-field__l"><label for="t-cook">' . esc_html__( 'Çerez Politikası sayfası', 'cookiekit' ) . '</label></div><div class="ck-field__c">' . self::page_select( 't-cook', self::OPT . '[cookie_url]', (string) $s['cookie_url'], '' ) . '<p class="ck-help">' . esc_html__( 'İpucu: Çerez Politikası sayfasına [cookiekit_cerez_listesi] kısa kodunu ekleyerek çerez tablosunu gösterin.', 'cookiekit' ) . '</p></div></div></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		// 3
		echo '<div data-wstep="3"><div class="ck-card__h"><h2>' . esc_html__( 'Önizleyin ve etkinleştirin', 'cookiekit' ) . '</h2><p>' . esc_html__( 'Etkinleştirince banner yayına girer.', 'cookiekit' ) . '</p></div><div class="ck-card__b ck-card__b--pad"><div data-prev-slot="wiz" data-compact></div><ul class="ck-sum" style="margin-top:var(--ck-s4)"><li>' . self::ico( 'check' ) . '<span id="w-sum1">' . esc_html__( 'Analiz ve reklam çerezleri sorulur', 'cookiekit' ) . '</span></li><li>' . self::ico( 'check' ) . '<span>' . esc_html__( 'Google Consent Mode v2: varsayılan “reddedildi”', 'cookiekit' ) . '</span></li><li>' . self::ico( 'check' ) . '<span>' . ( ! empty( $s['log'] ) ? esc_html__( 'Onay kararları kaydedilir (ham IP saklanmaz)', 'cookiekit' ) : esc_html__( 'Onay kararları kaydedilmez (günlük kapalı)', 'cookiekit' ) ) . '</span></li></ul></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="ck-wiz__f"><button type="button" class="ck-btn" id="wiz-back" hidden>' . esc_html__( 'Geri', 'cookiekit' ) . '</button><div><button type="button" class="ck-btn ck-btn--pri" id="wiz-next" hidden>' . esc_html__( 'Devam', 'cookiekit' ) . '</button><button type="submit" class="ck-btn ck-btn--pri" id="wiz-finish">' . esc_html__( 'Etkinleştir ve bitir', 'cookiekit' ) . '</button></div></div></form></div></section>';
	}

	// ───────────────────────── PENCERELER ─────────────────────────
	private static function dialogs( bool $wizard ): void {
		?>
<template id="ck-tpl-prev">
	<section class="ck-prev" aria-labelledby="pv-h-{id}">
		<div class="ck-prev__bar">
			<h2 id="pv-h-{id}"><?php esc_html_e( 'Canlı önizleme', 'cookiekit' ); ?></h2>
			<div class="ck-prev__ctl">
				<fieldset class="ck-seg"><legend class="ck-sr"><?php esc_html_e( 'Cihaz', 'cookiekit' ); ?></legend>
					<label><input type="radio" name="pv-dev-{id}" value="desk" checked><span><?php echo self::ico( 'desk' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="ck-sr"><?php esc_html_e( 'Masaüstü', 'cookiekit' ); ?></span></span></label>
					<label><input type="radio" name="pv-dev-{id}" value="mob"><span><?php echo self::ico( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="ck-sr"><?php esc_html_e( 'Mobil', 'cookiekit' ); ?></span></span></label></fieldset>
				<fieldset class="ck-seg"><legend class="ck-sr"><?php esc_html_e( 'Sayfa teması', 'cookiekit' ); ?></legend>
					<label><input type="radio" name="pv-th-{id}" value="light" checked><span><?php echo self::ico( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="ck-sr"><?php esc_html_e( 'Açık', 'cookiekit' ); ?></span></span></label>
					<label><input type="radio" name="pv-th-{id}" value="dark"><span><?php echo self::ico( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="ck-sr"><?php esc_html_e( 'Koyu', 'cookiekit' ); ?></span></span></label></fieldset>
			</div>
		</div>
		<div class="ck-prev__stage" data-stage><iframe class="ck-prev__frame" title="<?php esc_attr_e( 'Banner önizlemesi', 'cookiekit' ); ?>" tabindex="-1"></iframe></div>
		<div class="ck-prev__foot"><p><?php esc_html_e( 'Gerçek banner stiliyle çizilir; kaydedene kadar sitede değişmez.', 'cookiekit' ); ?></p><button type="button" class="ck-btn ck-btn--sm" data-panel-toggle aria-pressed="false"><?php esc_html_e( 'Tercih panelini göster', 'cookiekit' ); ?></button></div>
	</section>
</template>
		<?php
		if ( $wizard ) {
			return;
		}
		$post = esc_url( admin_url( 'admin-post.php' ) );
		?>
<dialog class="ck-dlg ck-dlg--wide" id="dlg-prev" aria-labelledby="dp-h"><form method="dialog"><div class="ck-dlg__b"><h2 id="dp-h"><?php esc_html_e( 'Banner önizlemesi', 'cookiekit' ); ?></h2><div data-prev-slot="dialog"></div></div><div class="ck-dlg__f"><button class="ck-btn ck-btn--pri"><?php esc_html_e( 'Kapat', 'cookiekit' ); ?></button></div></form></dialog>
<dialog class="ck-dlg" id="dlg-reset" aria-labelledby="dr-h"><form method="dialog"><div class="ck-dlg__b"><h2 id="dr-h"><?php esc_html_e( 'Metinler varsayılana dönsün mü?', 'cookiekit' ); ?></h2><p><?php esc_html_e( 'Başlık, açıklama ve düğme etiketleri Türkçe varsayılanlara döner. Kaydedene kadar sitede değişmez.', 'cookiekit' ); ?></p></div><div class="ck-dlg__f"><button class="ck-btn" value="iptal"><?php esc_html_e( 'Vazgeç', 'cookiekit' ); ?></button><button class="ck-btn ck-btn--pri" value="tamam" data-do="reset-texts"><?php esc_html_e( 'Varsayılana dön', 'cookiekit' ); ?></button></div></form></dialog>
<dialog class="ck-dlg" id="dlg-purge" aria-labelledby="du-h"><form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><input type="hidden" name="action" value="cookiekit_purge_log"><?php wp_nonce_field( 'cookiekit_purge_log' ); ?><div class="ck-dlg__b"><h2 id="du-h"><?php esc_html_e( 'Tüm onay kayıtları silinsin mi?', 'cookiekit' ); ?></h2><p><?php esc_html_e( 'Bu işlem geri alınamaz. KVKK kapsamında ispat için saklamanız gereken kayıtlar varsa önce CSV olarak indirin.', 'cookiekit' ); ?></p><label for="purge-t" style="font-weight:600"><?php esc_html_e( 'Onaylamak için SİL yazın', 'cookiekit' ); ?></label><input class="ck-input" id="purge-t" name="confirm" autocomplete="off" style="margin-top:var(--ck-s2)"></div><div class="ck-dlg__f"><button type="button" class="ck-btn" data-close><?php esc_html_e( 'Vazgeç', 'cookiekit' ); ?></button><button type="submit" class="ck-btn ck-btn--danger" id="purge-ok" disabled><?php esc_html_e( 'Tüm kayıtları sil', 'cookiekit' ); ?></button></div></form></dialog>
<dialog class="ck-dlg" id="dlg-rmcookie" aria-labelledby="dc-h"><form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><input type="hidden" name="action" value="cookiekit_cookie_remove"><input type="hidden" name="id" id="rm-id" value=""><?php wp_nonce_field( 'cookiekit_cookie_remove' ); ?><div class="ck-dlg__b"><h2 id="dc-h"><?php esc_html_e( 'Çerez listeden kaldırılsın mı?', 'cookiekit' ); ?></h2><p><?php esc_html_e( 'Çerez tercih panelinden ve politika tablosundan kalkar. Sitenizdeki çerezin kendisi silinmez.', 'cookiekit' ); ?></p></div><div class="ck-dlg__f"><button type="button" class="ck-btn" data-close><?php esc_html_e( 'Vazgeç', 'cookiekit' ); ?></button><button type="submit" class="ck-btn ck-btn--danger"><?php esc_html_e( 'Listeden kaldır', 'cookiekit' ); ?></button></div></form></dialog>
<dialog class="ck-dlg" id="dlg-cookie" aria-labelledby="dk-h"><form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><input type="hidden" name="action" value="cookiekit_cookie_add"><?php wp_nonce_field( 'cookiekit_cookie_add' ); ?><div class="ck-dlg__b"><h2 id="dk-h"><?php esc_html_e( 'Çerez ekle', 'cookiekit' ); ?></h2>
		<?php
		$rows = array(
			array( 'k-ad', 'name', __( 'Çerez adı', 'cookiekit' ), 'ornek_cerez', 64, true ),
			array( 'k-sg', 'provider', __( 'Sağlayıcı', 'cookiekit' ), __( 'Bu site', 'cookiekit' ), 80, false ),
			array( 'k-am', 'purpose', __( 'Amaç', 'cookiekit' ), __( 'Ne için kullanılıyor?', 'cookiekit' ), 200, false ),
			array( 'k-su', 'duration', __( 'Süre', 'cookiekit' ), __( '1 yıl', 'cookiekit' ), 40, false ),
		);
		echo '<div class="ck-field" style="grid-template-columns:1fr;border:0;padding-top:0"><div class="ck-field__l"><label for="k-cat">' . esc_html__( 'Kategori', 'cookiekit' ) . '</label></div><div class="ck-field__c"><select class="ck-select" id="k-cat" name="cat"><option value="necessary">' . esc_html__( 'Zorunlu', 'cookiekit' ) . '</option><option value="analytics">' . esc_html__( 'Analiz', 'cookiekit' ) . '</option><option value="ads">' . esc_html__( 'Reklam', 'cookiekit' ) . '</option></select></div></div>';
		foreach ( $rows as $r ) {
			echo '<div class="ck-field" style="grid-template-columns:1fr;border:0;padding-top:0"><div class="ck-field__l"><label for="' . esc_attr( $r[0] ) . '">' . esc_html( $r[2] ) . '</label></div><div class="ck-field__c"><input class="ck-input" id="' . esc_attr( $r[0] ) . '" name="' . esc_attr( $r[1] ) . '" placeholder="' . esc_attr( $r[3] ) . '" maxlength="' . (int) $r[4] . '"' . ( $r[5] ? ' required' : '' ) . '></div></div>';
		}
		?>
	</div><div class="ck-dlg__f"><button type="button" class="ck-btn" data-close><?php esc_html_e( 'Vazgeç', 'cookiekit' ); ?></button><button type="submit" class="ck-btn ck-btn--pri"><?php esc_html_e( 'Çerezi ekle', 'cookiekit' ); ?></button></div></form></dialog>
		<?php
	}
}
