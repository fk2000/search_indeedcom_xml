<?php
$cookie_file_path = tempnam(sys_get_temp_dir(),'cookie_');

$scraping_obj = new Linoricci_it_scraping($cookie_file_path);
$cookie_file_path = $scraping_obj->index();

//最終的に残ったクッキーを削除する。
unlink($cookie_file_path);

class Linoricci_it_scraping
{
	const BASE_URL = "";
	const LOGIN_PAGE_URL = "";
	const POST_URL = "";
	private $total_cnt = 0;
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
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $file_path);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $file_path);
		$output = curl_exec($ch) or dir('error ' . curl_error($ch));
		curl_close($ch);
		sleep(5);
		return;
	}

	public function login($file_path)
	{
		$params = array(
			 "email" => '',
			 "passwd" => '',
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
			$this->total_cnt = $this->total_cnt + count($get_data);
			print('TOTAL: '.$this->total_cnt);
			echo "\n";

			$this->insert_data($get_data);


			//2ページ以上のページが存在する場合は、更に次ページのスクレイピングを実行
			if( (Bool)$is_next ){
				//取得した商品データの配列の数が12未満の場合は、そのページがラストページであると判断し、スクレイピングを終える。
				//ラストページへ行くまで上と同様の処理を繰り返し行う。
				$is_last = false;
				while( count($get_data) >= 12 && (Bool)!$is_last ){
					$i++;
					$url = $val . '?p=' . $i;

					$_get_data = $this->exec($url);

					$this->total_cnt = $this->total_cnt + count($get_data);
					print('TOTAL: '.$this->total_cnt);
					echo "\n";

					//連続して同じページをスクレイピングするときがあるので、それに対応
					if( $this->is_same_array($get_data,$_get_data) ){
                        $is_last = true;
                        continue;
                    }
					$this->insert_data($_get_data);


					if( count($_get_data) < 12){
						$is_last = TRUE;
					}else{
						$is_last = $this->is_last_page($url);
					}
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
		$donna_contents_url = array(
			'http://www.linoricci.it/en/22-abiti',
			'http://www.linoricci.it/en/20-camicie',
			'http://www.linoricci.it/en/21-cappotti',
			'http://www.linoricci.it/en/23-felpe',
			'http://www.linoricci.it/en/24-giacche',
			'http://www.linoricci.it/en/147-giacchetti-',
			'http://www.linoricci.it/en/26-giacchetti-pelle',
			'http://www.linoricci.it/en/28-gonne',
			'http://www.linoricci.it/en/29-jeans',
			'http://www.linoricci.it/en/30-maglieria',
			'http://www.linoricci.it/en/31-pantaloni',
			'http://www.linoricci.it/en/32-pellicce-e-shearling',
			'http://www.linoricci.it/en/39-piumini',
			'http://www.linoricci.it/en/104-t-shirt',
			'http://www.linoricci.it/en/170-trench',
			'http://www.linoricci.it/en/179-intimo',
			'http://www.linoricci.it/en/178-borse-a-mano',
			'http://www.linoricci.it/en/47-borse-a-spalla',
			'http://www.linoricci.it/en/50-borse-shopping',
			'http://www.linoricci.it/en/48-pochette',
			'http://www.linoricci.it/en/140-portafogli',
			'http://www.linoricci.it/en/51-zaini',
			'http://www.linoricci.it/en/172-ballerine',
			'http://www.linoricci.it/en/180-decollete',
			'http://www.linoricci.it/en/173-sandali-alti',
			'http://www.linoricci.it/en/44-sandali-bassi',
			'http://www.linoricci.it/en/43-slip-on',
			'http://www.linoricci.it/en/42-stivali',
			'http://www.linoricci.it/en/45-sneakers',
			'http://www.linoricci.it/en/176-stringate',
			'http://www.linoricci.it/en/57-sciarpe',
			'http://www.linoricci.it/en/56-portachiavi',
			'http://www.linoricci.it/en/58-occhiali-da-sole-',
			'http://www.linoricci.it/en/52-gioielli',
			'http://www.linoricci.it/en/53-cinture',
			'http://www.linoricci.it/en/55-cappelli',
			'http://www.linoricci.it/en/102-shorts',
			'http://www.linoricci.it/en/168-top',
			'http://www.linoricci.it/en/149-portacarte',
			'http://www.linoricci.it/en/183-stickers',
			'http://www.linoricci.it/en/103-cover',
		);

		$uomo_contents_url = array(
			'http://www.linoricci.it/en/72-abiti-uomo',
			'http://www.linoricci.it/en/62-camicie-uomo',
			'http://www.linoricci.it/en/98-cappotti-uomo',
			'http://www.linoricci.it/en/69-felpe-uomo',
			'http://www.linoricci.it/en/61-giacche-uomo',
			'http://www.linoricci.it/en/99-giacchetti-uomo',
			'http://www.linoricci.it/en/66-jeans-uomo',
			'http://www.linoricci.it/en/78-piumini-uomo',
			'http://www.linoricci.it/en/67-maglieria-uomo',
			'http://www.linoricci.it/en/71-pantaloni-uomo',
			'http://www.linoricci.it/en/74-polo',
			'http://www.linoricci.it/en/70-t-shirt-uomo',
			'http://www.linoricci.it/en/177-trench',
			'http://www.linoricci.it/en/136-borse-',
			'http://www.linoricci.it/en/143-zaini',
			'http://www.linoricci.it/en/138-portafogli',
			'http://www.linoricci.it/en/106-anfibio',
			'http://www.linoricci.it/en/82-mocassini',
			'http://www.linoricci.it/en/116-polacchine',
			'http://www.linoricci.it/en/114-slip-on',
			'http://www.linoricci.it/en/84-sneakers',
			'http://www.linoricci.it/en/85-stivali',
			'http://www.linoricci.it/en/86-stringate',
			'http://www.linoricci.it/en/92-cappelli',
			'http://www.linoricci.it/en/89-cinture',
			'http://www.linoricci.it/en/95-cravatte',
			'http://www.linoricci.it/en/91-occhiali-da-sole',
			'http://www.linoricci.it/en/93-gioielli',
			'http://www.linoricci.it/en/90-guanti',
			'http://www.linoricci.it/en/94-sciarpe',
		);

		$saldi_contents_url = array(
			'http://www.linoricci.it/en/125-abbigliamento',
			'http://www.linoricci.it/en/126-accessori',
			'http://www.linoricci.it/en/127-calzature',
			'http://www.linoricci.it/en/129-abbigliamento',
			'http://www.linoricci.it/en/131-accessori',
			'http://www.linoricci.it/en/130-calzature',
			'http://www.linoricci.it/en/146-pelletteria',
		);
		$scraping_contents_url = array_merge($donna_contents_url,$uomo_contents_url,$saldi_contents_url);
		return $scraping_contents_url;
	}

	/**
	 * 1ページ以上存在するかを調べる
	 * @param  [type]  $url [調べるURL]
	 * @return boolean      [description]
	 */
	private function is_next_page($url)
	{
		$scraped_data_array = $this->get_scraped_data($url,"//div[@id='pagination_bottom']");
		if( isset($scraped_data_array[0]) && count($scraped_data_array[0]) > 1 ){
			return true;
		}else{
			return false;
		}
	}

	//現在のページが最終ページかを調べる。
	private function is_last_page($url)
	{
		$scraped_data_array = $this->get_scraped_data($url,"//div[@id='pagination_bottom']");

		$last_page_data	= null;
		if( isset( $scraped_data_array[0]['ul']['li'] ) ){
			if( is_array( end($scraped_data_array[0]['ul']['li']) ) ){
				$last_page_data = end($scraped_data_array[0]['ul']['li']);
			}
		}
		if( isset($last_page_data) && preg_match('/disabled/', $last_page_data['@attributes']['class']) ){
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
		//カテゴリの取得
		$scraped_data_array = $this->get_scraped_data($url,"//div[@class='breadcrumb clearfix']");
		$category_array = array();

		foreach ($scraped_data_array[0]['span'][1]['span'] as $key => $val) {
			if( is_array($val) ){
				$category_array[] = isset($val['a']['span']) ? $val['a']['span'] : null;
			}
		}
		$scraped_data_array = $this->get_scraped_data($url,"//h1[@class='page-heading product-listing']");
		$category_array[] = isset($scraped_data_array[0]['span']) ? trim($scraped_data_array[0]['span']) : null;

		$scraped_data_array = $this->get_scraped_data($url,"//section[@id='center_column']");

		$insert_data = array();
		$j = 0;

		if( isset($scraped_data_array[0]['div'][2]['div']['div']['div'][0]) ){

			foreach( $scraped_data_array[0]['div'][2]['div']['div']['div'] as $key => $val ){

				$product_url = isset($val['div']['div']['a']['@attributes']['href'])
								? $val['div']['div']['a']['@attributes']['href']
								: NULL;
				if( is_null($product_url) ){
					$product_url = isset($val['div']['div']['a'][0]['@attributes']['href'])
									? $val['div']['div']['a'][0]['@attributes']['href']
									: NULL;
				}

				if( isset($product_url) ){
					for ($k = 0; $k < 5; $k++) {
						$insert_data[$j]['category'][$k] = isset($category_array[$k]) ? $category_array[$k] : null;
					}

					$insert_data[$j]['shop_id'] = 22;
					$product_url = preg_replace('/\/it\//', '/\/en\//', $product_url);
					$insert_data[$j]['url'] = $product_url;//商品URL

					$_scraped_data_array = $this->get_scraped_data($product_url,"//div[@class='primary_block row']");

					if( !isset($_scraped_data_array) ){
						return;
					}

					$now = date("[Y/m/d H:i:s]");
					print( $now . ' ' . $insert_data[$j]['url'] );
					echo "\n";

					foreach ($_scraped_data_array as $__key => $content) {

						if( $__key === 0 ){

								//商品名を抽出
								$insert_data[$j]['name'] = isset($content['div'][2]['h1']) ? $content['div'][2]['h1'] : NULL;

								//ブランド名を抽出
								$insert_data[$j]['brand'] = isset($content['div'][2]['p'][0]['a']['span']) ? $content['div'][2]['p'][0]['a']['span'] : '';

								//画像のURLを抽出
								$insert_data[$j]['img_url'] = isset($content['div'][1]['div'][0]['span']['img']['@attributes']['src'])
																? $content['div'][1]['div'][0]['span']['img']['@attributes']['src']
																: '';
								//商品価格を抽出
								if( isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
									&& !is_array($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span']) ){

									//割引が行われている場合。
									$insert_data[$j]['price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
																	: '';

									$insert_data[$j]['discount_price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	: '';
								}else{

									//割引が行われていない場合は、定価のみを抽出。割引価格はなし。
									$insert_data[$j]['price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	: '';

									$insert_data[$j]['discount_price'] = '';
								}
						}
					}
				}
				$j++;
			}

		}elseif( isset($scraped_data_array[0]['div'][3]['div'][0]) ){

			foreach( $scraped_data_array[0]['div'][3]['div'] as $key => $val ){

				$product_url = isset($val['div']['div'][0]['div']['div']['a']['@attributes']['href'])
								? $val['div']['div'][0]['div']['div']['a']['@attributes']['href']
								: NULL;

				if( is_null($product_url) ){
					$product_url = isset($val['div']['div'][0]['div']['div']['a'][0]['@attributes']['href'])
									? $val['div']['div'][0]['div']['div']['a'][0]['@attributes']['href']
									: NULL;
				}

				if( isset($product_url) ){
					for ($k = 0; $k < 5; $k++) {
						$insert_data[$j]['category'][$k] = isset($category_array[$k]) ? $category_array[$k] : null;
					}

					$insert_data[$j]['shop_id'] = 22;
					$product_url = preg_replace('/\/it\//', '/\/en\//', $product_url);
					$insert_data[$j]['url'] = $product_url;//商品URL

					$_scraped_data_array = $this->get_scraped_data($product_url,"//div[@class='primary_block row']");

					if( !isset($_scraped_data_array) ){
						return;
					}

					$now = date("[Y/m/d H:i:s]");
					print( $now . ' ' . $insert_data[$j]['url'] );
					echo "\n";

					foreach ($_scraped_data_array as $__key => $content) {

						if( $__key === 0 ){

								//商品名を抽出
								$insert_data[$j]['name'] = isset($content['div'][2]['h1']) ? $content['div'][2]['h1'] : '';

								//ブランド名を抽出
								$insert_data[$j]['brand'] = isset($content['div'][2]['p'][0]['a']['span']) ? $content['div'][2]['p'][0]['a']['span'] : '';

								//画像のURLを抽出
								$insert_data[$j]['img_url'] = isset($content['div'][1]['div'][0]['span']['img']['@attributes']['src'])
																? $content['div'][1]['div'][0]['span']['img']['@attributes']['src']
																: '';

								//商品価格を抽出
								if( isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
									&& !is_array($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span']) ){

									//割引が行われている場合。
									$insert_data[$j]['price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
																	: '';


									$insert_data[$j]['discount_price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	: '';
								}else{

									//割引が行われていない場合は、定価のみを抽出。割引価格はなし。
									$insert_data[$j]['price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	: '';

									$insert_data[$j]['discount_price'] = '';
								}
						}
					}
				}
				$j++;
			}

		}elseif( isset($scraped_data_array[0]['div'][3]['div']['div']['div'][0]) ){

			foreach( $scraped_data_array[0]['div'][3]['div']['div']['div'] as $key => $val ){

				$product_url = isset($val['div']['div']['a']['@attributes']['href'])
								? $val['div']['div']['a']['@attributes']['href']
								: NULL;

				if( is_null($product_url) ){
					$product_url = isset($val['div']['h5']['a']['@attributes']['href'])
									? $val['div']['h5']['a']['@attributes']['href']
									: NULL;
				}
				if( is_null($product_url) ){
					$product_url = isset($val['div']['div']['a'][0]['@attributes']['href'])
									? $val['div']['div']['a'][0]['@attributes']['href']
									: NULL;
				}
				if( is_null($product_url) ){
					$product_url = isset($val['div']['div']['a']['@attributes']['href'])
									? $val['div']['div']['a']['@attributes']['href']
									: NULL;
				}

				if( isset($product_url) ){
					for ($k = 0; $k < 5; $k++) {
						$insert_data[$j]['category'][$k] = isset($category_array[$k]) ? $category_array[$k] : null;
					}

					$insert_data[$j]['shop_id'] = 22;
					$product_url = preg_replace('/\/it\//', '/\/en\//', $product_url);
					$insert_data[$j]['url'] = $product_url;//商品URL

					$_scraped_data_array = $this->get_scraped_data($product_url,"//div[@class='primary_block row']");

					if( !isset($_scraped_data_array) ){
						return;
					}

					$now = date("[Y/m/d H:i:s]");
					print( $now . ' ' . $insert_data[$j]['url'] );
					echo "\n";
					$j = 0;

					foreach ($_scraped_data_array as $__key => $content) {

						if( $__key === 0 ){

								//商品名を抽出
								$insert_data[$j]['name'] = isset($content['div'][2]['h1']) ? $content['div'][2]['h1'] : '';

								//ブランド名を抽出
								$insert_data[$j]['brand'] = isset($content['div'][2]['p'][0]['a']['span']) ? $content['div'][2]['p'][0]['a']['span'] : '';

								//画像のURLを抽出
								$insert_data[$j]['img_url'] = isset($content['div'][1]['div'][0]['span']['img']['@attributes']['src'])
																? $content['div'][1]['div'][0]['span']['img']['@attributes']['src']
																: '';
								//商品価格を抽出
								if( isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
									&& !is_array($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span']) ){
									//割引が行われている場合。
									$insert_data[$j]['price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
																	: '';

									$insert_data[$j]['discount_price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	: '';
								}else{

									//割引が行われていない場合は、定価のみを抽出。割引価格はなし。
									$insert_data[$j]['price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	: '';

									$insert_data[$j]['discount_price'] = '';
								}
						}
						$j++;
					}
				}
			}

		}elseif( isset($scraped_data_array[0]['div'][2]['div'][0]) ){
			foreach( $scraped_data_array[0]['div'][2]['div'] as $key => $val ){

				$product_url = isset($val['div']['div'][0]['div']['a']['@attributes']['href'])
								? $val['div']['div'][0]['div']['a']['@attributes']['href']
								: NULL;
				if( is_null($product_url) ){
					$product_url = isset($val['div']['div'][0]['div']['a'][0]['@attributes']['href'])
									? $val['div']['div'][0]['div']['a'][0]['@attributes']['href']
									: NULL;
				}
				if( is_null($product_url) ){
					$product_url = isset($val['div']['div'][0]['div']['div']['a']['@attributes']['href'])
									? $val['div']['div'][0]['div']['div']['a']['@attributes']['href']
									: NULL;
				}
				if( is_null($product_url) ){
					$product_url = isset($val['div']['div'][0]['div']['div']['a'][0]['@attributes']['href'])
									? $val['div']['div'][0]['div']['div']['a'][0]['@attributes']['href']
									: NULL;
				}

				if( isset($product_url) ){
					for ($k = 0; $k < 5; $k++) {
						$insert_data[$j]['category'][$k] = isset($category_array[$k]) ? $category_array[$k] : null;
					}

					$insert_data[$j]['shop_id'] = 22;
					$product_url = preg_replace('/\/it\//', '/\/en\//', $product_url);
					$insert_data[$j]['url'] = $product_url;//商品URL

					$_scraped_data_array = $this->get_scraped_data($product_url,"//div[@class='primary_block row']");

					if( !isset($_scraped_data_array) ){
						return;
					}

					$now = date("[Y/m/d H:i:s]");
					print( $now . ' ' . $insert_data[$j]['url'] );
					echo "\n";

					foreach ($_scraped_data_array as $__key => $content) {

						if( $__key === 0 ){

								//商品名を抽出
								$insert_data[$j]['name'] = isset($content['div'][2]['h1']) ? $content['div'][2]['h1'] : NULL;

								//ブランド名を抽出
								$insert_data[$j]['brand'] = isset($content['div'][2]['p'][0]['a']['span']) ? $content['div'][2]['p'][0]['a']['span'] : '';

								//画像のURLを抽出
								$insert_data[$j]['img_url'] = isset($content['div'][1]['div'][0]['span']['img']['@attributes']['src'])
																? $content['div'][1]['div'][0]['span']['img']['@attributes']['src']
																: '';
								//商品価格を抽出
								if( isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
									&& !is_array($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span']) ){

									//割引が行われている場合。
									$insert_data[$j]['price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][3]['span'])
																	: '';

									$insert_data[$j]['discount_price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	: '';
								}else{

									//割引が行われていない場合は、定価のみを抽出。割引価格はなし。
									$insert_data[$j]['price'] = isset($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	? trim($content['div'][3]['form']['div']['div'][0]['div'][0]['p'][0]['span'])
																	: '';

									$insert_data[$j]['discount_price'] = '';
								}
						}
					}
				}
				$j++;
			}
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

		if( is_null($insert_data) ){
			return;
		}
		foreach ($insert_data as $key => $val){

			if( !isset($val) ){
				return;
            }
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
		$query = "SELECT product_id FROM product WHERE url = ? AND category_raw_1 = ? AND category_raw_2 = ?";

		$stmt = $this->pdo->prepare($query);
		try{
			$stmt->execute(
							array(
								$check_data['url'],
								$check_data['category'][0],
								$check_data['category'][1],
							)
						);
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

	private function is_same_array($array1 = null, $array2 = null)
	{
    		if (empty($array1) && empty($array2)) {
        		return true;
    		} elseif( (empty($array1) && !empty($array2)) || (!empty($array1) && empty($array2)) ) {
        		return false;
    		} elseif( (is_array($array1) && !is_array($array2)) || (!is_array($array1) && is_array($array2)) ) {
        		return false;
    		}

    		foreach($array1 as $key => $value) {
        		if (is_array($value)) {
            			if (!isset($array2[$key])) {
                			return false;
           			} elseif(!is_array($array2[$key])) {
               	 			return false;
            			} elseif (!$this->is_same_array($value, $array2[$key])) {
               				return false;
            			}
        		} elseif(!array_key_exists($key, $array2) || $array2[$key] !== $value) {
            			return false;
        		}
    		}

		return true;
	}
}

?>
