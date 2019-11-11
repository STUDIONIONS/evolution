<?php
namespace ProjectSoft;

class AssetsCSS {

	public static function addCss(\DocumentParser $modx, array $list, string $out_type="content", string $attr = "")
	{
		if(is_array($list)):
			$list = array_map(function($val){
				$val = ltrim(trim($val), "\\/");
				return $val;
			}, $list);
			$content = "";
			$vars = $list;
			$attr = trim($attr);
			$attr = ((strlen(trim($attr)) > 0) ? " {$attr}" : "");
			foreach($vars as $key=>$val):
				$path_base = MODX_BASE_PATH . str_replace(MODX_BASE_PATH, '', $val);
				$time = 0;
				if(is_file($path_base))
					$time = filemtime($path_base);
				$vars[$key] = $path_base . '?' . $time;
			endforeach;
			$cache = 'assets/cache/css/style.'.md5(print_r($vars, true)).'.css';
			if(is_file(MODX_BASE_PATH . $cache)):
				$content = file_get_contents(MODX_BASE_PATH . $cache);
			else:
				@mkdir( MODX_BASE_PATH . 'assets/cache/css/', 0777, true );
				self::setHtaccess(MODX_BASE_PATH . 'assets/cache/css/');
				foreach($list as $key=>$val):
					$content .= trim(self::minifyCss(self::getContent($val)));
				endforeach;
				file_put_contents(MODX_BASE_PATH . $cache, $content);
			endif;
			if($out_type=="content"):
				$out = "<style type=\"text/css\"{$attr}>". $content . "</style>";
			else:
				$out = "<link rel=\"stylesheet\" href=\"/${cache}\"{$attr}>";
			endif;
			return $out;
		endif;
		return "";
	}
	
	private static function setHtaccess(string $path = "")
	{
		$dir = rtrim(MODX_BASE_PATH . str_replace(MODX_BASE_PATH, "", $path), "/") . "/";
		if($dir !== MODX_BASE_PATH && is_dir($dir)):
			$content = "order deny,allow".PHP_EOL;
			$content .= "allow from all".PHP_EOL;
			$content .= "Options -Indexes".PHP_EOL;
			@file_put_contents($dir . ".htaccess", $content);
		endif;
	}
	
	private static function getContent(string $path)
	{
		$path_base = MODX_BASE_PATH . str_replace(MODX_BASE_PATH, '', $path);
		if(is_file($path_base)):
			$content = @file_get_contents($path_base);
			if($content){
				return $content;
			}
		endif;
		return "";
	}
	
	private static function minifyCss(string $content)
	{
		$content = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content );
		$content = preg_replace( '/(\s\s+|\t|\n)/', ' ', $content );
		$content = preg_replace( array('(( )+{)','({( )+)'), '{', $content );
		$content = preg_replace( array('(( )+})','(}( )+)','(;( )*})'), '}', $content );
		$content = preg_replace( array('(;( )+)','(( )+;)'), ';', $content );
		$content = str_replace(array(', ', ': ', '; ', ' > ', ' }', '} ', ';}', '{ ', ' {'), array(',', ':', ';', '>', '}', '}', '}', '{', '{'), $content);
		return $content;
	}

	private function debug($args, string $debug = 'css.txt')
	{
		$h = @fopen(MODX_BASE_PATH . $debug, 'a+');
		@fwrite($h, print_r($args, true) . PHP_EOL);
		@fclose($h);
	}

}