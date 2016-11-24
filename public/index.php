<?php

$roles = [
    'admin' => 300,
    'contributor' => 200,
    'editor' => 100,
    'user' => 0
];

date_default_timezone_set('Europe/Brussels');

session_start();

require '../vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'sqlite',
    'database'  => __DIR__ . '/../refinedstorage.sqlite',
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
    private $accessLevel;

    public function __construct($accessLevel)
    {
        $this->accessLevel = $accessLevel;
    }

    public function __invoke($request, $response, $next)
    {
        if (getUser() == null) {
            return $response->withStatus(503)->withHeader('Location', '/login');
        }

        if (getUser()->role < $this->accessLevel) {
            return $response->withStatus(503)->withHeader('Location', '/503');
        }

        return $next($request, $response);
    }
}

class Maintenance
{
    public function __invoke($request, $response, $next)
    {
        if (getenv('RS_MAINTENANCE') == 'true' && $_SERVER['REQUEST_URI'] != '/maintenance') {
            return $response->withStatus(503)->withHeader('Location', '/maintenance');
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

function getWikiRevision($wiki, $revisionHash) {
    $revision = $wiki->revisions()->orderBy('date', 'desc');
    if ($revisionHash == null) {
        $revision = $revision->first();
    } else {
        $revision = $revision->where('hash', $revisionHash)->first();
    }
    return $revision;
}

class WikiRevision extends Illuminate\Database\Eloquent\Model {
    public $timestamps = false;

    public function user() {
        return $this->belongsTo('User');
    }

    public function revertedBy() {
        return $this->belongsTo('User', 'reverted_by', 'id');
    }

    public function revertedFrom() {
        return $this->belongsTo('WikiRevision', 'reverted_from', 'id');
    }
}

$app = new \Slim\App;

$app->add(new Maintenance());

$container = $app->getContainer();
$container['view'] = function ($container) use ($roles) {
    $view = new \Slim\Views\Twig('../templates');
    
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

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('can', function ($role) use ($roles) {
        $user = getUser();

        return $user != null && $user->role >= $roles[$role];
    }));

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('role', function ($role) use ($roles) {
        foreach ($roles as $name => $value) {
            if ($role >= $value) {
                return ucfirst($name);
            }
        }
        return 'Unknown';
    }));

    $view->getEnvironment()->addFunction(new Twig_SimpleFunction('getReleaseBadge', function ($type) {
        if ($type == 'beta' || $type == 'alpha') {
            return '<span class="tag ' . ($type == 'alpha' ? 'tag-warning' : 'tag-info') . '">' . ucfirst($type) . '</span>';
        }
        return '';
    }));

    return $view;
};

$container['notFoundHandler'] = function ($c) {
    return function (Request $request, Response $response) use ($c) {
        return $c->view->render($response->withStatus(404), '404.html');
    };
};

$app->get('/', function (Request $request, Response $response) {
    return $this->view->render($response, 'home.html', ['latest' => getLatestStableRelease(), 'landing' => findAndParseWiki('_landing')]);
});

$app->get('/releases', function (Request $request, Response $response) {
    return $this->view->render($response, 'releases.html', ['releases' => getReleases()->get(), 'latest' => getLatestStableRelease()]);
});

$app->get('/releases/create', function(Request $request, Response $response) {
    return $this->view->render($response, 'releases_create.html', ['errors' => []]);
})->add(new NeedsAuthentication($roles['contributor']));

function validateRelease($version, $type, $mc_version, $url) {
    $errors = [];
    if (empty($version)) {
        $errors[] = 'Missing version';
    }
    if ($type != 'alpha' && $type != 'beta' && $type != 'release') {
        $errors[] = 'Invalid release type';
    }
    if (empty($mc_version)) {
        $errors[] = 'Missing Minecraft version';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid URL';
    }
    return $errors;
}

$app->post('/releases/create', function(Request $request, Response $response) {
    $version = $request->getParams()['version'];
    $type = $request->getParams()['type'];
    $mc_version = $request->getParams()['mc_version'];
    $url = $request->getParams()['url'];
    $changelog = $request->getParams()['changelog'];

    $errors = validateRelease($version, $type, $mc_version, $url);

    if (count($errors) == 0) {
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
    } else {
        return $this->view->render($response, 'releases_create.html', ['errors' => $errors]);
    }
})->add(new NeedsAuthentication($roles['contributor']));

$app->get('/releases/{id}/edit', function (Request $request, Response $response, $args) {
    $release = getRelease($args['id']);
    
    if ($release == null) {
        return $response->withStatus(404);
    }

    return $this->view->render($response, 'releases_edit.html', ['release' => $release, 'errors' => []]);
})->add(new NeedsAuthentication($roles['contributor']));

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

