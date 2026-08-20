<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Region-staff review fixes for the partner tasks workbook (data/edits/Ҳудудлар).
 *
 * The monthly import:task-progress rebuilds titles, metric labels, units and
 * plan/actual values straight from the partner file, so any correction applied
 * only to the database would be silently undone on the next import. This class
 * is that correction set, expressed idempotently:
 *
 *  - TEXT_REPLACEMENTS / UNIT_MAP fix spelling the partner file itself carries;
 *  - valueFixes() maps a KNOWN-BAD stored value to its verified replacement
 *    (guarded by region + task_number + line + period), so a partner file that
 *    already carries the corrected value is left untouched.
 *
 * import:task-progress calls apply() after every run; `tasks:apply-review-fixes`
 * runs it manually. Verified sources: the region guarantee letters under
 * data/2-ярим йиллик бўйича кафолат хатлари/ and the review docs in data/edits/.
 */
class TaskReviewFixes
{
    /** Spelling fixes, applied to task titles and progress metric labels. */
    public const TEXT_REPLACEMENTS = [
        'биричи'                  => 'биринчи',
        'Биричи'                  => 'Биринчи',
        'саноат маҳсулотларни'    => 'саноат маҳсулотларини',
        'Саноат маҳсулотларни'    => 'Саноат маҳсулотларини',
        'тегилши'                 => 'тегишли',
        'Тегилши'                 => 'Тегишли',
        'Тадбикорлик'             => 'Тадбиркорлик',
        'тадбикорлик'             => 'тадбиркорлик',
        'хажми'                   => 'ҳажми',
        'Хажми'                   => 'Ҳажми',
        'дародмалари'             => 'даромадлари',
        'Дародмалари'             => 'Даромадлари',
        'инфртузилма'             => 'инфратузилма',
        'Инфртузилма'             => 'Инфратузилма',
        'камайтириш қисқартириш'  => 'қисқартириш',
        'бўйича орқали'           => 'бўйича',
        'ярмакалар'               => 'ярмаркалар',
        'Ярмакалар'               => 'Ярмаркалар',
        // Second review round (Самарқанд/Сирдарё/Сурхондарё/Фарғона docs).
        // Order matters: the doubled-phrase fixes run before the generic ones.
        'республикада жорий йил якуни билан кичик' => 'ҳудудда кичик',
        'якуни якуни билан'       => 'якуни билан',
        'йил йил якуни'           => 'йил якуни',
        'Йио якуни'               => 'Йил якуни',
        'якунига билан'           => 'якуни билан',
        'шаҳобча'                 => 'шохобча',
        'Шаҳобча'                 => 'Шохобча',
        'лойиҳарни'               => 'лойиҳаларни',
        'ягни уқув'               => 'янги ўқув',
        'узунгили'                => 'узунлиги',
        'солиқ маъмурчилиги'      => 'солиқ маъмуриятчилиги',
        'холи бўлган'             => 'ҳоли бўлган',
        'холи ҳудуд'              => 'ҳоли ҳудуд',
        'йўлган қўйила'           => 'йўлга қўйила',
        'Ишга туширилади лойиҳа'  => 'Ишга туширилаётган лойиҳа',
        'туширилади саноат'       => 'туширилаётган саноат',
        'манзилбай'               => 'манзилма-манзил',
        'оқава'                   => 'оқова',
        'Оқава'                   => 'Оқова',
        'кўриш .'                 => 'кўриш.',
        // Task №9 carries a stale «6,1 трлн» figure in its TITLE in every region
        // while each region has its own plan value (Самарқанд review 1.8).
        'зоналарида 6,1 трлн сўм. саноат маҳсулоти' => 'зоналарида саноат маҳсулоти',
        // Task №64 title is garbled in the source file (Фарғона review):
        // «кўрсаткичларини ошириш» → reach the planned targets.
        'қурилиш ишлари режада белгиланган кўрсаткичларини ошириш' => 'қурилиш ишлари ҳажмини режада белгиланган кўрсаткичларга етказиш',
    ];

    /** Unit spelling fixes (exact match on the stored unit string). */
    public const UNIT_MAP = [
        'сони'      => 'та',
        'млн долл'  => 'млн доллар',
        'млрд долл' => 'млрд доллар',
        'млн кВт/с' => 'млн кВт·соат',
    ];

