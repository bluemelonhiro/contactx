<?php
/*
Plugin Name: ContactX
Description: simple contact form plugin.
Author: Hiroyuki Teshima
Version: 1.0
*/

/* 
問い合わせフォームを利用する為のプラグイン、初めて自作した物なので、
後でソースを見ても分かるように丁寧にコメントを残す。
*/


class Contactx {

    const PROPERTIES_NAME = 'contactx';
	const DOMAIN_NAME = 'ContactX';
    // $wp_list_table　管理画面のテーブル表示用のクラスとして使用
	private $wp_list_table;

	public function __construct() {

        // WordPress標準の管理画面のテーブル表示用のクラスWP_List_Tableを読み込み
        if ( ! class_exists( 'WP_List_Table' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
        }
        // メール送信用のクラスを読み込み
        require_once 'includes/class-contactx-post.php';
        // 投稿フォーム用のクラスを読み込み
        require_once 'includes/class-contactx-form.php';
        // 管理画面のテーブル表示用のクラスを読み込み
        require_once 'includes/class-contactx-list-table.php';

        // 管理メニューに追加
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        // プラグインの設定を追加
        add_action( 'admin_init', array( $this, 'register_my_setting' ) );
        // 管理画面フッターにスクリプトを追加
        add_action( 'admin_print_footer_scripts', array( $this, 'my_admin_footer_script' ) );

        // プラグインを有効化した際に実行される処理'Contactx::activation'を追加
        if ( function_exists( 'register_activation_hook' ) ) {
            register_activation_hook( __FILE__ , 'Contactx::activation' );
        }
        // プラグインを無効化した際に実行される処理'Contactx::deactivation'を追加
        if ( function_exists( 'register_deactivation_hook' ) ) {
            register_deactivation_hook( __FILE__, 'Contactx::deactivation' );
        }
        // プラグインを削除した際に実行される処理'Contactx::uninstall'を追加
        if ( function_exists( 'register_uninstall_hook' ) ) {
            register_uninstall_hook( __FILE__, 'Contactx::uninstall' );
        }
        
        // セッションが開始されていない場合はセッションを開始する
        if ( session_status() !== PHP_SESSION_ACTIVE ) {
			session_start();
            session_regenerate_id( true );
        }
    }

    // 管理メニューを追加
	public function admin_menu() {
        // メニューページを追加
        add_menu_page(
            self::DOMAIN_NAME, // page_title
			self::DOMAIN_NAME, // menu_title
			'administrator', // capability
			self::PROPERTIES_NAME, // menu slug
			array( $this, 'load' ), // function
            'dashicons-email', // icon
        );
        // サブメニューページを追加
        add_submenu_page(
            self::PROPERTIES_NAME, // parent menu slug
            self::DOMAIN_NAME . ' Setting', // page_title
			self::DOMAIN_NAME . ' Setting', // menu_title
			'administrator', // capability
			self::PROPERTIES_NAME . 'setting', // menu slug
			array( $this, 'contactx_setting' ), // function
        );
    }

    // プラグインのオプション値を登録
    public function register_my_setting() {
        register_setting(
            'contactx', // option_group
            'ctx_save_post_data', // option_name
        );
        // reCaptchaを使用するか
        register_setting( 'contactx', 'ctx_use_recaptcha' );
        // reCaptchaV3のサイトキー
        register_setting( 'contactx', 'ctx_v3_sitekey' );
        // reCaptchaV3のシークレットキー
        register_setting( 'contactx', 'ctx_v3_secretkey' );
    }

    // load() から呼び出し、管理画面で投稿データ一覧を表示する処理を読み込む
    private function doaction() {
        // $_POSTに'action'の値がある場合は変数$actionに代入する、値がない場合はnullを代入
        $action = filter_input( INPUT_POST, 'action' );
        // 管理画面から呼び出されたかをチェック
        // $_POSTに'action'の値があるか、かつ、check_admin_referer()関数によるチェック、さらに、投稿データ$_POST['post']が配列の場合に処理を実行する
        if ( isset( $_POST['action'] ) && check_admin_referer('bulk-posts') && is_array( $_POST['post'] ) ) {
            foreach ( $_POST['post'] as $id ) {
                // 投稿データからContactx_Post()クラスを生成
                $ctx_post = new Contactx_Post();
                $ret = $ctx_post->doaction( $id, $action );
            }
            if ( $ret ) {

            }
        }
    }

    // admin_menu() から呼び出し
    // 投稿データ一覧を表示する
	public function load() {

        $this->doaction();
        // テーブル表示用のクラスContactx_List_Tableを生成
        // 一覧を出力する
        $this->wp_list_table = new Contactx_List_Table( array( 'screen'=>self::PROPERTIES_NAME ) );
        $this->wp_list_table->prepare_items();

        $page = esc_attr(filter_input( INPUT_GET, 'page' ) );

        echo '<div class="wrap">';
        echo '<h2>ContactX Messages</h2>';
       echo '<form method="post" id="contactx-filter" action="">';
// WordPressのフォームではmethod="get"が使われいるがgetにする理由が見つからないのでpostで行う
//        echo '<form method="get" id="contactx-filter" action="">';    
//        echo '<input type="hidden" name="page" value="' . $page . '">';    
        $this->wp_list_table->views(); 
        $this->wp_list_table->display();
        echo '</form>';
        echo '</div>';
    }

    // add_submenu_page()から呼び出し
    // プラグイン設定を保存、画面を出力する
    public function contactx_setting() {
        // 表示するメッセージ $messageを初期化
        $message = '';
        // 「変更を保存」ボタンが押された場合のメッセージ
        if ( isset( $_POST['submit'] ) && check_admin_referer('contactx_setting') ) {
            $message = '<div class="notice notice-success settings-error is-dismissible"><p>設定を保存しました。</p></div>';
        }
        // 設定項目'save_post_data'投稿データを保存するかの値を更新
        if ( isset( $_POST['ctx_save_post_data'] ) ) {
            update_option( 'ctx_save_post_data', $_POST['ctx_save_post_data'] );
        }
        // 設定項目'use_recaptcha'recaptchaを使用するかの値を更新
        if ( isset( $_POST['ctx_use_recaptcha'] ) ) {
            update_option( 'ctx_use_recaptcha', $_POST['ctx_use_recaptcha'] );
        }
        // 設定項目'v3_sitekey'recaptchaV3サイトキーの値を更新
        if ( isset( $_POST['ctx_v3_sitekey'] ) ) {
            update_option( 'ctx_v3_sitekey', $_POST['ctx_v3_sitekey'] );
        }
        // 設定項目'v3_secretkey'recaptchaV3シークレットキーの値を更新
        if ( isset( $_POST['ctx_v3_secretkey'] ) ) {
            update_option( 'ctx_v3_secretkey', $_POST['ctx_v3_secretkey'] );
        }
        
        // 以下は設定フォームに表示する値を変数に読み込む処理
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

        // 設定フォームを出力
        $str_html = '';
        $str_html .= '<div class="wrap">';
        $str_html .= '<h2>ContactX Setting</h2>';
        $str_html .=  $message;
        $str_html .= '<p>投稿フォームを表示する場合はショートコード[cxform]を使用してください。</p>';
        $str_html .= '<form id="contactx-recaptcha" action="' . menu_page_url( 'contactxsetting', false ) . '" method="post">';
        // 管理画面のセキュリティチェック用の値nonceを設定
        $str_html .= wp_nonce_field( 'contactx_setting' );
        $str_html .= '<table class="form-table"><tbody>';
        $str_html .= '<tr><th scope="row"><label for="ctx_save_post_data">save_post_data</label></th>';
        $str_html .= '<input type="hidden" name="ctx_save_post_data" value="0">';   
        $str_html .= '<td><input type="checkbox" id="ctx_save_post_data" name="ctx_save_post_data" value="1" ' . $save_post_data . '></td></tr>';
        $str_html .= '<tr><th scope="row"><label for="ctx_use_recaptcha">use_recaptcha</label></th>';
        $str_html .= '<input type="hidden" name="ctx_use_recaptcha" value="0">';   
        $str_html .= '<td><input type="checkbox" id="ctx_use_recaptcha" name="ctx_use_recaptcha" value="1" ' . $use_recaptcha . '></td></tr>';
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

    // __construct()から呼び出し
    // use_recaptchaのチェックを入れた時に
    // v3_sitekey、v3_secretkeyの読込専用を解除する
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

    // __construct()から呼び出し
    // uninstall() 以外の関数は使用していないが、必要に応じ追加する
    public static function activation() {
    } 

    public static function deactivation() {
    }
    // プラグイン削除時に実行される処理  
    public static function uninstall() {
		global $wpdb; 
        // wp_optionテーブルから'contactx'のデータを削除
        delete_option( 'contactx' );
        // wp_postsテーブルからpost_typeが'contactx_post'のデータを削除
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


// contctx.php　読み込み時に最初に実行
// Contactxクラスを生成
$contactx = new Contactx();


// 初期化時に実行、ポストタイプを登録
// Contactx_Postクラスのregister_post_type()を実行
add_action( 'init', function() {
    Contactx_Post::register_post_type();
	do_action( 'contatx_init' );
}, 10, 0 );

// add_shortcode()でショートコード[cxform]を登録
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
    // Contactx_Formクラスを生成、引数$argsはプラグインオプション値を
    $ctx_form = new Contactx_Form( $args );
    // 投稿フォームを出力
    return $ctx_form->show();
}
add_shortcode( 'cxform', 'show_cxform' );

// template_redirectに登録
// 管理画面のリダイレクトuriを設定
function ctx_redirect() {
    if ( is_admin() ) {
        wp_redirect( get_admin_url() . 'admin.php?page=contactx' );
        exit();
    }
}
//add_action( 'template_redirect', 'ctx_redirect' );