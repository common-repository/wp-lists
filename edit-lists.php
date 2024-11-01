<?php
/*
Author: Navid Azimi
Author URI: http://www.navidazimi.com
Description: This is the administrative user interface for the wp-lists plugin.

TODO / WISHLIST:
- add support for definition lists
- add support for regular tables
- add support for exporting lists
*/

require_once('admin.php');
$title = __('Lists');
$parent_file = 'edit.php';

// the following variables are being set to circumvent any global variable issues
$edit = $create = $save = $delete = false;
$action = $_REQUEST['action'];
$debug 	= $_REQUEST['debug'];

switch( $action )
{
	case "popup":	load_popup();			break;
	case "import":	load_popup_import();	break;
	case "export":	load_popup_export();	break;
	case "edit":	$edit = true;			break;
	case "create":	$create = true;			break;
	case "save":	$save = true;			break;
	case "delete":	$delete = true;			break;
	// no default case necessary
}

if( $debug == "true" )
{
	echo "<div style=\"display: none;\">";
	echo "<pre>";
	print_r( $GLOBALS );
	echo "</pre>";
	echo "</div>";
}

require_once('admin-header.php');
get_currentuserinfo();
?>

<script type="text/javascript">
<!--
	var keyStr = "ABCDEFGHIJKLMNOP" + "QRSTUVWXYZabcdef" + "ghijklmnopqrstuv" + "wxyz0123456789+/" + "=";

	/*
	 * The following is an implementation of base64 encoding and decoding used to disguise the notion
	 * that there are several fields associated to each value / text pair for each item in <select>. The code
	 * has been slightly modified from its original at: http://www.aardwulf.com/tutor/base64/base64.html
	 */
	function encode64( input )
	{
		var output = "";
		var chr1, chr2, chr3 = "";
		var enc1, enc2, enc3, enc4 = "";
		var i = 0;

		do {
			chr1 = input.charCodeAt(i++);
			chr2 = input.charCodeAt(i++);
			chr3 = input.charCodeAt(i++);
			enc1 = chr1 >> 2;
			enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
			enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
			enc4 = chr3 & 63;
			if( isNaN(chr2) ) enc3 = enc4 = 64;
			else if( isNaN(chr3) ) enc4 = 64;
			output = output +
				keyStr.charAt(enc1) +
				keyStr.charAt(enc2) +
				keyStr.charAt(enc3) +
				keyStr.charAt(enc4);
			chr1 = chr2 = chr3 = "";
			enc1 = enc2 = enc3 = enc4 = "";
		} while( i < input.length );

		return output;
	}

	/*
	 * This is the decoder function as described above.
	 */
	function decode64( input )
	{
		var output = "";
		var chr1, chr2, chr3 = "";
		var enc1, enc2, enc3, enc4 = "";
		var i = 0;

		// remove all characters that are not A-Z, a-z, 0-9, +, /, or =
		var base64test = /[^A-Za-z0-9\+\/\=]/g;
		if( base64test.exec(input) )
		{
			alert("There were invalid base64 characters in the input text.\n" +
					"Valid base64 characters are A-Z, a-z, 0-9, '+', '/', and '='\n" +
					"Expect errors in decoding.");
		}
		input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");
		do {
			enc1 = keyStr.indexOf(input.charAt(i++));
			enc2 = keyStr.indexOf(input.charAt(i++));
			enc3 = keyStr.indexOf(input.charAt(i++));
			enc4 = keyStr.indexOf(input.charAt(i++));

			chr1 = (enc1 << 2) | (enc2 >> 4);
			chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
			chr3 = ((enc3 & 3) << 6) | enc4;

			output = output + String.fromCharCode(chr1);

			if( enc3 != 64 ) output = output + String.fromCharCode(chr2);
			if( enc4 != 64 ) output = output + String.fromCharCode(chr3);

			chr1 = chr2 = chr3 = "";
			enc1 = enc2 = enc3 = enc4 = "";

		} while( i < input.length );

		return output;
	}

	/*
	 * This function encodes and returns the important variables for the .value attribute.
	 * Format: name|url|status|checked
	 */
	function get_value()
	{
		var form = document.itemform;
		var name = trim(form.itemname.value);
		var link = trim(form.itemurl.value);
		var status = "public";
		if( form.itemchecked.checked ) checked = "yes";
		else checked = "no";
		var temp = name + "|" + link + "|" + status + "|" + checked;
		return encode64(temp);
	}

	/*
	 * This function is used to add a new item to the list. If an update is in progress,
	 * the function automatically hands off to the update_item() function.
	 */
	function add_item()
	{
		var form = document.itemform;
		var item = trim(form.itemname.value);

		if( item.length == 0 ) return;

		toggle_buttons( false );
		if( trim(form.itemid.value).length != 0 )
		{
			update_item( item );
			return;
		}
		form.itemlist.options[form.itemlist.options.length] = new Option(item, get_value() );
		add_item_clear();
	}

	/*
	 * This is a wrapper function to ensure that items are added once the enter key (13)
	 * has been pressed. I added this because it was not working in IE without it.
	 */
	function add_item_check()
	{
		if( window.event.keyCode == 13 ) // return key
		{
			add_item();
		}
		else if( window.event.keyCode == 27 ) // esc key
		{
			add_item_clear();
		}
	}

	/*
	 * This function simply resets the "item attributes" fieldset.
	 */
	function add_item_clear()
	{
		var form = document.itemform;
		form.itemname.value = "";
		form.itemurl.value = "";
		/*[PRIVATE]form.item_status.options[0].selected = true;*/
		form.itemchecked.checked = false;
		form.itemid.value = "";
		form.itemname.focus();
		toggle_buttons( false );
	}

	/*
	 * This function loads the currently selected item in the list into the "item attributes" fieldset
	 * for editing or updating.
	 */
	function load_item( item )
	{
		if( item.selectedIndex == -1 ) return;

		add_item_clear();
		toggle_buttons( true );

		var form = document.itemform;
		var data = decode64(form.itemlist.options[ item.selectedIndex ].value).split("|");

		form.itemname.value = data[0];
		form.itemurl.value = data[1];
		//status
		if( data[3] == "yes" )
		{
			form.itemchecked.checked = true;
		}
		else
		{
			form.itemchecked.checked = false;
		}
		form.itemid.value = item.selectedIndex;
	}

	/*
	 * This function allows for items to be updated back into the list once they have been edited.
	 */
	function update_item()
	{
		var form = document.itemform;

		if( trim(form.itemname.value).length == 0 ) return;
		if( trim(form.itemid.value).length == 0 )
		{
			var temp = confirm("You cannot update an item which has yet to be added.\nDo you want to add this item instead?");
			if( !temp ) return true;
			else add_item();
		}

		var list = form.itemlist;
		list.options[ form.itemid.value ].text = form.itemname.value;
		list.options[ form.itemid.value ].value = get_value();

		add_item_clear();
		toggle_buttons( false );
	}

	/*
	 * This function will allow us to quickly turn off some buttons in order to prevent lost or corrupt data.
	 */
	function toggle_buttons( toggle )
	{
		var form = document.itemform;
		form.itemlist.disabled = toggle;
		form.up.disabled = toggle;
		form.down.disabled = toggle;
		form.selectall.disabled = toggle;
		form.selectinvert.disabled = toggle;
		form.delitem.disabled = toggle;
		/*
		form.import.disabled = toggle;
		form.export.disabled = toggle;
		form.sortlist.disabled = toggle;
		form.preview.disabled = toggle;
		form.save.disabled = toggle;
		*/
		if( toggle ) color = "gray";
		else color = "black";

		form.itemlist.style.color = color;
		form.up.style.color = color;
		form.down.style.color = color;
		form.selectall.style.color = color;
		form.selectinvert.style.color = color;
		form.delitem.style.color = color;
		/*
		form.import.style.color = color;
		form.export.style.color = color;
		form.sortlist.style.color = color;
		form.preview.style.color = color;
		form.save.style.color = color;
		*/
	}

	/*
	 * This function will delete one or more selected list items. Take note,
	 * the function deletes in reverse order to circumvent any indexing issues.
	 */
	function delete_items()
	{
		var list = document.itemform.itemlist;
		var length = list.options.length;
		for( i = length - 1; i > -1; i-- )
		{
			if( list.options[i].selected )
			{
				list.options[i] = null;
			}
		}
	}

	/*
	 * This function will sort the list alphabetically. There is currently no ascending/descending option.
	 * If anyone can implement a faster or cleaner algorithm, please let me know: wp-lists@navidazimi.com
	 * But do note that the current implementation develops new "Options" which is required because of the
	 * associated value / text / styling that goes along with each entry item.
	 */
	function sort_list()
	{
		var list = document.itemform.itemlist;
		var length = list.options.length;
		var temp = new Array();

		for( i = 0; i < length; i++ )
		{
			temp[i] = new Option( list.options[i].text, list.options[i].value, list.options[i].defaultSelected, list.options[i].selected );
		}

		if( temp.length == 0 ) return;

		temp = temp.sort(
			function(a,b) {
				if ((a.text.toLowerCase() + "") < (b.text.toLowerCase() + "")) { return -1; }
				if ((a.text.toLowerCase() + "") > (b.text.toLowerCase() + "")) { return 1; }
				return 0;
				}
			);

		for( i = 0; i < length; i++ )
		{
			list.options[i] = new Option( temp[i].text, temp[i].value, temp[i].defaultSelected, temp[i].selected );
		}
	}

	/*
	 * This function was a feature request. It randomizes the given list using the Fisher Yates algorithm.
	 */
	function randomize_list()
	{
		var list = document.itemform.itemlist;
		var length = list.options.length;
		var temp = new Array();

		for( i = 0; i < length; i++ )
		{
			temp[i] = new Option( list.options[i].text, list.options[i].value, list.options[i].defaultSelected, list.options[i].selected );
		}

		fisher_yates_shuffle( temp );

		for( i = 0; i < length; i++ )
		{
			list.options[i] = new Option( temp[i].text, temp[i].value, temp[i].defaultSelected, temp[i].selected );
		}
	}

	/*
	 * This function randomizes any given array using the Fisher Yates Algorithm.
	 */
	function fisher_yates_shuffle( myArray )
	{
		var i = myArray.length;
		while( i-- )
		{
			var j = Math.floor( Math.random() * ( i + 1 ) );
			var tempi = myArray[i];
			var tempj = myArray[j];
			myArray[i] = tempj;
			myArray[j] = tempi;
		}
	}

	/*
	 * This function moves and selects the item for sequential shifting. (n = -1 || 1)
	 */
	function move_item( n )
	{
		var list = document.itemform.itemlist;
		var index = list.selectedIndex;
		if( index - n < 0 || index - n == list.length || index == -1 ) return;
		var temp_one = list.options[ index ];
		var temp_two = list.options[ index - n ];
		list.options[ index ] = new Option(temp_two.text, temp_two.value);
		list.options[ index - n ] = new Option(temp_one.text, temp_one.value);
		list.options[ index - n ].selected = true;
	}

	/*
	 * This function is used to populate the live list preview functionality.
	 */
	function preview_list()
	{
		var list = document.itemform.itemlist;
		var length = list.options.length;

		if( length == 0 ) return;

		document.getElementById("listpreview").innerHTML = "<ul class=\"list\">";

		for( var i = 0; i < length; ++i )
		{
			data = decode64(list.options[i].value).split("|");
			name = data[0];
			link = data[1];
			status = data[2];
			checked = data[3];

			if( checked == "yes" )
			{
				name = "<del>" + name + "</del>";
			}

			if( link.length > 0 )
			{
				name = "<a href=\"" + link + "\">" + name + "</a>";
			}

			document.getElementById("listpreview").innerHTML += "<li>" + name + "</li>";
		}

		document.getElementById("listpreview").innerHTML += "</ul>";
	}

	/*
	 * This function prepares the form to be submitted, with some minor error checking.
	 */
	function save_list()
	{
		var form = document.itemform;
		if( trim(form.list_name.value).length == 0 )
		{
			alert("A rose by any other name, would smell as sweet. But, alas, this list still needs a name.");
			form.list_name.focus();
			return false;
		}
		if( trim(form.itemname.value).length > 0 )
		{
			var temp = confirm("You still have '" + form.itemname.value + "' waiting to be added.\n\nDiscard this item and continue?" );
			if( !temp ) return false;
		}
		select_all();
		form.submit();
	}

	/*
	 * This function, as the name implies, inverts the selection for all items in the item list.
	 */
	function select_invert()
	{
		var list = document.itemform.itemlist;
		var length = list.options.length;
		for( i = 0; i < length; i++ )
		{
			list.options[i].selected = !list.options[i].selected
		}
	}

	/*
	 * This function, as the name implies, selects all items in the item list.
	 */
	function select_all()
	{
		var list = document.itemform.itemlist;
		var length = list.options.length;
		for( i = 0; i < length; i++ )
		{
			list.options[i].selected = true;
		}
	}

	/*
	 * This function, as the name implies, deselects all items in the item list.
	 */
	function select_none()
	{
		var list = document.itemform.itemlist;
		var length = list.options.length;
		for( i = 0; i < length; i++ )
		{
			list.options[i].selected = false;
		}
	}

	/*
	 * This function facilitates the pasting of lists as a means of populating the select box.
	 */
	function import_list()
	{
		var w = 500; var h = 400;
		var top = (screen.availHeight) ? (screen.availHeight-h) / 2 : 50;
		var left = (screen.availWidth) ? (screen.availWidth-w) / 2 : 50;
		var temp = window.open("edit-lists.php?action=import", "import", "fullscreen=no,toolbar=no,statusbar=no,menubar=no,scrollbars=yes,resizable=yes,directories=no,location=no,width="+ w +",height=" + h +",top="+ top +",left=" + left);
		if( !temp.focus() ) temp.focus();
	}

	/*
	 * This function facilitates the exportation of a list to Excel, Notepad or what-have-you.
	 */
	function export_list()
	{
		alert("This feature has not yet been implemented.");
	}

	/*
	 * This is a simple function which will trim a string of whitespace (before and after).
	 */
	function trim( str )
	{
		return str.replace(/^\s*|\s*$/g,"");
	}
