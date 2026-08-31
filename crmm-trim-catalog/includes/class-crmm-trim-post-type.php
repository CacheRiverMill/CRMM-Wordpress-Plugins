<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Post_Type {
	public const POST_TYPE = 'crmm_trim_profile';

	public static function register(): void {
		$labels = array(
			'name'                  => __( 'Trim Profiles', 'crmm-trim-catalog' ),
			'singular_name'         => __( 'Trim Profile', 'crmm-trim-catalog' ),
			'add_new_item'          => __( 'Add Trim Profile', 'crmm-trim-catalog' ),
			'edit_item'             => __( 'Edit Trim Profile', 'crmm-trim-catalog' ),
			'new_item'              => __( 'New Trim Profile', 'crmm-trim-catalog' ),
			'view_item'             => __( 'View Trim Profile', 'crmm-trim-catalog' ),
			'search_items'          => __( 'Search Trim Profiles', 'crmm-trim-catalog' ),
			'not_found'             => __( 'No trim profiles found.', 'crmm-trim-catalog' ),
			'not_found_in_trash'    => __( 'No trim profiles found in Trash.', 'crmm-trim-catalog' ),
			'all_items'             => __( 'All Trim Profiles', 'crmm-trim-catalog' ),
			'archives'              => __( 'Trim Profile Archives', 'crmm-trim-catalog' ),
			'featured_image'        => __( 'Primary Profile Image', 'crmm-trim-catalog' ),
			'set_featured_image'    => __( 'Set primary profile image', 'crmm-trim-catalog' ),
			'remove_featured_image' => __( 'Remove primary profile image', 'crmm-trim-catalog' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'show_in_rest'        => true,
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'trim-profiles' ),
				'menu_icon'           => 'dashicons-screenoptions',
				'menu_position'       => 21,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ),
				'exclude_from_search' => false,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'capability_type'     => array( 'crmm_trim_profile', 'crmm_trim_profiles' ),
				'map_meta_cap'        => true,
			),
		);
	}
}
