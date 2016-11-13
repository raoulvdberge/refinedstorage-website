<?php

date_default_timezone_set('Europe/Brussels');

session_start();

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

function getUser() {
    if (isset($_SESSION['user'])) {
        return User::find($_SESSION['user']);
    }
    return null;
}

class NeedsAuthentication
{
    public function __invoke($request, $response, $next)
    {
        if (getUser() == null) {
            return $response->withHeader('Location', '/login');
        }

        return $next($request, $response);
    }
}

class Release extends Illuminate\Database\Eloquent\Model {
    public $timestamps = false;

    public function user() {
        return $this->belongsTo('User');
    }
}

function getReleases() {
    $releases = Release::orderBy('date', 'desc');
    if (getUser() == null) {
        $releases = $releases->where('status', '=', 0);
    }
    return $releases;
}

function getLatestStableRelease() {
    $releases = Release::orderBy('date', 'desc')->where('type', 'release');
    if (getUser() == null) {
        $releases = $releases->where('status', '=', 0);
    }
    return $releases->first();
}

function getRelease($id) {
    $releases = Release::where('id', '=', $id);;
    if (getUser() == null) {
        $releases = $releases->where('status', '=', 0);
    }
    return $releases->first();
}

class Wiki extends Illuminate\Database\Eloquent\Model {
    protected $table = 'wiki';
    public $timestamps = false;

    public function revisions() {
        return $this->hasMany('WikiRevision');
    }
}

function getWikiByUrl($url) {
    $wiki = Wiki::where(['url' => $url]);
    if (getUser() == null) {
        $wiki = $wiki->where('status', '=', 0);
    }
    return $wiki->first();
}

function getWikiByName($name) {
    $wiki = Wiki::where(['name' => $name]);
    if (getUser() == null) {
        $wiki = $wiki->where('status', '=', 0);
    }
    return $wiki->first();
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

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('uri', function () {
        return $_SERVER['REQUEST_URI'];
    }));

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('getUser', function () {
        return getUser();
    }));

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('getReleaseBadge', function ($type) {
        if ($type == 'beta' || $type == 'alpha') {
            return '<span class="tag ' . ($type == 'alpha' ? 'tag-warning' : 'tag-info') . '">' . ucfirst($type) . '</span>';
        }
        return '';
    }));

    return $view;
};

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.html', ['latest' => getLatestStableRelease()]);
});

$app->get('/releases', function (Request $request, Response $response) {
    return $this->view->render($response, 'releases.html', ['releases' => getReleases()->get(), 'latest' => getLatestStableRelease()]);
});

$app->get('/releases/create', function(Request $request, Response $response) {
    return $this->view->render($response, 'releases_create.html');
})->add(new NeedsAuthentication());

$app->post('/releases/create', function(Request $request, Response $response) {
    $version = $request->getParams()['version'];
    $type = $request->getParams()['type'];
    $mc_version = $request->getParams()['mc_version'];
    $url = $request->getParams()['url'];
    $changelog = $request->getParams()['changelog'];

    $release = new Release();
    $release->version = $version;
    $release->type = $type;
    $release->mc_version = $mc_version;
    $release->url = $url;
    $release->changelog = $changelog;
    $release->user_id = getUser()->id;
    $release->date = time();
    $release->status = 0;
    $release->save();

    return $response->withHeader('Location', '/releases/' . $release->id);
})->add(new NeedsAuthentication());

$app->get('/releases/{id}/edit', function (Request $request, Response $response, $args) {
    $release = getRelease($args['id']);
    
    if ($release == null) {
        return $response->withStatus(404);
    }

    return $this->view->render($response, 'releases_edit.html', ['release' => $release]);
})->add(new NeedsAuthentication());

$app->post('/releases/{id}/edit', function (Request $request, Response $response, $args) {
    $release = getRelease($args['id']);
    
    if ($release == null) {
        return $response->withStatus(404);
    }

    $version = $request->getParams()['version'];
    $type = $request->getParams()['type'];
    $mc_version = $request->getParams()['mc_version'];
    $url = $request->getParams()['url'];
    $changelog = $request->getParams()['changelog'];

    $release->version = $version;
    $release->type = $type;
    $release->mc_version = $mc_version;
    $release->url = $url;
    $release->changelog = $changelog;
    $release->user_id = getUser()->id;
    $release->date = time();
    $release->status = 0;
    $release->save();

    return $response->withHeader('Location', '/releases/' . $release->id);
})->add(new NeedsAuthentication());

$app->get('/releases/{id}', function (Request $request, Response $response, $args) {
	$release = getRelease($args['id']);
	
    if ($release == null) {
		return $response->withStatus(404);
	}

	return $this->view->render($response, 'release.html', ['release' => $release]);
});

$app->get('/releases/{id}/delete', function(Request $request, Response $response, $args) {
    $release = Release::find($args['id']);
    
    if ($release == null) {
        return $response->withStatus(404);
    }

    $release->status = 1;
    $release->save();

    return $response->withHeader('Location', '/releases/' . $release->id);
})->add(new NeedsAuthentication());

$app->get('/releases/{id}/restore', function(Request $request, Response $response, $args) {
    $release = Release::find($args['id']);
    
    if ($release == null) {
        return $response->withStatus(404);
    }

    $release->status = 0;
    $release->save();

    return $response->withHeader('Location', '/releases/' . $release->id);
})->add(new NeedsAuthentication());

function findAndParseWiki($url, $revisionId = null) {
    $wiki = getWikiByUrl($url);

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
        $reference = getWikiByName($name);

        return '<a href="' . ($reference == null ? '#' : '/wiki/' . $reference['url']) . '" ' . ($reference == null ? 'style="color: #c00"' : '') . '">' . $name . '</a>';
    }, $revision['body']);

    $wiki['revision'] = $revision;

    return $wiki;
}

$app->get('/wiki', function(Request $request, Response $response) {
    return $this->view->render($response, 'wiki.html', ['wiki' => findAndParseWiki('home'), 'sidebar' => findAndParseWiki('sidebar'), 'old' => false]);
});

$app->get('/wiki/{url}/delete', function(Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);
    
    if ($wiki == null) {
        return $response->withStatus(404);
    }

    $wiki->status = 1;
    $wiki->save();

    return $response->withHeader('Location', '/wiki/' . $wiki->url);
})->add(new NeedsAuthentication());

$app->get('/wiki/{url}/restore', function(Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);
    
    if ($wiki == null) {
        return $response->withStatus(404);
    }

    $wiki->status = 0;
    $wiki->save();

    return $response->withHeader('Location', '/wiki/' . $wiki->url);
})->add(new NeedsAuthentication());

$app->get('/wiki/{url}/revisions', function(Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

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

$app->get('/login', function(Request $request, Response $response) {
    return $this->view->render($response, 'login.html');
});

$app->post('/login', function(Request $request, Response $response) {
    $username = $request->getParams()['username'];
    $password = $request->getParams()['password'];

    $user = User::where(['username' => $username, 'password' => $password])->first();

    if ($user != null) {
        $_SESSION['user'] = $user['id'];
    }

    return $response->withStatus(302)->withHeader('Location', '/');
});

$app->get('/logout', function(Request $request, Response $response) {
    unset($_SESSION['user']);

    return $response->withHeader('Location', '/');
});

$app->run();