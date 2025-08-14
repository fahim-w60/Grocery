<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Term;
use App\Models\Product;
use Illuminate\Support\Facades\Queue;
use App\Jobs\FetchKrogerProductsJob;
use Illuminate\Support\Facades\Cache;


class KrogerController extends Controller
{
    public function fetchKrogerProducts(Request $request)
    {

        // $term = $request->input('term', 'seasonal decorations');
        // $limit = $request->input('limit', 50);
        // $start = $request->input('start', 1); // Kroger API: start must be 1-250

        // if ($start < 1 || $start > 250) {
        //     return response()->json(['error' => "The 'start' parameter must be between 1 and 250."], 422);
        // }
        // if ($limit < 1 || $limit > 50) {
        //     return response()->json(['error' => "The 'limit' parameter must be between 1 and 50."], 422);
        // }

        
        // $tokenResponse = Http::asForm()->withBasicAuth(
        //     env('KROGER_CLIENT_ID'),
        //     env('KROGER_CLIENT_SECRET')
        // )->post('https://api.kroger.com/v1/connect/oauth2/token', [
        //     'grant_type' => 'client_credentials',
        //     'scope' => env('KROGER_SCOPES'),
        // ]);


        // if (!$tokenResponse->ok()) {
        //     return response()->json(['error' => 'Unable to authenticate with Kroger'], 500);
        // }

        // //return $tokenResponse->json();

        // $accessToken = $tokenResponse['access_token'];


        // $terms = Term::select('id', 'name')->orderBy('name', 'asc')->get();
        // //return $terms;
        // foreach ($terms as $term) {
        //     $termName = $term->name;
        //     $limit = 50;
        //     $start = 1;

        //     $productResponse = Http::withToken($accessToken)
        //     ->get('https://api.kroger.com/v1/products', [
        //         'filter.limit' => $limit,
        //         'filter.term' => $termName,
        //         'filter.start' => $start,
        //     ]);

        //     $data = $productResponse->json();

        //     //return $data;

        //     $totalProducts = 0;
        //     if ($data !== null && isset($data['meta']['pagination']['total']))
        //     {
        //         $totalProducts = $data['meta']['pagination']['total'];
        //     }
        //     $limit = 50;
        //     $maxStart = 251; // filter.start can be at most 250, so last batch is 251
        //     $totalFetched = 0;
        //     $start = 1;

            
        //         for ($start = 1; $start <= $maxStart && $totalFetched < $totalProducts; $start += $limit) {
        //             $response = Http::withToken($accessToken)
        //                 ->get('https://api.kroger.com/v1/products', [
        //                     'filter.limit' => $limit,
        //                     'filter.term' => $termName,
        //                     'filter.start' => $start,
        //                     'filter.fulfillment' => 'ais, dth',
        //                 ]);

        //             $datas = $response->json();
        //             if (isset($datas['data']) && is_array($datas['data'])) {
        //                 foreach ($datas['data'] as $product) {
        //                     Product::create([
        //                         'name' => $product['description'] ?? '',
        //                     ]);
        //                 }
        //                 $totalFetched += count($datas['data']);
        //                 if (count($datas['data']) < $limit) {
        //                     break;
        //                 }
        //             }
        //         }    
        // }
        // return response()->json([
        //     'status' => true,
        //     'message' => 'Products fetched successfully',
        //     'total_products' => $totalProducts,
        //     'total_fetched' => $totalFetched,
        // ]);
       
    

        // if (!$productResponse->ok()) {
        //     return response()->json(['error' => 'Unable to fetch products from Kroger'], 500);
        // }

        // return response()->json($productResponse->json());

        \App\Jobs\FetchKrogerProductsJob::dispatch();
        // return response()->json([
        //     'status' => true,
        //     'message' => 'Kroger product fetching has been queued and will run in the background.'
        // ]);
        
    }

}
