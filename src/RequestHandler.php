<?php
/**
 * FratilyPHP Http Server Middleware
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.
 * Redistributions of files must retain the above copyright notice.
 *
 * @author      Kento Oka <kento-oka@kentoka.com>
 * @copyright   (c) Kento Oka
 * @license     MIT
 * @since       1.0.0
 */
namespace Fratily\Http\Server;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 *
 */
class RequestHandler implements RequestHandlerInterface{

    /**
     * @var \SplQueue
     */
    private $queue;

    /**
     * @var ResponseFactoryInterface
     */
    private $factory;

    /**
     * @var int
     */
    private $runningLevel   = 0;

    /**
     * @var \SplQueue|null
     */
    private $runningQueue;

    /**
     * Constructor
     *
     * @param   ResponseFactoryInterface
     */
    public function __construct(
        \SplQueue $queue,
        ResponseFactoryInterface $factory
    ){
        foreach($queue as $middleware){
            if(!$middleware instanceof MiddlewareInterface){
                throw new \InvalidArgumentException(
                    ""
                );
            }
        }

        $this->queue    = $queue;
        $this->factory  = $factory;
    }

    /**
     * Clone
     */
    public function __clone(){
        $this->queue        = clone $this->queue;
        $this->runningLevel = 0;
        $this->runningQueue = null;
    }

    /**
     * {@inheritdoc}
     *
     * @param   ServerRequestInterface  $request
     *
     * @return  ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface{
        if($this->runningQueue === null){
            $this->runningQueue = clone $this->queue;
        }

        $this->runningLevel++;

        if($this->runningQueue->isEmpty()){
            $response   = $this->factory->createResponse();
        }else{
            $response   = $this->runningQueue->dequeue()->process($request, $this);
        }

        $this->runningLevel--;

        if($this->runningLevel === 0){
            $this->runningQueue = null;
        }

        return $response;
    }
}