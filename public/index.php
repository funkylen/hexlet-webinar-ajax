<?php

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use DI\Container;
use Illuminate\Support\Arr;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();

$container->set('view', function () {
    $twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
    $twig->addExtension(new \Twig\Extension\DebugExtension());
    $twig->getEnvironment()->enableDebug();

    return $twig;
});

$container->set('db', function () {
    return new Database();
});

$app = AppFactory::createFromContainer($container);

$app->add(TwigMiddleware::createFromContainer($app));

$container->set('router', fn() => $app->getRouteCollector()->getRouteParser());

$app->get('/', function (Request $request, Response $response) {
    $filter = $request->getQueryParams()['name'] ?? null;

    $products = $this->get('db')->get('products');

    if (!empty($filter)) {
        $products = array_filter($products, fn($item) => str_contains(mb_strtolower($item['name']), mb_strtolower($filter)));
    }

    return $this->get('view')->render($response, 'layout.twig', [
        'filter' => $filter,
        'products' => $products,
        'cart' => $this->get('db')->get('cart'),
    ]);
})->setName('products');

function add($c, int $productId) {
    $products = $c->get('db')->get('products');
    $cart = $c->get('db')->get('cart');

    $product = Arr::first($products, fn($item) => $item['id'] === $productId);

    if (!$product) {
        throw new \Exception('product_id not found');
    }

    $cart[] = $product['id'];
    $c->get('db')->set('cart', array_values(array_unique($cart)));

    return $cart;
}

$app->post('/add/{product_id}', function (Request $request, Response $response, $args) {
    add($this, $args['product_id']);

    return $response->withHeader('Location', $this->get('router')->urlFor('products'))->withStatus(302);
})->setName('add');

$app->post('/ajax/add/{product_id}', function (Request $request, Response $response, $args) {
    $cart = add($this, $args['product_id']);

    $response->getBody()->write(json_encode($cart));

    return $response->withHeader('Content-Type', 'application/json');
})->setName('ajax.add');

function remove($c, int $productId) {
    $cart = $c->get('db')->get('cart');

    $c->get('db')->set('cart', array_values(array_filter($cart, fn($id) => $id !== $productId)));

    return $c->get('db')->get('cart');
}

$app->post('/remove/{product_id}', function (Request $request, Response $response, $args) {
    remove($this, $args['product_id']);

    return $response->withHeader('Location', $this->get('router')->urlFor('products'))->withStatus(302);
})->setName('remove');

$app->post('/ajax/remove/{product_id}', function (Request $request, Response $response, $args) {
    $cart = remove($this, $args['product_id']);

    $response->getBody()->write(json_encode($cart));

    return $response->withHeader('Content-Type', 'application/json');
})->setName('ajax.remove');

$app->run();
