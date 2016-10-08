<?php

$scraping_obj = new Contents_exporter();
$scraping_obj->index();

class Contents_exporter
{
	private $pdo;

	function __construct() {
		//DBに接続
		try{
			$dsn = 'mysql:dbname=vagrant_db;host=localhost;charset=utf8';
			$user = 'root';
			$password = '';
			$this->pdo = new PDO($dsn, $user, $password);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		}catch( PDOException $e ){
			exit( $e->getMessage() );
			die();
		}
	}

	public function index()
	{
		$get_contents = $this->get_scraping_contents();

		foreach ($get_contents as $key => $val) {
			$i=1;
			$url = $val . '&p=' . $i;

			$get_data = $this->exec($url);
			$this->insert_data($get_data);
			$is_last = $this->is_last_page($url,$i);

			if( count($get_data) >= 20 && !$is_last){
				while( count($get_data) >= 20 && !$is_last){
					$i++;
					$url = $val . '&p=' . $i;
					$get_data = $this->exec($url);
					$this->insert_data($get_data);

					$is_last = $this->is_last_page($url,$i);
				}
			}
		}
		return;
	}

	/**
	 * スクレイピングするコンテンツのURLを指定する
	 * @return [type] [description]
	 */
	private function get_scraping_contents()
	{
		$data = array(
			'http://masutabe.info/search/%E4%B8%AD%E5%87%BA%E3%81%97/?o=hof',
			'http://masutabe.info/search/%E7%B4%A0%E4%BA%BA/?o=hof',
			'http://masutabe.info/search/%E5%80%8B%E4%BA%BA%E6%92%AE%E5%BD%B1/?o=hof',
		);
		return $data;
	}

	//現在のページが最終ページかを調べる。
	private function is_last_page($url,$current_page_num)
	{
		$scraped_data_array = $this->get_scraped_data($url,"//section[@class='paging']");

		$last_page_data = isset($scraped_data_array[0]['ul']['li']) ? end($scraped_data_array[0]['ul']['li']) : null;

		$ele_last_page_data = isset($last_page_data['a']) ? $last_page_data['a'] : null;
		if( !isset($ele_last_page_data) || $ele_last_page_data !== '>' ){
			echo "LAST\n";
			echo "$url\n";
			return true;
		}else{
			echo "NOT LAST\n";
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
		$url_split_array = preg_split('/\//', urldecode($url));
		$category = isset($url_split_array[4]) ? $url_split_array[4] : NULL;

		$scraped_data_array = $this->get_scraped_data($url,"//section[@class='videoList']");

		$insert_data = array();
		$_insert_data = array();
		if( !isset($scraped_data_array[0]['article']) ){
			echo "ERROR!!!\n";
			var_dump($scraped_data_array[0]['article']);
			exit;
		}
		foreach( $scraped_data_array[0]['article'] as $key => $val ){
			$content_url = isset($val['figure']['a']['@attributes']['href']) ? $val['figure']['a']['@attributes']['href'] : NULL;
			if( !isset($content_url) ){
				echo 'IN NULL!!!';
				echo "\n";
				exit;
			}

			$content_url = 'http://masutabe.info' . $content_url;
			$scraped_data_array = $this->get_scraped_data($content_url,"//div[@id='videoInfo']");
			if( !isset($scraped_data_array) || empty($scraped_data_array) ){
				var_dump($content_url);
				echo 'CONTINUE!!!';
				continue;
			}

			$title = isset($scraped_data_array[0]['h1']) ? $scraped_data_array[0]['h1'] : NULL;
			$tag_array = array();
			foreach ($scraped_data_array[0]['ul'][0]['li'] as $_key => $_val) {
				if( count($_val) === 1 && isset($_val['a']) )
					$tag_array[] = $_val['a'];
			}
			$_scraped_data_array = $this->get_scraped_data($content_url,"//iframe");
			$referral_url = isset($_scraped_data_array[0]['@attributes']['src']) ? $_scraped_data_array[0]['@attributes']['src'] : NULL;

			$insert_data['url'] = $content_url;
			$insert_data['category'] = $category;
			$insert_data['title'] = $title;
			$insert_data['referral'] = $referral_url;
			if( !empty($tag_array) ){
				$insert_data['tags'] = implode(',',$tag_array);
			}else{
				$insert_data['tags'] = '';
			}
			$_insert_data[] = $insert_data;
		}
		return $_insert_data;
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
		$query = 'INSERT INTO contents (url, title, category, referral, tags) VALUES (?, ?, ?, ?, ?)';
		$stmt = $this->pdo->prepare($query);

		foreach ($insert_data as $key => $val) {
			try{
				$_insert_data = array(
										$val['url'],
										$val['title'],
										$val['category'],
										$val['referral'],
										$val['tags'],
									);
				$stmt->execute($_insert_data);

			}catch( Exception $e ){
				var_dump( $stmt->errorInfo());
				echo "エラー:" . $e->getMessage();
				exit;
			}
		}
		return;
	}
	//
	// /**
	//  * 商品データをアップデートする
	//  * @param  [type] $update_id   [UPDATEする商品のID]
	//  * @param  [type] $update_data [UPDATE内容]
	//  * @return [type]              [description]
	//  */
	// private function update_record($update_id,$update_data)
	// {
	// 	$query = "UPDATE product SET url = ?, name = ?, brand = ?, category_raw_1 = ?, category_raw_2 = ?, category_raw_3 = ?, category_raw_4 = ?, category_raw_5 = ?, price = ?, price_discount = ?, img = ?, updated_at = now() WHERE product_id = ?";
	//
	// 	$stmt = $this->pdo->prepare($query);
	// 	try{
	// 		$result = $stmt->execute(
	// 						array(
	// 							$update_data['url'],
	// 							$update_data['name'],
	// 							$update_data['brand'],
	// 							$update_data['category'][0],
	// 							$update_data['category'][1],
	// 							$update_data['category'][2],
	// 							$update_data['category'][3],
	// 							$update_data['category'][4],
	// 							$update_data['price'],
	// 							$update_data['discount_price'],
	// 							$update_data['img_url'],
	// 							$update_id
	// 						)
	// 					);
	// 		return $result;
	// 	}catch( Exception $e ){
	// 		echo "エラー:" . $e->getMessage();
	// 		exit;
	// 	}
	// }

}

?>
