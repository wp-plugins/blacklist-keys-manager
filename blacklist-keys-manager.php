<?php
/*
Plugin Name: Blacklist keys manager
Plugin URI: http://elearn.jp/wpman/column/blacklist-keys-manager.html
Description: This plugin manages a comment blacklist.
Author: tmatsuur
Version: 1.2.0
Author URI: http://12net.jp/
*/

/*
 Copyright (C) 2013-2015 tmatsuur (Email: takenori dot matsuura at 12net dot jp)
This program is licensed under the GNU GPL Version 2.
*/
require_once dirname( __FILE__ ).'/config.php';

$plugin_blacklist_keys_manager = new blacklist_keys_manager();

class blacklist_keys_manager {
	const PROPERTIES_NAME = 'blacklist-keys-manager-properties';
	const SEPS = "/[\s\.,:;!?&\|\"()\[\]{}<>\/=\-_　。、．，！？：；“”‘’（）［］｛｝【】「」『』]+/u";
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
			add_filter( 'pre_comment_approved' , array( &$this, 'maxlinks_comment_approved' ), 10, 2 );
		if ( isset( $this->properties['exblacklist'] ) && ( $mod_keys = trim( $this->properties['exblacklist'] ) ) != '' ) {
			if ( isset( $this->properties['use_extended_blacklist'] ) && $this->properties['use_extended_blacklist'] )
				add_filter( 'pre_comment_approved', array( &$this, 'exblacklist_comment_approved' ), 10, 2 );
			add_action( 'wp_ajax_test_exblacklist', array( &$this, 'test_exblacklist' ) );
		}
		add_action( 'wp_ajax_upload_white_list_file_for_comment', array( &$this, 'upload_white_list_files' ) );
		if ( !isset( $this->properties['freq_appearance'] ) ) {
			$this->properties['whitelist'] =
				"about\n"."activities\n"."addition\n"."admin\n"."after\n"."approach\n"."areas\n"."athletics\n".
				"balance\n"."banking\n"."beach\n"."beauty\n"."because\n"."blockquote\n"."boots\n"."brand\n"."business\n"."button\n"."buying\n"."black\n".
				"cache\n"."center\n"."chose\n"."chrome\n"."close\n"."clubs\n"."coach\n"."collect\n"."collection\n"."common\n"."company\n"."conference\n"."country\n"."coupon\n"."cosme\n"."cover\n"."camera\n"."calendar\n"."canada\n"."classic\n".
				"delivers\n"."didn't\n"."don't\n"."dinner\n"."dinners\n"."discount\n"."disney\n"."dollar\n"."dollars\n"."drive\n"."driver\n".
				"eating\n".
				"fashion\n"."fifty\n"."files\n"."finance\n"."flights\n"."first\n"."follow\n"."formed\n"."fresh\n".
				"going\n"."great\n"."ground\n".
				"happy\n"."helmet\n"."hotel\n"."hotels\n"."house\n"."however\n"."hunter\n".
				"image\n"."images\n"."include\n"."includes\n".
				"jacket\n"."japan\n"."japanese\n".
				"nofollow\n".
				"latest\n"."limited\n"."london\n"."lunch\n".
				"manage\n"."meals\n"."media\n"."model\n"."mystery\n".
				"night\n"."north\n"."nothing\n".
				"offers\n"."offline\n"."online\n"."order\n"."other\n"."outlet\n".
				"pants\n"."paris\n"."perform\n"."perhaps\n"."period\n"."personal\n"."personally\n"."place\n"."places\n"."pointer\n"."police\n"."possible\n"."prepared\n"."presents\n"."product\n"."promotion\n"."protect\n"."protector\n"."purpose\n"."puzzle\n"."pizza\n".
				"reality\n"."really\n"."report\n"."reports\n".
				"sales\n"."scripts\n"."searching\n"."secret\n"."shaft\n"."share\n"."shock\n"."shoes\n"."shopping\n"."shops\n"."simply\n"."specials\n"."sport\n"."staff\n"."strong\n"."steel\n"."store\n"."style\n"."super\n"."swatch\n"."swiss\n"."symbol\n"."system\n".
				"there\n"."these\n"."thing\n"."things\n"."throughout\n"."track\n"."technique\n"."thought\n"."tiger\n"."times\n".
				"unknown\n"."upload\n"."usually\n"."utility\n".
				"where\n"."which\n"."watch\n"."we've\n"."would\n".
				"years\n";
			$this->properties['freq_appearance'] = 1;
			$this->properties['freq_appearance_times'] = 10;
			update_option( BLACKLIST_KEYS_MANAGER_PROPERTIES, $this->properties );
		}
	}
	function register_activation() {
		blacklist_keys_manager_update_version();
	}
	function admin_init() {
	}
	function admin_menu() {
		add_options_page(
			__( 'Blacklist keys manager', BLACKLIST_KEYS_MANAGER_DOMAIN ),
			__( 'Comment Blacklist' ), 'manage_options', self::PROPERTIES_NAME, array( &$this, 'properties' ) );
	}
	function admin_head() {
		wp_enqueue_script( 'jquery-ui-sortable' );
		$this->admin_css();
	}
	function admin_css() {
?>
<style type="text/css" media="screen">
/* clearfix */
.clearfix:after {content: ".";display: block;height: 0;clear: both;	visibility: hidden;}
.clearfix {display: inline-block;}
/* Hides from IE-mac \*/
* html .clearfix {height: 1%;}
.clearfix {display: block;}
/* End hide from IE-mac */

tr[valign='top'] th, tr[valign='top'] td { vertical-align: top; }

.drag-frame {
	border: 3px dashed #CCCCCC;
	padding: 3px;
	overflow: auto;
	max-height: 17.7em;
	margin-bottom: 0.75em;
}
.drag-frame:hover {
	border: 3px dashed #CCCCFF;
}
.drag-frame ul {
	margin: 0 1.5em 1.5em 0;
}
.drag-frame ul li {
	margin: 1px;
	border: 1px solid #CCCCCC;
	padding: 0px 4px 0px 4px;
	line-height: 1.5em;
	height: 1.5em;
/*	width: 15em;*/
	float: left;
	background-color: #FFFFFF;
	cursor: pointer;
	border-radius: 3px;
	-webkit-border-radius: 3px;
	-moz-border-radius: 3px;
	white-space: nowrap;
	overflow: hidden;
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
.drag-frame ul#exblacklist_keys_list li.key,
.drag-frame ul#blacklist_keys_list li.key {
	border: 1px solid #888888;
	background-color: #444444;
	color: #FFFFFF;
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
.form-table h3 input {
	font-weight: normal;
	line-height: 20px !important;
	height: 22px !important;
	padding: 0 4px 1px !important;
}
.form-table h3 span.key-count {
	font-weight: normal;
	font-size: 80%;
}
.form-table td p {
	margin: 0 0.25em 0 0.25em;
	line-height: 1.5em;
}
.edit-frame {
	border: 3px dotted #278ab7;
	padding: 10px;
	text-align: center;
	width: 42em;
	margin-left: auto;
	margin-right: auto;
}
#key_name {
	width: 22em;
}
#test_exblacklist_result {
	padding-left: 10px;
	width: 98%;
}
#test_exblacklist_result table caption {
	text-align: left;
	padding: 0.25em;
}
#test_exblacklist_result table th {
	text-align: center;
}
.wait_icon {
	cursor: wait;
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
				$key = trim( $key );
				if ( !in_array( $key, $_blacklist ) && !in_array( $key, $_whitelist ) && !in_array( $key, $_moderation ) && !$this->in_exblacklist( $key ) ) {
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
	function maxlinks_comment_approved( $approved, $commentdata ) {
		if ( $approved == 0 &&
			isset( $this->properties['spam_max_links'] ) && $this->properties['spam_max_links'] &&
			preg_match_all( '/(https?:\/\/)([\.0-9a-zA-Z\-]+)([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%]*)/u', $commentdata['comment_content'], $_matched ) ) {
			// コメント中にURLが見つかったら
			if (count( $_matched[0] ) >= $this->properties['max_links'] )
				$approved = 'spam';
		}
		return $approved;
	}
	function exblacklist_comment_approved( $approved, $commentdata ) {
		if ( $approved == 0 ) {
			extract( $commentdata, EXTR_SKIP );
			$_whitelist = $this->keys( $this->properties['whitelist'] );
			foreach ( (array)explode( "\n", trim( $this->properties['exblacklist'] ) ) as $word ) {
				$word = trim( $word );
				if ( empty( $word ) )
					continue;

				$pattern = "#$word#i";
				if (	( preg_match( $pattern, $comment_author, $matched ) && !in_array( $matched[0], $_whitelist ) )
						|| ( preg_match( $pattern, $comment_author_email, $matched ) && !in_array( $matched[0], $_whitelist ) )
						|| ( preg_match( $pattern, $comment_author_url, $matched ) && !in_array( $matched[0], $_whitelist ) )
						|| ( preg_match_all( $pattern, $comment_content, $matched ) && count( array_intersect( $matched[0], $_whitelist ) ) == 0 )
						|| ( preg_match( $pattern, $comment_author_IP, $matched ) && !in_array( $matched[0], $_whitelist ) )
						|| ( preg_match( $pattern, $comment_agent, $matched ) && !in_array( $matched[0], $_whitelist ) ) )
					return 'spam';
			}
		}
		return $approved;
	}
	function test_exblacklist() {
		if ( !check_ajax_referer( self::PROPERTIES_NAME.'@test@'.$this->_nonce_suffix() ) ) {
			wp_send_json_error();
			die();
		}

		if ( isset( $_POST['exblacklist'] ) )
			$exblacklist = explode( "\n", stripslashes_deep( $_POST['exblacklist'] ) );
		else
			$exblacklist = explode( "\n", $this->properties['exblacklist'] );
		$_whitelist = $this->keys( $this->properties['whitelist'] );
		$hits = array();
		add_filter( 'comments_clauses', array( &$this, 'all_comments_clauses' ) );
		$comments = get_comments();
		foreach ( $comments as $c ) {
			foreach ( $exblacklist as $word ) {
				$word = trim( $word );
				if ( empty( $word ) )
					continue;

				$pattern = "#$word#i";
				if ( !isset( $hits[$word] ) ) {
					$hits[$word]['count'] = 0;
					$hits[$word]['word'] = array();
				}
				if (( preg_match( $pattern, $c->comment_author, $matched ) && !in_array( $matched[0], $_whitelist ) )
					|| ( preg_match( $pattern, $c->comment_author_email, $matched ) && !in_array( $matched[0], $_whitelist ) )
					|| ( preg_match( $pattern, $c->comment_author_url, $matched ) && !in_array( $matched[0], $_whitelist ) )
					|| ( preg_match_all( $pattern, $c->comment_content, $matched ) && count( array_intersect( $matched[0], $_whitelist ) ) == 0 )
					|| ( preg_match( $pattern, $c->comment_author_IP, $matched ) && !in_array( $matched[0], $_whitelist ) )
					|| ( preg_match( $pattern, $c->comment_agent, $matched ) && !in_array( $matched[0], $_whitelist ) ) ) {
					$hits[$word]['count']++;
					$hits[$word]['word'] = array_unique( array_merge( $hits[$word]['word'], (array)$matched[0] ) );
				}
			}
		}
		$response = array( 'ncomments'=>count( $comments ), 'result'=>$hits );
		wp_send_json_success( $response );
		die();
	}
	function in_exblacklist( $key ) {
		if ( !empty( $key ) && isset( $this->properties['use_extended_blacklist'] ) && $this->properties['use_extended_blacklist'] &&
			isset( $this->properties['exblacklist'] ) && ( $ex_keys = trim( $this->properties['exblacklist'] ) ) != '' ) {
			$exblacklist = explode( "\n", $ex_keys );
			foreach ( $exblacklist as $word ) {
				$word = trim( $word );
				if ( empty( $word ) )
					continue;
				if ( preg_match( "#$word#i", $key ) ) {
					return true;
				}
			}
		}
		return false;
	}
	function all_comments_clauses( $clauses ) {
		if ( isset( $clauses['where'] ) && $clauses['where'] == "( comment_approved = '0' OR comment_approved = '1' )" )
			$clauses['where'] = 'comment_approved IS NOT NULL';
		return $clauses;
	}
	function upload_white_list_files() {
		if ( check_ajax_referer( self::PROPERTIES_NAME.'@upload@'.$this->_nonce_suffix(), false, false ) ) {
			$whitelist = array();
			foreach ( $_FILES as $i=>$file ) {
				$fp = fopen( $file['tmp_name'], 'r' );
				if ( $fp !== false ) {
					while ( ( $line = fgets( $fp, 4096 ) ) !== false) {
						if ( !empty( $line ) )
							$whitelist = array_merge( $whitelist, preg_split( self::SEPS, $line ) );
					}
					fclose( $fp );
				}
			}
			$whitelist = array_unique( array_filter( $whitelist ) );
			sort( $whitelist );
			if ( count( $whitelist ) > 0 ) {
				$this->properties['whitelist'] = implode( "\n", $whitelist );
				update_option( BLACKLIST_KEYS_MANAGER_PROPERTIES, $this->properties );
			}
			wp_send_json_success( $whitelist );
		} else
			wp_send_json_error();
		die();
	}
	// private nonce functions
	private function _nonce_suffix() {
		return date_i18n( 'His TO', filemtime( __FILE__ ) );
	}
	private function _extract_words( $text ) {
		$words = preg_split( self::SEPS, $text );
		return $words;
	}
	private function _strlen( $text ) {
		if ( function_exists( 'mb_strlen' ) )
			return mb_strlen( $text );
		else
			return strlen( $text );
	}
	function properties() {
		if ( !current_user_can( 'manage_options' ) )
			return;	// Except an administrator

		$message = '';
		if ( isset( $_POST['properties'] ) ) {
			check_admin_referer( self::PROPERTIES_NAME.$this->_nonce_suffix() );

			$_POST['properties'] = stripslashes_deep( $_POST['properties'] );
			$this->properties['graylist'] = $_POST['properties']['graylist_keys'];
			$this->properties['whitelist'] = $_POST['properties']['whitelist_keys'];
			$this->properties['extract_url'] = $_POST['properties']['extract_url'];
			$this->properties['auto_extract'] = isset( $_POST['properties']['auto_extract'] );
			$this->properties['spam_max_links'] = isset( $_POST['properties']['spam_max_links'] );
			$this->properties['max_links'] = intval( $_POST['properties']['max_links'] );
			$this->properties['use_extended_blacklist'] = isset( $_POST['properties']['use_extended_blacklist'] );
			$this->properties['exblacklist'] = $_POST['properties']['exblacklist_keys'];
			$this->properties['freq_appearance'] = isset( $_POST['properties']['freq_appearance'] )? 1: 0;
			$this->properties['freq_appearance_times'] = intval( $_POST['properties']['freq_appearance_times'] );
			$new_blacklist = explode( "\n", trim( $_POST['properties']['blacklist_keys'] ) );
			$new_glaylist = explode( "\n", trim( $this->properties['graylist'] ) );
			foreach ( $new_blacklist as $i=>$key ) {
				if ( $this->in_exblacklist( $key ) ) {
					unset( $new_blacklist[$i] );
					if ( !in_array( $key, $new_glaylist ) )
						$new_glaylist[] = $key;
				}
			}
			if ( isset( $_POST['empty_blacklist'] ) ) {
				$new_blacklist = array();
			}
			update_option( 'blacklist_keys', implode( "\n", $new_blacklist ) );
			if ( isset( $_POST['empty_moderation'] ) ) {
				$new_moderation = '';
			} else
				$new_moderation = $_POST['properties']['moderation_keys'];
			update_option( 'moderation_keys', $new_moderation );
			$this->properties['graylist'] = implode( "\n", $new_glaylist );
			if ( isset( $_POST['empty_whitelist'] ) ) {
				$this->properties['whitelist'] = '';
			}
			update_option( BLACKLIST_KEYS_MANAGER_PROPERTIES, $this->properties );
			if ( isset( $_POST['empty_candidate'] ) ) {
				$deleted = explode( "\n", trim( $_POST['properties']['delete_keys'] ) );
				$message = sprintf( _n( 'Item permanently deleted.', '%s items permanently deleted.', count( $deleted ), BLACKLIST_KEYS_MANAGER_DOMAIN ), number_format_i18n( count( $deleted ) ) );
			} else if ( isset( $_POST['extract'] ) ) {
				$_blacklist = $this->keys( get_option( 'blacklist_keys' ) );
				$_moderation = $this->keys( get_option( 'moderation_keys' ) );
				$_graylist = $this->keys( $this->properties['graylist'] );
				$_whitelist = $this->keys( $this->properties['whitelist'] );
				$updated_black = array();
				$updated_gray = array();
				$_words = array();
				$count_appearance = ( $this->properties['freq_appearance'] && $this->properties['freq_appearance_times'] > 0 );
				// $this->properties['extract_url'] full or domain
				foreach ( get_comments( array( 'status' => 'spam', 'number'=>1000 ) ) as $c ) {
					if ( preg_match_all( '/(https?:\/\/)([\.0-9a-zA-Z\-]+)([-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%]*)/u', $c->comment_content, $_matched ) ) {
						$keys = ( $this->properties['extract_url'] == 'domain' )? $_matched[2]: $_matched[0];
						foreach ( $keys as $key ) {
							$key = trim( $key );
							if ( !in_array( $key, $_blacklist ) && !in_array( $key, $_whitelist ) && !in_array( $key, $_moderation ) && !$this->in_exblacklist( $key ) ) {
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
					// 単語に分割して保持
					if ( $count_appearance ) {
						$_words = array_merge( $_words, $this->_extract_words( $c->comment_author.' '.$c->comment_content ) );
					}
				}

				if ( $count_appearance ) {
					// 出現頻度の高い単語
					$words = array( $_words );
					foreach ( $_words as $_word ) {
						if ( !empty( $_word ) ) {
							$_word = trim( strtolower( $_word ), '"`\'' );
							if ( !preg_match( '/^[0-9]+$/', $_word ) ) {
								if ( isset( $words[$_word] ) )
									$words[$_word]++;
								else
									$words[$_word] = 1;
							}
						}
					}
					if ( !empty( $this->properties['whitelist'] ) ) {
						$_whitelist = $this->keys( $this->properties['whitelist'] );
						asort( $_whitelist );
					} else
						$_whitelist = array();
					foreach ( $words as $key=>$count ) {
						if ( $count < $this->properties['freq_appearance_times'] || $this->_strlen( $key ) <= 4 ||
							in_array( strtolower( $key ), $_whitelist ) || in_array( $key, $_blacklist ) || in_array( $key, $_moderation ) || in_array( $key, $_graylist ) ||
							in_array( $key, $updated_gray ) || in_array( $key, $updated_black ) )
								unset( $words[$key] );
					}
					$words = array_keys( $words );
					$updated_black = array_merge( $updated_black, $words );
					$_blacklist = array_merge( $_blacklist, $words );
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
			} else if ( empty( $message ) )
				$message = __( 'Settings saved.' );
		}
		if ( !empty( $this->properties['graylist'] ) ) {
			$_graylist = $this->keys( $this->properties['graylist'] );
			asort( $_graylist );
		} else
			$_graylist = array();
		if ( !empty( $this->properties['whitelist'] ) ) {
			$_whitelist = $this->keys( $this->properties['whitelist'] );
			asort( $_whitelist );
		} else
			$_whitelist = array();
		$_moderation = array();
		$moderation_keys = get_option( 'moderation_keys' );
		if ( !empty( $moderation_keys ) ) {
			$_moderation = $this->keys( $moderation_keys );
			asort( $_moderation );
		}
		$_blacklist = $this->keys( get_option( 'blacklist_keys' ) );
		asort( $_blacklist );
		if ( !empty( $this->properties['exblacklist'] ) ) {
			$_exblacklist = $this->keys( $this->properties['exblacklist'] );
			asort( $_exblacklist );
		} else
			$_exblacklist = array();
		$key_no = 1;
		$nkeys = count( $_graylist )+count( $_whitelist )+count( $_moderation )+count( $_blacklist )+count( $_exblacklist )+1001;
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
<td><h3 class="title"><?php _e('Comment Blacklist'); ?> <span id="nblacklist" class="key-count">(<?php echo number_format( count( $_blacklist ) ); ?>)</span>&nbsp;<input type="submit" id="empty_blacklist" name="empty_blacklist" value="<?php esc_attr_e( 'Empty', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>" class="button" /></h3>
<div class="drag-frame"><ul id="blacklist_keys_list" class="drag-drop">
<?php if ( count( $_blacklist ) > 0 ) { foreach ( $_blacklist as $value ) { ?>
<li id="key<?php echo $key_no; $key_no++ ?>" class="key"><?php echo esc_html( $value ); ?></li>
<?php } } else { ?>
<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>
<?php } ?>
</ul></div>
<p class="description"><?php _e( 'When a comment contains any of these words in its content, name, URL, e-mail, or IP, it will be marked as spam.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></p></td>
<td><h3 class="title"><?php _e('Comment Moderation'); ?> <span id="nmoderation" class="key-count">(<?php echo number_format( count( $_moderation ) ); ?>)</span>&nbsp;<input type="submit" id="empty_moderation" name="empty_moderation" value="<?php esc_attr_e( 'Empty', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>" class="button" /></h3>
<div class="drag-frame"><ul id="moderation_keys_list" class="drag-drop">
<?php if ( count( $_moderation ) > 0 ) { foreach ( $_moderation as $value ) { ?>
<li id="key<?php echo $key_no; $key_no++ ?>" class="key"><?php echo esc_html( $value ); ?></li>
<?php } } else { ?>
<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>
<?php } ?>
</ul></div>
<p class="description"><?php _e( 'When a comment contains any of these words in its content, name, URL, e-mail, or IP, it will be held in the <a href="edit-comments.php?comment_status=moderated">moderation queue</a>.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></p></td>
<td><h3 class="title"><?php _e( 'Trash' ); ?> <span id="ngraylist" class="key-count">(<?php echo number_format( count( $_graylist ) ); ?>)</span>&nbsp;<input type="submit" id="empty_candidate" name="empty_candidate" value="<?php esc_attr_e( 'Empty', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>" class="button" /></h3>
<div class="drag-frame"><ul id="graylist_keys_list" class="drag-drop">
<?php if ( count( $_graylist ) > 0 ) { foreach ( $_graylist as $value ) { ?>
<li id="key<?php echo $key_no; $key_no++ ?>" class="key"><?php echo esc_html( $value ); ?></li>
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
<button id="update_key" class="button" disabled="disabled"><?php _e( 'Update',BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></button>&nbsp;
<div style="margin-top: 0.5em;">
<button id="add_blacklist" class="button"><?php _e( 'Add to a blacklist',BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></button>&nbsp;
<button id="add_exblacklist" class="button"><?php _e( 'Add to a extended blacklist',BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></button>&nbsp;
<button id="add_whitelist" class="button"><?php _e( 'Add to a whitelist',BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></button>&nbsp;
</div>
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
<td rowspan="3" style="width: 56%;"><h3><?php _e( 'Comment Whitelist', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?> <span id="nwhitelist" class="key-count">(<?php echo number_format( count( $_whitelist ) ); ?>)</span>&nbsp;<input type="submit" id="empty_whitelist" name="empty_whitelist" value="<?php esc_attr_e( 'Empty', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>" class="button" /></h3>
<div class="drag-frame" id="upload_whitelist_here"><ul id="whitelist_keys_list" class="drag-drop">
<?php if ( count( $_whitelist ) > 0 ) { foreach ( $_whitelist as $value ) { ?>
<li id="key<?php echo $key_no; $key_no++ ?>" class="key"><?php echo esc_html( $value ); ?></li>
<?php } } else { ?>
<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>
<?php } ?>
</ul></div>
<p class="description"><?php _e( 'These keys are not added to a blacklist. If you want to collectively update, please drop a file of white list in the frame.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></p></td>
</tr>
<tr valign="top">
<th scope="row"><?php _e( 'Select extract pattern', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th>
<td>
<fieldset>
<label><?php _e( 'Extract the links from the spam comments.', BLACKLIST_KEYS_MANAGER_DOMAIN); ?></label><br />
<label><input type="radio" name="properties[extract_url]" value="full" <?php checked( $this->properties['extract_url'] == 'full' ); ?> />&nbsp;<?php _e( 'Full path', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></label>&nbsp;(<?php _e( 'e.g.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?> http://localhost/hello.html)<br />
<label><input type="radio" name="properties[extract_url]" value="domain" <?php checked( $this->properties['extract_url'] == 'domain' ); ?> />&nbsp;<?php _e( 'Domain name or IP address', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></label>&nbsp;(<?php _e( 'e.g.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?> localhost)<br />
</fieldset>
<fieldset>
<label><input type="checkbox" name="properties[freq_appearance]" value="1" <?php checked( isset( $this->properties['freq_appearance'] ) && $this->properties['freq_appearance'] ); ?> />&nbsp;<?php
printf( __( 'Add words that appeared in spam comment more than %s times the blacklist.', BLACKLIST_KEYS_MANAGER_DOMAIN ), '<input name="properties[freq_appearance_times]" type="number" step="1" min="1" id="freq_appearance_times" value="' . esc_attr( isset( $this->properties['freq_appearance_times'] )? $this->properties['freq_appearance_times']: 5 ) . '" class="small-text" />' );
?></label><br />
</fieldset>
</td>
</tr>
<tr valign="top">
<th scope="row"><?php _e( 'Auto extract', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th>
<td><label><input type="checkbox" name="properties[auto_extract]" value="1" <?php checked( $this->properties['auto_extract'] ); ?> />&nbsp;<?php _e( 'Extract URL when marked a comment as spam.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></label></td>
</tr>
</table>

<table class="form-table">
<caption><?php _e( 'Other', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></caption>
<tr valign="middle">
<th><?php _e( 'Max links', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th>
<td colspan="2"><input type="checkbox" name="properties[spam_max_links]" value="1" <?php checked( isset( $this->properties['spam_max_links'] ) && $this->properties['spam_max_links'] ); ?> />&nbsp;<?php printf( __( 'When a comment contains %s or more links, it marks as spam.', BLACKLIST_KEYS_MANAGER_DOMAIN ), '<input name="properties[max_links]" type="number" step="1" min="1" id="max_links" value="' . esc_attr( isset( $this->properties['max_links'] )? $this->properties['max_links']: get_option( 'comment_max_links' )+1 ) . '" class="small-text" />' ); ?></td>
</tr>
<tr valign="top">
<th><?php _e( 'Extended blacklist', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th>
<td><input type="checkbox" name="properties[use_extended_blacklist]" id="use_extended_blacklist" value="1" <?php checked( isset( $this->properties['use_extended_blacklist'] ) && $this->properties['use_extended_blacklist'] ); ?> />&nbsp;<label for="use_extended_blacklist"><?php _e( 'Use an extended blacklist.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></label>
<p class="description"><?php _e( 'A regular expression can be used in an extended blacklist. However, when it matches a white list, it is not marked as spam.', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></p></td>
<td style="width: 56%;"><h3><?php _e( 'Extended blacklist', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?> <span id="nexblacklist" class="key-count">(<?php echo number_format( count( $_exblacklist ) ); ?>)</span></h3>
<div class="drag-frame"><ul id="exblacklist_keys_list" class="drag-drop">
<?php if ( count( $_exblacklist ) > 0 ) { foreach ( $_exblacklist as $value ) { ?>
<li id="key<?php echo $key_no; $key_no++ ?>" class="key"><?php echo esc_html( $value ); ?></li>
<?php } } else { ?>
<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>
<?php } ?>
</ul></div>
</td>
</tr>
<tr valign="top">
<td colspan="3">
<?php wp_nonce_field( self::PROPERTIES_NAME.$this->_nonce_suffix() ); ?>
<input type="hidden" id="blacklist_keys_value" name="properties[blacklist_keys]" value="" />
<input type="hidden" id="exblacklist_keys_value" name="properties[exblacklist_keys]" value="" />
<input type="hidden" id="moderation_keys_value" name="properties[moderation_keys]" value="" />
<input type="hidden" id="graylist_keys_value" name="properties[graylist_keys]" value="" />
<input type="hidden" id="whitelist_keys_value" name="properties[whitelist_keys]" value="" />
<input type="hidden" id="delete_keys_value" name="properties[delete_keys]" value="" />
<input type="submit" id="properties_submit" name="submit" value="<?php esc_attr_e( 'Save Changes' ); ?>" class="button-primary" />&nbsp;
<input type="submit" id="extract_blacklist" name="extract" value="<?php esc_attr_e( 'Now extract blacklist', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>" class="button" />&nbsp;
<input type="submit" id="download_whitelist" name="download_whitelist" value="<?php esc_attr_e( 'Download whitelist', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>" class="button" />&nbsp;
<input type="button" id="test_exblacklist" value="<?php esc_attr_e( 'Test an extended blacklist', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>" class="button" />
</td>
</tr>
</table>
</form><!-- #form-extract -->

</div><!-- .wrap -->

<script type="text/javascript">
( function($) {
	jQuery.event.add( window, 'load', function() {
		$( '#empty_blacklist' ).click( function () {
			if ( $( '#blacklist_keys_list li.key' ).length > 0  && confirm( '<?php _e( 'Does it really delete?', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>' ) ) {
				return true;
			}
			return false;
		} );
		$( '#empty_moderation' ).click( function () {
			if ( $( '#moderation_keys_list li.key' ).length > 0  && confirm( '<?php _e( 'Does it really delete?', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>' ) ) {
				return true;
			}
			return false;
		} );
		$( '#empty_whitelist' ).click( function () {
			if ( $( '#whitelist_keys_list li.key' ).length > 0  && confirm( '<?php _e( 'Does it really delete?', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>' ) ) {
				return true;
			}
			return false;
		} );
		$( '#empty_candidate' ).click( function () {
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
			$( '#exblacklist_keys_list li.key' ).each( function () {
				$( '#exblacklist_keys_value' ).val( $( '#exblacklist_keys_value' ).val()+$(this).text()+"\n" );
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
				if ( ui.sender ) {
					$(this).find( '.ui-state-highlight' ).show();
					if ( $(this).attr( 'id' ) != ui.sender.attr( 'id' ) && ui.sender.find( 'li' ).length == 1 && ui.sender.find( 'li.ui-state-placeholder' ).length == 0 )
						ui.sender.append( '<li class="ui-state-highlight ui-state-placeholder ui-state-disabled">&nbsp;</li>' );
					else
						$(this).find( '.ui-state-placeholder' ).hide();
				}
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
				edit_offset = $( '#edit_label' ).offset();
				$( 'body,html' ).animate( { scrollTop: ( edit_offset.top*2/3 )+'px' }, 500 );
			}
		} );
		$( '#add_blacklist,#add_exblacklist,#add_whitelist,#update_key' ).click( function () {
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
					else if ( $(this).attr( 'id' ) == 'add_exblacklist' ) {
						$( '#exblacklist_keys_list' ).append( new_item );
					}
					$( '#last_key_no' ).val( parseInt( last_key_no )+1 );
				}
				$( '#key_name' ).val( '' );
				$( '#edit_key_no' ).val( '0' );
//				$( '#update_key' ).attr( 'disabled', 'disabled' ); disabledにするとsubmitしないので
//				return false;
			} else
				return false;
		} );
		$( '#download_whitelist' ).click( function () {
			var whitelist = '';
			$( '#whitelist_keys_list li.key' ).each( function () { whitelist += $.trim( $(this).text() )+"\n"; } );
			if ( whitelist != '' ) {
				var now = new Date();
				var link = document.createElement( 'a' );
				link.href = window.URL.createObjectURL( new Blob( [whitelist] ) );
				link.download = "whitelist-"+
					now.getFullYear()+
					( "0"+( now.getMonth()+1 ) ).slice(-2) +
					( "0"+now.getDate() ).slice(-2) +
					"-"+
					( "0"+now.getHours() ).slice(-2) +
					( "0"+now.getMinutes() ).slice(-2) +
					( "0"+now.getSeconds() ).slice(-2)+".txt";
				link.click();
			}
			return false;
		} );
		$( "#upload_whitelist_here" ).bind( "dragenter", function ( event ) {
			$("#upload_whitelist_here" ).addClass( "drag" );
			event.preventDefault();event.stopPropagation();return false;
		} ).bind( "dragleave", function ( event ) {
			$("#upload_whitelist_here" ).removeClass( "drag" );
			event.preventDefault();event.stopPropagation();return false;
		} ).bind( "dragover", function ( event ) {
			event.preventDefault();event.stopPropagation();return false;
		} ).bind( "drop", function ( event ) {
			$( "#upload_whitelist_here" ).removeClass( "drag" );
			event.preventDefault();event.stopPropagation();

			$( "#upload_whitelist_here" ).addClass( 'wait_icon' );
			var fd = new FormData();
			fd.append( 'action', 'upload_white_list_file_for_comment' );
			fd.append( '_ajax_nonce', '<?php echo wp_create_nonce( self::PROPERTIES_NAME.'@upload@'.$this->_nonce_suffix() ); ?>' );
			for ( var i = 0; i < event.originalEvent.dataTransfer.files.length; i++ ) {
				fd.append( 'files', event.originalEvent.dataTransfer.files[i] );
			}
			$.ajax( {
				url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
				type: 'POST',
				data: fd,
				processData: false,
				contentType: false,
				cache: false,
				timeout: 60000,
				dataType: 'json',
				success: function ( response, textStatus, jqXHR ) {
					if ( response.success && response.data.length > 0 ) {
						$( '#whitelist_keys_list li' ).remove();
						$.each( response.data, function ( i, val ) {
							var last_key_no = $( '#last_key_no' ).val();
							new_item = '<li id="key'+last_key_no+'" class="key">'+val+'</li>';
							$( '#whitelist_keys_list' ).append( new_item );
							$( '#last_key_no' ).val( parseInt( last_key_no )+1 );
						} );
						$( '#nwhitelist' ).text( '('+response.data.length.toString().replace(/([0-9]+?)(?=(?:[0-9]{3})+$)/g , '$1,')+')' );
					}
				},
				error: function ( jqXHR, textStatus, errorThrow ) { console.log( jqXHR ); },
				complete: function ( jqXHR, textStatus ) {
					$( "#upload_whitelist_here" ).removeClass( 'wait_icon' );
				}
			} );
			return false;
		} );
		$( '#test_exblacklist' ).click( function () {
			var exblacklist = '';
			$( '#exblacklist_keys_list li.key' ).each( function () { exblacklist += $.trim( $(this).text() )+"\n"; } );
			if ( exblacklist != '' ) {
				var request_html = '<?php _e( 'During the test...', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?>';
				if ( $( '#test_exblacklist_result' ).length == 1 )
					$( '#test_exblacklist_result' ).html( request_html );
				else
					$( '#<?php echo self::PROPERTIES_NAME; ?>' ).append( '<div id="test_exblacklist_result">'+request_html+'</div>' );
				$.ajax( {
					type: 'POST',
					url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
					data: {
						"action": "test_exblacklist",
						"_ajax_nonce": "<?php echo wp_create_nonce( self::PROPERTIES_NAME.'@test@'.$this->_nonce_suffix() ); ?>",
						"exblacklist": exblacklist
					},
					dataType : 'json',
					timeout: 30000,
					success: function ( data ) {
						var result_html = '<table class="wp-list-table widefat"><caption><?php _e( 'Result of the test', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?> (<?php _e( 'Amount of comments:', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?> '+data.ncomments+')</caption><thead><tr><th><?php _e( 'Extended blacklist key', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th><th><?php _e( 'Matched comments', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th><th width="70%"><?php _e( 'Matched words', BLACKLIST_KEYS_MANAGER_DOMAIN ); ?></th></tr></thead>';
						for ( var key in data.result ) {
							if ( Array.isArray( data.result[key]['word'] ) && data.result[key]['word'].length > 0 )
								words = data.result[key]['word'].join( ' ' );
							else
								words = '&nbsp;';
							result_html += '<tr><td>'+key+'</td><td class="num">'+data.result[key]['count']+'</td><td>'+words+'</td></tr>';
						}
						result_html += '</table>';
						$( '#test_exblacklist_result' ).html( result_html );
						result_offset = $( '#test_exblacklist_result' ).offset();
						$( 'body,html' ).animate( { scrollTop: ( result_offset.top-30 )+'px' }, 500 );
					},
					error: function ( data, status ) {
						$( '#test_exblacklist_result' ).html( 'Sorry! '+status );
					}
				} );
			}
			return false;
		} );
	} );
} )( jQuery );
</script>
<?php
	}
}
?>