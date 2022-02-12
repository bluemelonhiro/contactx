<?php
/**
 * WP_List_Tableをextends
 * 管理画面で一覧表示を行う。
 */

//if(!class_exists('WP_List_Table')){
//    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
//}

class Contactx_List_Table extends WP_List_Table {

    /**
	 * 初期化時の設定を行う
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
	 * 表で使用されるカラム情報の連想配列を返す
	 * @return array
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

    public function get_sortable_columns() {
        $sortable_columns = array(
            'post_title' 	=> array('post_title', false), //true means it's already sorted
            'post_content' 	=> array('post_content', false),
            'post_date'  	=> array('post_date', false),
        );
        return $sortable_columns;
    }

    public function get_bulk_actions() {
        $actions = array(
            'delete' => 'Delete',
        );
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
        if( $column_name === $primary )
        {
			$actions = array(
				'edit'   => sprintf( '<a href="?page=%s&action=%s&post=%s">%s</a>', $_REQUEST['page'], 'edit', $item['ID'], __( 'Edit' ) ),
//                'edit'   => sprintf( '<a href="%s">%s</a>', get_edit_post_link( $item['ID'] ), __( 'Edit' ) ),
				'delete' => sprintf( '<a href="?page=%s&action=%s&post=%s">%s</a>', $_REQUEST['page'], 'trash', $item['ID'], __( 'ゴミ箱へ移動' ) ),
//                'delete'   => sprintf( '<a href="%s">%s</a>', get_delete_post_link( $item['ID'] ), __( 'Delete' ) ),
            );
            // div class = raw-actions がキモやね
            return $this->row_actions( $actions );
        }

    }

    public function get_post_action() {
        $get_post_action = filter_input( INPUT_GET, 'action' );
        $post_action = '';
        if ( 'trash' === $get_action) {
			$post_action = 'trash';
        }
        return $post_action;
    }

    public function get_post_status() {
        $get_post_status = filter_input( INPUT_GET, 'post_status' );
		if ( 'trash' === $get_post_status) {
			$post_status = 'trash';
		} else {
			$post_status = 'publish';
        }
        return $post_status;
    }

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
	
	protected function get_views() {

        require_once 'class-contactx-post.php';
		$ctx_post = new Contactx_Post();

        $post_status = $this->get_post_status();   
        $status_links = array();

        $args = array( 'post_status' => 'publish' );
        $posts_in_inbox = $ctx_post->count( $args );

		$inbox = sprintf('%s<span class="count">(%s)</span>',
			__( '受信トレイ' ),
			number_format_i18n( $posts_in_inbox )
		);

		$status_links['inbox'] = sprintf( '<a href="%1$s"%2$s>%3$s</a>',
            esc_url( add_query_arg(
                array(
                    'post_status' => 'publish',
                ),
                menu_page_url( 'contactx', false )
            ) ),
			( $post_status == 'publish' ) ? ' class="current"' : '',
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
}