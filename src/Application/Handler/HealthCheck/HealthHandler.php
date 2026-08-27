<?php declare(strict_types=1);

namespace Application\Handler\HealthCheck;

use Laminas\Db\Adapter\Adapter;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\SimpleCache\CacheInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

readonly class HealthHandler implements RequestHandlerInterface
{

    public function __construct(
        private ?Adapter $adapter = null,
        private ?CacheInterface $cache = null
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $connections = [
            'database' => 'N/A',
            'cache' => 'N/A',
        ];

        if ($this->adapter !== null) {
            $connections['database'] = $this->adapter->getDriver()->getConnection()->connect()->isConnected()
                ? 'OK' :
                'KO';
        }

        if ($this->cache !== null) {
            $connections['cache'] = 'KO';

            $key = sprintf('healthcheck_test_%s', md5((string)mt_rand(1, 1000)));

            try {
                $success = $this->cache->set($key, 'OK');
                if ($success) {
                    $connections['cache'] = $this->cache->get($key) === 'OK' ? 'OK' : 'KO';
                }

                $this->cache->delete($key);
            } catch (Throwable) {}
        }

        $status = in_array('KO', $connections, true)
            ? 'KO'
            :'OK';

        return new JsonResponse([
            'status' => $status,
            'connections' => $connections
        ]);
    }
}