-->
</script>

<style type="text/css">
<!--
	#item_manager
	{
		display: block;
		height: 100%;
		min-height: 100%;
	}

	#itemform
	{
		height: 100%;
		min-height: 100%;
		display: block;
	}

	#itemform fieldset.small
	{
		height: 3.6em;
	}

	#itemform fieldset.parent
	{
		padding: 5px;
	}

	#itemform .input
	{
		width: 97%;
	}

	#itemform #itemlist
	{
		display: block;
		width: 98%;
	}

	#itemform td, #itemform tr, #itemform tbody
	{
		width: 50%;
		padding: 0.3em;
	}

	#listpreview
	{
		padding: 0.3em 1em 0.5em 2em;
		text-align: left;
	}

	.error
	{
		background: lightcoral;
		border: 1px solid #e64f69;
		margin: 1em 5% 10px;
		padding: 0 1em 0 1em;
	}

	.center		{ text-align: center;	}
	.right		{ text-align: right;	}
	.left		{ text-align: left;		}
	.top		{ vertical-align: top;	}
	.bold		{ font-weight: bold;	}
	.private	{ color: #e64f69;		}	/* dark red */
//-->
</style>

<?php
/*
 * Start Installation Routine
 *
 * The following procedure is used to create the necessary tables for the WP-Lists
 * plugin. In the case of an upgrade, it checks to ensure that the tables contain the
 * correct database schema (of columns) -- and adds them therein, if they are missing.
 * This code is only executed once per version cycle, the first the user activates.
 * Its purpose is of sorts an automated self-installation, to make it easier for most
 * people. If you feel comfortable enough, you could go ahead and create the
 * following database schemas manually and simply remove this procedure as it does
 * theoretically generate an extra query and a few checks, for optimal load time. Love you.
 *
 */

$tables = $wpdb->get_results("show tables;"); $table1 = false; $table2 = false;
foreach( $tables as $table )
{
	foreach( $table as $value )
	{
		if( $value == $WP_LISTS ) $table1 = true;
		if( $value == $WP_LISTS_ITEMS ) $table2 = true;
	}
}

if( !$table1 || !$table2 )
{
	if( !$table1 )
	{
		$sql = "CREATE TABLE `$WP_LISTS` (
					`ID` INT NOT NULL AUTO_INCREMENT ,
					`post_title` TEXT NOT NULL ,
					`post_author` TINYINT( 4 ) NOT NULL ,
					`post_status` ENUM( 'public', 'private' ) NOT NULL ,
					`post_date` DATETIME NOT NULL ,
					`post_date_gmt` DATETIME NOT NULL ,
					`list_url` TEXT NOT NULL ,
					`list_level` INT( 2 ) NOT NULL ,
					`list_dupes` ENUM( 'allow', 'disallow' ) NOT NULL ,
					PRIMARY KEY ( `ID` )
				);";
		$wpdb->get_results($sql);
	}

	if( !$table2 )
	{
		$sql = "CREATE TABLE `$WP_LISTS_ITEMS` (
				`ID` INT NOT NULL AUTO_INCREMENT ,
				`list_id` INT NOT NULL ,
				`item_name` TEXT NOT NULL ,
				`item_url` TEXT NOT NULL ,
				`item_status` ENUM( 'public', 'private' ) NOT NULL ,
				`item_checked` ENUM( 'yes', 'no' ) DEFAULT 'no' NOT NULL ,
				PRIMARY KEY ( `ID` )
			);";
		$wpdb->get_results($sql);
	}

	echo "<div class=\"updated\"><p><strong>Welcome! If you are reading this, then you have most probably installed this plugin successfully!</strong><br /><br />You should generally only see this message if (a) this is the first time you are running this plugin or (b) you have manually deleted the required tables. The following tables have been (re)created in your wordpress database: <strong>$WP_LISTS</strong> and <strong>$WP_LISTS_ITEMS</strong>. If you would like to disable this plugin, simply visit the <a href=\"plugins.php\">plugins page</a> and <strong>deactivate</strong>. If you want to remove or delete all associated data simply drop the aforementioned tables using your favorite MySQL administrative interface (ala phpMyAdmin). Your feedback and comments are highly appreciated, please do not hesitate to contact me regarding feature suggestions or bug reports: <a href=\"mailto:wp-lists@navidazimi.com\">wp-lists@navidazimi.com</a>.<br /><br />Adieu. Navid.</p></div>";
}
else // check if all our columns exist
{
	$sql = "SHOW COLUMNS FROM `$WP_LISTS`";
	$columns = $wpdb->get_results($sql);
	$found = false;

	foreach( $columns as $column )
	{
		// from v0.9 to v1.0
		if( $column->Field == "list_url" )
		{
			$found = true;
		}
	}

	if( !$found )
	{
		$sql = "ALTER TABLE `$WP_LISTS` ADD `list_url` TEXT NOT NULL AFTER `post_date_gmt`";
		$wpdb->get_results($sql);
		echo "<div class=\"updated\"><p><strong>Flippin' Sweet!</strong> Thank you for upgrading this version of the WordPress Lists Plugin (more information is always available at <a href=\"http://www.navidazimi.com/projects/wp-lists\">http://www.navidazimi.com/projects/wp-lists</a>). The necessary changes to the database schema have been made. If I didn't mess up, all your lists should still be intact. Good luck (you're going to need it)!</p></div>";
	}
}
/*
 * End Installation Routine
 */

