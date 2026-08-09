<?php

namespace App\Support;

final class Siklab2025Programme
{
    /** @return list<array{name: string, abbreviation: string, color: string}> */
    public static function teams(): array
    {
        return [
            ['name' => 'CSPC Buhi Campus', 'abbreviation' => 'Buhi', 'color' => 'Fuchsia Pink'],
            ['name' => 'College of Arts and Sciences', 'abbreviation' => 'CAS', 'color' => 'Red'],
            ['name' => 'College of Computer Studies', 'abbreviation' => 'CCS', 'color' => 'Yellow'],
            ['name' => 'College of Health Sciences', 'abbreviation' => 'CHS', 'color' => 'Purple'],
            ['name' => 'College of Engineering and Architecture', 'abbreviation' => 'CEA', 'color' => 'Gray'],
            ['name' => 'College of Technological and Developmental Education', 'abbreviation' => 'CTDE', 'color' => 'Blue'],
            ['name' => 'College of Tourism, Hospitality and Business Management', 'abbreviation' => 'CTHBM', 'color' => 'Green'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function sports(): array
    {
        return [
            self::sport('Basketball', ['Men', 'Women'], 'single_elimination', 'major', 15, 'team_total', 'Proposal pp. 10–11', rosterRoleLimits: [
                'student_coach' => 1,
                'faculty_coach' => 2,
            ]),
            self::sport('Volleyball', ['Men', 'Women'], 'single_elimination', 'major', 15, 'best_of_sets', 'Proposal pp. 10, 12', [
                'target_wins' => 2,
                'set_targets' => [20, 20, 15],
            ]),
            self::sport('Badminton', ['Men', 'Women'], 'double_elimination', 'standard', 4, 'team_tie', 'Proposal pp. 10–11', [
                'target_wins' => 2,
                'rubbers' => ['Singles', 'Doubles', 'Singles'],
                'game_target' => 21,
            ]),
            self::sport('Table Tennis', ['Men', 'Women'], 'double_elimination', 'standard', 4, 'team_tie', 'Proposal pp. 10, 12', [
                'target_wins' => 2,
                'rubbers' => ['Singles', 'Doubles', 'Singles'],
                'game_target' => 11,
            ]),
            self::sport('Lawn Tennis', ['Men', 'Women'], 'double_elimination', 'standard', 4, 'team_tie', 'Proposal pp. 10, 13', [
                'target_wins' => 2,
                'rubbers' => ['Singles', 'Doubles', 'Singles'],
                'game_target' => 8,
                'no_advantage' => true,
            ]),
            self::sport('Sepak Takraw', ['Men'], 'single_elimination', 'standard', 6, 'best_of_sets', 'Proposal pp. 10, 13', [
                'target_wins' => 2,
                'set_target' => 15,
                'set_cap' => 17,
            ], 'blocked', 'Master roster allows 6 while detailed rules allow a maximum of 4.'),
            self::sport('Chess', ['Men', 'Women'], 'round_robin', 'intermediate', 4, 'chess', 'Proposal pp. 10, 13–14', [
                'win_points' => 1,
                'draw_points' => 0.5,
                'loss_points' => 0,
            ]),
            self::sport('Taekwondo', ['Men', 'Women'], 'single_elimination', 'major', 8, 'combat_rounds', 'Proposal pp. 10, 14–15', [
                'target_wins' => 2,
                'point_values' => [
                    'body_kick' => 2,
                    'head_kick' => 3,
                    'turning_body_kick' => 4,
                    'turning_head_kick' => 5,
                ],
                'weight_classes' => [
                    'Men' => ['63kg', '68kg', '73kg', '79kg', '85kg', '86kg and above'],
                    'Women' => ['53kg', '57kg', '62kg', '67kg', '73kg', '74kg and above'],
                ],
            ], 'blocked', 'Weight-class tournament entries require final aggregate placement confirmation.'),
            self::sport('Arnis', ['Men', 'Women'], 'single_elimination', 'major', 8, 'combat_rounds', 'Proposal pp. 10, 15', [
                'target_wins' => 2,
                'weight_classes' => [
                    'Men' => ['65kg', '72kg', '80kg', '89kg', '99kg', '100kg and above'],
                    'Women' => ['56kg', '62kg', '69kg', '77kg', '86kg', '87kg and above'],
                ],
            ], 'blocked', 'Master roster allows 8 while detailed rules allow a maximum of 6.'),
            [
                'name' => 'Athletics',
                'divisions' => ['Men', 'Women'],
                'format' => 'aggregate',
                'template' => 'major',
                'participant_mode' => 'individual',
                'max_roster_size' => 10,
                'source_reference' => 'Proposal pp. 10, 15–16',
                'source_status' => 'blocked',
                'blocker' => 'The master table says single elimination while detailed rules require discipline aggregation.',
                'outcome_profile' => 'measurement',
                'configuration' => [],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function judgedCompetitions(): array
    {
        return [
            self::judged('Extemporaneous Speaking', 'individual', [
                ['Content and clear organization', 35], ['Delivery', 35], ['Pronunciation, enunciation and diction', 20], ['Stage presence', 10],
            ], 'individual', 'Proposal p. 18'),
            self::judged('Dagliang Talumpati', 'individual', [
                ['Nilalaman at malinaw na organisasyon', 35], ['Pagbigkas', 35], ['Pronunciation, enunciation and diction', 20], ['Stage presence', 10],
            ], 'individual', 'Proposal p. 18'),
            self::judged('Story Telling', 'individual', [
                ['Content and clear organization', 35], ['Delivery', 35], ['Pronunciation, enunciation and diction', 20], ['Stage presence', 10],
            ], 'individual', 'Proposal p. 19'),
            self::judged('Pagkukwento', 'individual', [
                ['Nilalaman at malinaw na organisasyon', 35], ['Pagbigkas', 35], ['Pronunciation, enunciation and diction', 20], ['Stage presence', 10],
            ], 'individual', 'Proposal p. 19'),
            self::judged('Radio Drama', 'team', [
                ['Radio Drama Script', 30], ['Technical Quality', 30], ['Vocal Quality', 30], ['Overall Appeal', 10],
            ], 'standard', 'Proposal p. 20'),
            self::judged('Pop Solo', 'individual', [
                ['Tone Quality', 40], ['Musicianship', 40], ['Deportment', 20],
            ], 'individual', 'Proposal p. 20'),
            self::judged('Kundiman', 'individual', [
                ['Tone Quality', 40], ['Musicianship', 40], ['Deportment', 20],
            ], 'individual', 'Proposal p. 20'),
            self::judged('Vocal Duet', 'pair', [
                ['Tone Quality and Vocal Technique', 30], ['Blending and Harmony', 30], ['Musicianship', 30], ['Deportment', 10],
            ], 'intermediate', 'Proposal p. 21'),
            self::judged('Instrumental Solo — Bandurria', 'individual', self::instrumentalCriteria(), 'individual', 'Proposal p. 21'),
            self::judged('Instrumental Solo — Piano', 'individual', self::instrumentalCriteria(), 'individual', 'Proposal p. 21'),
            self::judged('Instrumental Solo — Classical Guitar', 'individual', self::instrumentalCriteria(), 'individual', 'Proposal p. 21'),
            self::judged('Folk Dance', 'team', [
                ['Performance', 40], ['Interpretation', 30], ['Costume, music and equipment', 20], ['Overall Impact', 10],
            ], 'standard', 'Proposal p. 22'),
            self::judged('Hip Hop Dance', 'team', [
                ['Choreography', 20], ['Rhythm and Timing', 20], ['Costume', 10], ['Technique', 25], ['Performance', 25],
            ], 'standard', 'Proposal p. 22'),
            self::judged('Contemporary Dance', 'team', [
                ['Choreography and Composition', 30], ['Performance', 30], ['Technique', 20], ['Overall Impact', 20],
            ], 'standard', 'Proposal p. 22'),
            self::judged('Charcoal Rendering', 'individual', self::visualCriteria(), 'individual', 'Proposal p. 23'),
            self::judged('Pencil Drawing', 'individual', self::visualCriteria(), 'individual', 'Proposal p. 23'),
            self::judged('Painting', 'individual', self::visualCriteria(), 'individual', 'Proposal p. 23'),
            self::judged('On-the-Spot Poster Making', 'individual', self::visualCriteria(), 'individual', 'Proposal p. 23'),
            self::judged('Photography', 'individual', self::visualCriteria(), 'individual', 'Proposal p. 23'),
            self::judged('Essay Writing', 'individual', [
                ['Content and Relevance', 30], ['Organization and Structure', 25], ['Creativity and Originality', 20], ['Grammar and Mechanics', 10], ['Impact and Persuasiveness', 10],
            ], 'individual', 'Proposal p. 18', 'blocked', 'Criteria total 95 while the source prints 100.'),
            self::judged('Pagsulat ng Sanaysay', 'individual', [
                ['Nilalaman at Kaugnayan', 30], ['Organisasyon at Estruktura', 25], ['Pagkamalikhain at Orihinalidad', 20], ['Gramatika at Mekaniks', 10], ['Epekto at Panghihikayat', 10],
            ], 'individual', 'Proposal p. 18', 'blocked', 'Criteria total 95 while the source prints 100.'),
            self::judged('Dance Sports', 'pair', [
                ['Floor craft', null], ['Timing and basic rhythm', null], ['Body lines', null], ['Movement', null], ['Rhythmic interpretation', null], ['Footwork', null], ['Dance characterization', null],
            ], 'intermediate', 'Proposal p. 22', 'blocked', 'The proposal lists criteria without weights.'),
            self::judged('Cheer Dance', 'team', [
                ['Choreography and Synchronization', 30], ['Overall relevance to theme', 25], ['Cheers and Music', 15], ['Costume and Props', 15], ['Overall Impact', 100],
            ], 'standard', 'Proposal p. 24', 'blocked', 'Overall Impact is printed as 100 percent, producing an invalid total.'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function athleticsDisciplines(): array
    {
        $disciplines = [];

        foreach ([100, 200, 400, 800, 1500, 3000] as $distance) {
            $disciplines[] = [
                'code' => "{$distance}m",
                'name' => "{$distance}m",
                'family' => 'track',
                'performance_type' => 'time',
                'canonical_unit' => 'seconds',
                'sort_direction' => 'ascending',
                'sub_points' => [1 => 5, 2 => 4, 3 => 3, 4 => 1],
            ];
        }

        foreach ([['shot-put', 'Shot Put'], ['discus', 'Discus'], ['long-jump', 'Long Jump'], ['triple-jump', 'Triple Jump']] as [$code, $name]) {
            $disciplines[] = [
                'code' => $code,
                'name' => $name,
                'family' => 'field',
                'performance_type' => 'distance',
                'canonical_unit' => 'metres',
                'sort_direction' => 'descending',
                'sub_points' => [1 => 5, 2 => 4, 3 => 3, 4 => 1],
            ];
        }

        foreach ([['4x100m', '4 × 100m Relay'], ['4x400m', '4 × 400m Relay']] as [$code, $name]) {
            $disciplines[] = [
                'code' => $code,
                'name' => $name,
                'family' => 'relay',
                'performance_type' => 'time',
                'canonical_unit' => 'seconds',
                'sort_direction' => 'ascending',
                'sub_points' => [1 => 10, 2 => 8, 3 => 6, 4 => 2],
            ];
        }

        return $disciplines;
    }

    /** @return array<string, mixed> */
    private static function sport(
        string $name,
        array $divisions,
        string $format,
        string $template,
        int $maxRosterSize,
        string $outcomeProfile,
        string $sourceReference,
        array $configuration = [],
        string $sourceStatus = 'verified',
        ?string $blocker = null,
        array $rosterRoleLimits = [],
    ): array {
        return compact('name', 'divisions', 'format', 'template', 'maxRosterSize', 'outcomeProfile', 'sourceReference', 'configuration', 'sourceStatus', 'blocker', 'rosterRoleLimits') + [
            'participant_mode' => 'team',
        ];
    }

    /** @return array<string, mixed> */
    private static function judged(
        string $name,
        string $participantMode,
        array $criteria,
        string $template,
        string $sourceReference,
        string $sourceStatus = 'verified',
        ?string $blocker = null,
    ): array {
        return compact('name', 'participantMode', 'criteria', 'template', 'sourceReference', 'sourceStatus', 'blocker');
    }

    /** @return list<array{0: string, 1: int}> */
    private static function instrumentalCriteria(): array
    {
        return [['Technique', 30], ['Mastery of Piece', 30], ['Interpretation and Expression', 30], ['Stage Deportment', 10]];
    }

    /** @return list<array{0: string, 1: int}> */
    private static function visualCriteria(): array
    {
        return [['Concept', 35], ['Techniques', 35], ['Composition', 30]];
    }
}
