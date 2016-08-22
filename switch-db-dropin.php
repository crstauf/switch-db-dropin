<?php
/*
Plugin Name: Switch DB Drop-in
Plugin URI: https://github.com/crstauf/switch-db-dropin
Description: Switch between multiple db.php drop-ins
Version: 0.0.1
Author: Caleb Stauffer
Author URI: http://develop.calebstauffer.com
*/

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

add_action( 'plugins_loaded', array( 'switch_db_dropin', 'action_plugins_loaded' ) );

class switch_db_dropin {

    private static $current_dropin = false;
	protected static $activated_plugins = array();

    private static $supported_plugins = array(
        /*
        @TODO support HyperDB drop-in by renaming file when switching
        'hyper-db' => array(
            'abbr' => 'Hyper',
            'version' => '1.1',
        ),*/
        'next-level-cache' => array(
            'abbr' => 'NLC',
            'version' => '0.0.9',
        ),
        'query-monitor' => array(
            'abbr' => 'QM',
            'version' => '2.12.0',
        ),
        'w3-total-cache' => array(
            'abbr' => 'W3TC',
            'version' => '0.9.4.1',
        ),
    );

    static function action_plugins_loaded() {
        if (
            !function_exists( 'file_get_contents' )
            || !function_exists( 'unlink' )
            || !current_user_can( 'administrator' )
        ) return;

        self::is_nlc_activated();
        self::is_qm_activated();
        self::is_w3tc_activated();

        self::$activated_plugins = apply_filters( 'switch_db_dropin/activated_plugins', self::$activated_plugins );

        if ( file_exists( WP_CONTENT_DIR . '/db.php' ) )
            self::determine_current();

        add_action( 'init', array( __CLASS__, 'action_init' ) );
        add_action( 'wp_ajax_switch_db_dropin', array( __CLASS__, 'action_wp_ajax' ) );

    }

        static function is_nlc_activated() {
            if (
                class_exists( 'next_level_cache_wpdb' )
                && defined( 'NEXT_LEVEL_CACHE_VERSION' )
                && apply_filters( 'switch_db_dropin/plugins/next-level-cache', true )
            )
                self::$activated_plugins = 'next-level-cache';
        }

        static function is_qm_activated() {
            if (
                (
                    !defined( 'QM_DISABLE' )
                    || !QM_DISABLE
                )
                && class_exists( 'QM_Activation' )
                && function_exists( 'readlink' )
                && function_exists( 'symlink' )
                && apply_filters( 'switch_db_dropin/plugins/query-monitor', true )
            )
                self::$activated_plugins[] = 'query-monitor';
        }

        static function is_w3tc_activated() {
            if (
                defined( 'W3TC' ) && W3TC
                && apply_filters( 'switch_db_dropin/plugins/w3-total-cache', true )
            )
                self::$activated_plugins[] = 'w3-total-cache';
        }

        static function determine_current() {
            $db_file = WP_CONTENT_DIR . '/db.php';

            if ( is_link( WP_CONTENT_DIR . '/db.php' ) )
                $db_file = readlink( WP_CONTENT_DIR . '/db.php' );

            if ( file_exists( $db_file ) ) {
                $search = file_get_contents( $db_file, false, NULL, 0, 100 );
            } else {
                self::$current_dropin = false;
                if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
                    echo 'db.php file does not exist';
                return;
            }

            if ( false !== strpos( $search, 'Plugin Name: Query Monitor Database Class' ) )
                self::$current_dropin = 'query-monitor';

            else if ( false !== strpos( $search, '* Next Level Cache DB Driver' ) )
                self::$current_dropin = 'next-level-cache';

            else if ( false !== strpos( $search, '// HyperDB' ) )
                self::$current_dropin = 'hyperdb';

            else if ( false !== strpos( $search, '* W3 Total Cache Database module' ) )
                self::$current_dropin = 'w3-total-cache';

            else if ( false !== strpos( $search, 'Version Press' ) )
                self::$current_dropin = 'versionpress';

            else
                self::$current_dropin = false;
        }

	static function action_init() {
		if ( 2 > count( self::$activated_plugins ) ) return;

        $localize_array = array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'supported_plugins' => self::$supported_plugins,
            'nonce' => wp_create_nonce( __FILE__ ),
        );