$list_id = $_REQUEST['post'];
if( strlen($list_id) == 0 )
{
	$list_id = $_REQUEST['list_id'];
}

if( $save )
{
	$listname		= $_POST["list_name"];
	$listurl		= $_POST["list_url"];
	$listauthor		= $_POST["list_author"];
	$listlevel		= $_POST["list_level"];
	$liststatus		= $_POST["list_status"];
	$listdupes		= $_POST["list_dupes"];
	$itemlist		= $_POST["itemlist"];
	$postdategmt	= strtotime("now") + get_settings('gmt_offset') * 3600;

	if( $itemlist )
	{
		if( strlen($list_id) > 0 )
		{
			$wpdb->get_results("DELETE FROM $WP_LISTS_ITEMS WHERE list_id = $list_id");
		}
		else // creating a new page
		{
			$wpdb->get_results("INSERT INTO $WP_LISTS (post_title, post_author, post_status, post_date, post_date_gmt, list_url, list_level, list_dupes) VALUES ('$listname', '$listauthor', '$liststatus', now(), FROM_UNIXTIME($postdategmt), '$listurl', '$listlevel', '$listdupes')");
			$temp = $wpdb->get_results("SELECT id FROM $WP_LISTS WHERE post_title = '$listname' AND list_level = '$listlevel' ORDER BY id LIMIT 1");
			$list_id = $temp[0]->id;
		}

		if( strlen($list_id) > 0 )
		{
			$wpdb->get_results("UPDATE $WP_LISTS SET post_title = '$listname', post_status = '$liststatus', post_date = now(), post_date_gmt = FROM_UNIXTIME($postdategmt), list_url = '$listurl', list_level = '$listlevel', list_dupes = '$listdupes' WHERE id = $list_id");
			foreach( $itemlist as $item )
			{
				$data = explode("|", base64_decode($item) );
				$name = addslashes($data[0]);
				$link = addslashes($data[1]);
				$status = $data[2];
				$checked = $data[3];
				$wpdb->get_results("INSERT INTO $WP_LISTS_ITEMS (list_id, item_name, item_url, item_status, item_checked) VALUES ('$list_id', '$name', '$link', '$status', '$checked')");
			}
		}
		else $fail = true;
	}
	else $fail = true;

	if( $fail )	die("<div class=\"error\"><p><strong>Failure:</strong> I apologize but we've run into a minor snag, it appears that the database was not <strong>updated</strong>. Obviously, something went wrong when you attempted to save your new list. I'm going to assume you probably left some critical fields blank (like attempting to create a blank list), so why don't you go back and ensure that most of the information is filled in and accurate? Thanks baby.</p></div>");
	echo "<div class=\"updated\"><p>Congratulations. Your list titled <em>". stripslashes($listname) ."</em> has been <strong>saved</strong>.</p></div>";
}

