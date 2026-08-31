<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Notifications {
	public const OPTION = 'crmm_trim_notification_settings';

	public static function boot(): void {
		add_action( 'crmm_trim_number_assigned', array( __CLASS__, 'number_assigned' ), 10, 5 );
	}

	public static function defaults(): array {
		return array(
			'enabled'        => 1,
			'recipients'     => sanitize_email( (string) get_option( 'admin_email' ) ),
			'subject_prefix' => '[CRMM Trim Catalog]',
		);
	}

	public static function settings(): array {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function sanitize_settings( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$emails = preg_split( '/[\s,;]+/', (string) ( $input['recipients'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
		$emails = array_values( array_unique( array_filter( array_map( 'sanitize_email', $emails ) ) ) );

		return array(
			'enabled'        => empty( $input['enabled'] ) ? 0 : 1,
			'recipients'     => implode( ', ', $emails ),
			'subject_prefix' => sanitize_text_field( (string) ( $input['subject_prefix'] ?? '' ) ),
		);
	}

	public static function number_assigned( int $post_id, string $part_number, string $category_code, string $subtype_code, int $assigned_by ): void {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$recipients = preg_split( '/[\s,;]+/', (string) $settings['recipients'], -1, PREG_SPLIT_NO_EMPTY );
		$recipients = array_values( array_unique( array_filter( array_map( 'sanitize_email', $recipients ) ) ) );
		if ( ! $recipients ) {
			return;
		}

		$user = get_userdata( $assigned_by );
		$category = CRMM_Trim_Taxonomies::category_term_by_code( $category_code );
		$subtype  = CRMM_Trim_Taxonomies::subtype_term_by_code( $subtype_code );
		$prefix   = trim( (string) $settings['subject_prefix'] );
		$subject  = trim( $prefix . ' ' . sprintf( __( 'New part number %s', 'crmm-trim-catalog' ), $part_number ) );
		$body     = implode(
			"\n",
			array(
				sprintf( __( 'A new permanent trim-profile part number was assigned: %s', 'crmm-trim-catalog' ), $part_number ),
				'',
				sprintf( __( 'Profile: %s (ID %d)', 'crmm-trim-catalog' ), get_the_title( $post_id ) ?: __( '(untitled)', 'crmm-trim-catalog' ), $post_id ),
				sprintf( __( 'Category: %s', 'crmm-trim-catalog' ), $category ? $category->name : $category_code ),
				sprintf( __( 'Profile type: %s', 'crmm-trim-catalog' ), $subtype ? $subtype->name : $subtype_code ),
				sprintf( __( 'Assigned by: %s', 'crmm-trim-catalog' ), $user ? $user->display_name : __( 'Unknown user', 'crmm-trim-catalog' ) ),
				sprintf( __( 'Assigned at: %s UTC', 'crmm-trim-catalog' ), current_time( 'mysql', true ) ),
				sprintf( __( 'Edit profile: %s', 'crmm-trim-catalog' ), get_edit_post_link( $post_id, 'raw' ) ),
			)
		);

		$sent = wp_mail( $recipients, $subject, $body );
		CRMM_Trim_Audit::record(
			$post_id,
			'number_notification',
			array( 'sent' => $sent, 'recipient_count' => count( $recipients ), 'part_number' => $part_number )
		);

		if ( ! $sent ) {
			set_transient( 'crmm_trim_mail_failed_' . $assigned_by, $part_number, 5 * MINUTE_IN_SECONDS );
		}
	}

	public static function send_test( int $user_id ): bool {
		$settings = self::settings();
		$recipients = preg_split( '/[\s,;]+/', (string) $settings['recipients'], -1, PREG_SPLIT_NO_EMPTY );
		$recipients = array_values( array_unique( array_filter( array_map( 'sanitize_email', $recipients ) ) ) );
		if ( ! $recipients ) {
			return false;
		}
		$prefix = trim( (string) $settings['subject_prefix'] );
		return wp_mail(
			$recipients,
			trim( $prefix . ' ' . __( 'Test notification', 'crmm-trim-catalog' ) ),
			sprintf( __( 'Trim Catalog notification delivery was tested by user ID %d.', 'crmm-trim-catalog' ), $user_id )
		);
	}
}
