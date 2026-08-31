<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Capabilities {
	public const ASSIGN_NUMBERS       = 'assign_trim_numbers';
	public const VERIFY_PROFILES      = 'verify_trim_profiles';
	public const APPROVE_PROFILES     = 'approve_trim_profiles';
	public const IMPORT_PROFILES      = 'import_trim_profiles';
	public const MANAGE_TAXONOMIES    = 'manage_trim_taxonomies';
	public const MANAGE_NOTIFICATIONS = 'manage_trim_notifications';
	public const VIEW_INTERNAL         = 'view_trim_internal_records';

	private const POST_CAPABILITIES = array(
		'edit_crmm_trim_profile',
		'read_crmm_trim_profile',
		'delete_crmm_trim_profile',
		'edit_crmm_trim_profiles',
		'edit_others_crmm_trim_profiles',
		'publish_crmm_trim_profiles',
		'read_private_crmm_trim_profiles',
		'delete_crmm_trim_profiles',
		'delete_private_crmm_trim_profiles',
		'delete_published_crmm_trim_profiles',
		'delete_others_crmm_trim_profiles',
		'edit_private_crmm_trim_profiles',
		'edit_published_crmm_trim_profiles',
		'create_crmm_trim_profiles',
	);

	private const WORKFLOW_CAPABILITIES = array(
		self::ASSIGN_NUMBERS,
		self::VERIFY_PROFILES,
		self::APPROVE_PROFILES,
		self::IMPORT_PROFILES,
		self::MANAGE_TAXONOMIES,
		self::MANAGE_NOTIFICATIONS,
		self::VIEW_INTERNAL,
	);

	public static function install(): void {
		$manager_caps = array_fill_keys( array_merge( self::POST_CAPABILITIES, self::WORKFLOW_CAPABILITIES ), true );
		$manager_caps['read'] = true;
		$manager_caps['upload_files'] = true;

		$role = get_role( 'crmm_trim_catalog_manager' );
		if ( ! $role ) {
			$role = add_role(
				'crmm_trim_catalog_manager',
				__( 'Trim Catalog Manager', 'crmm-trim-catalog' ),
				$manager_caps
			);
		}

		if ( $role ) {
			foreach ( $manager_caps as $capability => $grant ) {
				$role->add_cap( $capability, $grant );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( array_keys( $manager_caps ) as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
	}

	public static function taxonomy_capabilities(): array {
		return array(
			'manage_terms' => self::MANAGE_TAXONOMIES,
			'edit_terms'   => self::MANAGE_TAXONOMIES,
			'delete_terms' => self::MANAGE_TAXONOMIES,
			'assign_terms' => 'edit_crmm_trim_profiles',
		);
	}
}
