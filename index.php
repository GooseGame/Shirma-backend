<?php
require __DIR__ . '/vendor/autoload.php';

// Маршрутизация
$request = $_SERVER['REQUEST_URI'];
switch (parse_url($request, PHP_URL_PATH)) {
    case '/login/google':
        $controller = new App\Controllers\LoginController();
        $controller->google();
        break;
	case '/logout':
		$controller = new App\Controllers\AuthController();
		$controller->logout();
		break;
	case '/refresh':
		$controller = new App\Controllers\AuthController();
		$controller->refresh();
		break;
	case '/user/get':
		$controller = new App\Controllers\LoginController();
        $controller->getUser();
		break;
	case '/user/changeName':
		$controller = new App\Controllers\LoginController();
        $controller->changeName();
		break;
	case '/user/delete':
		$controller = new App\Controllers\LoginController();
        $controller->deleteAccount();
		break;
    case '/characters/get':
        $controller = new App\Controllers\CharactersController();
        $controller->get();
        break;
	case '/characters/save':
        $controller = new App\Controllers\CharactersController();
        $controller->save();
        break;
	case '/presets/get':
        $controller = new App\Controllers\PresetsController();
        $controller->get();
        break;
	case '/presets/save':
        $controller = new App\Controllers\PresetsController();
        $controller->save();
        break;
    default:
        http_response_code(404);
        echo 'Not Found';
}