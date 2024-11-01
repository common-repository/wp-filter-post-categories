<?php 
/*
 Plugin Name: WP Filter Post Category
 Plugin URI: http://wordpress.phpanswer.com/wpplugins/wp-filter-post-categories/
 Description: This plugin allows you to choose which post categories youe site will show on the homepage. Just go to settings and deselect the categories that you want to hide. For WordPress 3.1 upgrade at <a href="http://wppluginspool.com/wp-filter-post-categories/">Filter Posts in Pages</a>
 Version: 2.1.4
 Author: Cristian Merli
 Author URI: http://wppluginspool.com
 */
 
/*
This program is free software; you can redistribute it and/or modify 
it under the terms of the GNU General Public License as published by 
the Free Software Foundation; version 2 of the License.

This program is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the 
GNU General Public License for more details. 

You should have received a copy of the GNU General Public License 
along with this program; if not, write to the Free Software 
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA 
*/



class MerlicFilterCategory {

    /**
     * Set show all categories by default
     */
    public function init() {
        $all_categories = get_categories('hide_empty=0');
        
        if (count($all_categories) > 0) {
            foreach ($all_categories as $category) {
                $cat_ID[] = $category->cat_ID;
            }
            add_option('merlic_filtercategory_allowed', implode(',', $cat_ID));
        }
        
		add_theme_support( 'post-thumbnails' );
    }
    
    /**
     * Filter categories from homepage, showing only posts that belong to selected categories
     * @param object $query
     * @return object $query
     */
    public function filter($query) {
        $featured_category_id = get_option('merlic_filtercategory_allowed', true);
        
        $featured_category_id_array = array();
        $featured_category_id_array = explode(',', $featured_category_id);
        
        $excluded_cat = array();
        
        $all_categories = get_categories();
        
        foreach ($all_categories as $cat) {
            //print_r($cat).'<br />';
            if (!in_array($cat->cat_ID, $featured_category_id_array))
                $excluded_cat[] = '-'.$cat->cat_ID;
        }
        
        if ($query->is_home AND $query->get('post_type') != 'nav_menu_item') {
            $query->set('cat', implode(',', $excluded_cat));
        }
        
        return $query;
    }
    
    /**
     * Applies the shortcode to pages other than homepage
     * @param object $atts
     * @return string The posts properly formatted
     */
    public function category_shortcode($atts) {
    	$output = null;
		
        //extract shortcode attributes
        extract(shortcode_atts(array('cat'=>'', 'limit'=>'-1', 'title_style'=>'b'), $atts));
        
        $filter = array('category'=>$cat, 'numberposts'=> $limit, 'order'=>'DESC', 'orderby'=>'date');
        $filtered_posts = get_posts($filter);
        
        if (count($filtered_posts) > 0) {
            foreach ($filtered_posts as $mypost) {
                $thumbnail = get_the_post_thumbnail($mypost->ID, 'thumbnail', array('style'=>'margin: 0 5px 5px 0; float: left;'));
                $output .= '<'.$title_style.' style="margin: 10px; 0 5px 0;"><a href="'.get_permalink($mypost->ID).'">'.$mypost->post_title.'</a></'.$title_style.'>';

				$output .= '<table>';
				$output .= '<tr>';
				$output .= '<td valign="top">';
                $output .= $thumbnail;
				
                if (get_option('merlic_filtercategory_show_as') == 'excerpt') {
                    $content = $mypost->post_excerpt;
                    $content = apply_filters('the_excerpt', $content);
                    $output .= '<p>'.$content.'</p>';
                } else {
                    $content = $mypost->post_content;
                    $content = apply_filters('the_content', $content);
                    $output .= '<p>'.$content.'</p>';
                }

            	$output .= '</td>';
				$output .= '</tr>';
				$output .= '</table>';
            }
        } else
            $output = 'No results';

        return $output;
        //return '<p>'.$output.'</p>';
    }

    
    /**
     * Callback function for admin_menu action
     */
    public function settings_menu() {
        add_options_page("Filter Posts in Pages", "Filter Posts in Pages", 'manage_options', 'merlic_filtercategory_admin', array('MerlicFilterCategory', 'draw_settings'));
    }
    
