<?php
/**
 * Uninstall: deliberately conservative. The share register is the club's
 * statutory record — it is NEVER dropped automatically. Only transient
 * operational options are removed; tables and user meta stay until the
 * club removes them knowingly.
 *
 * @package FanOwnershipPlatform
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'prx3_kill_switches' );
delete_option( 'prx3_mod_queue' );
delete_option( 'prx3_chat_slow' );
delete_option( 'prx3_badge_queue' );
// prx3_settings, prx3_gift_codes, prx3_verify_codes, sequences, and all
// custom tables are retained: they carry legal and financial records.
