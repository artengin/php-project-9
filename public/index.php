<?php

require __DIR__ . '/../vendor/autoload.php';

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Dotenv\Dotenv;
use Hexlet\Code\Connection;
use Hexlet\Code\UrlRepository;
use Hexlet\Code\UrlValidator;

session_start();

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeload();
$dotenv->required(['DATABASE_URL'])->notEmpty();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
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
    return $this->get('renderer')->render($response, "index.phtml");
})->setName('home');

$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception, $displayErrorDetails) {
    $response = new \Slim\Psr7\Response();
    return $this->get('renderer')->render($response->withStatus(404), "404.phtml");
});

$app->get('/urls/{id}', function ($request, $response, $args) use ($container) {
    $urlRepo = new UrlRepository($container->get(\PDO::class));

    $id = $args['id'];

    if (!is_numeric($id)) {
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml",);
    }

    $urlInfo = $urlRepo->findById($id);

    if (!$urlInfo) {
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml",);
    }

    $flash = $container->get('flash')->getMessages();
    $params = [
        'url' => $urlInfo,
        'flash' => $flash
    ];

    return $container->get('renderer')->render($response, 'url.phtml', $params);
})->setName('url');

$app->get('/urls', function ($request, $response) use ($container) {
    $urlRepo = new UrlRepository($container->get(\PDO::class));
    $urls = $urlRepo->findAll();

    $params = [
        'urls' => $urls
    ];
    return $container->get('renderer')->render($response, 'urls.phtml', $params);
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

$app->run();
