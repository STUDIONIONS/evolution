<?php
namespace ProjectSoft;

use ImageOptimizer\OptimizerFactory;

class PluginEvolution {
	
	// Оптимизация на лету. Загрузка файлов, генерация Изображений сниппетом thumb (порт phpthumb) где добавлено событие OnGenerateThumbnail
	public static function generateThumbnail(\DocumentParser $modx, $params)
	{
		/*
		** Оптимизация изображений
		** $params['filepath'] - Директория файла
		** $params['filename'] - Имя файла
		*/
		$validate = array('jpg', 'jpeg', 'png', 'gif');
		$arr = array_map('strtolower', explode(',', $modx->config['upload_images']));
		
		// Normalized path
		$params["filepath"] = rtrim(str_replace('\\','/', $params["filepath"]), "/\\");
		$params["filename"] = trim(str_replace('/','', str_replace($params["filepath"], "", str_replace('\\','/', $params["filename"]))), "/\\");
		
		$path = $params['filepath'] . "/" . $params["filename"];
		$info = pathinfo($path);
		$ext = strtolower($info['extension']);
		
		if (in_array($ext, $arr) && in_array($ext, $validate) && is_file($path) && is_writable($path)){
			try {
				$factory = new OptimizerFactory();
				$optimizer = $factory->get();
				$optimizer->optimize($path);
				file_put_contents(MODX_BASE_PATH . "upload.txt", print_r($params, true));
			} catch(\Exception $e){
				$modx->logEvent(0, 3, implode('<br />', $e->getMessage()), get_called_class());
			}
		}
	}
	
	// Создание дирректории по id документа c учётом родителей
	public static function createDocFolders(\DocumentParser $modx, $params)
	{
		if(!is_array($params)){
			$params = array();
		}
		
		$parent = 0;

		$params["pad"] = isset($params["pad"]) ? intval($params["pad"]) : 4;
		if($params["pad"]< 1)
			$params["pad"] = 4;
		$permsFolder = octdec($modx->config['new_folder_permissions']);
		$assetsPath = $modx->config['rb_base_dir'];

		$id = (isset($params['new_id'])) ? intval($params['new_id']) : intval($params["id"]);
		
		if(!$id){
			return;
		}
		
		$lists = array(str_pad($id, $params["pad"], "0", STR_PAD_LEFT));
		self::getParent($modx, $id, $lists, $params);
		
		$dir = implode('/', array_reverse($lists));
		
		if(!is_dir($assetsPath."images/".$dir)):
			@mkdir($assetsPath."images/".$dir, $permsFolder, true);
		endif;
		if(!is_dir($assetsPath."files/".$dir)):
			@mkdir($assetsPath."files/".$dir, $permsFolder, true);
		endif;
	}
	
	private static function getParent(\DocumentParser $modx, $id, &$lists, $params)
	{
		$table_content = $modx->getFullTableName('site_content');
		$parent = $modx->db->getValue($modx->db->select('parent', $table_content, "id='{$id}'"));
		if($parent):
			$lists[] = str_pad($parent, $params["pad"], "0", STR_PAD_LEFT);
			self::getParent($modx, $parent, $lists, $params);
		endif;
	}
	
