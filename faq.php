<?php
/*
Plugin Name: Simple FAQ
Plugin URI: http://www.spidersoft.com.au/2010/simple-faq/
Description: Simple plugin which creates editable FAQ on your site
Version: 0.6
Author: Slawomir Jasinski - SpiderSoft
Author URI: http://www.spidersoft.com.au/
License: GPL2

Copyright 2009-2011 Slawomir Jasinski  (email : slav123@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$faq_db_version = "0.3";

/**
 * install FAQ and create database for it
 */
function faq_install () {
   global $wpdb;
   global $faq_db_version;

   $table_name = $wpdb->prefix . "faq";
   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

      $sql = "CREATE TABLE " . $table_name . " (
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`author_id` INT NOT NULL ,
	`question_date` DATE NOT NULL ,
	`question` TEXT NOT NULL ,
	`answer_date` DATE NOT NULL ,
	`answer` TEXT NOT NULL
	) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);

      $insert = "INSERT INTO " . $table_name .
            " (author_id, question_date, question, answer_date, answer) " .
            "VALUES ('0', '".date("Y-m-d")."','Sample question', '".date("Y-m-d")."', 'Sample answer')";

      $results = $wpdb->query( $insert );

      add_option("faq_db_version", $jal_db_version);
   }

}

   register_activation_hook(__FILE__,'faq_install');

/**
 * @name Display FAQ
 */

function DisplayFAQ() {
    global $wpdb;
    $table_name = $wpdb->prefix . "faq";

    $select = "SELECT * FROM `{$table_name}` ORDER BY answer_date DESC";
    $all_faq = $wpdb->get_results($select);

    $buf = '<ol class="simple-faq">';
    foreach ($all_faq as $q) {
	$buf .= '<li>' . format_to_post( $q->question ). '<br/><span class="sf-answer">';
	$buf .= format_to_post( $q->answer ).'</span></li>';
    }
    $buf .= '</ol>';

    return $buf;
}

   add_shortcode('display_faq', 'DisplayFAQ');


/**
 * @name admin part
 */

    // rejestracja menu
    function FAQ_menu() {
	//add_submenu_page('plugins.php', 'FAQ list', 'Simple FAQ', 8, basename(__FILE__), 'faq_main');
	add_submenu_page( 'plugins.php', 'Simple FAQ', 'Simple FAQ', 'manage_options', basename(__FILE__), 'faq_main');
    }
    add_action('admin_menu', 'faq_menu');

function faq_main() {
   echo '<div class="wrap">';
   echo '<h2>Simple FAQ</h2>';

   if (isset($_REQUEST["act"]))
      switch ($_REQUEST["act"]) {
	 case 'edit':
	    $msg = faq_form('update', $_REQUEST['id']);
	 break;

	 case 'new':
	    $msg = faq_form('insert');
	 break;

	 case 'delete':
	    $msg = faq_delete($_REQUEST['id']);
	 break;

	 case 'update':
	    $msg = faq_update($_POST);
	 break;

	 case 'insert':
	    $msg = faq_insert($_POST);
	 break;

	 case 'view':
	    faq_view($_REQUEST['id']);
	 break;

	 default:
	    faq_list();
	 break;
      }
   else
      faq_list();

   if (!empty($msg)) {
      echo '<p>' . draw_ico(__('back to list'), 'Backward.png', '') . '</p>';
      _e("Message: ") ;
      echo $msg;
   }
   echo '</div>';
}

/**
  delete entry from database
*/
function faq_delete($id) {
   global $wpdb;
   $table_name = $wpdb->prefix . "faq";

   $results = $wpdb->query("DELETE FROM {$table_name} WHERE id='$id'");
   if ($results) {
      $msg = __("FAQ entry was successfully deleted.");
   }
   return $msg;
}

/**
 * update entry in database
 */
function faq_update($data) {
    global $wpdb, $current_user;
    $table_name = $wpdb->prefix . "faq";
    $wpdb->update($table_name,
		  array( 'question' => stripslashes_deep($data['question']),
			'answer' => stripslashes_deep($data['answer']),
			'answer_date' => date("Y-m-d"),
			'author_id' => $current_user->ID),
		  array( 'id' => $data['hid']));
    $msg = __("Question and answer updated");
    return $msg;
}

