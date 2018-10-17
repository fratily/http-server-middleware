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
class RequestHandlerBuilder{

    const INSERT_BEFORE = 0;
    const INSERT_AFTER  = 1;

    /**
     * @var \SplQueue
     */
    private $queue;

    /**
     * @var \SplObjectStorage
     */
    private $registerd;

    /**
     * Constructor
     *
     * @param   ResponseFactoryInterface
     */
    public function __construct(){
        $this->queue        = new \SplQueue();
        $this->registerd    = new \SplObjectStorage();
    }

    public function create(ResponseFactoryInterface $factory){
        return new RequestHandler(clone $this->queue, $factory);
    }

    /**
     * 指定したミドルウェアが既に登録されているか確認する
     *
     * @param   MiddlewareInterface $middleware
     *  確認対象ミドルウェアインスタンス
     *
     * @return  bool
     */
    public function isAlreadyRegistered(MiddlewareInterface $middleware){
        return isset($this->registerd[$middleware]);
    }

    /**
     * ミドルウェアを末尾に追加する
     *
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     *
     * @return  $this
     *
     * @throws  Exception\MiddlewareAlreadyRegisteredException
     */
    public function append(MiddlewareInterface $middleware){
        if($this->isAlreadyRegistered($middleware)){
            throw new Exception\MiddlewareAlreadyRegisteredException(
                ""
            );
        }

        $this->registerd[$middleware]   = true;

        $this->queue->push($middleware);

        return $this;
    }

    /**
     * ミドルウェアを先頭に追加する
     *
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     *
     * @return  $this
     *
     * @throws  Exception\MiddlewareAlreadyRegisteredException
     */
    public function prepend(MiddlewareInterface $middleware){
        if($this->isAlreadyRegistered($middleware)){
            throw new Exception\MiddlewareAlreadyRegisteredException(
                ""
            );
        }

        $this->registerd[$middleware]   = true;

        $this->queue->unshift($middleware);

        return $this;
    }

    /**
     * 指定したミドルウェアクラスの前にミドルウェアを挿入する
     *
     * @param   string  $target
     *  検索対象ミドルウェアクラス名
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     *
     * @return  $this
     *
     * @throws  Exception\ClassNotFoundInQueueException
     * @throws  Exception\MiddlewareAlreadyRegisteredException
     */
    public function insertBeforeClass(
        string $target,
        MiddlewareInterface $middleware
    ){
        if(null === ($index = $this->getClassIndexes($target))){
            throw new Exception\ClassNotFoundInQueueException(
                ""
            );
        }

        $this->insert($index, $middleware, self::INSERT_BEFORE);

        return $this;
    }

    /**
     * 指定したミドルウェアクラスの後にミドルウェアを挿入する
     *
     * @param   string  $target
     *  検索対象ミドルウェアクラス名
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     *
     * @return  $this
     *
     * @throws  Exception\ClassNotFoundInQueueException
     * @throws  Exception\MiddlewareAlreadyRegisteredException
     */
    public function insertAfterClass(
        string $target,
        MiddlewareInterface $middleware
    ){
        if(null === ($index = $this->getClassIndexes($target))){
            throw new Exception\ClassNotFoundInQueueException(
                ""
            );
        }

        $this->insert($index, $middleware, self::INSERT_AFTER);

        return $this;
    }

    /**
     * 指定したミドルウェアオブジェクトの前にミドルウェアを挿入する
     *
     * @param   MiddlewareInterface $target
     *  検索対象ミドルウェアインスタンス
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     *
     * @return  $this
     *
     * @throws  Exception\ObjectNotFoundInQueueException
     */
    public function insertBeforeObject(
        MiddlewareInterface $target,
        MiddlewareInterface $middleware
    ){
        if(null === ($index = $this->getObjectIndexes($target))){
            throw new Exception\ObjectNotFoundInQueueException(
                ""
            );
        }
        $this->insert($index, $middleware, self::INSERT_BEFORE);

        return $this;
    }

    /**
     * 指定したミドルウェアオブジェクトの後にミドルウェアを挿入する
     *
     * @param   MiddlewareInterface $target
     *  検索対象ミドルウェアインスタンス
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     *
     * @return  $this
     *
     * @throws  Exception\ObjectNotFoundInQueueException
     * @throws  Exception\MiddlewareAlreadyRegisteredException
     */
    public function insertAfterObject(
        MiddlewareInterface $target,
        MiddlewareInterface $middleware
    ){
        if(null === ($index = $this->getObjectIndexes($target))){
            throw new Exception\ObjectNotFoundInQueueException(
                ""
            );
        }

        $this->insert($index, $middleware, self::INSERT_AFTER);

        return $this;
    }

