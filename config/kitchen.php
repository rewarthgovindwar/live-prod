<?php

return [

    'meal_services' => [
        'breakfast' => 'Breakfast',
        'lunch' => 'Lunch',
        'supper' => 'Supper',
        'dinner' => 'Dinner',
    ],

    'served_to' => [
        'staff' => 'Staff',
        'students' => 'Students',
        'guests' => 'Guests',
        'mixed' => 'Mixed',
    ],

    'line_types' => [
        'ingredient' => 'Ingredient',
        'beverage' => 'Beverage',
        'custom' => 'Custom',
    ],

    'behavior_profiles' => [
        'perishable' => 'Perishable (vegetables, milk, bread)',
        'staple_dry' => 'Staple dry (rice, dal, flour)',
        'spice_condiment' => 'Spices & condiments',
        'gas_fuel' => 'Gas & fuel',
        'cleaning' => 'Cleaning supplies',
        'equipment' => 'Equipment / assets',
        'consumable' => 'General consumable',
    ],

    'waste_reasons' => [
        'spoilage' => 'Spoilage',
        'expired' => 'Expired',
        'cooking_waste' => 'Cooking waste',
        'serving_waste' => 'Serving waste',
        'damaged' => 'Damaged',
        'lost' => 'Lost / missing',
        'rejected' => 'Quality rejected',
        'other' => 'Other',
    ],

    'low_stock_threshold' => 10,

    'settings' => [
        'kitchen_low_stock_threshold' => 10,
        'kitchen_auto_procurement_urgency' => 'high',
        'kitchen_fast_purchase_max_amount' => 5000,
    ],

    'sample_recipes' => [
        [
            'name' => 'Plain Rice (भात)',
            'meal_service' => 'lunch',
            'default_servings' => 100,
            'description' => 'Standard steamed rice for hostel lunch.',
            'lines' => [
                ['item' => 'तांदूळ', 'quantity' => 12, 'uom' => 'kg'],
                ['item' => 'Rice', 'quantity' => 12, 'uom' => 'kg'],
            ],
        ],
        [
            'name' => 'Dal (वरण)',
            'meal_service' => 'lunch',
            'default_servings' => 100,
            'lines' => [
                ['item' => 'तूर', 'quantity' => 3, 'uom' => 'kg'],
                ['item' => 'मसूर', 'quantity' => 2, 'uom' => 'kg'],
                ['item' => 'मीठ', 'quantity' => 0.3, 'uom' => 'kg'],
            ],
        ],
        [
            'name' => 'Chapati (पोळी)',
            'meal_service' => 'dinner',
            'default_servings' => 100,
            'lines' => [
                ['item' => 'गहू', 'quantity' => 8, 'uom' => 'kg'],
                ['item' => 'Wheat', 'quantity' => 8, 'uom' => 'kg'],
            ],
        ],
        [
            'name' => 'Vegetable Curry',
            'meal_service' => 'lunch',
            'default_servings' => 100,
            'lines' => [
                ['item' => 'तेल', 'quantity' => 1.5, 'uom' => 'L'],
                ['item' => 'मीठ', 'quantity' => 0.2, 'uom' => 'kg'],
                ['item' => 'हळद', 'quantity' => 0.05, 'uom' => 'kg'],
            ],
        ],
        [
            'name' => 'Tea (चहा)',
            'meal_service' => 'breakfast',
            'default_servings' => 100,
            'lines' => [
                ['item' => 'चहा', 'quantity' => 0.3, 'uom' => 'kg'],
                ['item' => 'साखर', 'quantity' => 2, 'uom' => 'kg'],
                ['item' => 'दूध', 'quantity' => 5, 'uom' => 'L'],
            ],
        ],
    ],

];
