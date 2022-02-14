<?php
/**
 * The template for displaying contact form
 */

class Contactx_Form {

	const show_confirm = false;
	const max_sender_name = 60;
	const max_sender_email = 60;
	const max_send_message = 500;

	private $save_post_data = 0; // save post data
	private $use_recaptcha = 0; // use reCAPTCHA
	private $v3_sitekey = ''; // reCAPTCHA v3 Site Key
	private $v3_secretkey = '';	// reCAPTCHA v3 Secret Key
	private $sender_name = '';
	private $sender_email = '';
	private $send_message = '';
	private $page_flag = 0;
	private $ticket = null;
	private $errors = array();
	private $readonly = false;
	private $submit_button = '';
	private $return_button = '';
	private $confirm_button = '';
	private $str_html = '';
	private $post;

    public function __construct( $args = "" ) {
		if ( is_array( $args ) ) {
			$this->save_post_data = $args['save_post_data'];
			$this->use_recaptcha = $args['use_recaptcha'];
			$this->v3_sitekey = $args['v3_sitekey'];
			$this->v3_secretkey = $args['v3_secretkey'];
		}

		$this->set_button();
	}

	private function set_button() {

		if ( self::show_confirm ) {
			$this->return_button = '<input type="submit" name="btn_return" id="btn-return" class="edit-button button" value="戻る">';
			$this->confirm_button = '<input type="submit" name="btn_confirm" id="btn-confirm" class="edit-button button" value="確認">';
		}

		if ( $this->use_recaptcha ) {
			$this->submit_button = '<input type="submit" name="btn_submit" id="btn-submit" class="edit-button button button-primary g-recaptcha" data-sitekey="' . $this->v3_sitekey .'" data-callback="onSubmit" data-action="submit" value="送信">';
			$this->posted = 'g-recaptcha-response';
		} else {
			$this->submit_button = '<input type="submit" name="btn_submit" id="btn-submit" class="edit-button button button-primary" value="送信">';
			$this->posted = 'btn_submit';
		}
	}

	private function set_ticket() {
		if ( !isset( $_SESSION['ticket'] ) ) {
			$this->ticket = bin2hex(random_bytes(32));
			$_SESSION['ticket'] = $this->ticket;
		} else {
			$this->ticket = $_SESSION['ticket'];
		}
	}
	
	private function set_page_flag() {

		if ( self::show_confirm ) {
			if ( !empty( $_POST['btn_confirm'] ) ) {
				$this->page_flag = 1;		
				$this->confirm_button = '';
			} elseif ( !empty( $_POST['g-recaptcha-response'] ) || !empty( $_POST['btn_submit'] ) ) {
				$this->page_flag = 2;
				$this->confirm_button = '';
				$this->return_button = '';
			} else {
				$this->page_flag = 0;
				$this->return_button = '';
				$this->submit_button = '';
			}
		} else {
			if ( !empty( $_POST['g-recaptcha-response'] ) || !empty( $_POST['btn_submit'] ) ) {
				$this->page_flag = 2;
			}
		}	
	}

	private function check_post() {

		if ( $this->page_flag !== 0 ) {
		if ( $_POST['ticket'] !== $_SESSION['ticket'] )  die( 'Access denied' );
//		if ( $_POST['ticket'] !== $_SESSION['ticket'] )  $this->str_html = 'Access denied';
		}

		$this->sender_name = filter_input( INPUT_POST, 'sender_name' );
		$this->sender_email = filter_input( INPUT_POST, 'sender_email' );
		$this->send_message = filter_input( INPUT_POST, 'send_message' );
		
		if ( $this->page_flag !== 0 ) {
//		if ( !empty( $_POST[$this->posted] ) ) {
			if ( $this->sender_name === '' ) {
				$this->errors[] = 'お名前は必須項目です。';
			} elseif ( mb_strlen( $this->sender_name ) > self::max_sender_name ) {
				$this->errors[] = 'お名前は' . self::max_sender_name . '文字以内にしてください。';
			}
		
			if ( $this->sender_email === '' ) {
				$this->errors[] = 'メールアドレスは必須項目です。';
			} elseif ( mb_strlen( $this->sender_email ) > self::max_sender_email ) {
				$this->errors[] = 'メールアドレスは' . self::max_sender_email . '文字以内にしてください。';
			} elseif ( !filter_var( $this->sender_email, FILTER_VALIDATE_EMAIL ) ) {
				$this->errors[] = '正しいメールアドレスを指定してください。';
			}
		
			if ( $this->send_message === '' ) {
				$this->errors[] = 'お問い合わせ内容は必須項目です。';
			} elseif ( mb_strlen( $this->send_message ) > self::max_send_message ) {
				$this->errors[] = 'お問い合わせ内容は' . self::max_send_message . '文字以内にしてください。';
			}

			if ( $this->use_recaptcha ) {
				if ( isset( $_POST['g-recaptcha-response'] ) ) {
					$g_recaptcha_response = $_POST['g-recaptcha-response'];
				} else {
					$g_recaptcha_response = '';					
				}
				$recap_response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $this->v3_secretkey . '&response=' . $g_recaptcha_response);
				$recap_obj = json_decode( $recap_response );
				if ( $recap_obj->success === false ) {
					$this->errors[] = $recap_response;
				}
			}
		}