        add_action( 'admin_bar_menu', array( __CLASS__, 'action_admin_bar_menu' ), 600 );

        wp_register_script( 'switch-db-dropin', plugin_dir_url( __FILE__ ) . 'scripts' . ( !defined( 'COMPRESS_SCRIPTS' ) || COMPRESS_SCRIPTS ? '.min' : '' ) . '.js', array( 'jquery' ), 'init' );
            wp_localize_script( 'switch-db-dropin', 'switch_db_dropin', $localize_array );

        wp_register_style( 'switch-db-dropin', plugin_dir_url( __FILE__ ) . 'styles' . ( !defined( 'COMPRESS_CSS' ) || COMPRESS_CSS ? '.min' : '' ) . '.css', array(), 'init' );
	}

    static function action_admin_bar_menu( $bar ) {
        if ( !is_admin_bar_showing() ) return;

        wp_enqueue_script( 'switch-db-dropin' );
        wp_enqueue_style( 'switch-db-dropin' );

        $bar->add_node( array(
            'id' => 'switch-db-dropin',
            'title' => '<span class="ab-icon dashicons dashicons-download"></span><span class="ab-label">' . ( false !== self::$current_dropin ? self::$supported_plugins[self::$current_dropin]['abbr'] : 'DB' ) . '</span>',
            'href' => '#',
        ) );

        foreach ( self::$activated_plugins as $activated_plugin )
            $bar->add_node( array(
                'parent' => 'switch-db-dropin',
                'id' => 'switch-db-dropin-' . esc_attr( $activated_plugin ),
                'title' => '<input type="radio" name="switch_db_dropin"' . checked( $activated_plugin, self::$current_dropin, false ) . ' /> <span class="ab-icon dashicons"></span>' . esc_html( $activated_plugin ),
                'href' => '#' . esc_attr( $activated_plugin ),
            ));
    }

	static function action_wp_ajax() {
        if ( !check_ajax_referer( __FILE__, 'nonce', false ) )
            exit( 'referer check failed' );

        if ( !current_user_can( 'administrator' ) )
            exit( 'user does not have necesary privileges' );

        if ( self::$current_dropin === $_REQUEST['plugin'] )
            exit( 'dropin already matches requested plugin' );

        $switch_to = $_REQUEST['plugin'];

        if ( file_exists( WP_CONTENT_DIR . '/db.php' ) )
            unlink( WP_CONTENT_DIR . '/db.php' );

        $method = 'activate_' . strtolower( self::$supported_plugins[$switch_to]['abbr'] ) . '_dropin';

        if ( !method_exists( __CLASS__, $method ) )
            exit( 'method \'' . $method . '\' in \'' . __CLASS__ . '\' does not exist' );

        self::$method();
        self::determine_current();

        if ( self::$current_dropin === $switch_to )
            exit( 'true' );

        exit( 'dropin (\'' . self::$current_dropin . '\') does not match requested plugin (\'' . $switch_to . '\')' );
	}

        public static function activate_nlc_dropin() {
            @self::wp_copy_file( WP_CONTENT_URL . '/plugins/next-level-cache/db.php', WP_CONTENT_DIR . '/db.php' );
        }

        public static function activate_qm_dropin() {
            global $qm_dir;
            @symlink( trailingslashit( $qm_dir ) . 'wp-content/db.php', WP_CONTENT_DIR . '/db.php' );
		}

        public static function activate_w3tc_dropin() {
            @self::wp_copy_file( W3TC_INSTALL_FILE_DB, W3TC_ADDIN_FILE_DB );
		}

    // based on w3_wp_copy_file in W3TC v0.9.4.1
    private static function wp_copy_file($source_filename, $destination_filename) {
        $contents = @file_get_contents( $source_filename );

        if ( $contents )
            @file_put_contents( $destination_filename, $contents );

        if (
            @file_exists( $destination_filename )
            && @file_get_contents( $destination_filename ) === $contents
        )
            return;

        global $wp_filesystem;
        if ( isset( $wp_filesystem ) )
            $wp_filesystem->put_contents( $destination_filename, $contents, FS_CHMOD_FILE );
    }

}

?>
