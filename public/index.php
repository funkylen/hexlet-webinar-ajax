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

function addProductToCart($db, $id)
{
    $products = $db->get('products');
    $cart     = $db->get('cart');

    $product = Arr::first($products, fn($item) => $item['id'] === (int) $id);

    if (!$product) {
        throw new \Exception('product_id not found');
    }

    $cart[$product['id']] = 1;
    $db->set('cart', $cart);

    return $cart;
}

function removeProductFromCart($db, $id)
{
    $cart = $db->get('cart');

    unset($cart[(int) $id]);

    $db->set('cart', $cart);

    return $cart;
}

$app->get('/', function (Request $request, Response $response) {
    $filter = $request->getQueryParams()['name'] ?? null;

    $products = $this->get('db')->get('products');

    if (!empty($filter)) {
        $products = array_filter($products, fn($item) => str_contains(mb_strtolower($item['name']), mb_strtolower($filter)));
    }

    return $this->get('view')->render($response, 'products.twig', [
        'filter'    => $filter,
        'products'  => $products,
        'cart'      => array_keys($this->get('db')->get('cart')),
        'cartCount' => array_sum($this->get('db')->get('cart')),
    ]);
})->setName('products');

$app->get('/cart', function (Request $request, Response $response) {
    $products = $this->get('db')->get('products');
    $cart     = $this->get('db')->get('cart');

    $cartProducts = [];

    foreach ($cart as $id => $count) {
        $product = Arr::first($products, fn($item) => $item['id'] === $id);

        $cartProducts[] = [
            'id'          => $id,
            'name'        => $product['name'],
            'description' => $product['description'],
            'count'       => $count,
            'price'       => $product['price'] * $count,
        ];
    }

    return $this->get('view')->render($response, 'cart.twig', compact('cartProducts', 'cart'));
})->setName('cart');

$app->post('/add/{product_id}', function (Request $request, Response $response, $args) {
    addProductToCart($this->get('db'), $args['product_id']);

    return $response->withHeader('Location', $this->get('router')->urlFor('products'))->withStatus(302);
})->setName('add');

$app->post('/remove/{product_id}', function (Request $request, Response $response, $args) {
    removeProductFromCart($this->get('db'), $args['product_id']);

    return $response->withHeader('Location', $this->get('router')->urlFor('products'))->withStatus(302);
})->setName('remove');


$app->post('/ajax/add/{product_id}', function (Request $request, Response $response, $args) {
    $cart = addProductToCart($this->get('db'), $args['product_id']);

    $response->getBody()->write(json_encode($cart));

    return $response->withHeader('Content-Type', 'application/json');
})->setName('ajax.add');

$app->post('/ajax/remove/{product_id}', function (Request $request, Response $response, $args) {
    $cart = removeProductFromCart($this->get('db'), $args['product_id']);

    $response->getBody()->write(json_encode($cart));

    return $response->withHeader('Content-Type', 'application/json');
})->setName('ajax.remove');

$app->get('/ajax/products/autocomplete', function (Request $request, Response $response) {
    $names = Arr::pluck($this->get('db')->get('products'), 'name');

    $term          = $request->getQueryParams()['term'];
    $filteredNames = array_filter($names, function ($name) use ($term) {
        return str_contains(mb_strtolower($name), mb_strtolower($term));
    });

    $response->getBody()->write(json_encode($filteredNames));

    return $response->withHeader('Content-Type', 'application/json');
})->setName('ajax.products.autocomplete');

$app->run();
