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

	public function index($type)
	{
		switch ($type) {
			case 'man':
				$pass = MAN_PAGE;
				break;

			case 'woman':
				$pass = WOMAN_PAGE;
				break;

			case 'idea':
				$pass = IDEA_PAGE;
				break
			default:
				break;
		}
		$i=1;

		$url = BASE_URL . $pass . '?p=' .$i;

		$get_data = $this->exec($url);

		while( count($get_data) >= 30 ){
			error_log(count($get_data));
			echo "<pre>";
			var_dump($get_data);
			echo "<pre>";
			error_log(print_r($get_data,true));
			$i++;
			$url = 'http://www.tabs-shopping.com/donna.html?p=' . $i;
			error_log($url);
			$get_data = $this->exec($url);
		}
	}

	private function exec($url)
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
					$insert_data[$j]['shop_name'] = 'tabs_shopping';
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
																	? $content['div'][0]['form']['div'][5]['div'][0]['div'][2]['p'][0]['span'][1]
																	: NULL;

									$insert_data[$j]['discount_price'] = isset($content['div'][0]['form']['div'][5]['div'][0]['div'][2]['p'][1]['span'][1])
																			? $content['div'][0]['form']['div'][5]['div'][0]['div'][2]['p'][1]['span'][1]
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
}

?>