if( $delete )
{
	if( strlen($list_id) > 0 )
	{
		$wpdb->get_results("DELETE FROM $WP_LISTS WHERE id = $list_id");
		$wpdb->get_results("DELETE FROM $WP_LISTS_ITEMS WHERE list_id = $list_id");
	}
	else die("<div class=\"error\"><p>Uh, I'm sorry but you cannot <strong>delete</strong> a post when there is no <strong>post</strong> defined. Seriously, what were you thinking?</p></div>");
	echo "<div class=\"updated\"><p>Per your request, the list has been <strong>deleted</strong>. If that was a mistake, you're SOL.</p></div>";
}
?>

<div class="wrap">
<h2><?php _e('List Management'); ?></h2>
<?php
if( isset($user_ID) && ('' != intval($user_ID)) )
{
    $posts = $wpdb->get_results("SELECT * FROM $WP_LISTS WHERE post_author = $user_ID");
}
else
{
    $posts = $wpdb->get_results("SELECT * FROM $WP_LISTS");
}

if( $posts )
{
?>
	<table width="100%" cellpadding="3" cellspacing="3">
		<tr>
			<th scope="col"><?php _e('ID') ?></th>
			<th scope="col"><?php _e('List Name') ?></th>
			<th scope="col"><?php _e('Owner') ?></th>
			<th scope="col"><?php _e('Updated') ?></th>
			<th scope="col"></th>
			<th scope="col"></th>
		</tr>
<?php
	$bgcolor = '';
	foreach ($posts as $post) : start_wp();
	$class = ('alternate' == $class) ? '' : 'alternate';
?>
		<tr class='<?php echo $class; ?>'>
			<th scope="row" class="<?php echo $post->post_status ?>"><?php echo $id ?></th>
			<td><?php the_title() ?></td>
			<td><?php the_author() ?></td>
			<td><?php the_time('Y-m-d g:i a'); ?></td>
			<td><?php if (($user_level > $authordata->user_level) or ($user_login == $authordata->user_login)) { echo "<a href='edit-lists.php?action=edit&amp;post=$id' class='edit'>" . __('Edit') . "</a>"; } ?></td>
			<td><?php if (($user_level > $authordata->user_level) or ($user_login == $authordata->user_login)) { echo "<a href='edit-lists.php?action=delete&amp;post=$id' class='delete' onclick=\"return confirm('" . sprintf(__("You are about to delete the list: \'%s\'\\n  \'OK\' to delete, \'Cancel\' to stop."), the_title('','',0)) . "')\">" . __('Delete') . "</a>"; } ?></td>
		</tr>
<?php endforeach; ?>
	</table>
<?php
}
else
{
?>
	<p><?php _e('There were no lists found. Perhaps you should create one?') ?></p>
<?php
}
?>
	<h3><a href="edit-lists.php?action=create"><?php _e('Create New List'); ?> &raquo;</a></h3>
