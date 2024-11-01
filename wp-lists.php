<?php
/*
Plugin Name: List Manager
Plugin URI: http://www.navidazimi.com/projects/wp-lists/
Description: This plugin facilitates list management capabilities directly from the administrative console. It also provides a custom list tag to embed lists inside posts and pages. There are also several API methods available for direct manipulation.
Author: Navid Azimi
Author URI: http://www.navidazimi.com
Version: 1.8
*/

/*******************************************************************************
 *
 * This is the plugin core functionality. You generally do not want to change
 * anything you are unfamiliar with.
 *
 *******************************************************************************/

/*
 * This function embeds the Lists submenu under the Manage tab.
 */
function wplists_admin_menu($content) {
	global $submenu;
	$submenu['edit.php'][35] = array(__('Lists'), 8, 'edit-lists.php');
}

/*
 * This function adds javascript functionality to launch a new window.
 */
function wplists_add_head() {
	if( (!strstr($_SERVER['PHP_SELF'], 'post.php') && !strstr($_SERVER['PHP_SELF'], 'page-new.php')) || $_GET["action"] == 'editcomment' )
		return 0;

	echo <<<END
		<script language="JavaScript" type="text/javascript">
		<!--
			function wplists_popup()
			{
				if( !window.focus ) return true;

				var w = 400; var h = 400;
				var top = (screen.availHeight) ? (screen.availHeight-h) / 2 : 50;
				var left = (screen.availWidth) ? (screen.availWidth-w) / 2 : 50;

				window.open('edit-lists.php?action=popup', 'wplistspopup', "width=" + w + ",height=" + h + ",top=" + top + ",left=" + left + ",scrollbars=yes");
				return false;
			}
		//-->
		</script>
END;
}

/*
 * This function adds the "embed list" button to the quicktags menu.
 */
function wplists_add_button()
{
	if( (!strstr($_SERVER['PHP_SELF'], 'post.php') && !strstr($_SERVER['PHP_SELF'], 'page-new.php')) || $_GET["action"] == 'editcomment' )
		return 0;

	echo <<<END
		<script language="JavaScript" type="text/javascript">
		<!--
			document.getElementById("quicktags").innerHTML += "<input type=\"button\" class=\"ed_button\" id=\"ed_list\" value=\"Embed List\" onclick=\"return wplists_popup();\" />";
		//-->
		</script>
END;
}

/*
 * This is a wrapper function which initiates the callback for the list embedding.
 */
function wplists_embed_callback( $content )
{
	return preg_replace_callback("|<list id=[\"']?([0-9]+)[\"']?( +(\w+)=[\"']?([^\"'/]+)[\"' ]?)* />|", 'wplists_embed', $content);
}

/*'
 * This function handles the embedded list inside posts and pages, unwrapping the
 * <list id="#" /> tag into a fully expanded HTML list.
 */
function wplists_embed( $matches )
{
	// default values
	$before = "<li>";
	$after = "</li>";
	$showlinks = true;
	$showchecked = true;
	$showupdate = false;
	$attribute = "";
	$format = "";
	$gmt = false;

	// 0th and 1st element always exist
	$id = $matches[1];
	$size = preg_match_all("| +(\w+)=[\"']?([^\"'/]+)[\"' ]?|", $matches[0], $attributes);

	// any subsequent elements are in pairs of name/value
	for( $i = 1; $i <= $size; $i++ )
	{
		$name = strtolower($attributes[1][$i]);
		$value = $attributes[2][$i];

		if( strlen($name) < 1 ) continue;

		switch( $name )
		{
			case "odd":
				$oddclass = $value;
				break;
			case "even":
				$evenclass = $value;
				break;
			case "showlinks":
				if( $value == "false" || $value == "no" ) $showlinks = false;
				else $showlinks = true;
				break;
			case "showchecked":
				if( $value == "false" || $value == "no" ) $showchecked = false;
				else $showchecked = true;
				break;
			case "showupdate":
				if( $value == "false" || $value == "no" ) $showupdate = false;
				else $showupdate = true;
				break;
			case "attribute":
				switch( $value )
				{
					case "lastmodified":
					case "lastupdated":
						$attribute = "lastupdated";
						break;
					case "count":
						$attribute = "count";
						break;
					case "countchecked":
						$attribute = "countchecked";
						break;
				}
				break;
			case "format":
				if( strlen($value) != 0 ) $format = $value;
				else $format = "";
				break;
			case "gmt":
				if( $value == "false" || $value == "no" ) $gmt = false;
				else $gmt = true;
				break;
		}
	}

	switch( $attribute )
	{
		case "lastupdated":		return wplists_last_modified($id, $format, $gmt, false);
		case "count":			return wplists_count_total($id, false);
		case "countchecked":	return wplists_count_checked($id, false);
		default:				return wplists_print_by_id($id, $before, $after, $showlinks, $showchecked, $oddclass, $evenclass, $showupdate, false);
	}
}

add_action('admin_menu', 'wplists_admin_menu');
add_action('admin_head', 'wplists_add_head');
add_action('admin_footer', 'wplists_add_button');
add_filter('the_content', 'wplists_embed_callback', 1);

/*******************************************************************************
 *
 * These are the table names which will be created in your WordPress database.
 * I generally do not recommend that you change these, but they are global values
 * so you can.
 *
 *******************************************************************************/

$WP_LISTS = $table_prefix ."lists";
$WP_LISTS_ITEMS = $table_prefix ."lists_items";

