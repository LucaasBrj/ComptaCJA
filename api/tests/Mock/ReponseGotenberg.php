<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Reponse PDF fixe, pour tester le controleur sans appeler Gotenberg.
 */
final class ReponseGotenberg
{
    public function __invoke(string $methode, string $url, array $options = []): MockResponse
    {
        return new MockResponse('%PDF-1.4 test', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/pdf'],
        ]);
    }
}