    private const TASK_TEXT_COLUMNS = ['title', 'mechanism_text', 'executor_text', 'report_schedule_text', 'section_label', 'deadline_text'];

    /**
     * (region_code => task_numbers) the region reviews asked to REMOVE — duplicated
     * indicators whose authoritative copy lives in another task (see the review
     * docs under data/edits/Ҳудудлар). Rows are flagged tasks.hidden and skipped
     * by every board query (Task::scopeHasPlan); they are not deleted because
     * import:task-progress would recreate them from the partner file. Un-listing
     * a pair here un-hides it on the next apply().
     */
    public const SUPPRESSED = [
        // Самарқанд review 1.9/1.11/1.13: duplicated project/enterprise/housing
        // tasks whose figures the 20.07.2026 letter does not carry — the
        // authoritative copies are tasks №287 (82), №272 (80) and №66 (57).
        1718 => ['13', '15', '162', '407'],
        // Сирдарё review 1.1/1.2: same duplicate trio — №13 (5 та corp vs the
        // letter's 30), №15/№162 (397 дона / 10,2 минг vs the letter's 389 / 10,4
        // carried by №287); authoritative copies are №272 and №287.
        1724 => ['13', '15', '162'],
        // Сурхондарё review 1.1/1.5 + 2.4: №15 (1 300 млн / 380 дона vs the
        // letter's 865 млн / 365 та carried by №287), №201 (stale employment
        // plans vs №324's letter values), №407 (10 000 хонадон duplicate of №66).
        1722 => ['15', '201', '407'],
        // Тошкент вилояти review 2.1: №15 carries the older 472 та, the letter
        // says 451 та / 21 971 иш ўрни — carried by №287.
        1727 => ['15'],
    ];

    /**
     * Old-duplicate → authoritative task pairs. Four independent region reviews
     * (Самарқанд 1.9, Сирдарё 1.1–1.2, Сурхондарё 1.5, Тошкент вилояти 2.1) and
     * their letters confirmed the same pattern on the same shared workbook rows:
     * the LEFT task carries stale figures the letters do not back, the RIGHT one
     * carries the letter values. The old copy is hidden in EVERY region where its
     * authoritative pair exists with plans (per-region letters spot-checked in 5
     * regions; the row is shared, so the pattern is structural, not regional).
     */
    public const DUPLICATE_PAIRS = [
        '13'  => '272',
        '15'  => '287',
        '162' => '287',
        '201' => '324',
    ];

    /**
     * Apply every fix. Idempotent — safe after each import and on re-runs.
     *
     * @return array{text: int, units: int, values: int} changed-row counts
     */
    public static function apply(): array
    {
        $counts = ['text' => 0, 'units' => 0, 'values' => 0];

        DB::transaction(function () use (&$counts) {
            foreach (self::TEXT_REPLACEMENTS as $from => $to) {
                foreach (self::TASK_TEXT_COLUMNS as $col) {
                    $counts['text'] += DB::update(
                        "update tasks set {$col} = replace({$col}, ?, ?) where {$col} like ?",
                        [$from, $to, '%' . $from . '%']
                    );
                }
                $counts['text'] += DB::update(
                    'update task_progress set metric_label = replace(metric_label, ?, ?) where metric_label like ?',
                    [$from, $to, '%' . $from . '%']
                );
            }

            foreach (self::UNIT_MAP as $from => $to) {
                $counts['units'] += DB::update('update task_progress set unit = ? where unit = ?', [$to, $from]);
                $counts['units'] += DB::update('update tasks set headline_unit = ? where headline_unit = ?', [$to, $from]);
            }

            $counts['values'] += self::crossRegionValueFixes();
            $counts['values'] += self::andijanValueFixes();
            $counts['values'] += self::samarqandValueFixes();
            $counts['values'] += self::surxondaryoValueFixes();
            $counts['values'] += self::qashqadaryoValueFixes();
            $counts['values'] += self::fargonaValueFixes();
            $counts['values'] += self::applySuppressions();
        });

        return $counts;
    }