    $errors = validateRelease($version, $type, $mc_version, $url);

    if (count($errors) == 0) {
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
    } else {
        return $this->view->render($response, 'releases_edit.html', ['release' => $release, 'errors' => $errors]);
    }
})->add(new NeedsAuthentication($roles['contributor']));

$app->get('/releases/{id}', function (Request $request, Response $response, $args) {
	$release = getRelease($args['id']);
	
    if ($release == null) {
		return $response->withStatus(404);
	}

	return $this->view->render($response, 'releases_view.html', ['release' => $release]);
});

$app->get('/releases/{id}/delete', function(Request $request, Response $response, $args) {
    $release = getRelease($args['id']);
    
    if ($release == null) {
        return $response->withStatus(404);
    }

    $release->status = 1;
    $release->save();

    return $response->withHeader('Location', '/releases/' . $release->id);
})->add(new NeedsAuthentication($roles['contributor']));

$app->get('/releases/{id}/restore', function(Request $request, Response $response, $args) {
    $release = getRelease($args['id']);
    
    if ($release == null) {
        return $response->withStatus(404);
    }

    $release->status = 0;
    $release->save();

    return $response->withHeader('Location', '/releases/' . $release->id);
})->add(new NeedsAuthentication($roles['contributor']));

function findAndParseWiki($url, $revisionHash = null) {
    $wiki = getWikiByUrl($url);

    if ($wiki == null) {
        return null;
    }

    $revision = getWikiRevision($wiki, $revisionHash);

    if ($revision == null) {
        return null;
    }

    $parser = new Parsedown();

    $revision['body'] = $parser->text($revision['body']);
    $revision['body'] = preg_replace_callback("/\\[\\[(.+?)(\\=(.+?))?\\]\\]/", function ($matches) {
        if (count($matches) == 4) {
            $reference = getWikiByName($matches[3]);

            return '<a href="' . ($reference == null ? '#' : '/wiki/' . $reference['url']) . '" ' . ($reference == null ? 'style="color: #c00"' : '') . '">' . $matches[1] . '</a>';
        } else if (count($matches) == 2) {
            $reference = getWikiByName($matches[1]);

            return '<a href="' . ($reference == null ? '#' : '/wiki/' . $reference['url']) . '" ' . ($reference == null ? 'style="color: #c00"' : '') . '">' . $matches[1] . '</a>';
        }
    }, $revision['body']);
    $revision['body'] = str_replace('<table>', '<table class="table">', $revision['body']);

    $wiki['revision'] = $revision;

    return $wiki;
}

$app->get('/wiki', function(Request $request, Response $response) {
    return $this->view->render($response, 'wiki.html', ['wiki' => findAndParseWiki('_home'), 'sidebar' => findAndParseWiki('_sidebar'), 'old' => false]);
});

$app->get('/wiki/create', function(Request $request, Response $response, $args) {
    return $this->view->render($response, 'wiki_create.html', ['errors' => []]);
})->add(new NeedsAuthentication($roles['editor']));

$app->get('/wiki/pages', function(Request $request, Response $response, $args) {
    $wikis = Wiki::all();
    foreach ($wikis as $wiki) {
        $wiki['last_revision'] = getWikiRevision($wiki, null);
    }
    return $this->view->render($response, 'wiki_pages.html', ['wikis' => $wikis]);
})->add(new NeedsAuthentication($roles['editor']));