    /**
     * Draws the settings page and manages the stored options
     */
    public function draw_settings() {
        $save_message = null;
        $allowed = null;
        $shortcode = null;
        
        if (isset($_POST['merlic_filtercategory_allowed']))
            $allowed = $_POST['merlic_filtercategory_allowed'];
        if (isset($_POST['shortcode']))
            $shortcode = $_POST['shortcode'];
            
        $all_categories = get_categories('hide_empty=0');
        
        $homepage = get_page(get_option('page_on_front'));
        
        //check if the form has been submitted
        if (isset($_POST['merlic_filtercategory_save'])) {
        
            //save page meta data here
            if (count($allowed) > 0)
                update_option('merlic_filtercategory_allowed', implode(',', $_POST['merlic_filtercategory_allowed']));
            else
                delete_option('merlic_filtercategory_allowed');
                
            //save page meta data here
            if (count($shortcode) > 0) {
                $shortcode = '[wp_filter_posts cat="'.implode(',', $_POST['shortcode']).'" limit="'.$_POST['posts_limit'].'" title_style="'.$_POST['title_style'].'"]';
            } else
                $shortcode = '';
                
            update_option('merlic_filtercategory_show_as', $_POST['merlic_filtercategory_show_as']);
            
            $save_message = __('Changes have been saved');
            
        }
        
        //display the form
        $output = '
			<div class="wrap">
				<h2>'.__('Filter Posts in Pages Settings').'</h2>
				<p>Uncheck the categories that you want to hide from your post page</p>
		';
		
        $output .= '
			<form method="POST" accept-charset="utf-8" target="_self" action="'.$_SERVER['REQUEST_URI'].'">
				<table class="form-table">
					<tr valign="top"><th scope="row"><label><b>'.__('Page').'</b></lable></th><td><b>'.__('Categories').'</b></td></tr>
					<tr valign="top">
	                    <th scope="row"><label>'.__('Default Post Page').'</lable></th>
						<td>'.self::draw_categories($all_categories).'</td>
					</tr>
		';
		
        $posted_style = isset($_POST['title_style']) ? $_POST['title_style'] : '';
        
        $select_style = '
			<select name="title_style">
				<option '.($posted_style == 'h1' ? 'selected="selected"' : '').' value="h1" style="font-weight: 600; font-size: 1.5em;">Heading 1</option>
				<option '.($posted_style == 'h2' ? 'selected="selected"' : '').' value="h2" style="font-weight: 600; font-size: 1.4em;">Heading 2</option>
				<option '.($posted_style == 'h3' ? 'selected="selected"' : '').' value="h3" style="font-weight: 600; font-size: 1.3em;">Heading 3</option>
				<option '.($posted_style == 'b' ? 'selected="selected"' : '').' value="b" style="font-weight: 600; font-size: 0.9em;">Bold</option>
			</select>
		';
		
        $post_limt = isset($_POST['posts_limit']) ? $_POST['posts_limit'] : '';
        
        $output .= '
					<tr valign="top">
	                    <th scope="row"><label>'.__('Other pages').'</lable></th>
						<td>'.self::draw_shortcode_form($all_categories).'</td>
					</tr>
					<tr valign="top">
	                    <th scope="row">&nbsp;</th>
						<td><label>Number of posts to show</label><br /><input name="posts_limit" type="text" value="'.$post_limt.'"><span class="description">Leave blank to show all</span></td>
					</tr>
					<tr valign="top">
	                    <th scope="row">Posts List style</th>
						<td>'.$select_style.'<br /><span class="description">Choose the style of the posts title</span></td>
					</tr>
					<tr valign="top">
	                    <th scope="row">Show as</th>
						<td>
							<input type="radio" name="merlic_filtercategory_show_as" value="full" '.(get_option('merlic_filtercategory_show_as') == 'full' ? 'checked="checked"' : '').'>Full Content<br />
							<input type="radio" name="merlic_filtercategory_show_as" value="excerpt" '.(get_option('merlic_filtercategory_show_as') == 'excerpt' ? 'checked="checked"' : '').'>Excerpt<br />
							<span class="description">Choose the style of the posts title (<b>does not affect frontpage, that depends on your theme</b>)</span></td>
					</tr>
		';
		
        if (isset($_POST['merlic_filtercategory_save']))
            $output .= '<tr><th scope="row"><label><b>'.__('Shortcode').'</b></lable></th><td>'.$shortcode.'</td></tr>'."\n";
            
        $output .= '<tr><td>&nbsp;</td><td><i>'.$save_message.'</i></td></tr>'."\n";
        
        $output .= '<tr><td>&nbsp;</td><td><input class="button-primary" type="submit" name="merlic_filtercategory_save" value="'.__('Save Changes').'" /></td></tr>'."\n";
        $output .= '</table>'."\n";
        $output .= '</form>'."\n";
        
        //$output .= self::donate();
        $output .= '<br /><h3>More plugins from the same author</h3>';
        $output .= '<a href="http://wppluginspool.com">Wordpress Plugins</a>';
        $output .= '
			</div>
		';
		
        echo $output;
    }
    
