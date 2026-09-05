<?php
/**
 * CodeIgniter PHP-Development Server Rewrite Rules
 *
 * This script works with the CLI serve command to help run a seamless
 * development server based around PHP's built-in development
 * server. This file simply tries to mimic Apache's mod_rewrite
 * functionality so the site will operate as normal.
 */

// @codeCoverageIgnoreStart
// Avoid this file run when listing commands
if (php_sapi_name() === 'cli')
{
	return;
}

// Tangkap URI dengan benar - pastikan REQUEST_URI ada
// PHP built-in server menyediakan REQUEST_URI, tapi jika tidak ada gunakan PATH_INFO atau PHP_SELF
$requestUri = $_SERVER['REQUEST_URI'] ?? $_SERVER['PATH_INFO'] ?? '/';

// Parse URI untuk mendapatkan path saja (tanpa query string)
$parsed = parse_url($requestUri);
$uri = urldecode($parsed['path'] ?? '/');

// Jika URI kosong atau hanya '/', coba ambil dari PATH_INFO atau SCRIPT_NAME
if (empty($uri) || $uri === '/') {
	// Coba dari PATH_INFO
	if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
		$uri = $_SERVER['PATH_INFO'];
	}
	// Jika masih kosong, coba dari QUERY_STRING (untuk beberapa konfigurasi server)
	elseif (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['QUERY_STRING'], '/') === 0) {
		$uri = parse_url($_SERVER['QUERY_STRING'], PHP_URL_PATH);
	}
}

// #region agent log
$logPath = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . '.cursor' . DIRECTORY_SEPARATOR . 'debug.log';
$logDir = dirname($logPath);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logEntry = json_encode(['id'=>'log_'.time().'_rewrite_start','timestamp'=>time()*1000,'location'=>'rewrite.php:17','message'=>'Rewrite script started','data'=>['REQUEST_URI'=>$_SERVER['REQUEST_URI']??'','SCRIPT_NAME'=>$_SERVER['SCRIPT_NAME']??'','PHP_SELF'=>$_SERVER['PHP_SELF']??'','PATH_INFO'=>$_SERVER['PATH_INFO']??'','QUERY_STRING'=>$_SERVER['QUERY_STRING']??'','DOCUMENT_ROOT'=>$_SERVER['DOCUMENT_ROOT']??'','uri'=>$uri],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A']) . "\n";
@file_put_contents($logPath, $logEntry, FILE_APPEND);
// #endregion

// Pastikan REQUEST_URI di-set dengan benar untuk CodeIgniter
// Jika REQUEST_URI kosong atau hanya '/', gunakan URI yang sudah di-parse
if (empty($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === '/') {
	if ($uri !== '/' && !empty($uri)) {
		$_SERVER['REQUEST_URI'] = $uri;
		// Juga set PATH_INFO jika belum ada
		if (empty($_SERVER['PATH_INFO'])) {
			$_SERVER['PATH_INFO'] = $uri;
		}
	}
}

// Front Controller path - expected to be in the default folder
$fcpath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR;

// Full path
$path = $fcpath . ltrim($uri, '/');

// Check if it's an asset file (CSS, JS, images, etc.)
$assetExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'mp3', 'mp4', 'pdf', 'zip'];
$pathInfo = pathinfo($path);
$isAsset = isset($pathInfo['extension']) && in_array(strtolower($pathInfo['extension']), $assetExtensions);

// If $path is an existing file or folder within the document root
// then let the request handle it like normal.
if ($uri !== '/' && (is_file($path) || is_dir($path)))
{
	return false;
}

// If it's an asset request but file doesn't exist in document root, try assets folder
if ($isAsset && !is_file($path)) {
	// Try assets folder - check if document root is public or root
	$rootPath = $fcpath;
	// If document root is public folder, go up one level to root
	if (basename(rtrim($fcpath, DIRECTORY_SEPARATOR)) === 'public') {
		$rootPath = dirname($fcpath) . DIRECTORY_SEPARATOR;
	}
	// Remove leading slash from URI for path construction
	$assetUri = ltrim($uri, '/');
	$assetPath = $rootPath . 'assets' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetUri);
	if (is_file($assetPath)) {
		// Serve the asset file directly
		$mimeTypes = [
			'css' => 'text/css',
			'js' => 'application/javascript',
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'svg' => 'image/svg+xml',
			'ico' => 'image/x-icon',
			'woff' => 'font/woff',
			'woff2' => 'font/woff2',
			'ttf' => 'font/ttf',
			'eot' => 'application/vnd.ms-fontobject',
			'mp3' => 'audio/mpeg',
			'mp4' => 'video/mp4',
		];
		$ext = strtolower($pathInfo['extension'] ?? '');
		$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
		header('Content-Type: ' . $mimeType);
		header('Content-Length: ' . filesize($assetPath));
		readfile($assetPath);
		exit;
	}
}

// Otherwise, we'll load the index file and let
// the framework handle the request from here.
// #region agent log
$logPath = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . '.cursor' . DIRECTORY_SEPARATOR . 'debug.log';
$logDir = dirname($logPath);
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logEntry = json_encode(['id'=>'log_'.time().'_rewrite_require','timestamp'=>time()*1000,'location'=>'rewrite.php:85','message'=>'Requiring index.php','data'=>['uri'=>$uri,'path'=>$path,'fcpath'=>$fcpath,'REQUEST_URI'=>$_SERVER['REQUEST_URI']??'','isFile'=>is_file($path),'isDir'=>is_dir($path)],'sessionId'=>'debug-session','runId'=>'run1','hypothesisId'=>'A']) . "\n";
@file_put_contents($logPath, $logEntry, FILE_APPEND);
// #endregion

require_once $fcpath . 'index.php';
// @codeCoverageIgnoreEnd
