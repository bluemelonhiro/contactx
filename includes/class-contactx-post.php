<?php
/**
 * 投稿データを保存するPOST_TYPE、'contactx_post'のクラス
 * contactx.php、class-contactx-form.php、class-contactx-list-table.phpから呼び出される
 */

class Contactx_Post {

    const POST_TYPE = 'contactx_post';
    const POST_STATUS = 'draft';
    private $post;

	public function __construct() {

	} 

	// register_POST_TYPE()でPOST_TYPEを登録する
	public static function register_post_type() {

		register_post_type(
			POST_TYPE,
      array(
				'labels' => POST_TYPE,
				'public' => true,// 管理メニューに表示
				'description' => '',
				'rewrite' => false,
			  'query_var' => false,
				'show_in_rest' => true,
				'show_in_menu' => false,
				'supports' => array(
					'title',
					'editor',
				),

		  )
    );
	}

  /**
   * class-contactx-form.phpのsave_post()から呼び出し
	 *
   * @see Contactx_Form::save_post()
   * @return int $post_id
	 */
	public function add_post( $post ) {
		if ( empty( $post ) ) {
			return;
		}

		$this->post = array(
            'post_type' => self::POST_TYPE,
            'post_status' => self::POST_STATUS,
            'post_title' => $post['post_title'],
            'post_content' => $post['post_content'],
            'post_name' => self::POST_TYPE,
		);

		$post_id = wp_insert_post( $this->post );
    return $post_id;
	}

	public static function count( $args = '' ) {
		if ( $args ) {
			$args = wp_parse_args( $args, array(
				'post_type' => self::POST_TYPE,
				'post_status' => self::POST_STATUS,
			) );
		}
		$post_count = count( get_posts( $args ) );

		return $post_count;
	}

	public static function find( $args = '' ) {
		$defaults = array(
			'POST_TYPE' => self::POST_TYPE,
			'POST_STATUS' => self::POST_STATUS,
		);

		$args = wp_parse_args( $args, $defaults );

		$q = new WP_Query();
		$posts = $q->query( $args );

		self::$found_items = $q->found_posts;

		$objs = array();

		foreach ( (array) $posts as $post ) {
			$objs[] = new self( $post );
		}

		return $objs;
	}

	public function doaction( $id, $action ) {
		if ( $action === 'trash' ) {
			$ret = $this->trash( $id );
		} elseif ( $action === 'untrash' ) {
			$ret = $this->untrash( $id );
		} elseif ( $action === 'delete' ) {
			$ret = $this->delete( $id );
		}
		return $ret;
	}

	private function trash( $id ) {
		if ( empty( $id ) ) {
			return;
		}

		if ( ! EMPTY_TRASH_DAYS ) {
			return $this->delete();
		}

		$post = wp_trash_post( $id );

		return (bool) $post;
	}

	private function untrash( $id ) {
		if ( empty( $id ) ) {
			return;
		}

		$post = wp_untrash_post( $id );

		return (bool) $post;
	}

	private function delete( $id ) {
		if ( empty( $id ) ) {
			return;
		}

		$post = wp_delete_post( $id, true );

		return (bool) $post;
	}

}
