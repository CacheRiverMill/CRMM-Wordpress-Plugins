<?php

defined( 'ABSPATH' ) || exit;

final class CRMM_Trim_Audit {
	private const META_KEY = '_crmm_audit_log';
	private const MAX_EVENTS = 100;

	public static function record( int $post_id, string $event, array $context = array() ): void {
		if ( CRMM_Trim_Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$events = get_post_meta( $post_id, self::META_KEY, true );
		$events = is_array( $events ) ? $events : array();
		$events[] = array(
			'event'    => sanitize_key( $event ),
			'user_id'  => get_current_user_id(),
			'timestamp' => current_time( 'mysql', true ),
			'context'  => self::sanitize_context( $context ),
		);

		if ( count( $events ) > self::MAX_EVENTS ) {
			$events = array_slice( $events, -self::MAX_EVENTS );
		}

		update_post_meta( $post_id, self::META_KEY, $events );
	}

	private static function sanitize_context( array $context ): array {
		$sanitized = array();
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_scalar( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $sanitized;
	}
}