$app->post('/wiki/create', function(Request $request, Response $response, $args) {
    $url = $request->getParams()['url'];
    $name = $request->getParams()['name'];
    $body = $request->getParams()['body'];

    $errors = validateWiki(null, $url, null, $name);

    if (count($errors) == 0) {
        $wiki = new Wiki();
        $wiki->url = $url;
        $wiki->name = $name;
        $wiki->status = 0;
        $wiki->save();

        $rev = new WikiRevision();
        $rev->wiki_id = $wiki->id;
        $rev->body = $body;
        $rev->user_id = getUser()->id;
        $rev->reverted_by = 0;
        $rev->reverted_from = 0;
        $rev->date = time();
        $rev->hash = md5(microtime());

        $rev->save();

        if (isset($request->getParams()['submit_back'])) {
            return $response->withHeader('Location', '/wiki/' . $wiki->url);
        } else {
            return $response->withHeader('Location', '/wiki/' . $wiki->url . '/edit');
        }
    } else {
        return $this->view->render($response, 'wiki_create.html', ['errors' => $errors]);
    }
})->add(new NeedsAuthentication($roles['editor']));

function validateWiki($currentUrl, $url, $currentName, $name) {
    $errors = [];
    if (empty($url)) {
        $errors[] = 'Missing URL';
    } else if ($currentUrl != $url) {
        if (getWikiByUrl($url) != null) {
            $errors[] = 'URL conflict';
        }
    }
    if (!empty($url) && !ctype_alnum(str_replace(['-', '_'], '', $url))) { 
        $errors[] = 'Invalid URL, allowed: alphabetical letters, numbers, - and _.';
    }
    if (empty($name)) {
        $errors[] = 'Missing name';
    } else if ($currentName != $name) {
        if (getWikiByName($name) != null) {
            $errors[] = 'Name conflict';
        }
    }
    return $errors;
}

$app->get('/wiki/{url}/delete', function(Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);
    
    if ($wiki == null) {
        return $response->withStatus(404);
    }

    $wiki->status = 1;
    $wiki->save();

    return $response->withHeader('Location', '/wiki/' . $wiki->url);
})->add(new NeedsAuthentication($roles['editor']));

$app->get('/wiki/{url}/restore', function(Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);
    
    if ($wiki == null) {
        return $response->withStatus(404);
    }

    $wiki->status = 0;
    $wiki->save();

    return $response->withHeader('Location', '/wiki/' . $wiki->url);
})->add(new NeedsAuthentication($roles['editor']));

$app->get('/wiki/{url}/edit', function(Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);
    
    if ($wiki == null) {
        return $response->withStatus(404);
    }

    $wiki['revision'] = $wiki->revisions()->orderBy('date', 'desc')->first();

    return $this->view->render($response, 'wiki_edit.html', ['wiki' => $wiki, 'errors' => [], 'body' => $wiki->body]);
})->add(new NeedsAuthentication($roles['editor']));

$app->post('/wiki/{url}/edit', function(Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);
    
    if ($wiki == null) {
        return $response->withStatus(404);
    }

    $url = $request->getParams()['url'];
    $name = $request->getParams()['name'];
    $body = $request->getParams()['body'];

    $errors = validateWiki($wiki->url, $url, $wiki->name, $name);

    if (count($errors) == 0) {
        $wiki->name = $request->getParams()['name'];
        $wiki->url = $request->getParams()['url'];
        $wiki->save();

        $rev = new WikiRevision();
        $rev->wiki_id = $wiki->id;
        $rev->body = $request->getParams()['body'];
        $rev->user_id = getUser()->id;
        $rev->reverted_by = 0;
        $rev->reverted_from = 0;
        $rev->date = time();
        $rev->hash = md5(microtime());

        $rev->save();

        if (isset($request->getParams()['submit_back'])) {
            return $response->withHeader('Location', '/wiki/' . $wiki->url);
        } else {
            return $response->withHeader('Location', '/wiki/' . $wiki->url . '/edit');
        }
    } else {
        return $this->view->render($response, 'wiki_edit.html', ['wiki' => $wiki, 'errors' => $errors, 'body' => $body]);
    }
})->add(new NeedsAuthentication($roles['editor']));

