<?php
/**
 * The template for displaying contact form
 * contact.php から呼び出し
 * 投稿フォームを生成、出力するクラス
 */
class Contactx_Form {

	// 定数を指定、必要に応じて変更する
	// 確認画面を表示する、初期値はfalse
	const show_confirm = false;
	// お名前テキストボックスの最大値、初期値は60文字
	const max_sender_name = 60;
	// メールアドレステキストボックスの最大値、初期値は60文字
	const max_sender_email = 60;
	// お問い合わせ内容テキストエリアの最大値、初期値は500文字
	const max_send_message = 500;

	// 変数を初期化
	private $save_post_data = 0; // save post data
	private $use_recaptcha = 0; // use reCAPTCHA
	private $v3_sitekey = ''; // reCAPTCHA v3 Site Key
	private $v3_secretkey = '';	// reCAPTCHA v3 Secret Key
	private $sender_name = ''; // お名前
	private $sender_email = ''; // メールアドレス
	private $send_message = ''; // お問い合わせ内容
	private $page_flag = 0; // ページ制御フラッグ
	private $ticket = null; // チケット
	private $errors = array(); // エラー（配列）
	private $readonly = false; // 読み取り専用、初期値はfalse
	private $submit_button = ''; // 確認ボタン
	private $return_button = ''; // 戻るボタン
	private $confirm_button = ''; // 送信ボタン
	private $str_html = ''; // 出力HTML内容
	private $post; // 

/**
	* コンストラクタ、引数$argsはプラグインのオプション値を受け取る
	* @param array $args array of plugin option arguments.
	*/
	public function __construct( $args = "" ) {
		if ( is_array( $args ) ) {
			$this->save_post_data = $args['save_post_data'];
			$this->use_recaptcha = $args['use_recaptcha'];
			$this->v3_sitekey = $args['v3_sitekey'];
			$this->v3_secretkey = $args['v3_secretkey'];
		}
		// フォームの投稿ボタンを設定
		$this->set_button();
	}

/**
	* コンストラクタから呼び出し、フォームの投稿ボタンを設定
	* @see Contactx_Form::__construct()
	*/
	private function set_button() {
		// 確認画面を表示する設定の場合
		if ( self::show_confirm ) {
			// 戻るボタンを表示
			$this->return_button = '<input type="submit" name="btn_return" id="btn-return" class="edit-button button" value="戻る">';
			// 確認ボタンを表示
			$this->confirm_button = '<input type="submit" name="btn_confirm" id="btn-confirm" class="edit-button button" value="確認">';
		}
		// recaptchaを使用する設定の場合
		if ( $this->use_recaptcha ) {
			// 送信ボタンの属性にrecaptcha用の値を追加
			$this->submit_button = '<input type="submit" name="btn_submit" id="btn-submit" class="edit-button button button-primary g-recaptcha" data-sitekey="' . $this->v3_sitekey .'" data-callback="onSubmit" data-action="submit" value="送信">';
			// ボタンのnameは'g-recaptcha-response'
			$this->posted = 'g-recaptcha-response';
		} else {
			// 送信ボタンの属性は通常の値を設定
			$this->submit_button = '<input type="submit" name="btn_submit" id="btn-submit" class="edit-button button button-primary" value="送信">';
			// ボタンのnameは'btn_submit'
			$this->posted = 'btn_submit';
		}
	}

/**
	* 投稿フォームを出力する
	* @see contactx.php
	*/
	public function show() {
		// page_flag、ticketの設定、投稿内容の検証を行う
		$this->set_page_flag();
		$this->set_ticket();
		$this->check_post();
		// page_flagに応じて出力内容を区別する
		switch ( $this->page_flag ) {
			// page_flagが0の場合
			case 0 :
				$this->input_form();
				break;
			// page_flagが1の場合
			case 1 :
				// フォームの上部にメッセージを追加し
				// 読取専用で投稿フォームを出力する
				$this->readonly = true;
				$this->str_html .= '<p>フォームの内容は読み取り専用です、修正する場合は戻るを押してください。</p>';
				$this->str_html .= '<p>この内容でよろしければ送信を押してください。</p>';
				$this->input_form( $this->readonly );
				break;
			// page_flagが2の場合	
			case 2 :
				//  投稿されたデータを処理する
				$this->submit();
		}
		// recaptchaで使用するスクリプトを設定する
		$this->set_recaptcha_script();
		// HTMLの内容を返す
		return $this->str_html;
	}

/**
	* show()から呼び出し
	* ページ遷移用フラッグを設定
	* @see Contactx_Form::show()
	* page_flag:0　投稿フォームを表示、戻るボタンから遷移した場合は投稿内容をフォームに表示
	* page_flag:1　show_confirmがtrueの場合のみ使用、投稿内容確認用の画面を表示する
	* page_flag:2　送信ボタンが押された場合は投稿結果を表示	
	*/
	private function set_page_flag() {
		// 確認画面を表示する設定の場合
		if ( self::show_confirm ) {
			// 確認ボタンが押された場合、page_flag = 1
			if ( !empty( $_POST['btn_confirm'] ) ) {
				$this->page_flag = 1;
				$this->confirm_button = '';
			// 送信ボタンが押された場合、page_flag = 2
			} elseif ( !empty( $_POST['g-recaptcha-response'] ) || !empty( $_POST['btn_submit'] ) ) {
				$this->page_flag = 2;
				$this->confirm_button = '';
				$this->return_button = '';
			// 初期画面、page_flag = 0
			} else {
				$this->page_flag = 0;
				$this->return_button = '';
				$this->submit_button = '';
			}
		// 確認画面を表示しない設定の場合
		} else {
			// 送信ボタンが押された場合、page_flag = 2
			if ( !empty( $_POST['g-recaptcha-response'] ) || !empty( $_POST['btn_submit'] ) ) {
				$this->page_flag = 2;
			}
		}	
	}

/**
	* show()から呼び出し
	* セッションにチケットを設定
	* @see Contactx_Form::show()
	*/
	private function set_ticket() {
		// セッションにチケットが設定されていない場合
		if ( !isset( $_SESSION['ticket'] ) ) {
			// 32バイトの乱数を生成し変数ticketに入力
			// セッションのチケットに設定
			$this->ticket = bin2hex(random_bytes(32));
			$_SESSION['ticket'] = $this->ticket;
		// セッションにチケットが設定されている場合
		} else {
			// セッションのチケットの値を変数ticketに入力
			$this->ticket = $_SESSION['ticket'];
		}
	}

/**
	* show()から呼び出し
	* フォームの投稿内容を検証する
	* @see Contactx_Form::show()
	*/
	private function check_post() {
		// page_flag が0以外の場合
		if ( $this->page_flag !== 0 ) {
			// チケットが一致しない場合は処理を終了する
			if ( $_POST['ticket'] !== $_SESSION['ticket'] )  die( 'Access denied' );
		}
		// $_POSTにsender_name等の値がある場合、変数に代入する
		$this->sender_name = filter_input( INPUT_POST, 'sender_name' );
		$this->sender_email = filter_input( INPUT_POST, 'sender_email' );
		$this->send_message = filter_input( INPUT_POST, 'send_message' );
		
		// page_flag が0以外の場合
		// 検証結果がエラーの場合も結果を一旦エラー変数に代入し処理を継続する
		if ( $this->page_flag !== 0 ) {
			// sender_nameの検証
			// sender_nameの値が空白の場合
			if ( $this->sender_name === '' ) {
				$this->errors[] = 'お名前は必須項目です。';
			// sender_nameの文字数が最大値を超えている場合
			} elseif ( mb_strlen( $this->sender_name ) > self::max_sender_name ) {
				$this->errors[] = 'お名前は' . self::max_sender_name . '文字以内にしてください。';
			}
			// sender_emailの検証
			if ( $this->sender_email === '' ) {
				$this->errors[] = 'メールアドレスは必須項目です。';
			} elseif ( mb_strlen( $this->sender_email ) > self::max_sender_email ) {
				$this->errors[] = 'メールアドレスは' . self::max_sender_email . '文字以内にしてください。';
			} elseif ( !filter_var( $this->sender_email, FILTER_VALIDATE_EMAIL ) ) {
				$this->errors[] = '正しいメールアドレスを指定してください。';
			}
			// send_messageの検証
			if ( $this->send_message === '' ) {
				$this->errors[] = 'お問い合わせ内容は必須項目です。';
			} elseif ( mb_strlen( $this->send_message ) > self::max_send_message ) {
				$this->errors[] = 'お問い合わせ内容は' . self::max_send_message . '文字以内にしてください。';
			}
			// recaptchaを使用する設定の場合
			if ( $this->use_recaptcha ) {
				// $_POST変数から'g-recaptcha-response'の値を取得
				if ( isset( $_POST['g-recaptcha-response'] ) ) {
					$g_recaptcha_response = $_POST['g-recaptcha-response'];
				} else {
					$g_recaptcha_response = '';	
				}
				// recaptchaのAPIに'g-recaptcha-response'の値を渡し、
				// 結果のファイルの内容をfile_get_contents()で文字列に読み込む
				$recap_response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $this->v3_secretkey . '&response=' . $g_recaptcha_response);
				// $recap_responseに代入したJSON 文字列をデコードする
				$recap_obj = json_decode( $recap_response );
				// 結果が正常の場合は$recap_obj->successが返される、エラーの場合は、$recap_responseにエラー内容が返される
				// エラーの内容をエラー変数に代入する
				if ( $recap_obj->success === false ) {
					$this->errors[] = $recap_response;
				}
			}
		}

