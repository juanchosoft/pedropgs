<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Core\Db;

final class PropertyMatcher
{
    private const MIN_SCORE = 0.8;
    private const MIN_MARGIN = 0.1;

    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Finds a single property for free text, or null when no clear winner exists.
     *
     * @param list<int>|null $candidateIds restrict the search to these properties
     * @return array{id:int,name:string}|null
     */
    public function match(string $text, ?array $candidateIds = null): ?array
    {
        $needle = self::normalize($text);
        if ($needle === '') {
            return null;
        }

        $scores = [];
        foreach ($this->pool($candidateIds) as $id => $name) {
            $scores[$id] = self::score($needle, self::normalize($name));
        }
        if ($scores === []) {
            return null;
        }
        arsort($scores);
        $ids = array_keys($scores);
        $best = $scores[$ids[0]];
        $second = $scores[$ids[1] ?? -1] ?? 0.0;
        if ($best < self::MIN_SCORE || ($best - $second) < self::MIN_MARGIN) {
            return null;
        }

        return ['id' => (int) $ids[0], 'name' => $this->pool($candidateIds)[$ids[0]]];
    }

    public static function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $text = strtolower($ascii === false ? $value : $ascii);
        $text = (string) preg_replace('/\b(apt|apartment|unit|suite|ste|bldg|building)\b|#/', ' ', $text);
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private static function score(string $needle, string $name): float
    {
        if ($name === '') {
            return 0.0;
        }
        if ($needle === $name) {
            return 1.0;
        }
        if (strlen($needle) >= 3 && (str_contains($name, $needle) || str_contains($needle, $name))) {
            return 0.9;
        }
        similar_text($needle, $name, $percent);

        return $percent / 100;
    }

    /**
     * @param list<int>|null $candidateIds
     * @return array<int,string>
     */
    private function pool(?array $candidateIds): array
    {
        $rows = $this->pdo->query("SELECT id, nombre FROM tbl_unidades WHERE enable IN ('yes','si')")->fetchAll(\PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if ($candidateIds === null || in_array((int) $row['id'], $candidateIds, true)) {
                $out[(int) $row['id']] = (string) $row['nombre'];
            }
        }

        return $out;
    }
}
