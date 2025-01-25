<?php
namespace App\Service;

use App\Entity\News;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\NewsRepository;
use Psr\Log\LoggerInterface;

class NewsParser
{
    private $httpClient;
    private $entityManager;
    private $newsRepository;
    private $logger;

    public function __construct(HttpClientInterface $httpClient, 
    EntityManagerInterface $entityManager,
    NewsRepository $newsRepository, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->newsRepository = $newsRepository;
        $this->logger = $logger;
    }

    public function parse(string $url = 'https://highload.today/category/novosti/')
    {
        try {
            $this->logger->info('Starting news parsing from ' . $url);
            
            $htmlContent = $this->fetchHtmlContent($url);
            
            if (empty($htmlContent)) {
                $this->logger->error('Failed to fetch HTML content from ' . $url);
                return false;
            }

            $newsItems = $this->extractNewsItems($htmlContent);
            
            if (empty($newsItems)) {
                $this->logger->warning('No news items found from ' . $url);
                return false;
            }

            $successCount = 0;
            foreach ($newsItems as $item) {
                try {
                    if (!empty($item['title'])) {
                        $this->processNewsItem($item);
                        $successCount++;
                    }
                } catch (\Exception $itemException) {
                    $this->logger->error('Failed to process news item: ' . $itemException->getMessage());
                }
            }

            $this->entityManager->flush();
            
            $this->logger->info("Parsed $successCount news items successfully from $url");
            return $successCount > 0;

        } catch (\Exception $e) {
            $this->logger->error('News parsing failed: ' . $e->getMessage());
            $this->logger->error('Exception trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    private function fetchHtmlContent($url)
    {
        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();
        
        // Convert to UTF-8
        $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
        
        return $content;
    }

    private function extractNewsItems($htmlContent)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContent);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $newsItems = [];

        $articles = $xpath->query('//div[contains(@class, "lenta-item")]');
        foreach ($articles as $article) {
            $newsItems[] = $this->extractNewsItem($article, $xpath);
        }

        return $newsItems;
    }

    private function extractNewsItem($article, $xpath)
    {
        $titleNode = $xpath->query('.//a/h2', $article)->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : '';

        // More precise description extraction
        $descNode = $xpath->query('.//p[not(ancestor::div[@class="author-block"])]', $article)->item(0);
        $shortDescription = $descNode ? trim($descNode->textContent) : '';

        $imgNode = $xpath->query('.//div[@class="lenta-image"]//img', $article)->item(0);
        $picture = '';
        if ($imgNode) {
            // Prioritize data-lazy-src, then src, and handle SVG placeholders
            $picture = $imgNode->getAttribute('data-lazy-src') 
                    ?: $imgNode->getAttribute('src');
            
            // Optional: Strip out SVG placeholder if found
            if (strpos($picture, 'data:image/svg+xml') !== false) {
                $picture = $imgNode->getAttribute('data-lazy-src') 
                        ?: $imgNode->getAttribute('srcset') 
                        ?: '';
            }
        }

        $timeNode = $xpath->query('.//span[contains(@class, "meta-datetime")]', $article)->item(0);
        $timeCreated = $timeNode ? trim($timeNode->textContent) : '';

        return [
            'title' => $title,
            'shortDescription' => $shortDescription,
            'picture' => $picture,
            'createdAt' => $timeCreated,
            'updatedAt' => new \DateTime()
        ];
    }

    private function translateRelativeTime($input) {
        $translations = [
            'назад'     => '', // Remove 'ago'
            'секунду'   => 'second',
            'секунды'   => 'seconds',
            'минуту'    => 'minute',
            'минуты'    => 'minutes',
            'час'       => 'hour',
            'часа'      => 'hours',
            'день'      => 'day',
            'дня'       => 'days',
            'неделя'    => 'week',
            'недели'    => 'weeks',
            'месяц'     => 'month',
            'месяца'    => 'months',
            'год'       => 'year',
            'года'      => 'years',
        ];

        $input = mb_convert_encoding($input, 'UTF-8', 'auto');
        return strtr($input, $translations);
    }

    private function parseCreatedAt($createdAt) {
        if (empty($createdAt)) {
            return new \DateTime(); // Default to the current date
        }

        $createdAt = $this->translateRelativeTime($createdAt);

        // Remove any text after "months" or "days" or "weeks"
        foreach (['months', 'days', 'weeks'] as $unit) {
            if (strpos($createdAt, $unit) !== false) {
                $createdAt = substr($createdAt, 0, strpos($createdAt, $unit) + strlen($unit));
                break;
            }
        }

        if (preg_match('/(\d+)\s*(\w+)/', $createdAt, $matches)) {
            $number = (int)$matches[1];
            $unit = $matches[2];

            $intervalSpec = 'P';
            switch ($unit) {
                case 'second':
                case 'seconds':
                    $intervalSpec = 'PT' . $number . 'S';
                    break;
                case 'minute':
                case 'minutes':
                    $intervalSpec = 'PT' . $number . 'M';
                    break;
                case 'hour':
                case 'hours':
                    $intervalSpec = 'PT' . $number . 'H';
                    break;
                case 'day':
                case 'days':
                    $intervalSpec .= $number . 'D';
                    break;
                case 'week':
                case 'weeks':
                    $intervalSpec .= $number . 'W';
                    break;
                case 'month':
                case 'months':
                    $intervalSpec .= $number . 'M';
                    break;
                case 'year':
                case 'years':
                    $intervalSpec .= $number . 'Y';
                    break;
                default:
                    error_log("Failed to parse date unit: $unit");
                    return new \DateTime(); // Default to the current date on failure
            }

            try {
                $interval = new \DateInterval($intervalSpec);
                $date = new \DateTime();
                $date->sub($interval);
                return $date;
            } catch (\Exception $e) {
                error_log("Failed to parse date: " . $e->getMessage());
                return new \DateTime(); // Default to the current date on failure
            }
        } else {
            error_log("Failed to parse date: $createdAt");
            return new \DateTime(); // Default to the current date on failure
        }
    }

    private function processNewsItem($item)
    {
        $existingNews = $this->newsRepository->findOneByTitle($item['title']);
        if ($existingNews) {
            $existingNews->setUpdatedAt(new \DateTime());
        } else {
            $news = new News();
            $news->setTitle($item['title']);
            $news->setShortDescription($item['shortDescription']);
            $news->setPicture($item['picture']);
            $parsedDate = $this->parseCreatedAt($item['createdAt']);
            if ($parsedDate) {
                $news->setCreatedAt($parsedDate);
            }
            $news->setUpdatedAt(new \DateTime());
            $this->entityManager->persist($news);
            $this->logger->info("New news item persisted: {$item['title']}");
        }
    }
}