		// 出力するエラー文字を初期化
		$strError = '';
		// エラー変数に値がある場合
		if ( $this->errors ) {
			// エラー変数の配列からエラーを取得し文字列に代入する
			foreach ( $this->errors as $error ) {
				$strError .= '<p>' . $error . '</p>';
			}
			// page_flagに0を代入し初期画面を表示する
			$this->page_flag = 0;
			if ( self::show_confirm ) {
				$this->set_button();		
				$this->return_button = '';
				$this->submit_button = '';
			}
		}
		// 出力するHTMLにエラーの内容を追加
		$this->str_html .= $strError;
	}

	// フォームの要素にreadonlyを追加する場合に使用
	private function get_readonly() {
		$readonly_html = '';
		if ( $this->readonly ) {
			$readonly_html = 'readonly';
		}
		return $readonly_html;
	}

/**
	* show() から呼び出し、投稿フォームのHTMLを生成する
	* 引数の$readonlyの初期値はfalse、trueの場合はinput要素に読取専用の属性を付ける
	* @see Contactx_Form::show()
	*
	* @param bool $readonly default false 
	*/
	private function input_form( $readonly = false ) {
		
		$this->str_html .= '<form id="contact-form" action="" method="post" accept-charset="UTF-8">';
		$this->str_html .= '<dl>';
		$this->str_html .= '<dt><label for="sender_name" class="form-required">お名前</label></dt>';
		$this->str_html .= '<dd><input type="text" name="sender_name" id="sender-name" class="form-required" ' . $this->get_readonly() . ' value="' . esc_html( $this->sender_name ) . '" required="required" size="60" maxlength="' . self::max_sender_name . '"></dd>';
		$this->str_html .= '<dt><label for="sender_email" class="form-required">メールアドレス</label></dt>';
		$this->str_html .= '<dt><input type="email" name="sender_email" id="sender-email" class="form-required" ' . $this->get_readonly() . ' value="' . esc_html( $this->sender_email ) .'" required="required" size="60" maxlength="' . self::max_sender_email . '"></dd>';
		$this->str_html .= '<dt><label for="send_message" class="form-required">お問い合わせ内容</label></dt>';
		$this->str_html .= '<dt><textarea name="send_message" id="send-message" class="form-required" rows="10"  cols="60" required="required" ' . $this->get_readonly() . ' maxlength="' . self::max_send_message . '" placeholder="お問い合わせ内容は' . self::max_send_message . '文字以内でお願いします。">' . esc_textarea( $this->send_message ) . '</textarea></dd>';
		$this->str_html .= '</dl>';
		$this->str_html .= '<div>';
		$this->str_html .= '<input type="hidden" name="ticket" value="' . $this->ticket .'">';
		$this->str_html .= $this->return_button;
		$this->str_html .= $this->confirm_button;
		$this->str_html .= $this->submit_button;
		$this->str_html .= '</div>';
		$this->str_html .= '</form>';

	}
	
