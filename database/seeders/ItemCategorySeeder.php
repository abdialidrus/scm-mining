<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Root categories
            [
                'code' => 'SPR',
                'name' => 'Spare Parts',
                'description' => 'Spare parts for mining equipment and machinery',
                'color_code' => '#3B82F6',
                'sort_order' => 10,
                'children' => [
                    ['code' => 'SPR-ENG', 'name' => 'Engine Parts', 'sort_order' => 1],
                    ['code' => 'SPR-HYD', 'name' => 'Hydraulic Parts', 'sort_order' => 2],
                    ['code' => 'SPR-ELE', 'name' => 'Electrical Parts', 'sort_order' => 3],
                ],
            ],
            [
                'code' => 'CONS',
                'name' => 'Consumables',
                'description' => 'Consumable materials and supplies',
                'color_code' => '#10B981',
                'sort_order' => 20,
                'children' => [
                    ['code' => 'CONS-OIL', 'name' => 'Oils & Lubricants', 'sort_order' => 1],
                    ['code' => 'CONS-FLT', 'name' => 'Filters', 'sort_order' => 2],
                ],
            ],
            [
                'code' => 'PPE',
                'name' => 'Personal Protective Equipment',
                'description' => 'Safety equipment for personnel',
                'color_code' => '#F59E0B',
                'sort_order' => 30,
                'children' => [
                    ['code' => 'PPE-HEAD', 'name' => 'Head Protection', 'sort_order' => 1],
                    ['code' => 'PPE-HAND', 'name' => 'Hand Protection', 'sort_order' => 2],
                    ['code' => 'PPE-FOOT', 'name' => 'Foot Protection', 'sort_order' => 3],
                ],
            ],
            [
                'code' => 'TOOL',
                'name' => 'Tools & Equipment',
                'description' => 'Hand tools and equipment',
                'color_code' => '#8B5CF6',
                'sort_order' => 40,
            ],
            [
                'code' => 'CHEM',
                'name' => 'Chemicals',
                'description' => 'Chemical materials',
                'color_code' => '#EF4444',
                'sort_order' => 50,
                'requires_approval' => true,
            ],
            [
                'code' => 'EXPL',
                'name' => 'Explosives',
                'description' => 'Explosive materials (high security)',
                'color_code' => '#DC2626',
                'sort_order' => 60,
                'requires_approval' => true,
            ],
            [
                'code' => 'FUEL',
                'name' => 'Fuel & Lubricants',
                'description' => 'Fuel and lubrication materials',
                'color_code' => '#F97316',
                'sort_order' => 70,
            ],
            [
                'code' => 'SAFE',
                'name' => 'Safety Equipment',
                'description' => 'Safety and emergency equipment',
                'color_code' => '#14B8A6',
                'sort_order' => 80,
            ],
            [
                'code' => 'OFF',
                'name' => 'Office Supplies',
                'description' => 'Office stationery and supplies',
                'color_code' => '#6B7280',
                'sort_order' => 90,
            ],
            [
                'code' => 'IT',
                'name' => 'IT Equipment',
                'description' => 'Information technology equipment',
                'color_code' => '#06B6D4',
                'sort_order' => 100,
            ],
            [
                'code' => 'CONST',
                'name' => 'Construction Materials',
                'description' => 'Building and construction materials',
                'color_code' => '#78716C',
                'sort_order' => 110,
            ],
            [
                'code' => 'ELEC',
                'name' => 'Electrical Components',
                'description' => 'Electrical components and materials',
                'color_code' => '#FBBF24',
                'sort_order' => 120,
            ],
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $category = ItemCategory::updateOrCreate(
                ['code' => $categoryData['code']],
                array_merge($categoryData, ['is_active' => true])
            );

            // Create children if exists
            foreach ($children as $childData) {
                ItemCategory::updateOrCreate(
                    ['code' => $childData['code']],
                    array_merge($childData, [
                        'parent_id' => $category->id,
                        'is_active' => true,
                    ])
                );
            }
        }

        $this->command->info('✅ Item categories seeded successfully');
    }
}