    /**
     * 指定したミドルウェアクラスを別のミドルウェアに置き換える
     *
     * @param   MiddlewareInterface $target
     *  置換対象ミドルウェアクラス名
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     *
     * @return  $this
     *
     * @throws  Exception\ClassNotFoundInQueueException
     * @throws  Exception\MiddlewareAlreadyRegisteredException
     */
    public function replaceClass(
        string $target,
        MiddlewareInterface $middleware
    ){
        if(null === ($index = $this->getIndex($target))){
            throw new Exception\ClassNotFoundInQueueException(
                ""
            );
        }

        $this->replace($index, $middleware);

        return $this;
    }

    /**
     * 指定したミドルウェアオブジェクトを別のミドルウェアに置き換える
     *
     * @param   MiddlewareInterface $target
     *  置換対象ミドルウェアインスタンス
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     *
     * @return  $this
     *
     * @throws  Exception\ObjectNotFoundInQueueException
     * @throws  Exception\MiddlewareAlreadyRegisteredException
     */
    public function replaceObject(
        MiddlewareInterface $target,
        MiddlewareInterface $middleware
    ){
        if(null === ($index = $this->getIndex($target))){
            throw new Exception\ObjectNotFoundInQueueException(
                ""
            );
        }

        $this->replace($index, $middleware);

        return $this;
    }

    /**
     * キューの指定インデックスのミドルウェアを置き換える
     *
     * @param   int $index
     *  入れ替え対象ミドルウェアのキュー内におけるインデックス
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     *
     * @return  $this
     *
     * @throws  Exception\MiddlewareAlreadyRegisteredException
     */
    protected function replace(int $index, MiddlewareInterface $middleware){
        if(!isset($this->queue[$index])){
            throw new \InvalidArgumentException(
                ""
            );
        }

        if($this->isAlreadyRegistered($middleware)){
            throw new Exception\MiddlewareAlreadyRegisteredException(
                ""
            );
        }

        $this->registerd[$middleware]   = true;
        $this->queue[$index]            = $middleware;

        return $this;
    }

    /**
     * キューの指定インデックスの前もしくは後ろにミドルウェアを追加する
     *
     * @param   int $index
     *  挿入の基準となるミドルウェアのキュー内のインデックス
     * @param   MiddlewareInterface $middleware
     *  追加するミドルウェアインスタンス
     * @param   int $i
     *  前に追加するか後に追加するか
     *
     * @return  $this
     *
     * @throws  Exception\MiddlewareAlreadyRegisteredException
     */
    protected function insert(
        int $index,
        MiddlewareInterface $middleware,
        int $i = self::INSERT_BEFORE
    ){
        if(!isset($this->queue[$index])){
            throw new \InvalidArgumentException(
                ""
            );
        }

        if($this->isAlreadyRegistered($middleware)){
            throw new Exception\MiddlewareAlreadyRegisteredException(
                ""
            );
        }

        if(self::INSERT_BEFORE !== $i && self::INSERT_AFTER !== $i){
            throw new \InvalidArgumentException(
                ""
            );
        }

        $this->registerd[$middleware]   = true;

        $this->queue->add($index + $i, $middleware);

        return $this;
    }

    /**
     * 指定したミドルウェアオブジェクト(ミドルウェアクラス)のキュー内の位置を返す
     *
     * 複数存在する場合は、最も先頭(インデックスが小さい)もののみが返される。
     *
     * @param   MiddlewareInterface|string  $target
     *  検索対象のミドルウェアインスタンスもしくはミドルウェアクラス名
     *
     * @return  int|null
     */
    protected function getIndex($target){
        if(!is_string($target) && !is_object($target)){
            throw new \InvalidArgumentException(
                ""
            );
        }

        if(is_string($target)){
            if(!class_exists($target) && !interface_exists($target)){
                throw new \InvalidArgumentException(
                    ""
                );
            }

            foreach($this->queue as $index => $middleware){
                if(
                    get_class($middleware) === $target
                    || is_subclass_of($middleware, $target)
                ){
                    return $index;
                }
            }
        }elseif(is_object($target)){
            if(!$target instanceof MiddlewareInterface){
                throw new \InvalidArgumentException(
                    ""
                );
            }

            foreach($this->queue as $index => $middleware){
                if($target === $middleware){
                    return $index;
                }
            }
        }

        return null;
    }
}