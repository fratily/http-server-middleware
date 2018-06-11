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

use Psr\Http\{
    Message\ServerRequestInterface,
    Message\ResponseInterface,
    Server\RequestHandlerInterface,
    Server\MiddlewareInterface
};
use Interop\Http\Factory\ResponseFactoryInterface;

/**
 *
 */
class RequestHandler implements RequestHandlerInterface{

    const INSERT_BEFORE = 0;
    const INSERT_AFTER  = 1;

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
    private $runningLevel;

    /**
     * @var \SplQueue|null
     */
    private $runningQueue;

    /**
     * Constructor
     *
     * @param   ResponseFactoryInterface
     */
    public function __construct(ResponseFactoryInterface $factory){
        $this->queue        = new \SplQueue();
        $this->factory      = $factory;
        $this->runningLevel = 0;
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
     * レスポンスファクトリを変更する
     *
     * @param   ResponseFactoryInterface    $factory
     *
     * @return  void
     */
    public function setResopnseFactory(ResponseFactoryInterface $factory){
        $this->factory  = $factory;
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

    /**
     * ミドルウェアを末尾に追加する
     *
     * @param   MiddlewareInterface $middleware
     */
    public function append(MiddlewareInterface $middleware){
        $this->queue->push($middleware);

        return $this;
    }

    /**
     * ミドルウェアを先頭に追加する
     *
     * @param   MiddlewareInterface $middleware
     *
     * @return  $this
     */
    public function prepend(MiddlewareInterface $middleware){
        $this->queue->unshift($middleware);

        return $this;
    }

    /**
     * 指定したミドルウェアクラスの前にミドルウェアを挿入する
     *
     * 指定クラスが存在しなければ例外をスローする。
     *
     * @param   string  $target
     * @param   MiddlewareInterface $middleware
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function insertBefore(string $target, MiddlewareInterface $middleware){
        if(($indexes = $this->getClassIndexes($target)) !== null){
            $this->insert($indexes, $middleware, self::INSERT_BEFORE);
        }else{
            throw new \RuntimeException;
        }

        return $this;
    }

    /**
     * 指定したミドルウェアクラスの後にミドルウェアを挿入する
     *
     * 指定クラスが存在しなければ末尾に追加する。
     *
     * @param   string  $target
     * @param   MiddlewareInterface $middleware
     *
     * @return  $this
     */
    public function insertAfterClass(string $target, MiddlewareInterface $middleware){
        if(($indexes = $this->getClassIndexes($target)) !== null){
            $this->insert($indexes, $middleware, self::INSERT_AFTER);
        }else{
            $this->append($middleware);
        }

        return $this;
    }

    /**
     * 指定したミドルウェアオブジェクトの前にミドルウェアを挿入する
     *
     * 指定オブジェクトが存在しなければ例外をスローする。
     *
     * @param   MiddlewareInterface $target
     * @param   MiddlewareInterface $middleware
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function insertBeforeObject(MiddlewareInterface $target, MiddlewareInterface $middleware){
        if(($indexes = $this->getObjectIndexes($target)) !== null){
            $this->insert($indexes, $middleware, self::INSERT_BEFORE);
        }else{
            throw new \RuntimeException;
        }

        return $this;
    }

    /**
     * 指定したミドルウェアオブジェクトの後にミドルウェアを挿入する
     *
     * 指定オブジェクトが存在しなければ末尾に追加する。
     *
     * @param   MiddlewareInterface $target
     * @param   MiddlewareInterface $middleware
     *
     * @return  $this
     */
    public function insertAfterObject(MiddlewareInterface $target, MiddlewareInterface $middleware){
        if(($indexes = $this->getObjectIndexes($target)) !== null){
            $this->insert($indexes, $middleware, self::INSERT_AFTER);
        }else{
            $this->append($middleware);
        }

        return $this;
    }

    /**
     * 指定したミドルウェアクラスを別のミドルウェアに置き換える
     *
     * @param   MiddlewareInterface $target
     * @param   MiddlewareInterface $middleware
     *
     * @return  $this
     */
    public function replaceClass(string $target, MiddlewareInterface $middleware){
        $this->replace($this->getClassIndexes($target) ?? [], $middleware);

        return $this;
    }

    /**
     * 指定したミドルウェアオブジェクトを別のミドルウェアに置き換える
     *
     * @param   MiddlewareInterface $target
     * @param   MiddlewareInterface $middleware
     *
     * @return  $this
     */
    public function replaceObject(MiddlewareInterface $target, MiddlewareInterface $middleware){
        $this->replace($this->getObjectIndexes($target) ?? [], $middleware);

        return $this;
    }

    /**
     * 指定した名前のミドルウェアが登録されているか確認する
     *
     * @param   string  $name
     *
     * @return  bool
     */
    public function hasClass(string $name){
        return $this->getClassIndexes($name) !== null;
    }

    /**
     * 指定したミドルウェアが登録されているか確認する
     *
     * @param   MiddlewareInterface $middleware
     *
     * @return  bool
     */
    public function hasObject(MiddlewareInterface $middleware){
        return $this->getObjectIndexes($middleware) !== null;
    }

    /**
     * キューの指定インデックスの前もしくは後ろにミドルウェアを追加する
     *
     * @param   int[]   $indexes
     * @param   MiddlewareInterface $middleware
     * @param   int $i
     *      前に追加するか後に追加するか
     *
     * @return  void
     *
     * @throws  \InvalidArgumentException
     */
    protected function insert(array $indexes, MiddlewareInterface $middleware, int $i = self::INSERT_BEFORE){
        if($i !== self::INSERT_BEFORE && $i !== self::INSERT_AFTER){
            throw new \InvalidArgumentException();
        }

        foreach(asort(array_unique($indexes)) as $index){
            $index  = $index + $i++;

            if(isset($this->queue[$index])){
                $this->queue->add($index, $middleware);
            }
        }
    }

    /**
     * キューの指定インデックスのミドルウェアを置き換える
     *
     * @param   int[]   $indexes
     * @param   MiddlewareInterface $middleware
     *
     * @return  void
     */
    protected function replace(array $indexes, MiddlewareInterface $middleware){
        foreach($indexes as $index){
            if(isset($this->queue[$index])){
                $this->queue[$index]    = $middleware;
            }
        }
    }

    /**
     * 指定したミドルウェアクラスの位置を返す
     *
     * @param   string  $target
     *
     * @return  int[]|null
     *
     * @todo    $targetがインターフェイスや抽象クラスの場合の対応を書く
     */
    protected function getClassIndexes(string $target){
        $keys   = [];

        foreach($this->queue as $key => $val){
            if(get_class($val) === $target){
                $keys[] = $key;
            }
        }

        return empty($keys) ? null : $keys;
    }

    /**
     * 指定したミドルウェアオブジェクトの位置を返す
     *
     * @param   MiddlewareInterface $target
     *
     * @return  int[]|null
     */
    protected function getObjectIndexes(MiddlewareInterface $target){
        $keys   = [];

        foreach($this->queue as $key => $val){
            if($val === $target){
                $keys[] = $key;
            }
        }

        return empty($keys) ? null : $keys;
    }
}