<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\Term;
use App\Models\Product;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchKrogerProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 3; // Retry 3 times
    public $maxExceptions = 3;

    private $locationId;
    private $termId;
    private $start;
    private $limit;
    private $isMainJob;

    public function __construct($locationId = null, $termId = null, $start = 1, $limit = 50, $isMainJob = false)
    {
        $this->locationId = $locationId;
        $this->termId = $termId;
        $this->start = $start;
        $this->limit = $limit;
        $this->isMainJob = $isMainJob;
    }

    public function handle()
    {
        try {
            if ($this->isMainJob) {
                $this->processMainJob();
            } else {
                $this->processSpecificRequest();
            }
        } catch (\Exception $e) {
            Log::error('FetchKrogerProductsJob failed', [
                'error' => $e->getMessage(),
                'locationId' => $this->locationId,
                'termId' => $this->termId,
                'start' => $this->start
            ]);
            throw $e;
        }
    }

    private function processMainJob()
    {
        Log::info('Starting main Kroger job - breaking into smaller jobs');
        
        // Clear existing products only if this is the main job
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Product::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $locations = Location::select('id', 'locationId', 'storeName')->get();
        $terms = Term::select('id', 'name')->get();
        
        $totalJobs = 0;
        
        foreach ($locations as $location) {
            foreach ($terms as $term) {
                // Dispatch individual job for each location/term combination
                FetchKrogerProductsJob::dispatch(
                    $location->locationId,
                    $term->id,
                    1,
                    50,
                    false
                )->delay(now()->addSeconds(rand(1, 10))); // Random delay to avoid rate limiting
                
                $totalJobs++;
            }
        }
        
        Log::info("Main job completed - dispatched {$totalJobs} sub-jobs");
    }

    private function processSpecificRequest()
    {
        $location = Location::where('locationId', $this->locationId)->first();
        $term = Term::find($this->termId);

        if (!$location || !$term) {
            Log::error('Location or Term not found', [
                'locationId' => $this->locationId,
                'termId' => $this->termId
            ]);
            return;
        }

        // Get access token
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new \Exception('Failed to get access token');
        }

        // Fetch products with pagination
        $this->fetchProductsWithPagination($accessToken, $location, $term);
    }

    private function getAccessToken()
    {
        $tokenResponse = Http::asForm()
            ->withBasicAuth(
                env('KROGER_CLIENT_ID'),
                env('KROGER_CLIENT_SECRET')
            )
            ->timeout(30)
            ->retry(3, 1000)
            ->post('https://api.kroger.com/v1/connect/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => env('KROGER_SCOPES'),
            ]);

        if (!$tokenResponse->ok()) {
            Log::error('Unable to authenticate with Kroger', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body()
            ]);
            return null;
        }

        return $tokenResponse['access_token'];
    }

    private function fetchProductsWithPagination($accessToken, $location, $term)
    {
        $maxStart = 250; // API limit
        $totalFetched = 0;
        $batchSize = 100; // Process in batches of 100

        for ($start = $this->start; $start <= $maxStart; $start += $this->limit) {
            if ($start > $this->start) {
                sleep(1); // 1 second delay between requests
            }

            $maxRetries = 5;
            $retryDelay = 2; // seconds
            $attempt = 0;
            $response = null;
            while ($attempt < $maxRetries) {
                try {
                    $response = Http::withToken($accessToken)
                        ->timeout(30)
                        ->get('https://api.kroger.com/v1/products', [
                            'filter.limit' => $this->limit,
                            'filter.term' => $term->name,
                            'filter.start' => $start,
                            'filter.fulfillment' => 'dth,ais',
                            'filter.locationId' => $location->locationId,
                        ]);

                    if ($response->status() == 503) {
                        Log::warning('Kroger API 503 Service Unavailable, retrying...', [
                            'locationId' => $location->locationId,
                            'term' => $term->name,
                            'start' => $start,
                            'attempt' => $attempt + 1,
                            'delay' => $retryDelay
                        ]);
                        sleep($retryDelay);
                        $retryDelay *= 2; // Exponential backoff
                        $attempt++;
                        continue;
                    }

                    // If not 503, break out of retry loop
                    break;
                } catch (\Exception $e) {
                    Log::error('Kroger API request exception, retrying...', [
                        'locationId' => $location->locationId,
                        'term' => $term->name,
                        'start' => $start,
                        'attempt' => $attempt + 1,
                        'delay' => $retryDelay,
                        'error' => $e->getMessage()
                    ]);
                    sleep($retryDelay);
                    $retryDelay *= 2;
                    $attempt++;
                }
            }

            if (!$response || $response->failed()) {
                Log::error('Kroger API request failed after retries', [
                    'locationId' => $location->locationId,
                    'term' => $term->name,
                    'start' => $start,
                    'status' => $response ? $response->status() : 'no response',
                ]);
                continue;
            }

            if ($response->status() == 404) {
                Log::info('No more products found', [
                    'locationId' => $location->locationId,
                    'term' => $term->name,
                    'start' => $start
                ]);
                break;
            }

            $data = $response->json();
            if (empty($data['data'])) {
                break;
            }

            // Process products in batches
            $products = collect($data['data']);
            $products->chunk($batchSize)->each(function ($chunk) use ($location, $term) {
                $this->processProductChunk($chunk, $location, $term);
            });

            $totalFetched += count($data['data']);
            if ($totalFetched % 1000 == 0) {
                Log::info('Progress update', [
                    'locationId' => $location->locationId,
                    'term' => $term->name,
                    'totalFetched' => $totalFetched
                ]);
            }

            if (count($data['data']) < $this->limit) {
                break;
            }
        }

        Log::info('Completed fetching products', [
            'locationId' => $location->locationId,
            'term' => $term->name,
            'totalFetched' => $totalFetched
        ]);
    }

    private function processProductChunk($products, $location, $term)
    {
        $productData = [];
        
        foreach ($products as $product) {
            $imageUrl = $this->extractImages($product);
            $item = $product['items'][0] ?? null;

            $productData[] = [
                'productId' => $product['productId'] ?? null,
                'locationId' => $location->locationId ?? null,
                'name' => $product['description'] ?? null,
                'images' => $imageUrl,  // Store the URL directly
                'brand' => $product['brand'] ?? null,
                'categories' => !empty($product['categories']) ? json_encode($product['categories']) : null,
                'term' => $term->name ?? null,
                'storeName' => $location->storeName ?? null,
                'regular_price' => $item['price']['regular'] ?? null,
                'promo_price' => $item['price']['promo'] ?? null,
                'size' => $item['size'] ?? null,
                'soldBy' => $item['soldBy'] ?? null,
                'stockLevel' => $item['inventory']['stockLevel'] ?? null,
                'countryOrigin' => $product['countryOrigin'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($productData)) {
            Product::insert($productData);
        }
    }

    private function extractImages($product)
    {
        if (empty($product['images']) || !is_array($product['images'])) {
            return null;
        }
    
        // First, try to find the 'front' perspective
        foreach ($product['images'] as $image) {
            if (($image['perspective'] ?? '') === 'front') {
                if (!empty($image['sizes']) && is_array($image['sizes'])) {
                    foreach ($image['sizes'] as $size) {
                        if (($size['size'] ?? null) === 'xlarge' && isset($size['url'])) {
                            return $size['url'];
                        }
                    }
                }
            }
        }
    
        // If no 'front' perspective found, fall back to the first xlarge image
        foreach ($product['images'] as $image) {
            if (!empty($image['sizes']) && is_array($image['sizes'])) {
                foreach ($image['sizes'] as $size) {
                    if (($size['size'] ?? null) === 'xlarge' && isset($size['url'])) {
                        return $size['url'];
                    }
                }
            }
        }
    
        return null;
    }
}