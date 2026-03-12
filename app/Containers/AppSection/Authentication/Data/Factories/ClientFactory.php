<?php

namespace App\Containers\AppSection\Authentication\Data\Factories;

use App\Containers\AppSection\Authentication\Values\Clients\MemberClient;
use App\Containers\AppSection\Authentication\Values\Clients\WebClient;
use App\Containers\AppSection\Authentication\Values\Clients\MemberMobileClient;
use Laravel\Passport\Client;

final readonly class ClientFactory
{
    public static function webClient(array $attributes = []): WebClient
    {
        $provider = array_key_exists('users', config('auth.providers')) ? 'users' : null;

        $client = Client::factory()
            ->asPasswordClient()
            ->createOne([
                'provider' => $provider,
                ...$attributes,
            ]);

        return new WebClient($client->id, $client->plainSecret);
    }

    public static function memberClient(array $attributes = []): MemberClient
    {
        $provider = array_key_exists('members', config('auth.providers')) ? 'members' : null;

        $client = Client::factory()
            ->asPasswordClient()
            ->createOne([
                'provider' => $provider,
                ...$attributes,
            ]);

        return new MemberClient($client->id, $client->plainSecret);
    }

    public static function memberMobileClient(array $attributes = []): MemberMobileClient
    {
        $provider = array_key_exists('members', config('auth.providers')) ? 'members' : null;

        $client = Client::factory()
            ->asPasswordClient()
            ->createOne([
                'provider' => $provider,
                ...$attributes,
            ]);

        return new MemberMobileClient($client->id, $client->plainSecret);
    }
}
