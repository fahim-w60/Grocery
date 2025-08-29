<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Term;

class TermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $terms = [
            'milk', 'eggs', 'bread', 'cheese', 'butter', 'yogurt', 'cereal', 'pasta', 'rice', 'canned goods',
            'flour', 'sugar', 'salt', 'cooking oil', 'vinegar', 'baking supplies', 'peanut butter', 'jelly',
            'honey', 'syrup',
            'bananas', 'apples', 'strawberries', 'avocados', 'organic vegetables', 'salad mix', 'potatoes',
            'tomatoes', 'grapes', 'citrus fruits', 'lettuce', 'spinach', 'carrots', 'broccoli', 'bell peppers',
            'mushrooms', 'cucumbers', 'celery', 'zucchini', 'squash', 'asparagus', 'green beans', 'peas', 'corn',
            'onions', 'peaches', 'pears', 'pineapples', 'mangoes', 'lemons', 'limes', 'melons', 'blueberries',
            'raspberries',
            'chicken breast', 'ground beef', 'salmon', 'shrimp', 'turkey', 'bacon', 'sausage', 'organic meat',
            'frozen fish', 'deli meat', 'pork chops', 'roast beef', 'hot dogs', 'lamb', 'cod', 'tilapia', 'crab',
            'lobster', 'sardines', 'anchovies', 'fish fillets', 'seafood mix', 'sushi',
            'almond milk', 'oat milk', 'lactose-free milk', 'Greek yogurt', 'cottage cheese', 'sour cream',
            'plant-based cheese', 'whipped cream', 'half-and-half', 'ice cream', 'soy milk', 'cream', 'organic dairy',
            'frozen pizza', 'frozen vegetables', 'frozen meals', 'ice cream bars', 'frozen fruit', 'frozen waffles',
            'frozen burritos', 'frozen dinners', 'frozen shrimp', 'frozen desserts', 'frozen yogurt',
            'frozen breakfast items',
            'cakes', 'cupcakes', 'cookies', 'donuts', 'fresh bread', 'pie', 'muffins', 'bagels', 'croissants',
            'bakery deals', 'buns', 'rolls', 'tortillas', 'pastries', 'gluten-free bakery', 'artisan bread',
            'canned soups', 'canned beans', 'canned vegetables', 'canned fruits', 'canned tuna', 'canned chicken',
            'canned tomatoes', 'pasta sauce', 'broth', 'gravy mixes', 'instant noodles', 'lentils', 'beans',
            'trail mix', 'dried fruit',
            'chips', 'crackers', 'popcorn', 'nuts', 'pretzels', 'candy', 'chocolate', 'granola bars', 'protein bars',
            'beef jerky', 'snack mixes',
            'soda', 'bottled water', 'energy drinks', 'coffee', 'tea', 'juice', 'sparkling water', 'beer', 'wine',
            'liquor', 'hard seltzer', 'premixed cocktails', 'non-alcoholic beer', 'milk alternatives', 'sports drinks',
            'ketchup', 'mustard', 'mayonnaise', 'salad dressing', 'hot sauce', 'soy sauce', 'barbecue sauce', 'salsa',
            'dip mixes', 'relish', 'pickles', 'olives',
            'oatmeal', 'pancake mix', 'waffle mix', 'breakfast bars', 'granola', 'toaster pastries',
            'breakfast sausages',
            'Mexican food', 'Asian food', 'Italian food', 'Indian food', 'Middle Eastern food', 'kosher foods',
            'halal foods', 'ethnic spices', 'imported snacks',
            'Simple Truth organic', 'Private Selection', 'organic produce', 'organic dairy', 'organic snacks',
            'gluten-free products', 'vegan products', 'plant-based meats', 'non-GMO foods',
            'laundry detergent', 'paper towels', 'toilet paper', 'dish soap', 'trash bags', 'cleaning supplies',
            'disinfectant wipes', 'air fresheners', 'batteries', 'light bulbs', 'sponges',
            'vitamins', 'shampoo', 'toothpaste', 'makeup', 'skincare', 'diapers', 'baby formula', 'first aid',
            'hand soap', 'deodorant', 'conditioner', 'hair care', 'shaving supplies',
            'baby food', 'wipes', 'baby snacks', 'kids snacks', 'school lunch items', 'baby care products',
            'Comforts baby', 'Luvs diapers',
            'dog food', 'cat food', 'pet treats', 'pet toys', 'litter', 'pet health supplies', 'fish food',
            'small animal food',
            'Thanksgiving turkey', 'Christmas ham', 'holiday baking', 'Easter candy', 'Halloween candy',
            'Valentine\'s Day chocolates', 'pumpkin spice', 'seasonal decorations',
            'Value brand', 'Hemisfares wine', 'Psst products', 'Home Sense', 'Heritage Farm'
        ];
    
        foreach ($terms as $term) {
            Term::create([
                'name' => $term,
            ]);
        }
    }
}
