<?php
namespace ProjectSoft;

include_once(MODX_BASE_PATH . 'assets/lib/APIHelpers.class.php');
include_once(MODX_BASE_PATH . 'assets/lib/Helpers/FS.php');

class Util {
	
	public static function has(int $len = 10)
	{
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$chars_len = strlen($chars);
		$has = "";
		for ($i = 0; $i < $len; $i++):
			$has .= $chars[rand(0, $chars_len - 1)];
		endfor;
		return $has;
	}

	public static function hsc(\DocumentParser $modx, string $str = "")
	{
		return preg_replace('/&amp;(#[0-9]+|[a-z]+);/i', '&$1;', htmlspecialchars($str, ENT_QUOTES, $modx->config['modx_charset']));
	}

	public static function is_image(string $file = "")
	{
		if(!is_file($file))
			return false;
		return (@is_array(getimagesize($file)));
	}

	public static function normalizePath(string $path = "")
	{
		return str_replace('\\', '/', $path);
	}

	public static function trimBasePath(string $path = "")
	{
		return str_replace(MODX_BASE_PATH, '', self::normalizePath($path));
	}

	public static function setHitaccess(string $path = "path")
	{
		$dir = rtrim(MODX_BASE_PATH . str_replace(MODX_BASE_PATH, "", $path), "/") . "/";
		if($dir !== MODX_BASE_PATH && $dir !== MODX_MANAGER_PATH && is_dir($dir)):
			$content .= "DirectoryIndex index.php
Options -Indexes
<FilesMatch \".(htaccess|htpasswd|ini|phps|fla|psd|log|sh|php|json|xml|txt)$\">
	Order Allow,Deny
	Deny from all
</FilesMatch>".PHP_EOL;
			@file_put_contents($dir . ".htaccess", $content);
		endif;
	}

	public static function clearFolder(string $path = "assets/cache/css")
	{
		$dir = MODX_BASE_PATH . self::trimBasePath($path);
		if(is_dir($dir) && is_writable($dir)):
			$directory = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
			$iteartion = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::CHILD_FIRST);
			foreach ( $iteartion as $file ) {
				$file->isDir() ?  @rmdir($file) : @unlink($file);
			}
			//self::setHtaccess($path);
			return true;
		endif;
		return false;
	}

	public static function debug($args, string $debug = 'plugin_evolution.txt')
	{
		$h = @fopen(MODX_BASE_PATH . $debug, 'a+');
		$str = print_r($args, true);
		$time = (new \DateTime())->format('Y-m-d H:i:s.u');
		$out = $time . PHP_EOL . $str . PHP_EOL . "-------------------------------------" . PHP_EOL . PHP_EOL;
		@fwrite($h, $out);
		@fclose($h);
	}

	public static function print($args, bool $pre = true)
	{
		$out = $pre ? "<pre><code>" . PHP_EOL : "";
		$out .= print_r($args, true);
		$out .= $pre ? PHP_EOL . "</code></pre> " . PHP_EOL : "";
		return $out;
	}
}