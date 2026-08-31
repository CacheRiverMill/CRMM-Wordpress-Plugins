<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Plugin {
	private static ?CRMM_Trim_Plugin $instance = null;

	public static function instance(): CRMM_Trim_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'init', array( 'CRMM_Trim_Post_Type', 'register' ) );
		add_action( 'init', array( 'CRMM_Trim_Taxonomies', 'register' ) );
		add_action( 'init', array( 'CRMM_Trim_Taxonomies', 'seed_terms' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'acf_notice' ) );
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_dxf_uploads' ) );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'check_dxf_filetype' ), 10, 5 );

		CRMM_Trim_Fields::boot();
		CRMM_Trim_Admin::boot();
		CRMM_Trim_Admin_List::boot();
		CRMM_Trim_Integrity::boot();
		CRMM_Trim_Readiness::boot();
		CRMM_Trim_Notifications::boot();
		CRMM_Trim_Settings::boot();
		CRMM_Trim_Block_Bindings::boot();
	}

	public static function maybe_upgrade(): void {
		if ( CRMM_TRIM_CATALOG_VERSION === get_option( 'crmm_trim_catalog_db_version' ) ) {
			return;
		}

		CRMM_Trim_Capabilities::install();
		CRMM_Trim_Number_Registry::install();
		if ( false === get_option( CRMM_Trim_Notifications::OPTION, false ) ) {
			add_option( CRMM_Trim_Notifications::OPTION, CRMM_Trim_Notifications::defaults(), '', false );
		}
	}

	public static function acf_notice(): void {
		if ( function_exists( 'acf_add_local_field_group' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'CRMM Trim Catalog requires Advanced Custom Fields. Activate ACF to edit catalog and shop-record fields.', 'crmm-trim-catalog' ) . '</p></div>';
	}

	public static function allow_dxf_uploads( array $mimes ): array {
		if ( current_user_can( 'upload_files' ) && current_user_can( 'edit_crmm_trim_profiles' ) ) {
			$mimes['dxf'] = 'application/dxf';
		}

		return $mimes;
	}

	public static function check_dxf_filetype( array $data, string $file, string $filename, ?array $mimes, string|false|null $real_mime ): array {
		if ( current_user_can( 'upload_files' ) && current_user_can( 'edit_crmm_trim_profiles' ) && 'dxf' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) && self::is_probable_dxf( $file ) ) {
			$data['ext']             = 'dxf';
			$data['type']            = 'application/dxf';
			$data['proper_filename'] = $filename;
		}

		return $data;
	}

	private static function is_probable_dxf( string $file ): bool {
		if ( ! is_readable( $file ) ) {
			return false;
		}
		$sample = file_get_contents( $file, false, null, 0, 4096 );
		if ( false === $sample || '' === $sample ) {
			return false;
		}
		if ( str_starts_with( $sample, "AutoCAD Binary DXF\r\n\x1a\0" ) ) {
			return true;
		}
		$sample = str_replace( array( "\r\n", "\r" ), "\n", $sample );
		return 1 === preg_match( '/^\s*0\s*\n\s*(SECTION|HEADER|EOF)\b/i', $sample );
	}

	public static function activate(): void {
		CRMM_Trim_Capabilities::install();
		CRMM_Trim_Post_Type::register();
		CRMM_Trim_Taxonomies::register();
		CRMM_Trim_Taxonomies::seed_terms();
		CRMM_Trim_Number_Registry::install();
		if ( false === get_option( CRMM_Trim_Notifications::OPTION, false ) ) {
			add_option( CRMM_Trim_Notifications::OPTION, CRMM_Trim_Notifications::defaults(), '', false );
		}

		flush_rewrite_rules();
	}
}
