<?php

/**
 * This file is part of the spryker-community/search-debug package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Client\SearchDebug\Explanation;

/**
 * Extracts the boost/idf/tf breakdown from a `weight(field:term)` leaf's own child — the standard
 * BM25Similarity explain shape confirmed live against a real query:
 *
 *   weight(full-text:handcart in 7007) [PerFieldSimilarity], result of:
 *   └─ score(freq=8.0), computed as boost * idf * tf from:
 *      ├─ boost <- exact-match leaf
 *      ├─ idf, computed as log(1 + (N - n + 0.5) / (n + 0.5)) from: <- has its own n/N children
 *      │ ├─ n, number of documents containing term
 *      │ └─ N, total number of documents with field
 *      └─ tf, computed as freq / (freq + k1 * (1 - b + b * dl / avgdl)) from: <- own children
 *         ├─ freq, occurrences of term within document
 *         ├─ k1, term saturation parameter
 *         ├─ b, length normalization parameter
 *         ├─ dl, length of field (approximate)
 *         └─ avgdl, average length of field
 *
 * A pure function of one weight node — no dependency on {@see ExplanationParser}'s own accumulated state,
 * which is what makes this a clean extraction rather than a cosmetic one.
 */
class Bm25BreakdownExtractor
{
    /**
     * A `weight(field:term)` leaf's own single child under BM25Similarity — e.g.
     * "score(freq=8.0), computed as boost * idf * tf from:". Matched by substring rather than a full
     * pattern anchored at the start: the leading "score(freq=X.0), " portion is otherwise-unused detail
     * this class doesn't need to capture.
     *
     * @var string
     */
    protected const BM25_BREAKDOWN_MARKER = 'computed as boost * idf * tf from:';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_IDF = 'idf';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF = 'tf';

    /**
     * `boost` alone has no trailing comma/explanation (unlike idf/tf and their own children below), so it
     * is matched as an exact description rather than a prefix.
     *
     * @var string
     */
    protected const BM25_CHILD_DESCRIPTION_BOOST = 'boost';

    /**
     * idf's own two children — `n,`/`N,` (lowercase = documents containing the term, uppercase = total
     * documents with the field at all) — matched by prefix since each carries its own trailing
     * explanation (e.g. "n, number of documents containing term").
     *
     * @var string
     */
    protected const BM25_CHILD_PREFIX_IDF_N = 'n,';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_IDF_CAPITAL_N = 'N,';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_FREQ = 'freq';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_K1 = 'k1';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_B = 'b,';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_DL = 'dl';

    /**
     * @var string
     */
    protected const BM25_CHILD_PREFIX_TF_AVGDL = 'avgdl';

