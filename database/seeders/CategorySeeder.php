<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => "Attraction",
                'photo' => "/storage/categories_images/attraction_icon.svg",
                'priority' => 1,
            ],
            [
                'name' => "Tour Travel (recurring)",
                'photo' => "/storage/categories_images/tour_icon.png",
                'priority' => 2,
            ],
            [
                'name' => "Daily Activities",
                'photo' => "/storage/categories_images/activities_icon.png",
                'priority' => 3,
            ]
        ];
        foreach ($categories as $cat) {
            Category::create($cat);
        };
    }
}
