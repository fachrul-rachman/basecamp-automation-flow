<?php

namespace App\Modules\KpusGaHw\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class DateTitleParser
{
    /** @var array<string, int> */
    private const MONTHS = [
        'januari' => 1,
        'jan' => 1,
        'februari' => 2,
        'feb' => 2,
        'maret' => 3,
        'mar' => 3,
        'april' => 4,
        'apr' => 4,
        'mei' => 5,
        'juni' => 6,
        'jun' => 6,
        'juli' => 7,
        'jul' => 7,
        'agustus' => 8,
        'agu' => 8,
        'agt' => 8,
        'september' => 9,
        'sep' => 9,
        'oktober' => 10,
        'okt' => 10,
        'november' => 11,
        'nov' => 11,
        'desember' => 12,
        'des' => 12,
    ];

    public function parse(string $title): ?CarbonImmutable
    {
        $title = Str::of($title)->squish()->lower()->toString();

        if (preg_match('/^(\d{1,2})\s*[-.\/]\s*(\d{1,2})\s*[-.\/]\s*(\d{2}|\d{4})$/', $title, $matches) === 1) {
            return $this->date((int) $matches[1], (int) $matches[2], $this->normalizeYear($matches[3]));
        }

        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{2}|\d{4})$/', $title, $matches) === 1) {
            $month = self::MONTHS[$matches[2]] ?? null;

            if ($month === null) {
                return null;
            }

            return $this->date((int) $matches[1], $month, $this->normalizeYear($matches[3]));
        }

        return null;
    }

    private function normalizeYear(string $year): int
    {
        if (strlen($year) === 4) {
            return (int) $year;
        }

        return 2000 + (int) $year;
    }

    private function date(int $day, int $month, int $year): ?CarbonImmutable
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, (string) config('kpus-ga-hw.timezone'));
    }
}
