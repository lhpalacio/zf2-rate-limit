<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace Lhpalacio\Zf2RateLimit\Service;

use Lhpalacio\Zf2RateLimit\Storage\StorageInterface;
use Lhpalacio\Zf2RateLimit\Options\RateLimitOptions;
use Zend\Http\Response as HttpResponse;
use Lhpalacio\Zf2RateLimit\Exception\TooManyRequestsHttpException;

/**
 * RateLimitService
 *
 * @license MIT
 * @author Luiz Henrique Gomes Palácio <lhpalacio@outlook.com>
 */
class RateLimitService
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var RateLimitOptions
     */
    private $rateLimitOptions;

    /**
     * @param StorageInterface $storage
     * @param RateLimitOptions $rateLimitOptions
     */
    public function __construct(StorageInterface $storage, RateLimitOptions $rateLimitOptions)
    {
        $this->storage = $storage;
        $this->rateLimitOptions = $rateLimitOptions;
    }

    /**
     * @inheritdoc
     */
    public function rateLimitHandler()
    {
        if ($this->getRemainingCalls() == 0) {
            throw new TooManyRequestsHttpException('Too Many Requests');
        }

        $this->storage->set($this->getUserIp() . '::' . time(), $this->rateLimitOptions->getPeriod());
    }

    /**
     * @param HttpResponse $response
     * @return \Zend\Http\Headers
     */
    public function ensureHeaders(HttpResponse $response)
    {
        $headers = $response->getHeaders();

        $headers->addHeaderLine('X-RateLimit-Limit', $this->getLimit());
        $headers->addHeaderLine('X-RateLimit-Remaining', $this->getRemainingCalls());
        $headers->addHeaderLine('X-RateLimit-Reset', $this->getTimeToReset());

        return $headers;
    }

    /**
     * @return int
     */
    private function getLimit()
    {
        $limit = $this->rateLimitOptions->getLimit();
        return $limit;
    }

    /**
     * @return int
     */
    private function getRemainingCalls()
    {
        $limit = $this->rateLimitOptions->getLimit();
        $calls = $this->storage->count($this->getUserIp());

        return $limit - $calls;
    }

    /**
     * @return int
     */
    private function getTimeToReset()
    {
        $keys = $this->storage->getKeys($this->getUserIp());

        $times = array_map(function ($key) {
            return str_replace($this->getUserIp().'::', null, $key);
        }, $keys);

        $time = max($times);
        $time = $time + $this->rateLimitOptions->getPeriod();
        return $time;
    }

    /**
     * @return mixed
     */
    private function getUserIp()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @return array
     */
    public function getRoutes()
    {
        return $this->rateLimitOptions->getRoutes();
    }
}
