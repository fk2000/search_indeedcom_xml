<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Indeed.com - Search Sample</title>
  <script type="text/javascript"
src="http://gdc.indeed.com/ads/apiresults.js"></script>
                                    
</head>
<body>
<?php

require_once("publisher.php");

//ライブドアの天気予報を表示する関数
function search_indeed($query,$location){

  //XMLデータ取得用ベースURL
  $req = "http://api.indeed.com/ads/apisearch?publisher=".INDEEDCOM_PUBLISHER."&q=".$query."&l=".$location."&sort=&radius=&st=employer&jt=&start=&limit=&fromage=&filter=&latlong=1&co=jp&chnl=&userip=1.2.3.4&useragent=Mozilla/%2F4.0%28Firefox%29&v=2";
  //$req = "http://api.indeed.com/ads/apigetjobs?publisher=5138241454203000&jobkeys=5e50b56a7e69073c&v=2"

  //XMLデータ取得用リクエストURL生成
  //$req .= "?city=".$city."&day=".$day;
  
  //XMLファイルをパースし、オブジェクトを取得
  $xml = simplexml_load_file($req)
   or die("XMLパースエラー");


  echo '<p>query:'.$xml->query.'</p>';
  echo '<p>location:'.$xml->location.'</p>';
  echo '<p>results:'.$xml->results->result->count().'</p>';
//  $results = $xm->results;

  $ret = '<div class="lwws">';
  foreach($xml->results->result as $result) {
    $ret .= "<div><a href='".$result[0]->url."'>".$result[0]->jobtitle."</a></div>";
    //$ret .= "<div>".$result[0]->jobkey."</div>";

    $jobreq = "http://api.indeed.com/ads/apigetjobs?publisher=".INDEEDCOM_PUBLISHER."&jobkeys=".$result[0]->jobkey."&v=2";
	$jobxml = simplexml_load_file($jobreq)
     or die("job XMLパースエラー");

    echo "<p>".$jobxml."</p>";



  }
  $ret .= "</div>";

  return $ret;

}

echo "<h1>Indeed.com - Search Sample</h1>\n";

//リクエストパラメータ設定
$query = $_GET["query"]; //検索文字列を設定
$location = $_GET["location"]; //勤務地を設定

//検索結果を表示する関数をコールする
echo search_indeed($query,$location);

?>
<form action = "index.php" method = "get">
<input type = "text" name ="query"><br/>
<input type = "text" name ="location"><br/>
<input type = "submit" value ="検索">
</form>
<HR>
<span id="indeed_at"><a title="求人検索" href="https://jp.indeed.com"><img alt=Indeed src="https://www.indeed.com/p/jobsearch.gif" style="border: 0; vertical-align: middle;"> からの求人</a></span>
</body>
</html>

