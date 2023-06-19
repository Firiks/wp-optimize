<?php
/**
* Plugin Name: Wp-Optimize
* Description: Optimize wp installation, remove bloat, security hardening.
* Version: 1.0.0
* Author: WpOptimize
*/

if( !defined( 'ABSPATH' ) || !function_exists( 'add_action' ) ) {
  die();
}

if( !class_exists('WpOptimize') ) :

class WpOptimize {
  function __construct() {
    // security
    $this->prevent_login_scan();
    add_filter( 'rest_authentication_errors', array( $this, 'secure_rest_api' ) );

    // custom headear/footer additions
    add_action( 'wp_head', array( $this, 'head_custom' ) );
    add_action( 'wp_footer', array( $this, 'footer_custom') );

    // wp cleanup
    add_action( 'init' , array( $this, 'do_cleanup'), PHP_INT_MAX );

    // load custom scripts
    add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
  }

  function do_cleanup() {
    // xml-rpc
    add_filter( 'xmlrpc_enabled', '__return_false' );
    $this->disable_xmlrpc();

    // Remove Emoji
    $this->disable_wp_emojicons();

    // Html meta
    $this->useless_meta();

    // Shortlink
    $this->remove_shortlink();

    // Pingback
    $this->disable_pingback();

    remove_action( 'wp_head', 'wp_resource_hints', 2 ); // removes dns-prefetch for w.org

    add_filter( 'wp_resource_hints', array( $this, 'remove_hardcoded_reference' ) );

    $this->woocommerce_additions();

    $this->wp_additionals_tweaks();

  }

  function prevent_login_scan() {
    if( !empty($_GET['author']) ) {
      if( is_admin() && !defined('DOING_AJAX') ) return;
      die();
    }
  }

  function remove_hardcoded_reference( $urls ) {
    foreach ($urls as $key => $url) {
      // Remove dns-prefetch for w.org (we really don't need it)
      // See https://core.trac.wordpress.org/ticket/40426 for details 
      if ( 'https://s.w.org/images/core/emoji/13.0.0/svg/' === $url ) {
        unset( $urls[ $key ] );
      }
    }

    return $urls;
  }

  function disable_xmlrpc() {
    remove_action( 'wp_head', 'rsd_link' ); // Realy simple discovery link
    remove_action( 'wp_head', 'wlwmanifest_link' ); // wlwmanifest.xml (windows live writer)
    if( stripos( $_SERVER['REQUEST_URI'], '/xmlrpc.php' ) !== false ) die();
  }

  function secure_rest_api($result) {
    if ( true === $result || is_wp_error( $result ) ) {
      return $result;
    }

    if ( ! is_user_logged_in() ) {
      return new WP_Error(
        'rest_not_logged_in',
        __( 'You are not currently logged in.' ),
        array( 'status' => 401 )
      );
    }

    return $result;
  }

  function disable_wp_emojicons() {
    // all actions related to emojis
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

    // filter to remove TinyMCE emojis
    add_filter( 'emoji_svg_url', '__return_false' );

    // filter to remove the DNS prefetch
    add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojicons_tinymce' ) );
  }

  function useless_meta() {
    // <meta name="generator" content="WordPress 4.9.8" />
    remove_action( 'wp_head', 'wp_generator' );

    // EDD
    remove_action( 'wp_head', 'edd_version_in_header' );

    // also hide it from RSS
    add_filter( 'the_generator', '__return_false' );
  }

  function remove_shortlink() {
    // <link rel='shortlink' href="https://yourdomain.com/?p=1">
    remove_action('wp_head', 'wp_shortlink_wp_head');

    // link: <https://yourdomainname.com/wp-json/>; rel="https://api.w.org/", <https://yourdomainname.com/?p=[post_id_here]>; rel=shortlink
    remove_action('template_redirect', 'wp_shortlink_header', 11);
  }

  function disable_pingback() {
  // Disable Pingback method
    add_filter('xmlrpc_methods', static function ($methods) {
      unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
      return $methods;
    } );

    // Remove X-Pingback HTTP header
    add_filter('wp_headers', static function ($headers) {
      unset($headers['X-Pingback']);
      return $headers;
    });
  }

  // filter to disable TinyMCE emojicons
  function disable_emojicons_tinymce( $plugins ) {
    if ( is_array( $plugins ) ) {
      return array_diff( $plugins, array( 'wpemoji' ) );
    } else {
      return array();
    }
  }

  function remove_wp_footer($html) {
    $text = sprintf( __( 'Thank you for creating with <a href="%s">WordPress</a>.' ), __( 'https://wordpress.org/' ) );
    $html = str_replace( $text, '', $html );
    return $html;
  }

  function head_custom() {
    ?>
 
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-GOOGLEID"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-GOOGLEID');
      
    </script>
    <?php
  }

  function footer_custom() {
    // add custom data to footer
    ?>
    <?php
  }

  function load_scripts() {
    wp_register_script( 'wp-optimize', plugin_dir_url( __FILE__ ) . 'assets/js/wp-optimize.js', array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/wp-optimize.js' ), true );
    wp_enqueue_script( 'wp-optimize' );

    wp_register_style( 'wp-optimize', plugin_dir_url( __FILE__ ) . 'assets/css/wp-optimize.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/wp-optimize.css' ) );
    wp_enqueue_style( 'wp-optimize' );
  }

  function woocommerce_additions() {
    // WooCommerce message "Connect your store to WooCommerce.com to receive extensions updates and support."
    add_filter( 'woocommerce_helper_suppress_connect_notice', '__return_true' );
  }

  function wp_additionals_tweaks() {
    // Wp branding
    add_filter( 'admin_footer_text', array( $this, 'remove_wp_footer' ) );

    //  disabling WP_Automatic_Updater::send_email() with subject of "WordPress x.y.z is available. Please update!"
    add_filter( 'send_core_update_notification_email', '__return_false' );

    //  disabling WP_Automatic_Updater::send_email() with subject of "Your site has updated to WordPress x.y.z"
    add_filter( 'auto_core_update_send_email', '__return_false' );
  }

}

// Init
new WpOptimize;

endif;
