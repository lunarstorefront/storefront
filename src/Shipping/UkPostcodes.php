<?php

namespace Lunar\Storefront\Shipping;

final class UkPostcodes
{
    /**
     * Every GB postcode area.
     *
     * @var list<string>
     */
    public const GB_AREAS = [
        'AB', 'AL', 'B', 'BA', 'BB', 'BD', 'BH', 'BL', 'BN', 'BR', 'BS', 'BT', 'CA', 'CB', 'CF',
        'CH', 'CM', 'CO', 'CR', 'CT', 'CV', 'CW', 'DA', 'DD', 'DE', 'DG', 'DH', 'DL', 'DN', 'DT',
        'DY', 'E', 'EC', 'EH', 'EN', 'EX', 'FK', 'FY', 'G', 'GL', 'GU', 'GY', 'HA', 'HD', 'HG',
        'HP', 'HR', 'HS', 'HU', 'HX', 'IG', 'IM', 'IP', 'IV', 'JE', 'KA', 'KT', 'KW', 'KY', 'L',
        'LA', 'LD', 'LE', 'LL', 'LN', 'LS', 'LU', 'M', 'ME', 'MK', 'ML', 'N', 'NE', 'NG', 'NN',
        'NP', 'NR', 'NW', 'OL', 'OX', 'PA', 'PE', 'PH', 'PL', 'PO', 'PR', 'RG', 'RH', 'RM', 'S',
        'SA', 'SE', 'SG', 'SK', 'SL', 'SM', 'SN', 'SO', 'SP', 'SR', 'SS', 'ST', 'SW', 'SY', 'TA',
        'TD', 'TF', 'TN', 'TQ', 'TR', 'TS', 'TW', 'UB', 'W', 'WA', 'WC', 'WD', 'WF', 'WN', 'WR',
        'WS', 'WV', 'YO', 'ZE',
    ];

    /**
     * Highlands and islands areas, wholly outside the mainland.
     *
     * @var list<string>
     */
    public const HIGHLANDS_AND_ISLANDS_AREAS = ['HS', 'IV', 'KW', 'ZE'];

    /**
     * Outlying districts of otherwise mainland areas: [area, first, last].
     *
     * @var list<array{0: string, 1: int, 2: int}>
     */
    public const HIGHLANDS_AND_ISLANDS_DISTRICTS = [
        ['AB', 36, 38], ['AB', 55, 56],
        ['FK', 17, 21],
        ['KA', 27, 28],
        ['PA', 20, 49], ['PA', 60, 78],
        ['PH', 19, 26], ['PH', 30, 44], ['PH', 49, 50],
        ['PO', 30, 41],
        ['TR', 21, 25],
    ];

    /**
     * Areas outside the UK mainland zones, keyed by area with a label.
     *
     * @var array<string, string>
     */
    public const EXCLUDED_AREAS = [
        'BT' => 'Northern Ireland',
        'IM' => 'the Isle of Man',
        'JE' => 'Jersey',
        'GY' => 'Guernsey',
    ];

    /**
     * Every GB postcode area that is neither highlands and islands nor excluded.
     *
     * @return list<string>
     */
    public static function mainland(): array
    {
        return array_values(array_filter(
            self::GB_AREAS,
            fn (string $area): bool => ! in_array($area, self::HIGHLANDS_AND_ISLANDS_AREAS, true)
                && ! array_key_exists($area, self::EXCLUDED_AREAS),
        ));
    }

    /**
     * The highlands and islands areas plus every outlying district, expanded (e.g. PA20 ... PA49).
     *
     * @return list<string>
     */
    public static function highlandsAndIslands(): array
    {
        $districts = [];

        foreach (self::HIGHLANDS_AND_ISLANDS_DISTRICTS as [$area, $first, $last]) {
            for ($number = $first; $number <= $last; $number++) {
                $districts[] = $area.$number;
            }
        }

        return [...self::HIGHLANDS_AND_ISLANDS_AREAS, ...$districts];
    }

    /**
     * @return list<string>
     */
    public static function excluded(): array
    {
        return array_keys(self::EXCLUDED_AREAS);
    }
}
