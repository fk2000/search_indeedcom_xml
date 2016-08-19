<?php
$cookie_file_path = tempnam(sys_get_temp_dir(),'cookie_');

$scraping_obj = new Tabs_shopping_scraping($cookie_file_path);
$cookie_file_path = $scraping_obj->index();

//最終的に残ったクッキーを削除する。
unlink($cookie_file_path);

class Tabs_shopping_scraping
{
	const BASE_URL = "http://www.tabs-shopping.com/";
	const LOGIN_PAGE_URL = "http://www.tabs-shopping.com/customer/account/login";
	const POST_URL = "http://www.tabs-shopping.com/customer/account/loginPost/";

	private $pdo;
	private $cookie;

	function __construct($cookie_file_path = '') {
		$this->cookie = $cookie_file_path;
		//DBに接続
		try{
			$dsn = 'mysql:dbname=vagrant;host=localhost;charset=utf8';
			$user = 'root';
			$password = '';
			$this->pdo = new PDO($dsn, $user, $password);
		}catch( PDOException $e ){
			exit( $e->getMessage() );
			die();
		}
		$scraped_data_array = $this->move_login_page($cookie_file_path);
		$this->login($cookie_file_path);
	}

	public function move_login_page($file_path)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::LOGIN_PAGE_URL);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $file_path);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $file_path);
		$put = curl_exec($ch) or dir('error ' . curl_error($ch));
		curl_close($ch);

		sleep(5);
		return;
	}

	public function login($file_path)
	{
		$params = array(
			 "login[username]" => 'hogehoge',
			 "login[password]" => 'hogehoge',
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, self::POST_URL);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $file_path);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $file_path);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		$output = curl_exec($ch) or dir('error ' . curl_error($ch));
		curl_close($ch);

		sleep(5);
		return ;
	}

	public function index()
	{
		$get_contents = $this->get_scraping_contents();

		foreach ($get_contents as $key => $val) {
			$i=1;
			$url = $val . '?p=' . $i ;
			$is_next = $this->is_next_page($url);

			//1ページ目のスクレイピングを実行
			$get_data = $this->exec($url);
			$this->insert_data($get_data);

			//2ページ以上のページが存在する場合は、更に次ページのスクレイピングを実行
			if( $is_next ){
				//取得した商品データの配列の数が30未満の場合は、そのページがラストページであると判断し、スクレイピングを終える。
				//ラストページへ行くまで上と同様の処理を繰り返し行う。
				$is_last = false;
				while( count($get_data) >= 30 && !$is_last){
					$i++;
					$url = $val . '?p=' . $i;
					$get_data = $this->exec($url);
					$this->insert_data($get_data);

					$is_last = $this->is_last_page($url,$i);
				}
			}
		}
		return $this->cookie;
	}


	/**
	 * HOMEより、スクレイピングをするカテゴリのURLを取得する。
	 * @return [type] [description]
	 */
	private function get_scraping_contents()
	{
		$scraped_data_array = $this->get_scraped_data(self::BASE_URL);

		$data = array();
		//カテゴリwoman以下のサブカテゴリのURLを取得
		foreach ($scraped_data_array['body']['div'][2]['div']['ul']['li'][1]['ul']['li'][0]['ul']['li'] as $key => $val) {
			$data[] = $val['a']['@attributes']['href'];
		}
		foreach ($scraped_data_array['body']['div'][2]['div']['ul']['li'][1]['ul']['li'][1]['ul']['li'] as $key => $val) {
			$data[] = $val['a']['@attributes']['href'];
		}
		$data[] = $scraped_data_array['body']['div'][2]['div']['ul']['li'][1]['ul']['li'][2]['a']['@attributes']['href'];
		$data[] = $scraped_data_array['body']['div'][2]['div']['ul']['li'][1]['ul']['li'][3]['a']['@attributes']['href'];

		//カテゴリman以下のサブカテゴリのURLを取得
		foreach ($scraped_data_array['body']['div'][2]['div']['ul']['li'][2]['ul']['li'][0]['ul']['li'] as $key => $val) {
			$data[] = $val['a']['@attributes']['href'];
		}
		$data[] = $scraped_data_array['body']['div'][2]['div']['ul']['li'][2]['ul']['li'][1]['a']['@attributes']['href'];
		$data[] = $scraped_data_array['body']['div'][2]['div']['ul']['li'][2]['ul']['li'][2]['a']['@attributes']['href'];

		//カテゴリidea以下のサブカテゴリのURLを取得
		$data[] = $scraped_data_array['body']['div'][2]['div']['ul']['li'][4]['ul']['li'][0]['a']['@attributes']['href'];
		$data[] = $scraped_data_array['body']['div'][2]['div']['ul']['li'][4]['ul']['li'][1]['a']['@attributes']['href'];
		$data[] = $scraped_data_array['body']['div'][2]['div']['ul']['li'][4]['ul']['li'][2]['a']['@attributes']['href'];

		return $data;
	}

	/**
	 * 1ページ以上存在するかを調べる
	 * @param  [type]  $url [調べるURL]
	 * @return boolean      [description]
	 */
	private function is_next_page($url)
	{
		$scraped_data_array = $this->get_scraped_data($url,"//div[@class='pages']");

		if( !empty($scraped_data_array) ){
			return true;
		}else{
			return false;
		}
	}

	//現在のページが最終ページかを調べる。
	private function is_last_page($url,$current_page_num)
	{
		$scraped_data_array = $this->get_scraped_data($url,"//div[@class='pages']");
		$last_page_data = end($scraped_data_array[1]['ol']['li']);
		if( !is_array($last_page_data) && intval($last_page_data) === $current_page_num ){
			return true;
		}else{
			return false;
		}
	}


	/**
	 * 受け取った商品一覧ページのURLから商品詳細にアクセス。詳細ページでスクレイピングを行って、商品データを抽出
	 * @param  [type] $url [description]
	 * @return [type]      [description]
	 */
	private function exec($url)
	{
		$scraped_data_array = $this->get_scraped_data($url,"//div[@class='breadcrumbs']");

		$category_array = array();
		foreach ($scraped_data_array[0]['ul']['li'] as $key => $val) {
			if( $key !== 0 ){
				$category_array[] = isset($val['a']) ? $val['a'] : $val['strong'];
			}
		}

		$scraped_data_array = $this->get_scraped_data($url,"//div[@class='view-content']");

		$insert_data = array();
		$j = 0;
		foreach( $scraped_data_array as $key => $val ){

			foreach ($val['div'] as $_key => $_val) {

				if( $_key === 0 ){

					for ($k = 0; $k < 5; $k++) {
						$insert_data[$j]['category'][$k] = isset($category_array[$k]) ? $category_array[$k] : NULL;
					}

					$product_url = isset($_val['a']['@attributes']['href']) ? $_val['a']['@attributes']['href'] : NULL;
					$_url = $product_url . '?___store=en';
					// $_url = 'http://www.tabs-shopping.com/abito-over-in-fantasia-isola-marras-t9309tsxj891c91.html?___store=en';
					$insert_data[$j]['shop_id'] = 26;
					$insert_data[$j]['url'] = $_url;//商品URL

					if( isset($_url) && isset($product_url) ){

						$_scraped_data_array = $this->get_scraped_data($_url,"//div[@class='product-view']");

						//?___store=enを末尾につけたURLが存在しなかった場合
						if( !isset($_scraped_data_array) ){
							$insert_data[$j]['url'] = $product_url;
							$_scraped_data_array = $this->get_scraped_data($product_url,"//div[@class='product-view']");
						}
						if( !isset($_scraped_data_array) ){
							return;
						}
						$now = date("[Y/m/d H:i:s:]");
						print( $now . ' ' . $insert_data[$j]['url'] );
						echo "\n";
						foreach ($_scraped_data_array as $__key => $content) {

							if( $__key === 0 ){

									//商品名を抽出
									$insert_data[$j]['name'] = isset($content['div'][0]['form']['div'][5]['div'][0]['div'][0]['h2'])
																	? $content['div'][0]['form']['div'][5]['div'][0]['div'][0]['h2']
																	: NULL;

									//ブランド名を抽出
									$insert_data[$j]['brand'] = isset($content['div'][0]['form']['div'][5]['div'][0]['div'][0]['h3']['a'])
																	? $content['div'][0]['form']['div'][5]['div'][0]['div'][0]['h3']['a']
																	: NULL;

									//画像のURLを抽出
									$insert_data[$j]['img_url'] = isset($content['div'][0]['form']['div'][4]['div']['div'][0]['a'][0]['@attributes']['href'])
																	? $content['div'][0]['form']['div'][4]['div']['div'][0]['a'][0]['@attributes']['href']
																	: NULL;

									//商品価格を抽出
									if( isset($content['div'][0]['form']['div'][5]['div'][0]['div'][2]['span']['@attributes']['class']) ){

										//割引が行われていない場合は、定価のみを抽出。割引価格はなし。
										$insert_data[$j]['price'] = isset($content['div'][0]['form']['div'][5]['div'][0]['div'][2]['span']['span'])
																		? trim($content['div'][0]['form']['div'][5]['div'][0]['div'][2]['span']['span'])
																		: NULL;

										$insert_data[$j]['discount_price'] = NULL;
									}else{

										//割引が行われている場合。
										$insert_data[$j]['price'] = isset($content['div'][0]['form']['div'][5]['div'][0]['div'][2]['p'][0]['span'][1])
																		? trim($content['div'][0]['form']['div'][5]['div'][0]['div'][2]['p'][0]['span'][1])
																		: NULL;

										$insert_data[$j]['discount_price'] = isset($content['div'][0]['form']['div'][5]['div'][0]['div'][2]['p'][1]['span'][1])
																				? trim($content['div'][0]['form']['div'][5]['div'][0]['div'][2]['p'][1]['span'][1])
																				: NULL;
									}
							}
						}
					}
				}
			}
			$j++;
		}
		return $insert_data;
	}

	/**
	 * 指定されたURLをスクレイピングして、配列形式で内容を取得する。
	 * @param  [type] $url  [description]
	 * @param  [type] $pass [description]
	 * @return [type]       [description]
	 */
	private function get_scraped_data($url,$pass=NULL)
	{

		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_COOKIEFILE,$this->cookie);
		curl_setopt($ch,CURLOPT_COOKIEJAR, $this->cookie);
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
		$html=curl_exec($ch);
		curl_close($ch);
		if( !$html ){
			return NULL;
		}
		sleep(5);
		$dom = new DOMDocument();
		@$dom->loadHTML($html);
		$xml = simplexml_import_dom($dom);
		if( isset($pass) ){
			$div = $xml->xpath($pass);
			$json = json_encode($div);
		}else{
			$json = json_encode($xml);
		}
		return json_decode($json,true);
	}

	/**
	 * 商品データをチェックして、DBに保存。
	 * @param  [type] $insert_data [description]
	 * @return [type]              [description]
	 */
	private function insert_data($insert_data)
	{

		$query = "INSERT INTO product (url, name, shop_id, brand, category_raw_1, category_raw_2, category_raw_3, category_raw_4, category_raw_5, price, price_discount, img, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now())";
		$stmt = $this->pdo->prepare($query);

		foreach ($insert_data as $key => $val) {

			$update_id = $this->record_exists($val);
			if( !$this->validation_check($val) ){
				// 再ログイン
				unlink($this->cookie);
				$this->cookie = tempnam(sys_get_temp_dir(),'cookie_');
				$this->move_login_page($this->cookie);
				$this->login($this->cookie);
				return;
			}

			if( isset($update_id) ){
				$this->update_record($update_id,$val);
			}else{
				//INSERT実行
				try{
					$stmt->execute(
									array(
										$val['url'],
										$val['name'],
										$val['shop_id'],
										$val['brand'],
										$val['category'][0],
										$val['category'][1],
										$val['category'][2],
										$val['category'][3],
										$val['category'][4],
										$val['price'],
										$val['discount_price'],
										$val['img_url'],
									)
								);
				}catch( Exception $e ){
					echo "エラー:" . $e->getMessage();
					exit;
				}
			}
		}
		return;
	}

	/**
	 * 商品データが既にDBに頭足されたものかを調べる。すでに登録されていた場合は、その商品のIDを返す。
	 * @param  [type] $check_data [description]
	 * @return [type]             [description]
	 */
	private function record_exists($check_data)
	{
		//(商品ページのurlが同じ) = (同じ商品)とする。
		$query = "SELECT product_id FROM product WHERE url = ?";

		$stmt = $this->pdo->prepare($query);
		try{
			$stmt->execute(array($check_data['url']));
			$result = $stmt->fetch();

			if( $result['product_id'] ){
				return intval($result['product_id']);
			}else{
				return NULL;
			}
		}catch( Exception $e ){
			echo "エラー:" . $e->getMessage();
			exit;
		}
	}

	/**
	 * 商品データをアップデートする
	 * @param  [type] $update_id   [UPDATEする商品のID]
	 * @param  [type] $update_data [UPDATE内容]
	 * @return [type]              [description]
	 */
	private function update_record($update_id,$update_data)
	{
		$query = "UPDATE product SET url = ?, name = ?, brand = ?, category_raw_1 = ?, category_raw_2 = ?, category_raw_3 = ?, category_raw_4 = ?, category_raw_5 = ?, price = ?, price_discount = ?, img = ?, updated_at = now() WHERE product_id = ?";

		$stmt = $this->pdo->prepare($query);
		try{
			$result = $stmt->execute(
							array(
								$update_data['url'],
								$update_data['name'],
								$update_data['brand'],
								$update_data['category'][0],
								$update_data['category'][1],
								$update_data['category'][2],
								$update_data['category'][3],
								$update_data['category'][4],
								$update_data['price'],
								$update_data['discount_price'],
								$update_data['img_url'],
								$update_id
							)
						);
			return $result;
		}catch( Exception $e ){
			echo "エラー:" . $e->getMessage();
			exit;
		}
	}

	/**
	 * 商品詳細データが取得できているかを調べる
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	private function validation_check($data)
	{
		if( !isset($data['name']) || !isset($data['brand']) || !isset($data['price']) ){
			return false;
		}else{
			return true;
		}
	}
}

?>
