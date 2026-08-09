<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use App\Models\PlacementPointRule;
use App\Models\PlacementPointTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SiklabReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['CSPC Buhi Campus', 'Buhi', 'Fuchsia Pink'],
            ['College of Arts and Sciences', 'CAS', 'Red'],
            ['College of Computer Studies', 'CCS', 'Yellow'],
            ['College of Health Sciences', 'CHS', 'Purple'],
            ['College of Engineering and Architecture', 'CEA', 'Gray'],
            ['College of Technological and Developmental Education', 'CTDE', 'Blue'],
            ['College of Tourism, Hospitality and Business Management', 'CTHBM', 'Green'],
        ] as [$name, $abbreviation, $color]) {
            OrganizationalUnit::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'abbreviation' => $abbreviation,
                    'default_color' => $color,
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['major', 'Major', ['champion' => 25, 'first_runner_up' => 20, 'second_runner_up' => 15, 'participation' => 5]],
            ['standard', 'Standard', ['champion' => 20, 'first_runner_up' => 15, 'second_runner_up' => 10, 'participation' => 5]],
            ['individual', 'Individual', ['champion' => 5, 'first_runner_up' => 4, 'second_runner_up' => 3, 'participation' => 1]],
            ['intermediate', 'Intermediate', ['champion' => 8, 'first_runner_up' => 6, 'second_runner_up' => 4, 'participation' => 2]],
        ] as [$code, $name, $rules]) {
            $template = PlacementPointTemplate::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'version' => 1,
                    'source_reference' => 'Approved-2025-Intramurals-Proposal.pdf',
                    'is_signed_off' => false,
                ],
            );

            if (! $template->is_signed_off) {
                foreach ($rules as $placementKey => $points) {
                    PlacementPointRule::query()->updateOrCreate(
                        [
                            'placement_point_template_id' => $template->getKey(),
                            'placement_key' => $placementKey,
                        ],
                        [
                            'points' => $points,
                            'is_participation' => $placementKey === 'participation',
                            'display_order' => array_search($placementKey, array_keys($rules), true),
                        ],
                    );
                }

                $template->update(['is_signed_off' => true]);
            }
        }
    }
}
