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

class User extends Illuminate\Database\Eloquent\Model {
}

class Release extends Illuminate\Database\Eloquent\Model {
    public function user() {
        return $this->belongsTo('User');
    }
}

class Wiki extends Illuminate\Database\Eloquent\Model {
    protected $table = 'wiki';

    public function revisions() {
        return $this->hasMany('WikiRevision');
    }
}

class WikiRevision extends Illuminate\Database\Eloquent\Model {
    public function user() {
        return $this->belongsTo('User');
    }
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
    return $this->view->render($response, 'home.html', ['latest' => Release::orderBy('date', 'desc')->first()]);
});

$app->get('/releases', function (Request $request, Response $response) {
    return $this->view->render($response, 'releases.html', ['releases' => Release::orderBy('date', 'desc')->get()]);
});

$app->get('/releases/{id}', function (Request $request, Response $response, $args) {
	$release = Release::find($args['id']);
	
    if ($release == null) {
		return $response->withStatus(404);
	}

	return $this->view->render($response, 'release.html', ['release' => $release]);
});

function findAndParseWiki($url, $revisionId = null) {
    $wiki = Wiki::where(['url' => $url])->first();

    if ($wiki == null) {
        return null;
    }

    $revision = $wiki->revisions()->orderBy('date', 'desc');
    if ($revisionId == null) {
        $revision = $revision->first();
    } else {
        $revision = $revision->where('id', $revisionId)->first();
    }

    if ($revision == null) {
        return null;
    }

    $parser = new Parsedown();

    $revision['body'] = $parser->text($revision['body']);
    $revision['body'] = preg_replace_callback("/\\[\\[.+?\\]\\]/", function ($match) {
        $name = substr($match[0], 2, -2);
        $reference = Wiki::where(['name' => $name])->first();

        return '<a href="' . ($reference == null ? '#' : '/wiki/' . $reference['url']) . '" ' . ($reference == null ? 'style="color: #c00"' : '') . '">' . $name . '</a>';
    }, $revision['body']);

    $wiki['revision'] = $revision;

    return $wiki;
}

$app->get('/wiki', function(Request $request, Response $response) {
    return $this->view->render($response, 'wiki.html', ['wiki' => findAndParseWiki('home'), 'sidebar' => findAndParseWiki('sidebar'), 'old' => false]);
});

$app->get('/wiki/{url}/revisions', function(Request $request, Response $response, $args) {
    $wiki = Wiki::where(['url' => $args['url']])->first();

    if ($wiki == null) {
        return $response->withStatus(404);
    }

    return $this->view->render($response, 'wiki_revisions.html', ['wiki' => $wiki, 'revisions' => $wiki->revisions()->orderBy('date', 'desc')->get()]);
});

$app->get('/wiki/{url}[/{revision}]', function(Request $request, Response $response, $args) {
    $wiki = findAndParseWiki($args['url'], $args['revision']);

    if ($wiki == null) {
        return $response->withStatus(404);
    }

    return $this->view->render($response, 'wiki.html', ['wiki' => $wiki, 'sidebar' => findAndParseWiki('sidebar'), 'old' => $args['revision'] != null]);
});

$app->run();