<?php
$scraping_obj = new Tabs_shopping_scraping();
// echo 'hello';
$scraping_obj->index('man');



class Tabs_shopping_scraping
{
	const BASE_URL = "http://www.tabs-shopping.com/";
	const WOMAN_PAGE = 'donna.html';
	const MAN_PAGE = 'uomo.html';
	const IDEA_PAGE = 'idee.html';

	private $pdo;

	function __construct() {

		try{
			$dsn = 'mysql:dbname=vagrant_db;host=localhost;charset=utf8';
			$user = 'root';
			$password = '';
			$this->pdo = new PDO($dsn, $user, $password);
		}catch( PDOException $e ){
			exit( $e->getMessage() );
			die();

		}

	}

	public function index($type)
	{
		switch ($type) {
			case 'man':
				$pass = self::MAN_PAGE;
				break;

			case 'woman':
				$pass = self::WOMAN_PAGE;
				break;

			case 'idea':
				$pass = self::IDEA_PAGE;
				break;

			default:
				break;
		}
		$i=1;

		$url = self::BASE_URL . $pass . '?p=' .$i;
		$get_data = $this->exec($url);
		$this->insert_data($get_data);

		while( count($get_data) >= 30 ){
			$i++;
			$url = 'http://www.tabs-shopping.com/donna.html?p=' . $i;
			$get_data = $this->exec($url,$type);

			$this->insert_data($get_data);
			exit;
		}
	}

	private function exec($url,$type)
	{
		$html = file_get_contents($url);

		$dom = new DOMDocument();
		@$dom->loadHTML($html);
		$xml = simplexml_import_dom($dom);
		$div = $xml->xpath("//div[@class='view-content']");
		$json = json_encode($div);
		$scraped_data_array = json_decode($json,true);


		$insert_data = array();
		$j = 0;
		foreach( $scraped_data_array as $key => $val ){

			foreach ($val['div'] as $_key => $_val) {

				if( $_key === 0 ){
					$url = isset($_val['a']['@attributes']['href']) ? $_val['a']['@attributes']['href'] : NULL;
					$insert_data[$j]['shop_id'] = 26;
					$insert_data[$j]['url'] = $url;//商品URL

					if( isset($url) ){
						$_html = file_get_contents($url);
						$_dom = new DOMDocument();
						@$_dom->loadHTML($_html);
						$_xml = simplexml_import_dom($_dom);
						$_div = $_xml->xpath("//div[@class='product-view']");
						$_json = json_encode($_div);
						$_scraped_data_array = json_decode($_json,true);

						foreach ($_scraped_data_array as $__key => $content) {

							if( $__key === 0 ){
									$insert_data[$j]['name'] = isset($content['div'][0]['form']['div'][5]['div'][0]['div'][0]['h2'])
																	? $content['div'][0]['form']['div'][5]['div'][0]['div'][0]['h2']
																	: NULL;

									$insert_data[$j]['brand'] = isset($content['div'][0]['form']['div'][5]['div'][0]['div'][0]['h3']['a'])
																	? $content['div'][0]['form']['div'][5]['div'][0]['div'][0]['h3']['a']
																	: NULL;

									$insert_data[$j]['img_url'] = isset($content['div'][0]['form']['div'][4]['div']['div'][0]['a'][0]['@attributes']['href'])
																	? $content['div'][0]['form']['div'][4]['div']['div'][0]['a'][0]['@attributes']['href']
																	: NULL;

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
			$j++;
		}
		return $insert_data;
	}

	private function insert_data($insert_data)
	{

		$query = "INSERT INTO product (url, name, shop_id, brand, price, price_discount, img, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, now())";
		$stmt = $this->pdo->prepare($query);

		foreach ($insert_data as $key => $val) {
			$update_id = $this->record_exists($val);

			if( isset($update_id) ){
				$this->update_record($update_id,$val);
			}else{
				$stmt->execute(
								array(
									$val['url'],
									$val['name'],
									$val['shop_id'],
									$val['brand'],
									$val['price'],
									$val['discount_price'],
									$val['img_url'],
								)
							);
			}
		}
		return;
	}

	private function record_exists($check_data)
	{
		$_name = mysql_real_escape_string($check_data['name']);
		$_brand = mysql_real_escape_string($check_data['brand']);

		$query = "SELECT product_id FROM product WHERE name = ? AND brand = ?";
		$stmt = $this->pdo->prepare($query);
		$stmt->execute(array($_name,$_brand));
		$result = $stmt->fetch();

		if( $result['product_id'] ){
			return intval($result['product_id']);
		}else{
			return NULL;
		}
	}

	private function update_record($update_id,$update_data)
	{
		$query = "UPDATE product SET url = ?, name = ?, brand = ?, price = ?, price_discount = ?, img = ?, updated_at = now() WHERE product_id = ?";

		$stmt = $this->pdo->prepare($query);
		$result = $stmt->execute(
						array(
							$update_data['url'],
							$update_data['name'],
							$update_data['brand'],
							$update_data['price'],
							$update_data['discount_price'],
							$update_data['img_url'],
							$update_id
						)
					);

		return $result;
	}
}

?>