/*******************************************************************************
 *
 * The following includes a list of core API methods which may be used as you
 * wish throughout your website. If you are looking to embed a list inside a post
 * or page, you should simply use <list id="#" /> inside your lists, or use the
 * convienient "embed lists" button added to your quicktags. You can add more
 * hooks as you wish to the API but if you do make any improvements, please let
 * me know so I incorpirate them into the official release: wp-lists@navidazimi.com
 *
 * Please do not actually modify the current API methods as they are often times
 * actually used by the plugin core itself. If you wish to extend its functionality
 * please create additional functions and methods. Thanks.
 *
 *******************************************************************************/

/*
 * This function is a core API method used by the plugin itself. Please do not modify.
 */
function wplists_print_by_id( $list_id, $before = "<li>", $after = "</li>", $showlinks = true, $showchecked = true, $oddclass = "", $evenclass = "", $showupdate = false, $echo = true )
{
	if( strlen($list_id) == 0 )	return;

	global $wpdb, $WP_LISTS_ITEMS;
	$items = $wpdb->get_results("SELECT * FROM $WP_LISTS_ITEMS WHERE item_status = 'public' AND list_id = $list_id ORDER BY id");

	$i = 1;
	foreach( $items AS $item )
	{
		$name = $item->item_name;
		$url = $item->item_url;
		$checked = $item->item_checked;

		if( $showchecked && $checked == "yes" )
		{
			$name = "<del>$name</del>";
		}

		if( $showlinks && strlen($url) > 0 )
		{
			$name = "<a href=\"$url\">$name</a>";
		}

		if( $i % 2 == 0 )
		{
			if( strlen($evenclass) > 0 )
			{
				$before_temp = str_replace(">", " class=\"$evenclass\">", $before);
			}
		}
		else
		{
			if( strlen($oddclass) > 0 )
			{
				$before_temp = str_replace(">", " class=\"$oddclass\">", $before);
			}
		}

		if( strlen($before_temp) > 0 )
		{
			$text .= "$before_temp". $name ."$after";
		}
		else
		{
			$text .= "$before". $name ."$after";
		}

		$before_temp = "";
		$i++;
	}

	if( $echo ) print $text;
	return $text;
}

/*
 * This function facilitates expert users who would like to be able to handle
 * and manipulate the entire list themselves. This function returns an array
 * of objects pertaining to the list specified.
 */
function wplists_return_list_by_id( $list_id )
{
	if( strlen($list_id) == 0 )	return;

	global $wpdb, $WP_LISTS_ITEMS;
	$items = $wpdb->get_results("SELECT * FROM $WP_LISTS_ITEMS WHERE item_status = 'public' AND list_id = $list_id ORDER BY id");
	return $items;
}

/*
 * This function should be used to output a list of lists. To hide a list, simply mark its status as private
 * in the administrative console. This is the function that should be used in your sidebar.
 */
function wplists_get_lists( $before = "<li>", $after = "</li>", $linkurl = true, $orderby = "id" )
{
	switch( $orderby )
	{
		case "name":	$orderby = "post_title"; 	break;
		default:		$orderby = "id";
	}

	global $wpdb, $WP_LISTS;
	$lists = $wpdb->get_results("SELECT * FROM $WP_LISTS WHERE post_status = 'public' ORDER BY $orderby");
	foreach( $lists AS $list )
	{
		$name = $list->post_title;
		if( $linkurl )
		{
			$list_url = $list->list_url;
			if( strlen($list_url) > 0 )
			{
				$name = "<a href=\"$list_url\">$name</a>";
			}
		}
		print "$before". $name ."$after";
	}
}

/*
 * This function returns the date/time last modified for any given list. The $format parameter
 * offers functionality similar to the PHP date() method. For more info: http://www.php.net/date
 */
function wplists_last_modified( $list_id, $format = "", $gmt = false, $echo = true )
{
	if( strlen($list_id) == 0 )	return;
	if( $format == "" )
	{
		// if no format is assigned, lets take the WP default
		$format = get_settings('date_format') ." ". get_settings('time_format');
	}

	global $wpdb, $WP_LISTS;
	$items = $wpdb->get_results("SELECT post_date, post_date_gmt FROM $WP_LISTS WHERE id = $list_id");
	if( $gmt )
	{
		$last_modified = mysql2date($format, $items[0]->post_date_gmt);
	}
	else
	{
		$last_modified = mysql2date($format, $items[0]->post_date);
	}
	if( $echo ) print $last_modified;
	return $last_modified;
}

/*
 * This function returns the number of items in a given list.
 */
function wplists_count_total( $list_id, $echo = true )
{
	if( strlen($list_id) == 0 )	return;

	global $wpdb, $WP_LISTS_ITEMS;
	$items = $wpdb->get_results("SELECT COUNT(list_id) AS total_count FROM $WP_LISTS_ITEMS WHERE item_status = 'public' AND list_id = $list_id");
	if( $echo )
	{
		print $items[0]->checked_count;
	}
	return $items[0]->total_count;
}

/*
 * This function returns the number of checked items in a given list.
 */
function wplists_count_checked( $list_id, $echo = true )
{
	if( strlen($list_id) == 0 )	return;
	global $wpdb, $WP_LISTS_ITEMS;
	$items = $wpdb->get_results("SELECT COUNT(list_id) AS checked_count FROM $WP_LISTS_ITEMS WHERE item_status = 'public' AND item_checked = 'yes' AND list_id = $list_id");
	if( $echo )
	{
		print $items[0]->checked_count;
	}
	return $items[0]->checked_count;
}

?>