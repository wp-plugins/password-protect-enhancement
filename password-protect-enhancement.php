<?php
/*
Plugin Name: Password Protect enhancement
Plugin URI: http://quirm.net/2008/10/05/password-protect-enhancement/
Description: Enhance the password protection for posts and pages. Optional setting to display an excerpt, and change the current 'This post is password protected. To view it please enter your password below:' text. based upon an original idea at http://simbo.de/
Version: 0.0.1
Author: Rich Pedley 
Author URI: http://elfden.co.uk/

    Copyright 2008  RICH PEDLEY  (email : elfin@elfden.co.uk)

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
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
function display_protected_excerpts() {
	//global $post;
	global $id, $post, $more, $page, $pages, $multipage, $preview, $pagenow;

	$output = '';
	$output = $post->post_excerpt;
	if($output==''){//no need to do anything if the excerpt has been set
		if ( $page > count($pages) ) // if the requested page doesn't exist
			$page = count($pages); // give them the highest numbered page that DOES exist

		$content = $pages[$page-1];
		if ( preg_match('/<!--more(.*?)?-->/', $content, $matches) ) {//explode if there is a more tag
			$content = explode($matches[0], $content, 2);

		} else {
			$content = array($content);
			$chktext = $content[0];
			if(strlen($chktext<55)){
				$text = strip_shortcodes( $chktext ); 
				$text = str_replace(']]>', ']]&gt;', $text);
				$text = strip_tags($text);
				$excerpt_length = 55;
				$words = explode(' ', $text, $excerpt_length + 1);
				if (count($words) > $excerpt_length) {
					array_pop($words);
					array_push($words, '[...]');
					$text = implode(' ', $words);
				}
				$content[0]=$text;
			}
		}

		$output = $content[0];//formatting not needed as WP handles it
	}else{
		$output='<p>'.$output.'</p>';//format it nicely
	}
	return $output;
}

function change_password_protect_text($content) {
	global $post;
	$pass_protect = get_post_meta($post->ID,'_pass-protect');
	if (!empty($post->post_password)){
		$replacement_text='';
		if($pass_protect[0]['excerpt']=='Yes' || $pass_protect[0]['text']!=''){
			if($pass_protect[0]['excerpt']=='Yes'){
				$new_text = display_protected_excerpts();//add an excerpt
				$content=$new_text.$content;//add it to the content
			}
			if($pass_protect[0]['text']!='') {
				$replacement_text = $pass_protect[0]['text'];
			}
			if($replacement_text!=''){
				$string=__('This post is password protected. To view it please enter your password below:');
				return str_replace($string,$replacement_text, $content);
			}
		}
	}
	return $content;
}
add_filter('the_content','change_password_protect_text', 10);



//make it available
add_action('admin_menu', 'passprotect_add_custom_box');
/* Use the save_post action to do something with the data entered */
add_action('save_post', 'passprotect_save_postdata');

/* Adds a custom section to the "advanced" Post and Page edit screens */
function passprotect_add_custom_box() {
  if( function_exists( 'add_meta_box' )) {
    add_meta_box( 'passprotectpostcustom', __( 'Password Protect enhancement', 'passprotectenhance' ), 
                'passprotect_inner_custom_box', 'post', 'advanced','low' );
    add_meta_box( 'passprotectpostcustom', __( 'Password Protect enhancement', 'passprotectenhance' ), 
                'passprotect_inner_custom_box', 'page', 'advanced' );
   }
}

	

/* Prints the inner fields for the custom post/page section */
function passprotect_inner_custom_box() {
	global $wpdb;
  	// Use nonce for verification
	echo '<input type="hidden" name="passprotectenhance_noncename" id="passprotectenhance_noncename" value="' . 
	wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
	if( isset( $_REQUEST[ 'post' ] ) ) {
		$pass_protect = get_post_meta($_REQUEST[ 'post' ],'_pass-protect');
		$pass_protect = $pass_protect[0];
    }
    if(!isset($pass_protect) || (isset($pass_protect) && sizeof($pass_protect)==1))
		$pass_protect = array('text' => '','excerpt' => 'No');
	?>
	<p><label for="pass-protect-text"><?php _e('Password form text','passprotectenhance'); ?> </label><input id="pass-protect-text" name="pass-protect-text" value="<?php echo $pass_protect['text']; ?>" type="text" size="30" /></p>
	
	<h4><?php _e('Display Excerpt','passprotectenhance'); ?></h4>
	<p>
	<input id="pass-protect-excerpt_yes" name="pass-protect-excerpt" value="Yes"<?php echo $pass_protect['excerpt']=='Yes' ? 'checked="checked"' : ''; ?> type="radio" /> <label for="pass-protect-excerpt_yes" class="selectit"><?php _e('Yes','passprotectenhance'); ?></label>
	<input id="pass-protect-excerpt_no" name="pass-protect-excerpt" value="No" <?php echo $pass_protect['excerpt']=='No' ? 'checked="checked"' : ''; ?>type="radio" /> <label for="pass-protect-excerpt_no" class="selectit"><?php _e('No','passprotectenhance'); ?></label>
	</p>
	<?php
}

/* When the post is saved, saves our custom data */
function passprotect_save_postdata( $post_id ) {
	global $wpdb;
  // verify this came from the our screen and with proper authorization,
  // because save_post can be triggered at other times

  if ( !wp_verify_nonce( $_POST['passprotectenhance_noncename'], plugin_basename(__FILE__) )) {
    return $post_id;
  }

  if ( 'page' == $_POST['post_type'] ) {
    if ( !current_user_can( 'edit_page', $post_id ))
      return $post_id;
  } else {
    if ( !current_user_can( 'edit_post', $post_id ))
      return $post_id;
  }
	if( !isset( $id ) )
		$id = $post_id;
  // OK, we're authenticated: we need to find and save the data
  	//$pass_protect = array('text' => '','excerpt' => 'No');
	$pass_protect['text']=attribute_escape($_POST['pass-protect-text']);
	$pass_protect['excerpt']=attribute_escape($_POST['pass-protect-excerpt']);
	if(trim($pass_protect['text'])=='' && $pass_protect['excerpt']=='No')
		delete_post_meta( $id, '_pass-protect' );
	else
		delete_post_meta( $id, '_pass-protect' );
		add_post_meta( $id, '_pass-protect', $pass_protect,true);

   	return;
}
?>