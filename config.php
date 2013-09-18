<?php
define( 'BLACKLIST_KEYS_MANAGER_DOMAIN', 'blacklist-keys-manager' );
define( 'BLACKLIST_KEYS_MANAGER_DB_VERSION_NAME', 'blacklist-keys-manager-db-version' );
define( 'BLACKLIST_KEYS_MANAGER_DB_VERSION', '1.1.0' );
define( 'BLACKLIST_KEYS_MANAGER_PROPERTIES', 'blacklist-keys-manager-properties' );

function blacklist_keys_manager_update_version() {
	if ( get_option( BLACKLIST_KEYS_MANAGER_DB_VERSION_NAME ) != BLACKLIST_KEYS_MANAGER_DB_VERSION ) {
		update_option( BLACKLIST_KEYS_MANAGER_DB_VERSION_NAME, BLACKLIST_KEYS_MANAGER_DB_VERSION );
		return true;
	}
	return false;
}
?>