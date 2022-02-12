<?php
/*
Plugin Name: ContactX
Description: simple contact form plugin.
Author: Hiroyuki Teshima
Version: 1.0
*/

class Contactx {

    const PROPERTIES_NAME = 'contactx';
	const DOMAIN_NAME = 'ContactX';
	private $wp_list_table;

	public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_my_setting' ) );
        add_action( 'admin_print_footer_scripts', array( $this, 'my_admin_footer_script' ) );

        if ( function_exists( 'register_activation_hook' ) ) {
            register_activation_hook( __FILE__ , 'Contactx::activation' );
        }
        if ( function_exists( 'register_deactivation_hook' ) ) {
            register_deactivation_hook( __FILE__, 'Contactx::deactivation' );
        }
        if ( function_exists( 'register_uninstall_hook' ) ) {
            register_uninstall_hook( __FILE__, 'Contactx::uninstall' );
        }

        if ( session_status() !== PHP_SESSION_ACTIVE ) {
			session_start();
            session_regenerate_id( true );
        }
    }

	public function admin_menu() {
        add_menu_page(
            self::DOMAIN_NAME, // page_title
			self::DOMAIN_NAME, // menu_title
			'administrator', // capability
			self::PROPERTIES_NAME, // menu slug
			array( $this, 'load' ), // function
            'dashicons-email', // icon
        );

        add_submenu_page(
            self::PROPERTIES_NAME, // parent menu slug
            self::DOMAIN_NAME . ' Setting', // page_title
			self::DOMAIN_NAME . ' Setting', // menu_title
			'administrator', // capability
			self::PROPERTIES_NAME . 'setting', // menu slug
			array( $this, 'contactx_setting' ), // function
        );
    }

    public function register_my_setting() {
        register_setting(
            'contactx', // option_group
            'ctx_save_post_data', // option_name
        );
        register_setting( 'contactx', 'ctx_use_recaptcha' );
        register_setting( 'contactx', 'ctx_v3_sitekey' );
        register_setting( 'contactx', 'ctx_v3_secretkey' );
    }

	public function load() {
        require_once 'includes/class-contactx-list-table.php';
        $this->wp_list_table = new Contactx_List_Table( array( 'screen'=>self::PROPERTIES_NAME ) );
        $this->wp_list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h2>ContactX Messages</h2>';
        echo  '<form method="post" id="bulk-action-form">';
        $this->wp_list_table->views(); 
        $this->wp_list_table->display();
        echo '</form>';
        echo '</div>';
    }

    public function contactx_setting() {
        $message = '';
        if ( isset( $_POST['submit'] ) ) {
            $message = '<div class="notice notice-success settings-error is-dismissible"><p>設定を保存しました。</p></div>';
        }

        if ( isset( $_POST['ctx_save_post_data'] ) ) {
            update_option( 'ctx_save_post_data', $_POST['ctx_save_post_data'] );
        }
        if ( isset( $_POST['ctx_use_recaptcha'] ) ) {
            update_option( 'ctx_use_recaptcha', $_POST['ctx_use_recaptcha'] );
        }
        if ( isset( $_POST['ctx_v3_sitekey'] ) ) {
            update_option( 'ctx_v3_sitekey', $_POST['ctx_v3_sitekey'] );
        }
        if ( isset( $_POST['ctx_v3_secretkey'] ) ) {
            update_option( 'ctx_v3_secretkey', $_POST['ctx_v3_secretkey'] );
        }

        $save_post_data = get_option( 'ctx_save_post_data' );
        if ( empty( $save_post_data ) ) {
            $save_post_data = '';
        } else {
            $save_post_data = 'checked="checked"';
        }
        $use_recaptcha = get_option( 'ctx_use_recaptcha' );
        if ( empty( $use_recaptcha ) ) {
            $use_recaptcha = '';
        } else {
            $use_recaptcha = 'checked="checked"';
        }
        $v3_sitekey = get_option( 'ctx_v3_sitekey' );
        $v3_secretkey = get_option( 'ctx_v3_secretkey' );

        $str_html = '';
        $str_html .= '<div class="wrap">';
        $str_html .= '<h2>ContactX Setting</h2>';
        $str_html .=  $message;
        $str_html .= '<p>投稿フォームを表示する場合はショートコード[cxform]を使用してください。</p>';
        $str_html .= '<form id="contactx-recaptcha" method="post" action="">';
        $str_html .= settings_fields( 'contactx' );
        $str_html .= do_settings_sections( 'contactx' );       
        $str_html .= '<table class="form-table"><tbody>';
        $str_html .= '<tr><th scope="row"><label for="ctx_save_post_data">save_post_data</label></th>';
        $str_html .= '<input type="hidden" name="ctx_save_post_data" value="0">';   
        $str_html .= '<td><input type="checkbox" id="ctx_save_post_data" name="ctx_save_post_data" value="1" '. $save_post_data . '></td></tr>';
        $str_html .= '<tr><th scope="row"><label for="ctx_use_recaptcha">use_recaptcha</label></th>';
        $str_html .= '<input type="hidden" name="ctx_use_recaptcha" value="0">';   
        $str_html .= '<td><input type="checkbox" id="ctx_use_recaptcha" name="ctx_use_recaptcha" value="1" '. $use_recaptcha . '></td></tr>';
        $str_html .= '<tr><th scope="row"><label for="v3_sitekey">v3_sitekey</label></th>';
        $str_html .= '<td><input type="text" id="ctx_v3_sitekey" name="ctx_v3_sitekey" size="60" maxlength="60" value="' . esc_html($v3_sitekey) . '"></td></tr>';
        $str_html .= '<tr><th scope="row"><label for="ctx_v3_secretkey">v3_secretkey</label></th>';
        $str_html .= '<td><input type="text" id="ctx_v3_secretkey" name="ctx_v3_secretkey" size="60" maxlength="60" value="' . esc_html($v3_secretkey) . '"></td></tr>';
        $str_html .= '</tbody></table>';
        $str_html .= '<input type="submit" name="submit" id="submit" class="button button-primary" value="変更を保存">'; 
        $str_html .= '</form>';
        $str_html .= '</div>';
        echo $str_html;
    }

    public function my_admin_footer_script() {
		$page = filter_input( INPUT_GET, 'page' );       

        if ( $page === 'recaptcha' ) {
            echo '<script>
            let use_recaptcha = document.getElementById("ctx_use_recaptcha");
            let v3_sitekey = document.getElementById("ctx_v3_sitekey");
            let v3_secretkey = document.getElementById("ctx_v3_secretkey");  
            let checked = use_recaptcha.checked;
            
            if ( checked ) {
                v3_sitekey.readOnly = false;
                v3_secretkey.readOnly = false;
            } else {
                v3_sitekey.readOnly = true;
                v3_secretkey.readOnly = true;
            }

            use_recaptcha.addEventListener("change", function () {
                if ( use_recaptcha.checked ) {
                    v3_sitekey.readOnly = false;
                    v3_secretkey.readOnly = false;
                } else {
                    v3_sitekey.readOnly = true;
                    v3_secretkey.readOnly = true;
                }
            } );        
            </script>'.PHP_EOL;
        }
    }


    public static function activation() {
    } 

    public static function deactivation() {
    }

    public static function uninstall() {
		global $wpdb; 

        delete_option( 'contactx' );

		$sql = "SELECT ID FROM $wpdb->posts WHERE post_type = %s";
        $query = $wpdb->prepare( $sql, 'contactx_post' );
        $postids = $wpdb->get_col( $query );
       
        foreach ( $postids as $id ) {
            $ret = wp_delete_post( $id , true);
            if ( !$ret ) {
                echo 'Error : delete failed';
            }
        }  
    }

}


$contactx_form = new Contactx();


function show_cxform() {
    $save_post_data = get_option( 'ctx_save_post_data' );
    $use_recaptcha = get_option( 'ctx_use_recaptcha' );
    $v3_sitekey = get_option( 'ctx_v3_sitekey' );
    $v3_secretkey = get_option( 'ctx_v3_secretkey' );
    $args = array(
        'save_post_data' => $save_post_data,
        'use_recaptcha' => $use_recaptcha,
        'v3_sitekey' => $v3_sitekey,
        'v3_secretkey' => $v3_secretkey,               
    );

    require_once 'includes/class-contactx-form.php';
    $template = new Contactx_Form( $args );
    return $template->show();
}
add_shortcode( 'cxform', 'show_cxform' );
