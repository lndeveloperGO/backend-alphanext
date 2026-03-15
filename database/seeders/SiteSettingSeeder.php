<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'login_bg_image',
                'value' => 'https://cdn-prd.tongkolspace.com/hipwee/wp-content/uploads/2020/12/hipwee-Depositphotos_317220796_l-2015.jpg',
                'type' => 'image',
                'label' => 'Login Background Image',
            ],
            [
                'key' => 'register_bg_image',
                'value' => 'https://cdn.antaranews.com/cache/1200x800/2021/08/10/shutterstock_1389777500.jpg',
                'type' => 'image',
                'label' => 'Register Background Image',
            ],
            [
                'key' => 'landing_cta_bg_image',
                'value' => '/BackropAlphaNext_LandingPage.png',
                'type' => 'image',
                'label' => 'Landing Page CTA Background',
            ],
            [
                'key' => 'app_logo',
                'value' => '/logoAlphanext.jpg',
                'type' => 'image',
                'label' => 'Application Logo',
            ],
        ];

        foreach ($settings as $setting) {
            \App\Models\SiteSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
