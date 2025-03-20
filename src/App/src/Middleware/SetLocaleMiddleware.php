<?php

declare(strict_types=1);

namespace App\Middleware;

use Olodoc\DocumentManagerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Diactoros\Response\RedirectResponse;

class SetLocaleMiddleware implements MiddlewareInterface
{
    protected $config;

    /**
     * Constructor
     * 
     * @param TranslatorInterface $translator laminas translator
     * @param array               $config     laminas config
     */
    public function __construct(
        array $config, 
        private TranslatorInterface $translator,
        private DocumentManagerInterface $documentManager,
    )
    {
        $this->config = $config['olodoc'];
    }

    /**
     * Process
     *
     * @param  ServerRequestInterface  $request request
     * @param  RequestHandlerInterface $handler request handler
     *
     * @return object|exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $env = getenv("APP_ENV");
        $method = $request->getMethod();
        $server = $request->getServerParams();
        $params = $request->getQueryParams();
        $path = $request->getUri()->getPath();
        $hostname = $server['SERVER_NAME'];

        $locale = substr($hostname, 0, 2);
        if (! in_array($locale, array_keys($this->config['available_locales']))) {
            $locale = locale_accept_from_http($server['HTTP_ACCEPT_LANGUAGE']);
            if (! in_array($locale, $this->config['available_locales'])) {
                $locale = $this->config['default_locale'];
            }
        }
        if (! empty($locale)) { // set default language
            $this->translator->setLocale($locale);
        }
        $locale = $this->translator->getLocale();

        define('LANG_ID', $locale);
        define('BASE_URL', ($locale == "en") ? HTTP_PREFIX.REQUEST_ORIGIN : HTTP_PREFIX.$locale.".".REQUEST_ORIGIN);

        $this->documentManager->setLocale($locale);

        return $handler->handle($request);
    }

}
