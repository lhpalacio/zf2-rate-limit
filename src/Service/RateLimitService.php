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

use Lhpalacio\Zf2RateLimit\Options\RateLimitOptions;
use Zend\Cache\Storage\Adapter\AbstractAdapter;
use Zend\Http\Response as HttpResponse;
use Zend\Http\PhpEnvironment\RemoteAddress;
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
     * @var AbstractAdapter
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
    public function __construct(AbstractAdapter $storage, RateLimitOptions $rateLimitOptions)
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

        // Get the data from the cache
        $data = $this->getDataFromStorage();

        // Set the current request time
        $data[] = time();

        // Set the data
        $this->saveDataInStorage($data);
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
        // Get the data from the cache
        $data = $this->getDataFromStorage();

        $limit = $this->rateLimitOptions->getLimit();
        $calls = count($data);

        return $limit - $calls;
    }

    /**
     * @return int
     */
    private function getTimeToReset()
    {
        // Get the data from the cache
        $data = $this->getDataFromStorage();

        if (empty($data)) {
            return time();
        }
        else {
            $time = max($data);
            $time = $time + $this->rateLimitOptions->getPeriod();
            return $time;
        }
    }

    /**
     * @return array
     */
    private function getDataFromStorage()
    {
        // Get the data from the cache
        $data = $this->storage->getItem($this->getUserIp(), $success);

        if (!$success) {
            // We had no value set
            $data = [];
        }
        else
        {
            // Un"serialize" (unjsonize? :P)
            $data = json_decode($data, true);

            if (!is_array($data))
            {
                // Error prevention
                $data = [];
                $this->saveDataInStorage($data);
            }

            // Whether we need to update the data
            $doUpdate = false;

            foreach ($data as $key => $value) {
                if ($value <= (time() - $this->rateLimitOptions->getPeriod())) {
                    // Get rid of this
                    unset($data[$key]);

                    // Flag as needing update
                    $doUpdate = true;
                }
            }

            if ($doUpdate) {
                // Set the new data object
                $this->saveDataInStorage($data);
            }
        }

        return $data;
    }

    /**
     * @return bool
     */
    private function saveDataInStorage($data)
    {
        // Set the new data object
        return $this->storage->setItem($this->getUserIp(), json_encode($data));
    }

    /**
     * @return string
     */
    private function getUserIp()
    {
        // Begin grabbing the remote address
        $remote = new RemoteAddress;
        $remoteAddress = $remote->setUseProxy()->getIpAddress();

        switch ($remoteAddress) {
            case '::1':
            case '127.0.0.1':
                $remoteAddress = 'localhost';
                break;

            default:
                $remoteAddress = str_replace(['.', ':'], '-', $remoteAddress);
                break;
        }

        return $remoteAddress;
    }

    /**
     * @return array
     */
    public function getRoutes()
    {
        return $this->rateLimitOptions->getRoutes();
    }
}
