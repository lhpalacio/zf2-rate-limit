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

namespace Lhpalacio\Zf2RateLimit\Mvc;

use Lhpalacio\Zf2RateLimit\Exception\TooManyRequestsHttpException;
use Lhpalacio\Zf2RateLimit\Service\RateLimitService;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\Http\Response as HttpResponse;
use Zend\Http\Request as HttpRequest;
use Zend\Mvc\Router\RouteMatch;

/**
 * RateLimitRequestListener
 *
 * @license MIT
 * @author Luiz Henrique Gomes Palácio <lhpalacio@outlook.com>
 */
class RateLimitRequestListener extends AbstractListenerAggregate
{
    /**
     * @var RateLimitService
     */
    private $rateLimitService;

    /**
     * @param RateLimitService $rateLimitService
     */
    public function __construct(RateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * @param EventManagerInterface $events
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'rateLimitHandler'], -1);
    }

    /**
     * @param MvcEvent $event
     * @return void|HttpResponse
     */
    public function rateLimitHandler(MvcEvent $event)
    {
        /** @var HttpRequest $request */
        $request  = $event->getRequest();
        /** @var HttpResponse $response */
        $response = $event->getResponse();

        if (!$request instanceof HttpRequest) {
            return;
        }

        /** @var RouteMatch $routeMatch */
        $routeMatch = $event->getRouteMatch();

        if (!$routeMatch instanceof RouteMatch || !$this->hasRoute($routeMatch)) {
            return;
        }

        try {
            $this->rateLimitService->rateLimitHandler();
        } catch (TooManyRequestsHttpException $exception) {
            $response = new HttpResponse();
            $response->setStatusCode(429)
                ->setReasonPhrase($exception->getMessage());

            $this->rateLimitService->ensureHeaders($response);

            return $response;
        }

        $this->rateLimitService->ensureHeaders($response);
        $event->setResponse($response);
    }

    /**
     * @param RouteMatch $routeMatch
     * @return bool
     */
    private function hasRoute(RouteMatch $routeMatch)
    {
        $routes = $this->rateLimitService->getRoutes();

        if (!$routes) {
            return false;
        }

        foreach ($routes as $route) {
            if (fnmatch($route, $routeMatch->getMatchedRouteName())) {
                return true;
            }
        }

        return false;
    }
}
