<?php

namespace App\Containers\AppSection\Authentication\Data\Factories;

use App\Containers\AppSection\Authentication\Data\DTOs\PasswordToken;
use App\Containers\AppSection\Authentication\Values\RequestProxies\PasswordGrant\AccessTokenProxy;
use App\Containers\AppSection\Authentication\Values\RequestProxies\PasswordGrant\RefreshTokenProxy;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Contracts\Container\Container;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Exceptions\OAuthServerException as PassportOAuthServerException;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueOAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

final class PasswordTokenFactory
{
    private User|null $user = null;

    public function __construct(
        private readonly AuthorizationServer $server,
        private readonly TokenAttributeFormatter $tokenFormatter,
        private readonly Container $container,
    ) {
    }

    public function make(AccessTokenProxy|RefreshTokenProxy $proxy): PasswordToken
    {
        $response = $this->processTokenRequest($proxy);
        $this->updateUserTokenIfNeeded($response['access_token']);

        return PasswordToken::fromArray($response);
    }

    private function processTokenRequest(AccessTokenProxy|RefreshTokenProxy $proxy): array
    {
        return $this->dispatchRequestToAuthorizationServer(
            $this->createRequest($proxy),
        );
    }

    protected function dispatchRequestToAuthorizationServer(ServerRequestInterface $request): array
    {
        try {
            return json_decode(
                (string) $this->server->respondToAccessTokenRequest(
                    $request,
                    $this->container->make(ResponseInterface::class),
                )->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (LeagueOAuthServerException $e) {
            throw new PassportOAuthServerException($e);
        }
    }

    protected function createRequest(AccessTokenProxy|RefreshTokenProxy $proxy): ServerRequestInterface
    {
        return (new PsrHttpFactory())->createRequest(
            Request::create(
                '',
                'POST',
                $proxy->toArray(),
            ),
        );
    }

    private function updateUserTokenIfNeeded(string $accessToken): void
    {
        if (!is_null($this->user)) {
            $this->setUserCurrentToken(
                new AccessToken(
                    $this->tokenFormatter->format($accessToken),
                ),
            );
        }
    }

    private function setUserCurrentToken(AccessToken $token): void
    {
        $this->user->refresh()->withAccessToken($token);
    }

    /**
     * Set the access token as the user's current token.
     */
    public function for(User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
