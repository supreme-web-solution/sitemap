<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductTableSeeder extends Seeder
{
    /**
     * JVZoo product IDs (cproditem) => role granted on purchase. Any product not listed here is removed.
     */
    public function run(): void
    {
        $products = [
            ['product_id' => 462101, 'name' => 'FE', 'funnel' => 'FE'],
            ['product_id' => 462103, 'name' => 'Bundle', 'funnel' => 'Bundle'],
            ['product_id' => 463017, 'name' => 'Fast-Pass Bundle', 'funnel' => 'Bundle'],
            ['product_id' => 463019, 'name' => 'Fast-Pass Bundle', 'funnel' => 'Bundle'],
            ['product_id' => 463025, 'name' => 'AgencyVault', 'funnel' => 'AgencyVault'],
            ['product_id' => 463027, 'name' => 'AgencyVault', 'funnel' => 'AgencyVault'],
            ['product_id' => 463029, 'name' => 'Online Reputation App', 'funnel' => 'Online Reputation App'],
            ['product_id' => 463033, 'name' => 'Online Reputation App', 'funnel' => 'Online Reputation App'],
            ['product_id' => 463035, 'name' => 'Ai Multichannel Sales Employee', 'funnel' => 'Ai Multichannel Sales Employee'],
            ['product_id' => 463037, 'name' => 'Ai Multichannel Sales Employee', 'funnel' => 'Ai Multichannel Sales Employee'],
            ['product_id' => 463039, 'name' => 'Reseller', 'funnel' => 'Reseller'],
            ['product_id' => 463041, 'name' => 'Reseller', 'funnel' => 'Reseller'],
        ];

        Product::query()
            ->whereNotIn('product_id', array_column($products, 'product_id'))
            ->delete();

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['product_id' => $product['product_id']],
                ['name' => $product['name'], 'funnel' => $product['funnel']],
            );
        }
    }
}
