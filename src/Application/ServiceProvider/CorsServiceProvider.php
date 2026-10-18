<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use Borsch\Config\Config;
use InvalidArgumentException;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Mezzio\Cors\Configuration\{ConfigurationInterface, ProjectConfiguration, RouteConfigurationFactory};
use Mezzio\Cors\Middleware\CorsMiddleware;
use Mezzio\Cors\Service\{ConfigurationLocator, Cors, ResponseFactory as CorsResponseFactory};
use Mezzio\Router\RouterInterface;
use Psr\Http\Message\{ResponseFactoryInterface, ServerRequestFactoryInterface, UriFactoryInterface};

/**
 * CORS Service Provider
 *
 * Builds the CORS middleware from the configuration (see `.env.example`), as the factories of mezzio-cors expect the
 * configuration of a Mezzio application:
 *
 *  - `CORS_ALLOWED_ORIGINS`: the allowed origins, separated by commas, with `*` wildcards (`https://*.example.com`),
 *    or `*` for any origin. CORS is disabled when empty (default).
 *  - `CORS_ALLOWED_HEADERS`: the request headers allowed, `Content-Type` by default, for the JSON requests.
 *  - `CORS_EXPOSED_HEADERS`: the response headers readable by the browser, besides the CORS-safelisted ones (`ETag`,
 *    ...).
 *  - `CORS_ALLOW_CREDENTIALS`: whether cookies and `Authorization` headers are sent, `false` by default.
 *  - `CORS_MAX_AGE`: how long, in seconds, the browser caches a preflight response, not cached by default.
 *
 * The allowed HTTP methods are not configured: they are the methods of the route that is requested.
 *
 * @see https://docs.mezzio.dev/mezzio-cors/
 */
class CorsServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return in_array($id, [
            ConfigurationInterface::class,
            CorsMiddleware::class
        ]);
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(ConfigurationInterface::class, static fn (Config $config) => self::configuration($config))
            ->addArgument(Config::class);

        $this
            ->getContainer()
            ->add(
                CorsMiddleware::class,
                static fn (
                    ConfigurationInterface $configuration,
                    UriFactoryInterface $uriFactory,
                    ServerRequestFactoryInterface $serverRequestFactory,
                    ResponseFactoryInterface $responseFactory,
                    RouterInterface $router
                ) => new CorsMiddleware(
                    new Cors($uriFactory),
                    new ConfigurationLocator(
                        $configuration,
                        $serverRequestFactory,
                        $router,
                        new RouteConfigurationFactory()
                    ),
                    new CorsResponseFactory($responseFactory)
                )
            )
            ->addArguments([
                ConfigurationInterface::class,
                UriFactoryInterface::class,
                ServerRequestFactoryInterface::class,
                ResponseFactoryInterface::class,
                RouterInterface::class
            ]);
    }

    /**
     * @throws InvalidArgumentException If credentials are allowed for any origin.
     */
    private static function configuration(Config $config): ProjectConfiguration
    {
        $origins = self::list($config->getOrDefault('CORS_ALLOWED_ORIGINS', ''));
        $maxAge = trim((string)$config->getOrDefault('CORS_MAX_AGE', ''));
        $credentialsAllowed = filter_var(
            $config->getOrDefault('CORS_ALLOW_CREDENTIALS', false),
            FILTER_VALIDATE_BOOL
        );

        // mezzio-cors answers with the origin of the request when any origin is allowed: with credentials, any website
        // could send requests on behalf of the users.
        if ($credentialsAllowed && in_array(ConfigurationInterface::ANY_ORIGIN, $origins, true)) {
            throw new InvalidArgumentException(
                'CORS_ALLOW_CREDENTIALS cannot be enabled when any origin is allowed (CORS_ALLOWED_ORIGINS=*).'
            );
        }

        return new ProjectConfiguration([
            'allowed_origins' => $origins,
            'allowed_headers' => self::list($config->getOrDefault('CORS_ALLOWED_HEADERS', 'Content-Type')),
            'exposed_headers' => self::list($config->getOrDefault('CORS_EXPOSED_HEADERS', '')),
            'credentials_allowed' => $credentialsAllowed,
            'allowed_max_age' => $maxAge !== '' ? $maxAge : ConfigurationInterface::PREFLIGHT_CACHE_DISABLED
        ]);
    }

    /**
     * @return string[] The values of a comma-separated list, trimmed, without the empty ones.
     */
    private static function list(mixed $value): array
    {
        $items = array_map(trim(...), explode(',', (string)$value));

        return array_values(array_filter($items, fn (string $item) => $item !== ''));
    }
}
