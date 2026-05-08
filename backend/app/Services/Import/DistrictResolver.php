<?php

namespace App\Services\Import;

use App\Enums\IssueKind;
use App\Enums\IssueSeverity;
use App\Models\District;

class DistrictResolver
{
    private array $aliasToCode = [];

    public function __construct(private IssueCollector $issues) {}

    public function loadFor(string $regionCode): void
    {
        $this->aliasToCode = [];
        District::where('region_code', $regionCode)->get()->each(function (District $d) {
            $aliases = is_array($d->alt_labels) ? $d->alt_labels : (json_decode($d->alt_labels ?? '[]', true) ?: []);
            $aliases[] = $d->name_short;
            $aliases[] = $d->name_full;
            if ($d->name_latin) {
                $aliases[] = $d->name_latin;
            }
            foreach ($aliases as $alias) {
                $key = trim($alias);
                if ($key !== '') {
                    $this->aliasToCode[$key] = $d->code;
                }
            }
        });
    }

    public function resolve(string $workbookString, ImportContext $ctx, string $sourceLabel): ?string
    {
        $key = trim($workbookString);
        if (isset($this->aliasToCode[$key])) {
            return $this->aliasToCode[$key];
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
