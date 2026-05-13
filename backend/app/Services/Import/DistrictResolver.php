<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\District;
use App\Support\Import\DistrictNameNormalizer;

class DistrictResolver
{
    private array $aliasToCode = [];

    public function __construct(private IssueCollector $issues) {}

    public function loadFor(int $regionCode): void
    {
        $this->aliasToCode = [];
        District::where('region_code', $regionCode)
            ->orderBy('sort_order')
            ->get()
            ->each(function (District $d) {
                $aliases = is_array($d->alt_labels)
                    ? $d->alt_labels
                    : (json_decode($d->alt_labels ?? '[]', true) ?: []);
                $aliases[] = $d->name_short;
                $aliases[] = $d->name_full;
                if ($d->name_latin) {
                    $aliases[] = $d->name_latin;
                }

                foreach ($aliases as $alias) {
                    $key = trim((string) $alias);
                    if ($key === '') continue;

                    // Raw exact key (always registers; last write wins for raw, which is fine — duplicates are rare)
                    $this->aliasToCode[$key] = $d->code;

                    // Normalized key — first writer wins to keep determinism on collisions
                    $norm = DistrictNameNormalizer::normalize($key);
                    if ($norm !== '' && ! isset($this->aliasToCode[$norm])) {
                        $this->aliasToCode[$norm] = $d->code;
                    }
                }

                // Cities also register the bare-name normalized form (without ' ш.')
                // so workbook bare 'Хонобод' resolves when no district claims that key.
                if ($d->kind === 'city') {
                    $bareNorm = preg_replace('/ ш\.$/u', '', DistrictNameNormalizer::normalize($d->name_short));
                    if ($bareNorm !== '' && ! isset($this->aliasToCode[$bareNorm])) {
                        $this->aliasToCode[$bareNorm] = $d->code;
                    }
                }
            });
    }

    public function resolve(string $workbookString, ImportContext $ctx, string $sourceLabel): ?int
    {
        $key = trim($workbookString);

        if (isset($this->aliasToCode[$key])) {
            return $this->aliasToCode[$key];
        }

        $norm = DistrictNameNormalizer::normalize($key);
        if ($norm !== '' && isset($this->aliasToCode[$norm])) {
            return $this->aliasToCode[$norm];
        }

        $this->issues->add(
            kind: IssueKind::UnknownDistrict,
            severity: IssueSeverity::High,
            detail: "District string did not match any alt_label in region {$ctx->regionCode()}",
            regionCode: $ctx->regionCode(),
            detectedValue: $key,
            sourceLabel: $sourceLabel,
            importRunId: $ctx->run->id,
        );
        return null;
    }
}
