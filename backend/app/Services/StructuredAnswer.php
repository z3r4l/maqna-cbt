<?php

namespace App\Services;

final class StructuredAnswer
{
    public static function normalize(string $type, mixed $answer, array $options, array $choices = []): ?array
    {
        if ($answer === null || $answer === []) return null;
        if (! is_array($answer)) throw new \InvalidArgumentException('Jawaban terstruktur tidak valid.');
        $ids = array_map('intval', array_column($options, 'id'));
        $choiceIds = array_map('intval', array_column($choices, 'id'));
        if ($type === 'multi_mcq') {
            if (! array_is_list($answer) || count($answer) > count($ids) || count($answer) !== count(array_unique($answer, SORT_REGULAR))) throw new \InvalidArgumentException('Pilihan jamak tidak valid.');
            foreach ($answer as $id) if (! is_int($id) || ! in_array($id, $ids, true)) throw new \InvalidArgumentException('Pilihan jamak tidak termasuk soal.');
            sort($answer, SORT_NUMERIC);
            return $answer;
        }
        if (! in_array($type, ['true_false', 'matching'], true) || array_is_list($answer) || count($answer) > count($ids)) throw new \InvalidArgumentException('Jawaban per baris tidak valid.');
        $clean = [];
        foreach ($answer as $key => $value) {
            if (! ctype_digit((string) $key) || ! in_array((int) $key, $ids, true)) throw new \InvalidArgumentException('Baris tidak termasuk soal.');
            if ($type === 'true_false' && ! is_bool($value)) throw new \InvalidArgumentException('Benar/salah harus berupa boolean.');
            if ($type === 'matching' && (! is_int($value) || ! in_array($value, $choices === [] ? $ids : $choiceIds, true))) throw new \InvalidArgumentException('Pasangan tidak termasuk soal.');
            $clean[(string) $key] = $value;
        }
        if ($type === 'matching' && count(array_unique(array_values($clean))) !== count($clean)) throw new \InvalidArgumentException('Satu pasangan hanya boleh digunakan sekali.');
        ksort($clean, SORT_NUMERIC);
        return $clean;
    }

    public static function points(string $type, ?array $answer, array $options, float $weight, array $choices = []): array
    {
        if ($answer === null || $answer === []) return [0.0, false];
        if ($type === 'multi_mcq') {
            $expected = array_map('intval', array_column(array_filter($options, static fn ($option) => (int) $option['is_correct'] === 1), 'id'));
            sort($expected, SORT_NUMERIC);
            return [$answer === $expected ? $weight : 0.0, $answer === $expected];
        }
        $correct = 0;
        foreach ($options as $option) {
            $id = (string) $option['id'];
            if (! array_key_exists($id, $answer)) continue;
            if ($type === 'true_false' && $answer[$id] === ((int) $option['is_correct'] === 1)) $correct++;
            if ($type === 'matching' && $answer[$id] === (int) ($option['match_choice_id'] ?? $option['id'])) $correct++;
        }
        return [round($weight * $correct / count($options), 2), $correct === count($options)];
    }
}
