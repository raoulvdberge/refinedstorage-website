<?php

date_default_timezone_set('Europe/Brussels');

require 'vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'sqlite',
    'database'  => __DIR__.'/refinedstorage.sqlite',
    'prefix' => ''
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

class Release extends Illuminate\Database\Eloquent\Model {}
class Wiki extends Illuminate\Database\Eloquent\Model {
    protected $table = 'wiki';
}

$app = new \Slim\App;

$container = $app->getContainer();
$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('templates');
    
    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');

    $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));

    $function = new Twig_SimpleFunction('uri', function () {
    	return $_SERVER['REQUEST_URI'];
    });

    $view->getEnvironment()->addFunction($function);

    return $view;
};
$container['uri'] = $_SERVER['REQUEST_URI'];

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.html');
});

$app->get('/releases', function (Request $request, Response $response) {
    return $this->view->render($response, 'releases.html', ['releases' => Release::all()]);
});

$app->get('/releases/{id}', function (Request $request, Response $response, $args) {
	$release = Release::find($args['id']);
	
    if ($release == null) {
		return $response->withStatus(404);
	}

	return $this->view->render($response, 'release.html', ['release' => $release]);
});

$app->get('/wiki/{url}', function(Request $request, Response $response, $args) {
    $wiki = Wiki::where(['url' => $args['url']])->first();

    if ($wiki == null) {
        return $response->withStatus(404);
    }

    $wiki['body'] = preg_replace_callback("/\\[\\[.+?\\]\\]/", function ($match) {
        $name = substr($match[0], 2, -2);
        $reference = Wiki::where(['name' => $name])->first();

        return '<a href="' . ($reference == null ? '#' : '/wiki/' . $reference['url']) . '" ' . ($reference == null ? 'style="color: #c00"' : '') . '">' . $name . '</a>';
    }, $wiki['body']);

    return $this->view->render($response, 'wiki.html', ['wiki' => $wiki]);
});

$app->run();