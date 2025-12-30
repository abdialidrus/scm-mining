<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // SPARE PARTS - Engine Parts (SPR-ENG)
            [
                'sku' => 'SPR-ENG-001',
                'name' => 'Cylinder Head Assembly CAT 3512',
                'category_code' => 'SPR-ENG',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 5,
            ],
            [
                'sku' => 'SPR-ENG-002',
                'name' => 'Piston Ring Set CAT 3412',
                'category_code' => 'SPR-ENG',
                'uom_code' => 'SET',
                'is_serialized' => false,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'SPR-ENG-003',
                'name' => 'Turbocharger Cummins QSK60',
                'category_code' => 'SPR-ENG',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 5,
            ],
            [
                'sku' => 'SPR-ENG-004',
                'name' => 'Connecting Rod Bearing',
                'category_code' => 'SPR-ENG',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'SPR-ENG-005',
                'name' => 'Crankshaft Main Bearing',
                'category_code' => 'SPR-ENG',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 5,
            ],

            // SPARE PARTS - Hydraulic Parts (SPR-HYD)
            [
                'sku' => 'SPR-HYD-001',
                'name' => 'Hydraulic Pump A10VO140',
                'category_code' => 'SPR-HYD',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 5,
            ],
            [
                'sku' => 'SPR-HYD-002',
                'name' => 'Hydraulic Cylinder 100x200mm',
                'category_code' => 'SPR-HYD',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'SPR-HYD-003',
                'name' => 'Hydraulic Hose 1/2" 2-Wire',
                'category_code' => 'SPR-HYD',
                'uom_code' => 'MTR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'SPR-HYD-004',
                'name' => 'Hydraulic Control Valve 4-Spool',
                'category_code' => 'SPR-HYD',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 5,
            ],
            [
                'sku' => 'SPR-HYD-005',
                'name' => 'Seal Kit for Cylinder 100mm',
                'category_code' => 'SPR-HYD',
                'uom_code' => 'SET',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],

            // SPARE PARTS - Electrical Parts (SPR-ELE)
            [
                'sku' => 'SPR-ELE-001',
                'name' => 'Alternator 24V 100A',
                'category_code' => 'SPR-ELE',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'SPR-ELE-002',
                'name' => 'Starter Motor 24V',
                'category_code' => 'SPR-ELE',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'SPR-ELE-003',
                'name' => 'Battery 12V 200Ah',
                'category_code' => 'SPR-ELE',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'SPR-ELE-004',
                'name' => 'Wiring Harness Main',
                'category_code' => 'SPR-ELE',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'SPR-ELE-005',
                'name' => 'ECU Engine Controller',
                'category_code' => 'SPR-ELE',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 5,
            ],

            // CONSUMABLES - Oils & Lubricants (CONS-OIL)
            [
                'sku' => 'CONS-OIL-001',
                'name' => 'Engine Oil SAE 15W-40',
                'category_code' => 'CONS-OIL',
                'uom_code' => 'LTR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'CONS-OIL-002',
                'name' => 'Hydraulic Oil ISO 68',
                'category_code' => 'CONS-OIL',
                'uom_code' => 'LTR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'CONS-OIL-003',
                'name' => 'Gear Oil SAE 85W-140',
                'category_code' => 'CONS-OIL',
                'uom_code' => 'LTR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'CONS-OIL-004',
                'name' => 'Grease Lithium Complex NLGI 2',
                'category_code' => 'CONS-OIL',
                'uom_code' => 'KG',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],
            [
                'sku' => 'CONS-OIL-005',
                'name' => 'Coolant Antifreeze 50/50',
                'category_code' => 'CONS-OIL',
                'uom_code' => 'LTR',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],

            // CONSUMABLES - Filters (CONS-FLT)
            [
                'sku' => 'CONS-FLT-001',
                'name' => 'Fuel Filter (Heavy Duty)',
                'category_code' => 'CONS-FLT',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'CONS-FLT-002',
                'name' => 'Oil Filter Spin-On',
                'category_code' => 'CONS-FLT',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'CONS-FLT-003',
                'name' => 'Air Filter Primary',
                'category_code' => 'CONS-FLT',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'CONS-FLT-004',
                'name' => 'Hydraulic Filter Return',
                'category_code' => 'CONS-FLT',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'CONS-FLT-005',
                'name' => 'Cabin Air Filter',
                'category_code' => 'CONS-FLT',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 1,
            ],

            // PPE - Head Protection (PPE-HEAD)
            [
                'sku' => 'PPE-HEAD-001',
                'name' => 'Safety Helmet with Visor',
                'category_code' => 'PPE-HEAD',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'PPE-HEAD-002',
                'name' => 'Hard Hat Suspension',
                'category_code' => 'PPE-HEAD',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],
            [
                'sku' => 'PPE-HEAD-003',
                'name' => 'Welding Helmet Auto-Darkening',
                'category_code' => 'PPE-HEAD',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],

            // PPE - Hand Protection (PPE-HAND)
            [
                'sku' => 'PPE-HAND-001',
                'name' => 'Safety Gloves Leather',
                'category_code' => 'PPE-HAND',
                'uom_code' => 'PAIR',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],
            [
                'sku' => 'PPE-HAND-002',
                'name' => 'Cut Resistant Gloves Level 5',
                'category_code' => 'PPE-HAND',
                'uom_code' => 'PAIR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'PPE-HAND-003',
                'name' => 'Chemical Resistant Gloves',
                'category_code' => 'PPE-HAND',
                'uom_code' => 'PAIR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],

            // PPE - Foot Protection (PPE-FOOT)
            [
                'sku' => 'PPE-FOOT-001',
                'name' => 'Safety Boots Steel Toe',
                'category_code' => 'PPE-FOOT',
                'uom_code' => 'PAIR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'PPE-FOOT-002',
                'name' => 'Mining Boots Waterproof',
                'category_code' => 'PPE-FOOT',
                'uom_code' => 'PAIR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],

            // TOOLS & EQUIPMENT (TOOL)
            [
                'sku' => 'TOOL-001',
                'name' => 'Torque Wrench 1/2" Drive 50-250Nm',
                'category_code' => 'TOOL',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'TOOL-002',
                'name' => 'Impact Wrench Pneumatic 3/4"',
                'category_code' => 'TOOL',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'TOOL-003',
                'name' => 'Angle Grinder 7"',
                'category_code' => 'TOOL',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 2,
            ],
            [
                'sku' => 'TOOL-004',
                'name' => 'Socket Set 1/2" Drive 46pcs',
                'category_code' => 'TOOL',
                'uom_code' => 'SET',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],
            [
                'sku' => 'TOOL-005',
                'name' => 'Hydraulic Jack 20 Ton',
                'category_code' => 'TOOL',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'TOOL-006',
                'name' => 'Welding Machine MIG 250A',
                'category_code' => 'TOOL',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'TOOL-007',
                'name' => 'Chain Block 2 Ton',
                'category_code' => 'TOOL',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 3,
            ],

            // CHEMICALS (CHEM)
            [
                'sku' => 'CHEM-001',
                'name' => 'Degreaser Industrial',
                'category_code' => 'CHEM',
                'uom_code' => 'LTR',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],
            [
                'sku' => 'CHEM-002',
                'name' => 'Rust Remover Spray',
                'category_code' => 'CHEM',
                'uom_code' => 'CAN',
                'is_serialized' => false,
                'criticality_level' => 1,
            ],
            [
                'sku' => 'CHEM-003',
                'name' => 'Paint Spray Enamel',
                'category_code' => 'CHEM',
                'uom_code' => 'CAN',
                'is_serialized' => false,
                'criticality_level' => 1,
            ],
            [
                'sku' => 'CHEM-004',
                'name' => 'Thread Locker High Strength',
                'category_code' => 'CHEM',
                'uom_code' => 'BTL',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],

            // EXPLOSIVES (EXPL)
            [
                'sku' => 'EXPL-001',
                'name' => 'ANFO Explosive (Bulk)',
                'category_code' => 'EXPL',
                'uom_code' => 'KG',
                'is_serialized' => false,
                'criticality_level' => 5,
            ],
            [
                'sku' => 'EXPL-002',
                'name' => 'Detonator Electric Delay',
                'category_code' => 'EXPL',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 5,
            ],
            [
                'sku' => 'EXPL-003',
                'name' => 'Blasting Cap Non-Electric',
                'category_code' => 'EXPL',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 5,
            ],

            // FUEL & LUBRICANTS (FUEL)
            [
                'sku' => 'FUEL-001',
                'name' => 'Diesel Fuel High Speed',
                'category_code' => 'FUEL',
                'uom_code' => 'LTR',
                'is_serialized' => false,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'FUEL-002',
                'name' => 'AdBlue DEF (Diesel Exhaust Fluid)',
                'category_code' => 'FUEL',
                'uom_code' => 'LTR',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],

            // SAFETY EQUIPMENT (SAFE)
            [
                'sku' => 'SAFE-001',
                'name' => 'Fire Extinguisher ABC 9kg',
                'category_code' => 'SAFE',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'SAFE-002',
                'name' => 'First Aid Kit Complete',
                'category_code' => 'SAFE',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'SAFE-003',
                'name' => 'Safety Harness Full Body',
                'category_code' => 'SAFE',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'SAFE-004',
                'name' => 'Gas Detector 4-in-1',
                'category_code' => 'SAFE',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 5,
            ],
            [
                'sku' => 'SAFE-005',
                'name' => 'Emergency Eyewash Station',
                'category_code' => 'SAFE',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 3,
            ],

            // OFFICE SUPPLIES (OFF)
            [
                'sku' => 'OFF-001',
                'name' => 'Paper A4 80gsm',
                'category_code' => 'OFF',
                'uom_code' => 'RIM',
                'is_serialized' => false,
                'criticality_level' => 1,
            ],
            [
                'sku' => 'OFF-002',
                'name' => 'Ballpoint Pen Blue',
                'category_code' => 'OFF',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 1,
            ],
            [
                'sku' => 'OFF-003',
                'name' => 'Toner Cartridge HP LaserJet',
                'category_code' => 'OFF',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],

            // IT EQUIPMENT (IT)
            [
                'sku' => 'IT-001',
                'name' => 'Laptop Dell Latitude 5520',
                'category_code' => 'IT',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'IT-002',
                'name' => 'Monitor LED 24 inch',
                'category_code' => 'IT',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 2,
            ],
            [
                'sku' => 'IT-003',
                'name' => 'Network Switch 24-Port Gigabit',
                'category_code' => 'IT',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'IT-004',
                'name' => 'UPS 1000VA',
                'category_code' => 'IT',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 3,
            ],

            // CONSTRUCTION MATERIALS (CONST)
            [
                'sku' => 'CONST-001',
                'name' => 'Cement Portland 50kg',
                'category_code' => 'CONST',
                'uom_code' => 'BAG',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],
            [
                'sku' => 'CONST-002',
                'name' => 'Steel Rebar 12mm',
                'category_code' => 'CONST',
                'uom_code' => 'BTG',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],
            [
                'sku' => 'CONST-003',
                'name' => 'Plywood 18mm 4x8 ft',
                'category_code' => 'CONST',
                'uom_code' => 'SHT',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],

            // ELECTRICAL COMPONENTS (ELEC)
            [
                'sku' => 'ELEC-001',
                'name' => 'Cable NYFGBY 3x240mm2',
                'category_code' => 'ELEC',
                'uom_code' => 'MTR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'ELEC-002',
                'name' => 'Circuit Breaker 3P 250A',
                'category_code' => 'ELEC',
                'uom_code' => 'PCS',
                'is_serialized' => true,
                'criticality_level' => 4,
            ],
            [
                'sku' => 'ELEC-003',
                'name' => 'Contactor AC 100A',
                'category_code' => 'ELEC',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'ELEC-004',
                'name' => 'LED Floodlight 200W',
                'category_code' => 'ELEC',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],
        ];

        $uomIdsByCode = DB::table('uoms')->pluck('id', 'code');
        $categoryIdsByCode = DB::table('item_categories')->pluck('id', 'code');

        foreach ($items as $item) {
            $baseUomId = $uomIdsByCode[$item['uom_code']] ?? null;
            $categoryId = $categoryIdsByCode[$item['category_code']] ?? null;

            DB::table('items')->updateOrInsert(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'is_serialized' => $item['is_serialized'],
                    'criticality_level' => $item['criticality_level'],
                    'base_uom_id' => $baseUomId,
                    'item_category_id' => $categoryId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $this->command->info('✅ ' . count($items) . ' items seeded successfully');
    }
}