</div>
<?php
if( $edit )
{
	if( strlen($list_id) > 0 )
	{
		$items = $wpdb->get_results("SELECT * FROM $WP_LISTS_ITEMS WHERE list_id = $list_id ORDER BY id");
		$lists = $wpdb->get_results("SELECT * FROM $WP_LISTS WHERE id = $list_id");

		foreach( $lists as $list )
		{
			$list_name		= $list->post_title;
			$list_status	= $list->post_status;
			$list_level		= $list->list_level;
			$list_url		= $list->list_url;
			$list_dupes		= $list->list_dupes;
		}
	}
	else die("<div class=\"error\"><p>I apologize but you cannot <strong>edit</strong> a post when there is no <strong>post</strong> defined.</p></div>");
}

if( !$edit && !$create )
{
	include('admin-footer.php');
	exit(0);
}
?>

<form name="itemform" id="itemform" class="wrap" method="post" action="edit-lists.php?action=save" onsubmit="add_item(); return false;">

<div id="item_manager">
<h2><?php _e('Item Management'); ?></h2>

	<input type="hidden" name="list_id" value="<?php echo $list_id; ?>" />
	<input type="hidden" name="list_author" value="<?php echo $user_ID; ?>" />
	<input type="hidden" name="itemid" value="" />

	<div style="float: left; width: 49%; clear: both;" class="top">
			<!-- List Information -->
			<fieldset class="parent"><legend><?php _e('List Information'); ?></legend>
				<!-- List Name -->
				<fieldset class="small"><legend><?php _e('List Name'); ?></legend>
					<input type="text" name="list_name" class="input" size="30" <?php if( $edit ) echo "value=\"$list_name\""; ?> />
				</fieldset>
				<!-- List URL -->
				<fieldset class="small"><legend><?php _e('List URL'); ?></legend>
					<input type="text" name="list_url" class="input" size="30" <?php if( $edit ) echo "value=\"$list_url\""; ?> />
				</fieldset>
				<!-- List Options -->
				<fieldset class="small right"><legend><?php _e('List Options'); ?></legend>
					<!-- List Status -->
					<select name="list_status">
						<?php
							if( $list_status == "private" ) $private = "selected";
							else $public = "selected";
						?>
						<option <?php echo $public ?>>public</option>
						<option <?php echo $private ?>>private</option>
					</select>
					<!-- List Duplicates -->
					<select name="list_dupes">
						<?php
							if( $list_dupes == "disallow" ) $disallow = "selected=\"true\"";
							else $allow = "";
						?>
						<option value="allow" <?php echo $allow ?>>allow duplicates</option>
						<option value="disallow" <?php echo $disallow ?>>disallow duplicates</option>
					</select>
					<!-- List Level -->
					<select name="list_level">
						<?php
							if( !isset($list_level) ) $list_level = 0;
							echo "<option value=\"$list_level\">auth level</option>";
							for( $i = 0; $i <= $user_level; ++$i )
							{
								echo "<option value=\"$i\">$i</option>";
							}
						?>
					</select>
				</fieldset>
			</fieldset>

			<!-- Item Attributes -->
			<fieldset class="parent center"><legend><?php _e('Item Attributes'); ?></legend>
				<fieldset class="small"><legend><?php _e('Item Name'); ?></legend><input type="text" name="itemname" class="input" size="30" onKeyPress="add_item_check();" /></fieldset>
				<fieldset class="small"><legend><?php _e('Item URL'); ?></legend><input type="text" name="itemurl" class="input" size="30" onKeyPress="add_item_check();" /></fieldset>
				<fieldset class="small"><legend><?php _e('Item Options'); ?></legend>
					<label for="itemchecked"><input type="checkbox" name="itemchecked" id="itemchecked" /> Check / Cross Item</label>
					<!--/*[PRIVATE]
						<select name="item_status">
							<option>public</option>
							<option>private</option>
						</select>
					//-->
				</fieldset>
				<input type="button" class="button" name="cancel" value="<?php _e('Cancel'); ?>" onclick="add_item_clear(); this.blur();" />
				<input type="button" class="button" name="update" value="<?php _e('Update Item'); ?>" onclick="update_item(); this.blur();" />
				<input type="button" class="button" name="add" value="<?php _e('Add Item'); ?>" onclick="add_item(); this.blur();" />
			</fieldset>
			<div style="clear:both; height:1px;">&nbsp;</div>
		</div>
		<div style="float: right; width: 49%;" class="top">
			<!-- List Items -->
			<fieldset class="parent center"><legend><?php _e('List Items'); ?></legend>
				<select name="itemlist[]" id="itemlist" multiple="multiple" size="15" onDblClick="load_item(this);">
					<?php
						if( $edit )
						{
							foreach( $items as $item )
							{
								$value = $item->item_name ."|". $item->item_url ."|". $item->item_status ."|". $item->item_checked;
								print "<option value=\"". base64_encode($value) ."\">". $item->item_name ."</option>";
							}
						}
					?>
				</select>
				<input type="button" name="up" class="button" value="Up" onClick="move_item(1); this.blur();" />
				<input type="button" name="down" class="button" value="Down" onClick="move_item(-1); this.blur();" />
				<input type="button" name="selectall" class="button" value="Select All" onclick="select_all(); this.blur();" />
				<input type="button" name="selectinvert" class="button" value="Select Invert" onclick="select_invert(); this.blur();" />
				<input type="button" name="delitem" class="button" value="Delete Item(s)" onclick="delete_items(); this.blur();" />
			</fieldset>

			<!-- List Actions -->
			<fieldset class="parent center"><legend><?php _e('List Actions'); ?></legend>
				<input type="button" name="import" class="button" value="Import" onclick="import_list(); this.blur();" />
				<input type="button" name="randomize" class="button" value="Randomize" onclick="randomize_list(); this.blur();" />
				<!--<input type="button" name="export" class="button" value="Export" onclick="export_list(); this.blur();" />-->
				<input type="button" name="sortlist" class="button" value="Sort List" onclick="sort_list(); this.blur();" />
				<input type="button" name="preview" class="button" value="Preview &raquo;" onclick="preview_list(); this.blur();" />
				<input type="button" name="save" class="button bold" value="Save &raquo;" onclick="save_list(); this.blur();" />
			</fieldset>

			<!-- List Preview -->
			<fieldset class="listpreview"><legend><?php _e('List Preview'); ?></legend>
				<div id="listpreview"></div>
			</fieldset>
			<div style="clear:both; height:1px;">&nbsp;</div>
		</div>
		<div style="clear:both; height:1px;">&nbsp;</div>
</div>
	<input type="submit" name="submitform" value="hidden-submit" style="display: none;" />
</form>
<?php

/*
 * This function is used to circumvent the entire file when it is opened as a popup.
 */