/**
	* show() から呼び出す
	* 投稿データのメール送信
	* データベースに保存する処理を行う
	* @see Contactx_Form::show()
	*/
	private function submit() {
		// WordPressの設定から管理者のメールアドレスを取得
		$mail_to = get_bloginfo('admin_email');
		// WordPressの設定からサイト名を取得、メールの題名を作成
		$mail_subject = get_bloginfo('name') . "へのお問い合わせがありました";
		// サイト名、管理者のメールアドレスからメールヘッダーを作成
		$header = "From: " . mb_encode_mimeheader( get_bloginfo('name') ) . "<" . get_bloginfo('admin_email') . ">";
		// 投稿者名、メールアドレス、投稿内容からメール本文を作成
		$mail_body = "お名前: " . $this->sender_name . "\nメールアドレス: " . $this->sender_email . "\n問い合わせ内容: \n" . $this->send_message;

		// メールが文字化けした場合に見直す文字コードの設定
		// 現時点では文字化けはしない為、コメントアウト
		// mb_language("Japanese");
		// mb_internal_encoding("UTF-8");
		// $mail_body = mb_convert_encoding( $mail_body, "ISO-2022-JP-MS", "UTF-8" );

		// WordPressのメール送信関数を使用、不具合がある場合はPHPのmail()関数に置き換える
		// 引数にメールの内容を渡す、
		// 正常に送信できた場合
		if ( wp_mail( $mail_to, $mail_subject, $mail_body, $header ) ) {
			$this->str_html .= "<h2>お問い合わせを送信しました。</h2>";
			$this->str_html .= "<p>お問い合わせいただきありがとうございます。</p>";
		// 送信に失敗した場合
		} else {
			$this->str_html .= "<h2>お問い合わせの送信に失敗しました。</h2>";
			$this->str_html .= "<p>ご迷惑をおかけして誠に申し訳ございません。</p>";
			$this->str_html .= "<p>しばらくしてから、もう一度お試しください。</p>";
		}

		// 投稿データを保存する設定の場合
		// データ保存処理を行う
		if ( $this->save_post_data ) {
			// 引数$postの配列に値を代入
			$post = array(
				// 'post_title'にsender_name、sender_emailの文字列
				'post_title' => $this->sender_name . '<' . $this->sender_email . '>',
				// 'post_content'にmail_body
				'post_content' => $mail_body,
			);
			if ( !$this->save_post( $post ) ) {
				// データ保存に失敗した場合はメッセージを出力
				$this->str_html .= "<p>データの保存に失敗しました。</p>";
			}
		}
		// セッションステータスがアクティブの場合、セッションを破棄する
		if( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();		
		}

	}

/**
	* submit() から呼び出す
	* データの保存処理を行う
	* データベースに保存する処理を行う
	* @see Contactx_Form::submit()
	*
	* @param array $post post data
	*/
	private function save_post( $post ) {
		// Contactx_Postクラスを生成
		// 引数$postはsender_name、sender_email、$mail_bodyを配列にしたもの
		$this->post = new Contactx_Post();
    return $this->post->add_post( $post );
  }
		
	// show() から呼び出す
	// recaptchaで使用するスクリプトを出力する
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
