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
	
	private function has()
	{
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$chars_len = strlen($chars);
		$has = "";
		for ($i = 0; $i < 10; $i++):
			$has .= $chars[rand(0, $chars_len - 1)];
		endfor;
		return $has;
	}
	
	// Get Code Content
	public static function preCodeSave(\DocumentParser $modx, $content)
	{
		//html_entity_decode
		$re = '/(<pre(?:.+")?>(?:.+)?<code>)(.*)(<\/code>(?:.+)?<\/pre>)/Usi';
		$arrCode = array();
		$content = preg_replace_callback($re, function ($matches) use (&$arrCode) {
			$md = "@@@" . md5(self::has()) . "@@@";
			$code = html_entity_decode(trim($matches[2]), ENT_NOQUOTES, $modx->config['modx_charset']);
			$code = $matches[1] . htmlentities($code, ENT_NOQUOTES, $modx->config['modx_charset']) . $matches[3];
			$arrCode["/(" . $md . ")/"] = $code;
			return $md;
		}, $content);
		foreach($arrCode as $key=>$value){
			$content = preg_replace($key, $value, $content);
		}
		return $content;
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
		if($modx->documentObject['minify'][1]==1 && $minify):
			$str = $modx->documentOutput;
			$re = '/<pre(?:.*)?>.*<\/pre>/Usi';
			$count = 0;
			$arrCode = array();
			$str = preg_replace_callback($re, function ($matches) use (&$arrCode, &$count) {
				$mb = "@@@" . md5(self::has()) . "_" . $count . "@@@";
				$arrCode["/(" . $mb . ")/"] = $matches[0];
				++$count;
				return $mb;
			}, $str);
			
			$re = '/((?:content=))(?:"|\')([A-я\S\s\d\D\X\W\w]+)?(?:"|\')/U';
			$mb = md5(self::has());
			$str = preg_replace_callback($re, function ($matches) use ($mb) {
				$res = preg_replace('(\r(?:\n)?)', $mb, $matches[2]);
				return $matches[1].'"'.$res.'"';
			}, $str);
			
			$str = preg_replace("/<!(--)?(\s+)?(?!\[).*-->/", '', $str);
			$str = preg_replace("/(\s+)?\n(\s+)?/", '', $str);
			$str = preg_filter("/\s+/u", ' ', $str);
			
			$str = preg_replace("/(" . $mb . ")/", "\n", $str);
			foreach($arrCode as $key=>$value):
				$str = preg_replace($key, $value, $str);
			endforeach;
			$modx->documentOutput = $str;
		endif;
	}

	// Роутер на короткие ссылки по ID документа
	public static function routeNotFound(\DocumentParser $modx, array $params)
	{
		$tmp_url = trim($_SERVER['REQUEST_URI'], '/');
		$tmp_url = rtrim($tmp_url, $modx->config['friendly_url_suffix']);
		$url = ltrim($tmp_url, '/');
		file_put_contents(MODX_BASE_PATH . "reid.txt", print_r($url, true));
		$arr = explode('/', $url);
		if(isset($arr[0])):
			$arr[0] = intval($arr[0]);
		endif;
		if(is_int($arr[0])):
			$id = (int)$arr[0];
			$q = $modx->db->query("SELECT `id` FROM ".$modx->getFullTableName("site_content")." WHERE `id`='".$modx->db->escape($id)."'");
			$docid = (int)$modx->db->getValue($q);
			if($docid > 0):
				$has = "?" . self::has();
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
			self::setHtaccess($path);
			return true;
		endif;
		return false;
	}

	private static function setHtaccess(string $path = "")
	{
		$dir = rtrim(MODX_BASE_PATH . str_replace(MODX_BASE_PATH, "", $path), "/") . "/";
		if($dir !== MODX_BASE_PATH && is_dir($dir)):
			$content .= "<FilesMatch \".(htaccess|htpasswd|ini|phps|fla|psd|log|sh|php|json|xml|txt)$\">
	Order Allow,Deny
	Deny from all
</FilesMatch>".PHP_EOL;
			@file_put_contents($dir . ".htaccess", $content);
		endif;
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

	private function debug($args, string $debug = 'plugin_evolution.txt')
	{
		$h = @fopen(MODX_BASE_PATH . $debug, 'a+');
		@fwrite($h, print_r($args, true) . PHP_EOL);
		@fclose($h);
	}

}