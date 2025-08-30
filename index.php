<?php
require __DIR__ . '/vendor/autoload.php';

$allowedOrigins = [
    "http://localhost:5173",
    "https://shirma.fun"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Маршрутизация
$request = $_SERVER['REQUEST_URI'];
switch (parse_url($request, PHP_URL_PATH)) {
    case '/login/google':
        $controller = new app\Controllers\GoogleLoginController();
        $controller->google();
        break;
	case '/logout':
		$controller = new app\Controllers\AuthController();
		$controller->logout();
		break;
	case '/refresh':
		$controller = new app\Controllers\AuthController();
		$controller->refresh();
		break;
	case '/user/get':
		$controller = new app\Controllers\LoginController();
        $controller->getUser();
		break;
	case '/user/changeName':
		$controller = new app\Controllers\LoginController();
        $controller->changeName();
		break;
	case '/user/delete':
		$controller = new app\Controllers\LoginController();
        $controller->deleteAccount();
		break;
    case '/characters/get':
        $controller = new app\Controllers\CharactersController();
        $controller->get();
        break;
	case '/characters/save':
        $controller = new app\Controllers\CharactersController();
        $controller->save();
        break;
    case '/characters/count':
        $controller = new app\Controllers\CharactersController();
        $controller->count();
        break;
	case '/presets/get':
        $controller = new app\Controllers\PresetsController();
        $controller->get();
        break;
    case '/presets/count':
        $controller = new app\Controllers\PresetsController();
        $controller->count();
        break;
	case '/presets/save':
        $controller = new app\Controllers\PresetsController();
        $controller->save();
        break;
    default:
        http_response_code(404);
        echo 'Not Found';
}