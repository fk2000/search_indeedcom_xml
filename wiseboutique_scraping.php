<?php
$cookie_file_path = tempnam(sys_get_temp_dir(),'cookie_');
$scraping_obj = new Wiseboutique_scraping($cookie_file_path);

$cookie_file_path = $scraping_obj->index();

//最終的に残ったクッキーを削除する。
unlink($cookie_file_path);

class Wiseboutique_scraping
{
	const BASE_URL = "";
	const LOGIN_PAGE_URL = "";
	const POST_URL = "";

	private $pdo;
	private $cookie;

	function __construct($cookie_file_path = '') {
		$this->cookie = $cookie_file_path;
		//DBに接続
		try{
			$dsn = 'mysql:dbname=vagrant_db;host=localhost;charset=utf8';
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
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_COOKIEJAR, realpath($file_path));
		curl_setopt($ch, CURLOPT_COOKIEFILE, realpath($file_path));
		$output = curl_exec($ch) or dir('error ' . curl_error($ch));
		curl_close($ch);
		sleep(5);
		return;
	}

	public function login($file_path)
	{
		$params = array(
			"username" => '',
			"password" => '',
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
		return;
	}

	public function index()
	{
		$get_contents_url = $this->get_scraping_contents();
		$_each_contents = $this->get_each_category_contents($get_contents_url);
		foreach ($_each_contents as $key => $val) {

			$url = $val;
			$url = str_replace('?show=all','',$url);
			//1ページ目のスクレイピングを実行
			$get_data = $this->exec($url);
			$this->insert_data($get_data);

			//取得した商品データの配列の数が30未満の場合は、そのページがラストページであると判断し、スクレイピングを終える。
			//ラストページへ行くまで上と同様の処理を繰り返し行う。
			$is_last = false;
			$offset = 0;
			while( count($get_data) >= 20 && !$is_last ){
				$offset = $offset + count($get_data);
				$_offset = $offset + 1;
				$url = $val . '?offset=' . $_offset;

				$get_data = $this->exec($url);
				if ( is_null($get_data) ){
					$is_last = true;
					break;
				}
				$this->insert_data($get_data);
			}
			//ここまでできてるので、明日はfor文をぶん回してみる
		}
		return $this->cookie;
	}


	/**
	 * HOMEより、スクレイピングをするカテゴリのURLを取得する。
	 * @return [type] [description]
	 */
	private function get_scraping_contents()
	{
		$donna_contents_url = array();

		$man_sales_url = 'http://www.wiseboutique.com/en/pages/index/man-16/?show=all';
		$woman_sales_url = 'http://www.wiseboutique.com/en/pages/index/woman-15/?show=all';

		$new_arraival_man_url = 'http://www.wiseboutique.com/en/pages/index/man-1043/?show=all';
		$new_arraival_woman_url = 'http://www.wiseboutique.com/en/pages/index/woman-1044/?show=all';

		$man_url = 'http://www.wiseboutique.com/en/pages/index/man-14/?show=all';
		$woman_url = 'http://www.wiseboutique.com/en/pages/index/woman-13/?show=all';

		$toy_url = 'http://www.wiseboutique.com/en/pages/index/toys-316/?show=all';

		$scraping_contents_url = array(
			$man_sales_url,
			$woman_sales_url,
			$new_arraival_man_url,
			$new_arraival_woman_url,
			$man_url,
			$woman_url,
			$toy_url,
		);
		return $scraping_contents_url;
	}

	private function get_each_category_contents($category_url)
	{
		$data = array();
		foreach ($category_url as $url) {
			$get_href = $this->get_scraped_data($url,"//div[contains(@id,'menu_products')]//a[contains(@id,'page')]");
			if( isset($get_href) ){
				foreach ($get_href as $_url) {
					if( isset( $_url['@attributes']['href'] ) ){
						$data[] = $_url['@attributes']['href'];
					}
				}
			}
		}
		return $data;
	}

	/**
	 * 受け取った商品一覧ページの商品データを抽出
	 * @param  [type] $url [description]
	 * @return [type]      [description]
	 */
	private function exec($url)
	{
		$scraped_data_array = $this->get_scraped_data($url,"//h3[@class='breacrumb']");
		$category_array = array();
		if( isset($scraped_data_array[0]['a']) ){
			foreach ($scraped_data_array[0]['a'] as $val) {
				if( !is_array($val) ){
					$category_array[] = trim($val);
				}
			}
		}

		$scraped_data_array = $this->get_scraped_data($url,"//div[@id='products-container']//div[@class='product item hover']");
		if( empty($scraped_data_array) || !isset($scraped_data_array) ){
			return NULL;
		}
		$insert_data = array();
		$j = 0;
		foreach ($scraped_data_array as $key => $val) {
			//カテゴリ
			for ($k = 0; $k < 5; $k++) {
				$insert_data[$j]['category'][$k] = isset($category_array[$k]) ? $category_array[$k] : NULL;
			}

			//商品のURL
			$insert_data[$j]['url'] = isset($val['div']['div']['a']['@attributes']['href']) ? $val['div']['div']['a']['@attributes']['href'] : NULL;
			//shop_id
			$insert_data[$j]['shop_id'] = 7;

			//ブランド名
			if( isset($val['div']['div']['a']['div'][1]['div'][0]) && !is_array($val['div']['div']['a']['div'][1]['div'][0]) ){
				$insert_data[$j]['brand'] = trim($val['div']['div']['a']['div'][1]['div'][0]);
			}else{
				$insert_data[$j]['brand'] = '';
			}

			//商品名
			if( isset($val['div']['div']['a']['div'][1]['div'][1]) && !is_array($val['div']['div']['a']['div'][1]['div'][1]) ){
				$insert_data[$j]['name'] = trim($val['div']['div']['a']['div'][1]['div'][1]);
			}else{
				$insert_data[$j]['name'] = '';
			}

			//画像
			$insert_data[$j]['img_url'] = isset($val['img']['@attributes']['src']) ? $val['img']['@attributes']['src'] : NULL;

			//価格
			if( isset($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span'])
				&& is_array($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span']) ){

				$insert_data[$j]['price'] = isset($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span'][0])
												? trim($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span'][0])
												: '';

				$insert_data[$j]['discount_price'] = isset($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span'][2])
														? trim($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span'][2])
														: '';

			}elseif( isset($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span'])
					&& !is_array($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span']) ){

				$insert_data[$j]['price'] = isset($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span'])
												? trim($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span'])
												: '';
				$insert_data[$j]['discount_price'] = '';
			}

			if( !isset($insert_data[$j]['price']) ){
				var_dump($val['div']['div']['a']['div'][1]['div'][2]['div'][0]['span']);exit;
			}


			if( !stripos($insert_data[$j]['price'], '€') ){
				var_dump($insert_data[$j]['price']);
				var_dump($val['div']['div']['a']['div'][1]['div'][2]['div']);
				exit;
			}
			$now = date("[Y/m/d H:i:s:]");
			print( $now . ' ' . $insert_data[$j]['url'] );
			echo "\n";
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
		if( !isset($insert_data) ){
			return;
		}
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
