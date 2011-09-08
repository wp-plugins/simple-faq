<?php
/*
Plugin Name: Simple FAQ
Plugin URI: http://www.spidersoft.com.au/2010/simple-faq/
Description: Simple plugin which creates editable FAQ on your site
Version: 1.0
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

$faq_db_version = "0.4";

/**
 * install FAQ and create database for it
 */
function faq_install () {
   global $wpdb;
   global $faq_db_version;

   $table_name = $wpdb->prefix . "faq";
   $installed_ver = get_option( "faq_db_version" );
   
   if($wpdb->get_var("show tables like '$table_name'") != $table_name || $installed_ver != $faq_db_version) {

       $sql = "CREATE TABLE " . $table_name . " (
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`author_id` INT NOT NULL ,
	`question_date` DATE NOT NULL ,
	`question` TEXT NOT NULL ,
	`answer_date` DATE NOT NULL ,
	`answer` TEXT NOT NULL,
	`status` TINYINT NOT NULL
	) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);
      
      update_option( "faq_db_version", $faq_db_version );

      $insert = "INSERT INTO " . $table_name .
            " (author_id, question_date, question, answer_date, answer, status) " .
            "VALUES ('0', '".date("Y-m-d")."','Sample question', '".date("Y-m-d")."', 'Sample answer', 1)";

      $results = $wpdb->query( $insert );

      add_option("faq_db_version", $faq_db_version);
   }	
} // end faq_install
   
   register_activation_hook(__FILE__,'faq_install');


   /**
    *   check if DB is up to date
    */
   function myplugin_update_db_check() {
      global $faq_db_version;
      if (get_site_option('faq_db_version') != $faq_db_version) {
	 faq_install();
      }
   }
   add_action('plugins_loaded', 'myplugin_update_db_check');

/**
 * @name Display FAQ
 */

