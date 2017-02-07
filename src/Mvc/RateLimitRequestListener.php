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
use Zend\Router\RouteMatch;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

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
     * Attach to an event manager
     *
     * @param  EventManagerInterface $events
     * @param  int $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute']);
    }

    /**
     * Listen to the "route" event and attempt to intercept the request
     *
     * If no matches are returned, triggers "dispatch.error" in order to
     * create a 404 response.
     *
     * Seeds the event with the route match on completion.
     *
     * @param  MvcEvent $event
     * @return null|RouteMatch
     */
    public function onRoute(MvcEvent $event)
    {
        $request    = $event->getRequest();
        $router     = $event->getRouter();
        $routeMatch = $router->match($request);

        if (!$request instanceof HttpRequest) {
            return;
        }

        if (!$routeMatch instanceof RouteMatch || !$this->hasRoute($routeMatch)) {
            return;
        }

        try {
            // Check if we're within the limit
            $this->rateLimitService->rateLimitHandler();

            // Update the response
            $response = $event->getResponse();
            $this->rateLimitService->ensureHeaders($response);
            $event->setResponse($response);

        } catch (TooManyRequestsHttpException $exception) {

            // Generate a new response
            $response = new ApiProblemResponse(
                new ApiProblem(429, $exception->getMessage())
            );

            // Add the headers so clients will know when they can try aga
            $this->rateLimitService->ensureHeaders($response);

            // And we're done here
            return $response;
        }
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
