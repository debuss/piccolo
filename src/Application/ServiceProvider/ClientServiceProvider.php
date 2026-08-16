<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Http\Client\Curl\Client;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Http\Client\ClientInterface;

/**
 * Client Service Provider
 *
 * @see https://docs.php-http.org/en/latest/clients/curl-client.html
 */
class ClientServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return $id === ClientInterface::class;
    }

    public function register(): void
    {
        $this->getContainer()->add(ClientInterface::class, Client::class);
    }
}
