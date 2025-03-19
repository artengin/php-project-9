<?php

require __DIR__ . '/../vendor/autoload.php';

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Views\PhpRenderer;
use Dotenv\Dotenv;
use Hexlet\Code\Connection;
use Hexlet\Code\UrlRepository;
use Hexlet\Code\CheckRepository;
use Hexlet\Code\UrlValidator;
use GuzzleHttp\Client;
use DiDom\Document;

session_start();

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeload();
$dotenv->required(['DATABASE_URL'])->notEmpty();

$container = new Container();
$container->set('renderer', function () {
    $render = new PhpRenderer(__DIR__ . '/../templates');
    $render->setLayout('layout.phtml');
    return $render;
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set(\PDO::class, function () {
    $connection = new Connection();
    return $connection->get();
});

$app = AppFactory::createFromContainer($container);
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $viewData = [
        'title' => 'Анализатор страниц',
        'currentRoute' => 'home'
    ];
    return $this->get('renderer')->render($response, "index.phtml", $viewData);
})->setName('home');

$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception, $displayErrorDetails) {
    $response = new \Slim\Psr7\Response();
    $viewData = [
        'title' => 'Страница не найдена!'
    ];
    return $this->get('renderer')->render($response->withStatus(404), "404.phtml", $viewData);
});

$errorMiddleware->setErrorHandler(
    HttpMethodNotAllowedException::class,
    function ($request, $exception, $displayErrorDetails) {
        $response = new \Slim\Psr7\Response();
        $viewData = [
            'title' => 'Ошибка 500!'
        ];
        return $this->get('renderer')->render($response->withStatus(500), "500.phtml", $viewData);
    }
);

$app->get('/urls/{id:[0-9]+}', function ($request, $response, $args) {
    $urlRepo = new UrlRepository($this->get(\PDO::class));
    $checksRepo = new CheckRepository($this->get(\PDO::class));

    $id = $args['id'];

    $urlInfo = $urlRepo->findById((int) $id);

    if (!$urlInfo) {
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml",);
    }

    $flash = $this->get('flash')->getMessages();
    $params = [
        'url' => $urlInfo,
        'flash' => $flash,
        'checks' => $checksRepo->getChecks($args['id']),
        'title' => 'Сайт: ' . $urlInfo['name']
    ];

    return $this->get('renderer')->render($response, 'url.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) {
    $urlRepo = new UrlRepository($this->get(\PDO::class));
    $checksRepo = new CheckRepository($this->get(\PDO::class));
    $urls = $urlRepo->findAll();
    $urlsWithLastChecks = $checksRepo->getLastCheck($urls);

    $checksCollection = collect($urlsWithLastChecks)->keyBy('id');
    $mergedIdWithLastChecks = collect($urls)->map(function ($url) use ($checksCollection) {
        $id = $url['id'];
        $result = isset($checksCollection[$id]) ? array_merge($url, $checksCollection[$id]) : $url;
        return $result;
    })->toArray();

    $params = [
        'urls' => $mergedIdWithLastChecks,
        'title' => 'Список сайтов',
        'currentRoute' => 'urls'
    ];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
})->setName('urls');

$app->post('/urls', function ($request, $response) use ($router) {
    $urlRepo = new UrlRepository($this->get(\PDO::class));
    $urlData = $request->getParsedBodyParam('url');

    $validator = new UrlValidator();
    $errors = $validator->validateUrl($urlData);

    if (count($errors) > 0) {
        $params = [
            'errors' => $errors,
            'url' => $urlData
        ];
        $response = $response->withStatus(422);
        return $this->get('renderer')->render($response, 'index.phtml', $params);
    }

    $parsedUrl = parse_url($urlData['name']);
    $normalizedUrl = strtolower("{$parsedUrl['scheme']}://{$parsedUrl['host']}");
    $findUrl = $urlRepo->findByName($normalizedUrl);

    if ($findUrl) {
        $this->get('flash')->addMessage('info', 'Страница уже существует');
        $params = ['id' => $findUrl['id']];
        return $response->withRedirect($router->urlFor('url', $params));
    }

    $newUrlId = $urlRepo->save($normalizedUrl);
    $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    $params = ['id' => (string) $newUrlId];
    return $response->withRedirect($router->urlFor('url', $params));
});

$app->post('/urls/{url_id}/checks', function ($request, $response, $args) use ($router) {
    $urlId = (int) $args['url_id'];
    $urlRepo = new UrlRepository($this->get(\PDO::class));
    $checksRepo = new CheckRepository($this->get(\PDO::class));
    $client = new Client();
    $url = $urlRepo->findById($urlId);

    try {
        $urlName = $client->get($url["name"]);
        $statusCode = $urlName->getStatusCode();
        $body = (string) $urlName->getBody();

        $document = new Document($body);
        $h1 = optional($document->first('h1'))->text() ?? "";
        $h1 = mb_strlen($h1) > 255 ? mb_strimwidth($h1, 0, 252, "...") : $h1;
        $title = optional($document->first('title'))->text() ?? "";
        $descriptionTag = $document->first('meta[name=description]') ?? "";
        $description = $descriptionTag ? $descriptionTag->getAttribute('content') : "";
        $checksRepo->addCheck($urlId, $statusCode, $h1, $title, $description);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (\Exception $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при проверке, не удалось подключиться');
    }

    $params = ['id' => (string) $urlId];
    return $response->withRedirect($router->urlFor('url', $params));
})->setName('url_check');

$app->run();
