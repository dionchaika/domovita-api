<?php

namespace API\Domovita;

use RuntimeException;
use Dionchaika\Http\Uri;
use Dionchaika\Http\Client\Client;
use Dionchaika\Http\Factory\RequestFactory;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * The API class for domovita.by.
 */
class Domovita
{
    /**
     * The HTTP client.
     *
     * @var \Dionchaika\Http\Client\Client
     */
    protected $client;

    /**
     * The HTTP request factory.
     *
     * @var \Dionchaika\Http\Factory\RequestFactory
     */
    protected $factory;

    /**
     * Is the client logged in.
     *
     * @var bool
     */
    protected $loggedIn = false;

    /**
     * The CSRF-token.
     *
     * @var string
     */
    protected $csrfToken;

    /**
     * The API constructor.
     *
     * @param  bool  $debug
     * @param  string|null  $debugFile
     */
    public function __construct(bool $debug = false, ?string $debugFile = null)
    {
        $config = [

            'headers' => [

                'Accept'          => 'text/html, application/xhtml+xml, application/xml; q=0.9, image/webp, image/apng, */*; q=0.8, application/signed-exchange; v=b3',
                'Accept-Encoding' => 'gzip, deflate',
                'Accept-Language' => 'ru-RU, ru; q=0.9, en-US; q=0.8, en; q=0.7',

                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.157 Safari/537.36'

            ],

            'redirects' => true,

            'debug'      => $debug,
            'debug_file' => $debugFile

        ];

        $this->client = new Client($config);
        $this->factory = new RequestFactory;
    }

    /**
     * Log in.
     *
     * @param  string  $user
     * @param  string  $password
     * @return void
     *
     * @throws \RuntimeException
     */
    public function login(string $user, string $password): void
    {
        $uri = new Uri('https://domovita.by/');
        try {
            $response = $this->client->sendRequest($this->factory->createRequest('GET', $uri));
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException($e->getMessage());
        }

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('Error loading page: '.$uri.'!');
        }

        if (!preg_match('/\<meta name\=\"csrf\-token\" content\=\"(.+)\"\>/', $response->getBody(), $matches)) {
            throw new RuntimeException('Error getting the CSRF-token!');
        }

        $this->csrfToken = $matches[1];

        $data = [

            '_csrf'           => $this->csrfToken,
            'isPush'          => 'false',
            'AuthForm[view]'  => '_login_short',
            'AuthForm[email]' => $user

        ];

        $uri = new Uri('https://domovita.by/user/sign-in/auth');
        $request = $this->factory->createUrlencodedRequest('POST', $uri, $data)
            ->withHeader('Accept', '*/*')
            ->withHeader('X-CSRF-Token', $this->csrfToken)
            ->withHeader('X-Requested-With', 'XMLHttpRequest');

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException($e->getMessage());
        }

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('Login error!');
        }

        $data = [

            '_csrf'                      => $this->csrfToken,
            'ShortLoginForm[password]'   => $password,
            'ShortLoginForm[rememberMe]' => '0',
            'ShortLoginForm[rememberMe]' => '1'

        ];

        $uri = new Uri('https://domovita.by/user/sign-in/short-login');
        $request = $this->factory->createUrlencodedRequest('POST', $uri, $data)
            ->withHeader('Accept', '*/*')
            ->withHeader('X-CSRF-Token', $this->csrfToken)
            ->withHeader('X-Requested-With', 'XMLHttpRequest');

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException($e->getMessage());
        }

        if (302 !== $response->getStatusCode()) {
            throw new RuntimeException('Login error!');
        }

        $this->loggedIn = true;
    }
}