    /**
     * Returns null when the node isn't shaped this way at all (missing child, wrong wording, or a
     * different Similarity module entirely) — no partial or guessed breakdown is ever shown, matching
     * {@see ExplanationParser}'s degrade-gracefully posture for every other unrecognized shape.
     *
     * @param array<string, mixed> $weightNode
     *
     * @return array{boost: float, idf: array{value: float, n: float, capitalN: float}, tf: array{value: float, freq: float, k1: float, b: float, dl: float, avgdl: float}}|null
     */
    public function extract(array $weightNode): ?array
    {
        $scoreNode = ($weightNode['details'] ?? [])[0] ?? null;

        if ($scoreNode === null || !str_contains((string)($scoreNode['description'] ?? ''), static::BM25_BREAKDOWN_MARKER)) {
            return null;
        }

        $scoreDetails = $scoreNode['details'] ?? [];
        $primary = $this->findRequiredChildren($scoreDetails, [
            'boost' => fn (array $nodes): ?array => $this->findChildByDescription($nodes, static::BM25_CHILD_DESCRIPTION_BOOST),
            'idf' => fn (array $nodes): ?array => $this->findChildByPrefix($nodes, static::BM25_CHILD_PREFIX_IDF),
            'tf' => fn (array $nodes): ?array => $this->findChildByPrefix($nodes, static::BM25_CHILD_PREFIX_TF),
        ]);

        if ($primary === null) {
            return null;
        }

        $idf = $this->findRequiredChildren($primary['idf']['details'] ?? [], [
            'n' => fn (array $nodes): ?array => $this->findChildByPrefix($nodes, static::BM25_CHILD_PREFIX_IDF_N),
            'capitalN' => fn (array $nodes): ?array => $this->findChildByPrefix($nodes, static::BM25_CHILD_PREFIX_IDF_CAPITAL_N),
        ]);

        if ($idf === null) {
            return null;
        }

        $tf = $this->findRequiredChildren($primary['tf']['details'] ?? [], [
            'freq' => fn (array $nodes): ?array => $this->findChildByPrefix($nodes, static::BM25_CHILD_PREFIX_TF_FREQ),
            'k1' => fn (array $nodes): ?array => $this->findChildByPrefix($nodes, static::BM25_CHILD_PREFIX_TF_K1),
            'b' => fn (array $nodes): ?array => $this->findChildByPrefix($nodes, static::BM25_CHILD_PREFIX_TF_B),
            'dl' => fn (array $nodes): ?array => $this->findChildByPrefix($nodes, static::BM25_CHILD_PREFIX_TF_DL),
            'avgdl' => fn (array $nodes): ?array => $this->findChildByPrefix($nodes, static::BM25_CHILD_PREFIX_TF_AVGDL),
        ]);

        if ($tf === null) {
            return null;
        }

        return [
            'boost' => (float)($primary['boost']['value'] ?? 0.0),
            'idf' => [
                'value' => (float)($primary['idf']['value'] ?? 0.0),
                'n' => (float)($idf['n']['value'] ?? 0.0),
                'capitalN' => (float)($idf['capitalN']['value'] ?? 0.0),
            ],
            'tf' => [
                'value' => (float)($primary['tf']['value'] ?? 0.0),
                'freq' => (float)($tf['freq']['value'] ?? 0.0),
                'k1' => (float)($tf['k1']['value'] ?? 0.0),
                'b' => (float)($tf['b']['value'] ?? 0.0),
                'dl' => (float)($tf['dl']['value'] ?? 0.0),
                'avgdl' => (float)($tf['avgdl']['value'] ?? 0.0),
            ],
        ];
    }

    /**
     * Looks up several named children at once and bails (returns null) the moment ANY of them is
     * missing — every one of {@see extract()}'s two lookup rounds needs its full set of children found
     * together or not at all; no partial breakdown is ever produced.
     *
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, callable(array<int, array<string, mixed>>): (array<string, mixed>|null)> $findersByKey
     *
     * @return array<string, array<string, mixed>>|null
     */
    protected function findRequiredChildren(array $nodes, array $findersByKey): ?array
    {
        $found = [];

        foreach ($findersByKey as $key => $finder) {
            $child = $finder($nodes);

            if ($child === null) {
                return null;
            }

            $found[$key] = $child;
        }

        return $found;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param string $description
     *
     * @return array<string, mixed>|null
     */
    protected function findChildByDescription(array $nodes, string $description): ?array
    {
        return $this->findChild($nodes, fn (string $childDescription): bool => $childDescription === $description);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param string $prefix
     *
     * @return array<string, mixed>|null
     */
    protected function findChildByPrefix(array $nodes, string $prefix): ?array
    {
        return $this->findChild($nodes, fn (string $childDescription): bool => str_starts_with($childDescription, $prefix));
    }

    /**
     * Shared linear search behind {@see findChildByDescription()} and {@see findChildByPrefix()} — the two
     * differ only in how a child's own description is matched, not in how the list is walked.
     *
     * @param array<int, array<string, mixed>> $nodes
     * @param callable(string): bool $matches
     *
     * @return array<string, mixed>|null
     */
    protected function findChild(array $nodes, callable $matches): ?array
    {
        foreach ($nodes as $node) {
            if ($matches((string)($node['description'] ?? ''))) {
                return $node;
            }
        }

        return null;
    }
}