function DisplayFAQ() {
    global $wpdb;
    $table_name = $wpdb->prefix . "faq";

    $select = "SELECT * FROM `{$table_name}` WHERE status=1 ORDER BY answer_date DESC";
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
   ?>
   <div id="msg" style="overflow: auto"></div>
   <div class="wrap">
   <h2>Simple FAQ <a href="?page=faq.php&act=new" class="add-new-h2">Add New</a></h2>
   <!--<ul class="subsubsub">
      <li class="all"><a href="?page=faq.php" class="current">Entries </a> | </li>
      <li class="active"><a href="?page=faq.php?act=settings">Settings </a></li>
   </ul>-->
   <div style="clear: both"></div>
   <?php

   if (isset($_REQUEST["act"]))
      switch ($_REQUEST["act"]) {
	 case 'edit':
	    $msg = faq_form('update', $_REQUEST['id']);
	 break;

	 case 'new':
	    $msg = faq_form('insert');
	 break;
      
	 case 'bulk':
	    faq_bulk($_REQUEST);
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
      echo '<p><a href="?page=faq.php">' . __('back to list'). '</a></p>';
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

function faq_bulk($data) {
   $ids = '';
   
   if (is_array($data['faq'])) {
      $ids = join(',', $data['faq']);
   } else {
      return false;
   }
   global $wpdb;
   $table_name = $wpdb->prefix . "faq";
   
   if (!empty($ids)) {
      switch ($data['action']) {
	 case 'publish':
	    $results = $wpdb->query("UPDATE {$table_name} SET status=1 WHERE id IN ({$ids})");
	 break;
	 case 'unpublish':
	    $results = $wpdb->query("UPDATE {$table_name} SET status=0 WHERE id IN ({$ids})");
	 break;
	 case 'trash':
	    $results = $wpdb->query("DELETE FROM {$table_name} WHERE id IN ({$ids})");
	 break;
      }
   }

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
			'author_id' => $current_user->ID,
			'status' => $data['status']),
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
			'author_id' => $current_user->ID,
			'status' => $data['status']),
		  array( '%s', '%s', '%s', '%d', '%d' ) );
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

   $select = "SELECT id, question, answer, author_id, answer_date, status FROM {$table_name} ORDER BY answer_date DESC";
   $all_faq = $wpdb->get_results($select);

   ?>
   <form id="faq_table" method="post" onsubmit="return faqBulkAction();">
   <div class="tablenav top">
      <div class="alignleft actions">
	 <select name="action" id="faq_action">
	    <option value="-1" selected="selected">Bulk Actions</option>
	    <option value="publish">Publish</option>
	    <option value="unpublish">Unpublish</option>
	    <option value="trash">Move to Trash</option>
	 </select>
	 <input type="submit" name="" id="doaction" class="button-secondary action" value="Apply">
      </div>
   </div>	
   
   <input type="hidden" name="act" value="bulk"/>
   <table class="wp-list-table widefat">
   <thead>
   <tr>
      <th scope="col" class="manage-column"><input type="checkbox" id="faq_chb"></th>
      <th scope="col" class="manage-column"><?php _e("Question") ?></th>
      <th scope="col" class="manage-column"><?php _e("Created") ?></th>
      <th scope="col" class="manage-column"><?php _e("Author") ?></th>
      <th scope="col" class="manage-column"><?php _e("Status") ?></th>
   </tr>
   </thead>
   <tbody>
   <?php

    $buf = '<tr>';
    $status = array('Draft', 'Published');
    foreach ($all_faq as $q) {
      if ($q->author_id == 0) $q->author_id = $current_user->ID;
      
	 $user_info = get_userdata($q->author_id);
	 $edit_link = '?page=faq.php&amp;id=' . $q->id . '&amp;act=edit';
	 $view_link ='?page=faq.php&amp;id=' . $q->id . '&amp;act=view';
	 $delete_link = '?page=faq.php&amp;id=' . $q->id . '&amp;act=delete';

	echo '<tr>';
	echo '<th scope="row"><input type="checkbox" name="faq[]" value="' . $q->id . '" class="faq_chb"></th>';
	echo "<td><strong><a href=\"{$edit_link}\" title=\"Edit question\">" . $q->question . "</a></strong>";
	echo '<div class="row-actions">';
	echo "<span class=\"edit\"><a href=\"{$edit_link}\" title=\"Edit this item\">Edit</a></span> | ";
	echo "<span class=\"view\"><a href=\"{$view_link}\" title=\"View this item\">View</a></span> | ";
	echo "<span class=\"trash\"><a href=\"{$delete_link}\" title=\"Move this item to Trash\">Trash</a></span>";
	echo '</div>';
	echo '</td>';
	echo '<td>' . $q->answer_date . '</td>';
	echo '<td>' . $user_info->user_login . '</td>';
	echo '<td>' . $status[$q->status] . '</td>';
	echo '</tr>';
    }
?>
    </tbody></table></form>
    <script type="text/javascript">
      function faqBulkAction(){
	 var sdata = jQuery('#faq_table').serialize();
	 var action = jQuery('#faq_action').val();
	 if (action == -1) {
	    alert('You need to chose action!');
	    return false;
	 }
	 if (action == 'trash') {
	    if (!confirm('Are you sure?')) return false;
	 }
	 jQuery.ajax({
	    url: '?page=faq.php',
	    data: sdata,
	    type: 'POST',
	    success: function(msg) {
	       document.location.reload();
	       return false;
	    },
	    failure: function() {
	       alert("Error occured in ajax query");
	       return false;
	    }
	 });
	 return false;
      }
      
      jQuery(document).ready(function() {
	 jQuery('#faq_chb').bind('click', function(){
	    var checked_status = this.checked; 
            jQuery(".faq_chb").each(function() { 
               this.checked = checked_status; 
            }); 
	 });
      });
    </script>
   <?php

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
   echo '<p><a href="plugins.php?page=faq">&laquo; ' . __('back to list'). '</p>';
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
    <p>
      <label><input type="radio" name="status" value="0" <?php if($row->status == 0) echo "checked" ?>> Draft</label> <label><input type="radio" name="status" value="1" <?php if($row->status == 1) echo "checked" ?>> Published</label> 
    </p>
    <p class="submit"><input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" class="button-primary" /></p>
    </form>
<?}



?>
