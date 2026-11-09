<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Lunar\Core\Models\Brand;
use Normalizer;

class BrandManager implements \Lunar\Storefront\Contracts\BrandManager
{
    /** @var array<string, list<string>>|null */
    private static ?array $initials = null;

    public function getBySlug(string $slug): Brand
    {
        return Brand::with(['thumbnail'])
            ->whereHas('defaultUrl', fn (Builder $query) => $query->where('slug', $slug))
            ->firstOrFail();
    }

    public function getPaginated(int $perPage = 150): LengthAwarePaginator
    {
        return Brand::whereHas('defaultUrl')
            ->with(['defaultUrl', 'thumbnail'])
            ->orderByRaw($this->letterOrder(self::initials()))
            ->orderBy((new Brand)->qualifyColumn('name'))
            ->paginate($perPage);
    }

    /**
     * The characters a name can start with to file under each letter: the letter in
     * either case and every character whose canonical decomposition (NFD) starts with
     * it, so "Évian" files under E. Anything else ("18 Studio", "Øresund", "유진게임즈")
     * files under "#". This is the same fold a browser does with `normalize('NFD')`,
     * and it does not depend on the database or its collation.
     *
     * @return array<string, list<string>>
     */
    public static function initials(): array
    {
        if (self::$initials !== null) {
            return self::$initials;
        }

        $initials = [];
        foreach (range('A', 'Z') as $letter) {
            $initials[$letter] = [$letter, strtolower($letter)];
        }

        // The only code points whose decomposition starts with a basic Latin letter: the Latin
        // supplement and extended blocks, and the Kelvin and Angstrom signs.
        foreach ([...range(0xC0, 0x24F), ...range(0x1E00, 0x1EFF), 0x212A, 0x212B] as $codepoint) {
            $character = mb_chr($codepoint, 'UTF-8');
            $base = substr((string) Normalizer::normalize($character, Normalizer::FORM_D), 0, 1);

            if ($character !== $base && preg_match('/^[A-Za-z]$/', $base) === 1) {
                $initials[strtoupper($base)][] = $character;
            }
        }

        return self::$initials = $initials;
    }

    /**
     * Whether the name's first character is one of `$characters`, compared exactly:
     * MySQL compares the bytes, so no collation folds "Ø" into O behind the fold above.
     *
     * @param  list<string>  $characters
     */
    private function firstCharacterIn(array $characters): string
    {
        $connection = (new Brand)->getConnection();
        $name = $connection->getQueryGrammar()->wrap((new Brand)->qualifyColumn('name'));

        $first = match ($connection->getDriverName()) {
            'mysql', 'mariadb' => "cast(substr({$name}, 1, 1) as binary)",
            'pgsql', 'sqlite' => "substr({$name}, 1, 1)",
            default => throw new LogicException(
                "The brand index can't order names on the [{$connection->getDriverName()}] driver."
            ),
        };

        return '('.$first.' in ('.implode(', ', array_map(fn (string $character) => $connection->escape($character), $characters)).'))';
    }

    /**
     * Orders by the letter a name files under, A to Z and then "#", so a folded name
     * ("Évian") sits with its letter and names starting with a digit or a symbol
     * ("#1 Games", "[Bracket]") list after the alphabet, the same on every database.
     *
     * @param  array<string, list<string>>  $initials
     */
    private function letterOrder(array $initials): string
    {
        $cases = collect($initials)
            ->map(fn (array $characters, string $letter) => 'when '.$this->firstCharacterIn($characters).' then '.(ord($letter) - ord('A')))
            ->implode(' ');

        return "case {$cases} else 26 end";
    }
}