/**
 * insert new entry into database
 */
function faq_insert($data) {
    global $wpdb, $current_user;

    $table_name = $wpdb->prefix . "faq";
    $wpdb->insert( $table_name,
		  array(
			'question' => stripslashes_deep($data['question']),
			'answer' => stripslashes_deep($data['answer']),
			'answer_date' => date("Y-m-d"),
			'author_id' => $current_user->ID),
		  array( '%s', '%s', '%s', '%d' ) );
    $msg = __("Entry added");
    return $msg;
}

/**
 * draw small ico
 */
function draw_ico($text, $gfx, $url) {
   return '<a href="?page=faq.php'.$url . '" style="text-decoration: none"><img src="../wp-content/plugins/simple-faq/gfx/' . $gfx .'" width="18" height="18" alt="+" style="vertical-align: middle; margin: 0 5px 0 0"/>' . $text . '</a>';
}

/**
 * show entries from database
 */

function faq_list() {
   global $wpdb, $current_user;
   $table_name = $wpdb->prefix . "faq";

   echo '<h3>List of entries.</h3>';
   echo '<p>' . draw_ico(__('add new entry'), 'add.png', '&amp;act=new') . '</p>';


   $select = "SELECT id, question, answer, author_id, answer_date FROM {$table_name} ORDER BY answer_date DESC";
   $all_faq = $wpdb->get_results($select);

   ?><table class="widefat">
   <thead>
      <th scope="col"><?php _e("Question") ?></th>
      <th scope="col"><?php _e("Created") ?></th>
      <th scope="col"><?php _e("Author") ?></th>
      <th scope="col" width="30"><?php _e("Edit") ?></th>
      <th scope="col" width="30"><?php _e("View"); ?></th>
      <th scope="col" width="30"><?php _e("Delete");?></th>
   </thead>
   <tbody>
   <?


    $buf = '<tr>';
    foreach ($all_faq as $q) {
      if ($q->author_id == 0) $q->author_id = $current_user->ID;
	 $user_info = get_userdata($q->author_id);

	echo '<tr>';
	echo '<td>' . $q->question . '</td>';
	echo '<td>' . $q->answer_date . '</td>';
	echo '<td>' . $user_info->user_login . '</td>';
	echo '<td>' . draw_ico('', 'tool.png', '&amp;id=' . $q->id . '&amp;act=edit') . '</td>';
	echo '<td>' . draw_ico('', 'zoom.png', '&amp;id=' . $q->id . '&amp;act=view') . '</td>';
	echo '<td>' . draw_ico('', 'del.png', '&amp;id=' . $q->id . '&amp;act=delete') . '</td>';
	echo '</tr>';
    }

    echo '</tbody></table>';

}

function faq_view($id) {
   global $wpdb;
   $table_name = $wpdb->prefix . "faq";

   $row = $wpdb->get_row("SELECT * FROM `{$table_name}` WHERE id = '$id'");
   echo '<p>';
   _e("Question:");
   echo '<br/>';
   echo $row->question;
   echo '<p>';
   _e("Answer:");
   echo '<br/>';
   echo $row->answer;
   echo '<p>' . draw_ico(_e('back to list'), 'Backward.png', 'plugins.php?page=faq') . '</p>';
}

/**
 * form for edit/new entries
 */

function faq_form($act, $id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . "faq";



    if ($act == 'insert') {
      $row->question = '';
      $row->answer = '';
      $id = null;
    } else {
        $row = $wpdb->get_row("SELECT * FROM `{$table_name}` WHERE id = '$id'");
    }



    ?>
    <form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
    <input type="hidden" name="hid" value="<?php echo $id ?>"/>
    <input type="hidden" name="act" value="<?php echo $act ?>"/>

    <p><?php _e("Question:", 'mt_trans_domain' ); ?><br/>
    <input type="text" name="question" value="<?php echo $row->question; ?>" size="20" class="large-text"/>
    <p><?php _e("Answer:", 'mt_trans_domain' ); ?><br/>
    <textarea name="answer" rows="10" cols="30" class="large-text"><?php echo $row->answer; ?></textarea>
    </p><hr />
    <p class="submit"><input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" class="button-primary" /></p>
    </form>
<?}



?>
