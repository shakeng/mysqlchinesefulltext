<?php
/*
表结构：


CREATE TABLE `full_text` (
  `id` int(11) NOT NULL,
  `text` mediumtext NOT NULL,
  `full_index` mediumtext NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

ALTER TABLE `full_text` ADD PRIMARY KEY (`id`);
ALTER TABLE `full_text` ADD FULLTEXT KEY `full_index` (`full_index`);
ALTER TABLE `full_text` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

*/


define('FTS',"|");

//text为原文本，full_index为全文索引

//插入数据
$text = "《唐诗三百首》选诗范围相当广泛，收录了77家诗";
$text = toUtf8($text);//UTF8并非必要
$full_index = fullTextSplit($text,true);
$insert_sql = "INSERT INTO `full_text` (`id`, `text`, `full_index`) VALUES (NULL, '".$text."', '".$full_index."')";
echo $insert_sql,"\r\n";


//查询语句
$query_field = 'full_index';//索引字段

$query_single_word = '唐诗';
$query_sql = "SELECT * FROM `full_text` WHERE ".keyword2FullTextSql($query_single_word,$query_field);
echo $query_sql,"\r\n";


$query_multi_word = '唐诗 收录';
$query_sql = "SELECT * FROM `full_text` WHERE ".keyword2FullTextSql($query_multi_word,$query_field);
echo $query_sql,"\r\n";



//索引子串
function fullTextSplit($text,$wrap = false){
	if (!defined('FTS')) define('FTS', '|');
	$cind = 0;
	$new_text = FTS;
	for($i = 0; $i < strlen($text); $i++){
		if(strlen(substr($text, $cind, 1)) > 0){
			if(ord(substr($text, $cind, 1)) < 192){
				if(preg_match("/[a-zA-Z0-9\.]/",substr($text, $cind, 1))){
					$is_numeric = preg_match("/[0-9\.]/",substr($text, $cind, 1));
					if($cind <strlen($text)){
						$is_numeric_next = preg_match("/[0-9\.]/",substr($text, $cind+1, 1));
					}else{
						$is_numeric_next = $is_numeric;
					}
					if($is_numeric <> $is_numeric_next){
						$new_text .= substr($text, $cind, 1).FTS;
					}else{
						$new_text .= substr($text, $cind, 1);
					}					
				}else{
					$new_text .= FTS;
				}
				
				$cind++;
			}elseif(ord(substr($text, $cind, 1)) < 224) {
				$new_text .= FTS.substr($text, $cind, 2);		
				$cind+=2;
			}else{
				$new_text .= FTS.substr($text, $cind, 3).FTS;		
				$cind+=3;
			}
		}
	}

	$new_text = explode(FTS,$new_text);
	$new_text = arrayFilter($new_text);
	if($wrap){
		return FTS. implode(FTS,$new_text) .FTS;
	}else{
		return implode(FTS,$new_text);
	}
}


//查询sql
function keyword2FullTextSql($keyword,$field){
	$keyword = explode('|',$keyword);
	$keyword = array_unique(array_diff($keyword,array('',NULL,false)));
	$keyword_sql = array();
	if(!empty($keyword)){
		$return = '';
		foreach($keyword as $kw){
			$kw = explode(' ',$kw);
			$kw = array_unique(array_diff($kw,array('',NULL,false)));
			if(!empty($kw)){
				foreach($kw as $key => $val){
					$kw[$key] = '"'.fullTextSplit($val,true).'"';
				}
				$keyword_sql[] = ' MATCH(`'.$field.'`) AGAINST(\'+'.implode(' +',$kw).'\'  IN BOOLEAN MODE)';
			}
		}
	}
	
	if(!empty($keyword_sql)){
		return '('. implode(" OR ",$keyword_sql) .')';
	}else{
		return false;
	}
}


//转换编码（非必须）
function toUtf8($text,$ignore = false){
	if(is_array($text)){
		foreach($text as $k=>$v){
			$text[$k] = toUtf8($text[$k],$ignore);
		}
		return $text;
	}else{
		$charset = mb_detect_encoding($text,array('UTF-8','ASCII','EUC-CN','CP936','BIG-5','GB2312','GBK'));
		if ($charset != 'UTF-8' && !empty($charset)){
			@$text = mb_convert_encoding($text, "UTF-8", $charset);
		}else{
			@$text = mb_convert_encoding($text, "UTF-8", 'auto');
		}
		
		if($ignore) $text = iconv('UTF-8','UTF-8//IGNORE',$text);
		return preg_replace ( '/(<meta\s+.+?content=".+?charset=)(.+?)("\s?\/?\s*>)/i', "\\1UTF-8\\3", $text, 1 );
	}
}

//数组过滤
function arrayFilter($array){
	$array = array_diff($array,array('',NULL,false));
	return $array;
}