	// Open Graph image
	private static function ogImage(\DocumentParser $modx)
	{
		$object = $modx->documentObject;
		$ID = $object['id'];
		$img = $object['imgSoc'][1];
		$out = "";
		$type = (isset($object['gallery']) || isset($object['advance']));
		if(isset($object['gallery'])):
			$display = isset($_GET["advance"]) ? $modx->config["display_advance"] : "all";
			$json = $modx->runSnippet('multiTV', array(
				"tvName"		=> "gallery",
				"docid"			=> $ID,
				"display"		=> $display,
				"paginate"		=> 1,
				"reverse"		=> 1,
				"toJson"		=> 1
			));
			$json = json_decode($json);
			if(isset($json[0])):
				$img = MODX_BASE_PATH . $json[0]->image;
			endif;

			if(isset($_GET["gallery"]) || isset($_GET["advance"])):
				$i = isset($_GET["gallery"]) ? (int)$_GET["gallery"] - 1 : (isset($_GET["advance"]) ? (int)$_GET["advance"] - 1 : 0);

				if(isset($json[$i])):
					$img = $json[$i]->image;
				endif;
			endif;
		endif;
		if(is_file(MODX_BASE_PATH . $img)):
			$site_url = $modx->config['site_url'];
			$og_1 = $modx->runSnippet('thumb', array(
				'input'		=> $img,
				'options'	=> 'w=537,h=240,f=jpg,zc=T,sx=2'
			));
			$og_2 = $modx->runSnippet('thumb', array(
				'input'		=> $img,
				'options'	=> 'w=400,h=400,f=jpg,zc=T,sx=2'
			));
			$out .= '	<meta itemprop="image" content="' . $site_url . $og_1 . '" />';
			$out .= PHP_EOL . '		<meta property="og:image" content="' . $site_url . $og_1 . '" />';
			$out .= PHP_EOL . '		<meta property="og:image:width" content="537" />';
			$out .= PHP_EOL . '		<meta property="og:image:height" content="240" />';
			$out .= PHP_EOL . '		<meta property="og:image:type" content="image/jpeg" />';
			$out .= PHP_EOL . '		<meta property="og:image" content="' . $site_url . $og_2 . '" />';
			$out .= PHP_EOL . '		<meta property="og:image:width" content="400" />';
			$out .= PHP_EOL . '		<meta property="og:image:height" content="400" />';
			$out .= PHP_EOL . '		<meta property="og:image:type" content="image/jpeg" />';
			$out .= PHP_EOL . '		<meta property="twitter:image0" content="' . $site_url . $img . '" />';
			$out .= PHP_EOL . '		<meta property="twitter:image1" content="' . $site_url . $og_1 . '" />';
			$out .= PHP_EOL . '		<meta property="twitter:image2" content="' . $site_url . $og_2 . '" />';
		endif;
		return $out;
	}
	// Минификация HTML кода
	public static function minifyHTML(\DocumentParser $modx)
	{
		$minify = true;
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'):
			if(!empty($_POST["formid"])):
				$minify = false;
			endif;
		endif;
		$str = $modx->documentOutput;
		if(isset($modx->documentObject['imgSoc'][1])):
			$ogImage = self::ogImage($modx);
			$re = '/<head>(.*)<\/head>/Usi';
			$subst = '<head>$1' . $ogImage . PHP_EOL . '	</head>';
			$str = preg_replace($re, $subst, $str);
		endif;
		if($modx->documentObject['minify'][1]==1 && $minify):
			$types = array(
				"application/rss+xml",
				"text/html",
				"text/css",
				"text/xml",
				"application/json"
			);
			if(in_array($modx->documentObject['contentType'], $types)):
				$re = '/((?:content=))(?:"|\')([A-я\S\s\d\D\X\W\w]+)?(?:"|\')/U';
				$mb = md5(Util::has());
				$str = preg_replace_callback($re, function ($matches) use ($mb) {
					$res = preg_replace('(\r(?:\n)?)', $mb, $matches[2]);
					return $matches[1].'"'.$res.'"';
				}, $str);
				
				$str = preg_replace("/<!(--)?(\s+)?(?!\[).*-->/", '', $str);
				$str = preg_replace("/(\s+)?\n(\s+)?/", '', $str);
				$str = preg_filter("/\s+/u", ' ', $str);
				
				$str = preg_replace("/(" . $mb . ")/", "\n", $str);
			endif;
		endif;
		$modx->documentOutput = $str;
	}

	// Роутер на короткие ссылки по ID документа
	public static function routeNotFound(\DocumentParser $modx, array $params)
	{
		$arrReque = explode("?", $_SERVER['REQUEST_URI']);
		parse_str(htmlspecialchars_decode($_SERVER['QUERY_STRING'], ENT_HTML5), $arrQuery);
		Util::debug($arrQuery);
		$tmp_url = trim($arrQuery['q'], '/');
		$tmp_url = rtrim($tmp_url, $modx->config['friendly_url_suffix']);
		$url = ltrim($tmp_url, '/');
		$arr = explode('/', $url);
		if(isset($arr[0])):
			$arr[0] = intval($arr[0]);
		endif;
		if(is_int($arr[0])):
			$id = (int)$arr[0];
			$q = $modx->db->query("SELECT `id` FROM ".$modx->getFullTableName("site_content")." WHERE `id`='".$modx->db->escape($id)."'");
			$docid = (int)$modx->db->getValue($q);
			if($docid > 0):
				unset($arrQuery['q']);
				$arrQuery['hash'] = Util::has();
				$has = "?" . http_build_query($arrQuery);
				$url = $modx->makeUrl($docid) . $has;
				$modx->sendRedirect($url, 0, 'REDIRECT_HEADER', 'HTTP/1.1 301 Moved Permanently');
			endif;
		endif;
	}

	// Очистка директории
	public static function clearFolder(string $path = "assets/cache/css")
	{
		$dir = MODX_BASE_PATH . str_replace(MODX_BASE_PATH, "", $path);
		if(is_dir($dir) && is_writable($dir)):
			$directory = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
			$iteartion = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::CHILD_FIRST);
			foreach ( $iteartion as $file ) {
				$file->isDir() ?  @rmdir($file) : @unlink($file);
			}
			Util::setHtaccess($path);
			return true;
		endif;
		return false;
	}

	public static function addOpenDialog(\DocumentParser $modx, array $params)
	{
		$browser = $modx->getConfig('which_browser');
		$media_browser = MODX_MANAGER_URL . 'media/browser/' . $browser . '/browse.php';
		$dir = str_replace('\\','/', dirname(__FILE__)) . "/";
		$out = '<script type="text/javascript">window.filemanageropen_url = "'. $media_browser . '";</script>
		<script type="text/javascript">' . @file_get_contents($dir . "filemanager.js") . '</script>';
		$out .= '<style type="text/css">' . @file_get_contents($dir . "filemanager.css") . '</style>';

		$modx->event->output($out);
	}

}