function load_popup()
{
	global $wpdb, $WP_LISTS;

	$lists = $wpdb->get_results("SELECT * FROM $WP_LISTS WHERE post_status = 'public';");
	echo <<<END
	<html>
	<head>
		<title>Popup List Manager</title>
		<link rel="stylesheet" href="wp-admin.css" type="text/css" />
		<script language="JavaScript" type="text/javascript">
		<!--
			function embed_list( listid )
			{
				var showlinks = confirm("By default, items which have an URL associated with them are automatically hyperlinked. If you would like to prevent this, click Cancel. Otherwise, for default behavior, click OK.");
				var showchecked = confirm("By default, items which have been checked off appear \"crossed off\". If you would like to prevent this, click Cancel. Otherwise, for default behavior, click OK.");
				var odd = prompt("Please enter the class name associated with each odd <li> entry:");
				var even = prompt("Please enter the class name associated with each even <li> entry:");
				var attributes = " ";

				if( !showlinks )
				{
					attributes += "showlinks=\"" + showlinks + "\" ";
				}

				if( !showchecked )
				{
					attributes += "showchecked=\"" + showchecked + "\" ";
				}

				if( odd )
				{
					attributes += "odd=\"" + odd + "\" ";
				}

				if( even )
				{
					attributes += "even=\"" + even + "\"";
				}

				insertAtCursor(window.opener.document.post.content, "<list id=\"" + listid + "\"" + attributes + "/>");
				window.close();
			}

			// adapted from wp-amazon.php plugin which in turn is a
			// modified version of a script from Alex King (http://www.alexking.org)
			// the code did not support the AtCursor across multiple windows
			function insertAtCursor(myField, myValue)
			{
				//IE support
				if (document.selection) {
					// only insert text for IE (not at cursor)
					myField.value += myValue;
				}
				//MOZILLA/NETSCAPE support
				else if (myField.selectionStart || myField.selectionStart == '0') {
					var startPos = myField.selectionStart;
					var endPos = myField.selectionEnd;
					myField.value = myField.value.substring(0, startPos)
						+ myValue
						+ myField.value.substring(endPos, myField.value.length);
				} else {
					myField.value += myValue;
				}
			}

			function listen()
			{
				if( window.event.keyCode == 27 ) // esc key
					window.close();
			}
		//-->
		</script>
	</head>
	<body onKeyPress="listen();">
		<h2>Insertable Lists:</h2>
		<ul>
END;
		foreach( $lists as $list )
		{
			$list_name = $list->post_title;
			$list_id = $list->ID;
			print "<li><a href=\"#\" onclick=\"embed_list('$list_id');\">$list_name</a></li>";
		}

	echo "</ul></body></html>";
	exit(0);
}

