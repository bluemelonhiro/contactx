<?php

class Contactx_Post {

    const post_type = 'contactx_post';
    const post_status = 'publish';

    private $post;

//    public function __construct( $post ) {
	public function __construct() {


        add_action( 'init', array( $this, 'register_post_type' ) ); 
 /*
		if ( ! empty( $post ) ) {

            $this->post = array(
                'post_type' => self::post_type,
                'post_status' => self::post_status,
                'post_title' => $post['post_title'],
                'post_content' => $post['post_content'],
                'post_name' => self::post_type,
			);
		}
*/
	} 

	public function register_post_type() {

		register_post_type(
            self::post_type,
            array(
			    'labels' => array(
                    'name' => self::post_type,
			    ),
				'public' => true,
				'rewrite' => false,
			    'query_var' => false,

		    )
        );
	}

//    public function add_post() {
	public function add_post( $post ) {
		if ( empty( $post ) ) {
			return;
		}

		$this->post = array(
            'post_type' => self::post_type,
            'post_status' => self::post_status,
            'post_title' => $post['post_title'],
            'post_content' => $post['post_content'],
            'post_name' => self::post_type,
		);

		$post_id = wp_insert_post( $this->post );
        return $post_id;
	}

	public static function count( $args = '' ) {
		if ( $args ) {
			$args = wp_parse_args( $args, array(
				'post_type' => self::post_type,
				'post_status' => self::post_status,
			) );
		}
		$post_count = count( get_posts( $args ) );

		return $post_count;
	}

	public static function find( $args = '' ) {
		$defaults = array(
			'post_type' => self::post_type,
			'post_status' => self::post_status,
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

	public function trash() {
		if ( empty( $this->id ) ) {
			return;
		}

		if ( ! EMPTY_TRASH_DAYS ) {
			return $this->delete();
		}

		$post = wp_trash_post( $this->id );

		return (bool) $post;
	}

	public function untrash() {
		if ( empty( $this->id ) ) {
			return;
		}

		$post = wp_untrash_post( $this->id );

		return (bool) $post;
	}

	public function delete() {
		if ( empty( $this->id ) ) {
			return;
		}

		if ( $post = wp_delete_post( $this->id, true ) ) {
			$this->id = 0;
		}

		return (bool) $post;
	}

}
