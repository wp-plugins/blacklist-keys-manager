<?php
/*
Plugin Name: Blacklist keys manager
Plugin URI: http://elearn.jp/wpman/column/blacklist-keys-manager.html
Description: This plugin manages a comment blacklist.
Author: tmatsuur
Version: 1.0.0
Author URI: http://12net.jp/
*/

/*
 Copyright (C) 2013 tmatsuur (Email: takenori dot matsuura at 12net dot jp)
This program is licensed under the GNU GPL Version 2.
*/
require_once dirname( __FILE__ ).'/config.php';

$plugin_blacklist_keys_manager = new blacklist_keys_manager();

class blacklist_keys_manager {
	const PROPERTIES_NAME = 'blacklist-keys-manager-properties';
	var $properties;

	function __construct() {
		load_plugin_textdomain( BLACKLIST_KEYS_MANAGER_DOMAIN, false, plugin_basename( dirname( __FILE__ ) ).'/languages' );
		register_activation_hook( __FILE__ , array( &$this , 'register_activation' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		if ( isset( $_GET['page'] ) && $_GET['page'] == self::PROPERTIES_NAME )
			add_action( 'admin_head', array( &$this, 'admin_head' ) );

		$this->properties = get_option( BLACKLIST_KEYS_MANAGER_PROPERTIES );	// this plugin orignal keys
		if ( !isset( $this->properties['auto_extract'] ) ) $this->properties['auto_extract'] = false;
		if ( !isset( $this->properties['extract_url'] ) ) $this->properties['extract_url'] = 'full';
		if ( isset( $this->properties['auto_extract'] ) && $this->properties['auto_extract'] )
			add_action( 'spammed_comment', array( &$this, 'spammed_comment' ) );
		if ( isset( $this->properties['spam_max_links'] ) && $this->properties['spam_max_links'] )
			add_filter( 'pre_comment_approved' , array( &$this, 'extend_comment_approved' ), 10, 2 );
	}
	function register_activation() {
		blacklist_keys_manager_update_version();
	}
	function admin_init() {
	}
	function admin_menu() {
		add_options_page(
			__( 'Blacklist keys manager', BLACKLIST_KEYS_MANAGER_DOMAIN ),
			__( 'Comment Blacklist' ), 9, self::PROPERTIES_NAME, array( &$this, 'properties' ) );
	}
	function admin_head() {
		wp_enqueue_script( 'jquery-ui-sortable' );
		$this->admin_css();
	}
	function admin_css() {
?>
<style type="text/css" media="screen">
/* clearfix */
.clearfix:after {
	content: ".";
	display: block;
	height: 0;
	clear: both;
	visibility: hidden;
}

.clearfix {display: inline-block;}

/* Hides from IE-mac \*/
* html .clearfix {height: 1%;}
.clearfix {display: block;}
/* End hide from IE-mac */

.drag-frame {
	border: 3px dashed #CCCCCC;
	padding: 4px;
	overflow: auto;
	max-height: 20.8em;
	margin-bottom: 0.75em;
}
.drag-frame:hover {
	border: 3px dashed #CCCCFF;
}
.drag-frame ul {
	margin: 0 0 1.5em 0;
}
.drag-frame ul li {
	margin: 1px;
	border: 1px solid #CCCCCC;
	padding: 0px 4px 0px 4px;
	height: 1.8em;
/*	width: 15em;*/
	float: left;
	background-color: #FFFFFF;
	cursor: pointer;
	border-radius: 3px;
	-webkit-border-radius: 3px;
	-moz-border-radius: 3px;
}
.drag-frame ul li.ui-sortable-helper {
	background-color: #FFFFFF;
}
.drag-frame ul li.ui-state-highlight {
	background-color: #F8F8F8;
	width: 8em;
	cursor: auto;
}
.drag-frame ul li.ui-state-placeholder {
	border: 1px dashed #CCCCCC;
	background-color: #FCFCFC;
	width: 8em;
}
.form-table {
	margin-top: 1.5em;
	margin-bottom: 1em;
}
.form-table th {
	width: 10.5em;
}
.form-table td {
/*	padding: 5px 10px 5px 10px;*/
	min-width: 10.5em;
}
.form-table caption {
	text-align: left;
	font-size: 1.33em;
	font-weight: bold;
}
.form-table h3 {
	margin: 0 0 0.5em 0;
	white-space: nowrap;
}
#delete_candidate {
	font-weight: normal;
}
.form-table td p {
	margin: 0 0.25em 0 0.25em;
	line-height: 1.5em;
}
.edit-frame {
	border: 3px dotted #278ab7;
	padding: 10px;
	text-align: center;
	width: 52em;
	margin-left: auto;
	margin-right: auto;
}
#key_name {
	width: 15em;
}
</style>
<?php
	}
	function keys( $values ) {
		$keys = explode( "\n", trim( $values ) );
		foreach ( array_unique( $keys ) as $i=>$value ) {
			if ( ( $value = trim( $value ) ) == '' )
				unset( $keys[$i] );
			else
				$keys[$i] = $value;
		}
		return $keys;
	}
	function spammed_comment( $comment_id ) {
		$_blacklist = $this->keys( get_option( 'blacklist_keys' ) );
		$_moderation = $this->keys( get_option( 'moderation_keys' ) );
		$_graylist = $this->keys( $this->properties['graylist'] );
		$_whitelist = $this->keys( $this->properties['whitelist'] );
		$updated_black = array();
		$updated_gray = array();
		$c = get_comment( $comment_id );
		if ( preg_match_all( '/(https?:\/\/)([\.0-9a-zA-Z\-]+)([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%]*)/u', $c->comment_content, $_matched ) ) {
			$keys = ( $this->properties['extract_url'] == 'domain' )? $_matched[2]: $_matched[0];
			foreach ( $keys as $key ) {
				if ( !in_array( $key, $_blacklist ) && !in_array( $key, $_whitelist ) && !in_array( $key, $_moderation ) ) {
					foreach ( $_blacklist as $bi=>$_black ) {
						if ( strpos( $_black, $key ) !== false ) {
							// ブラックリストの単語が見つかったキーを含んでいたら
							$_blacklist[$bi] = $key;
							$updated_black[] = $key;
							if ( !in_array( $_black, $_graylist ) )
								$updated_gray[] = $_black;
							break;
						} else if ( strpos( $key, $_black ) !== false ) {
							// 見つかったキーの中にブラックリストの単語があったら
							if ( !in_array( $key, $_graylist ) )
								$updated_gray[] = $key;
							break;
						}
					}
					if ( !in_array( $key, $_graylist ) && !in_array( $key, $updated_gray ) && !in_array( $key, $updated_black ) ) {
						// 見つかったキーがブラックリストになかったら
						$_blacklist[] = $key;
						$updated_black[] = $key;
					}
				}
			}
		}
		if ( count( $updated_black ) > 0 ) {
			asort( $_blacklist );
			update_option( 'blacklist_keys', implode( "\n", array_unique( $_blacklist ) ) );
		}
		if ( count( $updated_gray ) > 0 ) {
			$this->properties['graylist'] = implode( "\n", array_unique( array_merge( $_graylist, $updated_gray ) ) );
			update_option( BLACKLIST_KEYS_MANAGER_PROPERTIES, $this->properties );
		}
	}
	function extend_comment_approved( $approved, $commentdata ) {
		if ( isset( $this->properties['spam_max_links'] ) && $this->properties['spam_max_links'] &&
			$approved == 0 &&
			preg_match_all( '/(https?:\/\/)([\.0-9a-zA-Z\-]+)([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%]*)/u', $commentdata['comment_content'], $_matched ) ) {
			// コメント中にURLが見つかったら
			if (count( $_matched[0] ) >= $this->properties['max_links'] )
				$approved = 'spam';
		}
		return $approved;
	}
	function properties() {
		$message = '';
		if ( isset( $_POST['properties'] ) ) {
			update_option( 'blacklist_keys', $_POST['properties']['blacklist_keys'] );
			update_option( 'moderation_keys', $_POST['properties']['moderation_keys'] );
			$this->properties['graylist'] = $_POST['properties']['graylist_keys'];
			$this->properties['whitelist'] = $_POST['properties']['whitelist_keys'];
			$this->properties['extract_url'] = $_POST['properties']['extract_url'];
			$this->properties['auto_extract'] = isset( $_POST['properties']['auto_extract'] );
			$this->properties['spam_max_links'] = isset( $_POST['properties']['spam_max_links'] );
			$this->properties['max_links'] = intval( $_POST['properties']['max_links'] );
			update_option( BLACKLIST_KEYS_MANAGER_PROPERTIES, $this->properties );
			if ( isset( $_POST['delete'] ) ) {
				$deleted = explode( "\n", trim( $_POST['properties']['delete_keys'] ) );
				$message = sprintf( _n( 'Item permanently deleted.', '%s items permanently deleted.', count( $deleted ) ), number_format_i18n( count( $deleted ) ) );
			} else if ( isset( $_POST['extract'] ) ) {
				$_blacklist = $this->keys( get_option( 'blacklist_keys' ) );
				$_moderation = $this->keys( get_option( 'moderation_keys' ) );
				$_graylist = $this->keys( $this->properties['graylist'] );
				$_whitelist = $this->keys( $this->properties['whitelist'] );
				$updated_black = array();
				$updated_gray = array();
				// $this->properties['extract_url'] full or domain
				foreach ( get_comments( array( 'status' => 'spam' ) ) as $c ) {
					if ( preg_match_all( '/(https?:\/\/)([\.0-9a-zA-Z\-]+)([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%]*)/u', $c->comment_content, $_matched ) ) {
						$keys = ( $this->properties['extract_url'] == 'domain' )? $_matched[2]: $_matched[0];
						foreach ( $keys as $key ) {
							if ( !in_array( $key, $_blacklist ) && !in_array( $key, $_whitelist ) && !in_array( $key, $_moderation ) ) {
								foreach ( $_blacklist as $bi=>$_black ) {
									if ( strpos( $_black, $key ) !== false ) {
										// ブラックリストの単語が見つかったキーを含んでいたら
										$_blacklist[$bi] = $key;
										$updated_black[] = $key;
										if ( !in_array( $_black, $_graylist ) )
											$updated_gray[] = $_black;
										break;
									} else if ( strpos( $key, $_black ) !== false ) {
										// 見つかったキーの中にブラックリストの単語があったら
										if ( !in_array( $key, $_graylist ) )
											$updated_gray[] = $key;
										break;
									}
								}
								if ( !in_array( $key, $_graylist ) && !in_array( $key, $updated_gray ) && !in_array( $key, $updated_black ) ) {
									// 見つかったキーがブラックリストになかったら
									$_blacklist[] = $key;
									$updated_black[] = $key;
								}
							}
						}
					}
				}
				if ( count( $updated_black ) > 0 ) {
					asort( $_blacklist );
					update_option( 'blacklist_keys', implode( "\n", array_unique( $_blacklist ) ) );
				}
				if ( count( $updated_gray ) > 0 ) {
					$this->properties['graylist'] = implode( "\n", array_unique( array_merge( $_graylist, $updated_gray ) ) );
					update_option( BLACKLIST_KEYS_MANAGER_PROPERTIES, $this->properties );
				}
				$message = sprintf( _n( '%s key updated.', '%s keys updated.', count( $updated_black ), BLACKLIST_KEYS_MANAGER_DOMAIN ), number_format_i18n( count( $updated_black ) ) );
			} else
				$message = __( 'Settings saved.' );
		}
		$_graylist = $this->keys( $this->properties['graylist'] );
		asort( $_graylist );
		$_whitelist = $this->keys( $this->properties['whitelist'] );
		asort( $_whitelist );
		$_moderation = $this->keys( get_option( 'moderation_keys' ) );
		asort( $_moderation );
		$_blacklist = $this->keys( get_option( 'blacklist_keys' ) );
		asort( $_blacklist );
		$key_no = 1;
		$nkeys = count( $_graylist )+count( $_whitelist )+count( $_moderation )+count( $_blacklist )+1001;
?>
<div id="<?php echo self::PROPERTIES_NAME; ?>" class="wrap">
<div id="icon-options-general" class="icon32"><br /></div>
<h2><?php _e('Comment Blacklist'); ?></h2>
<?php if ( $message != '' ) { global $wp_version; ?>
<?php if ( version_compare( $wp_version, '3.5', '>=' ) ) { ?>
<div id="setting-error-settings_updated" class="updated settings-error"><p><strong><?php echo $message; ?></strong></p></div>
<?php } else { ?>
<div id="message" class="update fade"><p><?php echo $message; ?></p></div>
<?php } } ?>

<form method="post" id="form-properties">
<table class="form-table">
<caption><?php _e('Comment Blacklist'); ?> <?php _e( 'Settings' ) ;?></caption>
<tr valign="top">
<td colspan="3"><?php _e( 'A comment blacklist is managed. Please drag and drop a key suitably. Moreover, if a key is double-clicked, it can edit.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></td>
</tr>
<tr valign="top">
<td><h3 class="title"><?php _e('Comment Blacklist'); ?> <span id="nblacklist">(<?php echo count( $_blacklist ); ?>)</span></h3><div class="drag-frame"><ul id="blacklist_keys_list" class="drag-drop">
<?php if ( count( $_blacklist ) > 0 ) { foreach ( $_blacklist as $value ) { ?>
<li id="key<?php echo $key_no; $key_no++ ?>" class="key"><?php _e( $value ); ?></li>
<?php } } else { ?>
<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>
<?php } ?>
</ul></div>
<p class="description"><?php _e( 'When a comment contains any of these words in its content, name, URL, e-mail, or IP, it will be marked as spam.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></p></td>
<td><h3 class="title"><?php _e('Comment Moderation'); ?> <span id="nmoderation">(<?php echo count( $_moderation ); ?>)</span></h3><div class="drag-frame"><ul id="moderation_keys_list" class="drag-drop">
<?php if ( count( $_moderation ) > 0 ) { foreach ( $_moderation as $value ) { ?>
<li id="key<?php echo $key_no; $key_no++ ?>" class="key"><?php _e( $value ); ?></li>
<?php } } else { ?>
<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>
<?php } ?>
</ul></div>
<p class="description"><?php _e( 'When a comment contains any of these words in its content, name, URL, e-mail, or IP, it will be held in the <a href="edit-comments.php?comment_status=moderated">moderation queue</a>.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></p></td>
<td><h3 class="title"><?php _e( 'Trash' ); ?> <span id="ngraylist">(<?php echo count( $_graylist ); ?>)</span></h3><div class="drag-frame"><ul id="graylist_keys_list" class="drag-drop">
<?php if ( count( $_graylist ) > 0 ) { foreach ( $_graylist as $value ) { ?>
<li id="key<?php echo $key_no; $key_no++ ?>" class="key"><?php _e( $value ); ?></li>
<?php } } else { ?>
<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>
<?php } ?>
</ul></div>
<p class="description"><?php _e( 'The key which became unnecessary should move here.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></p></td>
</tr>
<tr valign="top">
<td colspan="3">
<div class="edit-frame">
<label id="edit_label" for="key_name"><?php _e( 'Edit key:',BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></label> <input type="text" name="key_name" id="key_name" value="" />&nbsp;
<button id="add_blacklist" class="button"><?php _e( 'Add to a blacklist',BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></button>&nbsp;
<button id="add_whitelist" class="button"><?php _e( 'Add to a whitelist',BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></button>&nbsp;
<button id="update_key" class="button" disabled="disabled"><?php _e( 'Update',BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></button>
<input type="hidden" id="last_key_no" value="<?php echo $nkeys; ?>" />
<input type="hidden" id="edit_key_no" value="0" />
</div>
</td>
</tr>
</table>

<table class="form-table">
<caption><?php _e( 'Extract blacklist',BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></caption>
<tr valign="top">
<th colspan="2"><?php _e( 'Extract blacklist keys from spam comments.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th>
<td rowspan="3"><h3><?php _e( 'Comment Whitelist', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?> <span id="nwhitelist">(<?php echo count( $_whitelist ); ?>)</span></h3><div class="drag-frame"><ul id="whitelist_keys_list" class="drag-drop">
<?php if ( count( $_whitelist ) > 0 ) { foreach ( $_whitelist as $value ) { ?>
<li id="key<?php echo $key_no; $key_no++ ?>" class="key"><?php _e( $value ); ?></li>
<?php } } else { ?>
<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>
<?php } ?>
</ul></div>
<p class="description"><?php _e( 'These keys are not added to a blacklist.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></p></td>
</tr>
<tr valign="top">
<th scope="row"><?php _e( 'Select extract pattern', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th>
<td>
<fieldset>
<label><input type="radio" name="properties[extract_url]" value="full" <?php checked( $this->properties['extract_url'] == 'full' ); ?> />&nbsp;<?php _e( 'Full path', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></label>&nbsp;(<?php _e('e.g.', BLACKLIST_KEYS_MANAGER_DOMAIN); ?> http://localhost/hello.html)<br />
<label><input type="radio" name="properties[extract_url]" value="domain" <?php checked( $this->properties['extract_url'] == 'domain' ); ?> />&nbsp;<?php _e( 'Domain name or IP address', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></label>&nbsp;(<?php _e('e.g.', BLACKLIST_KEYS_MANAGER_DOMAIN); ?> localhost)<br />
</fieldset>
</td>
</tr>
<tr valign="top">
<th scope="row"><?php _e( 'Auto extract', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th>
<td><label><input type="checkbox" name="properties[auto_extract]" value="1" <?php checked( $this->properties['auto_extract'] ); ?> />&nbsp;<?php _e( 'When marked a comment as spam.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></label></td>
</tr>
</table>

<table class="form-table">
<caption><?php _e( 'Other', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></caption>
<tr valign="middle">
<th><?php _e( 'Max links', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th>
<td><input type="checkbox" name="properties[spam_max_links]" value="1" <?php checked( $this->properties['spam_max_links'] ); ?> />&nbsp;<?php printf( __( 'When a comment contains %s or more links, it marks as spam.', BLACKLIST_KEYS_MANAGER_DOMAIN ), '<input name="properties[max_links]" type="number" step="1" min="1" id="max_links" value="' . esc_attr( isset( $this->properties['max_links'] )? $this->properties['max_links']: get_option('comment_max_links')+1 ) . '" class="small-text" />' ); ?></td>
</tr>
<tr valign="top">
<td colspan="2">
<input type="hidden" id="blacklist_keys_value" name="properties[blacklist_keys]" value="" />
<input type="hidden" id="moderation_keys_value" name="properties[moderation_keys]" value="" />
<input type="hidden" id="graylist_keys_value" name="properties[graylist_keys]" value="" />
<input type="hidden" id="whitelist_keys_value" name="properties[whitelist_keys]" value="" />
<input type="hidden" id="delete_keys_value" name="properties[delete_keys]" value="" />
<input type="submit" id="properties_submit" name="submit" value="<?php esc_attr_e( 'Save Changes' ); ?>" class="button-primary" />&nbsp;
<input type="submit" id="extract_blacklist" name="extract" value="<?php esc_attr_e( 'Extract now', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>" class="button" />&nbsp;
<input type="submit" id="delete_candidate" name="delete" value="<?php esc_attr_e( 'Empty Trash' ); ?>" class="button" />
</td>
</tr>
</table>
</form><!-- #form-extract -->

</div><!-- .wrap -->

<script type="text/javascript">
( function($) {
	jQuery.event.add( window, 'load', function() {
		$( '#delete_candidate' ).click( function () {
			if ( $( '#graylist_keys_list li.key' ).length > 0  && confirm( '<?php _e( 'Does it really delete?', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>' ) ) {
				$( '#graylist_keys_list li.key' ).each( function () {
					$( '#delete_keys_value' ).val( $( '#delete_keys_value' ).val()+$(this).text()+"\n" );
					$(this).removeClass( 'key' );
				} )
				return true;
			}
			return false;
		} );
		$( '#form-properties' ).submit( function () {
			$( '#blacklist_keys_list li.key' ).each( function () {
				$( '#blacklist_keys_value' ).val( $( '#blacklist_keys_value' ).val()+$(this).text()+"\n" );
			} );
			$( '#moderation_keys_list li.key' ).each( function () {
				$( '#moderation_keys_value' ).val( $( '#moderation_keys_value' ).val()+$(this).text()+"\n" );
			} );
			$( '#graylist_keys_list li.key' ).each( function () {
				$( '#graylist_keys_value' ).val( $( '#graylist_keys_value' ).val()+$(this).text()+"\n" );
			} );
			$( '#whitelist_keys_list li.key' ).each( function () {
				$( '#whitelist_keys_value' ).val( $( '#whitelist_keys_value' ).val()+$(this).text()+"\n" );
			} );
		} );
		$( '.drag-frame ul' ).sortable( {
			items: 'li:not(.ui-state-disabled)',
			connectWith: 'ul.drag-drop',
			placeholder: 'ui-state-highlight',
			over: function ( event, ui ) {
				$(this).find( '.ui-state-highlight' ).show();
				if ( $(this).attr( 'id' ) != ui.sender.attr( 'id' ) && ui.sender.find( 'li' ).length == 1 && ui.sender.find( 'li.ui-state-placeholder' ).length == 0 )
					ui.sender.append( '<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>' );
				else
					$(this).find( '.ui-state-placeholder' ).hide();
			},
			out: function ( event, ui ) {
				if ( $(this).attr( 'id' ) != ui.sender.attr( 'id' ) )
					$(this).find( '.ui-state-highlight' ).hide();
				$(this).find( '.ui-state-placeholder' ).show();
			},
			stop: function ( event, ui ) {
				var to = ui.item.parent( 'ul' );
				droped_items = 0;
				to.find( 'li' ).each( function () {
					if ( typeof $(this).attr( 'id' ) != 'undefined' ) {
						droped_items++;
					}
				} );
				if ( typeof ui.item.attr( 'id' ) != 'undefined' && $(this).find( 'li' ).length > 1 )
					$(this).find( '.ui-state-placeholder' ).remove();
			},
			receive: function ( event, ui ) {
				if ( typeof ui.item.attr( 'id' ) == 'undefined' )
					ui.sender.append( ui.item );	// placeholderは移動しない
				else
					$(this).find( '.ui-state-placeholder' ).remove();
			},
			update: function ( event, ui ) {
				$(this).parent( 'div' ).prev().find( 'span' ).text( '('+$(this).find( 'li.key' ).length+')' );
			}
		} ).disableSelection().addClass( 'clearfix' );
		$( '.drag-frame ul li' ).dblclick( function () {
			if ( $(this).html() != '' && $(this).html() != '&nbsp;' ) {
				$( '#key_name' ).val( $(this).html() );
				$( '#edit_key_no' ).val( $(this).attr( 'id' ).replace( 'key', '' ) );
				$( '#update_key' ).removeAttr( 'disabled' );
			}
		} );
		$( '#add_blacklist,#add_whitelist,#update_key' ).click( function () {
			var new_key = $.trim( $( '#key_name' ).val() );
			if ( new_key != '' ) {
				var already = null;
				$( '.drag-drop li.key' ).each( function () {
					if ( new_key == $(this).text() )
						already = $(this);
				} );
				if ( already != null ) {
					alert( '<?php _e( 'The key is already registered.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>' );
					return false;
				}
				if ( $(this).attr( 'id' ) == 'update_key' ) {
					$( '#key'+$( '#edit_key_no' ).val() ).text( new_key );
				} else {
					last_key_no = $( '#last_key_no' ).val();
					new_item = '<li id="key'+last_key_no+'" class="key">'+new_key+'</li>';
					if ( $(this).attr( 'id' ) == 'add_blacklist' )
						$( '#blacklist_keys_list' ).append( new_item );
					else if ( $(this).attr( 'id' ) == 'add_whitelist' )
						$( '#whitelist_keys_list' ).append( new_item );
					$( '#last_key_no' ).val( parseInt( last_key_no )+1 );
				}
				$( '#key_name' ).val( '' );
				$( '#edit_key_no' ).val( '0' );
//				$( '#update_key' ).attr( 'disabled', 'disabled' ); disabledにするとsubmitしないので
//				return false;
			} else
				return false;
		} );
	} );
} )( jQuery );
</script>
<?php
	}
}
?>