    /**
     * Defect classes confirmed in one region's review and letter that the shared
     * workbook rows carry into EVERY region (task_number is the same row across
     * all 14 region blocks).
     */
    private static function crossRegionValueFixes(): int
    {
        $changed = 0;

        // Task №121: the annual figure labeled «Бюджет даромади» is the TAX
        // receipts commitment in every letter checked (Андижон п.4: 5 299 =
        // солиқ тушумлари; Сирдарё п.4: 2 422,8 = солиқ тушумлари).
        $changed += DB::update(
            "update task_progress set metric_label = 'Солиқ тушумлари миқдори (йиллик)'
             from tasks t
             where task_progress.task_id = t.id and t.task_number = '121'
               and task_progress.metric_label = 'Бюджет даромади миқдори (йиллик)'"
        );

        // Task №272: the plan-less, actual-less lead line duplicates the real
        // indicators below it (Самарқанд 2.5, Сирдарё 1.2) — drop it everywhere.
        $changed += DB::delete(
            "delete from task_progress
             using tasks t
             where task_progress.task_id = t.id and t.task_number = '272'
               and task_progress.plan_value is null and task_progress.actual_value is null
               and task_progress.metric_label = 'Манзилли ишланадиган йирик корхоналар'"
        );

        // Task №331: same plan-less lead-line pattern (Сурхондарё 1.5).
        $changed += DB::delete(
            "delete from task_progress
             using tasks t
             where task_progress.task_id = t.id and t.task_number = '331'
               and task_progress.plan_value is null and task_progress.actual_value is null
               and task_progress.metric_label = 'Шундан, кичик бизнесга'"
        );

        // Tasks №10 and №210 count TERRITORIES (their Батафсил lists the executor
        // districts), but the file labels the plan «фоиз» (Андижон 2.1, Фарғона
        // 1–2: «3 фоиз эмас, 3 та туман»).
        foreach (['10', '210'] as $number) {
            $changed += DB::update(
                "update task_progress set unit = 'та ҳудуд'
                 from tasks t
                 where task_progress.task_id = t.id and t.task_number = ? and task_progress.unit = 'фоиз'", [$number]
            );
            $changed += DB::update(
                "update tasks set headline_unit = 'та ҳудуд' where task_number = ? and headline_unit = 'фоиз'", [$number]
            );
        }

        // Task №285 tracks ТТХИ (direct foreign investment) but sits under
        // «Бюджет инвестициялари» via its section numeral (Фарғона review).
        $changed += DB::update(
            "update tasks set module_code = 'foreign_invest'
             where task_number = '285' and module_code = 'budget_invest' and title like '%ТТХИ%'"
        );

        // Task №379 is the agriculture growth driver — a Макро иқтисодиёт task,
        // not «Инфляция» (Фарғона review: 87-топшириқ).
        $changed += DB::update(
            "update tasks set module_code = 'macro'
             where task_number = '379' and module_code = 'inflation' and title like 'Қишлоқ хўжалиги%'"
        );

        return $changed;
    }

