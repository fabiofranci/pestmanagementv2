<?php

namespace App\Services\Leads;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class LeadExtractorService
{
    public function __construct(protected HttpFactory $http)
    {
    }

    public function extractFromWebsite(string $url): array
    {
        $result = [
            'title' => null,
            'emails' => [],
            'phones' => [],
            'contact_links' => [],
        ];

        try {
            $response = $this->http
                ->withHeaders([
                    'User-Agent' => 'StudioWeb19 Lead Research Bot (+https://studioweb19.it)',
                ])
                ->timeout(10)
                ->get($url);

            if (! $response->ok()) {
                return $result;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            $result['title'] = trim($crawler->filter('title')->first()->text(''));
            $result['emails'] = $this->extractEmails($html);
            $result['phones'] = $this->extractPhones($html);
            $result['contact_links'] = $this->extractContactLinks($url, $html);
        } catch (\Throwable) {
            // swallow errors for resilience
        }

        return $result;
    }

    public function extractEmails(string $text): array
    {
        preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $text, $matches);

        return array_values(array_unique(array_map(fn ($email) => strtolower(trim($email)), $matches[0] ?? [])));
    }

    public function extractPhones(string $text): array
    {
        preg_match_all('/(?:\+?\d[\d\s().-]{6,}\d)/', $text, $matches);

        return array_values(array_unique(array_map(fn ($phone) => preg_replace('/[^0-9+]/', '', $phone), $matches[0] ?? [])));
    }

    public function extractContactLinks(string $baseUrl, string $html): array
    {
        $crawler = new Crawler($html);
        $links = $crawler->filter('a[href]')->links();
        $contactLinks = [];

        foreach ($links as $link) {
            $href = $link->getUri();
            if (Str::contains(strtolower($href), ['contact', 'contatti', 'contatto', 'info', 'email'])) {
                $contactLinks[] = $this->normalizeUrl($baseUrl, $href);
            }

            if (count($contactLinks) >= 5) {
                break;
            }
        }

        return array_values(array_unique($contactLinks));
    }

    protected function normalizeUrl(string $baseUrl, string $href): string
    {
        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
    }
}
