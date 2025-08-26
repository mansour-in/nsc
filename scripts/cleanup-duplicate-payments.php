<?php
/**
 * Cleanup script for duplicate nsc_payments rows.
 *
 * Removes older payment rows keeping the most recent record per user.
 *
 * Usage:
 *   wp eval-file scripts/cleanup-duplicate-payments.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit( "This script must be run from WP-CLI.\n" );
}

global $wpdb;
$table = "{$wpdb->prefix}nsc_payments";

$deleted = $wpdb->query( "
    DELETE p1 FROM $table p1
    INNER JOIN $table p2
        ON p1.user_id = p2.user_id
        AND p1.payment_id < p2.payment_id
" );

WP_CLI::success( sprintf( 'Removed %d duplicate payment rows.', $deleted ) );