    /**
     * Самарқанд (1718) value fixes from the region's review doc, verified against
     * the guarantee letter of 20.07.2026. Known-bad → known-good only.
     */
    private static function samarqandValueFixes(): int
    {
        $changed = 0;

        $taskId = fn (string $number) => DB::selectOne(
            'select id from tasks where region_code = 1718 and task_number = ?', [$number]
        )?->id;

        // №66: apartments in year-end housing — letter п.14 says 12 000, not 12 025.
        if (($id = $taskId('66')) !== null) {
            $changed += DB::update(
                'update task_progress set plan_value = 12000
                 where task_id = ? and plan_value = 12025 and metric_label like ?', [$id, '%хонадонлар сони%']
            );
        }

        // №200: year-end unemployment ceiling — letter п.3 says 3,2%, not 4,2%.
        if (($id = $taskId('200')) !== null) {
            $changed += DB::update(
                'update task_progress set plan_value = 3.2 where task_id = ? and plan_value = 4.2', [$id]
            );
            $changed += DB::update(
                'update tasks set headline_plan = 3.2 where id = ? and headline_plan = 4.2', [$id]
            );
        }

        // №272: the plan-less lead line duplicates the two real indicators
        // (420 млрд / 21 та, letter п.2) — remove it.
        if (($id = $taskId('272')) !== null) {
            $changed += DB::delete(
                "delete from task_progress where task_id = ? and plan_value is null and actual_value is null
                   and metric_label = 'Манзилли ишланадиган йирик корхоналар'", [$id]
            );
        }

        // №287: the two ТТХИ lines repeat task №<89 board> («Ургут» ЭИЗ, letter:
        // 356,6 млн / 18 та / 1 000) — keep them only there.
        if (($id = $taskId('287')) !== null) {
            $changed += DB::delete(
                "delete from task_progress where task_id = ? and metric_label like 'ТТХИ%'", [$id]
            );
        }

        // №340: the letter's «640 нафар реестрдаги фуқаро» commitment is missing
        // as an indicator — add it once.
        if (($id = $taskId('340')) !== null) {
            $missing = DB::selectOne(
                "select count(*) c from task_progress where task_id = ? and report_period = '2026-H1'
                   and metric_label like '%реестридаги%фуқаролар сони%'", [$id]
            )->c === 0;
            if ($missing) {
                $src = DB::selectOne(
                    "select * from task_progress where task_id = ? and report_period = '2026-H1' and line_no = 0", [$id]
                );
                if ($src !== null) {
                    $next = (int) DB::selectOne(
                        "select coalesce(max(line_no), -1) + 1 m from task_progress where task_id = ? and report_period = '2026-H1'", [$id]
                    )->m;
                    DB::insert(
                        'insert into task_progress (task_id, line_no, metric_label, plan_value, unit, report_period, period_type, reported_at, import_run_id, created_at, updated_at)
                         values (?, ?, ?, 640, ?, ?, ?, ?, ?, now(), now())',
                        [$id, $next, 'Камбағаллик реестридаги ссуда ва субсидия олувчи фуқаролар сони', 'нафар', $src->report_period, $src->period_type, $src->reported_at, $src->import_run_id]
                    );
                    $changed++;
                }
            }
        }

        // KPI dashboard facts (indicator_facts): the H1 industry card shows the
        // PLAN growth as the actual (letter п.2: actual 109,8%, plan 108,1), and
        // the annual ЯҲМ target growth is 110,0% (letter II.1), not 108,9.
        $changed += DB::update(
            "update indicator_facts set growth_pct = 109.8, plan_growth_pct = 108.1
             where region_code = 1718 and district_code is null and indicator_code = 'industry'
               and period = 'h1' and growth_pct between 108.11 and 108.12"
        );
        $changed += DB::update(
            "update indicator_facts set growth_pct = 110.0
             where region_code = 1718 and district_code is null and indicator_code = 'grp'
               and period = 'year' and growth_pct between 108.88 and 108.89"
        );

        return $changed;
    }

    /**
     * Сурхондарё (1722) value fixes from the region's review doc, verified against
     * the guarantee letter of 2026. Known-bad → known-good only.
     */
    private static function surxondaryoValueFixes(): int
    {
        $changed = 0;

        $taskId = fn (string $number) => DB::selectOne(
            'select id from tasks where region_code = 1722 and task_number = ?', [$number]
        )?->id;

        // №66: year-end apartments — the letter says «10 минг хонадонлар», the
        // file still carries the older 8 516 target.
        if (($id = $taskId('66')) !== null) {
            $changed += DB::update(
                'update task_progress set plan_value = 10000
                 where task_id = ? and plan_value = 8516 and metric_label like ?', [$id, '%хонадон%']
            );
        }

        // №93: fodder-crop areas are «минг гектар» (15,9 + 9,0 = 24,9 минг га
        // per the letter), the file carries plain «гектар».
        if (($id = $taskId('93')) !== null) {
            $changed += DB::update(
                "update task_progress set unit = 'минг гектар' where task_id = ? and unit = 'гектар'", [$id]
            );
            $changed += DB::update(
                "update tasks set headline_unit = 'минг гектар' where id = ? and headline_unit = 'гектар'", [$id]
            );
        }

        // №412: the letter's construction list includes «1 та спортзал» and
        // «1 та олий таълим муассасаси ётоқхонаси» — both missing as indicators.
        if (($id = $taskId('412')) !== null) {
            foreach (['Спортзал', 'Олий таълим муассасаси ётоқхонаси'] as $label) {
                $missing = DB::selectOne(
                    "select count(*) c from task_progress where task_id = ? and report_period = '2026-H1' and metric_label = ?",
                    [$id, $label]
                )->c === 0;
                if (! $missing) {
                    continue;
                }
                $src = DB::selectOne(
                    "select * from task_progress where task_id = ? and report_period = '2026-H1' and line_no = 0", [$id]
                );
                if ($src === null) {
                    continue;
                }
                $next = (int) DB::selectOne(
                    "select coalesce(max(line_no), -1) + 1 m from task_progress where task_id = ? and report_period = '2026-H1'", [$id]
                )->m;
                DB::insert(
                    'insert into task_progress (task_id, line_no, metric_label, plan_value, unit, report_period, period_type, reported_at, import_run_id, created_at, updated_at)
                     values (?, ?, ?, 1, ?, ?, ?, ?, ?, now(), now())',
                    [$id, $next, $label, 'та', $src->report_period, $src->period_type, $src->reported_at, $src->import_run_id]
                );
                $changed++;
            }
        }

        // №295: the plan-less, actual-less lead line (its yearly local-products
        // target is not set for this region) hides the real 272,9/351,6 quarters.
        if (($id = $taskId('295')) !== null) {
            $changed += DB::delete(
                "delete from task_progress where task_id = ? and plan_value is null and actual_value is null and line_no = 0
                   and metric_label like 'Шундан, маҳсулотлар%'", [$id]
            );
        }

        // №208/№331 repeat each other (review 1.5): drop №208's programme-credit
        // line (2,1 трлн ≡ №331's 2 100 млрд) and №331's plan-less lead line.
        if (($id = $taskId('208')) !== null) {
            $changed += DB::delete(
                "delete from task_progress where task_id = ? and plan_value = 2.1 and unit = 'трлн сўм'
                   and metric_label like 'Тадбиркорлик дастурлари%'", [$id]
            );
        }
        if (($id = $taskId('331')) !== null) {
            $changed += DB::delete(
                "delete from task_progress where task_id = ? and plan_value is null and actual_value is null
                   and metric_label = 'Шундан, кичик бизнесга'", [$id]
            );
        }

        return $changed;
    }

