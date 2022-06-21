<?php
/**
 * WP_List_Tableをextends
 * 管理画面で一覧表示を行う。
 */

//if(!class_exists('WP_List_Table')){
//    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
//}

class Contactx_List_Table extends WP_List_Table {

    private $is_trash = false;

    /**
	 * 初期化時の設定を行う
     * @param array $args
	 */
	public function __construct( $args = array() ) {

		parent::__construct( array(
				'singular'  => 'post', 
				'plural' 	=> 'posts',
				'ajax'      => false, //does this table support ajax?
				'screen' 	=> isset( $args['screen'] ) ? $args['screen'] : null,
			) 
		);
	}

	/**
     * prepare_items()から呼び出し
	 * 表で使用されるカラム情報の連想配列を返す
     * @see Contactx_List_Table::prepare_items()
     * 
	 * @return array $columns column items
	 */
	public function get_columns() {
		$columns = array(
			'cb'			=> '<input type="checkbox" />',
			'post_title'	=> '送信者',
			'post_content'	=> '内容',
			'post_date'		=> '日付',
		);
		return $columns;
	}

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'],  
            $item['ID'],
        );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'post_title':
			case 'post_content':
            case 'post_date':
                return esc_html( $item[$column_name] );			
            default:
				//Show the whole array for troubleshooting purposes
                return print_r( $item, true ); 
        }
    }

    /**
     * prepare_items()から呼び出し
	 * 並び替え可能なカラム$sortable_columnsを配列として設定
     * @see Contactx_List_Table::prepare_items()
     * @return array $sortable_columns
	 */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'post_title' 	=> array('post_title', false), //true means it's already sorted
            'post_content' 	=> array('post_content', false),
            'post_date'  	=> array('post_date', false),
        );
        return $sortable_columns;
    }

    public function get_bulk_actions() {
            
		if ( $this->is_trash ) {
			$actions['untrash'] = __( '復元' );
            $actions['delete'] = __( 'Delete Permanently' );
		} else {
            $actions['trash'] = __( 'ゴミ箱へ移動' );
        }
        return $actions;
    }

    public function process_bulk_action() {
        
        //Detect when a bulk action is being triggered...
        if( 'delete' === $this->current_action() ) {
            wp_die( 'Items deleted (or they would be if we had items to delete)!' );
        }
        
    }

    protected function handle_row_actions( $item, $column_name, $primary )
    {
        $args = array('post'=>$item['ID'], 'action'=>'edit');
        $nonce = wp_create_nonce();

        if( $column_name === $primary )
        {
            $edit_link =  sprintf( '<a href="%s">%s</a>', get_edit_post_link( $item['ID'] ), __( 'Edit' ) );
            $trash_link = sprintf( '<a href="%s">%s</a>', get_delete_post_link( $item['ID'] ), _x( 'Trash', 'verb' ));
            $untrash_link = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'post.php?post=' . $item['ID'] . '&amp;action=untrash' ), 'untrash-post_' . $item['ID'] ),  __( 'Restore' ) );
            $delete_link = sprintf( '<a href="%s">%s</a>', get_delete_post_link( $item['ID'] , '', true ), __( 'Delete Permanently' ) );

            if ( $this->is_trash ) {
                $actions = array(
                    'untrash' => $untrash_link,
                    'delete' => $delete_link,
                );
            } else {
                $actions = array(
                    'edit' => $edit_link,
                    'trash' => $trash_link,
                );              
            }
        
            return $this->row_actions( $actions );
        }

    }

	protected function get_edit_link( $args, $label, $class = '' ) {
		$url = add_query_arg( $args, 'post.php' );

		$class_html   = '';
		$aria_current = '';

		if ( ! empty( $class ) ) {
			$class_html = sprintf(
				' class="%s"',
				esc_attr( $class )
			);

			if ( 'current' === $class ) {
				$aria_current = ' aria-current="page"';
			}
		}

		return sprintf(
			'<a href="%s"%s%s>%s</a>',
			esc_url( $url ),
			$class_html,
			$aria_current,
			$label
		);
	}

    public function get_post_action() {
        $get_post_action = filter_input( INPUT_GET, 'action' );
        $post_action = '';
        if ( 'trash' === $get_action) {
			$post_action = 'trash';
        }
        return $post_action;
    }

    /**
     * prepare_items()から呼び出し
	 * $_GET変数の'post_status'を取得し、表示するデータのステータス$post_statusに代入
     * すべて('draft')またはゴミ箱('trash')に区分
     * 
     * @see Contactx_List_Table::prepare_items()
     * @return string $post_status
	 */
    public function get_post_status() {
        $get_post_status = filter_input( INPUT_GET, 'post_status' );
		if ( 'trash' === $get_post_status) {
			$post_status = 'trash';
            $this->is_trash = true;
		} else {
			$post_status = 'draft';
// 'any'では上手くデータ取得出来なかったか？            
//			$post_status = 'any';
        }
        return $post_status;
    }

    /**
     * contactx.phpから呼び出し
	 * データベースから$per_pageで指定した件数のデータの読み込み、
     * $this->itemsに配列で格納する
     * 
     * @see contactx.php
	 */
    public function prepare_items() {
		// This is used only if making any database queries
		global $wpdb; 

        // First, lets decide how many records per page to show
        $per_page = 5;

        $post_status = $this->get_post_status();        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();       

        $this->_column_headers = array($columns, $hidden, $sortable);

        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        $this->process_bulk_action();

        $sql = "SELECT ID, post_title, post_content, post_date FROM $wpdb->posts WHERE post_status = %s AND post_type = %s";
        $query = $wpdb->prepare( $sql, $post_status, 'contactx_post' );
		$data = $wpdb->get_results( $query, 'ARRAY_A' );

        function usort_reorder($a,$b){
            $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'post_date'; //If no sort, default to title
            $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc'; //If no order, default to asc
            $result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
            return ($order==='asc') ? $result : -$result; //Send final sort direction to usort
        }
        usort($data, 'usort_reorder');
        
        $current_page = $this->get_pagenum();
        $total_items = count( $data );
        $data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

        $this->items = $data;

        $this->set_pagination_args(array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }
	
    /**
     * 親クラスWP_List_Tableのviews()から呼び出し、
	 * 戻り値$status_links（配列）
     * テーブルの上部に表示する文字列を生成する
     * 
     * @see WP_List_Table::views()
     * @return array $status_links
	 */
	protected function get_views() {

		$ctx_post = new Contactx_Post();

        $post_status = $this->get_post_status();   
        $status_links = array();

        $args = array( 'post_status' => 'any' );
        $posts_in_inbox = $ctx_post->count( $args );

		$inbox = sprintf('%s<span class="count">(%s)</span>',
			__( 'すべて' ),
			number_format_i18n( $posts_in_inbox )
		);

		$status_links['inbox'] = sprintf( '<a href="%1$s"%2$s>%3$s</a>',
            esc_url( add_query_arg(
                array(
                    'post_status' => 'draft',
                ),
                menu_page_url( 'contactx', false )
            ) ),
			( $post_status == 'draft' ) ? ' class="current"' : '',
			$inbox );

		// Trash
        $args = array( 'post_status' => 'trash' );
//        $posts_in_trash = $ctx_post->count($args);
        $posts_in_trash = Contactx_Post::count( $args );

		if ( empty( $posts_in_trash ) ) {
//			return $status_links;
		}

		$trash = sprintf( '%s<span class="count">(%s)</span>',
            __( 'ゴミ箱' ),
			number_format_i18n( $posts_in_trash )
		);

		$status_links['trash'] = sprintf( '<a href="%1$s"%2$s>%3$s</a>',
			esc_url( add_query_arg(
				array(
					'post_status' => 'trash',
				),
				menu_page_url( 'contactx', false )
			) ),
			'trash' == $post_status ? ' class="current"' : '',
			$trash
		);

		return $status_links;
    }

    /**
     * 親クラスWP_List_Tableのviews()をそのまま実行するので不要だが、
	 * 分かり易い様に記述しておく
     * contactx.phpより呼び出し
     * 最後にテーブル上部のステータス選択を出力する
     * 出力内容はget_views()で設定
     * 
     * @see WP_List_Table::views() contactx.php
	 */
    public function views() {
        parent::views();
    }

    /**
     * 親クラスWP_List_Tableのdisplay()をそのまま実行するので不要だが、
	 * 分かり易い様に記述しておく
     * contactx.phpより呼び出し
     * 最後にテーブル表示の内容を出力する
     * 
     * @see WP_List_Table::display() contactx.php
	 */
    public function display() {
        parent::display();
    }

}