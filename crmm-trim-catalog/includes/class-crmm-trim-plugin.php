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
		add_action( 'init', array( 'CRMM_Trim_Post_Type', 'register' ) );
		add_action( 'init', array( 'CRMM_Trim_Taxonomies', 'register' ) );
		add_action( 'init', array( 'CRMM_Trim_Taxonomies', 'seed_terms' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'acf_notice' ) );
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_dxf_uploads' ) );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'check_dxf_filetype' ), 10, 5 );

		CRMM_Trim_Fields::boot();
		CRMM_Trim_Admin::boot();
	}

	public static function acf_notice(): void {
		if ( function_exists( 'acf_add_local_field_group' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'CRMM Trim Catalog requires Advanced Custom Fields. Activate ACF to edit catalog and shop-record fields.', 'crmm-trim-catalog' ) . '</p></div>';
	}

	public static function allow_dxf_uploads( array $mimes ): array {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['dxf'] = 'application/dxf';
		}

		return $mimes;
	}

	public static function check_dxf_filetype( array $data, string $file, string $filename, array $mimes, string|false $real_mime ): array {
		if ( current_user_can( 'manage_options' ) && 'dxf' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			$data['ext']             = 'dxf';
			$data['type']            = 'application/dxf';
			$data['proper_filename'] = $filename;
		}

		return $data;
	}

	public static function activate(): void {
		CRMM_Trim_Post_Type::register();
		CRMM_Trim_Taxonomies::register();
		CRMM_Trim_Taxonomies::seed_terms();
		CRMM_Trim_Number_Registry::install();

		flush_rewrite_rules();
	}
}
