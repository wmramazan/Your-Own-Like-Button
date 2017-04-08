<?php
/*
Plugin Name: Your Own Like Button
Plugin URI: https://github.com/wmramazan/your-own-like-button
Description: This plugin adds a like button to single posts.
Version: 1.0
Author: Ramazan Vapurcu
Author URI: http://wmramazan.adnagu.com
License: GPL2
*/

add_action('wp_enqueue_scripts', 'yolb_scripts');
function yolb_scripts() {
	//Enqueue if the current page is single post.
	if(is_single()) {
		wp_enqueue_style('yolb', plugin_dir_url(__FILE__) . "your-own-like-button.css");
		wp_enqueue_script('yolb-js', plugin_dir_url(__FILE__) . 'your-own-like-button.js', array('jquery'), '1.0', true);
		wp_localize_script('yolb-js', 'yolb', array(
			'url' => admin_url('admin-ajax.php'),
			'like' => __('Like', 'yolb'),
			'unlike' => __('Unlike', 'yolb')
		));
	}
}

add_action('wp_ajax_nopriv_yolb_like', 'yolb_like');
function yolb_like() {
	if(@is_numeric($_REQUEST['id'])) {
		$id = $_REQUEST['id'];
		//Prevent CSRF
		if(!wp_verify_nonce($_REQUEST['nonce'], 'yolb-'.$id)) wp_nonce_ays();
		$like = get_post_meta($id, '_yolb_like', true);
		$ip = get_ip();
		$ip_list = get_ip_list($id);
		$tag_ids = wp_get_post_tags($id, array('fields' => 'ids'));
		if(in_array($ip, $ip_list)) {
			//Unlike
			unset($ip_list[array_search($ip, $ip_list)]);
			$like--;
			$response['liked'] = false;
			
			foreach($tag_ids as $tag_id) {
				$tag_like = get_term_meta($tag_id, '_yolb_like', true);
				update_term_meta($tag_id, '_yolb_like', $tag_like - 1);
			}
		} else {
			//Like
			$ip_list[count($ip_list)] = $ip;
			$like++;
			$response['liked'] = true;
			
			foreach($tag_ids as $tag_id) {
				$tag_like = get_term_meta($tag_id, '_yolb_like', true);
				update_term_meta($tag_id, '_yolb_like', $tag_like + 1);
			}
		}
		update_post_meta($id, '_yolb_ip', $ip_list);
		update_post_meta($id, '_yolb_like', $like);
		$response['like'] = $like;
		
		//Redirect if javascript is disabled
		if(@$_REQUEST['redirect']) wp_redirect(get_permalink($id));
		else wp_send_json($response);
	}
}

function get_ip_list($id) {
	$ip_list = get_post_meta($id, '_yolb_ip', true);
	return is_array($ip_list) ? $ip_list : array();
}

function get_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else $ip = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
    if(filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
	else die;
}

add_filter('the_content', 'yolb_content');
function yolb_content($content = NULL) {
	if(is_single()) {
		$id = get_the_ID();
		$nonce = wp_create_nonce('yolb-' . $id);
		$like = get_post_meta($id, '_yolb_like', true);
		if(!$like) $like = 0;
		//TODO: like -> 1.2k
		if(in_array(get_ip(), get_ip_list($id))) {
			$class = ' liked';
			$text = __('Unlike', 'yolb');
		} else {
			$class = '';
			$text = __('Like', 'yolb');
		}
		$button = '<a href="'.admin_url('admin-ajax.php?action=yolb_like&id='.$id.'&nonce='.$nonce.'&redirect=true').'" class="yolb-button'.$class.'" data-id="'.$id.'" data-nonce="'.$nonce.'">'.$text.'</a> <span class="yolb-count">'.$like.'</span>';
		return $content . $button;
	} else return $content;
}

add_shortcode('yolb_tags', 'yolb_tags');

function yolb_tags() {
	$page = (get_query_var('paged')) ? get_query_var( 'paged' ) : 1;
	$number = 10;
	$offset = ($page - 1) * $number;
	
	$tags = get_tags(array(
		'meta_key' => '_yolb_like',
		'orderby' => 'meta_value_num',
		'order' => 'DESC',
		'number' => $number,
		'offset' => $offset
	));
	
    $html = '<ul class="yolb-tags">';
	if($tags) {
		foreach($tags as $tag) {
			$html .= '<li><a href="'.get_term_link($tag).'">'.$tag->name.' ('.get_term_meta($tag->term_id, '_yolb_like', true).')</a></li>';
		}
		$html .= '</ul>';
		if($page != 1) $html .= '<div><a href="'.get_permalink().'page/'.($page - 1).'">'.__('Previous Page', 'yolb').'</a></div>';
		if(count($tags) == $number) {
			$html .= '<div><a href="'.get_permalink().'page/'.($page + 1).'">'.__('Next Page', 'yolb').'</a></div>';
		}
	} else {
		echo __('There is no tags to show.', 'yolb');
		if($page != 1) $html .= '<div><a href="'.get_permalink().'page/'.($page - 1).'">'.__('Previous Page', 'yolb').'</a></div>';
	}
	
	return $html;
}

add_action('admin_menu', 'yolb_menu');
function yolb_menu() {
	add_menu_page('Your Own Like Button', 'Your Own Like Button', 'administrator', 'yolb-settings', 'yolb_settings_page', 'dashicons-thumbs-up');
}

function yolb_settings_page() {
	?>
	<div style="padding: 32px; font-size: 16pt"><a href="https://github.com/wmramazan/your-own-like-button">your-own-like-button</a></div>
	<?php ;
}

class yolb_widget extends WP_Widget {

	function __construct() {
		parent::__construct('yolb_widget', __('Popular Posts', 'yolb'), array(
			'classname' => 'yolb_widget',
			'description' => __('This widget lists the most popular posts', 'yolb')));
	}

	function form($instance) {
		 $title = !empty($instance['title']) ? $instance['title'] : ''; ?>
		  <p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php echo __('Title')?>:</label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo esc_attr($title); ?>" />
		  </p><?php
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		return $instance;
	}

	function widget($args, $instance) {
		$title = apply_filters('widget_title', $instance['title']);
		if(!$title) $title = __('Popular Posts', 'yolb');
		
		echo $args['before_widget'] . $args['before_title'] . $title . $args['after_title'];
		
		$posts = query_posts(array(
			'meta_key' => '_yolb_like',
			'orderby' => 'meta_value_num',
			'order' => 'DESC',
			'number' => 10,
			'fields' => array('ID', 'post_title')
		));
		
		if($posts) {
			echo '<ul>';
			foreach($posts as $post) {
				echo '<li><a href="'.get_permalink($post).'">'.$post->post_title.' ('.get_post_meta($post->ID, '_yolb_like', true).')</a></li>';
			}
			echo '</ul>';
		} else echo __('No posts.', 'yolb');
		
		echo $args['after_widget'];
	}
}

add_action('widgets_init', 'yolb_register_widget');
function yolb_register_widget() { 
	register_widget('yolb_widget');
}

add_action('plugins_loaded', 'yolb_load_textdomain');
function yolb_load_textdomain() {
	load_plugin_textdomain('yolb', false, dirname( plugin_basename(__FILE__) ) . '/lang/');
}
?>