/*
 * This popup allows users to paste and/or type out their list based on a textarea. As such
 * each new line coresponds as one entry. Importing items are appended to the current list.
 */
function load_popup_import()
{
	echo <<<END
	<html>
	<head>
		<title>Import List</title>
		<link rel="stylesheet" href="wp-admin.css" type="text/css" />
		<style type="text/css">
		<!--
			body
			{
				margin: 1em;
			}

			textarea
			{
				width: 100%;
			}
		//-->
		</style>
		<script type="text/javascript">
		<!--
			function insert_list()
			{
				var importlist = document.importform.importlist;
				var win = opener.document.itemform;

				var lines = importlist.value.split("\\n");
				importlist.value = '';

				for( var i = 0; i < lines.length; i++ )
				{
					parts = lines[i].split("|");
					if( parts[0] )
					{
						name = parts[0]
						win.itemname.value = name;
					}
					if( parts[1] )
					{
						link = parts[1];
						win.itemurl.value = link;
					}
					opener.add_item();
				}
				self.close();
			}

			function toggle_size( amount )
			{
				document.importform.importlist.rows += amount;
			}

			function listen()
			{
				if( window.event.keyCode == 27 ) // esc key
					window.close();
			}
		//-->
		</script>
	</head>
	<body onkeypress="listen();">
		<h2>Import List:</h2>
		<fieldset><legend>Paste your list in the textarea below (1 line per entry)</legend>
		<form name="importform" id="importform" method="post" onsubmit="return false;">
			<textarea rows="14" cols="14" name="importlist"></textarea>
			<br />
			<div style="float: left;">
				<input type="button" class="button" name="import" value="Import" onclick="insert_list();" />
				<input type="button" class="button" name="cancel" value="Cancel" onclick="javascript:self.close();" />
			</div>
			<div style="float: right;">
				<input type="button" class="button" name="increase" value="Size +" onclick="toggle_size(5); this.blur();" />
				<input type="button" class="button" name="decrease" value="Size -" onclick="toggle_size(-5); this.blur();" />
			</div>
			<input type="submit" value="Submit" class="hidden" />
		</form>
		</fieldset>
	</body>
	</html>
END;
	exit(0);
}

/*
 * This popup allows users to export their current list as a comma delimited file.
 */
function load_popup_export()
{
	// this feature has not yet been implemented
}

include('admin-footer.php');

?>