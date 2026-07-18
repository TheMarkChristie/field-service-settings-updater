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

delete_option( 'fop_kill_switches' );
delete_option( 'fop_mod_queue' );
delete_option( 'fop_chat_slow' );
delete_option( 'fop_badge_queue' );
// fop_settings, fop_gift_codes, fop_verify_codes, sequences, and all
// custom tables are retained: they carry legal and financial records.
