<?php
/*
Plugin Name: Custom CSS/JS
Plugin URI: http://www.becauseinterwebs.com/wordpress-custom-css-js/
Description: Simple plugin that allows you to add both page-specific and global custom CSS and Javascript to pages and posts.
Version: 1.0
Author: becauseinterwebs
Author URI: http://www.becauseinterwebs.com/
License: GPL2 or later
Copyright 2015  BecauseInterwebs.com  (email : info@becauseinterwebs.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

To obtain a copy of the GNU General Public License, write to the
Free Software Foundation, Inc., 51 Franklin St, Fifth Floor,
Boston, MA  02110-1301  USA
*/

if (!class_exists('BIW_Custom_Css_Js')) {

  class BIW_Custom_Css_Js {

    private $rn = "\r\n";
    private $break = "[biw_br]";

    function __construct() {

      if (is_admin()) {
        // admin functions
        add_action('add_meta_boxes', array($this, 'add_meta_boxes' ));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'biw_enqueue_scripts'));
      } else {
        // front-end functions
        add_action('wp_print_footer_scripts', array($this, 'add_css'), PHP_INT_MAX);
        add_action('wp_print_footer_scripts', array($this, 'add_js'), PHP_INT_MAX);
      }

    }

    public function biw_enqueue_scripts() {
      wp_register_style('BIW_CSS_JS', plugins_url('style.css', __FILE__), null, '1.0', 'screen');
      wp_enqueue_style('BIW_CSS_JS');
    }

    public function admin_menu() {
      // Add a page to manage this plugin's settings
      add_menu_page(
        'Custom CSS/JS',
        'Custom CSS/JS',
        'manage_options',
        'custom_css_js_dashboard',
        array($this, 'dashboard')
      );
      add_submenu_page(
        'custom_css_js_dashboard',
        'Global Scripts',
        'Global Scripts',
        'manage_options',
        'custom_css_js_dashboard',
        array($this, 'dashboard')
      );
    }

    function dashboard() {
      if (isset($_POST['biw_save_button'])) {
        $this->save_meta_box_data();
        echo '<div class="updated notice">' . __('Settings saved.', 'biw_custom_css_js_textdomain') . '</div>';
      }
      echo "<h1>" . __('Global Custom CSS/JS', 'biw_custom_css_js_textdomain') . "</h1>";
      echo "<h4>" . __('by Because...Interwebs!') . "</h4>";
      echo "<p>" . __('Enter custom Javascript and CSS that will be used on <em>each page/post</em> for your site. If you wish to add page- and post-specific custom CSS and Javascript, simply edit that page or post.', 'biw_custom_css_js_textdomain') . "</p>";
      echo '<form method="POST">';
      $this->meta_box_css();
      $this->meta_box_js_external();
      $this->meta_box_js();
      echo '<input type="submit" class="button action clearfix" name="biw_save_button" value="' . __('Save', 'biw_custom_css_js_textdomain'). '"/>';
      echo '</form>';
    }

    function add_meta_boxes() {
      $screens = array( 'post', 'page' );
      foreach ( $screens as $screen ) {
        add_meta_box(
          'biw_custom_css',
          __( 'Custom CSS', 'biw_custom_css_js_textdomain' ),
          array($this, 'meta_box_css'),
          $screen
        );
        add_meta_box(
          'biw_custom_js_external',
          __( 'External Javascripts', 'biw_custom_css_js_textdomain' ),
          array($this, 'meta_box_js_external'),
          $screen
        );
        add_meta_box(
          'biw_custom_js',
          __( 'Custom Javascript', 'biw_custom_css_js_textdomain' ),
          array($this, 'meta_box_js'),
          $screen
        );
      }
    }

    function add_meta_box($type, $post, $text) {
      wp_nonce_field( 'biw_meta_box_'.$type, 'biw_meta_box_nonce_'.$type);
      if ($post) {
        $value = get_post_meta( $post->ID, '_biw_custom_'.$type, true );
      } else {
        $value = get_option('_biw_custom_'.$type, '');
      }
      if ($text) {
        echo "<p>{$text}</p>";
      }
      echo '<textarea class="biw_textarea" name="biw_custom_'.$type.'">' . esc_textarea(stripslashes($value)) . '</textarea>';
    }

    function meta_box_css($post = null) {
      $this->add_meta_box('css', $post, __('Enter custom CSS below (no &lt;style&gt;&lt;/style&gt; tags...we\'ll add them for you.)', 'biw_custom_css_js_textdomain'));
    }

    function meta_box_js($post = null) {
      $this->add_meta_box('js', $post, __('Enter custom Javascript below (no &lt;script&gt;&lt;/script&gt; tags...we\'ll add them for you.)', 'biw_custom_css_js_textdomain'));
    }

    function meta_box_js_external($post = null) {
      $this->add_meta_box('js_external', $post, __('Enter external Javascript URL\'s starting with \'http://\', \'https://\' or \'//\' (one entry per line). External scripts are loaded <em>first</em> so that you can reference them in your custom Javascript.', 'biw_custom_css_js_textdomain'));
    }

    function save_meta_box_data($post_id = 0) {

      if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
      }

      // Check the user's permissions.
      if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
          return;
        }
      } else {
        if ( ! current_user_can( 'edit_post', $post_id ) && !is_admin()) {
          return;
        }
      }

      $exts = array('css','js', 'js_external');

      foreach ($exts as $ext) {
        if (isset( $_POST['biw_meta_box_nonce_'.$ext])) {
          if (wp_verify_nonce($_POST['biw_meta_box_nonce_'.$ext], 'biw_meta_box_'.$ext)) {
            if (isset($_POST['biw_custom_'.$ext])) {
              $value = $_POST['biw_custom_'.$ext];
              //$value = str_replace($this->rn, $this->break, $value);
              //$value = sanitize_text_field( $value );
              $value = str_replace($this->break, $this->rn, $value);
              if ($post_id) {
                update_post_meta( $post_id, '_biw_custom_'.$ext, $value );
              } else {
                update_option('_biw_custom_'.$ext, $value, false);
              }
            }
          }
        }
      }

    }

    function insert_script($script = null) {
      if ($script === null) return;
      $script = trim(stripslashes($script));
      if (!$script || $script == '') return;
      echo '<script type="text/javascript"';
      if (stripos($script, '://') == 4 || stripos($script, '://') == 5) {
        echo " src=\"{$script}\">";
      } else {
        echo ">\r\n{$script}\r\n";
      }
      echo "</script>\r\n";
    }

    function add_js() {
      $exts = array('js_external', 'js');
      global $post;
      // global first
      foreach ($exts as $ext) {
        if ($value = get_option('_biw_custom_'.$ext, '')) {
          $this->insert_script($value);
        }
      }
      // then page/post
      if (isset($post)) {
        foreach ($exts as $ext) {
          if ($value = get_post_meta( $post->ID, '_biw_custom_'.$ext, true )) {
            $this->insert_script($value);
          }
        }
      }
    }

    function add_css() {
      global $post;
      // global first
      if ($value = get_option('_biw_custom_css', '' )) {
        echo "<style>" . $value . "</style>";
      }
      // then page/post
      if (isset($post)) {
        if ($value = get_post_meta( $post->ID, '_biw_custom_css', true )) {
          echo "<style>" . $value . "</style>";
        }
      }
    }

  }

  if (!defined('DOING_AJAX') || !DOING_AJAX) {
    $biw_custom_css_js = new BIW_Custom_Css_Js();
  }

}