    /**
     *
     * @param object $page The page object
     * @param array $categories The category objects
     * @return string A list of checkboxes, one for each category
     */
    private function draw_categories($categories) {
        $checkboxes = null;
        
        if (count($categories) > 0) {
        
            foreach ($categories as $category) {
                //get the allowed categories for this page that have been previously saved
                $allowed_categories = get_option('merlic_filtercategory_allowed', true);
                $allowed_categories_array = explode(',', $allowed_categories);
                
                if (in_array($category->cat_ID, $allowed_categories_array))
                    $checked = 'checked = "checked"';
                else
                    $checked = '';
                    
                //draw the checkbox
                $checkboxes .= '<input type="checkbox" name="merlic_filtercategory_allowed[]" value="'.$category->cat_ID.'" '.$checked.'> '.$category->name.'<br/>';
            }
        }
        return $checkboxes;
    }
    
    /**
     * Draws the form to choose the shortcode for other pages
     * @param object $categories
     * @return
     */
    private function draw_shortcode_form($categories) {
        $checkboxes = null;
        $checked = null;
        
        if (count($categories) > 0) {
        
            foreach ($categories as $category) {
                if (isset($_POST['shortcode']) AND is_array($_POST['shortcode'])) {
                    if (in_array($category->cat_ID, $_POST['shortcode']))
                        $checked = 'checked="checked"';
                    else
                        $checked = '';
                }
                
                //draw the checkbox
                $checkboxes .= '<input type="checkbox" name="shortcode[]" value="'.$category->cat_ID.'" '.$checked.'> '.$category->name.'<br/>';
            }
        }
        return $checkboxes;
    }
    
    private function donate() {
        return '
				<h4>More plugins from the same author</h4>
				Please visit <a href="http://wppluginspool.com">Wordpress Plugins Store</a> for more plugins.
				<br/><h4>Free Ebooks offered</h4>
				Please visit <a href="http://thedollarebook.com">The Dollar Ebook</a> for free ebooks from the author.		
			</form>
		';
    }
    
    private function println($text) {
        if (is_array($text) or is_object($text)) {
            echo '<pre>';
            print_r($text);
            echo '</pre>';
        } else {
            echo '<pre>';
            echo $text;
            echo '</pre>';
        }
        
        echo '<br />'."\n";
    }

    
}


add_action('pre_get_posts', array('MerlicFilterCategory', 'filter'), 1);
add_action('admin_menu', array('MerlicFilterCategory', 'settings_menu'));
add_action('init', array('MerlicFilterCategory', 'init'));
add_shortcode('wp_filter_posts', array('MerlicFilterCategory', 'category_shortcode'));

register_activation_hook(__FILE__, 'merlic_filterpost_activate');

function merlic_filterpost_activate() {
    if (!get_option('merlic_filtercategory_show_as'))
        add_option('merlic_filtercategory_show_as', 'full');
}


?>
