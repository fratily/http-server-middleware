<?php
/**
 * FratilyPHP Http Server Middleware
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.
 * Redistributions of files must retain the above copyright notice.
 *
 * @author      Kento Oka <kento.oka@kentoka.com>
 * @copyright   (c) Kento Oka
 * @license     MIT
 * @since       1.0.0
 */
namespace Fratily\Http\Server\Middleware;

use Psr\Http\{
    Message\ResponseInterface,
    Message\ServerRequestInterface,
    Server\MiddlewareInterface
};

/**
 *
 */
class SampleMiddleware implements MiddlewareInterface{

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface{
        //  do something
        //  ex) $request    = $request->withAttribute("newAttribute", "value");

        $response = $handler->handle($request);

        //  do something
        //  ex) $response->getBody()->write(time());

        return $response;
    }
}