		$strError = '';
		if ( $this->errors ) {

			foreach ( $this->errors as $error ) {
				$strError .= '<p>' . $error . '</p>';
			}

			$this->page_flag = 0;
			if ( self::show_confirm ) {
				$this->set_button();		
				$this->return_button = '';
				$this->submit_button = '';
			}

		}
		$this->str_html .= $strError;
	}

	private function get_readonly() {
		$readonly_html = '';
		if ( $this->readonly ) {
			$readonly_html = 'readonly';
		}
		return $readonly_html;
	}

	public function show() {

		$this->set_page_flag();
		$this->set_ticket();
		$this->check_post();

		switch ( $this->page_flag ) {
			case 0 :
				$this->input_form();
				break;
			case 1 :
				$this->readonly = true;
				$this->str_html .= '<p>フォームの内容は読み取り専用です、修正する場合は戻るを押してください。</p>';
				$this->str_html .= '<p>この内容でよろしければ送信を押してください。</p>';
				$this->input_form( $this->readonly );
				break;
			case 2 :
				$this->submit();
		}

		$this->set_recaptcha_script();

		return $this->str_html;
	}

	private function input_form( $readonly = false ) {
		
		$this->str_html .= '<form id="contact-form" action="" method="post" accept-charset="UTF-8">';
		$this->str_html .= '<div>';
		$this->str_html .= '<label for="sender_name" class="form-required">お名前</label>';
		$this->str_html .= '<input type="text" name="sender_name" id="sender-name" class="form-required" ' . $this->get_readonly() . ' value="' . esc_html( $this->sender_name ) . '" required="required" size="60" maxlength="' . self::max_sender_name . '">';
		$this->str_html .= '</div>';
		$this->str_html .= '<div>';
		$this->str_html .= '<label for="sender_email" class="form-required">メールアドレス</label>';
		$this->str_html .= '<input type="email" name="sender_email" id="sender-email" class="form-required" ' . $this->get_readonly() . ' value="' . esc_html( $this->sender_email ) .'" required="required" size="60" maxlength="' . self::max_sender_email . '">';
		$this->str_html .= '</div>';
		$this->str_html .= '<div>';
		$this->str_html .= '<label for="send_message" class="form-required">お問い合わせ内容</label>';
		$this->str_html .= '<textarea name="send_message" id="send-message" class="form-required" rows="10"  cols="60" required="required" ' . $this->get_readonly() . ' maxlength="' . self::max_send_message . '" placeholder="お問い合わせ内容は' . self::max_send_message . '文字以内でお願いします。">' . esc_textarea( $this->send_message ) . '</textarea>';
		$this->str_html .= '</div>';
		$this->str_html .= '<div>';
		$this->str_html .= '<input type="hidden" name="ticket" value="' . $this->ticket .'">';
		$this->str_html .= $this->return_button;
		$this->str_html .= $this->confirm_button;
		$this->str_html .= $this->submit_button;
		$this->str_html .= '</div>';
		$this->str_html .= '</form>';

	}

	private function submit() {

		$mail_to = get_bloginfo('admin_email');
		$mail_subject = get_bloginfo('name') . "へのお問い合わせがありました";
		$header = "From: " . mb_encode_mimeheader( get_bloginfo('name') ) . "<" . get_bloginfo('admin_email') . ">";
		$mail_body = "お名前: " . $this->sender_name . "\nメールアドレス: " . $this->sender_email . "\n問い合わせ内容: \n" . $this->send_message;

//		mb_language("Japanese");
//		mb_internal_encoding("UTF-8");
//		$mail_body = mb_convert_encoding( $mail_body, "ISO-2022-JP-MS", "UTF-8" );

		if ( wp_mail( $mail_to, $mail_subject, $mail_body, $header ) ) {
			$this->str_html .= "<h2>お問い合わせを送信しました。</h2>";
			$this->str_html .= "<p>お問い合わせいただきありがとうございます。</p>";
		} else {
			$this->str_html .= "<h2>お問い合わせの送信に失敗しました。</h2>";
			$this->str_html .= "<p>ご迷惑をおかけして誠に申し訳ございません。</p>";
			$this->str_html .= "<p>しばらくしてから、もう一度お試しください。</p>";
		}

		if ( $this->save_post_data ) {
			$post = array(
				'post_title' => $this->sender_name . '<' . $this->sender_email . '>',
				'post_content' => $mail_body,
			);
			if ( !$this->save_post( $post ) ) {
				$this->str_html .= "<p>データの保存に失敗しました。</p>";
			}
		}

		if( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();		
		}

	}

	private function save_post( $post ) {

		$this->post = new Contactx_Post();
        return $this->post->add_post( $post );

    }
	
	private function set_recaptcha_script() {
		$str_script = "";		
		if ( $this->use_recaptcha ) {			
			if ( (!self::show_confirm) || (self::show_confirm && $this->page_flag === 2) ) {
				$str_script = "<script>";
				$str_script .= "function onSubmit(token) {";
				$str_script .= "document.getElementById('contact-form').submit();";
				$str_script .= "}";
				$str_script .= "</script>";	
			}
		}
		$this->str_html .= $str_script;
	}
}