$app->get('/wiki/{url}/revisions', function(Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        return $response->withStatus(404);
    }

    return $this->view->render($response, 'wiki_revisions.html', ['wiki' => $wiki, 'revisions' => $wiki->revisions()->orderBy('date', 'desc')->get()]);
});

$app->get('/wiki/{url}/{revision}/revert', function(Request $request, Response $response, $args) {
    $wiki = getWikiByUrl($args['url']);

    if ($wiki == null) {
        return $response->withStatus(404);
    }

    $revOld = getWikiRevision($wiki, $args['revision']);

    if ($revOld == null) {
        return $response->withStatus(404);
    }

    $rev = new WikiRevision();
    $rev->wiki_id = $wiki->id;
    $rev->body = $revOld->body;
    $rev->user_id = $revOld->user_id;
    $rev->reverted_by = getUser()->id;
    $rev->reverted_from = $revOld->id;
    $rev->date = time();
    $rev->hash = md5(microtime());

    $rev->save();

    return $response->withHeader('Location', '/wiki/' . $wiki->url);
})->add(new NeedsAuthentication($roles['editor']));

$app->get('/wiki/{url}[/{revision}]', function(Request $request, Response $response, $args) {
    $wiki = findAndParseWiki($args['url'], isset($args['revision']) ? $args['revision'] : null);

    if ($wiki == null) {
        return $response->withStatus(404);
    }

    return $this->view->render($response, 'wiki.html', ['wiki' => $wiki, 'sidebar' => findAndParseWiki('_sidebar'), 'old' => isset($args['revision'])]);
});

$app->get('/login', function(Request $request, Response $response) {
    return $this->view->render($response, 'login.html', ['failed' => false]);
});

$app->post('/login', function(Request $request, Response $response) {
    $email = $request->getParams()['email'];
    $password = $request->getParams()['password'];

    $user = User::where(['email' => $email])->first();

    if ($user != null && password_verify($password, $user->password)) {
        $_SESSION['user'] = $user['id'];
        
        return $response->withStatus(302)->withHeader('Location', '/');
    } else {
        return $this->view->render($response, 'login.html', ['failed' => true]);
    }
});

$app->get('/logout', function(Request $request, Response $response) {
    unset($_SESSION['user']);

    return $response->withHeader('Location', '/');
})->add(new NeedsAuthentication($roles['user']));

$app->get('/profile/{username}', function(Request $request, Response $response, $args) {
    $user = User::where('username', '=', $args['username'])->first();

    if ($user == null) {
        return $response->withStatus(404);
    }

    return $this->view->render($response, 'profile.html', ['profile' => $user]);
});

$app->get('/search', function(Request $request, Response $response) {
    return $this->view->render($response, 'search.html', ['show' => false, 'query' => '']);
});

$app->post('/search', function(Request $request, Response $response) {
    $query = $request->getParams()['query'];
    $wikis = [];

    if (!empty($query)) {
        $allWikis = Wiki::where('status', '=', 0)->get();

        foreach ($allWikis as $wiki) {
            if (stristr($wiki->name, $query)) {
                $wikis[] = $wiki;
            } else {
                $rev = getWikiRevision($wiki, null);

                if (stristr($rev->body, $query)) {
                    $wikis[] = $wiki;
                }
            }
        }
    }

    return $this->view->render($response, 'search.html', ['show' => !empty($query), 'results' => $wikis, 'query' => $query]);
});

$app->get('/503', function(Request $request, Response $response) {
    return $this->view->render($response->withStatus(503), '503.html');
});

$app->get('/maintenance', function(Request $request, Response $response) {
    return $this->view->render($response->withStatus(503), 'maintenance.html');
});

$app->run();