    /**
     * Қашқадарё (1710) value fixes: the annual apartments plan on task №66 is the
     * REMAINING count (letter п.4: «режадаги қолган 6 437 та хонадон»), the
     * review sets the full-year total at 7 510.
     */
    private static function qashqadaryoValueFixes(): int
    {
        $changed = 0;

        $task = DB::selectOne("select id from tasks where region_code = 1710 and task_number = '66'");
        if ($task !== null) {
            $changed += DB::update(
                'update task_progress set plan_value = 7510
                 where task_id = ? and plan_value = 6437 and metric_label like ?', [$task->id, '%хонадон%']
            );
        }

        return $changed;
    }

    /**
     * Фарғона (1730) value fixes from the region's review doc. The review names
     * Бешариқ, Боғдод and Фурқат as the three districts task №210 covers.
     */
    private static function fargonaValueFixes(): int
    {
        $changed = 0;

        $task = DB::selectOne("select id from tasks where region_code = 1730 and task_number = '210'");
        if ($task === null) {
            return 0;
        }

        $districts = DB::select(
            "select id from districts where region_code = 1730
              and (name_full ilike '%Бешариқ%' or name_full ilike '%Боғдод%' or name_full ilike '%Фурқат%')"
        );
        foreach ($districts as $d) {
            $exists = DB::selectOne(
                'select count(*) c from task_districts where task_id = ? and district_id = ?', [$task->id, $d->id]
            )->c > 0;
            if (! $exists) {
                DB::insert(
                    'insert into task_districts (task_id, district_id) values (?, ?)', [$task->id, $d->id]
                );
                $changed++;
            }
        }

        return $changed;
    }

    /** Flag review-suppressed duplicates hidden; un-hide anything no longer listed. */
    private static function applySuppressions(): int
    {
        $ids = [];

        foreach (self::SUPPRESSED as $region => $numbers) {
            foreach (DB::select(
                'select id from tasks where region_code = ? and task_number = any(?)',
                [$region, '{' . implode(',', $numbers) . '}']
            ) as $r) {
                $ids[] = $r->id;
            }
        }

        // Old duplicates: hidden only where their authoritative pair exists with plans.
        foreach (self::DUPLICATE_PAIRS as $old => $auth) {
            foreach (DB::select(
                'select o.id from tasks o
                 where o.task_number = ?
                   and exists (
                     select 1 from tasks a
                     join task_progress ap on ap.task_id = a.id and ap.plan_value is not null
                     where a.region_code = o.region_code and a.task_number = ?
                   )', [$old, $auth]
            ) as $r) {
                $ids[] = $r->id;
            }
        }

        $ids = array_values(array_unique($ids));
        $changed = DB::table('tasks')->whereIn('id', $ids)->where('hidden', false)->update(['hidden' => true]);
        $changed += DB::table('tasks')->whereNotIn('id', $ids)->where('hidden', true)->update(['hidden' => false]);

        return $changed;
    }

    /**
     * Andijan (1703) value fixes from the region's review doc, each verified
     * against the guarantee letter of 20.07.2026. Known-bad → known-good only.
     */
    private static function andijanValueFixes(): int
    {
        $changed = 0;

        $taskId = fn (string $number) => DB::selectOne(
            'select id from tasks where region_code = 1703 and task_number = ?', [$number]
        )?->id;

        // №10 «Паст ўсиш кузатилган...»: the plan counts territories, not percent.
        if (($id = $taskId('10')) !== null) {
            $changed += DB::update(
                "update task_progress set unit = 'та ҳудуд' where task_id = ? and unit = 'фоиз'", [$id]
            );
            $changed += DB::update(
                "update tasks set headline_unit = 'та ҳудуд' where id = ? and headline_unit = 'фоиз'", [$id]
            );
        }

        // №295 «...экспорт ҳажмини таъминлаш»: letter plans to reach 1.0 bln USD
        // (489 H1 + 230 Q3 + 281 Q4), the file still carries the older 967 target.
        if (($id = $taskId('295')) !== null) {
            $changed += DB::update(
                'update task_progress set plan_value = 1000 where task_id = ? and line_no = 0 and plan_value = 967', [$id]
            );
            $changed += DB::update(
                'update tasks set headline_plan = 1000 where id = ? and headline_plan = 967', [$id]
            );
        }

        // №121 «Йил якуни билан бюджет даромадлари прогнози...»: per the letter the
        // 5 299 billion figure is TAX receipts; budget revenues are 5 889 (5299+590).
        if (($id = $taskId('121')) !== null) {
            $changed += DB::update(
                "update task_progress set metric_label = 'Солиқ тушумлари миқдори (йиллик)'
                 where task_id = ? and line_no = 0 and report_period = '2026-H1'
                   and metric_label = 'Бюджет даромади миқдори (йиллик)'", [$id]
            );

            $missing = DB::selectOne(
                "select count(*) c from task_progress where task_id = ? and report_period = '2026-H1' and line_no = 4", [$id]
            )->c === 0;
            if ($missing) {
                $src = DB::selectOne(
                    "select * from task_progress where task_id = ? and report_period = '2026-H1' and line_no = 0", [$id]
                );
                if ($src !== null) {
                    DB::insert(
                        'insert into task_progress (task_id, line_no, metric_label, plan_value, unit, report_period, period_type, reported_at, import_run_id, created_at, updated_at)
                         values (?, 4, ?, 5889, ?, ?, ?, ?, ?, now(), now())',
                        [$id, 'Бюджет даромадлари миқдори (йиллик)', 'млрд сўм', $src->report_period, $src->period_type, $src->reported_at, $src->import_run_id]
                    );
                    $changed++;
                }
            }
        }

        // №110 «Бюджет даромадлари... 2-чорак прогноз»: the reported actual was the
        // TOTAL quarterly receipts (1 420 bln vs a 117.4 additional-revenue plan,
        // 1210%); the real additional-revenue actual is pending from the partner.
        if (($id = $taskId('110')) !== null) {
            $changed += DB::update(
                'update task_progress set actual_value = null, pct_of_plan = null
                 where task_id = ? and line_no = 0 and actual_value between 1420 and 1421', [$id]
            );
            $changed += DB::update(
                'update tasks set headline_actual = null, headline_pct = null
                 where id = ? and headline_actual between 1420 and 1421', [$id]
            );
        }

        // №111 «Яширин иқтисодиёт...»: line 0 is the aggregate of the five sector
        // lines (their plans sum to 117) but the file carries no plan for it.
        if (($id = $taskId('111')) !== null) {
            $changed += DB::update(
                "update task_progress set plan_value = 117
                 where task_id = ? and line_no = 0 and report_period = '2026-H1' and plan_value is null", [$id]
            );
        }

        return $changed;
    }
}
