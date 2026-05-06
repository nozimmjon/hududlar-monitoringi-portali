from __future__ import annotations

import json
import re
import zipfile
import xml.etree.ElementTree as ET
from collections import Counter
from pathlib import Path

import openpyxl


ROOT = Path(__file__).resolve().parents[1]
DATA_JSON = ROOT / "platform prototypes" / "andijon_full_pilot_assets" / "andijon_full_pilot_data.json"
OUT_HTML = ROOT / "platform prototypes" / "andijon_integrated_platform_v7.html"
MACRO_XLSX = ROOT / "data_source" / "Кафолат хатлар имзога" / "2. Андижон" / "1.1-1.5-жадваллар (макро).xlsx"
KAFOLAT_TASK_DOCX = ROOT / "data_source" / "Кафолат хатлар имзога" / "2. Андижон" / "1. Кафолат хати (Андижон).docx"
KAFOLAT_ACTION_PLAN_DOCX = (
    ROOT
    / "data_source"
    / "Кафолат хатлар имзога"
    / "2. Андижон"
    / "00_Чора_тадбир_Андижон.docx"
)
ACTION_PLAN_AUDIT_JSON = ROOT / "platform prototypes" / "andijon_action_plan_audit.json"
KPI_COMPARE_AUDIT_JSON = ROOT / "platform prototypes" / "andijon_kpi_source_compare_audit.json"


SECTOR_BY_KPI = {
    "grp": "Макро иқтисодиёт",
    "industry": "Макро иқтисодиёт",
    "localization": "Макро иқтисодиёт",
    "energy_electricity": "Макро иқтисодиёт",
    "energy_gas": "Макро иқтисодиёт",
    "services": "Макро иқтисодиёт",
    "agriculture": "Макро иқтисодиёт",
    "construction": "Макро иқтисодиёт",
    "inflation": "Макро иқтисодиёт",
    "budget": "Бюджет",
    "budget_investment": "Бюджет",
    "investment": "Хорижий инвестиция",
    "export": "Экспорт",
    "unemployment": "Бандлик ва камбағаллик",
    "small_business_share": "Бандлик ва камбағаллик",
    "jobs": "Бандлик ва камбағаллик",
    "legalization": "Бандлик ва камбағаллик",
    "mfy_clear": "Бандлик ва камбағаллик",
    "microprojects": "Бандлик ва камбағаллик",
    "poverty": "Бандлик ва камбағаллик",
}

MODULE_DEFS = {
    "macro": {
        "id": "macro",
        "label": "1. Макроиқтисодиёт",
        "short": "Макро",
        "source": "1.1-1.5-жадваллар (макро).xlsx",
        "sheets": ["1.1. ЯҲМ", "1.2. Саноат", "1.3. Ҳудудий саноат", "1.4. ҚХ", "1.5. Бозор хизматлари"],
    },
    "inflation": {
        "id": "inflation",
        "label": "2. Инфляция ва озиқ-овқат",
        "short": "Инфляция",
        "source": "2.1-2.2-жадваллар (инфляция).xlsx",
        "sheets": ["1.1. Баланс", "1.2. Омборлар"],
    },
    "budget": {
        "id": "budget",
        "label": "3. Бюджет тушумлари",
        "short": "Бюджет",
        "source": "3-жадвал (бюджет).xlsx",
        "sheets": ["тушум"],
    },
    "budget_investment": {
        "id": "budget_investment",
        "label": "4. Бюджет инвестициялари",
        "short": "Бюджет инвест",
        "source": "4.1-жадвал (бюджет инвестка).xlsx",
        "sheets": ["2.Анд"],
    },
    "investment": {
        "id": "investment",
        "label": "5. Хорижий инвестиция",
        "short": "Инвестиция",
        "source": "4.2-жадвал (инвестициялар).xlsx",
        "sheets": ["4,2-хорижий инв"],
    },
    "export": {
        "id": "export",
        "label": "6. Экспорт",
        "short": "Экспорт",
        "source": "5.1-5.2-жадваллар (экспорт).xlsx",
        "sheets": ["5-жадвал", "02_Анд", "Корхона сони"],
    },
    "employment": {
        "id": "employment",
        "label": "7. Бандлик ва камбағаллик",
        "short": "Бандлик",
        "source": "6-жадвал (бандлик ва камбағаллик даражаси).xlsx",
        "sheets": ["6. Камбағаллик", "Инфо"],
    },
}

KPI_TO_MODULE = {
    "grp": "macro",
    "industry": "macro",
    "localization": "macro",
    "energy_electricity": "macro",
    "energy_gas": "macro",
    "services": "macro",
    "agriculture": "macro",
    "construction": "macro",
    "inflation": "inflation",
    "budget": "budget",
    "budget_investment": "budget_investment",
    "investment": "investment",
    "export": "export",
    "unemployment": "employment",
    "small_business_share": "employment",
    "jobs": "employment",
    "legalization": "employment",
    "mfy_clear": "employment",
    "microprojects": "employment",
    "poverty": "employment",
}


def has_target_value(text: str) -> bool:
    low = text.casefold()
    return bool(re.search(r"\d+[\d\s,.]*\s*(фоиз|%|трлн|млрд|млн|минг|та)", low))


def has_action_word(text: str) -> bool:
    low = text.casefold()
    action_words = [
        "ишга тушир",
        "ташкил эт",
        "ташкиллаштир",
        "қайта тиклаш",
        "қайта йўлга қўйиш",
        "кўмаклашиш",
        "манзилли ишлаш",
        "жойлаштир",
        "ажрат",
        "ўқит",
        "легаллаштир",
        "ўтказ",
        "олиб келиш",
        "етказиб бериш",
        "экиш",
        "ишлаб чиқ",
        "амалга ошир",
        "чоралар кўриш",
        "сақлаб",
        "сақлаш",
        "оширмаслик",
        "пасайтириш",
        "қайта кўриб чиқиш",
        "эришиш",
        "назорат",
        "мониторинг",
        "кредит",
        "субсид",
        "экспортчи",
        "тадбиркор",
        "фойдаланишга топшир",
    ]
    return any(word in low for word in action_words)


def is_platform_kpi_target(task: dict) -> bool:
    text = str(task.get("title") or "")
    low = text.casefold()
    if "ялпи ҳудудий маҳсулот" in low and "кичик тадбиркорлик" not in low:
        return True
    if "саноат маҳсулот" in low and "ўсиш суръат" in low:
        return True
    if "бозор хизматлари ҳажм" in low:
        return True
    if "қишлоқ хўжалиги маҳсулотлари ҳажм" in low:
        return True
    if "қурилиш ишлари ҳажм" in low:
        return True
    if "инфляция даражас" in low:
        return True
    if "бюджет даромадлари прогнози" in low or "йиллик прогноз" in low:
        return True
    if "2-чорак учун" in low and "даромадларга эришиш" in low:
        return True
    if "инвестициялар ва кредитларни жалб қилиш" in low and "лойиҳа" not in low:
        return True
    if re.search(r"^\s*\d[\d\s,.]*\s*млн\s+доллар.*экспорт\s+таъминлаш", low):
        return True
    if "ишсизлик даражас" in low:
        return True
    if "камбағаллик даражас" in low:
        return True
    return False


def classify_kafolat_row(task: dict) -> tuple[str, str, str]:
    text = str(task.get("title") or "")
    low = text.casefold()
    districts = task.get("districts") or []
    instrument_words = [
        "корхона",
        "лойиҳа",
        "экспортчи",
        "омбор",
        "ярмарка",
        "кўча",
        "объект",
        "хонадон",
        "уйлар",
        "субсид",
        "кредит",
        "ўқит",
        "легаллаштир",
        "ишга жойлаштир",
        "бизнес-миссия",
        "кўргазма",
        "инфратузилма",
        "хизмат кўрсатиш шаҳоб",
    ]
    has_instrument = any(word in low for word in instrument_words)
    if is_platform_kpi_target(task):
        return (
            "KPI мақсад",
            "KPI экрани",
            "Платформада асосий KPI сифатида киритилган индикатор. Топшириқлар реестридан чиқарилади.",
        )
    if districts and has_target_value(text) and not (
        has_instrument and any(word in low for word in ["ишга тушир", "ташкил эт", "корхона", "экспортчи", "лойиҳа"])
    ):
        return (
            "Туман мақсадли кўрсаткичи",
            "Туманлар экрани",
            "Туман/шаҳар кесимидаги режа. Топшириқлар сонига қўшилмайди.",
        )
    if has_action_word(text) or has_target_value(text):
        return (
            "Ижро топшириғи",
            "Топшириқлар / Ижро мониторинги",
            "KPIга эришиш учун амалий ҳаракат бор. Топшириқ сифатида қолади.",
        )
    return (
        "Маълумот/асос",
        "Керак бўлса маълумот сифатида",
        "Мониторинг объекти эмас. Асословчи ёки тушунтирувчи матн сифатида қаралади.",
    )


def assign_kafolat_platform_ids(rows: list[dict]) -> list[dict]:
    counters = {
        "KPI мақсад": 0,
        "Туман мақсадли кўрсаткичи": 0,
        "Ижро топшириғи": 0,
        "Маълумот/асос": 0,
    }
    prefixes = {
        "KPI мақсад": "KPI",
        "Туман мақсадли кўрсаткичи": "D",
        "Ижро топшириғи": "T",
        "Маълумот/асос": "INFO",
    }
    classified: list[dict] = []
    for row in rows:
        kind, surface, note = classify_kafolat_row(row)
        counters[kind] += 1
        prefix = prefixes[kind]
        platform_id = f"{prefix}-{counters[kind]:03d}" if prefix not in {"KPI", "D"} else f"{prefix}-{counters[kind]:02d}"
        item = {
            **row,
            "sourceId": row["id"],
            "platformId": platform_id,
            "classification": kind,
            "platformSurface": surface,
            "classificationNote": note,
        }
        module = MODULE_DEFS.get(KPI_TO_MODULE.get(str(row.get("kpi") or ""), "macro"), MODULE_DEFS["macro"])
        item["module"] = module["id"]
        item["moduleLabel"] = module["label"]
        item["moduleShort"] = module["short"]
        item["moduleSource"] = module["source"]
        item["moduleSheets"] = module["sheets"]
        if kind == "Ижро топшириғи":
            item["id"] = platform_id
        classified.append(item)
    return classified


def norm_name(value: object) -> str:
    return " ".join(str(value or "").split()).casefold()


def num(value: object) -> float | int | None:
    if value is None or value == "":
        return None
    if isinstance(value, (int, float)):
        return int(value) if float(value).is_integer() else float(value)
    try:
        parsed = float(str(value).replace(",", "."))
    except ValueError:
        return None
    return int(parsed) if parsed.is_integer() else parsed


def docx_paragraphs(path: Path) -> list[str]:
    if not path.exists():
        return []
    ns = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
    with zipfile.ZipFile(path) as zf:
        root = ET.fromstring(zf.read("word/document.xml"))
    paragraphs: list[str] = []
    for para in root.findall(".//w:body/w:p", ns):
        text = "".join(t.text or "" for t in para.findall(".//w:t", ns)).strip()
        text = " ".join(text.split())
        if text:
            paragraphs.append(text)
    return paragraphs


def clean_docx_text(text: str) -> str:
    return " ".join(str(text or "").replace("\xa0", " ").split()).strip()


def docx_table_rows(path: Path, table_index: int = 0) -> list[list[str]]:
    """Read a DOCX table without requiring python-docx at runtime."""
    if not path.exists():
        return []
    ns = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
    val_attr = f"{{{ns['w']}}}val"
    with zipfile.ZipFile(path) as zf:
        root = ET.fromstring(zf.read("word/document.xml"))
    tables = root.findall(".//w:body/w:tbl", ns)
    if table_index >= len(tables):
        return []
    rows: list[list[str]] = []
    for tr in tables[table_index].findall("w:tr", ns):
        cells: list[str] = []
        for tc in tr.findall("w:tc", ns):
            parts: list[str] = []
            for para in tc.findall(".//w:p", ns):
                text = "".join(t.text or "" for t in para.findall(".//w:t", ns))
                text = clean_docx_text(text)
                if text:
                    parts.append(text)
            cell_text = clean_docx_text(" ".join(parts))
            grid_span = tc.find("./w:tcPr/w:gridSpan", ns)
            span = 1
            if grid_span is not None:
                try:
                    span = max(1, int(grid_span.attrib.get(val_attr, "1")))
                except ValueError:
                    span = 1
            cells.extend([cell_text] * span)
        if any(cells):
            rows.append(cells)
    return rows


def action_plan_row_number(value: str) -> int | None:
    match = re.fullmatch(r"\s*(\d+)\.?\s*", clean_docx_text(value))
    return int(match.group(1)) if match else None


def action_plan_is_section(cells: list[str]) -> bool:
    first = clean_docx_text(cells[0] if cells else "")
    if not first or first == "№":
        return False
    if action_plan_row_number(first) is not None:
        return False
    return True


def deadline_to_period(deadline: str) -> str:
    text = deadline.casefold()
    if "ii чорак" in text or "i ярим" in text or "январь-июнь" in text or "май" in text or "июн" in text:
        return "h1"
    if "iii чорак" in text or "9 ой" in text:
        return "m9"
    if "iv чорак" in text or "vi чорак" in text or "йил яку" in text or "давомида" in text:
        return "year"
    if "i чорак" in text:
        return "q1"
    return "year"


def section_count(section: str) -> int | None:
    match = re.search(r"(\d+)\s*та\s+топшириқ", section)
    return int(match.group(1)) if match else None


def task_kpi_from_section(section: str, text: str) -> str:
    s = section.casefold()
    t = text.casefold()

    if "бандлиг" in s or "ишсизлик" in s or "7.1" in s:
        if (
            ("кичик тадбиркорлик" in t and "улуш" in t)
            or "кичик ва ўрта бизнес" in t
            or "тадбиркорлик субъект" in t
            or "субъектларининг фаолиятини тиклаш" in t
        ):
            return "small_business_share"
        return "unemployment"
    if "камбағаллик" in s or "7.2" in s:
        if "холи бўлган маҳаллалар сони" in t:
            return "mfy_clear"
        return "poverty"

    if "бозор хизмат" in s:
        return "services"
    if "қишлоқ хўжалиги" in s:
        return "agriculture"
    if "қурилиш" in s:
        return "construction"
    if "инфляция" in s:
        return "inflation"
    if "бюджет тушум" in s:
        return "budget"
    if "инвестицияларни ўзлаштириш" in s:
        return "budget_investment"
    if "хорижий инвестиция" in s:
        return "investment"
    if "экспорт" in s:
        return "export"

    # ЯҲМ is a final result KPI. Execution tasks from the macro section must
    # attach to the concrete driver that can be monitored.
    if "ялпи ҳудудий маҳсулот" in t:
        return "grp"
    if "маҳаллийлаштириш" in s or "маҳаллийлаштир" in t or "маҳаллий контент" in t or "давлат харид" in t:
        return "localization"
    if "табиий газ" in t or "куб метр" in t:
        return "energy_gas"
    if "энергия" in s or "энергия" in t or "электр" in t or "квт" in t:
        return "energy_electricity"
    if "корхона" in t or "ишлаб чиқариш" in t or "саноат" in t:
        return "industry"
    if "энергия" in s:
        return "energy_gas" if "газ" in t else "energy_electricity"
    if "саноат" in s:
        return "industry"
    return "industry"


def district_names_in_text(text: str, districts: list[dict]) -> list[str]:
    low = text.casefold()
    found: list[str] = []
    stems: dict[str, int] = {}
    for district in districts:
        stem = re.sub(r"\s+(шаҳри|тумани)$", "", str(district.get("name") or ""), flags=re.IGNORECASE).casefold()
        stems[stem] = stems.get(stem, 0) + 1
    aliases = {
        "Андижон шаҳри": ["андижон шаҳар", "андижон шаҳри", "андижон шаҳарда"],
        "Андижон тумани": ["андижон тум.", "андижон тумани", "андижон туманида", "андижон тум"],
        "Хонобод шаҳри": ["хонобод шаҳар", "хонабод шаҳар", "хонобод шаҳри", "хонабод шаҳри", "хонобод шаҳарда", "хонабод шаҳарларида"],
    }
    for district in districts:
        name = str(district.get("name") or "")
        stem = re.sub(r"\s+(шаҳри|тумани)$", "", name, flags=re.IGNORECASE)
        stem_key = stem.casefold()
        ambiguous = stems.get(stem_key, 0) > 1
        variants = aliases.get(name, []) + [name.casefold()]
        if name.casefold().endswith("тумани"):
            variants += [
                stem_key + " тум",
                stem_key + " тум.",
                stem_key + " тумани",
                stem_key + " туманида",
            ]
        elif name.casefold().endswith("шаҳри"):
            variants += [
                stem_key + " шаҳар",
                stem_key + " шаҳарда",
                stem_key + " шаҳри",
                stem_key + " шаҳрида",
            ]
        if not ambiguous:
            variants += [
                stem_key + "да",
                stem_key + "даги",
            ]
        stem_pattern = rf"(^|[\s,;:()–-]){re.escape(stem.casefold())}($|[\s,.;:()–-]|да|даги|нинг|ни|га)"
        if any(v and v in low for v in variants):
            found.append(name)
            continue
        if ambiguous:
            continue
        if re.search(stem_pattern, low):
            found.append(name)
    return sorted(set(found))


def extract_kafolat_action_plan_rows(data: dict, path: Path = KAFOLAT_ACTION_PLAN_DOCX) -> list[dict]:
    """Extract the cleaned action-plan table: KPI rows and execution rows.

    This parser is intentionally separate from the legacy paragraph parser. It
    preserves the user's two-layer model: KPI and амалга ошириладиган ишлар.
    """
    raw_rows = docx_table_rows(path)
    if not raw_rows:
        return []

    records: list[dict] = []
    section = "Кафолат хати чора-тадбирлари"
    districts = data.get("districts", [])
    for cells in raw_rows:
        cells = [clean_docx_text(cell) for cell in cells]
        if not cells:
            continue
        if action_plan_is_section(cells):
            section = cells[0]
            continue
        if cells[0] == "№":
            continue
        row_no = action_plan_row_number(cells[0])
        if row_no is None:
            continue
        padded = cells + [""] * max(0, 5 - len(cells))
        title, deadline, owner, source_type = padded[1], padded[2], padded[3], padded[4]
        kpi = task_kpi_from_section(section, title)
        districts_from_title = district_names_in_text(title, districts)
        districts_from_executor = district_names_in_text(owner, districts)
        linked_districts = sorted(set(districts_from_title + districts_from_executor))
        is_kpi = source_type == "KPI"
        item = {
            "id": f"action-plan-{row_no:03d}",
            "sourceId": f"action-plan-{row_no:03d}",
            "sourceNo": row_no,
            "title": title,
            "sector": SECTOR_BY_KPI.get(kpi, "Макро иқтисодиёт"),
            "kpi": kpi,
            "period": deadline,
            "periodCode": deadline_to_period(deadline),
            "deadline": deadline,
            "owner": owner or "Андижон вилояти ҳокимлиги",
            "status": "grey",
            "source": path.name,
            "section": section,
            "sourceType": source_type,
            "displayLayer": "KPI" if is_kpi else "Амалга ошириладиган иш",
            "districts": linked_districts,
            "districtsFromTitle": districts_from_title,
            "districtsFromExecutor": districts_from_executor,
            "scope": "туман" if linked_districts else "вилоят",
            "group": "KPI" if is_kpi else "Амалга ошириладиган иш",
            "monitoringRow": True,
        }
        records.append(item)
    return records


def module_metadata_for_kpi(kpi: str) -> dict:
    module = MODULE_DEFS.get(KPI_TO_MODULE.get(str(kpi or ""), "macro"), MODULE_DEFS["macro"])
    return {
        "module": module["id"],
        "moduleLabel": module["label"],
        "moduleShort": module["short"],
        "moduleSource": module["source"],
        "moduleSheets": module["sheets"],
    }


def action_plan_aliases_for_district(name: str, all_districts: list[dict]) -> list[str]:
    stem = re.sub(r"\s+(шаҳри|тумани)$", "", str(name or ""), flags=re.IGNORECASE).casefold()
    stem_counts: dict[str, int] = {}
    for district in all_districts:
        district_stem = re.sub(
            r"\s+(шаҳри|тумани)$",
            "",
            str(district.get("name") or ""),
            flags=re.IGNORECASE,
        ).casefold()
        stem_counts[district_stem] = stem_counts.get(district_stem, 0) + 1
    aliases = [str(name or "").casefold()]
    if str(name or "").casefold().endswith("тумани"):
        aliases += [f"{stem} тумани", f"{stem} тум.", f"{stem} тум"]
    elif str(name or "").casefold().endswith("шаҳри"):
        aliases += [f"{stem} шаҳри", f"{stem} шаҳар"]
    if stem_counts.get(stem, 0) == 1:
        aliases.append(stem)
    return sorted(set(aliases), key=len, reverse=True)


def parse_action_plan_int(value: str | None) -> int | None:
    if not value:
        return None
    match = re.search(r"\d[\d\s]*", value)
    if not match:
        return None
    return int(match.group(0).replace(" ", ""))


def action_plan_breakdown_kind(task: dict) -> str | None:
    title = str(task.get("title") or "").casefold()
    if "субъект" in title and "фаолиятини тиклаш" in title:
        return "restored_subjects"
    if "камбағаллик ва ишсизликдан холи ҳудуд" in title:
        return "poverty_free_mfy"
    return None


def action_plan_district_breakdowns(task: dict, districts: list[dict]) -> list[dict]:
    """Create internal district rows only where the source gives real district targets."""
    kind = action_plan_breakdown_kind(task)
    if not kind:
        return []
    title = str(task.get("title") or "")
    low = title.casefold()
    rows: list[dict] = []
    for district in districts:
        name = str(district.get("name") or "")
        for alias in action_plan_aliases_for_district(name, districts):
            pattern = (
                rf"{re.escape(alias)}(?:да|даги)?\s*"
                rf"(?:[–—-]\s*)?(\d[\d\s]*)\s*та"
                rf"(?:\s*\((?:йил якуни билан\s*)?(\d[\d\s]*)\s*та\))?"
            )
            match = re.search(pattern, low)
            if not match:
                continue
            h1_or_main = parse_action_plan_int(match.group(1))
            year_value = parse_action_plan_int(match.group(2))
            if h1_or_main is None:
                break
            if kind == "restored_subjects":
                rows.append({
                    "title": f"Фаолияти тикланадиган субъектлар — {name}",
                    "district": name,
                    "value": h1_or_main,
                    "yearValue": h1_or_main,
                    "unit": "та субъект",
                    "note": "800 та субъектлар фаолиятини тиклаш бўйича туман кесими.",
                })
            else:
                if year_value is None and task.get("periodCode") == "year":
                    year_value = h1_or_main
                rows.append({
                    "title": f"Камбағаллик ва ишсизликдан холи ҳудудлар — {name}",
                    "district": name,
                    "value": h1_or_main,
                    "yearValue": year_value,
                    "unit": "та маҳалла",
                    "note": "Оғир туманларда маҳаллаларни камбағаллик ва ишсизликдан холи ҳудудга айлантириш.",
                })
            break
    return rows


def assign_action_plan_platform_records(rows: list[dict], data: dict) -> dict[str, list[dict]]:
    counters = {"KPI": 0, "Чора-тадбирлар": 0}
    all_records: list[dict] = []
    kpi_targets: list[dict] = []
    execution_tasks: list[dict] = []

    for row in rows:
        source_type = str(row.get("sourceType") or "")
        if source_type not in counters:
            continue
        counters[source_type] += 1
        is_kpi = source_type == "KPI"
        platform_id = f"KPI-{counters[source_type]:02d}" if is_kpi else f"T-{counters[source_type]:03d}"
        item = {
            **row,
            "id": platform_id,
            "sourceId": row.get("sourceId") or row.get("id"),
            "platformId": platform_id,
            "classification": "KPI мақсад" if is_kpi else "Чора-тадбир",
            "platformSurface": "KPI мониторинги" if is_kpi else "Топшириқлар / Ижро мониторинги",
            "classificationNote": (
                "Платформа KPIси. Топшириқлар сонига қўшилмайди."
                if is_kpi
                else "KPIга эришиш учун амалга ошириладиган иш. Топшириқлар реестрига киради."
            ),
            "displayLayer": "KPI" if is_kpi else "Чора-тадбир",
            "group": "KPI" if is_kpi else "Чора-тадбир",
            "sourceReference": f"{row.get('source')} · {row.get('sourceNo')}-қатор",
        }
        item.update(module_metadata_for_kpi(str(item.get("kpi") or "")))
        all_records.append(item)
        if is_kpi:
            kpi_targets.append(item)
        else:
            execution_tasks.append(item)

    district_targets: list[dict] = []
    district_counter = 0
    districts = data.get("districts", [])
    for task in execution_tasks:
        for breakdown in action_plan_district_breakdowns(task, districts):
            district_counter += 1
            platform_id = f"D-{district_counter:02d}"
            district_item = {
                **task,
                **breakdown,
                "id": platform_id,
                "platformId": platform_id,
                "parentTaskId": task["id"],
                "sourceId": task["sourceId"],
                "classification": "Туман кесими",
                "platformSurface": "Туманлар мониторинги",
                "classificationNote": "Чора-тадбир ичидаги туман/шаҳар кесими. Топшириқлар сонига қўшилмайди.",
                "displayLayer": "Туман кесими",
                "districts": [breakdown["district"]],
                "scope": "туман",
                "status": "grey",
            }
            district_targets.append(district_item)

    return {
        "rows": all_records,
        "kpi_targets": kpi_targets,
        "execution_tasks": execution_tasks,
        "district_targets": district_targets,
        "info_rows": [],
    }


def action_plan_audit(data: dict, path: Path = KAFOLAT_ACTION_PLAN_DOCX) -> dict:
    rows = extract_kafolat_action_plan_rows(data, path)
    nums = [int(row["sourceNo"]) for row in rows]
    type_counts: dict[str, int] = {}
    section_counts: dict[str, dict[str, int]] = {}
    for row in rows:
        source_type = str(row.get("sourceType") or "")
        type_counts[source_type] = type_counts.get(source_type, 0) + 1
        section = str(row.get("section") or "")
        section_counts.setdefault(section, {})
        section_counts[section][source_type] = section_counts[section].get(source_type, 0) + 1
    missing_numbers = [number for number in range(min(nums), max(nums) + 1) if number not in set(nums)] if nums else []
    duplicate_numbers = sorted({number for number in nums if nums.count(number) > 1})
    allowed_types = {"KPI", "Чора-тадбирлар"}

    district_rows = [row for row in rows if row.get("districts")]
    child_breakdown_rows = [
        row for row in rows
        if row.get("districtsFromTitle") and re.search(r"\d+[\d\s,.]*\s*(та|млрд|млн|минг|%)", str(row.get("title") or ""))
    ]
    executor_title_mismatches = []
    for row in district_rows:
        from_title = set(row.get("districtsFromTitle") or [])
        from_executor = set(row.get("districtsFromExecutor") or [])
        extra = sorted(from_title - from_executor)
        if extra:
            executor_title_mismatches.append({
                "sourceNo": row["sourceNo"],
                "extraInTitle": extra,
                "districtsFromExecutor": row.get("districtsFromExecutor") or [],
            })

    long_task_rows = [
        {"sourceNo": row["sourceNo"], "length": len(str(row.get("title") or ""))}
        for row in rows
        if row.get("sourceType") == "Чора-тадбирлар" and len(str(row.get("title") or "")) > 550
    ]

    return {
        "sourceDoc": str(path),
        "rowCount": len(rows),
        "numberRange": [min(nums), max(nums)] if nums else [],
        "missingNumbers": missing_numbers,
        "duplicateNumbers": duplicate_numbers,
        "unknownTypes": sorted({str(row.get("sourceType") or "") for row in rows} - allowed_types),
        "emptyRequiredFields": [
            {"sourceNo": row["sourceNo"], "field": field}
            for row in rows
            for field in ["title", "deadline", "owner", "sourceType"]
            if not row.get(field)
        ],
        "typeCounts": type_counts,
        "sectionCounts": section_counts,
        "districtLinkedRows": [
            {
                "sourceNo": row["sourceNo"],
                "sourceType": row["sourceType"],
                "districts": row.get("districts") or [],
                "districtsFromTitle": row.get("districtsFromTitle") or [],
                "districtsFromExecutor": row.get("districtsFromExecutor") or [],
            }
            for row in district_rows
        ],
        "childBreakdownRows": [
            {
                "sourceNo": row["sourceNo"],
                "sourceType": row["sourceType"],
                "districtsFromTitle": row.get("districtsFromTitle") or [],
            }
            for row in child_breakdown_rows
        ],
        "executorTitleMismatches": executor_title_mismatches,
        "longTaskRows": long_task_rows,
    }


def audit_action_plan_cli() -> None:
    data = json.loads(DATA_JSON.read_text(encoding="utf-8"))
    audit = action_plan_audit(data)
    ACTION_PLAN_AUDIT_JSON.write_text(json.dumps(audit, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"source: {audit['sourceDoc']}")
    print(f"rows: {audit['rowCount']}")
    print(f"types: {audit['typeCounts']}")
    print(f"missing numbers: {audit['missingNumbers']}")
    print(f"duplicate numbers: {audit['duplicateNumbers']}")
    print(f"unknown types: {audit['unknownTypes']}")
    print(f"empty required fields: {len(audit['emptyRequiredFields'])}")
    print(f"district-linked rows: {len(audit['districtLinkedRows'])}")
    print(f"child breakdown rows: {len(audit['childBreakdownRows'])}")
    print(f"audit json: {ACTION_PLAN_AUDIT_JSON}")


def parse_decimal(value: str) -> float | None:
    text = clean_docx_text(value).replace(",", ".")
    text = text.replace(" ", "")
    try:
        return float(text)
    except ValueError:
        return None


def parse_first_number(pattern: str, text: str) -> float | None:
    match = re.search(pattern, text, flags=re.IGNORECASE)
    return parse_decimal(match.group(1)) if match else None


def parse_uzs_bln(text: str) -> float | None:
    low = text.casefold()
    trln = parse_first_number(r"(\d[\d\s]*(?:[,.]\d+)?)\s*трлн", low)
    bln = parse_first_number(r"(\d[\d\s]*(?:[,.]\d+)?)\s*млрд", low)
    mln = parse_first_number(r"(\d[\d\s]*(?:[,.]\d+)?)\s*млн\s+сўм", low)
    if trln is not None:
        return trln * 1000 + (bln or 0)
    if bln is not None:
        return bln
    if mln is not None:
        return mln / 1000
    return None


def parse_usd_mln(text: str) -> float | None:
    low = text.casefold()
    bln = parse_first_number(r"(\d[\d\s]*(?:[,.]\d+)?)\s*млрд\s+доллар", low)
    mln = parse_first_number(r"(\d[\d\s]*(?:[,.]\d+)?)\s*млн\s+доллар", low)
    if bln is not None:
        return bln * 1000
    return mln


def parse_percent_values(text: str) -> list[float]:
    values: list[float] = []
    for raw in re.findall(r"(\d[\d\s]*(?:[,.]\d+)?)\s*(?:фоиз|%)", text, flags=re.IGNORECASE):
        value = parse_decimal(raw)
        if value is not None:
            values.append(value)
    return values


def growth_index_to_plus(value: float | None) -> float | None:
    if value is None:
        return None
    return value - 100 if value > 80 else value


def current_excel_kpi_values(data: dict) -> dict[str, dict[str, dict[str, object]]]:
    enrich_industry_drivers(data)
    regional = data.get("regional", {})
    current: dict[str, dict[str, dict[str, object]]] = {}

    macro_map = {
        "grp": 0,
        "industry": 1,
        "agriculture": 2,
        "construction": 3,
        "services": 4,
    }
    macro_rows = regional.get("macro", [])
    for kpi, index in macro_map.items():
        if index >= len(macro_rows):
            continue
        row = macro_rows[index]
        current[kpi] = {
            "h1": {
                "value_bln": row.get("h1_value"),
                "growth_pct": growth_index_to_plus(num(row.get("h1_growth"))),
                "unit": row.get("unit"),
                "source": row.get("source"),
            },
            "year": {
                "value_bln": row.get("year_value"),
                "growth_pct": growth_index_to_plus(num(row.get("year_growth"))),
                "unit": row.get("unit"),
                "source": row.get("source"),
            },
        }

    drivers = regional.get("industry_drivers", {})
    current["energy_electricity"] = {
        "h1": {"value": drivers.get("energy_electricity_h1"), "unit": "млн кВт·соат", "source": drivers.get("source")},
        "year": {"value": drivers.get("energy_electricity_year"), "unit": "млн кВт·соат", "source": drivers.get("source")},
    }
    current["energy_gas"] = {
        "h1": {"value": drivers.get("energy_gas_h1"), "unit": "млн м³", "source": drivers.get("source")},
        "year": {"value": drivers.get("energy_gas_year"), "unit": "млн м³", "source": drivers.get("source")},
    }
    current["localization"] = {
        "h1": {
            "projects": drivers.get("localization_h1_projects"),
            "value_mln": drivers.get("localization_h1_value_mln"),
            "source": drivers.get("source"),
        },
        "year": {
            "projects": drivers.get("localization_year_projects"),
            "value_mln": drivers.get("localization_year_value_mln"),
            "source": drivers.get("source"),
        },
    }

    current["inflation"] = {
        "h1": {"percent_limit": 2.9, "unit": "%", "source": "Кафолат хати + инфляция UI қоидаси"},
        "year": {"percent_limit": 6.6, "unit": "%", "source": "Кафолат хати + инфляция UI қоидаси"},
    }

    budget = regional.get("budget", {})
    current["budget"] = {
        "q2": {"value_bln": budget.get("q2_plan"), "kind": "режа", "unit": budget.get("unit"), "source": budget.get("source")},
        "h1": {"value_bln": budget.get("h1_plan"), "expected_bln": budget.get("h1_expected"), "unit": budget.get("unit"), "source": budget.get("source")},
        "year": {"value_bln": budget.get("year_plan"), "expected_bln": budget.get("year_expected"), "unit": budget.get("unit"), "source": budget.get("source")},
    }

    budget_inv = regional.get("budget_investment", {})
    current["budget_investment"] = {
        "h1": {
            "value_bln": (budget_inv.get("h1_absorption") or 0) / 1000,
            "pct": budget_inv.get("h1_pct"),
            "unit": "млрд сўм",
            "source": budget_inv.get("source"),
        },
        "year": {
            "value_bln": (budget_inv.get("year_absorption") or 0) / 1000,
            "pct": budget_inv.get("year_pct"),
            "unit": "млрд сўм",
            "source": budget_inv.get("source"),
        },
    }

    inv = regional.get("foreign_investment", {})
    current["investment"] = {
        "h1": {"value_mln_usd": inv.get("h1_expected"), "plan_mln_usd": inv.get("h1_plan"), "source": inv.get("source")},
        "year": {"value_mln_usd": inv.get("year_expected"), "plan_mln_usd": inv.get("year_forecast"), "source": inv.get("source")},
    }

    exp = regional.get("export", {})
    current["export"] = {
        "h1": {
            "value_mln_usd": (exp.get("h1_expected") or 0) / 1000,
            "growth_pct": growth_index_to_plus(num(exp.get("h1_growth"))),
            "source": exp.get("source"),
        },
        "year": {
            "value_mln_usd": (exp.get("year_expected") or 0) / 1000,
            "growth_pct": growth_index_to_plus(num(exp.get("year_growth"))),
            "source": exp.get("source"),
        },
    }

    emp = regional.get("employment", {})
    current["unemployment"] = {
        "h1": {"percent_limit": emp.get("unemployment_h1"), "unit": "%", "source": emp.get("source")},
        "year": {"percent_limit": emp.get("unemployment_year"), "unit": "%", "source": emp.get("source")},
    }
    current["poverty"] = {
        "h1": {"percent_limit": emp.get("poverty_h1"), "unit": "%", "source": emp.get("source")},
        "year": {"percent_limit": emp.get("poverty_year"), "unit": "%", "source": emp.get("source")},
    }
    current["mfy_clear"] = {
        "h1": {"count": emp.get("mfy_h1"), "unit": "та", "source": emp.get("source")},
        "year": {"count": emp.get("mfy_year"), "unit": "та", "source": emp.get("source")},
    }
    current["jobs"] = {
        "h1": {"value_thousand": emp.get("jobs_h1"), "unit": "минг", "source": emp.get("source")},
        "year": {"value_thousand": emp.get("jobs_year"), "unit": "минг", "source": emp.get("source")},
    }
    current["legalization"] = {
        "h1": {"value_thousand": emp.get("legalization_h1"), "unit": "минг", "source": emp.get("source")},
        "year": {"value_thousand": emp.get("legalization_year"), "unit": "минг", "source": emp.get("source")},
    }
    current["microprojects"] = {
        "h1": {"count": emp.get("microprojects_h1"), "unit": "та", "source": emp.get("source")},
        "year": {"count": emp.get("microprojects_year"), "unit": "та", "source": emp.get("source")},
    }
    return current


def compare_number(word_value: float | None, current_value: float | None, tolerance: float) -> dict[str, object]:
    if word_value is None or current_value is None:
        return {"status": "missing", "word": word_value, "current": current_value, "diff": None}
    diff = word_value - current_value
    status = "match" if abs(diff) <= tolerance else "mismatch"
    return {"status": status, "word": word_value, "current": current_value, "diff": diff}


def action_plan_kpi_compare(data: dict) -> dict[str, object]:
    rows = [row for row in extract_kafolat_action_plan_rows(data) if row.get("sourceType") == "KPI"]
    current = current_excel_kpi_values(data)
    comparisons: list[dict[str, object]] = []
    word_kpis = {str(row.get("kpi")) for row in rows}

    for row in rows:
        title = str(row.get("title") or "")
        kpi = str(row.get("kpi") or "")
        period = str(row.get("periodCode") or "")
        compare_period = "q2" if kpi == "budget" and "2-чорак" in title.casefold() else period
        current_row = current.get(kpi, {}).get(compare_period)
        checks: dict[str, object] = {}

        if current_row is None:
            comparisons.append({
                "sourceNo": row["sourceNo"],
                "kpi": kpi,
                "period": compare_period,
                "status": "no-current-source",
                "title": title,
                "checks": checks,
            })
            continue

        percentages = parse_percent_values(title)
        if kpi in {"grp", "industry", "agriculture", "construction", "services"}:
            checks["value_bln"] = compare_number(parse_uzs_bln(title), num(current_row.get("value_bln")), 60)
            word_growth = percentages[0] if percentages else None
            checks["growth_pct"] = compare_number(word_growth, num(current_row.get("growth_pct")), 0.25)
        elif kpi == "inflation":
            checks["percent_limit"] = compare_number(percentages[0] if percentages else None, num(current_row.get("percent_limit")), 0.05)
        elif kpi == "budget":
            checks["value_bln"] = compare_number(parse_uzs_bln(title), num(current_row.get("value_bln")), 1)
        elif kpi == "budget_investment":
            checks["value_bln"] = compare_number(parse_uzs_bln(title), num(current_row.get("value_bln")), 1)
            if percentages:
                checks["pct"] = compare_number(percentages[-1], num(current_row.get("pct")), 0.6)
        elif kpi == "investment":
            checks["value_mln_usd"] = compare_number(parse_usd_mln(title), num(current_row.get("value_mln_usd")), 15)
        elif kpi == "export":
            checks["value_mln_usd"] = compare_number(parse_usd_mln(title), num(current_row.get("value_mln_usd")), 2)
            if percentages:
                checks["growth_pct"] = compare_number(percentages[-1], num(current_row.get("growth_pct")), 0.6)
        elif kpi in {"unemployment", "poverty"}:
            checks["percent_limit"] = compare_number(percentages[0] if percentages else None, num(current_row.get("percent_limit")), 0.06)
        elif kpi == "mfy_clear":
            checks["count"] = compare_number(parse_first_number(r"(\d[\d\s]*(?:[,.]\d+)?)\s*та", title), num(current_row.get("count")), 0)
        elif kpi in {"energy_electricity", "energy_gas"}:
            checks["value"] = compare_number(parse_first_number(r"(\d[\d\s]*(?:[,.]\d+)?)\s*млн", title), num(current_row.get("value")), 0.2)
        else:
            comparisons.append({
                "sourceNo": row["sourceNo"],
                "kpi": kpi,
                "period": compare_period,
                "status": "no-comparison-rule",
                "title": title,
                "checks": checks,
                "current": current_row,
            })
            continue

        statuses = [str(item.get("status")) for item in checks.values() if isinstance(item, dict)]
        if not statuses:
            status = "no-checks"
        elif "mismatch" in statuses:
            status = "mismatch"
        elif "missing" in statuses:
            status = "partial"
        else:
            status = "match"
        comparisons.append({
            "sourceNo": row["sourceNo"],
            "kpi": kpi,
            "period": compare_period,
            "status": status,
            "title": title,
            "checks": checks,
            "current": current_row,
        })

    current_only = sorted(set(current) - word_kpis - {"small_business_share"})
    word_only = sorted(word_kpis - set(current))
    return {
        "sourceDoc": str(KAFOLAT_ACTION_PLAN_DOCX),
        "wordKpiCount": len(rows),
        "currentKpiSourceCount": len(current),
        "summary": dict(sorted(Counter(item["status"] for item in comparisons).items())),
        "comparisons": comparisons,
        "wordOnlyKpis": word_only,
        "currentOnlyKpis": current_only,
        "notes": [
            "Ўсиш кўрсаткичлари Wordда +7,2%, Excelда 107,2 индекси бўлса, қиёслашда 107,2 -> 7,2 қилиб нормаллаштирилди.",
            "Триллион/миллиард/миллион бирликлари қиёслаш учун ягона шкалага ўтказилди.",
            "currentOnlyKpis ҳозирги платформанинг детал/драйвер KPIлари бўлиб, янги Word жадвалида KPI эмас, чора-тадбир сифатида қолиши мумкин.",
        ],
    }


def audit_kpi_compare_cli() -> None:
    data = json.loads(DATA_JSON.read_text(encoding="utf-8"))
    normalize_source_data(data)
    report = action_plan_kpi_compare(data)
    KPI_COMPARE_AUDIT_JSON.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"word KPI rows: {report['wordKpiCount']}")
    print(f"current KPI sources: {report['currentKpiSourceCount']}")
    print(f"summary: {report['summary']}")
    print(f"word-only KPIs: {report['wordOnlyKpis']}")
    print(f"current-only KPIs: {report['currentOnlyKpis']}")
    print(f"audit json: {KPI_COMPARE_AUDIT_JSON}")


def task_group_from_text(text: str, kpi: str) -> str:
    return "Ижро топшириғи"


def extract_kafolat_rows(data: dict) -> list[dict]:
    paragraphs = docx_paragraphs(KAFOLAT_TASK_DOCX)
    if not paragraphs:
        return data.get("tasks", [])
    section = "Кафолат хати"
    buffer: list[str] = []
    rows: list[dict] = []
    declared_total = None
    task_phrase = "та топшириқ"
    for text in paragraphs:
        if text.startswith("Жами топшириқлар"):
            match = re.search(r"(\d+)", text)
            declared_total = int(match.group(1)) if match else None
            continue
        if text.startswith("Андижон вилоятида"):
            continue
        if task_phrase in text.casefold() and not text.startswith("Муддат:") and len(text) < 190:
            section = text
            buffer = []
            continue
        if text.startswith("Муддат:"):
            if buffer:
                title = " ".join(buffer).strip()
                kpi = task_kpi_from_section(section, title)
                districts = district_names_in_text(title, data.get("districts", []))
                rows.append({
                    "id": f"kafolat-{len(rows) + 1:03d}",
                    "title": title,
                    "sector": SECTOR_BY_KPI.get(kpi, "Макро иқтисодиёт"),
                    "kpi": kpi,
                    "period": text.replace("Муддат:", "").strip(),
                    "periodCode": deadline_to_period(text),
                    "deadline": text.replace("Муддат:", "").strip(),
                    "owner": "Вилоят ва туман/шаҳар ҳокимликлари",
                    "status": "grey",
                    "source": "1. Кафолат хати (Андижон).docx",
                    "section": section,
                    "sectionTarget": section_count(section),
                    "districts": districts,
                    "scope": "туман" if districts else "вилоят",
                    "group": task_group_from_text(title, kpi),
                    "monitoringRow": True,
                })
            buffer = []
        else:
            buffer.append(text)
    return rows or data.get("tasks", [])


def extract_kafolat_tasks(data: dict) -> list[dict]:
    action_rows = extract_kafolat_action_plan_rows(data)
    if not action_rows:
        rows = assign_kafolat_platform_ids(extract_kafolat_rows(data))
        kpi_targets = [row for row in rows if row["classification"] == "KPI мақсад"]
        district_targets = [row for row in rows if row["classification"] == "Туман мақсадли кўрсаткичи"]
        execution_tasks = [row for row in rows if row["classification"] == "Ижро топшириғи"]
        info_rows = [row for row in rows if row["classification"] == "Маълумот/асос"]
        data["kafolat_rows"] = rows
        data["kafolat_kpi_targets"] = kpi_targets
        data["kafolat_district_targets"] = district_targets
        data["kafolat_info_rows"] = info_rows
        data["task_modules"] = list(MODULE_DEFS.values())
        data["task_meta"] = {
            "source_doc": str(KAFOLAT_TASK_DOCX.name),
            "declared_total": len(execution_tasks),
            "monitoring_rows": len(rows),
            "kpi_targets": len(kpi_targets),
            "district_targets": len(district_targets),
            "execution_tasks": len(execution_tasks),
            "info_rows": len(info_rows),
            "note": "Эски кафолат хати parser fallback сифатида ишлади; 0_Чора-тадбир жадвали топилмади.",
        }
        return execution_tasks or data.get("tasks", [])

    assigned = assign_action_plan_platform_records(action_rows, data)
    kpi_targets = assigned["kpi_targets"]
    district_targets = assigned["district_targets"]
    execution_tasks = assigned["execution_tasks"]
    info_rows = assigned["info_rows"]
    district_linked = [row for row in execution_tasks if row.get("districts")]
    data["kafolat_rows"] = assigned["rows"]
    data["kafolat_kpi_targets"] = kpi_targets
    data["kafolat_district_targets"] = district_targets
    data["kafolat_info_rows"] = info_rows
    data["task_modules"] = list(MODULE_DEFS.values())
    data["task_meta"] = {
        "source_doc": str(KAFOLAT_ACTION_PLAN_DOCX.name),
        "declared_total": len(execution_tasks),
        "source_table_rows": len(action_rows),
        "monitoring_rows": len(action_rows),
        "kpi_targets": len(kpi_targets),
        "district_targets": len(district_targets),
        "execution_tasks": len(execution_tasks),
        "district_linked_actions": len(district_linked),
        "district_linked_source_rows": [row.get("sourceNo") for row in district_linked],
        "info_rows": len(info_rows),
        "note": (
            f"{KAFOLAT_ACTION_PLAN_DOCX.name} икки қатламга ажратилди: "
            f"{len(kpi_targets)} та KPI топшириқлар сонига қўшилмайди, "
            f"{len(execution_tasks)} та чора-тадбир эса T-001...T-{len(execution_tasks):03d} реестрига киритилди."
        ),
    }
    return execution_tasks or data.get("tasks", [])


def enrich_industry_drivers(data: dict) -> None:
    """Attach district-level industry driver fields from macro workbook sheet 1.3."""
    if not MACRO_XLSX.exists():
        return
    wb = openpyxl.load_workbook(MACRO_XLSX, read_only=True, data_only=True)
    ws = wb["1.3. Ҳудудий саноат"]
    by_name = {norm_name(d.get("name")): d for d in data.get("districts", [])}
    totals = {
        "localization_h1_projects": 0,
        "localization_h1_value_mln": 0,
        "localization_year_projects": 0,
        "localization_year_value_mln": 0,
        "energy_electricity_h1": 0,
        "energy_gas_h1": 0,
        "energy_electricity_year": 0,
        "energy_gas_year": 0,
    }

    for row in ws.iter_rows(min_row=8, max_col=20, values_only=True):
        name = norm_name(row[1])
        district = by_name.get(name)
        if not district:
            continue
        ddata = district.setdefault("data", {})
        localization = {
            "h1_projects": num(row[10]),
            "h1_value_mln": num(row[11]),
            "year_projects": num(row[13]),
            "year_value_mln": num(row[14]),
            "unit": "та / млн сўм",
            "source": "1.1-1.5-жадваллар (макро).xlsx · 1.3. Ҳудудий саноат",
        }
        energy = {
            "electricity_h1": num(row[16]),
            "gas_h1": num(row[17]),
            "electricity_year": num(row[18]),
            "gas_year": num(row[19]),
            "unit": "млн кВт·с / млн куб метр",
            "source": "1.1-1.5-жадваллар (макро).xlsx · 1.3. Ҳудудий саноат",
        }
        ddata["localization"] = localization
        ddata["energy_efficiency"] = energy

        # Keep legacy flat keys used by earlier prototype code.
        ddata["localization_projects_h1"] = localization["h1_projects"]
        ddata["localization_projects_year"] = localization["year_projects"]
        ddata["localization_value_h1_mln"] = localization["h1_value_mln"]
        ddata["localization_value_year_mln"] = localization["year_value_mln"]
        ddata["energy_electricity_h1"] = energy["electricity_h1"]
        ddata["energy_gas_h1"] = energy["gas_h1"]
        ddata["energy_electricity_year"] = energy["electricity_year"]
        ddata["energy_gas_year"] = energy["gas_year"]

        for key, value in [
            ("localization_h1_projects", localization["h1_projects"]),
            ("localization_h1_value_mln", localization["h1_value_mln"]),
            ("localization_year_projects", localization["year_projects"]),
            ("localization_year_value_mln", localization["year_value_mln"]),
            ("energy_electricity_h1", energy["electricity_h1"]),
            ("energy_gas_h1", energy["gas_h1"]),
            ("energy_electricity_year", energy["electricity_year"]),
            ("energy_gas_year", energy["gas_year"]),
        ]:
            totals[key] += value or 0

    data.setdefault("regional", {})["industry_drivers"] = {
        **totals,
        "source": "1.1-1.5-жадваллар (макро).xlsx · 1.3. Ҳудудий саноат",
    }


def normalize_source_data(data: dict) -> None:
    """Remove non-indicator helper rows and attach a compact audit summary."""
    regional = data.setdefault("regional", {})
    regional["food_balance"] = [
        row for row in regional.get("food_balance", [])
        if row.get("product") and any(row.get(key) is not None for key in [
            "resource_total",
            "production",
            "import",
            "use_total",
            "local_supply_ratio",
            "year_end_stock",
        ])
    ]
    data["source_audit"] = {
        "kpi_sources_checked": 7,
        "andijon_workbooks": [
            "1.1-1.5-жадваллар (макро).xlsx",
            "2.1-2.2-жадваллар (инфляция).xlsx",
            "3-жадвал (бюджет).xlsx",
            "4.1-жадвал (бюджет инвестка).xlsx",
            "4.2-жадвал (инвестициялар).xlsx",
            "5.1-5.2-жадваллар (экспорт).xlsx",
            "6-жадвал (бандлик ва камбағаллик даражаси).xlsx",
        ],
        "notes": [
            "ЯҲМ туман кесимида берилмаган, шунинг учун туманлар экранида ЯҲМ таркибий кўрсаткичлари орқали очилади.",
            "Бюджет ва инвестиция жадвалларида келгуси даврлар учун 'кутилиш' қиймати режага нисбатан ижро сифатида кўрсатилади.",
            "Кафолат хатидаги платформа KPIлари топшириқлар сонидан чиқарилди, амалий чоралар эса топшириқ сифатида қолдирилди.",
        ],
    }


def main() -> None:
    data = json.loads(DATA_JSON.read_text(encoding="utf-8"))
    normalize_source_data(data)
    enrich_industry_drivers(data)
    data["tasks"] = extract_kafolat_tasks(data)
    html = HTML.replace("__DATA__", json.dumps(data, ensure_ascii=False).replace("</", "<\\/"))
    OUT_HTML.write_text(html, encoding="utf-8")
    print(OUT_HTML)


HTML = r"""<!doctype html>
<html lang="uz-Cyrl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Андижон вилояти мониторинг платформаси · v7</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Inter+Tight:wght@600;700;800&display=swap&subset=cyrillic,cyrillic-ext,latin,latin-ext">
  <style>
    :root {
      --bg: #f4f7fb;
      --paper: #ffffff;
      --surface: #fbfdff;
      --ink: #102033;
      --muted: #657386;
      --line: #dfe7f0;
      --line-strong: #c5d2e0;
      --nav: #062844;
      --nav-2: #08395d;
      --blue: #1769e0;
      --blue-2: #0b4c7a;
      --blue-soft: #eef6ff;
      --green: #15803d;
      --green-soft: #e7f7ed;
      --amber: #b45309;
      --amber-soft: #fff3d6;
      --red: #b91c1c;
      --red-soft: #fee5e5;
      --grey: #71717a;
      --grey-soft: #f0f1f3;
      --map-good: #8db9d7;
      --map-good-stroke: #39769d;
      --map-mid: #f0c66f;
      --map-mid-stroke: #a76f1d;
      --map-attn: #df8b92;
      --map-attn-stroke: #a8444d;
      --map-nodata: #d8dee8;
      --map-nodata-stroke: #8792a0;
      --shadow: 0 14px 34px rgba(15, 42, 71, .09);
      --shadow-sm: 0 1px 2px rgba(15, 42, 71, .05), 0 5px 14px rgba(15, 42, 71, .06);
      --shadow-md: 0 6px 18px rgba(15, 42, 71, .08), 0 18px 44px rgba(15, 42, 71, .08);
      --shadow-lg: 0 12px 26px rgba(15, 42, 71, .10), 0 24px 60px rgba(15, 42, 71, .12);
      --ring-blue: 0 0 0 1px rgba(23, 105, 224, .20), 0 0 0 4px rgba(23, 105, 224, .08);
      --accent-grad: linear-gradient(135deg, #1769e0 0%, #0b4c7a 100%);
      --accent-grad-soft: linear-gradient(135deg, rgba(23, 105, 224, .08) 0%, rgba(11, 76, 122, .10) 100%);
      --motion: 180ms cubic-bezier(.2, .7, .2, 1);
      --motion-slow: 280ms cubic-bezier(.2, .7, .2, 1);
      --nav-w: 232px;
      --r: 8px;
      --r-md: 12px;
      --r-lg: 16px;
      font-family: "Inter", "Inter Tight", "Segoe UI", Arial, sans-serif;
      font-feature-settings: "ss01", "cv11";
    }

    .num, .tabular {
      font-variant-numeric: tabular-nums;
      font-feature-settings: "tnum", "ss01", "cv11";
      letter-spacing: -0.005em;
    }

    @keyframes pulse-dot {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.18); opacity: .82; }
    }

    @media (prefers-reduced-motion: reduce) {
      .module-tab.active .module-dot { animation: none; }
      * { transition-duration: 0ms !important; animation-duration: 0ms !important; }
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      color: var(--ink);
      background:
        radial-gradient(circle at 92% 0, rgba(23, 105, 224, .10), transparent 30%),
        linear-gradient(180deg, #fbfdff 0%, var(--bg) 45%, #eef3f8 100%);
      overflow-x: hidden;
    }

    button, input, select { font: inherit; }
    button { cursor: pointer; }
    :focus-visible {
      outline: 2px solid rgba(27, 77, 90, .55);
      outline-offset: 3px;
      transition: outline-color var(--motion), outline-offset var(--motion);
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 30;
      color: #f7fbff;
      background:
        radial-gradient(circle at 78% 0, rgba(255,255,255,.12), transparent 30%),
        linear-gradient(135deg, #082c49, #0b4c7a);
      box-shadow: 0 12px 28px rgba(7, 36, 61, .20);
    }

    .mast {
      min-height: 92px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: center;
      gap: 24px;
      padding: 16px clamp(18px, 3vw, 40px) 12px;
      position: relative;
      overflow: hidden;
    }

    .mast::after {
      content: "";
      position: absolute;
      right: -24px;
      top: -42px;
      width: 430px;
      height: 145px;
      opacity: .22;
      background:
        linear-gradient(45deg, transparent 46%, rgba(255,255,255,.75) 47% 53%, transparent 54%) 0 0 / 74px 74px,
        linear-gradient(-45deg, transparent 46%, rgba(255,255,255,.75) 47% 53%, transparent 54%) 0 0 / 74px 74px;
      pointer-events: none;
    }

    .brand {
      display: grid;
      grid-template-columns: 74px minmax(0, 1fr);
      gap: 14px;
      align-items: center;
      min-width: 0;
      position: relative;
      z-index: 1;
    }

    .brand > div {
      min-width: 0;
    }

    .brand-mark {
      height: 54px;
      border: 1px solid rgba(255,255,255,.35);
      border-radius: 8px;
      display: grid;
      place-items: center;
      font-weight: 950;
      font-size: 20px;
      letter-spacing: .04em;
      background: rgba(255,255,255,.10);
    }

    .brand h1 {
      margin: 0;
      font-size: clamp(19px, 2.1vw, 27px);
      line-height: 1.08;
      letter-spacing: 0;
      overflow-wrap: anywhere;
    }

    .brand p {
      margin: 5px 0 0;
      color: rgba(247,251,255,.78);
      font-size: 13px;
      font-weight: 650;
      overflow-wrap: anywhere;
    }

    .kpi-mark {
      color: rgba(247,251,255,.72);
      font-size: clamp(42px, 5.5vw, 64px);
      font-weight: 950;
      letter-spacing: 8px;
      line-height: 1;
      position: relative;
      z-index: 1;
    }

    .shell {
      display: grid;
      grid-template-columns: var(--nav-w) minmax(0, 1fr);
      min-height: calc(100vh - 92px);
    }

    .sidebar {
      position: sticky;
      top: 92px;
      height: calc(100vh - 92px);
      background: linear-gradient(180deg, var(--nav), var(--nav-2));
      color: rgba(255,255,255,.78);
      padding: 18px 12px;
      overflow: auto;
      box-shadow: 16px 0 42px rgba(7, 26, 45, .16);
    }

    .side-title {
      padding: 0 10px 14px;
      border-bottom: 1px solid rgba(255,255,255,.12);
      margin-bottom: 12px;
    }

    .side-title strong {
      display: block;
      color: #fff;
      font-size: 15px;
    }

    .side-title span {
      display: block;
      margin-top: 5px;
      font-size: 12px;
      line-height: 1.3;
      color: rgba(255,255,255,.58);
    }

    .nav-btn {
      width: 100%;
      min-height: 46px;
      border: 0;
      border-radius: 8px;
      padding: 10px 12px;
      margin-bottom: 6px;
      color: rgba(255,255,255,.76);
      background: transparent;
      display: grid;
      grid-template-columns: 26px minmax(0, 1fr);
      align-items: center;
      gap: 8px;
      text-align: left;
      font-weight: 800;
    }

    .nav-btn svg {
      width: 20px;
      height: 20px;
      stroke-width: 2.2;
    }

    .nav-btn.active,
    .nav-btn:hover {
      color: #fff;
      background: var(--blue);
      box-shadow: 0 10px 24px rgba(23, 105, 224, .28);
    }

    .main {
      min-width: 0;
      padding: 20px clamp(16px, 2.4vw, 34px) 34px;
    }

    .page-head {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 16px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 16px;
      box-shadow: var(--shadow);
    }

    .eyebrow {
      color: var(--blue);
      font-size: 12px;
      font-weight: 950;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    h2, h3, p { margin: 0; letter-spacing: 0; }
    .page-head h2 { margin-top: 4px; font-size: clamp(22px, 2.2vw, 30px); line-height: 1.08; }
    .page-head p { margin-top: 6px; color: var(--muted); font-size: 14px; }

    .toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      flex-wrap: wrap;
    }

    .segmented {
      display: inline-flex;
      gap: 4px;
      padding: 4px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 999px;
      box-shadow: 0 10px 24px rgba(16, 48, 82, .08);
    }

    .segmented button {
      min-height: 44px;
      border: 0;
      border-radius: 999px;
      padding: 9px 14px;
      color: var(--muted);
      background: transparent;
      font-size: 12px;
      font-weight: 900;
      white-space: nowrap;
    }

    .segmented button.active {
      color: #fff;
      background: var(--blue);
    }

    input, select {
      min-height: 44px;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 10px;
      background: #fff;
      color: var(--ink);
      outline: none;
    }

    .front-kpis {
      position: relative;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      background: transparent;
      border: 0;
      box-shadow: none;
      border-radius: 0;
      overflow: visible;
      margin-bottom: 16px;
    }

    .front-kpis::before { display: none; }

    .dashboard-module-tabs {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 8px;
      margin-bottom: 16px;
    }

    .module-tab {
      position: relative;
      border: 1px solid var(--line);
      border-radius: var(--r-md);
      background: #fff;
      color: var(--muted);
      padding: 12px;
      text-align: left;
      cursor: pointer;
      min-height: 54px;
      transition: transform var(--motion), background var(--motion), border-color var(--motion), box-shadow var(--motion), color var(--motion);
    }

    .module-tab .module-dot {
      display: none;
    }

    .module-tab strong {
      display: block;
      color: var(--ink);
      font-size: 14px;
      line-height: 1.18;
      margin-top: 3px;
      font-weight: 700;
      letter-spacing: -0.005em;
    }

    .module-tab span {
      display: none;
      font-size: 11px;
      font-weight: 600;
      color: #718199;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .module-tab:hover {
      transform: translateY(-1px);
      background: #fff;
      border-color: rgba(23, 105, 224, .30);
      box-shadow: var(--shadow-sm);
      color: var(--ink);
    }

    .module-tab.active {
      background: #fff;
      border-color: rgba(23, 105, 224, .50);
      box-shadow: inset 0 -4px 0 var(--blue), var(--shadow-sm);
      color: var(--blue);
    }

    .module-tab.active .module-dot {
      animation: none;
    }

    .module-tab.active strong { color: var(--ink); font-weight: 800; }

    .module-tab[data-dashboard-module] { --module-color: var(--blue); }

    .module-heading {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: end;
      margin: 8px 0 14px;
      position: relative;
    }

    .module-heading > div {
      position: relative;
      padding-left: 14px;
    }

    .module-heading > div::before {
      content: "";
      position: absolute;
      left: 0;
      top: 6px;
      bottom: 6px;
      width: 4px;
      border-radius: 4px;
      background: var(--accent-grad);
    }

    .module-heading h2 {
      margin: 0;
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: clamp(22px, 2.4vw, 28px);
      line-height: 1.12;
      letter-spacing: -0.018em;
      font-weight: 800;
    }

    .module-heading p {
      margin: 6px 0 0;
      color: var(--muted);
      max-width: 70ch;
    }

    .module-heading .chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #fff;
      color: var(--ink);
      border: 1px solid var(--line);
      box-shadow: var(--shadow-sm);
      padding: 6px 12px;
      font-weight: 700;
    }

    .module-heading .chip::before {
      content: "";
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: var(--accent-grad);
    }

    .scoreline {
      display: grid;
      grid-template-columns: minmax(180px, .9fr) minmax(180px, 1.15fr) repeat(2, minmax(130px, .72fr)) minmax(190px, .86fr);
      gap: 10px;
      align-items: stretch;
      margin: 16px 0;
      padding: 16px;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 18px;
      box-shadow: var(--shadow);
    }

    .scoreline.execution-strip {
      grid-template-columns: minmax(240px, 1fr) minmax(330px, .9fr) minmax(120px, .32fr) minmax(170px, .42fr);
      align-items: center;
      margin-top: 16px;
    }

    .execution-strip .score-actions {
      align-content: stretch;
    }

    .exec-status-grid {
      min-width: 0;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 8px;
    }

    .exec-status-pill {
      min-width: 0;
      min-height: 64px;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fbfdff;
      padding: 10px 12px;
      text-align: left;
      display: grid;
      align-content: center;
      gap: 3px;
      color: var(--ink);
      transition: border-color var(--motion), box-shadow var(--motion), transform var(--motion);
    }

    .exec-status-pill:hover {
      border-color: rgba(23, 105, 224, .35);
      box-shadow: var(--shadow-sm);
      transform: translateY(-1px);
    }

    .exec-status-pill span {
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      letter-spacing: .035em;
      white-space: normal;
      overflow: visible;
      text-overflow: clip;
    }

    .exec-status-pill strong {
      font-size: 26px;
      line-height: 1;
      font-weight: 950;
      color: var(--ink);
      font-variant-numeric: tabular-nums;
    }

    .exec-status-pill.green strong { color: #16a34a; }
    .exec-status-pill.red strong { color: #ef4444; }

    .exec-progress-box {
      min-width: 0;
      display: grid;
      justify-items: center;
      gap: 5px;
    }

    .exec-donut {
      width: 54px;
      height: 54px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background:
        radial-gradient(circle at center, #fff 0 56%, transparent 57%),
        conic-gradient(#16a34a calc(var(--pct) * 1%), #eef2f6 0);
      border: 1px solid var(--line);
    }

    .exec-donut strong {
      font-size: 14px;
      font-weight: 950;
      color: var(--ink);
    }

    .exec-progress-box small {
      color: var(--muted);
      font-size: 11px;
      font-weight: 800;
      text-align: center;
    }

    .scoreline-copy {
      min-width: 0;
      display: grid;
      align-content: center;
      gap: 5px;
    }

    .scoreline-copy span {
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    .scoreline-copy strong {
      font-size: 18px;
      line-height: 1.15;
      color: var(--ink);
    }

    .scoreline-copy small {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.3;
    }

    .score {
      min-width: 0;
      padding: 13px;
      background: #fbfdff;
      border: 1px solid var(--line);
      border-radius: 14px;
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      transition: transform var(--motion), box-shadow var(--motion), border-color var(--motion);
    }

    .score:hover {
      transform: translateY(-1px);
      border-color: rgba(23, 105, 224, .38);
      box-shadow: var(--shadow-sm);
    }

    .score:focus-visible {
      outline: none;
      border-color: var(--blue);
      box-shadow: var(--ring-blue);
    }

    .score-label {
      font-size: 12px;
      font-weight: 800;
      color: var(--muted);
      white-space: nowrap;
      text-transform: uppercase;
      letter-spacing: .035em;
    }

    .score-chart-wrap {
      position: relative;
      width: 64px;
      height: 64px;
      flex-shrink: 0;
    }

    .score-chart-wrap svg { width: 64px; height: 64px; }

    .score-pct {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 800;
      color: var(--ink);
    }

    .score-legend {
      display: none;
    }

    .score-leg-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: var(--muted);
      font-weight: 600;
      white-space: nowrap;
    }

    .score-leg-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .score-num {
      font-size: 34px;
      font-weight: 900;
      line-height: 1;
      margin-left: auto;
    }

    .score-actions {
      display: grid;
      gap: 8px;
      align-content: center;
    }

    .score-action {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      text-decoration: none;
      min-height: 38px;
      border: 1px solid rgba(23, 105, 224, .36);
      border-radius: 10px;
      background: #fff;
      color: var(--blue);
      font-size: 12px;
      font-weight: 900;
      text-align: left;
      padding: 8px 10px;
      cursor: pointer;
      transition: transform var(--motion), box-shadow var(--motion), border-color var(--motion), background var(--motion);
    }

    .score-action.primary {
      background: var(--blue);
      color: #fff;
      border-color: var(--blue);
    }

    .score-action:hover {
      transform: translateY(-1px);
      box-shadow: var(--shadow-sm);
    }

    .score-action:focus-visible {
      outline: none;
      border-color: var(--blue);
      box-shadow: var(--ring-blue);
    }

    .front-kpis.module-kpis.macro-layout {
      grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .front-kpis.module-kpis.employment-layout {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .front-kpis.module-kpis.macro-layout .front-kpi {
      min-height: 92px;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent {
      background: #fff;
      box-shadow: none;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent .kpi-icon {
      background: var(--blue-soft);
      box-shadow: none;
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent.active {
      background: #fff;
      box-shadow: inset 0 -4px 0 var(--blue), var(--shadow-sm);
    }

    .front-kpis.module-kpis.macro-layout .front-kpi.parent.active .kpi-icon {
      background: var(--blue-soft);
      color: var(--blue);
      box-shadow: none;
    }

    .front-kpi {
      position: relative;
      border: 1px solid var(--line);
      border-radius: 14px;
      background: #fff;
      min-height: 88px;
      padding: 14px;
      display: grid;
      grid-template-columns: 44px minmax(0, 1fr);
      gap: 12px;
      align-items: center;
      text-align: left;
      color: inherit;
      transition: transform var(--motion), background var(--motion), box-shadow var(--motion);
    }

    .front-kpi::after { display: none; }

    .front-kpi:nth-child(4n),
    .front-kpi:last-child,
    .front-kpi:nth-child(n+5) { border: 1px solid var(--line); }
    .front-kpi:hover { background: var(--surface); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
    .front-kpi.active {
      background: #fff;
      border-color: rgba(23, 105, 224, .62);
      transform: none;
      box-shadow: inset 0 -4px 0 var(--blue), var(--shadow-sm);
      z-index: 1;
    }
    .front-kpi.active h3 { color: var(--ink); }
    .front-kpi.active .front-kpi-meta { color: var(--blue); }
    .front-kpi.active .front-kpi-dot { background: var(--blue); box-shadow: 0 0 0 3px rgba(23, 105, 224, .12); }
    .front-kpi.active .kpi-icon {
      background: var(--blue-soft);
      color: var(--blue);
      box-shadow: none;
    }

    .yhm-focus-bar {
      margin-bottom: 16px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: center;
      border: 1px solid var(--line);
      border-radius: var(--r);
      background: #fff;
      box-shadow: var(--shadow);
      padding: 14px 16px;
    }

    .yhm-focus-bar strong {
      display: block;
      color: var(--blue);
      font-size: clamp(28px, 3vw, 42px);
      line-height: 1;
    }

    .yhm-focus-bar span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 13px;
      font-weight: 850;
    }

    .command-summary {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      margin: 0 0 16px;
    }

    .command-card {
      border: 1px solid var(--line);
      border-radius: var(--r);
      background: rgba(255,255,255,.96);
      padding: 12px;
      min-width: 0;
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
    }

    .command-card span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .command-card strong {
      display: block;
      margin-top: 5px;
      color: var(--ink);
      font-size: 22px;
      line-height: 1;
    }

    .command-card small {
      display: block;
      margin-top: 6px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
    }

    .kpi-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      color: var(--blue);
      background: var(--blue-soft);
      box-shadow: none;
      transition: transform var(--motion);
    }

    .front-kpi:hover .kpi-icon { transform: scale(1.03); }

    .kpi-icon svg { width: 22px; height: 22px; stroke-width: 2.2; }
    .front-kpi h3 {
      color: #4d6172;
      font-size: 15px;
      line-height: 1.18;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .front-kpi-copy {
      min-width: 0;
      display: grid;
      gap: 5px;
    }

    .front-kpi-copy p {
      margin: 0;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.2;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .front-kpi-meta {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 800;
      line-height: 1.1;
    }

    .front-kpi-dot {
      width: 7px;
      height: 7px;
      border-radius: 999px;
      background: #9aa8b5;
      flex: 0 0 auto;
    }

    .grid-3 {
      display: grid;
      grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr) minmax(320px, .7fr);
      gap: 16px;
      align-items: start;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: minmax(0, 1.25fr) minmax(360px, .75fr);
      gap: 16px;
      align-items: start;
    }

    .panel {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: var(--r);
      box-shadow: var(--shadow);
      overflow: hidden;
      min-width: 0;
    }

    .panel-head {
      min-height: 60px;
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
      background: #f8fbff;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
    }

    .panel-head h3 { font-size: 17px; line-height: 1.2; }
    .panel-head p { margin-top: 4px; color: var(--muted); font-size: 12px; line-height: 1.3; }
    .panel-body { padding: 14px 16px 16px; }

    .workflow {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .flow-step {
      position: relative;
      min-height: 78px;
      border: 1px solid var(--line);
      border-radius: var(--r);
      background: #fff;
      padding: 12px 14px;
      box-shadow: 0 10px 24px rgba(16, 48, 82, .08);
    }

    .flow-step::after {
      content: "";
      position: absolute;
      right: -15px;
      top: 50%;
      width: 20px;
      height: 2px;
      background: var(--line-strong);
    }

    .flow-step:last-child::after { display: none; }
    .flow-step.active { border-color: var(--blue); background: var(--blue-soft); box-shadow: 0 10px 24px rgba(21, 42, 45, .08); }
    .flow-step span { color: var(--blue); font-size: 12px; font-weight: 950; }
    .flow-step strong { display: block; margin-top: 4px; font-size: 15px; }
    .flow-step small { display: block; margin-top: 4px; color: var(--muted); line-height: 1.25; }

    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    th, td {
      padding: 10px 10px;
      border-bottom: 1px solid var(--line);
      text-align: left;
      vertical-align: middle;
      font-size: 13px;
      line-height: 1.25;
      overflow-wrap: anywhere;
    }

    th {
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
      background: #fbfdff;
    }

    tr.clickable { cursor: pointer; }
    tr.clickable:hover { background: #f7fbff; }
    tr.active-row { background: var(--blue-soft); }

    .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .muted { color: var(--muted); }

    .chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      padding: 4px 8px;
      font-size: 11px;
      line-height: 1;
      font-weight: 950;
      white-space: nowrap;
    }

    .chip.green { color: var(--green); background: var(--green-soft); }
    .chip.amber { color: var(--amber); background: var(--amber-soft); }
    .chip.red { color: var(--red); background: var(--red-soft); }
    .chip.grey { color: var(--grey); background: var(--grey-soft); }
    .chip.blue { color: var(--blue); background: var(--blue-soft); }

    .progress {
      width: 100%;
      height: 8px;
      border-radius: 999px;
      background: #e8eef6;
      overflow: hidden;
      margin-top: 6px;
    }

    .progress i {
      display: block;
      height: 100%;
      width: var(--w, 0%);
      background: var(--c, var(--blue));
      border-radius: inherit;
    }

    .detail-hero {
      display: grid;
      gap: 10px;
    }

    .detail-main {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 12px;
      align-items: start;
    }

    .detail-main strong {
      font-size: 28px;
      color: var(--blue);
      line-height: 1;
    }

    .kv {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 8px;
      margin-top: 8px;
    }

    .kv div {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px;
      background: #fbfdff;
      min-width: 0;
    }

    .kv span { display: block; color: var(--muted); font-size: 11px; font-weight: 900; }
    .kv b { display: block; margin-top: 4px; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .district-workspace {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(330px, .38fr);
      gap: 16px;
      align-items: start;
    }

    .district-context {
      margin-bottom: 14px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: end;
      border: 1px solid var(--line);
      border-radius: var(--r);
      background: #fff;
      box-shadow: var(--shadow);
      padding: 14px 16px;
    }

    .district-context h3 {
      margin: 4px 0 0;
      font-size: clamp(22px, 2vw, 30px);
      line-height: 1.05;
    }

    .district-context p {
      margin-top: 5px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.35;
    }

    .district-context-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }

    .district-table-wrap {
      overflow-x: auto;
    }

    .district-detail-table {
      margin-top: 16px;
    }

    .district-table {
      min-width: 920px;
    }

    .district-table td:first-child,
    .district-table th:first-child {
      width: 19%;
    }

    .district-table .row-title strong {
      display: block;
      font-size: 13px;
      color: var(--ink);
    }

    .district-table .row-title span,
    .district-table small {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .district-table tr.active-row td:first-child {
      border-color: var(--blue);
      background: var(--blue-soft);
    }

    .district-preview {
      position: sticky;
      top: 118px;
    }

    .preview-score {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: start;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #f8fbff;
    }

    .preview-score strong {
      display: block;
      color: var(--blue);
      font-size: 28px;
      line-height: 1;
    }

    .preview-score span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
    }

    .task-list, .district-list {
      display: grid;
      gap: 10px;
    }

    .task-card {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: #fff;
      display: grid;
      gap: 8px;
      min-width: 0;
      cursor: pointer;
    }

    .task-card:hover {
      border-color: var(--blue);
      background: var(--surface);
    }

    .task-card header {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      align-items: flex-start;
    }

    .task-card strong {
      font-size: 13px;
      line-height: 1.3;
      overflow-wrap: anywhere;
    }

    .task-code {
      display: inline-flex;
      align-items: center;
      width: fit-content;
      margin-bottom: 6px;
      padding: 3px 7px;
      border-radius: 999px;
      background: #eaf3ff;
      color: var(--blue);
      font-size: 11px;
      font-weight: 900;
    }

    .callout-note {
      border: 1px solid #cfe0f7;
      border-radius: 8px;
      background: #f5f9ff;
      color: var(--ink);
      padding: 10px 12px;
      font-size: 13px;
      line-height: 1.45;
    }

    .task-meta {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
      color: var(--muted);
      font-size: 11px;
    }

    .district-card {
      display: grid;
      grid-template-columns: minmax(170px, .75fr) repeat(4, minmax(115px, 1fr)) 128px;
      gap: 8px;
      align-items: stretch;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px;
      background: #fff;
      cursor: pointer;
    }

    .district-card:hover,
    .district-card.active { border-color: var(--blue); background: var(--surface); }

    .district-card.active {
      box-shadow: 0 10px 24px rgba(21, 42, 45, .07);
    }

    .district-name strong { display: block; font-size: 14px; }
    .district-name span { display: block; margin-top: 4px; color: var(--muted); font-size: 12px; }

    .metric-cell {
      border-left: 1px solid var(--line);
      padding-left: 8px;
      min-width: 0;
    }

    .metric-cell span { display: block; color: var(--muted); font-size: 11px; font-weight: 900; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .metric-cell b { display: block; margin-top: 3px; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .metric-cell small { display: block; margin-top: 2px; color: var(--muted); font-size: 11px; }

    .debt-cell {
      border-left: 1px solid var(--line);
      padding-left: 8px;
      display: grid;
      align-content: center;
      justify-items: end;
    }

    .debt-cell strong { font-size: 20px; color: var(--red); line-height: 1; }
    .debt-cell span { margin-top: 4px; color: var(--muted); font-size: 11px; text-align: right; }

    .district-controls {
      display: grid;
      grid-template-columns: minmax(220px, 1fr) minmax(160px, .7fr) minmax(180px, .8fr);
      gap: 10px;
      margin-bottom: 16px;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255,255,255,.9);
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
    }

    .district-controls label {
      display: grid;
      gap: 5px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
      min-width: 0;
    }

    .district-controls select,
    .district-controls input {
      width: 100%;
      min-width: 0;
    }

    .district-preview {
      position: sticky;
      top: 112px;
    }

    .preview-score {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 10px;
      align-items: start;
      margin-bottom: 12px;
    }

    .preview-score strong {
      display: block;
      color: var(--blue);
      font-size: 30px;
      line-height: 1;
    }

    .preview-score span {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 12px;
    }

    .preview-metrics {
      display: grid;
      gap: 9px;
    }

    .preview-metric {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: start;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px 11px;
      background: #fff;
    }

    .preview-metric strong {
      display: block;
      font-size: 12px;
      line-height: 1.25;
    }

    .preview-metric small {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .preview-task-summary {
      margin-top: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: #fbfdff;
    }

    .preview-task-summary strong {
      display: block;
      font-size: 13px;
      line-height: 1.25;
    }

    .preview-task-summary span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .cards-3 {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }

    .context-strip {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .context-step {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 11px 12px;
      background: rgba(255,255,255,.88);
      min-width: 0;
    }

    .context-step.active {
      border-color: var(--blue);
      background: #f5f9ff;
      border-color: var(--blue);
      background: var(--blue-soft);
    }

    .context-step span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .context-step strong {
      display: block;
      margin-top: 4px;
      font-size: 14px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .action-row {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
      margin-top: 12px;
    }

    .mini-button {
      border: 1px solid var(--line);
      border-radius: 8px;
      min-height: 44px;
      padding: 10px 12px;
      background: #fff;
      color: var(--ink);
      font-size: 12px;
      font-weight: 900;
    }

    .mini-button.primary {
      border-color: var(--blue);
      background: var(--blue);
      color: #fff;
    }

    .mini-button.danger {
      border-color: rgba(220, 38, 38, .28);
      color: #b91c1c;
      background: #fff5f5;
    }

    .mini-button.profile {
      min-height: 34px;
      padding: 7px 10px;
      border-color: rgba(30, 96, 195, .22);
      color: var(--blue-dark);
      background: #f7fbff;
    }

    .action-row.compact {
      margin-top: 0;
      gap: 6px;
    }

    .action-row.compact .mini-button {
      min-height: 34px;
      padding: 7px 9px;
    }

    .workflow-strip {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 14px;
    }

    .workflow-step {
      border: 1px solid var(--line);
      border-radius: var(--radius);
      background: rgba(255,255,255,.82);
      padding: 12px 14px;
      display: grid;
      gap: 4px;
    }

    .workflow-step span {
      color: var(--muted);
      font-size: 11px;
      font-weight: 900;
      text-transform: uppercase;
    }

    .workflow-step strong {
      font-size: 14px;
    }

    .workflow-step small {
      color: var(--muted);
      line-height: 1.45;
    }

    .kpi-monitor-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
      align-items: start;
    }

    .kpi-monitor-grid.single {
      grid-template-columns: minmax(0, 1fr);
    }

    .kpi-monitor-card {
      position: relative;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 18px;
      box-shadow: var(--shadow);
      overflow: hidden;
      min-width: 0;
      transition: transform var(--motion), box-shadow var(--motion), border-color var(--motion);
    }

    .kpi-monitor-card::before { display: none; }

    .kpi-monitor-card:hover {
      transform: none;
      box-shadow: var(--shadow);
      border-color: var(--line);
    }

    .kpi-monitor-head {
      position: relative;
      min-height: 84px;
      display: grid;
      grid-template-columns: 52px minmax(0, 1fr) auto;
      gap: 14px;
      align-items: center;
      padding: 18px 20px;
      border-bottom: 1px solid var(--line);
      background: #fff;
      overflow: hidden;
    }

    .kpi-head-district {
      position: relative;
      z-index: 1;
      min-height: 36px;
      padding: 8px 12px;
      white-space: nowrap;
    }

    .kpi-monitor-head .head-watermark {
      display: none;
    }

    .kpi-monitor-head .head-watermark svg {
      width: 100%;
      height: 100%;
      stroke-width: 1.6;
    }

    .kpi-monitor-head h3 {
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: 19px;
      line-height: 1.2;
      letter-spacing: -0.012em;
      font-weight: 800;
    }

    .kpi-monitor-head p {
      margin-top: 4px;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.35;
      font-weight: 500;
    }

    .small-icon {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      color: var(--blue);
      background: var(--blue-soft);
      box-shadow: none;
      transition: transform var(--motion);
    }

    .kpi-monitor-card:hover .small-icon { transform: scale(1.02); }

    .small-icon svg {
      width: 24px;
      height: 24px;
      stroke-width: 2.2;
    }

    .annual-plan {
      text-align: right;
      min-width: 94px;
    }

    .annual-plan span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
    }

    .annual-plan strong {
      display: block;
      margin-top: 4px;
      color: var(--blue);
      font-size: 20px;
      line-height: 1.05;
    }

    .quarter-matrix {
      padding: 18px 20px 20px;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
    }

    .quarter-row {
      position: relative;
      display: grid;
      gap: 12px;
      align-content: start;
      padding: 15px;
      border: 1px solid var(--line);
      border-radius: 14px;
      background: #fff;
      font-size: 12px;
      overflow: hidden;
      transition: border-color var(--motion), background var(--motion), transform var(--motion), box-shadow var(--motion);
    }

    .quarter-row::before { display: none; }

    .quarter-row:hover {
      border-color: rgba(23, 105, 224, .24);
      transform: none;
      box-shadow: var(--shadow-sm);
    }
    .quarter-row:hover::before { opacity: 1; }

    .quarter-row.actual { --row-accent: var(--blue); }
    .quarter-row.planned { --row-accent: var(--amber); }
    .quarter-row.empty { --row-accent: var(--grey); }

    .quarter-row .q-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding-top: 2px;
    }

    .quarter-row .q-period {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--muted);
      font-size: 14px;
      font-weight: 800;
      letter-spacing: 0;
      text-transform: uppercase;
    }

    .quarter-row .q-period::before {
      content: "";
      width: 5px;
      height: 5px;
      border-radius: 999px;
      background: var(--row-accent, var(--blue));
      box-shadow: 0 0 0 3px color-mix(in srgb, var(--row-accent, var(--blue)) 15%, transparent);
    }

    .quarter-row .q-head .chip {
      padding: 3px 8px;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .quarter-row .q-hero {
      display: flex;
      align-items: baseline;
      flex-wrap: wrap;
      gap: 4px 10px;
      padding-bottom: 2px;
    }

    .quarter-row .q-hero-value {
      color: var(--ink);
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: clamp(25px, 2.15vw, 34px);
      line-height: 1;
      font-weight: 800;
      letter-spacing: -0.022em;
      font-variant-numeric: tabular-nums;
      font-feature-settings: "tnum", "ss01";
    }

    .quarter-row .q-hero .q-trend,
    .quarter-row .q-hero .q-trend.up,
    .quarter-row .q-hero .q-trend.down,
    .quarter-row .q-hero .q-trend.flat {
      font-size: clamp(25px, 2.15vw, 34px);
      font-weight: 800;
      letter-spacing: -0.022em;
      color: var(--ink);
    }

    .quarter-row .q-hero .q-trend::before {
      border-left-width: 6px;
      border-right-width: 6px;
      transform: translateY(-3px);
    }

    .quarter-row .q-hero .q-trend.up::before { border-bottom-width: 8px; }
    .quarter-row .q-hero .q-trend.down::before { border-top-width: 8px; }
    .quarter-row .q-hero .q-trend.flat::before { width: 10px; height: 2px; transform: translateY(-2px); }

    .quarter-row .q-hero-label {
      color: var(--muted);
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 0;
      text-transform: none;
    }

    .quarter-row .q-aux {
      margin: 0;
      display: grid;
      gap: 4px;
      padding-top: 10px;
      border-top: 1px solid rgba(223, 231, 240, .95);
    }

    .quarter-row .q-aux-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 8px;
      align-items: baseline;
      color: var(--muted);
      font-size: 11.5px;
      font-weight: 500;
    }

    .quarter-row .q-aux-row.status b {
      justify-self: end;
    }

    .quarter-row .q-aux-row.status .chip {
      font-size: 11px;
      text-transform: none;
      letter-spacing: 0;
    }

    .quarter-row .q-aux-row b {
      color: var(--ink);
      font-weight: 600;
      font-size: 12.5px;
      letter-spacing: -0.005em;
      font-variant-numeric: tabular-nums;
      font-feature-settings: "tnum", "ss01";
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .quarter-row .q-report {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 8px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 500;
      padding-top: 6px;
      border-top: 1px dashed rgba(20, 30, 35, .09);
    }

    .quarter-row .q-report b {
      color: var(--ink);
      font-weight: 600;
      font-size: 11.5px;
    }

    .quarter-row .q-trend {
      display: inline-flex;
      align-items: baseline;
      gap: 5px;
      padding: 0;
      background: transparent;
      font-size: 12.5px;
      font-weight: 700;
      letter-spacing: -0.005em;
      font-variant-numeric: tabular-nums;
      font-feature-settings: "tnum", "ss01";
    }

    .quarter-row .q-trend::before {
      content: "";
      display: inline-block;
      width: 0;
      height: 0;
      border-left: 4px solid transparent;
      border-right: 4px solid transparent;
    }

    .quarter-row .q-trend.up { color: var(--green); }
    .quarter-row .q-trend.up::before { border-bottom: 5px solid var(--green); }
    .quarter-row .q-trend.down { color: var(--red); }
    .quarter-row .q-trend.down::before { border-top: 5px solid var(--red); }
    .quarter-row .q-trend.flat { color: var(--muted); }
    .quarter-row .q-trend.flat::before { width: 7px; height: 1.5px; border: 0; background: currentColor; align-self: center; }

    .quarter-main {
      display: block;
      color: var(--blue);
      font-size: clamp(24px, 2.4vw, 34px);
      line-height: 1;
    }

    .quarter-note {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
      min-height: 16px;
    }

    .macro-growth-panel {
      padding: 18px 20px 20px;
      display: grid;
      gap: 16px;
      border-top: 1px solid var(--line);
      background: #fbfdff;
    }

    .macro-growth-overview {
      display: grid;
      grid-template-columns: minmax(220px, .7fr) minmax(0, 1.7fr);
      gap: 12px;
      align-items: stretch;
    }

    .macro-annual-card,
    .macro-period-card,
    .macro-composition-panel {
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      min-width: 0;
    }

    .macro-annual-card {
      padding: 18px 18px 16px;
      display: grid;
      gap: 8px;
      align-content: center;
    }

    .macro-annual-card span,
    .macro-period-card span,
    .macro-composition-card span {
      display: block;
      color: var(--muted);
      font-size: 12px;
      font-weight: 800;
    }

    .macro-annual-card strong {
      color: var(--blue);
      font-size: clamp(38px, 4vw, 58px);
      line-height: .95;
      letter-spacing: -0.04em;
      font-weight: 900;
      font-variant-numeric: tabular-nums;
    }

    .macro-annual-card small,
    .macro-period-card small,
    .macro-composition-card small {
      color: var(--muted);
      font-size: 11.5px;
      line-height: 1.35;
    }

    .macro-period-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      min-width: 0;
    }

    .macro-period-card {
      padding: 14px;
      display: grid;
      gap: 9px;
      align-content: start;
    }

    .macro-period-card.actual { border-color: rgba(23, 105, 224, .24); }
    .macro-period-card.planned { border-color: rgba(100, 116, 139, .18); }

    .macro-period-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .macro-period-head b {
      color: var(--ink);
      font-size: 13px;
      font-weight: 850;
    }

    .macro-period-card strong {
      color: var(--ink);
      font-size: clamp(24px, 2.4vw, 36px);
      line-height: 1;
      letter-spacing: -0.03em;
      font-weight: 900;
      font-variant-numeric: tabular-nums;
    }

    .macro-mini-bar {
      display: block;
      height: 6px;
      border-radius: 999px;
      background: #e8eef6;
      overflow: hidden;
    }

    .macro-mini-bar i {
      display: block;
      width: min(var(--w), 100%);
      height: 100%;
      border-radius: inherit;
      background: var(--blue);
    }

    .macro-composition-panel {
      padding: 16px;
      display: grid;
      gap: 12px;
    }

    .macro-composition-panel > summary {
      list-style: none;
    }

    .macro-composition-panel > summary::-webkit-details-marker {
      display: none;
    }

    .macro-composition-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      cursor: pointer;
    }

    .macro-composition-head strong {
      display: block;
      color: var(--ink);
      font-size: 18px;
      font-weight: 850;
      letter-spacing: -0.012em;
    }

    .macro-composition-head small {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 12px;
    }

    .macro-dropdown-meta {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--blue);
      font-size: 12px;
      font-weight: 800;
      white-space: nowrap;
    }

    .macro-dropdown-caret {
      width: 26px;
      height: 26px;
      border-radius: 8px;
      display: inline-grid;
      place-items: center;
      border: 1px solid var(--line);
      background: #fff;
      color: var(--blue);
      transition: transform var(--motion), border-color var(--motion), background var(--motion);
    }

    .macro-composition-panel[open] .macro-dropdown-caret {
      transform: rotate(180deg);
      border-color: rgba(30, 86, 168, .28);
      background: var(--blue-soft);
    }

    .macro-composition-body {
      display: grid;
      gap: 12px;
      padding-top: 12px;
      border-top: 1px solid var(--line);
    }

    .macro-composition-actions {
      display: flex;
      justify-content: flex-end;
    }

    .macro-composition-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
    }

    .macro-composition-card {
      padding: 15px;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      text-align: left;
      cursor: pointer;
      display: grid;
      gap: 8px;
      transition: border-color var(--motion), background var(--motion), box-shadow var(--motion);
    }

    .macro-composition-card:hover,
    .macro-composition-card.active {
      border-color: rgba(23, 105, 224, .26);
      background: #f7fbff;
      box-shadow: var(--shadow-sm);
    }

    .macro-composition-card strong {
      color: var(--blue);
      font-size: clamp(24px, 2.2vw, 34px);
      line-height: 1;
      letter-spacing: -0.03em;
      font-weight: 900;
      font-variant-numeric: tabular-nums;
    }

    .macro-composition-bar {
      display: block;
      height: 7px;
      border-radius: 999px;
      background: #e8eef6;
      overflow: hidden;
    }

    .macro-composition-bar i {
      display: block;
      width: min(var(--w), 100%);
      height: 100%;
      border-radius: inherit;
      background: var(--blue);
    }

    .kpi-monitor-card.macro-layout-card {
      border: 0;
      background: transparent;
      box-shadow: none;
      overflow: visible;
    }

    .kpi-monitor-card.macro-layout-card .kpi-monitor-head {
      display: none;
    }

    .kpi-monitor-card.macro-layout-card:hover {
      box-shadow: none;
    }

    .macro-layout-card .macro-growth-panel {
      padding: 0;
      border-top: 0;
      background: transparent;
      grid-template-columns: minmax(0, 1fr);
      align-items: start;
      gap: 16px;
    }

    .macro-layout-card .macro-growth-panel.with-side {
      grid-template-columns: minmax(0, 1.04fr) minmax(340px, .7fr);
    }

    .macro-main-panel,
    .industry-driver-panel {
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      box-shadow: var(--shadow-sm);
      min-width: 0;
    }

    .macro-main-panel {
      padding: 24px;
      display: grid;
      gap: 16px;
    }

    .macro-section-title {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .macro-section-title strong {
      color: var(--ink);
      font-size: 18px;
      font-weight: 850;
      letter-spacing: -0.012em;
    }

    .macro-section-title span {
      color: var(--muted);
      font-size: 13px;
      font-weight: 600;
    }

    .macro-hero-card {
      position: relative;
      min-height: 150px;
      display: grid;
      grid-template-columns: 1fr;
      align-items: center;
      padding: 26px 34px;
      border: 1px solid rgba(19, 126, 61, .18);
      border-radius: 12px;
      background:
        linear-gradient(180deg, #f5fbf7 0%, #eff8f3 100%),
        repeating-linear-gradient(90deg, transparent 0 56px, rgba(15, 135, 58, .035) 56px 57px);
      overflow: hidden;
    }

    .macro-hero-copy {
      min-width: 0;
      display: grid;
      gap: 6px;
      align-content: center;
      text-align: center;
      justify-items: center;
    }

    .macro-hero-copy span {
      color: var(--muted);
      font-size: 15px;
      font-weight: 800;
    }

    .macro-hero-copy strong {
      color: #08742d;
      font-size: clamp(48px, 5vw, 72px);
      line-height: .9;
      letter-spacing: -0.055em;
      font-weight: 950;
      font-variant-numeric: tabular-nums;
    }

    .macro-hero-copy small {
      color: var(--muted);
      font-size: 15px;
      font-weight: 700;
    }

    .macro-layout-card .macro-growth-overview {
      display: grid;
      grid-template-columns: 1fr;
      gap: 14px;
    }

    .macro-layout-card .macro-period-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .macro-layout-card .macro-period-card {
      min-height: 128px;
      justify-items: center;
      text-align: center;
      background: #f9fbfd;
    }

    .macro-layout-card .macro-period-card.actual {
      background: #f2fbf5;
      border-color: rgba(15, 135, 58, .18);
    }

    .macro-layout-card .macro-period-card strong {
      color: var(--blue);
      font-size: clamp(28px, 2.5vw, 38px);
    }

    .macro-layout-card .macro-period-card.actual strong {
      color: #08742d;
    }

    .macro-layout-card .macro-period-head {
      width: 100%;
      display: grid;
      justify-items: center;
      gap: 6px;
    }

    .macro-layout-card .macro-mini-bar {
      display: none;
    }

    .macro-layout-card .macro-composition-panel {
      padding: 12px 14px;
      border: 1px solid var(--line);
      border-radius: 12px;
      box-shadow: none;
      background: #f9fbfd;
    }

    .macro-layout-card .macro-composition-head {
      align-items: center;
    }

    .macro-layout-card .macro-composition-body {
      margin-top: 12px;
    }

    .macro-layout-card .macro-composition-grid {
      grid-template-columns: 1fr;
      gap: 8px;
    }

    .macro-layout-card .macro-composition-card {
      grid-template-columns: 40px minmax(150px, .5fr) minmax(180px, 1fr) 78px;
      align-items: center;
      gap: 12px;
      padding: 7px 0;
      border: 0;
      border-radius: 0;
      background: transparent;
      box-shadow: none;
    }

    .macro-layout-card .macro-composition-card:hover,
    .macro-layout-card .macro-composition-card.active {
      background: transparent;
      border-color: transparent;
      box-shadow: none;
    }

    .macro-comp-icon {
      width: 36px;
      height: 36px;
      border-radius: 9px;
      display: grid;
      place-items: center;
      color: var(--blue);
      background: var(--blue-soft);
    }

    .macro-comp-icon svg {
      width: 21px;
      height: 21px;
      stroke-width: 1.8;
    }

    .macro-comp-name {
      color: var(--ink);
      font-size: 14px;
      font-weight: 700;
    }

    .macro-layout-card .macro-composition-card strong.macro-comp-value {
      color: #08742d;
      font-size: 20px;
      text-align: right;
      letter-spacing: -0.012em;
    }

    .macro-layout-card .macro-composition-bar {
      height: 14px;
      background: #eef3f7;
    }

    .macro-layout-card .macro-composition-bar i {
      background: var(--macro-color, var(--blue));
    }

    .industry-driver-panel {
      padding: 24px 22px;
      display: grid;
      gap: 16px;
    }

    .industry-driver-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .industry-driver-head strong {
      color: var(--ink);
      font-size: 18px;
      font-weight: 850;
      letter-spacing: -0.012em;
    }

    .industry-driver-list {
      display: grid;
      gap: 16px;
    }

    .industry-driver-card {
      display: grid;
      grid-template-columns: 56px minmax(0, 1fr) 18px;
      gap: 14px;
      align-items: start;
      padding: 20px;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      text-align: left;
      cursor: pointer;
      transition: border-color var(--motion), background var(--motion), box-shadow var(--motion);
    }

    .industry-driver-card:hover {
      border-color: rgba(23, 105, 224, .25);
      background: #f9fbfd;
      box-shadow: var(--shadow-sm);
    }

    .driver-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      display: grid;
      place-items: center;
    }

    .driver-icon svg {
      width: 30px;
      height: 30px;
      stroke-width: 2;
    }

    .driver-icon.green { color: #08742d; background: #eef9f1; }
    .driver-icon.blue { color: var(--blue); background: #eef5ff; }
    .driver-icon.orange { color: #d35a0f; background: #fff3ea; }

    .industry-driver-arrow {
      color: var(--muted);
      font-size: 24px;
      line-height: 1;
      padding-top: 5px;
    }

    .industry-driver-body {
      display: grid;
      gap: 14px;
      min-width: 0;
    }

    .industry-driver-title strong {
      color: var(--ink);
      font-size: 16px;
      font-weight: 850;
    }

    .industry-driver-title span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.3;
    }

    .industry-driver-metrics {
      display: grid;
      grid-template-columns: 1fr 1px 1fr;
      gap: 16px;
      align-items: start;
    }

    .industry-driver-divider {
      width: 1px;
      height: 48px;
      background: var(--line);
      align-self: center;
    }

    .industry-driver-metric {
      display: grid;
      gap: 4px;
    }

    .industry-driver-metric span {
      color: var(--muted);
      font-size: 11.5px;
      font-weight: 700;
    }

    .industry-driver-metric strong {
      color: var(--blue);
      font-size: 18px;
      font-weight: 900;
      letter-spacing: -0.012em;
      font-variant-numeric: tabular-nums;
    }

    .industry-driver-metric small {
      color: var(--muted);
      font-size: 10.8px;
      line-height: 1.25;
    }

    .industry-driver-card.green .industry-driver-metric strong { color: #08742d; }
    .industry-driver-card.orange .industry-driver-metric strong { color: #d35a0f; }

    .industry-driver-panel .mini-button {
      width: 100%;
      justify-content: center;
      min-height: 38px;
      color: var(--blue-dark);
      border-color: var(--blue);
      background: #fff;
    }

    .budget-invest-panel {
      padding: 18px 20px 20px;
      display: grid;
      gap: 16px;
      background: linear-gradient(180deg, #fbfdff 0%, #fff 45%);
      border-top: 1px solid var(--line);
    }

    .budget-invest-summary {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      overflow: hidden;
    }

    .budget-invest-summary > div {
      min-width: 0;
      padding: 14px 16px;
      border-right: 1px solid var(--line);
    }

    .budget-invest-summary > div:last-child { border-right: 0; }

    .budget-invest-summary span,
    .budget-period-card span,
    .budget-dynamics-card span {
      display: block;
      color: var(--muted);
      font-size: 12px;
      font-weight: 800;
    }

    .budget-invest-summary strong {
      display: block;
      margin-top: 6px;
      color: var(--ink);
      font-size: clamp(21px, 2vw, 31px);
      line-height: 1;
      font-weight: 850;
      letter-spacing: -0.018em;
      font-variant-numeric: tabular-nums;
    }

    .budget-invest-summary small {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 11px;
    }

    .budget-invest-body {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(250px, 300px);
      gap: 14px;
      align-items: stretch;
    }

    .budget-periods-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      min-width: 0;
    }

    .budget-period-card,
    .budget-dynamics-card {
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      padding: 14px;
      min-width: 0;
    }

    .budget-period-card {
      display: grid;
      gap: 11px;
      align-content: start;
    }

    .budget-period-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .budget-period-top b {
      color: var(--ink);
      font-size: 14px;
      font-weight: 850;
    }

    .budget-period-card strong {
      color: var(--ink);
      font-size: clamp(22px, 2vw, 30px);
      line-height: 1;
      font-weight: 850;
      letter-spacing: -0.02em;
      font-variant-numeric: tabular-nums;
    }

    .budget-period-card.actual { border-color: rgba(23, 105, 224, .24); }
    .budget-period-card.expected { border-color: rgba(100, 116, 139, .22); }
    .budget-period-card.missing {
      background: #f8fafc;
      border-style: dashed;
    }

    .budget-period-card.missing strong,
    .budget-period-card.missing .budget-period-meta b {
      color: var(--muted);
    }

    .budget-period-meta {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 10px;
      color: var(--muted);
      font-size: 12px;
    }

    .budget-period-meta b {
      color: var(--ink);
      font-size: 19px;
      font-weight: 850;
      font-variant-numeric: tabular-nums;
    }

    .budget-progress {
      height: 8px;
      border-radius: 999px;
      background: #e8eef6;
      overflow: hidden;
    }

    .budget-progress i {
      display: block;
      width: min(var(--w), 100%);
      height: 100%;
      border-radius: inherit;
      background: var(--c, var(--blue));
    }

    .budget-period-note {
      color: var(--muted);
      font-size: 11.5px;
      line-height: 1.25;
    }

    .budget-dynamics-card {
      display: grid;
      gap: 12px;
      align-content: start;
      background: #fcfdff;
    }

    .budget-dynamics-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .budget-dynamics-head strong {
      color: var(--ink);
      font-size: 14px;
      font-weight: 850;
    }

    .budget-dynamics-svg {
      width: 100%;
      height: 116px;
      display: block;
    }

    .budget-dynamics-list {
      display: grid;
      gap: 7px;
    }

    .budget-dynamics-list div {
      display: grid;
      grid-template-columns: 68px minmax(0, 1fr) auto;
      align-items: center;
      gap: 8px;
      color: var(--muted);
      font-size: 11.5px;
    }

    .budget-dynamics-list i {
      height: 5px;
      border-radius: 999px;
      background: #e8eef6;
      overflow: hidden;
    }

    .budget-dynamics-list i::before {
      content: "";
      display: block;
      width: min(var(--w), 100%);
      height: 100%;
      border-radius: inherit;
      background: var(--c, var(--blue));
    }

    .budget-dynamics-list b {
      color: var(--ink);
      font-variant-numeric: tabular-nums;
    }

    .finance-sector-panel {
      display: grid;
      gap: 16px;
    }

    .finance-sector-grid {
      display: block;
    }

    .finance-active-pane {
      min-width: 0;
    }

    .finance-card {
      display: grid;
      grid-template-rows: auto 1fr auto;
      min-width: 0;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .finance-card.active {
      border-color: rgba(30, 86, 168, .28);
      box-shadow: 0 18px 42px rgba(15, 43, 77, .1);
    }

    .finance-card-head {
      display: flex;
      align-items: center;
      gap: 12px;
      min-height: 92px;
      padding: 20px 18px;
      border-bottom: 1px solid var(--line);
    }

    .finance-icon {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      color: var(--blue);
      background: var(--blue-soft);
      flex: 0 0 auto;
    }

    .finance-icon.purple {
      color: #6d45c4;
      background: #f0ecff;
    }

    .finance-icon.green {
      color: #0b8a65;
      background: #eaf8f3;
    }

    .finance-icon.gold {
      color: #b67805;
      background: #fff5df;
    }

    .finance-icon svg {
      width: 24px;
      height: 24px;
      stroke-width: 1.9;
    }

    .finance-card-head strong {
      color: var(--ink);
      font-size: 16px;
      font-weight: 900;
      letter-spacing: -0.012em;
      line-height: 1.18;
    }

    .finance-card-body {
      display: grid;
      gap: 18px;
      padding: 20px 18px;
      align-content: start;
    }

    .finance-card-body.two-col {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 18px;
    }

    .finance-section-title {
      color: var(--ink);
      font-size: 13px;
      font-weight: 900;
    }

    .finance-metric-row {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 14px;
    }

    .finance-metric-row.two {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .finance-metric {
      min-width: 0;
    }

    .finance-metric span {
      display: block;
      color: var(--muted);
      font-size: 12px;
      font-weight: 850;
      line-height: 1.25;
    }

    .finance-metric strong {
      display: block;
      margin-top: 8px;
      color: var(--ink);
      font-size: clamp(20px, 1.8vw, 28px);
      line-height: 1;
      font-weight: 900;
      letter-spacing: -0.02em;
      font-variant-numeric: tabular-nums;
    }

    .finance-metric.accent strong { color: var(--blue); }
    .finance-metric.gold strong { color: #b67805; }

    .finance-metric small {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .finance-divider {
      height: 1px;
      background: var(--line);
    }

    .finance-summary-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
    }

    .finance-summary-tile,
    .finance-quarter-cell {
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fbfdff;
      padding: 13px 14px;
      min-width: 0;
    }

    .finance-summary-tile span,
    .finance-quarter-cell span {
      display: block;
      color: var(--muted);
      font-size: 11.5px;
      font-weight: 850;
      line-height: 1.25;
    }

    .finance-summary-tile strong,
    .finance-quarter-cell strong {
      display: block;
      margin-top: 8px;
      color: var(--ink);
      font-size: clamp(18px, 1.7vw, 24px);
      line-height: 1;
      font-weight: 900;
      letter-spacing: -0.018em;
      font-variant-numeric: tabular-nums;
    }

    .finance-summary-tile.accent strong,
    .finance-quarter-cell.accent strong { color: var(--blue); }

    .finance-summary-tile.purple strong,
    .finance-quarter-cell.purple strong { color: #6d45c4; }

    .finance-summary-tile.gold strong,
    .finance-quarter-cell.gold strong { color: #b67805; }

    .finance-summary-tile small,
    .finance-quarter-cell small {
      display: block;
      margin-top: 6px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .finance-quarter-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .finance-source {
      margin: 0;
      padding: 10px 12px;
      border: 1px solid var(--line);
      border-radius: 9px;
      background: #f7fbff;
      color: var(--blue);
      font-size: 11.5px;
      line-height: 1.3;
    }

    .kpi-monitor-card > .finance-source {
      margin: 14px 16px 16px;
    }

    .finance-action {
      justify-self: stretch;
      margin-top: 4px;
    }

    .kpi-signal {
      padding: 14px 16px 4px;
      display: grid;
      grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
      gap: 12px;
      border-bottom: 1px solid var(--line);
      background: #fbfdff;
    }

    .signal-main,
    .signal-side {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      padding: 12px;
      min-width: 0;
    }

    .signal-main span,
    .signal-side span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .signal-main strong {
      display: block;
      margin-top: 6px;
      color: var(--blue);
      font-size: clamp(28px, 3vw, 42px);
      line-height: 1;
    }

    .signal-main small,
    .signal-side small {
      display: block;
      margin-top: 7px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.3;
    }

    .signal-track {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 8px;
      margin-top: 10px;
    }

    .signal-step {
      min-width: 0;
    }

    .signal-step b {
      display: block;
      color: var(--ink);
      font-size: 12px;
      line-height: 1.2;
    }

    .signal-step i {
      display: block;
      height: 7px;
      margin-top: 6px;
      border-radius: 999px;
      background: var(--grey-soft);
      overflow: hidden;
    }

    .signal-step i::after {
      content: "";
      display: block;
      width: var(--w, 0%);
      height: 100%;
      background: var(--c, var(--blue));
      border-radius: inherit;
    }

    .lagging {
      border-top: 1px solid var(--line);
      padding: 12px 16px 14px;
      background: #fbfdff;
    }

    .lagging-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
    }

    .lagging-title strong {
      font-size: 13px;
    }

    .lagging-list {
      display: grid;
      gap: 7px;
    }

    .lagging-item {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 8px;
      align-items: center;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 10px;
      background: #fff;
      cursor: pointer;
    }

    .lagging-item:hover {
      border-color: var(--blue);
      background: #f7fbff;
    }

    .lagging-item strong {
      display: block;
      font-size: 12px;
      line-height: 1.2;
    }

    .lagging-item span {
      display: block;
      margin-top: 2px;
      color: var(--muted);
      font-size: 11px;
    }

    .composition {
      border-top: 1px solid var(--line);
      padding: 12px 16px 14px;
      background: #fbfdff;
    }

    .composition-grid,
    .driver-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .industry-driver-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .inflation-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    .component-card,
    .driver-card,
    .food-card {
      position: relative;
      border: 1px solid rgba(20, 30, 35, .06);
      border-radius: var(--r-md);
      background: #fff;
      padding: 13px 14px 13px 18px;
      min-width: 0;
      box-shadow: var(--shadow-sm);
      transition: transform var(--motion), box-shadow var(--motion), border-color var(--motion);
    }

    .component-card::before,
    .driver-card::before,
    .food-card::before { display: none; }

    .driver-card:hover,
    .food-card:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); border-color: rgba(27, 77, 90, .18); }

    .component-card { cursor: pointer; }

    .component-card:hover,
    .component-card.active {
      transform: translateY(-1px);
      border-color: rgba(27, 77, 90, .25);
      background: #f7fbff;
      box-shadow: var(--shadow-md);
    }

    .component-card.active::before { display: none; }

    .component-card span,
    .driver-card span,
    .food-card span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .component-card strong,
    .driver-card strong,
    .food-card strong {
      display: block;
      margin-top: 6px;
      color: var(--blue);
      font-size: 19px;
      line-height: 1.08;
      font-weight: 800;
      letter-spacing: -0.012em;
      font-variant-numeric: tabular-nums;
      font-feature-settings: "tnum", "ss01";
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .component-card small,
    .driver-card small,
    .food-card small {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .product-card {
      display: grid;
      grid-template-columns: 48px minmax(0, 1fr);
      gap: 14px;
      align-items: center;
      text-align: left;
      padding: 14px 16px 14px 18px;
    }

    .product-card .product-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: #fbfdfd;
      border: 1px solid rgba(98, 148, 162, .18);
      display: grid;
      place-items: center;
      flex: none;
      text-transform: none;
      letter-spacing: 0;
      font-size: inherit;
      font-weight: inherit;
      color: inherit;
      margin: 0;
      transition: border-color var(--motion), box-shadow var(--motion), background var(--motion);
    }

    .product-card .product-icon .emoji {
      width: 32px;
      height: 32px;
      display: block;
      background-color: #6294a2;
      -webkit-mask: var(--icon-url) no-repeat center / contain;
              mask: var(--icon-url) no-repeat center / contain;
      pointer-events: none;
    }

    .product-card:hover .product-icon {
      background: #ffffff;
      border-color: rgba(98, 148, 162, .42);
      box-shadow: 0 6px 14px rgba(98, 148, 162, .14);
    }

    .product-card .product-body {
      display: grid;
      gap: 3px;
      min-width: 0;
      text-transform: none;
      letter-spacing: 0;
      font-size: inherit;
      font-weight: inherit;
      margin: 0;
      color: inherit;
    }

    .product-card .product-name {
      display: block;
      color: var(--ink);
      font-size: 12.5px;
      font-weight: 700;
      letter-spacing: -0.005em;
      text-transform: none;
      line-height: 1.25;
      white-space: normal;
      overflow: visible;
    }

    .product-card .product-value {
      display: block;
      margin-top: 2px;
      color: var(--blue);
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: 16px;
      font-weight: 800;
      line-height: 1.1;
      letter-spacing: -0.01em;
      font-variant-numeric: tabular-nums;
      font-feature-settings: "tnum", "ss01";
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .product-card .product-note {
      display: block;
      margin-top: 2px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 500;
      line-height: 1.3;
    }

    .drivers {
      border-top: 1px solid var(--line);
      padding: 14px 16px 16px;
      background: transparent;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .drivers .composition,
    .drivers .lagging {
      border-top: 0;
      border-radius: 12px;
      background: #fbfdff;
      padding: 14px 16px 16px;
    }

    .data-note {
      margin: 10px 0 0;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .poverty-section {
      padding: 18px 20px 22px;
      display: grid;
      gap: 16px;
      background:
        radial-gradient(circle at 0% 0%, rgba(98, 148, 162, .07), transparent 45%),
        #fafdfd;
    }

    .poverty-section .poverty-head {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: end;
      gap: 12px;
    }

    .poverty-section .poverty-head strong {
      display: block;
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: clamp(17px, 1.6vw, 20px);
      font-weight: 800;
      letter-spacing: -0.014em;
      color: var(--ink);
    }

    .poverty-section .poverty-head p {
      margin: 4px 0 0;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.4;
      max-width: 60ch;
    }

    .poverty-stats {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
    }

    .employment-driver-section .poverty-stats {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .poverty-stat {
      position: relative;
      display: grid;
      grid-template-columns: 44px minmax(0, 1fr);
      gap: 14px;
      padding: 16px 18px 14px;
      background: #ffffff;
      border: 1px solid rgba(20, 30, 35, .07);
      border-radius: 14px;
      box-shadow: var(--shadow-sm);
      transition: transform var(--motion), box-shadow var(--motion), border-color var(--motion);
    }

    .poverty-stat:hover {
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
      border-color: rgba(98, 148, 162, .35);
    }

    .poverty-stat-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      background: linear-gradient(145deg, rgba(98, 148, 162, .18), rgba(98, 148, 162, .08));
      color: #4a7280;
      display: grid;
      place-items: center;
      align-self: start;
      box-shadow: inset 0 0 0 1px rgba(98, 148, 162, .18);
    }

    .poverty-stat-icon svg {
      width: 24px;
      height: 24px;
      stroke-width: 1.7;
    }

    .poverty-stat-body { display: grid; gap: 6px; min-width: 0; }

    .poverty-stat-label {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 600;
      line-height: 1.32;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .poverty-stat-value {
      display: flex;
      align-items: baseline;
      gap: 6px;
      color: var(--ink);
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: clamp(20px, 1.9vw, 26px);
      font-weight: 800;
      line-height: 1;
      letter-spacing: -0.022em;
      font-variant-numeric: tabular-nums;
      font-feature-settings: "tnum", "ss01";
    }

    .poverty-stat-value em {
      color: var(--muted);
      font-style: normal;
      font-weight: 600;
      font-size: 12.5px;
      letter-spacing: 0;
    }

    .poverty-stat-meta {
      display: flex;
      align-items: baseline;
      gap: 6px;
      flex-wrap: wrap;
      color: var(--muted);
      font-size: 11.5px;
      font-weight: 500;
    }

    .poverty-stat-meta b {
      color: var(--ink);
      font-weight: 700;
      font-variant-numeric: tabular-nums;
      font-feature-settings: "tnum", "ss01";
    }

    .poverty-stat-divider { color: rgba(20, 30, 35, .25); }

    .poverty-progress {
      margin-top: 4px;
      height: 5px;
      background: rgba(98, 148, 162, .15);
      border-radius: 999px;
      overflow: hidden;
    }

    .poverty-progress i {
      display: block;
      height: 100%;
      background: linear-gradient(90deg, #6294a2, #88b4c0);
      border-radius: 999px;
      transition: width var(--motion-slow);
    }

    .poverty-progress-label {
      display: block;
      color: var(--muted);
      font-size: 10.5px;
      font-weight: 500;
      letter-spacing: 0.02em;
    }

    .poverty-territory {
      position: relative;
      display: grid;
      gap: 12px;
      padding: 18px 20px 20px;
      background:
        radial-gradient(circle at 100% 0%, rgba(98, 148, 162, .14), transparent 55%),
        #ffffff;
      border: 1px solid rgba(20, 30, 35, .07);
      border-radius: 14px;
      box-shadow: var(--shadow-sm);
    }

    .poverty-territory::before { display: none; }

    .poverty-territory-head {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: end;
      gap: 12px;
    }

    .poverty-territory-eyebrow {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    .poverty-territory-head strong {
      display: flex;
      align-items: baseline;
      gap: 8px;
      margin-top: 4px;
      color: var(--blue);
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: clamp(28px, 3vw, 38px);
      font-weight: 800;
      line-height: 1;
      letter-spacing: -0.022em;
      font-variant-numeric: tabular-nums;
    }

    .poverty-territory-head strong em {
      color: var(--muted);
      font-style: normal;
      font-weight: 600;
      font-size: 13px;
      letter-spacing: 0;
    }

    .poverty-territory p {
      margin: 0;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.45;
      max-width: 62ch;
    }

    .poverty-territory-list {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .poverty-territory-chip {
      display: inline-flex;
      align-items: center;
      padding: 5px 10px;
      border-radius: 999px;
      background: rgba(98, 148, 162, .1);
      border: 1px solid rgba(98, 148, 162, .22);
      color: #2f5560;
      font-size: 11.5px;
      font-weight: 600;
      letter-spacing: 0;
      text-transform: none;
      white-space: nowrap;
    }

    .poverty-territory-empty {
      color: var(--muted);
      font-style: italic;
      font-size: 12.5px;
    }

    @media (max-width: 1080px) {
      .poverty-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .employment-driver-section .poverty-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 640px) {
      .poverty-section { padding: 14px 14px 18px; }
      .poverty-stats { grid-template-columns: 1fr; }
      .employment-driver-section .poverty-stats { grid-template-columns: 1fr; }
      .poverty-section .poverty-head { grid-template-columns: 1fr; }
    }

    .districts-head {
      display: block;
      margin-bottom: 16px;
    }

    .districts-head .eyebrow {
      color: var(--muted);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .districts-head h2 {
      margin: 4px 0 4px;
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: clamp(20px, 2vw, 26px);
      font-weight: 800;
      letter-spacing: -0.018em;
      color: var(--ink);
    }

    .districts-head p {
      margin: 0;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.4;
      max-width: 70ch;
    }

    .districts-head-actions {
      display: grid;
      grid-template-columns: minmax(170px, .45fr) minmax(240px, .65fr);
      gap: 10px;
      align-items: end;
      padding: 14px;
      border: 1px solid var(--line);
      border-radius: 16px;
      background: #fff;
      box-shadow: var(--shadow-sm);
    }

    .district-module-tabs {
      margin-bottom: 12px;
    }

    .district-kpi-selector {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 10px;
      margin-bottom: 12px;
    }

    .district-kpi-option {
      display: grid;
      grid-template-columns: 38px minmax(0, 1fr);
      gap: 10px;
      align-items: center;
      min-height: 74px;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      padding: 11px 12px;
      text-align: left;
      color: var(--ink);
      cursor: pointer;
      transition: border-color var(--motion), box-shadow var(--motion), background var(--motion);
    }

    .district-kpi-option:hover,
    .district-kpi-option.active {
      border-color: rgba(23, 105, 224, .44);
      background: #f7fbff;
      box-shadow: var(--shadow-sm);
    }

    .district-kpi-option.active {
      box-shadow: inset 0 -3px 0 var(--blue), var(--shadow-sm);
    }

    .district-kpi-option .kpi-mini-icon {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      display: grid;
      place-items: center;
      color: var(--blue);
      background: var(--blue-soft);
    }

    .district-kpi-option svg {
      width: 20px;
      height: 20px;
      stroke-width: 1.9;
    }

    .district-kpi-option strong,
    .district-kpi-option small {
      display: block;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .district-kpi-option strong {
      color: var(--ink);
      font-size: 13.5px;
      font-weight: 850;
      line-height: 1.18;
      white-space: nowrap;
    }

    .district-kpi-option small {
      margin-top: 3px;
      color: var(--muted);
      font-size: 11.5px;
      line-height: 1.25;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    .district-data-layers {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      width: 100%;
    }

    .district-data-layer {
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fff;
      padding: 12px;
      min-width: 0;
    }

    .district-data-layer span,
    .district-layer-note span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .district-data-layer strong {
      display: block;
      margin-top: 5px;
      color: var(--ink);
      font-size: 18px;
      font-weight: 850;
      line-height: 1.05;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .district-data-layer small {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 11.5px;
      line-height: 1.3;
    }

    .district-layer-note {
      border: 1px solid var(--line);
      border-radius: 12px;
      background: var(--surface);
      padding: 12px;
      grid-column: 1 / -1;
    }

    .district-layer-note strong {
      display: block;
      margin-top: 5px;
      font-size: 13px;
      line-height: 1.35;
    }

    .district-count-split {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      align-items: center;
    }

    .districts-control {
      display: grid;
      gap: 4px;
      font-size: 11px;
      color: var(--muted);
    }

    .districts-control > span {
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .districts-control select,
    .districts-control input {
      border: 1px solid rgba(20, 30, 35, .12);
      border-radius: 8px;
      padding: 8px 10px;
      background: #ffffff;
      color: var(--ink);
      font: inherit;
      font-size: 12.5px;
      outline: none;
      width: 100%;
      min-width: 0;
      transition: border-color var(--motion), box-shadow var(--motion);
    }

    .districts-control select:focus,
    .districts-control input:focus {
      border-color: rgba(98, 148, 162, .55);
      box-shadow: 0 0 0 3px rgba(98, 148, 162, .15);
    }

    .districts-control--search input { min-width: 0; }

    .districts-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.42fr) minmax(330px, .68fr);
      gap: 14px;
      margin-bottom: 16px;
      align-items: start;
    }

    .districts-side {
      display: grid;
      gap: 14px;
      min-width: 0;
    }

    .districts-map {
      position: relative;
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 18px;
      box-shadow: var(--shadow);
      padding: 16px;
      overflow: hidden;
      min-width: 0;
    }

    .districts-map::before { display: none; }

    .districts-map::after {
      content: "";
      position: absolute;
      inset: -40% -10% auto auto;
      width: 320px;
      height: 320px;
      background: radial-gradient(circle, rgba(23, 105, 224, .06), transparent 60%);
      pointer-events: none;
    }

    .districts-map-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 12px;
      gap: 12px;
      position: relative;
      z-index: 1;
    }

    .districts-map-head strong {
      display: block;
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: 16px;
      font-weight: 800;
      letter-spacing: -0.012em;
      color: var(--ink);
    }

    .districts-map-head span {
      display: block;
      margin-top: 2px;
      color: var(--muted);
      font-size: 11.5px;
      line-height: 1.35;
      font-weight: 500;
      max-width: 64ch;
      white-space: normal;
      overflow: visible;
      text-overflow: clip;
      overflow-wrap: break-word;
    }

    .districts-map-canvas {
      position: relative;
      display: grid;
      place-items: center;
      background: linear-gradient(180deg, #f8fbff 0%, #eef4fa 100%);
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 18px 12px;
      min-height: 430px;
      min-width: 0;
      overflow: hidden;
      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, .8),
        inset 0 -1px 0 rgba(23, 105, 224, .04),
        0 1px 2px rgba(15, 42, 71, .03);
    }

    .districts-map-canvas::before {
      display: none;
    }

    .andijan-map {
      width: 100%;
      max-width: 820px;
      height: auto;
      display: block;
      min-width: 0;
      position: relative;
      z-index: 1;
      filter: none;
    }

    .map-cell { cursor: pointer; outline: none; }

    .map-cell .map-fill {
      stroke: rgba(255, 255, 255, .92);
      stroke-width: 1.2;
      stroke-linejoin: round;
      transition: fill var(--motion), stroke var(--motion), stroke-width var(--motion), transform var(--motion), filter var(--motion);
      transform-box: fill-box;
      transform-origin: center;
    }

    .map-label {
      fill: #0f1d22;
      font-size: 15px;
      font-weight: 800;
      letter-spacing: 0.01em;
      pointer-events: none;
      paint-order: stroke fill;
      stroke: #ffffff;
      stroke-width: 4.5;
      stroke-linejoin: round;
      stroke-opacity: .95;
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      opacity: 0;
      transition: opacity var(--motion), fill var(--motion);
    }

    .map-label.is-city {
      font-size: 11px;
      stroke-width: 3.2;
    }

    .map-label.selected,
    .map-label.hover {
      opacity: 1;
    }

    .map-label.selected { fill: var(--blue); }

    .map-cell.green .map-fill { fill: var(--map-good); stroke: var(--map-good-stroke); }
    .map-cell.amber .map-fill { fill: var(--map-mid); stroke: var(--map-mid-stroke); }
    .map-cell.red   .map-fill { fill: var(--map-attn); stroke: var(--map-attn-stroke); }
    .map-cell.grey  .map-fill { fill: var(--map-nodata); stroke: var(--map-nodata-stroke); }

    .map-cell.is-city .map-fill {
      stroke-width: 1.8;
      stroke-dasharray: 3 2.5;
    }

    .map-cell:hover .map-fill,
    .map-cell:focus .map-fill {
      filter: saturate(1.06);
      transform: none;
    }

    .map-cell.selected .map-fill {
      stroke: var(--blue-2);
      stroke-width: 3;
      filter: drop-shadow(0 3px 8px rgba(11, 76, 122, .28));
    }


    .districts-map-legend {
      display: flex;
      gap: 8px;
      margin-top: 12px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .legend-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 600;
      border: 1px solid var(--line);
      background: #ffffff;
      color: var(--muted);
    }

    .legend-chip::before {
      content: "";
      width: 8px;
      height: 8px;
      border-radius: 999px;
    }

    .legend-chip.green::before { background: var(--map-good); border: 1px solid var(--map-good-stroke); }
    .legend-chip.amber::before { background: var(--map-mid); border: 1px solid var(--map-mid-stroke); }
    .legend-chip.red::before   { background: var(--map-attn); border: 1px solid var(--map-attn-stroke); }
    .legend-chip.grey::before  { background: var(--map-nodata); border: 1px solid var(--map-nodata-stroke); }

    .districts-leaderboard {
      position: relative;
      background: #ffffff;
      border: 1px solid var(--line);
      border-radius: 18px;
      box-shadow: var(--shadow-sm);
      padding: 14px;
      overflow: hidden;
      display: grid;
      grid-template-rows: auto 1fr;
    }

    .districts-leaderboard::before { display: none; }

    .districts-lb-head {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      margin-bottom: 10px;
      gap: 8px;
    }

    .districts-lb-head strong {
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: 16px;
      font-weight: 800;
      letter-spacing: -0.012em;
      color: var(--ink);
    }

    .districts-lb-head span {
      color: var(--muted);
      font-size: 11.5px;
      font-weight: 500;
    }

    .districts-lb-list {
      margin: 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 4px;
      max-height: 318px;
      overflow-y: auto;
    }

    .lb-row {
      display: grid;
      grid-template-columns: 24px minmax(0, 1fr) auto;
      grid-template-areas: "rank name value" "bar bar bar";
      align-items: center;
      gap: 4px 10px;
      padding: 9px 12px 10px;
      border-radius: 10px;
      border: 1px solid transparent;
      background: #fbfdff;
      cursor: pointer;
      transition: background var(--motion), border-color var(--motion), box-shadow var(--motion);
      outline: none;
    }

    .lb-row:hover { background: var(--blue-soft); }

    .lb-row.selected {
      background: #ffffff;
      border-color: rgba(23, 105, 224, .50);
      box-shadow: var(--shadow-sm);
    }

    .lb-rank {
      grid-area: rank;
      color: var(--muted);
      font-size: 11px;
      font-weight: 700;
      font-variant-numeric: tabular-nums;
      text-align: center;
    }

    .lb-name {
      grid-area: name;
      color: var(--ink);
      font-size: 13px;
      font-weight: 600;
      letter-spacing: -0.005em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .lb-value {
      grid-area: value;
      color: var(--blue);
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: 14px;
      font-weight: 800;
      font-variant-numeric: tabular-nums;
      letter-spacing: -0.005em;
      white-space: nowrap;
    }

    .lb-bar {
      grid-area: bar;
      height: 4px;
      background: rgba(98, 148, 162, .14);
      border-radius: 999px;
      overflow: hidden;
      margin-top: 4px;
    }

    .lb-bar i {
      display: block;
      height: 100%;
      border-radius: 999px;
      transition: width var(--motion-slow);
    }

    .lb-row.green .lb-bar i { background: var(--map-good-stroke); }
    .lb-row.amber .lb-bar i { background: var(--map-mid-stroke); }
    .lb-row.red .lb-bar i   { background: var(--map-attn-stroke); }
    .lb-row.grey .lb-bar i  { background: var(--map-nodata-stroke); }

    .lb-empty {
      padding: 18px 12px;
      border: 1px dashed var(--line);
      border-radius: 12px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.35;
      background: #fbfdff;
    }

    .district-summary-card {
      border: 1px solid var(--line);
      border-radius: 18px;
      background: #fff;
      box-shadow: var(--shadow-sm);
      padding: 16px;
      display: grid;
      gap: 14px;
      min-width: 0;
    }

    .district-summary-card.empty {
      min-height: 180px;
      align-content: center;
    }

    .district-summary-head {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: start;
    }

    .district-summary-head span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 900;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    .district-summary-head h3 {
      margin: 4px 0 0;
      font-size: 21px;
      line-height: 1.1;
      letter-spacing: -0.015em;
    }

    .district-summary-value {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 12px;
      align-items: end;
      padding: 14px;
      border: 1px solid rgba(23, 105, 224, .18);
      border-radius: 14px;
      background: var(--blue-soft);
    }

    .district-summary-value strong {
      display: block;
      color: var(--blue);
      font-size: clamp(30px, 3vw, 42px);
      line-height: 1;
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      letter-spacing: -0.025em;
    }

    .district-summary-value span,
    .district-summary-value small {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
    }

    .district-summary-metrics {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
    }

    .district-summary-metric {
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 10px;
      background: #fbfdff;
      min-width: 0;
    }

    .district-summary-metric span {
      display: block;
      color: var(--muted);
      font-size: 10.5px;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: .03em;
      line-height: 1.2;
    }

    .district-summary-metric strong {
      display: block;
      margin-top: 5px;
      color: var(--ink);
      font-size: 15px;
      line-height: 1.1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .district-summary-metric small {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .district-summary-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }

    .district-summary-actions .mini-button {
      min-height: 38px;
      padding: 8px 10px;
      text-align: center;
    }

    .district-profile-card {
      position: relative;
      background: #ffffff;
      border-radius: var(--r-lg);
      box-shadow: var(--shadow-md);
      padding: 18px 22px 20px;
      display: grid;
      gap: 16px;
      overflow: hidden;
    }

    .district-profile-card::before { display: none; }

    .dpc-head {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) auto auto;
      gap: 18px;
      align-items: end;
    }

    .dpc-head-titles .eyebrow {
      color: var(--muted);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .dpc-head-titles h3 {
      margin: 4px 0 2px;
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: clamp(20px, 2vw, 26px);
      font-weight: 800;
      letter-spacing: -0.018em;
      color: var(--ink);
    }

    .dpc-head-titles p {
      margin: 0;
      color: var(--muted);
      font-size: 12.5px;
    }

    .dpc-head-stat { text-align: right; }

    .dpc-head-stat strong {
      display: block;
      color: var(--blue);
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: clamp(24px, 2.4vw, 32px);
      font-weight: 800;
      letter-spacing: -0.022em;
      font-variant-numeric: tabular-nums;
      line-height: 1;
    }

    .dpc-head-stat span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .dpc-head-chips {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }

    .dpc-metrics {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 10px;
    }

    .dpc-metric {
      display: grid;
      gap: 3px;
      padding: 12px 14px;
      background: rgba(98, 148, 162, .04);
      border: 1px solid rgba(98, 148, 162, .15);
      border-radius: 10px;
    }

    .dpc-metric-label {
      color: var(--muted);
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .dpc-metric-value {
      color: var(--ink);
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: 17px;
      font-weight: 800;
      letter-spacing: -0.01em;
      font-variant-numeric: tabular-nums;
    }

    .dpc-metric-note {
      color: var(--muted);
      font-size: 11px;
      line-height: 1.3;
    }

    .dpc-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }

    .dpc-cross {
      display: grid;
      gap: 10px;
      padding-top: 14px;
      border-top: 1px dashed rgba(20, 30, 35, .1);
    }

    .dpc-cross-head {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 10px;
    }

    .dpc-cross-head strong {
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: 14px;
      font-weight: 800;
      letter-spacing: -0.012em;
      color: var(--ink);
    }

    .dpc-cross-head span {
      color: var(--muted);
      font-size: 11.5px;
      font-weight: 500;
    }

    .dpc-cross-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 8px;
    }

    .dpc-cross-tile {
      position: relative;
      display: grid;
      gap: 4px;
      padding: 11px 13px;
      border: 1px solid rgba(20, 30, 35, .07);
      border-radius: 10px;
      background: #ffffff;
      text-align: left;
      cursor: pointer;
      transition: transform var(--motion), border-color var(--motion), box-shadow var(--motion);
    }

    .dpc-cross-tile::before { display: none; }

    .dpc-cross-tile:hover {
      transform: translateY(-1px);
      border-color: rgba(27, 77, 90, .3);
      box-shadow: var(--shadow-sm);
    }

    .dpc-cross-label {
      color: var(--muted);
      font-size: 10.5px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .dpc-cross-value {
      color: var(--ink);
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: 16px;
      font-weight: 800;
      letter-spacing: -0.01em;
      font-variant-numeric: tabular-nums;
    }

    .dpc-tasks-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      padding-top: 14px;
      border-top: 1px dashed rgba(20, 30, 35, .1);
    }

    .dpc-task-panel {
      display: grid;
      gap: 8px;
      padding: 12px 14px;
      border: 1px solid rgba(20, 30, 35, .07);
      border-radius: 10px;
      background: rgba(98, 148, 162, .04);
    }

    .dpc-task-head {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 8px;
    }

    .dpc-task-head strong {
      font-family: "Inter Tight", "Inter", "Segoe UI", Arial, sans-serif;
      font-size: 13px;
      font-weight: 800;
      letter-spacing: -0.005em;
      color: var(--ink);
    }

    .dpc-task-list {
      list-style: none;
      margin: 0;
      padding: 0;
      display: grid;
      gap: 6px;
      max-height: 280px;
      overflow-y: auto;
    }

    .dpc-task-item {
      display: grid;
      grid-template-columns: 56px minmax(0, 1fr) auto;
      gap: 8px;
      align-items: center;
      padding: 8px 10px;
      background: #ffffff;
      border: 1px solid rgba(20, 30, 35, .06);
      border-radius: 8px;
    }

    .dpc-task-id {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 3px 6px;
      border-radius: 6px;
      background: rgba(27, 77, 90, .08);
      color: var(--blue);
      font-size: 10px;
      font-weight: 800;
      letter-spacing: 0.02em;
      font-variant-numeric: tabular-nums;
      white-space: nowrap;
    }

    .dpc-task-body { display: grid; gap: 2px; min-width: 0; }

    .dpc-task-body strong {
      color: var(--ink);
      font-size: 12px;
      font-weight: 600;
      line-height: 1.3;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .dpc-task-body small {
      color: var(--muted);
      font-size: 10.5px;
      line-height: 1.3;
    }

    .dpc-task-empty {
      margin: 0;
      color: var(--muted);
      font-size: 12px;
      font-style: italic;
    }

    .dpc-task-more {
      margin: 0;
      color: var(--muted);
      font-size: 11px;
      text-align: center;
      padding-top: 4px;
    }

    @media (max-width: 720px) {
      .dpc-tasks-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 1080px) {
      .districts-grid { grid-template-columns: 1fr; }
      .dpc-head { grid-template-columns: 1fr 1fr; }
      .districts-map-canvas { min-height: 360px; }
    }

    @media (max-width: 720px) {
      .districts-head { grid-template-columns: 1fr; }
      .districts-head-actions { display: grid; grid-template-columns: 1fr; gap: 8px; }
      .districts-control select, .districts-control input { min-width: 0; width: 100%; }
      .dpc-head { grid-template-columns: 1fr; }
      .district-data-layers { grid-template-columns: 1fr; }
      .district-summary-metrics, .district-summary-actions { grid-template-columns: 1fr; }
      .districts-grid, .districts-map, .districts-side, .district-profile-card { width: 100%; max-width: 100%; min-width: 0; }
      .districts-map { padding: 14px; }
      .districts-map-head { display: block; }
      .districts-map-head strong { font-size: 15px; line-height: 1.18; }
      .districts-map-head span { font-size: 11px; max-width: 100%; }
      .districts-map-canvas { width: 100%; max-width: 100%; min-height: 270px; padding: 10px 6px; }
      .andijan-map { width: 100%; max-width: 100%; transform: scale(.92); transform-origin: center; }
      .districts-map-legend { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); justify-content: stretch; overflow: visible; }
      .legend-chip { justify-content: center; padding: 4px 6px; font-size: 10.5px; min-width: 0; white-space: normal; }
    }

    .small-stat {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: #fff;
      min-width: 0;
    }

    .small-stat span { display: block; color: var(--muted); font-size: 11px; font-weight: 900; }
    .small-stat strong { display: block; margin-top: 4px; color: var(--blue); font-size: 24px; line-height: 1; }
    .small-stat small { display: block; margin-top: 5px; color: var(--muted); font-size: 12px; }

    .task-board {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }

    .board-col {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fbfdff;
      min-height: 420px;
      overflow: hidden;
    }

    .board-col h3 {
      padding: 12px 12px 10px;
      font-size: 15px;
      border-bottom: 1px solid var(--line);
      background: #fff;
    }

    .board-col .task-list { padding: 10px; }

    .task-workspace {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(300px, .38fr);
      gap: 16px;
      align-items: start;
    }

    .task-filter {
      display: grid;
      grid-template-columns: minmax(260px, .7fr) minmax(220px, .3fr) auto;
      gap: 12px;
      align-items: end;
      margin-bottom: 16px;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255,255,255,.92);
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
    }

    .task-filter label {
      display: grid;
      gap: 4px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
      min-width: 0;
    }

    .task-filter select,
    .task-filter input {
      width: 100%;
      min-width: 0;
    }

    .task-filter.report-filter {
      grid-template-columns: minmax(220px, .9fr) minmax(240px, 1fr) minmax(150px, .55fr) minmax(220px, .85fr);
    }

    .task-filter.report-filter.execution-filter {
      grid-template-columns: repeat(5, minmax(135px, 1fr)) auto;
    }

    .task-summary-strip {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .task-summary-strip.execution-overview {
      grid-template-columns: minmax(220px, .8fr) minmax(460px, 1.25fr) minmax(110px, .28fr) minmax(170px, .42fr);
      align-items: center;
      padding: 16px;
      border: 1px solid var(--line);
      border-radius: 18px;
      background: #fff;
      box-shadow: var(--shadow);
    }

    .task-summary-strip.execution-overview .exec-status-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .task-summary-copy {
      display: grid;
      align-content: center;
      gap: 5px;
      min-width: 0;
    }

    .task-summary-copy span {
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    .task-summary-copy strong {
      color: var(--ink);
      font-size: 18px;
      line-height: 1.18;
      overflow-wrap: anywhere;
    }

    .task-summary-copy small {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .task-summary-strip .exec-status-pill.active {
      border-color: rgba(23, 105, 224, .42);
      background: #eef6ff;
      box-shadow: 0 10px 22px rgba(23, 105, 224, .12);
    }

    .exec-status-pill.blue strong { color: var(--blue); }

    .task-advanced-filters {
      margin: -4px 0 16px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255,255,255,.78);
      overflow: hidden;
    }

    .task-advanced-filters summary {
      cursor: pointer;
      padding: 10px 12px;
      color: var(--blue);
      font-size: 12px;
      font-weight: 950;
      list-style-position: inside;
    }

    .task-advanced-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(180px, 1fr));
      gap: 12px;
      padding: 0 12px 12px;
    }

    .task-advanced-grid label {
      display: grid;
      gap: 4px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .task-advanced-grid select {
      width: 100%;
      min-width: 0;
    }

    .task-focus {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      padding: 14px;
      box-shadow: 0 16px 34px rgba(16, 48, 82, .08);
    }

    .task-focus h3 {
      font-size: 19px;
      line-height: 1.15;
    }

    .task-focus p {
      margin-top: 7px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.4;
    }

    .task-side-stack {
      display: grid;
      gap: 10px;
      margin-top: 14px;
    }

    .task-side-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: center;
      padding: 11px 0;
      border-top: 1px solid var(--line);
    }

    .task-side-row:first-child {
      border-top: 0;
      padding-top: 0;
    }

    .task-side-row strong {
      display: block;
      color: var(--ink);
      font-size: 13px;
      line-height: 1.25;
    }

    .task-side-row span {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
    }

    .task-groups {
      display: grid;
      gap: 12px;
    }

    .task-group {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fbfdff;
      overflow: hidden;
    }

    .task-group-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      padding: 12px 14px;
      border-bottom: 1px solid var(--line);
      background: #fff;
    }

    .task-group-head h3 {
      font-size: 15px;
      line-height: 1.2;
    }

    .task-group .task-list {
      padding: 12px;
    }

    .task-card.compact {
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: start;
    }

    .task-card.compact header {
      display: grid;
      gap: 6px;
    }

    .task-card.compact .task-actions {
      display: grid;
      gap: 6px;
      justify-items: end;
    }

    .modal-bg {
      position: fixed;
      inset: 0;
      z-index: 80;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: rgba(24, 33, 35, .42);
    }

    .modal-bg.open { display: flex; }

    .modal {
      width: min(760px, 100%);
      max-height: min(760px, 92vh);
      overflow: auto;
      border-radius: 8px;
      background: #fff;
      border: 1px solid var(--line);
      box-shadow: 0 24px 70px rgba(20, 28, 30, .24);
    }

    .modal-head {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 16px;
      align-items: start;
      padding: 18px 20px;
      border-bottom: 1px solid var(--line);
      background: var(--surface);
    }

    .modal-title {
      font-size: 19px;
      line-height: 1.2;
      font-weight: 900;
    }

    .modal-sub {
      margin-top: 6px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .modal-close {
      width: 36px;
      height: 36px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      color: var(--muted);
      font-size: 20px;
      line-height: 1;
    }

    .modal-body {
      padding: 18px 20px 20px;
      display: grid;
      gap: 16px;
    }

    .modal-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    .modal-field {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px;
      background: var(--surface);
      min-width: 0;
    }

    .modal-field span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 900;
      text-transform: uppercase;
    }

    .modal-field strong {
      display: block;
      margin-top: 4px;
      font-size: 13px;
      line-height: 1.25;
      overflow-wrap: anywhere;
    }

    .modal textarea {
      min-height: 96px;
      resize: vertical;
    }

    .modal-field input,
    .modal-field select,
    .modal-field textarea {
      width: 100%;
      margin-top: 7px;
      min-width: 0;
    }

    .report-context {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 8px;
    }

    .context-pill {
      min-width: 0;
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px 11px;
      background: #fff;
    }

    .context-pill span {
      display: block;
      color: var(--muted);
      font-size: 10px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .context-pill strong {
      display: block;
      margin-top: 4px;
      font-size: 13px;
      line-height: 1.2;
      overflow-wrap: anywhere;
    }

    .advanced-report {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      overflow: hidden;
    }

    .advanced-report summary {
      cursor: pointer;
      padding: 11px 12px;
      color: var(--blue);
      font-size: 12px;
      font-weight: 950;
      list-style-position: inside;
    }

    .advanced-report .modal-grid {
      padding: 0 12px 12px;
    }

    .field-error {
      display: none;
      margin-top: 5px;
      color: var(--red);
      font-size: 12px;
      font-weight: 800;
      text-transform: none;
    }

    .field-error.show {
      display: block;
    }

    .evidence-list {
      display: grid;
      gap: 8px;
    }

    .evidence-item {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 10px;
      background: #fff;
    }

    .evidence-item span {
      color: var(--muted);
      font-size: 11px;
      font-weight: 850;
    }

    .profile-top {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 300px;
      gap: 16px;
      align-items: start;
      margin-bottom: 16px;
    }

    .profile-filter {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255,255,255,.9);
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
    }

    .profile-filter label {
      display: grid;
      gap: 4px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .profile-filter select {
      min-width: min(360px, 100%);
      text-transform: none;
      font-weight: 700;
    }

    .district-kpis {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .district-kpi {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      padding: 12px;
      min-width: 0;
      box-shadow: 0 10px 24px rgba(16, 48, 82, .07);
      color: inherit;
      text-align: left;
    }

    .district-kpi.active,
    .district-kpi:hover {
      border-color: var(--blue);
      background: #f5f9ff;
    }

    .district-kpi span { display: block; color: var(--muted); font-size: 11px; font-weight: 950; text-transform: uppercase; }
    .district-kpi strong { display: block; margin-top: 5px; color: var(--blue); font-size: 21px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .district-kpi small { display: block; margin-top: 5px; color: var(--muted); font-size: 11px; line-height: 1.25; }

    .profile-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
      gap: 16px;
      align-items: start;
    }

    .profile-focus {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      overflow: hidden;
      box-shadow: 0 16px 36px rgba(16, 48, 82, .08);
    }

    .profile-hero {
      padding: 18px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 14px;
      align-items: start;
      border-bottom: 1px solid var(--line);
      background:
        linear-gradient(135deg, rgba(34, 105, 214, .1), rgba(255,255,255,0) 58%),
        #fff;
    }

    .profile-hero h3 {
      font-size: 25px;
      line-height: 1.05;
      color: var(--ink);
    }

    .profile-hero p {
      margin-top: 7px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.4;
      max-width: 76ch;
    }

    .profile-main-value {
      text-align: right;
      min-width: 150px;
    }

    .profile-main-value strong {
      display: block;
      color: var(--blue);
      font-size: 34px;
      line-height: .95;
      white-space: nowrap;
    }

    .profile-main-value span {
      display: block;
      margin-top: 6px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 900;
      text-transform: uppercase;
    }

    .profile-metrics {
      padding: 14px;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .profile-metric {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      min-width: 0;
      background: #fbfdff;
    }

    .profile-metric span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      text-transform: uppercase;
    }

    .profile-metric strong {
      display: block;
      margin-top: 6px;
      color: var(--ink);
      font-size: 22px;
      line-height: 1.05;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .profile-metric small {
      display: block;
      margin-top: 6px;
      min-height: 28px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .profile-actions {
      display: grid;
      gap: 10px;
    }

    .profile-side-stat {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: center;
      padding: 11px 0;
      border-bottom: 1px solid var(--line);
    }

    .profile-side-stat:last-child { border-bottom: 0; }
    .profile-side-stat span { display: block; color: var(--muted); font-size: 12px; }
    .profile-side-stat strong { color: var(--ink); font-size: 16px; }

    .profile-secondary {
      margin-top: 16px;
    }

    .profile-secondary .district-kpis {
      grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .profile-bottom-grid {
      display: grid;
      grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
      gap: 16px;
      align-items: start;
      margin-top: 16px;
    }

    .profile-report {
      display: grid;
      gap: 10px;
    }

    .report-item {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: #fbfdff;
      display: grid;
      gap: 8px;
    }

    .report-item header {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
    }

    .report-item strong {
      color: var(--ink);
      font-size: 14px;
      line-height: 1.2;
    }

    .report-item p {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .execution-command {
      display: grid;
      grid-template-columns: minmax(260px, .85fr) minmax(420px, 1.2fr) minmax(190px, .45fr);
      gap: 12px;
      align-items: stretch;
      margin-bottom: 16px;
      padding: 16px;
      border: 1px solid var(--line);
      border-radius: 18px;
      background: #fff;
      box-shadow: var(--shadow);
    }

    .execution-command-copy {
      display: grid;
      align-content: center;
      gap: 6px;
      min-width: 0;
    }

    .execution-command-copy span {
      color: var(--muted);
      font-size: 11px;
      font-weight: 950;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    .execution-command-copy strong {
      color: var(--ink);
      font-size: 19px;
      line-height: 1.16;
    }

    .execution-command-copy small {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .execution-status-grid {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 8px;
      min-width: 0;
    }

    .execution-status-btn {
      min-width: 0;
      border: 1px solid var(--line);
      border-radius: 12px;
      background: #fbfdff;
      padding: 10px 11px;
      text-align: left;
      display: grid;
      align-content: center;
      gap: 4px;
      color: var(--ink);
      transition: border-color var(--motion), box-shadow var(--motion), transform var(--motion);
    }

    .execution-status-btn:hover {
      border-color: rgba(23, 105, 224, .35);
      box-shadow: var(--shadow-sm);
      transform: translateY(-1px);
    }

    .execution-status-btn.active {
      border-color: rgba(23, 105, 224, .42);
      background: #eef6ff;
      box-shadow: 0 10px 22px rgba(23, 105, 224, .12);
    }

    .execution-status-btn span {
      color: var(--muted);
      font-size: 10px;
      font-weight: 950;
      letter-spacing: .025em;
      line-height: 1.15;
      text-transform: uppercase;
    }

    .execution-status-btn strong {
      font-size: 24px;
      line-height: 1;
      font-weight: 950;
      color: var(--ink);
      font-variant-numeric: tabular-nums;
    }

    .execution-status-btn.green strong { color: #16a34a; }
    .execution-status-btn.amber strong { color: #d97706; }
    .execution-status-btn.red strong { color: #ef4444; }
    .execution-status-btn.blue strong { color: var(--blue); }

    .execution-actions {
      display: grid;
      gap: 8px;
      align-content: center;
    }

    .execution-flow {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .execution-step {
      display: grid;
      gap: 5px;
      padding: 12px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: rgba(255,255,255,.84);
    }

    .execution-step span {
      color: var(--blue);
      font-size: 12px;
      font-weight: 950;
    }

    .execution-step strong {
      color: var(--ink);
      font-size: 13px;
      line-height: 1.25;
    }

    .execution-step small {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .execution-workspace {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(320px, .42fr);
      gap: 16px;
      align-items: start;
      margin-bottom: 16px;
    }

    .execution-lane {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      overflow: hidden;
      box-shadow: 0 14px 28px rgba(16, 48, 82, .07);
    }

    .execution-lane-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      padding: 13px 14px;
      border-bottom: 1px solid var(--line);
      background: #fbfdff;
    }

    .execution-lane-head h3 {
      font-size: 15px;
      line-height: 1.2;
    }

    .execution-card-list {
      display: grid;
      gap: 10px;
      padding: 12px;
    }

    .execution-card {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 12px;
      background: #fff;
      display: grid;
      gap: 9px;
      min-width: 0;
    }

    .execution-card header {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: start;
    }

    .execution-card strong {
      color: var(--ink);
      font-size: 13px;
      line-height: 1.3;
      overflow-wrap: anywhere;
    }

    .execution-card p {
      color: var(--muted);
      font-size: 12px;
      line-height: 1.35;
    }

    .execution-card-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 6px 10px;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.25;
    }

    .execution-impact {
      display: grid;
      gap: 10px;
    }

    .impact-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: center;
      padding: 10px 0;
      border-top: 1px solid var(--line);
    }

    .impact-row:first-child {
      border-top: 0;
      padding-top: 0;
    }

    .impact-row strong {
      display: block;
      color: var(--ink);
      font-size: 13px;
      line-height: 1.25;
    }

    .impact-row span {
      display: block;
      margin-top: 3px;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.25;
    }

    .execution-empty {
      padding: 18px;
      border: 1px dashed #cbd8e8;
      border-radius: 8px;
      background: #fbfdff;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.45;
    }

    .history-list {
      display: grid;
      gap: 6px;
      margin-top: 8px;
    }

    .history-list span {
      display: block;
      color: var(--muted);
      font-size: 11px;
      line-height: 1.35;
    }

    .profile-task-list {
      display: grid;
      gap: 10px;
    }

    .link-grid {
      display: grid;
      grid-template-columns: minmax(210px, .8fr) minmax(0, 1.15fr) minmax(240px, .85fr);
      gap: 12px;
      align-items: stretch;
    }

    .link-box {
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      padding: 13px;
      min-width: 0;
    }

    .link-box.active { border-color: var(--blue); background: var(--blue-soft); }
    .link-box h3 { font-size: 15px; }
    .link-box p { margin-top: 7px; color: var(--muted); font-size: 12px; line-height: 1.35; }

    .hidden { display: none !important; }

    @media (max-width: 1180px) {
      :root { --nav-w: 86px; }
      .side-title span, .side-title strong, .nav-btn span { display: none; }
      .nav-btn { grid-template-columns: 1fr; justify-items: center; }
      .grid-3, .grid-2, .profile-top { grid-template-columns: 1fr; }
      .profile-filter { align-items: stretch; flex-direction: column; }
      .district-workspace, .district-context { grid-template-columns: 1fr; }
      .task-workspace, .task-filter, .task-filter.report-filter, .task-summary-strip, .workflow-strip { grid-template-columns: 1fr; }
      .execution-command, .execution-flow, .execution-workspace { grid-template-columns: 1fr; }
      .execution-status-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .modal-grid, .report-context { grid-template-columns: 1fr; }
      .profile-grid, .profile-bottom-grid { grid-template-columns: 1fr; }
      .profile-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .profile-secondary .district-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .district-context-actions { justify-content: flex-start; }
      .district-preview { position: static; }
      .dashboard-module-tabs { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .scoreline { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .scoreline.execution-strip { grid-template-columns: 1fr; }
      .scoreline-copy, .score-actions { grid-column: 1 / -1; }
      .exec-status-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .task-summary-strip.execution-overview .exec-status-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
      .front-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .front-kpis.module-kpis.macro-layout { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .front-kpis.module-kpis.employment-layout { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .front-kpi:nth-child(3) { border-right: 0; }
      .front-kpi:nth-child(n+4) { border-top: 1px solid var(--line); }
      .district-card { grid-template-columns: minmax(150px, .8fr) repeat(2, minmax(110px, 1fr)); }
      .debt-cell { justify-items: start; border-left: 0; border-top: 1px solid var(--line); padding: 8px 0 0; }
      .metric-cell { border-left: 0; border-top: 1px solid var(--line); padding: 8px 0 0; }
      .district-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 760px) {
      .mast { grid-template-columns: 1fr; gap: 10px; }
      .brand { grid-template-columns: 66px minmax(0, 1fr); gap: 10px; }
      .brand-mark { width: 66px; height: 50px; font-size: 17px; }
      .brand h1 { font-size: 18px; line-height: 1.12; }
      .brand p { display: none; }
      .kpi-mark { display: none; }
      .shell { grid-template-columns: 1fr; }
      .sidebar {
        position: static;
        height: auto;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 6px;
        overflow: visible;
        padding: 10px;
      }
      .side-title { display: none; }
      .nav-btn { min-width: 0; margin: 0; gap: 6px; padding: 8px 4px; }
      .nav-btn span { display: block; font-size: 10.5px; line-height: 1.1; white-space: normal; }
      .main { padding: 14px; min-width: 0; max-width: 100vw; overflow-x: hidden; }
      .page-head { display: grid; }
      .toolbar { justify-content: flex-start; }
      .dashboard-module-tabs, .module-heading, .scoreline, .front-kpis, .front-kpis.module-kpis.macro-layout, .front-kpis.module-kpis.employment-layout, .workflow, .task-board, .cards-3, .command-summary, .context-strip, .district-controls, .link-grid, .district-kpis { grid-template-columns: 1fr; }
      .execution-command, .execution-status-grid, .execution-flow, .execution-workspace { grid-template-columns: 1fr; }
      .task-summary-strip.execution-overview .exec-status-grid, .task-advanced-grid { grid-template-columns: 1fr; }
      .profile-grid, .profile-bottom-grid, .profile-metrics, .profile-secondary .district-kpis { grid-template-columns: 1fr; }
      .profile-hero { grid-template-columns: 1fr; }
      .profile-main-value { text-align: left; }
      .kpi-signal { grid-template-columns: 1fr; }
      .kpi-monitor-grid { grid-template-columns: 1fr; }
      .kpi-monitor-head { grid-template-columns: 46px minmax(0, 1fr); }
      .kpi-monitor-head .mini-button { grid-column: 1 / -1; justify-self: start; }
      .exec-status-grid { grid-template-columns: 1fr; }
      .annual-plan { grid-column: 1 / -1; text-align: left; }
      .quarter-matrix { grid-template-columns: 1fr; }
      .quarter-row { min-height: 0; }
      .macro-layout-card .macro-growth-panel,
      .finance-sector-grid,
      .finance-card-body.two-col,
      .finance-metric-row,
      .finance-metric-row.two,
      .finance-summary-grid,
      .finance-quarter-grid,
      .macro-growth-overview,
      .macro-period-grid,
      .macro-composition-grid,
      .budget-invest-summary,
      .budget-invest-body,
      .budget-periods-grid { grid-template-columns: 1fr; }
      .macro-hero-card { grid-template-columns: 1fr; text-align: center; }
      .macro-hero-copy { text-align: center; justify-items: center; }
      .macro-layout-card .macro-composition-card { grid-template-columns: 36px minmax(0, .7fr) minmax(110px, 1fr) auto; }
      .industry-driver-card { grid-template-columns: 48px minmax(0, 1fr); }
      .industry-driver-arrow { display: none; }
      .industry-driver-metrics { grid-template-columns: 1fr; gap: 10px; }
      .industry-driver-divider { display: none; }
      .budget-invest-summary > div { border-right: 0; border-bottom: 1px solid var(--line); }
      .budget-invest-summary > div:last-child { border-bottom: 0; }
      .composition-grid, .driver-grid { grid-template-columns: 1fr; }
      .front-kpi { border-right: 0; border-bottom: 1px solid var(--line); }
      .district-card { grid-template-columns: 1fr; }
      .metric-cell, .debt-cell { border-left: 0; border-top: 1px solid var(--line); padding: 8px 0 0; justify-items: start; }
      .task-card.compact { grid-template-columns: 1fr; }
      .task-card.compact .task-actions { justify-items: start; }
      .modal-bg { padding: 10px; align-items: flex-end; }
      .modal { max-height: 92vh; }
      .modal-head { padding: 14px; }
      .modal-body { padding: 14px; }
      table { min-width: 720px; }
      .table-scroll { overflow-x: auto; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="mast">
      <div class="brand">
        <div class="brand-mark">CERR</div>
        <div>
          <h1>Андижон вилояти мониторинг платформаси</h1>
          <p>KPI · туманлар · ижро мониторинги</p>
        </div>
      </div>
    </div>
  </header>

  <div class="shell">
    <aside class="sidebar">
      <div class="side-title">
        <strong>Бошқарув маркази</strong>
      </div>
      <button class="nav-btn active" data-page="dashboard" title="KPI"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 13h6V4H4v9Zm10 7h6V4h-6v16ZM4 20h6v-4H4v4Z"/></svg><span>KPI</span></button>
      <button class="nav-btn" data-page="tasks" title="Топшириқлар"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 11l2 2 4-5M5 4h14v16H5z"/></svg><span>Топшириқлар</span></button>
      <button class="nav-btn" data-page="districts" title="Туманлар"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-7h6v7"/></svg><span>Туманлар</span></button>
      <button class="nav-btn" data-page="execution" title="Ижро мониторинги"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 5h16M4 12h16M4 19h10M8 9l2 2 4-4"/></svg><span>Ижро</span></button>
    </aside>

    <main class="main">
      <section class="page-head">
        <div>
          <div class="eyebrow" id="pageEyebrow">Андижон вилояти</div>
          <h2 id="pageTitle">KPI</h2>
          <p id="pageSubtitle">Йиллик мақсад, чораклар кесими ва ижро ҳолати.</p>
        </div>
        <div class="toolbar">
          <div class="segmented" id="periodTabs"></div>
          <select id="sectorFilter" aria-label="Сектор фильтри"></select>
          <input id="searchBox" type="search" placeholder="Қидириш">
        </div>
      </section>

      <section id="dashboardPage"></section>
      <section id="tasksPage" class="hidden"></section>
      <section id="districtsPage" class="hidden"></section>
      <section id="profilePage" class="hidden"></section>
      <section id="executionPage" class="hidden"></section>
    </main>
  </div>

  <div class="modal-bg" id="taskModalBg" aria-hidden="true">
    <div class="modal" id="taskModal" role="dialog" aria-modal="true" aria-labelledby="taskModalTitle"></div>
  </div>
  <div class="modal-bg" id="reportModalBg" aria-hidden="true">
    <div class="modal" id="reportModal" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle"></div>
  </div>

  <script>
    const DATA = __DATA__;

    const periods = [
      { id: "h1", label: "II чорак", suffix: "ярим йил" },
      { id: "m9", label: "III чорак", suffix: "9 ой" },
      { id: "year", label: "Йиллик", suffix: "йил" }
    ];

    const kpiDefs = [
      { id: "grp", label: "ЯҲМ", short: "ЯҲМ", sector: "Макро иқтисодиёт", icon: "trend" },
      { id: "industry", label: "Саноат маҳсулотлари", short: "Саноат", sector: "Макро иқтисодиёт", icon: "factory" },
      { id: "inflation", label: "Инфляция ва асосий озиқ-овқат нархлари", short: "Инфляция", sector: "Инфляция", icon: "price" },
      { id: "budget", label: "Бюджет тушумлари", short: "Бюджет", sector: "Бюджет", icon: "bank" },
      { id: "investment", label: "Хорижий инвестициялар", short: "Инвестиция", sector: "Хорижий инвестиция", icon: "rocket" },
      { id: "export", label: "Экспорт ҳажми", short: "Экспорт", sector: "Экспорт", icon: "globe" },
      { id: "unemployment", label: "Ишсизлик даражаси", short: "Ишсизлик", sector: "Бандлик ва камбағаллик", icon: "users" },
      { id: "poverty", label: "Камбағаллик даражаси", short: "Камбағаллик", sector: "Бандлик ва камбағаллик", icon: "users" },
      { id: "small_business_share", label: "Кичик тадбиркорликнинг ЯҲМдаги улуши", short: "Кичик бизнес улуши", sector: "Бандлик ва камбағаллик", icon: "briefcase" }
    ];

    const macroComponentDefs = [
      { id: "industry", label: "Саноат маҳсулотлари", short: "Саноат", sector: "ЯҲМ таркиби", icon: "factory", macroIndex: 1 },
      { id: "agriculture", label: "Қишлоқ хўжалиги маҳсулотлари", short: "Қишлоқ хўжалиги", sector: "ЯҲМ таркиби", icon: "trend", macroIndex: 2 },
      { id: "construction", label: "Қурилиш ишлари", short: "Қурилиш", sector: "ЯҲМ таркиби", icon: "bank", macroIndex: 3 },
      { id: "services", label: "Бозор хизматлари", short: "Хизматлар", sector: "ЯҲМ таркиби", icon: "globe", macroIndex: 4 }
    ];

    const districtOnlyDefs = [
      { id: "localization", label: "Маҳаллийлаштириш дастури", short: "Маҳаллийлаштириш", sector: "Саноат", icon: "factory" },
      { id: "energy_electricity", label: "Электр энергиясини тежаш", short: "Электр тежаш", sector: "Саноат", icon: "trend" },
      { id: "energy_gas", label: "Табиий газни тежаш", short: "Газ тежаш", sector: "Саноат", icon: "trend" },
      { id: "budget_investment", label: "Бюджет инвестициялари ўзлаштирилиши", short: "Бюджет инвест", sector: "Бюджет инвестициялари", icon: "bank" },
      { id: "jobs", label: "Доимий ишга жойлаштириш", short: "Ишга жойлаштириш", sector: "Бандлик ва камбағаллик", icon: "users" },
      { id: "legalization", label: "Норасмий бандларни легаллаштириш", short: "Легаллаштириш", sector: "Бандлик ва камбағаллик", icon: "users" },
      { id: "mfy_clear", label: "Камбағаллик ва ишсизликдан холи МФЙлар", short: "Камбағалликдан холи МФЙлар", sector: "Бандлик ва камбағаллик", icon: "users" },
      { id: "microprojects", label: "Микролойиҳалар", short: "Микролойиҳа", sector: "Бандлик ва камбағаллик", icon: "users" }
    ];

    const districtKpiRegistry = {
      grp: { layer: "composition", source: "1.2, 1.4, 1.5-жадваллар", periods: ["q1", "h1", "m9", "year"], note: "ЯҲМ туман кесимида берилмаган; саноат, қишлоқ хўжалиги ва хизматлар орқали кўрилади." },
      industry: { layer: "excel", source: "1.2-жадвал", periods: ["q1", "h1", "m9", "year"], note: "Excel туман KPI: ҳажм ва ўсиш суръати." },
      agriculture: { layer: "excel", source: "1.4-жадвал", periods: ["q1", "h1", "m9", "year"], note: "Excel туман KPI: ҳажм ва ўсиш суръати." },
      services: { layer: "excel", source: "1.5-жадвал", periods: ["q1", "h1", "m9", "year"], note: "Excel туман KPI: ҳажм ва ўсиш суръати." },
      localization: { layer: "excel", source: "1.3-жадвал", periods: ["h1", "year"], note: "Excel туман мақсади: лойиҳа сони ва қиймати." },
      energy_electricity: { layer: "excel", source: "1.3-жадвал", periods: ["h1", "year"], note: "Excel туман мақсади: тежаладиган электр энергияси." },
      energy_gas: { layer: "excel", source: "1.3-жадвал", periods: ["h1", "year"], note: "Excel туман мақсади: тежаладиган табиий газ." },
      inflation: { layer: "excel-proxy", source: "2.2-жадвал", periods: ["year"], note: "Туман инфляция фоизи эмас; нарх барқарорлиги учун омбор инфратузилмаси." },
      budget: { layer: "excel", source: "3-жадвал", periods: ["q2", "h1", "year"], note: "Excel туман KPI: режа, кутилиш/амалда ва ижро." },
      budget_investment: { layer: "excel", source: "4.1-жадвал", periods: ["q1", "h1", "year"], note: "Excel туман KPI: лимит, объектлар ва ўзлаштириш." },
      investment: { layer: "excel", source: "4.2-жадвал", periods: ["q1", "h1", "year"], note: "Excel туман KPI: режа/прогноз, кутилиш, лойиҳа ва иш ўрни." },
      export: { layer: "excel", source: "5.1-жадвал", periods: ["q1", "h1", "year"], note: "Excel туман KPI: экспорт ҳажми, ўсиш ва экспортчилар." },
      unemployment: { layer: "excel-target", source: "6-жадвал", periods: ["h1", "year"], note: "Excel туман мақсади: ишсизлик даражаси чегараси." },
      poverty: { layer: "excel-target", source: "6-жадвал", periods: ["h1", "year"], note: "Excel туман мақсади: камбағаллик даражаси чегараси." },
      small_business_share: { layer: "guarantee-target", source: "0_Чора-тадбир · 74-қатор", periods: ["year"], note: "Кафолат хати KPI: вилоят даражасидаги кичик тадбиркорлик улуши." },
      jobs: { layer: "excel-target", source: "6-жадвал", periods: ["h1", "year"], note: "Excel туман мақсади: доимий ишга жойлаштириш." },
      legalization: { layer: "excel-target", source: "6-жадвал", periods: ["h1", "year"], note: "Excel туман мақсади: норасмий бандларни легаллаштириш." },
      mfy_clear: { layer: "excel-target", source: "6-жадвал", periods: ["h1", "year"], note: "Excel туман мақсади: камбағаллик ва ишсизликдан холи МФЙлар." },
      microprojects: { layer: "excel-target", source: "6-жадвал", periods: ["h1", "year"], note: "Excel туман мақсади: микролойиҳалар." }
    };

    const state = {
      page: "dashboard",
      period: "h1",
      dashboardModule: "macro",
      kpi: "grp",
      district: null,
      sector: "all",
      search: "",
      districtSort: "attention",
      taskModule: "all",
      taskStatus: "open",
      taskPeriod: "all",
      taskDistrict: "all",
      reportStatus: "all",
      reportPeriod: "all",
      reportDistrict: "all"
    };
    let renderedPage = null;

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const h = value => String(value ?? "").replace(/[&<>"']/g, ch => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    }[ch]));

    function n(value) {
      if (value === null || value === undefined || value === "" || Number.isNaN(Number(value))) return null;
      return Number(value);
    }

    function fmt(value, digits = 1) {
      const num = n(value);
      if (num === null) return "—";
      return new Intl.NumberFormat("uz-Cyrl-UZ", { maximumFractionDigits: digits, minimumFractionDigits: num % 1 ? 1 : 0 }).format(num);
    }

    function displayValue(value, unit = "", compact = true) {
      const num = n(value);
      if (num === null) return "—";
      if (unit.includes("минг доллар")) return `${fmt(num / 1000, compact ? 1 : 2)} млн $`;
      if (unit.includes("млн доллар")) {
        if (compact && Math.abs(num) >= 1000) return `${fmt(num / 1000, 1)} млрд $`;
        return `${fmt(num, compact ? 1 : 2)} млн $`;
      }
      if (unit.includes("млрд сўм")) {
        if (compact && Math.abs(num) >= 1000) return `${fmt(num / 1000, 1)} трлн сўм`;
        return `${fmt(num, 1)} млрд сўм`;
      }
      if (unit.includes("млн сўм")) return `${fmt(num / 1000, 1)} млрд сўм`;
      if (unit.includes("%")) return `${fmt(num, 1)}%`;
      if (unit.includes("минг нафар")) return `${fmt(num, 1)} минг`;
      return `${fmt(num, compact ? 1 : 2)} ${unit}`.trim();
    }

    function planValue(row, compact = true) {
      if (!row) return "—";
      if (row.planText) return row.planText;
      const value = displayValue(row.plan, row.unit, compact);
      return row.max && value !== "—" ? `≤ ${value}` : value;
    }

    function factValue(row, compact = true) {
      if (!row) return "—";
      if (row.factText) return row.factText;
      return displayValue(row.fact, row.unit, compact);
    }

    function periodSourceKind(def, period, row) {
      const id = typeof def === "string" ? def : def?.id;
      if (row?.reportStatus === "approved") return "actual";
      if (period === "q1" && (n(row?.fact) !== null || n(row?.growth) !== null)) return "actual";
      if (["budget", "budget_investment", "investment", "export"].includes(id) && period !== "q1" && (n(row?.fact) !== null || n(row?.execution) !== null)) return "expected";
      if (["inflation", "unemployment", "poverty", "small_business_share"].includes(id)) return "target";
      return hasPeriodValue(row) ? "plan" : "empty";
    }

    function planLabel(def, row, period) {
      return periodSourceKind(def, period, row) === "target" ? "Режа (мақсад)" : "Режа";
    }

    function factLabel(def, row, period) {
      return periodSourceKind(def, period, row) === "expected" ? "Кутилиш" : "Амалда";
    }

    function tinyValue(value, unit = "") {
      const num = n(value);
      if (num === null) return "—";
      if (unit.includes("минг доллар")) return `${fmt(num / 1000, 1)}м$`;
      if (unit.includes("млн доллар")) return Math.abs(num) >= 1000 ? `${fmt(num / 1000, 1)}млрд$` : `${fmt(num, 0)}м$`;
      if (unit.includes("млрд сўм")) return Math.abs(num) >= 1000 ? `${fmt(num / 1000, 1)}трлн` : `${fmt(num, 0)}млрд`;
      if (unit.includes("млн сўм")) return `${fmt(num / 1000, 1)}млрд`;
      if (unit.includes("%")) return `${fmt(num, 1)}%`;
      if (unit.includes("минг нафар")) return `${fmt(num, 1)}м`;
      return fmt(num, 1);
    }

    function growthValue(value) {
      const num = n(value);
      if (num === null) return "—";
      const delta = Math.abs(num) > 50 ? num - 100 : num;
      return `${delta >= 0 ? "+" : ""}${fmt(delta, 1)}%`;
    }

    function primaryMetric(row) {
      if (!row) return "—";
      if (row.main) return row.main;
      if (n(row.growth) !== null) return growthValue(row.growth);
      if (n(row.execution) !== null) return `${fmt(row.execution, 1)}%`;
      return planValue(row);
    }

    function hasPeriodValue(row) {
      return n(row.fact) !== null || n(row.plan) !== null || n(row.growth) !== null || n(row.execution) !== null || row.planText;
    }

    function periodState(def, period, row) {
      if (row?.reportStatus === "approved") return { cls: "actual", chip: "green", label: "Тасдиқланди" };
      if (row?.reportStatusLabel) return { cls: "planned", chip: reportStatusClass(row.reportStatus), label: row.reportStatusLabel };
      if (period === "q1" && (n(row.fact) !== null || n(row.growth) !== null)) return { cls: "actual", chip: "", label: "" };
      if (period === "q1") return { cls: "empty", chip: "grey", label: "I чорак белгиланмаган" };
      const kind = periodSourceKind(def, period, row);
      if (kind === "expected") return { cls: "planned", chip: "grey", label: "Кутилиш" };
      if (kind === "target") return { cls: "planned", chip: "grey", label: "Маълумот кутилмоқда" };
      if (kind === "plan") return { cls: "planned", chip: "grey", label: "Режа" };
      return { cls: "empty", chip: "grey", label: "Давр белгиланмаган" };
    }

    function executionLabel(def, row, period) {
      const kind = periodSourceKind(def, period, row);
      if (kind === "target") return "Мақсад";
      if (n(row?.execution) === null) return "Кўрсаткич";
      if (row?.reportStatus === "approved" || period === "q1") return "Ижро";
      return "Кутилган ижро";
    }

    function pct(actual, target, direction = "higher") {
      const a = n(actual), t = n(target);
      if (a === null || t === null || t === 0) return null;
      if (a === 0) return direction === "lower" ? 100 : 0;
      const value = direction === "lower" ? (t / a) * 100 : (a / t) * 100;
      return Math.round(value * 10) / 10;
    }

    function statusFor(execution) {
      const v = n(execution);
      if (v === null) return "grey";
      if (v >= 100) return "green";
      if (v >= 80) return "amber";
      return "red";
    }

    function statusLabel(status) {
      return {
        green: "Бажарилган",
        amber: "Эътибор",
        red: "Кечиккан",
        grey: "Кутилмоқда"
      }[status] || "Маълумот кутилмоқда";
    }

    function reportStatusLabel(status) {
      return {
        submitted: "Киритилди",
        approved: "Тасдиқланди",
        review: "Кўриб чиқилмоқда",
        rejected: "Қайтарилди"
      }[status] || "Киритилди";
    }

    function reportStatusClass(status) {
      return {
        submitted: "blue",
        approved: "green",
        review: "amber",
        rejected: "red"
      }[status] || "blue";
    }

    function executionStatusClass(status) {
      return {
        "Бажарилди": "green",
        "Қисман бажарилди": "amber",
        "Бажарилмади": "red",
        "Муддати кечикди": "red",
        "Маълумот йўқ": "grey"
      }[status] || "grey";
    }

    function evidenceStatusClass(status) {
      return {
        "Етарли": "green",
        "Етарли эмас": "amber",
        "Далил йўқ": "red"
      }[status] || "grey";
    }

    function colorFor(status) {
      return { green: "var(--green)", amber: "var(--amber)", red: "var(--red)", grey: "var(--grey)" }[status] || "var(--grey)";
    }

    function icon(name) {
      const icons = {
        trend: '<path d="M4 17h16M6 14l4-5 4 3 4-7"/><path d="M16 5h4v4"/>',
        factory: '<path d="M4 20V9l5 3V9l5 3h6v8H4Z"/><path d="M8 16h1M12 16h1M16 16h1"/>',
        bank: '<path d="M4 10h16M6 10v8M10 10v8M14 10v8M18 10v8M3 20h18M12 4l8 4H4l8-4Z"/>',
        price: '<path d="M12 3v18"/><path d="M17 7.5C16.2 6 14.7 5 12.3 5H11a3 3 0 0 0 0 6h2a3 3 0 0 1 0 6h-1.3C9.3 17 7.8 16 7 14.5"/>',
        rocket: '<path d="M12 15l-3-3c1-5 4-8 10-9-1 6-4 9-9 10Z"/><path d="M9 12l-4 1-2 4 4-2 1-4M12 15l-1 4-4 2 2-4 4-1"/><circle cx="15" cy="8" r="1.5"/>',
        globe: '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2 2 3 5 3 8s-1 6-3 8M12 4c-2 2-3 5-3 8s1 6 3 8"/>',
        briefcase: '<path d="M10 6V5a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v1"/><rect x="4" y="6" width="16" height="14" rx="2"/><path d="M4 12h16M10 12v2h4v-2"/>',
        users: '<path d="M16 19v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="3"/><path d="M20 19v-2a3 3 0 0 0-2-2.8M16 4.2a3 3 0 0 1 0 5.6"/>'
      };
      return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor">${icons[name] || icons.trend}</svg>`;
    }

    function foodIcon(name) {
      const codepoints = {
        butter: "1f9c8",      egg: "1f95a",         potato: "1f954",
        onion: "1f9c5",       carrot: "1f955",      fish: "1f41f",
        bread: "1fad3",       fruit: "1f34e",       vegetable: "1f966",
        tomato: "1f345",      cucumber: "1f952",    pepper: "1f336",
        grapes: "1f347",      lemon: "1f34b",       melon: "1f348",
        watermelon: "1f349",  pear: "1f350",        peach: "1f351",
        produce: "1f345"
      };
      const customSvg = {
        meat:        '<svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M511.904,253.069c-1.554-48.548-44.377-84.942-104.409-88.656c-120.865-7.486-109.51-67.345-209.664-73.722c-5.642-0.361-11.206-0.536-16.662-0.536c-102.294,0-171.103,61.839-180.098,137.418c-0.548,4.591-0.839,9.124-0.968,13.612H0v52.824h0.31c3.218,66.926,53.423,119.234,135.051,121.51c48.123,1.342,182.039,5.082,224.552,6.268c69.628,1.94,136.722-44.738,149.856-104.255c0.87-3.94,1.419-7.815,1.767-11.639H512v-52.824H511.904z M483.976,270.015c-10.169,46.098-63.947,83.595-119.866,83.595c-1.154,0-2.308-0.02-3.462-0.046l-56.247-1.574l-168.305-4.694c-36.877-1.032-66.784-13.638-86.496-36.458c-18.042-20.898-25.967-49.361-22.304-80.144c7.996-67.196,71.272-114.127,153.874-114.127c4.913,0,9.956,0.162,14.985,0.484c40.514,2.579,58.118,14.489,80.41,29.578c27.553,18.642,58.788,39.779,129.299,44.144c29.797,1.844,54.784,13.387,68.557,31.655C484.569,235.897,487.877,252.353,483.976,270.015z"/><path d="M338.827,236.587l-101.03-7.222c-10.969-0.786-20.957-6.596-27.05-15.746l-27.257-40.882c-3.037-4.553-9.182-5.784-13.734-2.747c-4.553,3.037-5.784,9.182-2.747,13.734l12.522,18.784c5.474,8.215,7.287,18.338,5.004,27.946c-2.283,9.608-8.454,17.829-17.043,22.704l-38.947,22.104c-4.759,2.702-6.43,8.744-3.728,13.502c2.702,4.759,8.744,6.43,13.502,3.728l69.48-39.431c6.1-3.469,13.09-5.043,20.086-4.546l109.535,7.828c5.455,0.387,10.188-3.72,10.582-9.176C348.39,241.714,344.289,236.974,338.827,236.587z"/></svg>',
        sheep:       '<svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M392.8 107.5c9.3 5.3 25.8 9.3 40 9.2 7.7-.1 14.6-1.2 19.5-3.2 5-1.8 6.9-4.9 8.9-8.8-9.2-6.08-22.1-12.27-31.8-12.87-14.9.53-28.8 8.13-36.6 15.67zm-253 20.2c-1.7 5.5-7.9 8.1-13 5.4-26.5-14.5-50.46-6.9-67.71 8.7-35.93 32.6-45.13 87.3-32.47 145.7 7.31 33.6 18.99 53 41.29 62.8 0 .1.1.1.15.1 2.22 1 4.21 1.9 6.09 2.8l4.61-22c1.02-4.9 5.8-8 10.66-7s7.98 5.8 6.96 10.7l-23.5 112c4.79 7.2 16.4 1.2 21.3-1.2l38.12-106.5c10.8-9.4 21.2-19 28.7-29.2 6.6-9.1 10.4-18.4 10.6-23.5.2-5 4.4-8.9 9.4-8.7 5 .2 9 4.6 8.6 9.6-.6 11.2-6.2 22.4-14 33.2-7.3 10-16.7 19.6-27.2 27.2l-3.3 8.9c6.9 8.7 13.4 13.8 19.6 16.8 8.8 4.1 17.7 4.6 28.5 3.3 16.4-1.9 34.6-12.9 43.5-37.2 2.8-7.7 13.6-8 16.8-.5 7.7 21.2 36.1 32.6 55.1 24l-3.9-23.3c-.8-4.9 2.5-9.6 7.4-10.4 4.9-.9 9.6 2.5 10.4 7.4l17.6 105.9c9.2 6.3 14.5 2.4 19.9-4.4l-13.8-114.4c-.7-5.3 3.3-10 8.6-10.2 4.8-.2 8.8 3.3 9.3 8l4.3 35.7c5.1-1.2 9.1-2.5 12.4-5 4.3-3.2 8.5-8.7 12.1-21.5 1.7-6 9-8.5 14.1-4.7 13.6 8.3 27.4-1.8 35.6-12.2 12.9-16.5 14.7-42.4 13.2-69.2-2.1.3-4.2.5-6.3.6-8.8.5-17.9-.9-25.7-4.4-12.4-7-22-18.4-28.2-28.9-3.9-6.8-7.3-13.7-10.5-20-5.4 9.9-11 23.1-19.2 25-12.5 2.1-23.9-3.7-29.8-12.7-5.9-8.9-7.4-20.2-4.8-31.1 2.7-11.7 9.8-38.3 22.6-56.1 2.2-2.9 4.5-5.3 6.8-7.4-7.5-3.1-16.2-3.8-22.9-3.8-5.8 0-13.5 1.8-19.7 5-6.2 3.3-10.7 7.8-12.2 11.8-3.2 8.5-15.5 7.5-17.3-1.3-3.8-22.78-53.9-17.8-65.6 2-3.8 7-14.1 5.9-16.5-1.7-8.1-22.61-62.7-21.3-66.7 5.9zm345-1.5c1.7 16.4 3.5 32.2 4.2 45.6 1.8 6.5 6 18.9 8.7 7.3.9-4.1.8-11-.4-18.6-.1-7.1-14.5-47.3-12.5-34.3zm-112.7-2.5c-11.9 15-19.2 37.4-23.3 53.7-.6 5.8-.6 12.6 2.3 17.1 2.3 3.4 4.8 5.2 9.4 5 5.8-9.4 12.1-19.8 15.6-28.2-1.2-7.9-2.8-19.9-3.6-31.4-.4-5.8-.6-11.2-.4-16.2zm94.4 2.4c-2.4 1.6-4.8 3.1-7.5 4.1-7.8 3.2-16.8 4.4-26 4.5-14.8.1-30.2-2.7-42.9-8.4 0 3.6.1 7.7.4 12.3.9 12.6 3 27.2 4 33.5 10.5 16.6 19.9 44.4 36.8 52.5 5.8 2 11.9 3.1 17.2 2.9 6-.4 10.6-2.6 11.5-3.7 3.5-8 5.9-15.2 7.3-22.3 2.1-10.9 3.4-23.3 3.6-31.6.3-6.4-.6-13.3-1.1-18.7-1.4 4.1-5.7 6.6-10 5.9-4.3-.7-7.5-4.4-7.5-8.8 0-5.1 4.2-9.2 9.3-9 3 0 5.8 1.7 7.4 4.3-.9-6.1-1.4-12-2.5-17.5zm-58.3 16.5c4.9.2 8.7 4.2 8.7 9 0 5-4 9-9 9-4.9 0-9-4-9-9s4.2-9.1 9.3-9zm47.5 48.3c3.7-.1 6.5 1.9 6.5 6.2 0 7.8-5.8 15-12.7 19l-1-23.1c2.5-1.4 5-2.1 7.2-2.1zm-24.1 2c1.8-.1 3.9.4 5.8 1.3l3.8 22.5c-6-3.7-15.4-3.6-16.5-16.1-.5-5.2 2.8-7.7 6.9-7.7zm-30.9 164.2c-3.7 5.1-7.6 9.1-12.6 12.1l16.6 62c7.6 1.5 15.9 1 19.2-5.1zm-241.2 33.7l1.5 46.8c7.9 7.9 12.9 4.8 19.7-3l-3.7-39.5c-6.3-.9-12.6-2.2-17.5-4.3z"/></svg>',
        rice:        '<svg viewBox="0 0 463.817 463.817" xmlns="http://www.w3.org/2000/svg"><polygon points="128.333,168.868 135.102,200.243 157.095,183.966 146.507,174.432"/><path d="M463.704,222.173c-2.286-8.346-17.073-16.156-42.492-22.516l-9.012-38.119l-58.427-83.057L231.136,27.628l-61.234,34.553l-30.771,39.605L76.574,112.6l-46.645,89.855c-18.332,5.646-27.768,12.783-29.665,19.717c0,0-0.327,1.299-0.253,2.646c0.801,74.245,35.159,140.254,88.343,183.057h-8.601v28.314h304.459v-28.314h-8.599c53.185-42.803,87.401-108.813,88.202-183.059C463.83,224.638,463.704,222.173,463.704,222.173z M380.28,238.595c-41.113,6.375-93.779,9.886-148.296,9.886c-54.518,0-107.184-3.511-148.299-9.886c-32.28-5.006-49.564-10.469-58.536-14.313c7.554-3.242,21.005-7.625,44.337-11.92l-10.857-8.662l34.732-68.357l47.002-8.125l48.099,26.174l1.914,1.041l1.04-0.037l62.008-2.092l-59.122-9.406l-33.175-27.373l25.74-33.9l45.965-25.938l104.491,43.328l51.454,73.148l5.385,22.146l-12.116,15.934c31.143,4.93,47.969,10.26,56.773,14.039C429.848,228.126,412.561,233.589,380.28,238.595z"/><polygon points="299.366,154.981 288.645,170.68 320.615,173.55 311.617,147.71"/><polygon points="246.532,208.364 259.93,223.405 273.62,208.364 260.077,202.743"/><polygon points="225.307,103.577 239.965,103.214 250.059,92.577 231.706,84.272"/></svg>',
        flour:       '<svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M257.407,0.023l104.189,132.58h33.758C327.252,31.857,296.566,0.731,257.407,0.023z"/><path d="M257.407,0.023c29.516,0.956,52.787,32.228,104.189,132.58H116.657C185.558,30.652,216.165,0,256,0C256.473,0,256.945,0.011,257.407,0.023z"/><circle cx="306.637" cy="98.169" r="8.44"/><circle cx="284.132" cy="64.411" r="8.44"/><circle cx="205.363" cy="89.167" r="8.44"/><path d="M442.818,458.122c11.399,11.399,11.399,29.865,0,41.264c-10.217,10.217-26.129,11.275-37.517,3.162l-41.174-59.077v-257.08l56.264,33.758l7.776,223.322L442.818,458.122z"/><path d="M409.06,458.122c11.399,11.399,11.399,29.865,0,41.264c-1.182,1.182-2.442,2.239-3.758,3.162c-1.317-0.923-2.566-1.98-3.747-3.162l-5.345-5.345C356.037,505.642,301.36,512,256,512s-100.037-6.358-140.209-17.959l-5.345,5.345c-11.388,11.399-29.865,11.399-41.264,0c-11.399-11.399-11.399-29.865,0-41.264l14.651-14.651l7.776-223.322l147.512-45.011l147.512,45.011l7.776,223.322L409.06,458.122z"/><path d="M420.391,132.602h-33.758v87.546h33.758c12.333,0,22.325-9.992,22.325-22.314v-42.918C442.717,142.595,432.724,132.602,420.391,132.602z"/><path d="M408.959,154.917v42.918c0,12.322-9.992,22.314-22.325,22.314H91.609c-12.333,0-22.325-9.992-22.325-22.314v-42.918c0-12.322,9.992-22.314,22.325-22.314h295.025C398.966,132.602,408.959,142.595,408.959,154.917z"/></svg>',
        cow:         '<svg viewBox="0 0 556.012 556.012" xmlns="http://www.w3.org/2000/svg"><path d="M9.469,226.129c0.992,5.573,2.358,11.102,2.999,16.712c1.428,12.501,3.949,25.108,3.362,37.543c-0.75,15.896-4.121,31.665-6.361,47.487c0.833-0.347,1.665-0.698,2.501-1.045c3.285,4.998,6.569,9.996,10.127,15.415c-1.437,4.622-3.129,10.064-5.104,16.409c2.432-1.946,4.268-3.415,4.811-3.848c1.265,7.385,2.636,15.378,4.215,24.599c2.57-2.117,3.37-2.778,3.949-3.26c0.673,3.578,1.351,7.168,2.028,10.759c0.445-0.024,0.889-0.049,1.334-0.073c0.698-2.815,1.396-5.635,2.093-8.45c0.665,0.024,1.33,0.05,1.995,0.074c10.18,30.791,22.595,59.535,25.063,90.584c0,0-1.787,5.594,2.191,8.127c3.978,2.53,44.847,2.893,48.462,0c3.615-2.893-8.604-17.14-17.683-21.049c-2.848-11.803-6.185-22.097-8.054-34.063c-1.897-12.143-2.746-24.509-3.166-36.806c-0.424-12.379,4.063-23.301,10.897-33.758c5.161-7.899,8.886-16.883,12.273-25.757c3.566-9.344,5.479-19.319,9.041-28.662c3.007-7.887,6.846-8.025,13.472-2.596c6.756,5.541,13.337,12.146,21.196,15.271c9.649,3.84,20.518,4.688,30.906,6.577c6.075,1.106,12.26,2.57,18.356,2.407c18.601-0.497,37.173-1.941,55.773-2.542c8.364-0.269,14.093,4.194,15.602,12.433c2.272,12.402,2.848,25.141,5.528,37.438c2.677,12.272,2.203,24.99,8.417,36.903c6.691,12.828,9.295,27.394,4.762,41.951c-1.049,3.37-3.721,6.772-6.605,8.771c0,0-3.383,16.961,0.478,20.82c3.859,3.855,24.112,2.411,35.202,1.448c0,0,27.968,5.786,39.058,2.412c11.094-3.375,5.304-10.127,4.341-13.982c-0.967-3.855-8.494-4.794-8.494-4.794c-1.469-2.856-6.59-4.125-7.169-7.152c-2.692-14.125-1.832-28.091,1.832-42.085c4.554-17.381,7.85-35.101,12.677-52.4c1.673-6.001,5.585-11.799,9.739-16.593c7.747-8.939,21.399-10.972,27.242-22.452c0.085-0.167,0.412-0.225,0.636-0.302c11.29-3.986,16.124-13.081,19.295-23.771c1.656-5.585,4.06-10.959,6.287-16.36c6.585-15.981,19.185-27.262,31.31-38.695c3.664-3.452,10.024-6.748,14.55-6.079c13.929,2.057,27.564,5.569,41.302-0.518c0.637-0.281,1.444-0.767,1.991-0.591c9.837,3.174,18.339-0.192,26.487-5.3c2.366-1.485,6.638-3.162,6.605-4.675c-0.147-6.752-0.204-14.056-2.791-20.082c-3.803-8.845-10.245-16.52-14.586-25.182c-2.982-5.949-3.064-12.709-0.6-19.56c2.081,0.718,3.427,1.167,4.757,1.648c9.172,3.301,15.745-1.979,14.827-11.473c-0.245-2.534,0.607-5.757,2.13-7.785c8.282-11.012,6.564-17.997-6.422-23.105c-2.546-1-5.61-1.167-8.385-0.975c-5.198,0.359-10.354,1.261-14.586,1.812c-2.791-3.929-4.325-8.278-7.303-9.796c-4.99-2.538-8.075-5.112-6.517-10.2c-8.637-3.207-16.695-6.202-25.561-9.494c2.011-1.057,3.043-1.595,4.076-2.138c-0.245-0.669-0.49-1.338-0.734-2.008c-7.487,2.277-14.974,4.558-22.697,6.908c0.171,4.602-0.094,9.298-7.308,8.817c-1.15-0.078-2.611,1.212-3.574,2.211c-4.182,4.333-8.404,6.609-14.517,3.043c-1.828-1.065-5.287-0.245-7.638,0.657c-7.144,2.742-13.293,7.165-22.064,6.096c-6.116-0.743-12.705,2.844-19.144,4.186c-14.822,3.093-29.376,3.623-42.979-4.818c-1.963-1.22-4.524-1.995-6.83-2.052c-9.649-0.229-19.318,0.171-28.964-0.2c-6.487-0.249-12.938-1.383-19.4-2.154c-3.239-0.388-6.454-1.012-9.702-1.253c-7.74-0.571-15.888,0.327-23.113-1.86c-5.985-1.812-12.228-1.318-16.634,0.11c-10.016,3.248-19.56,2.468-28.69-0.286c-29.192-8.809-59.185-13.072-89.254-16.797c-6.348-0.759-11.358-3.089-16.369-6.181c-3.75-2.318-7.177-5.235-11.383-2.126c-4.851-1.249-10.392-2.974-14.521-1.273c-5.757,2.371-13.386-0.779-17.572,5.908c-0.171,0.269-1.347-0.008-2.032-0.151c-7.928-1.648-14.305,2.656-19.854,6.769c-6.393,4.741-12.632,7.229-20.126,5.5c-3.321,4.133-2.644,11.122-9.861,11.42c-0.604,0.024-1.428,1.367-1.677,2.232C7.764,115.366,6.572,122,4.132,128.144c-4.757,11.991-2.607,24.57-3.941,36.834c-0.4,3.664-0.139,7.507,0.473,11.159C3.476,192.82,6.503,209.471,9.469,226.129z M317.652,411.418c0.641-0.032,1.285-0.061,1.926-0.094c1.028,7.426,2.627,14.831,2.93,22.285c0.306,7.613,1.767,15.626-2.689,23.896C314.625,441.394,312.307,426.494,317.652,411.418z"/></svg>',
        sugar:       '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#000" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="13" width="8" height="8" rx=".8"/><rect x="13" y="13" width="8" height="8" rx=".8"/><rect x="8" y="3" width="8" height="8" rx=".8"/></svg>',
        oil:         '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#000" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="2.5" rx=".5"/><path d="M10 4.5v2.5l-1.5 2"/><path d="M14 4.5v2.5l1.5 2"/><path d="M8 9.5h8v10a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2z"/><circle cx="12" cy="14.5" r="1.6"/><path d="M12 17.5v2.5"/></svg>',
        milk_bottle: '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#000" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="2.5" rx=".5"/><path d="M9 4.5v3l-1 1.5M15 4.5v3l1 1.5"/><path d="M8 9h8v11a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2z"/><path d="M9 13h6"/></svg>'
      };
      const lower = String(name || "").toLowerCase();
      const map = [
        [/мол\s*гўшт|қорамол/, "cow"],
        [/қўй\s*гўшт|қўзи\s*гўшт|эчки\s*гўшт/, "sheep"],
        [/гўшт|тарам/, "meat"],
        [/тухум/, "egg"],
        [/сариёғ|маска/, "butter"],
        [/сут/, "milk_bottle"],
        [/картошка/, "potato"],
        [/пиёз/, "onion"],
        [/сабзавот/, "vegetable"],
        [/сабзи/, "carrot"],
        [/гуруч/, "rice"],
        [/балиқ/, "fish"],
        [/шакар|қанд/, "sugar"],
        [/нон|лаваш/, "bread"],
        [/помидор/, "tomato"],
        [/бодринг/, "cucumber"],
        [/қалампир|мурч/, "pepper"],
        [/узум/, "grapes"],
        [/лимон/, "lemon"],
        [/қовун/, "melon"],
        [/тарвуз/, "watermelon"],
        [/олма/, "fruit"],
        [/беҳи|нок/, "pear"],
        [/шафтоли|ўрик/, "peach"],
        [/мева/, "fruit"],
        [/ёғ/, "oil"],
        [/(^|\s)ун($|\s)/, "flour"]
      ];
      let key = "produce";
      for (const [pattern, k] of map) {
        if (pattern.test(lower)) { key = k; break; }
      }
      let url;
      if (customSvg[key]) {
        url = "data:image/svg+xml;utf8," + encodeURIComponent(customSvg[key]);
      } else {
        const code = codepoints[key] || codepoints.produce;
        url = `https://cdn.jsdelivr.net/gh/jdecked/twemoji@latest/assets/svg/${code}.svg`;
      }
      return `<span class="emoji" role="img" aria-label="${name}" style="--icon-url:url(&quot;${url}&quot;)"></span>`;
    }

    function macroByIndex(index, period) {
      const row = DATA.regional.macro[index];
      const key = period === "year" ? "year" : period;
      const value = row?.[`${key}_value`];
      const growth = row?.[`${key}_growth`];
      const fact = null;
      const plan = value;
      return {
        label: row.indicator,
        fact,
        plan,
        unit: row.unit,
        growth,
        execution: null,
        main: growth ? growthValue(growth) : displayValue(value, row.unit),
        status: "grey",
        note: `${periodLabel()} режа ва ўсиш`
      };
    }

    function inflationPeriodKpi(period) {
      const map = {
        q1: { plan: null, note: "I чорак учун алоҳида чегара белгиланмаган" },
        h1: { plan: 2.9, note: "II чорак якуни билан 2,9 фоиздан ошмаслик" },
        m9: { plan: null, note: "III чорак учун алоҳида чегара белгиланмаган" },
        year: { plan: 6.6, note: "йил якуни билан 6,6 фоиздан ошмаслик" }
      };
      const row = map[period] || map.year;
      const planText = row.plan === null ? "—" : `≤ ${fmt(row.plan, 1)}%`;
      return {
        label: "Инфляция даражаси",
        fact: null,
        plan: row.plan,
        planText,
        unit: "%",
        growth: null,
        execution: null,
        main: period === "h1" ? "≤2,9%" : period === "year" ? "≤6,6%" : planText,
        max: true,
        status: "grey",
        note: row.note
      };
    }

    function kafolatPercentTarget(kpiId, period) {
      const rows = DATA.kafolat_kpi_targets || [];
      const row = rows.find(item => item.kpi === kpiId && item.periodCode === period)
        || rows.find(item => item.kpi === kpiId && item.periodCode === "year")
        || rows.find(item => item.kpi === kpiId);
      const match = (row?.title || "").match(/(\d+(?:[,.]\d+)?)\s*(?:фоиз|%)/i);
      if (!match) return null;
      return Number(match[1].replace(",", "."));
    }

    function baseRegionalKpi(id, period = state.period) {
      if (id === "grp") return macroByIndex(0, period);
      if (id === "industry") return macroByIndex(1, period);
      if (id === "agriculture") return macroByIndex(2, period);
      if (id === "construction") return macroByIndex(3, period);
      if (id === "services") return macroByIndex(4, period);
      if (id === "inflation") return inflationPeriodKpi(period);
      if (id === "budget") {
        const b = DATA.regional.budget;
        const map = {
          year: [b.year_expected, b.year_plan],
          h1: [b.h1_expected, b.h1_plan],
          m9: [null, null]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Бюджет тушумлари", fact, plan, unit: b.unit, growth: null, execution, main: execution ? `${fmt(execution, 1)}%` : displayValue(fact ?? plan, b.unit), status: statusFor(execution), note: "кутилиш / режа" };
      }
      if (id === "investment") {
        const x = DATA.regional.foreign_investment;
        const map = {
          year: [x.year_expected, x.year_forecast],
          h1: [x.h1_expected, x.h1_plan],
          m9: [null, null]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Хорижий инвестициялар", fact, plan, unit: x.unit, growth: null, execution, main: displayValue(fact ?? plan, x.unit), status: statusFor(execution), note: `${x.h1_projects} та лойиҳа, ${fmt(x.h1_jobs, 0)} иш ўрни` };
      }
      if (id === "export") {
        const e = DATA.regional.export;
        const map = {
          year: [e.year_expected, e.year_forecast, e.year_growth],
          h1: [e.h1_expected, e.h1_expected, e.h1_growth],
          m9: [null, null, null]
        };
        const [fact, plan, growth] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Экспорт ҳажми", fact, plan, unit: e.unit, growth, execution, main: growth ? growthValue(growth) : displayValue(fact, e.unit), status: execution ? statusFor(execution) : "grey", note: `${e.year_exporters} экспортёр режада` };
      }
      if (id === "unemployment") {
        const emp = DATA.regional.employment;
        const target = period === "year" ? emp.unemployment_year : period === "h1" ? emp.unemployment_h1 : null;
        return { label: "Ишсизлик даражаси", fact: null, plan: target, unit: "%", growth: null, execution: null, main: target ? `${fmt(target, 1)}%` : "—", status: "grey", note: "мақсадли кўрсаткич" };
      }
      if (id === "poverty") {
        const emp = DATA.regional.employment;
        const target = period === "year" ? emp.poverty_year : period === "h1" ? emp.poverty_h1 : null;
        return { label: "Камбағаллик даражаси", fact: null, plan: target, unit: "%", growth: null, execution: null, main: target ? `${fmt(target, 1)}%` : "—", status: "grey", note: `${fmt(emp.mfy_h1, 0)} та МФЙ H1, ${fmt(emp.mfy_year, 0)} та йиллик` };
      }
      if (id === "small_business_share") {
        const target = period === "year" ? kafolatPercentTarget(id, period) : null;
        return { label: "Кичик тадбиркорлик улуши", fact: null, plan: target, unit: "%", growth: null, execution: null, main: target ? `${fmt(target, 1)}%` : "—", status: "grey", note: "0_Чора-тадбир · 74-қатор" };
      }
      return baseRegionalKpi("export", period);
    }

    function regionalKpi(id, period = state.period) {
      return applyApprovedReport(baseRegionalKpi(id, period), id, period, null);
    }

    function baseDashboardPeriodKpi(id, period) {
      const macroIndexes = { grp: 0, industry: 1, agriculture: 2, construction: 3, services: 4 };
      if (id in macroIndexes) {
        const index = macroIndexes[id];
        const row = DATA.regional.macro[index];
        const key = period === "year" ? "year" : period;
        const value = row?.[`${key}_value`];
        const growth = row?.[`${key}_growth`];
        return {
          fact: period === "q1" ? value : null,
          plan: period === "q1" ? null : value,
          unit: row.unit,
          growth,
          execution: null,
          status: "grey"
        };
      }
      if (id === "inflation") return inflationPeriodKpi(period);
      if (id === "budget_investment") {
        const row = DATA.regional.budget_investment || {};
        const map = {
          q1: [row.q1_absorption, row.limit, row.q1_pct],
          h1: [row.h1_absorption, row.limit, row.h1_pct],
          m9: [null, null, null],
          year: [row.year_absorption, row.limit, row.year_pct]
        };
        const [fact, plan, executionFromSource] = map[period] || map.year;
        const execution = n(executionFromSource) ?? pct(fact, plan);
        return {
          fact,
          plan,
          unit: row.unit || "млн сўм",
          growth: null,
          execution,
          status: statusFor(execution),
          main: execution ? `${fmt(execution, 1)}%` : displayValue(fact ?? plan, row.unit || "млн сўм"),
          note: `${fmt(row.objects, 0)} та объект · 4.1-жадвал Жами қатори`
        };
      }
      if (id === "budget") {
        const b = DATA.regional.budget;
        const map = {
          q1: [null, null],
          h1: [b.h1_expected, b.h1_plan],
          m9: [null, null],
          year: [b.year_expected, b.year_plan]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { fact, plan, unit: b.unit, growth: null, execution, status: statusFor(execution) };
      }
      if (id === "investment") {
        const x = DATA.regional.foreign_investment;
        const map = {
          q1: [x.q1_actual, x.q1_plan],
          h1: [x.h1_expected, x.h1_plan],
          m9: [null, null],
          year: [x.year_expected, x.year_forecast]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { fact, plan, unit: x.unit, growth: null, execution, status: statusFor(execution) };
      }
      if (id === "export") {
        const e = DATA.regional.export;
        const map = {
          q1: [e.q1_value, null, e.q1_growth],
          h1: [e.h1_expected, e.h1_expected, e.h1_growth],
          m9: [null, null, null],
          year: [e.year_expected, e.year_forecast, e.year_growth]
        };
        const [fact, plan, growth] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { fact, plan, unit: e.unit, growth, execution, status: statusFor(execution) };
      }
      if (id === "unemployment") {
        const emp = DATA.regional.employment;
        const map = {
          q1: [null, null],
          h1: [null, emp.unemployment_h1],
          m9: [null, null],
          year: [null, emp.unemployment_year]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan, "lower");
        return { fact, plan, unit: "%", growth: null, execution, status: statusFor(execution) };
      }
      if (id === "poverty") {
        const emp = DATA.regional.employment;
        const map = {
          q1: [null, null],
          h1: [null, emp.poverty_h1],
          m9: [null, null],
          year: [null, emp.poverty_year]
        };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan, "lower");
        return { fact, plan, unit: "%", growth: null, execution, status: statusFor(execution) };
      }
      if (id === "small_business_share") {
        const plan = period === "year" ? kafolatPercentTarget(id, period) : null;
        return { fact: null, plan, unit: "%", growth: null, execution: null, status: "grey", main: plan ? `${fmt(plan, 1)}%` : "—" };
      }
      return baseDashboardPeriodKpi("export", period);
    }

    function dashboardPeriodKpi(id, period) {
      return applyApprovedReport(baseDashboardPeriodKpi(id, period), id, period, null);
    }

    function annualPlanKpi(id) {
      return dashboardPeriodKpi(id, "year");
    }

    function laggingDistrictsFor(kpiId) {
      if (kpiId === "grp" || kpiId === "construction") return [];
      if (kpiId === "poverty") {
        return [...DATA.districts]
          .map(d => ({ d, k: districtKpi(d, kpiId, "h1") }))
          .sort((a, b) => (n(b.k.plan) || 0) - (n(a.k.plan) || 0))
          .slice(0, 3);
      }
      return [...DATA.districts]
        .map(d => ({ d, k: districtKpi(d, kpiId, "h1") }))
        .sort((a, b) => {
          const ax = n(a.k.execution);
          const bx = n(b.k.execution);
          if (ax !== null && bx !== null) return ax - bx;
          if (ax !== null) return -1;
          if (bx !== null) return 1;
          return (b.d.debt?.task_unfinished || 0) - (a.d.debt?.task_unfinished || 0);
        })
        .slice(0, 3);
    }

    function baseDistrictKpi(d, id, period = state.period) {
      const data = d.data || {};
      const key = period === "year" ? "year" : period;
      if (id === "grp") {
        return { label: "Таркибий кўрсаткичлар", fact: null, plan: null, unit: "", growth: null, execution: null, main: "Таркиб", note: "ЯҲМ туман кесимида берилмаган" };
      }
      if (id === "inflation") {
        const w = data.warehouses || {};
        const capacity = (n(w.reserve_capacity_t) || 0) + (n(w.cold_storage_capacity_t) || 0);
        const count = (n(w.reserve_warehouses) || 0) + (n(w.cold_storage_count) || 0);
        const newCount = (n(w.new_small_cold_storage_count) || 0) + (n(w.new_large_cold_storage_count) || 0);
        return { label: "Озиқ-овқат омборлари", fact: null, plan: capacity || null, unit: "тонна", growth: null, execution: null, main: capacity ? `${fmt(capacity, 0)} т` : "—", note: `${fmt(count, 0)} та омбор · ${fmt(newCount, 0)} та янги режа` };
      }
      if (id === "industry") {
        const row = data.industry;
        const value = row?.[`${key}_value`];
        const growth = row?.[`${key}_growth`];
        return { label: "Саноат", fact: period === "q1" ? value : null, plan: period === "q1" ? null : value, unit: row?.unit || "млрд сўм", growth, execution: null, main: growth ? growthValue(growth) : displayValue(value, row?.unit) };
      }
      if (id === "localization") {
        const row = data.localization || {};
        const projects = period === "year" ? (row.year_projects ?? data.localization_projects_year) : period === "h1" ? (row.h1_projects ?? data.localization_projects_h1) : null;
        const value = period === "year" ? (row.year_value_mln ?? data.localization_value_year_mln) : period === "h1" ? (row.h1_value_mln ?? data.localization_value_h1_mln) : null;
        return { label: "Маҳаллийлаштириш", fact: null, plan: projects, unit: "та", execution: null, main: displayValue(projects, "та"), note: value ? `қиймати ${displayValue(value, "млн сўм")}` : "1.3-жадвал" };
      }
      if (id === "energy_electricity") {
        const row = data.energy_efficiency || {};
        const value = period === "year" ? (row.electricity_year ?? data.energy_electricity_year) : period === "h1" ? (row.electricity_h1 ?? data.energy_electricity_h1) : null;
        return { label: "Электр тежаш", fact: null, plan: value, unit: "млн кВт·с", execution: null, main: displayValue(value, "млн кВт·с"), note: "энергия самарадорлиги" };
      }
      if (id === "energy_gas") {
        const row = data.energy_efficiency || {};
        const value = period === "year" ? (row.gas_year ?? data.energy_gas_year) : period === "h1" ? (row.gas_h1 ?? data.energy_gas_h1) : null;
        return { label: "Газ тежаш", fact: null, plan: value, unit: "млн куб метр", execution: null, main: displayValue(value, "млн куб метр"), note: "энергия самарадорлиги" };
      }
      if (id === "agriculture") {
        const row = data.agriculture;
        const value = row?.[`${key}_value`];
        const growth = row?.[`${key}_growth`];
        return { label: "Қишлоқ хўжалиги", fact: period === "q1" ? value : null, plan: period === "q1" ? null : value, unit: row?.unit || "млрд сўм", growth, execution: null, main: growth ? growthValue(growth) : displayValue(value, row?.unit) };
      }
      if (id === "services") {
        const row = data.services;
        const value = row?.[`${key}_value`];
        const growth = row?.[`${key}_growth`];
        return { label: "Хизматлар", fact: period === "q1" ? value : null, plan: period === "q1" ? null : value, unit: row?.unit || "млрд сўм", growth, execution: null, main: growth ? growthValue(growth) : displayValue(value, row?.unit) };
      }
      if (id === "construction") {
        return { label: "Қурилиш", fact: null, plan: null, unit: "млрд сўм", growth: null, execution: null, main: "—" };
      }
      if (id === "budget") {
        const row = data.budget || {};
        const map = { q2: [row.q2_expected, row.q2_plan], year: [row.year_expected, row.year_plan], h1: [row.h1_expected, row.h1_plan], m9: [null, null], q1: [null, null] };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan) || (period === "h1" ? row.h1_execution_pct : period === "q2" ? row.q2_execution_pct : null);
        return { label: "Бюджет", fact, plan, unit: row.unit || "млрд сўм", execution, main: execution ? `${fmt(execution)}%` : displayValue(fact ?? plan, row.unit) };
      }
      if (id === "budget_investment") {
        const row = data.budget_investment || {};
        const map = { q1: [row.q1_absorption, row.limit, row.q1_pct], h1: [row.h1_absorption, row.limit, row.h1_pct], year: [row.year_absorption, row.limit, row.year_pct] };
        const [fact, plan, execution] = map[period] || map.year;
        return { label: "Бюджет инвестициялари", fact, plan, unit: row.unit || "млн сўм", execution, main: execution ? `${fmt(execution)}%` : displayValue(fact, row.unit || "млн сўм"), note: `${fmt(row.objects, 0)} та объект · лимит ${displayValue(row.limit, row.unit || "млн сўм")}` };
      }
      if (id === "investment") {
        const row = data.foreign_investment || {};
        const map = { year: [row.year_expected, row.year_forecast], h1: [row.h1_expected, row.h1_plan], q1: [row.q1_actual, row.q1_plan], m9: [null, null] };
        const [fact, plan] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Инвестиция", fact, plan, unit: row.unit || "млн доллар", execution, main: displayValue(fact, row.unit || "млн доллар") };
      }
      if (id === "export") {
        const row = data.export || {};
        const map = { year: [row.year_expected, row.year_forecast, row.year_growth], h1: [row.h1_expected, row.h1_expected, row.h1_growth], q1: [row.q1_value, null, row.q1_growth], m9: [null, null, null] };
        const [fact, plan, growth] = map[period] || map.year;
        const execution = pct(fact, plan);
        return { label: "Экспорт", fact, plan, unit: row.unit || "минг доллар", growth, execution, main: growth ? growthValue(growth) : displayValue(fact, row.unit || "минг доллар") };
      }
      if (id === "unemployment") {
        const row = data.employment || {};
        const target = period === "year" ? row.unemployment_year : period === "h1" ? row.unemployment_h1 : null;
        return { label: "Ишсизлик", fact: null, plan: target, unit: "%", execution: null, main: target ? `${fmt(target, 1)}%` : "—" };
      }
      if (id === "poverty") {
        const row = data.employment || {};
        const target = period === "year" ? row.poverty_year : period === "h1" ? row.poverty_h1 : null;
        const numeric = n(target);
        return { label: "Камбағаллик", fact: null, plan: numeric, planText: numeric === null && target ? String(target) : null, unit: "%", execution: null, main: numeric !== null ? `${fmt(numeric, 1)}%` : target || "—" };
      }
      if (id === "jobs") {
        const row = data.employment || {};
        const target = period === "year" ? row.jobs_year : period === "h1" ? row.jobs_h1 : null;
        return { label: "Бандлик", fact: null, plan: target, unit: "минг нафар", execution: null, main: displayValue(target, "минг нафар") };
      }
      if (id === "legalization") {
        const row = data.employment || {};
        const target = period === "year" ? row.legalization_year : period === "h1" ? row.legalization_h1 : null;
        return { label: "Легаллаштириш", fact: null, plan: target, unit: "минг нафар", execution: null, main: displayValue(target, "минг нафар") };
      }
      if (id === "mfy_clear") {
        const row = data.employment || {};
        const target = period === "year" ? row.mfy_year : period === "h1" ? row.mfy_h1 : null;
        return { label: "Камбағалликдан холи МФЙлар", fact: null, plan: target, unit: "та", execution: null, main: displayValue(target, "та") };
      }
      if (id === "microprojects") {
        const row = data.employment || {};
        const target = period === "year" ? row.microprojects_year : period === "h1" ? row.microprojects_h1 : null;
        return { label: "Микролойиҳа", fact: null, plan: target, unit: "та", execution: null, main: displayValue(target, "та") };
      }
      return baseDistrictKpi(d, "export", period);
    }

    function districtKpi(d, id, period = state.period) {
      return applyApprovedReport(baseDistrictKpi(d, id, period), id, period, d?.name || null);
    }

    function periodLabel() {
      return periods.find(p => p.id === state.period)?.label || "Йиллик";
    }

    function currentKpiDef() {
      return [...kpiDefs, ...macroComponentDefs, ...districtOnlyDefs].find(k => k.id === state.kpi) || kpiDefs[3];
    }

    const dashboardKpiMap = {
      macro: ["grp", "industry", "agriculture", "construction", "services"],
      inflation: ["inflation"],
      budget: ["budget"],
      budget_investment: ["budget_investment"],
      investment: ["investment"],
      export: ["export"],
      employment: ["unemployment", "poverty", "small_business_share"]
    };

    function kpiDefById(id) {
      return [...kpiDefs, ...macroComponentDefs, ...districtOnlyDefs]
        .find((def, idx, arr) => def.id === id && arr.findIndex(item => item.id === def.id) === idx)
        || [...kpiDefs, ...macroComponentDefs, ...districtOnlyDefs].find(def => def.id === id);
    }

    function dashboardModules() {
      return taskModules().filter(module => dashboardKpiMap[module.id]);
    }

    function dashboardKpisForModule(moduleId = state.dashboardModule) {
      return (dashboardKpiMap[moduleId] || dashboardKpiMap.macro).map(kpiDefById).filter(Boolean);
    }

    function dashboardModuleForKpi(kpiId) {
      const direct = Object.entries(dashboardKpiMap).find(([, ids]) => ids.includes(kpiId));
      if (direct) return direct[0];
      if (["localization", "energy_electricity", "energy_gas"].includes(kpiId)) return "macro";
      if (["jobs", "legalization", "mfy_clear", "microprojects", "small_business_share"].includes(kpiId)) return "employment";
      return "macro";
    }

    const financeKpiIds = ["budget", "budget_investment", "investment", "export"];

    function isFinanceKpi(kpiId) {
      return financeKpiIds.includes(kpiId);
    }

    function dashboardModuleIntro(moduleId) {
      const text = {
        macro: "ЯҲМ ва асосий таркибий кўрсаткичлар",
        inflation: "Инфляция чегараси, озиқ-овқат баланси ва омборлар.",
        budget: "Бюджет тушумлари бўйича режа ва ижро.",
        budget_investment: "Бюджет инвестициялари ўзлаштирилиши.",
        investment: "Хорижий инвестициялар ҳажми, режа ва ижро.",
        export: "Экспорт ҳажми ва ўсиш кўрсаткичлари.",
        employment: "Ишсизлик, камбағаллик ва кичик тадбиркорлик бўйича асосий KPIлар."
      };
      return text[moduleId] || "";
    }

    function districtSelectorDefs() {
      return [
        { ...kpiDefs.find(k => k.id === "grp"), label: "ЯҲМ таркиби", short: "ЯҲМ таркиби" },
        ...macroComponentDefs.filter(k => k.id !== "construction"),
        kpiDefs.find(k => k.id === "inflation"),
        kpiDefs.find(k => k.id === "budget"),
        kpiDefs.find(k => k.id === "investment"),
        kpiDefs.find(k => k.id === "export"),
        kpiDefs.find(k => k.id === "unemployment"),
        kpiDefs.find(k => k.id === "poverty"),
        ...districtOnlyDefs
      ].filter(Boolean);
    }

    function districtKpiMeta(kpiId) {
      return districtKpiRegistry[kpiId] || { layer: "excel", source: "Excel жадвал", periods: [state.period], note: "Excel туман KPI." };
    }

    function districtLayerTitle(meta) {
      const labels = {
        composition: "Excel таркибий KPI",
        excel: "Excel туман KPI",
        "excel-proxy": "Excel ёрдамчи KPI",
        "excel-target": "Excel мақсад KPI",
        "guarantee-target": "Кафолат хати KPI"
      };
      return labels[meta.layer] || "Excel туман KPI";
    }

    function districtKpiCoverage(kpiId, period = null) {
      const meta = districtKpiMeta(kpiId);
      const def = districtSelectorDefs().find(item => item.id === kpiId) || currentKpiDef();
      const cfg = districtTableConfig(def);
      const usePeriod = period || cfg.primaryPeriod || state.period;
      const districts = DATA.districts || [];
      const available = districts.filter(d => hasPeriodValue(districtKpi(d, kpiId, usePeriod))).length;
      return {
        total: districts.length,
        available,
        period: usePeriod,
        periods: meta.periods || [],
        label: `${available}/${districts.length}`
      };
    }

    function periodNameById(periodId) {
      if (periodId === "q2") return "II чорак";
      return periods.find(item => item.id === periodId)?.label || periodId;
    }

    function renderDistrictDataLayers(kpi, period) {
      const meta = districtKpiMeta(kpi.id);
      const coverage = districtKpiCoverage(kpi.id, period);
      const targetCount = districtTargetsForKpi(kpi.id).length;
      const taskCount = tasksForKpi(kpi.id).length;
      const periodList = coverage.periods.length ? coverage.periods.map(periodNameById).join(", ") : "давр белгиланмаган";
      return `<div class="district-data-layers">
        <div class="district-data-layer">
          <span>${districtLayerTitle(meta)}</span>
          <strong>${coverage.label} ҳудуд</strong>
          <small>${meta.source} · ${periodList}</small>
        </div>
        <div class="district-data-layer">
          <span>D-мақсад</span>
          <strong>${targetCount} та</strong>
          <small>Кафолат хатидан ажратилган туман/шаҳар мажбурияти.</small>
        </div>
        <div class="district-data-layer">
          <span>T-топшириқ</span>
          <strong>${taskCount} та</strong>
          <small>Ижро назоратидаги амалий топшириқлар; D-мақсадлар бунга қўшилмайди.</small>
        </div>
        <div class="district-layer-note">
          <span>Мантиқ</span>
          <strong>${meta.note}</strong>
        </div>
      </div>`;
    }

    function currentDistrict() {
      return DATA.districts.find(d => d.name === state.district) || DATA.districts[0];
    }

    function contextStrip(active = "kpi") {
      const kpi = currentKpiDef();
      const districtLabel = kpi.id === "grp" ? "ЯҲМ таркиби" : kpi.short;
      return `<div class="context-strip" aria-label="KPI monitoring">
        <div class="context-step ${active === "kpi" ? "active" : ""}"><span>1. KPI</span><strong>${kpi.short}</strong></div>
        <div class="context-step ${active === "districts" ? "active" : ""}"><span>2. Туманлар кесими</span><strong>${districtLabel} бўйича 16 ҳудуд</strong></div>
        <div class="context-step ${active === "profile" ? "active" : ""}"><span>3. Туман ҳолати</span><strong>${state.district}</strong></div>
      </div>`;
    }

    function valueLine(row) {
      const fact = factValue(row);
      const plan = planValue(row);
      const growth = row.growth ? growthValue(row.growth) : "—";
      const execution = row.execution ? `${fmt(row.execution, 1)}%` : "—";
      return { fact, plan, growth, execution };
    }

    function districtPeriodRows(d, kpiId) {
      return [
        ["I чорак", "q1"],
        ["II чорак", "h1"],
        ["III чорак", "m9"],
        ["Йиллик", "year"]
      ].map(([label, period]) => ({ label, period, row: districtKpi(d, kpiId, period) }));
    }

    function barValue(row, kpiId) {
      if (n(row.execution) !== null) return Math.min(100, Math.max(0, n(row.execution)));
      if (n(row.growth) !== null) return Math.min(100, Math.max(0, n(row.growth) - 80) / 40 * 100);
      if (kpiId === "inflation" && n(row.plan) !== null) return Math.min(100, n(row.plan) / 20000 * 100);
      return 0;
    }

    function rowStatus(row) {
      if (n(row.execution) !== null) return statusFor(row.execution);
      if (n(row.growth) !== null) {
        if (row.growth >= 105) return "green";
        if (row.growth >= 100) return "amber";
        return "red";
      }
      return "grey";
    }

    function districtPrimaryLabel(kpiId) {
      if (kpiId === "grp") return "Таркиб";
      if (["industry", "agriculture", "services"].includes(kpiId)) return "Ўсиш";
      if (kpiId === "inflation") return "Омбор сиғими";
      if (kpiId === "localization") return "Лойиҳа";
      if (["energy_electricity", "energy_gas"].includes(kpiId)) return "Тежаш";
      if (kpiId === "unemployment") return "Мақсад";
      if (["jobs", "legalization"].includes(kpiId)) return "Минг нафар";
      if (["mfy_clear", "microprojects"].includes(kpiId)) return "Мақсад";
      return "Ижро";
    }

    function districtPrimaryValue(row, kpiId) {
      if (kpiId === "grp") return "Саноат / ҚХ / Хизматлар";
      if (["industry", "agriculture", "services", "export"].includes(kpiId) && row.growth) return growthValue(row.growth);
      if (row.execution) return `${fmt(row.execution, 1)}%`;
      if (kpiId === "inflation") return row.main || planValue(row);
      return row.main || factValue(row) || planValue(row);
    }

    function districtAllMetrics(d) {
      const defs = [
        { id: "industry", label: "Саноат" },
        { id: "localization", label: "Маҳаллийлаштириш" },
        { id: "energy_electricity", label: "Электр тежаш" },
        { id: "energy_gas", label: "Газ тежаш" },
        { id: "agriculture", label: "Қишлоқ хўжалиги" },
        { id: "services", label: "Хизматлар" },
        { id: "budget", label: "Бюджет" },
        { id: "budget_investment", label: "Бюджет инвест" },
        { id: "investment", label: "Инвестиция" },
        { id: "export", label: "Экспорт" },
        { id: "unemployment", label: "Ишсизлик" },
        { id: "poverty", label: "Камбағаллик" },
        { id: "jobs", label: "Бандлик" },
        { id: "legalization", label: "Легаллаштириш" },
        { id: "mfy_clear", label: "Камбағалликдан холи МФЙлар" },
        { id: "microprojects", label: "Микролойиҳа" },
        { id: "inflation", label: "Омборлар" }
      ];
      return defs.map(def => ({ def, row: districtKpi(d, def.id, state.period) }));
    }

    function filteredTasks() {
      const kpi = currentKpiDef();
      const macroTaskKpis = ["grp", "industry", "agriculture", "construction", "services", "localization", "energy_electricity", "energy_gas", "inflation"];
      const defaultSector = macroTaskKpis.includes(kpi.id) ? "Макро иқтисодиёт" : kpi.sector;
      const sector = state.sector === "all" ? defaultSector : state.sector;
      const q = state.search.trim().toLowerCase();
      return DATA.tasks.filter(t => {
        const sectorOk = sector === "all" || t.sector === sector;
        const qOk = !q || `${t.title} ${t.sector} ${t.owner}`.toLowerCase().includes(q);
        const specificOk = isSpecificTaskForKpi(t, kpi.id);
        return sectorOk && qOk && specificOk;
      });
    }

    function isSpecificTaskForKpi(task, kpiId) {
      if (task.kpi) {
        if (kpiId === "industry") return ["industry", "localization", "energy_electricity", "energy_gas"].includes(task.kpi);
        return task.kpi === kpiId;
      }
      const title = cleanTaskTitle(task.title);
      const lower = title.toLowerCase();
      if (lower.includes("ялпи ҳудудий маҳсулотнинг 7,2") || title === "1. Саноат маҳсулотларини ишлаб чиқариш.") {
        return false;
      }
      if (kpiId === "localization") {
        return /маҳаллийлаштириш|локализация|маҳаллий контент/i.test(title);
      }
      if (kpiId === "energy_electricity") {
        return /энергия|электр/i.test(title);
      }
      if (kpiId === "energy_gas") {
        return /энергия|газ/i.test(title);
      }
      if (kpiId === "industry") {
        return /саноат|ишлаб чиқариш|корхона|локализация|энергия|электр|газ|Хонобод|Шаҳрихон/i.test(title);
      }
      if (kpiId === "inflation") {
        return /инфляц|нарх|озиқ|овқат|омбор|захира|бозор/i.test(title);
      }
      if (kpiId === "poverty") {
        return /камбағал|холи|оила|индивидуал|хизмат|кредит|субсид|тадбиркор|касб|оғир|маҳалла|инфратузилма/i.test(title);
      }
      if (kpiId === "mfy_clear") {
        return /холи|мфй|маҳалла|камбағал|ишсиз/i.test(title);
      }
      if (kpiId === "microprojects") {
        return /микролойиҳа|тадбиркор|кредит|субсид|камбағал/i.test(title);
      }
      if (kpiId === "legalization") {
        return /легаллаш|норасмий|банд/i.test(title);
      }
      if (kpiId === "small_business_share") {
        return /кичик ва ўрта бизнес|кичик тадбиркор|тадбиркорлик субъект|субъектларининг фаолияти|кредитлар ажратиш/i.test(title);
      }
      if (kpiId === "budget_investment") {
        return /бюджет|инвест|объект|ўзлаштир|қурилиш/i.test(title);
      }
      if (kpiId === "unemployment" || kpiId === "jobs" || kpiId === "legalization" || kpiId === "mfy_clear" || kpiId === "microprojects") {
        return /ишсиз|банд|ишга жойлаш|легаллаш|тадбиркор|субъект|микролойиҳа/i.test(title) && !/камбағаллик даражаси|камбағал оила/i.test(title);
      }
      return true;
    }

    function filteredDistricts() {
      const q = state.search.trim().toLowerCase();
      return DATA.districts.filter(d => !q || d.name.toLowerCase().includes(q) || d.owner.toLowerCase().includes(q));
    }

    function renderPeriodTabs() {
      $("#periodTabs").innerHTML = periods.map(p => `<button class="${p.id === state.period ? "active" : ""}" data-period="${p.id}">${p.label}</button>`).join("");
      $$("#periodTabs button").forEach(btn => btn.addEventListener("click", () => {
        state.period = btn.dataset.period;
        render();
      }));
    }

    function renderSectorFilter() {
      const sectors = ["all", ...new Set(DATA.tasks.map(t => t.sector))];
      $("#sectorFilter").innerHTML = sectors.map(s => `<option value="${s}" ${state.sector === s ? "selected" : ""}>${s === "all" ? "Барча секторлар" : s}</option>`).join("");
    }

    function kpiCard(def) {
      const active = def.id === state.kpi ? "active" : "";
      const parent = def.id === "grp" && state.dashboardModule === "macro" ? "parent" : "";
      const meta = active ? "Танланган KPI" : "Кўрсаткични очиш";
      return `<button class="front-kpi ${active} ${parent}" data-kpi="${def.id}" aria-label="${def.label}">
        <div class="kpi-icon">${icon(def.icon)}</div>
        <div class="front-kpi-copy">
          <h3>${def.short}</h3>
          <p>${def.label}</p>
          <span class="front-kpi-meta"><i class="front-kpi-dot" aria-hidden="true"></i>${meta}</span>
        </div>
      </button>`;
    }

    function bindKpiCards(root = document) {
      $$(".front-kpi", root).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.kpi;
        state.dashboardModule = dashboardModuleForKpi(state.kpi);
        render();
      }));
    }

    function overviewCommandSummary(selected) {
      const q1Ready = kpiDefs.filter(def => {
        const row = dashboardPeriodKpi(def.id, "q1");
        return n(row.fact) !== null || n(row.growth) !== null;
      }).length;
      const districtReady = selected.id === "grp" ? "таркибий" : districtSelectorDefs().some(def => def.id === selected.id) ? "бор" : "йўқ";
      const q1Missing = kpiDefs.length - q1Ready;
      return `<div class="command-summary">
        <div class="command-card"><span>I чорак факт</span><strong>${q1Ready}/${kpiDefs.length}</strong><small>${q1Missing} та KPIда факт эмас, режа ёки чеклов кўрсатилган.</small></div>
        <div class="command-card"><span>Танланган KPI</span><strong>${selected.short}</strong><small>${selected.label}</small></div>
        <div class="command-card"><span>Мониторинг кесими</span><strong>${selected.id === "grp" ? "таркибий" : "KPI"}</strong><small>режа, амалдаги натижа ва ижро ҳолати.</small></div>
        <div class="command-card"><span>Туман кесими</span><strong>${districtReady}</strong><small>Туманлар бўйича кўриш имкони.</small></div>
      </div>`;
    }

    function uniqueTasks(tasks) {
      const seen = new Set();
      return tasks.filter(task => {
        const key = task.id || `${task.kpi || ""}:${task.title || ""}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      });
    }

    function dashboardScorelineTasks(selected, moduleId) {
      if (moduleId === "employment") {
        if (selected.id === "unemployment") {
          return uniqueTasks(["unemployment", "jobs", "legalization", "small_business_share"].flatMap(tasksForKpi));
        }
        if (selected.id === "poverty") {
          return uniqueTasks(["poverty", "mfy_clear", "microprojects"].flatMap(tasksForKpi));
        }
        if (selected.id === "small_business_share") {
          return uniqueTasks(["small_business_share", "jobs", "legalization"].flatMap(tasksForKpi));
        }
      }
      if (moduleId !== "macro") return DATA.tasks.filter(t => t.module === moduleId);
      if (selected.id === "grp") return DATA.tasks.filter(t => t.module === "macro");
      if (selected.id === "industry") {
        return uniqueTasks(["industry", "localization", "energy_electricity", "energy_gas"].flatMap(tasksForKpi));
      }
      return uniqueTasks(tasksForKpi(selected.id).filter(t => t.module === "macro"));
    }

    function dashboardScorelineLabel(selected, moduleId) {
      if (moduleId !== "macro") {
        const totalTasks = DATA.task_meta?.declared_total || DATA.tasks.length;
        return `${selected.short} бўйича чора-тадбирлар ҳолати. Бу ерда умумий ${totalTasks} та реестр эмас, фақат танланган KPI/йўналишга тегишли ишлар кўрсатилади.`;
      }
      if (selected.id === "grp") {
        return "ЯҲМга оид макроиқтисодий чора-тадбирлар ҳолати. ЯҲМ якуний KPI бўлгани учун бу ерда унга олиб борувчи макро ишлар кўрсатилади.";
      }
      return `${selected.short}га оид чора-тадбирлар ҳолати.`;
    }

    function dashboardScorelineRouteKpi(selected, moduleId) {
      if (moduleId !== "macro") return "all";
      if (selected.id === "grp") return "all";
      return selected.id;
    }

    function renderDashboard() {
      if (state.page === "dashboard") {
        state.dashboardModule = dashboardModuleForKpi(state.kpi || "grp");
        const allowed = dashboardKpisForModule(state.dashboardModule);
        if (!allowed.some(def => def.id === state.kpi)) state.kpi = allowed[0]?.id || "grp";
      }
      const selected = currentKpiDef();
      const moduleKpis = dashboardKpisForModule(state.dashboardModule);
      const selectedModule = moduleById(state.dashboardModule) || dashboardModules()[0];
      const moduleTasks = dashboardScorelineTasks(selected, state.dashboardModule);
      const moduleTotal = moduleTasks.length;
      const moduleDone = moduleTasks.filter(t => t.status === "green").length;
      const moduleOpen = moduleTotal - moduleDone;
      const modulePct = moduleTotal > 0 ? Math.round(moduleDone / moduleTotal * 100) : 0;
      const taskScope = selected.id === "grp" ? "ЯҲМга оид чора-тадбирлар" : `${selected.short}га оид чора-тадбирлар`;
      const scorelineHtml = `<div class="scoreline execution-strip">
          <div class="scoreline-copy">
            <span>Чора-тадбирлар ижроси</span>
            <strong>${taskScope}</strong>
            <small>${dashboardScorelineLabel(selected, state.dashboardModule)}</small>
          </div>
          <div class="exec-status-grid">
            <button class="exec-status-pill" type="button" data-scoreline-status="all">
              <span>Жами</span>
              <strong>${moduleTotal}</strong>
            </button>
            <button class="exec-status-pill green" type="button" data-scoreline-status="done">
              <span>Бажарилди</span>
              <strong>${moduleDone}</strong>
            </button>
            <button class="exec-status-pill red" type="button" data-scoreline-status="open">
              <span>Бажарилмади</span>
              <strong>${moduleOpen}</strong>
            </button>
          </div>
          <div class="exec-progress-box">
            <div class="exec-donut" style="--pct:${modulePct}"><strong>${modulePct}%</strong></div>
            <small>бажарилиш</small>
          </div>
          <div class="score-actions">
            <button class="score-action primary" type="button" data-scoreline-status="all">Чора-тадбирларни кўриш</button>
            <button class="score-action" type="button" data-open-execution data-exec-kpi="${selected.id}">Ижро журнали</button>
          </div>
        </div>`;
      const isMacro = state.dashboardModule === "macro";
      const showModuleKpis = isMacro || state.dashboardModule === "employment";
      const moduleKpiLayout = isMacro ? "macro-layout" : state.dashboardModule === "employment" ? "employment-layout" : "";
      const kpiWorkspaceHtml = `<div class="kpi-monitor-grid single">${kpiDashboardCard(selected)}</div>`;
      $("#dashboardPage").innerHTML = `
        <div class="dashboard-module-tabs">
          ${dashboardModules().map(module => `<button class="module-tab ${module.id === state.dashboardModule ? "active" : ""}" data-dashboard-module="${module.id}" type="button">
            <span class="module-dot" aria-hidden="true"></span>
            <strong>${module.label.replace(/^\d+\.\s*/, "")}</strong>
          </button>`).join("")}
        </div>
        <div class="module-heading">
          <div>
            <h2>${selectedModule?.label || "1. Макроиқтисодиёт"}</h2>
            <p>${dashboardModuleIntro(state.dashboardModule)}</p>
          </div>
        </div>
        ${showModuleKpis ? `<div class="front-kpis module-kpis ${moduleKpiLayout}">${moduleKpis.map(kpiCard).join("")}</div>` : ""}
        ${kpiWorkspaceHtml}
        ${scorelineHtml}`;
      bindKpiCards($("#dashboardPage"));
      $$("[data-dashboard-module]", $("#dashboardPage")).forEach(btn => btn.addEventListener("click", () => {
        state.dashboardModule = btn.dataset.dashboardModule;
        state.kpi = dashboardKpisForModule(state.dashboardModule)[0]?.id || "grp";
        render();
      }));
      $$("[data-component]", $("#dashboardPage")).forEach(card => card.addEventListener("click", () => {
        state.kpi = card.dataset.component;
        state.dashboardModule = dashboardModuleForKpi(state.kpi);
        render();
      }));
      $$("[data-district]", $("#dashboardPage")).forEach(card => card.addEventListener("click", () => {
        state.district = card.dataset.district;
        state.page = "profile";
        render();
      }));
      $$("[data-open-districts]", $("#dashboardPage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.openDistricts;
        if (btn.dataset.period) state.period = btn.dataset.period;
        state.page = "districts";
        render();
      }));
      $$("[data-open-execution]", $("#dashboardPage")).forEach(btn => btn.addEventListener("click", () => {
        openExecutionJournal(btn.dataset.execKpi || state.kpi, btn.dataset.execDistrict || null, btn.dataset.execPeriod || null);
      }));
      $$("[data-open-profile]", $("#dashboardPage")).forEach(btn => btn.addEventListener("click", () => {
        state.district = btn.dataset.openProfile;
        state.page = "profile";
        render();
      }));
      const goToScorelineTasks = status => {
        state.taskModule = state.dashboardModule;
        state.taskStatus = status;
        state.kpi = dashboardScorelineRouteKpi(selected, state.dashboardModule);
        state.taskDistrict = "all";
        state.taskPeriod = "all";
        state.page = "tasks";
        render();
      };
      $$("[data-scoreline-status]", $("#dashboardPage")).forEach(card => {
        card.addEventListener("click", () => goToScorelineTasks(card.dataset.scorelineStatus));
        card.addEventListener("keydown", event => {
          if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            goToScorelineTasks(card.dataset.scorelineStatus);
          }
        });
      });
    }

    function signalBarWidth(def, row) {
      if (n(row.execution) !== null) return Math.max(4, Math.min(100, n(row.execution)));
      if (n(row.growth) !== null) return Math.max(4, Math.min(100, Math.abs(n(row.growth) - 100) / 20 * 100));
      if (n(row.plan) !== null) return 72;
      return 8;
    }

    function renderKpiSignal(def) {
      const periodsForSignal = [["I чорак", "q1"], ["II чорак", "h1"], ["III чорак", "m9"], ["Йиллик", "year"]];
      const annual = dashboardPeriodKpi(def.id, "year");
      const q1 = dashboardPeriodKpi(def.id, "q1");
      const growthOnly = def.id === "grp" || macroComponentDefs.some(item => item.id === def.id) || ["export"].includes(def.id);
      const main = growthOnly && n(annual.growth) !== null ? growthValue(annual.growth) : primaryMetric(annual);
      const decision = (() => {
        if (def.id === "inflation") return "Йил якунида инфляция белгиланган чегарадан ошмаслиги керак.";
        if (def.id === "poverty") return "Камбағаллик даражаси туманлар кесимида кузатилади.";
        if (def.id === "unemployment") return "Ишсизлик даражаси режага нисбатан баҳоланади.";
        if (["budget", "investment"].includes(def.id)) return "Кутилиш ёки тасдиқланган амалдаги натижа режага нисбатан баҳоланади.";
        if (growthOnly) return "Асосий кўрсаткич ўсиш суръати.";
        return "Режа, амалдаги натижа ва ижро бир жойда.";
      })();
      return `<div class="kpi-signal">
        <div class="signal-main">
          <span>Бошқарув сигнали</span>
          <strong>${main}</strong>
          <small>${decision}</small>
        </div>
        <div class="signal-side">
          <span>Даврлар кесими</span>
          <div class="signal-track">
            ${periodsForSignal.map(([label, period]) => {
              const row = dashboardPeriodKpi(def.id, period);
              const stateInfo = periodState(def, period, row);
              const value = growthOnly && n(row.growth) !== null ? growthValue(row.growth) : primaryMetric(row);
              return `<div class="signal-step">
                <b>${label}</b>
                <small>${value}</small>
                <i style="--w:${signalBarWidth(def, row)}%;--c:${stateInfo.cls === "actual" ? "var(--blue)" : "var(--line-strong)"}"></i>
              </div>`;
            }).join("")}
          </div>
        </div>
      </div>`;
    }

    function dashboardDistrictRoute(def) {
      const moduleKpis = dashboardKpisForModule(dashboardModuleForKpi(def.id));
      const kpiId = kpiHasAnyDistrictData(def.id)
        ? def.id
        : moduleKpis.find(item => kpiHasAnyDistrictData(item.id))?.id || null;
      if (!kpiId) return null;
      return {
        kpiId,
        label: def.id === kpiId ? "Туманлар кесими" : "Таркибий KPIлар кесими"
      };
    }

    function macroGrowthPeriods() {
      return [
        { label: "I чорак", period: "q1", state: "Амалда", cls: "actual" },
        { label: "II чорак", period: "h1", state: "Режа", cls: "planned" },
        { label: "III чорак", period: "m9", state: "Режа", cls: "planned" },
        { label: "Йиллик", period: "year", state: "Режа", cls: "planned" }
      ];
    }

    function macroGrowthWidth(value, values) {
      const delta = Math.max(0, Math.abs((n(value) || 100) - 100));
      const maxDelta = Math.max(1, ...values.map(v => Math.max(0, Math.abs((n(v) || 100) - 100))));
      return Math.max(8, Math.min(100, (delta / maxDelta) * 100));
    }

    function renderMacroComposition() {
      const yearValues = macroComponentDefs.map(def => dashboardPeriodKpi(def.id, "year").growth);
      return `<details class="macro-composition-panel macro-composition-dropdown" aria-label="ЯҲМ таркиби">
        <summary class="macro-composition-head">
          <div>
            <strong>ЯҲМ таркибий мақсадлари</strong>
            <small>Саноат, қишлоқ хўжалиги, қурилиш ва хизматлар ўсиш суръати</small>
          </div>
          <span class="macro-dropdown-meta">
            <span>${macroComponentDefs.length} та мақсад</span>
            <span class="macro-dropdown-caret" aria-hidden="true">⌄</span>
          </span>
        </summary>
        <div class="macro-composition-body">
          <div class="macro-composition-grid">
            ${macroComponentDefs.map((item, idx) => {
              const year = dashboardPeriodKpi(item.id, "year");
              const width = macroGrowthWidth(year.growth, yearValues);
              const colors = ["#08742d", "#43a95d", "#24959a", "var(--blue)"];
              return `<button class="macro-composition-card ${state.kpi === item.id ? "active" : ""}" type="button" data-component="${item.id}">
                <span class="macro-comp-icon">${icon(item.icon)}</span>
                <span class="macro-comp-name">${item.short}</span>
                <i class="macro-composition-bar" aria-hidden="true"><i style="--w:${width}%;--macro-color:${colors[idx]}"></i></i>
                <strong class="macro-comp-value">${growthValue(year.growth)}</strong>
              </button>`;
            }).join("")}
          </div>
          <div class="macro-composition-actions">
            <button class="mini-button primary" type="button" data-open-districts="industry" data-period="h1">Туманлар кесимига ўтиш</button>
          </div>
        </div>
      </details>`;
    }

    function renderIndustryDriversPanel() {
      const src = DATA.regional.industry_drivers || {};
      const drivers = [
        {
          id: "localization",
          cls: "green",
          icon: "factory",
          title: "Маҳаллийлаштириш",
          desc: "Лойиҳалар сони ва қиймати",
          h1: `${fmt(src.localization_h1_projects, 0)} та`,
          h1Note: displayValue(src.localization_h1_value_mln, "млн сўм"),
          year: `${fmt(src.localization_year_projects, 0)} та`,
          yearNote: displayValue(src.localization_year_value_mln, "млн сўм")
        },
        {
          id: "energy_electricity",
          cls: "blue",
          icon: "trend",
          title: "Электр тежаш",
          desc: "Тежаладиган электр энергияси",
          h1: `${fmt(src.energy_electricity_h1, 1)} млн кВт·соат`,
          h1Note: "",
          year: `${fmt(src.energy_electricity_year, 1)} млн кВт·соат`,
          yearNote: ""
        },
        {
          id: "energy_gas",
          cls: "orange",
          icon: "rocket",
          title: "Газ тежаш",
          desc: "Тежаладиган табиий газ",
          h1: `${fmt(src.energy_gas_h1, 1)} млн м³`,
          h1Note: "",
          year: `${fmt(src.energy_gas_year, 1)} млн м³`,
          yearNote: ""
        }
      ];
      return `<aside class="industry-driver-panel" aria-label="Саноат драйверлари">
        <div class="industry-driver-head">
          <strong>Саноат драйверлари</strong>
          <span class="info-dot" title="Саноатга боғланган туманлар кесимидаги драйверлар">i</span>
        </div>
        <div class="industry-driver-list">
          ${drivers.map(item => `<button class="industry-driver-card ${item.cls}" type="button" data-open-districts="${item.id}" data-period="h1">
            <span class="driver-icon ${item.cls}">${icon(item.icon)}</span>
            <span class="industry-driver-body">
              <span class="industry-driver-title">
                <strong>${item.title}</strong>
                <span>${item.desc}</span>
              </span>
              <span class="industry-driver-metrics">
                <span class="industry-driver-metric"><span>I ярим йиллик</span><strong>${item.h1}</strong>${item.h1Note ? `<small>${item.h1Note}</small>` : ""}</span>
                <span class="industry-driver-divider" aria-hidden="true"></span>
                <span class="industry-driver-metric"><span>Йиллик кутилиш</span><strong>${item.year}</strong>${item.yearNote ? `<small>${item.yearNote}</small>` : ""}</span>
              </span>
            </span>
            <span class="industry-driver-arrow" aria-hidden="true">›</span>
          </button>`).join("")}
        </div>
        <button class="mini-button" type="button" data-open-districts="industry" data-period="h1">Саноат деталларига ўтиш ›</button>
      </aside>`;
    }

    function renderMacroGrowthPanel(def) {
      const year = dashboardPeriodKpi(def.id, "year");
      const periods = macroGrowthPeriods();
      const values = periods.map(item => dashboardPeriodKpi(def.id, item.period).growth);
      const showComposition = def.id === "grp";
      const showIndustryDrivers = def.id === "industry";
      return `<section class="macro-growth-panel ${showIndustryDrivers ? "with-side" : "solo"}" aria-label="${def.label} ўсиш мониторинги">
        <div class="macro-main-panel">
          <div class="macro-section-title"><strong>${def.short} ўсиши</strong><span>(солиштирма нархларда)</span></div>
          <div class="macro-hero-card">
            <div class="macro-hero-copy">
              <span>Йиллик ўсиш (мақсад)</span>
              <strong>${growthValue(year.growth)}</strong>
              <small>2026 йил</small>
            </div>
          </div>
          <div class="macro-period-grid">
            ${periods.map(item => {
              const row = dashboardPeriodKpi(def.id, item.period);
              const width = macroGrowthWidth(row.growth, values);
              return `<div class="macro-period-card ${item.cls}">
                <div class="macro-period-head">
                  <b>${item.label}</b>
                  <span class="chip ${item.cls === "actual" ? "blue" : "grey"}">${item.state}</span>
                </div>
                <strong>${growthValue(row.growth)}</strong>
                <small>ўсиш суръати</small>
                <i class="macro-mini-bar" aria-hidden="true"><i style="--w:${width}%"></i></i>
              </div>`;
            }).join("")}
          </div>
          ${showComposition ? renderMacroComposition() : ""}
        </div>
        ${showIndustryDrivers ? renderIndustryDriversPanel() : ""}
      </section>`;
    }

    function budgetInvestmentPeriods() {
      return [
        { label: "I чорак", period: "q1", kind: "actual", chip: "blue", state: "Амалда", note: "амалдаги ўзлаштириш" },
        { label: "II чорак", period: "h1", kind: "expected", chip: "grey", state: "Кутилиш", note: "тезкор кутилиш" },
        { label: "III чорак", period: "m9", kind: "missing", chip: "grey", state: "Маълумот йўқ", note: "9 ой учун алоҳида маълумот йўқ" },
        { label: "Йиллик", period: "year", kind: "expected", chip: "grey", state: "Кутилиш", note: "йил якуни бўйича кутилиш" }
      ];
    }

    function budgetInvestmentCard(item) {
      const row = dashboardPeriodKpi("budget_investment", item.period);
      const value = item.kind === "missing" ? "—" : factValue(row);
      const execution = n(row.execution);
      const pctText = execution === null ? "—" : `${fmt(execution, 1)}%`;
      const progress = execution === null ? 0 : Math.max(0, Math.min(execution, 108));
      const accent = item.kind === "missing" ? "var(--grey)" : "var(--blue)";
      return `<div class="budget-period-card ${item.kind}">
        <div class="budget-period-top">
          <b>${item.label}</b>
          <span class="chip ${item.chip}">${item.state}</span>
        </div>
        <strong>${value}</strong>
        <div class="budget-period-meta">
          <span>йиллик лимитга нисбатан</span>
          <b>${pctText}</b>
        </div>
        <div class="budget-progress" aria-hidden="true"><i style="--w:${progress}%;--c:${accent}"></i></div>
        <div class="budget-period-note">${item.note}</div>
      </div>`;
    }

    function renderBudgetInvestmentDynamics(items) {
      const points = items
        .map((item, index) => {
          const row = dashboardPeriodKpi("budget_investment", item.period);
          const value = n(row.execution);
          if (value === null) return null;
          const x = [24, 96, 168, 240][index];
          const y = 100 - (Math.min(Math.max(value, 0), 110) / 110) * 78;
          return { ...item, x, y, value };
        })
        .filter(Boolean);
      const path = points.map((point, index) => `${index ? "L" : "M"} ${point.x} ${point.y}`).join(" ");
      return `<div class="budget-dynamics-card">
        <div class="budget-dynamics-head">
          <strong>Ўзлаштириш динамикаси</strong>
          <span>йиллик лимитга нисбатан</span>
        </div>
        <svg class="budget-dynamics-svg" viewBox="0 0 264 116" role="img" aria-label="Бюджет инвестициялари ўзлаштириш динамикаси">
          <line x1="16" y1="100" x2="250" y2="100" stroke="#dce6f0" stroke-width="1"/>
          <line x1="16" y1="61" x2="250" y2="61" stroke="#edf2f7" stroke-width="1"/>
          <line x1="16" y1="22" x2="250" y2="22" stroke="#edf2f7" stroke-width="1"/>
          <path d="${path}" fill="none" stroke="var(--blue)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          ${points.map(point => `<circle cx="${point.x}" cy="${point.y}" r="4.5" fill="#fff" stroke="var(--blue)" stroke-width="3"><title>${point.label}: ${fmt(point.value, 1)}%</title></circle>`).join("")}
        </svg>
        <div class="budget-dynamics-list">
          ${items.map(item => {
            const row = dashboardPeriodKpi("budget_investment", item.period);
            const value = n(row.execution);
            const pctText = value === null ? "—" : `${fmt(value, 1)}%`;
            const width = value === null ? 0 : Math.max(0, Math.min(value, 100));
            return `<div><span>${item.label}</span><i style="--w:${width}%;--c:${item.kind === "missing" ? "var(--grey)" : "var(--blue)"}"></i><b>${pctText}</b></div>`;
          }).join("")}
        </div>
      </div>`;
    }

    function renderBudgetInvestmentPanel() {
      const source = DATA.regional.budget_investment || {};
      const annual = dashboardPeriodKpi("budget_investment", "year");
      const items = budgetInvestmentPeriods();
      return `<section class="budget-invest-panel" aria-label="Бюджет инвестициялари ўзлаштирилиши">
        <div class="budget-invest-summary">
          <div><span>Йиллик лимит</span><strong>${displayValue(source.limit, source.unit || "млн сўм")}</strong><small>2026 йил прогноз лимити</small></div>
          <div><span>Объектлар</span><strong>${fmt(source.objects, 0)} та</strong><small>жами объектлар</small></div>
          <div><span>Йиллик кутилиш</span><strong>${factValue(annual)}</strong><small>йил якуни бўйича тезкор кутилиш</small></div>
          <div><span>Йиллик ижро</span><strong>${n(annual.execution) === null ? "—" : `${fmt(annual.execution, 1)}%`}</strong><small>йиллик лимитга нисбатан</small></div>
        </div>
        <div class="budget-invest-body">
          <div class="budget-periods-grid">
            ${items.map(budgetInvestmentCard).join("")}
          </div>
          ${renderBudgetInvestmentDynamics(items)}
        </div>
      </section>`;
    }

    function financeMetric(label, value, note = "", tone = "") {
      return `<div class="finance-metric ${tone}">
        <span>${label}</span>
        <strong>${value}</strong>
        ${note ? `<small>${note}</small>` : ""}
      </div>`;
    }

    function financeSummaryTile(label, value, note = "", tone = "") {
      return `<div class="finance-summary-tile ${tone}">
        <span>${label}</span>
        <strong>${value}</strong>
        ${note ? `<small>${note}</small>` : ""}
      </div>`;
    }

    function financeIconTone(kpiId) {
      return kpiId === "budget_investment" ? "purple" : kpiId === "investment" ? "green" : kpiId === "export" ? "gold" : "";
    }

    function financeDisplayLabel(def) {
      return def.short === "Бюджет инвест" ? "Бюджет инвестициялари" : def.label;
    }

    function financeCardHead(def) {
      return `<header class="finance-card-head">
        <span class="finance-icon ${financeIconTone(def.id)}" aria-hidden="true">${icon(def.icon)}</span>
        <strong>${financeDisplayLabel(def)}</strong>
      </header>`;
    }

    function financeQuarterCell(label, row, options = {}) {
      const main = options.main || (n(row.growth) !== null ? growthValue(row.growth) : n(row.execution) !== null ? `${fmt(row.execution, 1)}%` : factValue(row));
      const note = options.note || "";
      return `<div class="finance-quarter-cell ${options.tone || ""}">
        <span>${label}</span>
        <strong>${main}</strong>
        ${note ? `<small>${note}</small>` : ""}
      </div>`;
    }

    function financeBudgetCard(activeId) {
      const def = kpiDefById("budget");
      const h1 = dashboardPeriodKpi("budget", "h1");
      const year = dashboardPeriodKpi("budget", "year");
      return `<article class="finance-card ${activeId === "budget" ? "active" : ""}">
        ${financeCardHead(def)}
        <div class="finance-card-body">
          <div>
            <div class="finance-section-title">I ярим йиллик</div>
            <div class="finance-metric-row">
              ${financeMetric("Режа", planValue(h1))}
              ${financeMetric("Кутилиш", factValue(h1))}
              ${financeMetric("Кутилган ижро", n(h1.execution) === null ? "—" : `${fmt(h1.execution, 1)}%`, "", "accent")}
            </div>
          </div>
          <div class="finance-divider"></div>
          <div>
            <div class="finance-section-title">Йиллик</div>
            <div class="finance-metric-row two">
              ${financeMetric("Режа", planValue(year))}
              ${financeMetric("Кутилиш", factValue(year))}
            </div>
          </div>
          <button class="mini-button primary finance-action" type="button" data-open-districts="budget" data-period="h1">Туманлар кесимига ўтиш</button>
        </div>
      </article>`;
    }

    function financeBudgetInvestmentCell(item) {
      const row = dashboardPeriodKpi("budget_investment", item.period);
      const value = item.kind === "missing" ? "—" : factValue(row);
      const execution = n(row.execution);
      const tone = item.kind === "actual" ? "accent" : item.kind === "missing" ? "" : "purple";
      const note = execution === null ? item.note : `${fmt(execution, 1)}% · ${item.note}`;
      return `<div class="finance-quarter-cell ${tone}">
        <span>${item.label} · ${item.state}</span>
        <strong>${value}</strong>
        <small>${note}</small>
      </div>`;
    }

    function financeBudgetInvestmentCard(activeId) {
      const def = kpiDefById("budget_investment");
      const source = DATA.regional.budget_investment || {};
      const annual = dashboardPeriodKpi("budget_investment", "year");
      const items = budgetInvestmentPeriods();
      return `<article class="finance-card finance-card-wide ${activeId === "budget_investment" ? "active" : ""}">
        ${financeCardHead(def)}
        <div class="finance-card-body">
          <div class="finance-summary-grid">
            ${financeSummaryTile("Йиллик лимит", displayValue(source.limit, source.unit || "млн сўм"))}
            ${financeSummaryTile("Объектлар", `${fmt(source.objects, 0)} та`)}
            ${financeSummaryTile("Йиллик кутилиш", factValue(annual), "", "purple")}
            ${financeSummaryTile("Йиллик ижро", n(annual.execution) === null ? "—" : `${fmt(annual.execution, 1)}%`, "лимитга нисбатан", "purple")}
          </div>
          <div class="finance-quarter-grid">
            ${items.map(financeBudgetInvestmentCell).join("")}
          </div>
          ${renderBudgetInvestmentDynamics(items)}
          <button class="mini-button primary finance-action" type="button" data-open-districts="budget_investment" data-period="h1">Туманлар кесимига ўтиш</button>
        </div>
      </article>`;
    }

    function financeInvestmentCard(activeId) {
      const def = kpiDefById("investment");
      const x = DATA.regional.foreign_investment || {};
      const year = dashboardPeriodKpi("investment", "year");
      const h1 = dashboardPeriodKpi("investment", "h1");
      return `<article class="finance-card ${activeId === "investment" ? "active" : ""}">
        ${financeCardHead(def)}
        <div class="finance-card-body">
          <div class="finance-metric-row two">
            ${financeMetric("Йиллик прогноз", planValue(year))}
            ${financeMetric("Кутилиш", factValue(year))}
          </div>
          <div class="finance-divider"></div>
          <div class="finance-metric-row two">
            ${financeMetric("Лойиҳалар", `${fmt(x.h1_projects, 0)} та`, "I ярим йиллик")}
            ${financeMetric("Иш ўринлари", `${fmt(x.h1_jobs, 0)} та`, "I ярим йиллик")}
          </div>
          ${financeQuarterCell("II чорак кутилиш", h1, { main: factValue(h1), note: n(h1.execution) === null ? "" : `${fmt(h1.execution, 1)}% ижро`, tone: "accent" })}
          <button class="mini-button primary finance-action" type="button" data-open-districts="investment" data-period="h1">Туманлар кесимига ўтиш</button>
        </div>
      </article>`;
    }

    function financeExportCard(activeId) {
      const def = kpiDefById("export");
      const e = DATA.regional.export || {};
      const q1 = dashboardPeriodKpi("export", "q1");
      const h1 = dashboardPeriodKpi("export", "h1");
      const year = dashboardPeriodKpi("export", "year");
      return `<article class="finance-card ${activeId === "export" ? "active" : ""}">
        ${financeCardHead(def)}
        <div class="finance-card-body">
          <div class="finance-metric-row two">
            ${financeMetric("Йиллик ўсиш", growthValue(year.growth), "", "gold")}
            ${financeMetric("Йиллик кутилиш", factValue(year))}
          </div>
          <div class="finance-divider"></div>
          ${financeMetric("Экспортёрлар", `${fmt(e.year_exporters, 0)} та`, "йиллик режа")}
          <div class="finance-quarter-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            ${financeQuarterCell("I чорак амалда", q1, { main: growthValue(q1.growth), note: factValue(q1), tone: "accent" })}
            ${financeQuarterCell("II чорак кутилиш", h1, { main: growthValue(h1.growth), note: factValue(h1), tone: "gold" })}
          </div>
          <button class="mini-button primary finance-action" type="button" data-open-districts="export" data-period="h1">Туманлар кесимига ўтиш</button>
        </div>
      </article>`;
    }

    function financeActiveCard(activeId) {
      const renderers = {
        budget: financeBudgetCard,
        budget_investment: financeBudgetInvestmentCard,
        investment: financeInvestmentCard,
        export: financeExportCard
      };
      return (renderers[activeId] || financeBudgetCard)(activeId);
    }

    function financeSourceText(kpiId) {
      const map = {
        budget: "Манба: 3-жадвал (бюджет тушумлари).",
        budget_investment: "Манба: 4.1-жадвал (бюджет инвестициялари).",
        investment: "Манба: 4.2-жадвал (хорижий инвестициялар).",
        export: "Манба: 5.1-5.2-жадваллар (экспорт)."
      };
      return map[kpiId] || "Манба: тегишли жадвал маълумотлари.";
    }

    function renderFinanceSectorPanel(selected) {
      const activeId = isFinanceKpi(selected.id) ? selected.id : "budget";
      return `<section class="finance-sector-panel" aria-label="Молия, инвестиция ва экспорт KPI мониторинги">
        <div class="finance-sector-grid">
          <div class="finance-active-pane">${financeActiveCard(activeId)}</div>
        </div>
        <p class="finance-source">${financeSourceText(activeId)}</p>
      </section>`;
    }

    function kpiDashboardCard(def) {
      const macroGrowthKpi = def.id === "grp" || macroComponentDefs.some(item => item.id === def.id);
      const growthOnly = macroGrowthKpi;
      const districtRoute = dashboardDistrictRoute(def);
      const quarters = [
        ["I чорак", "q1"],
        ["II чорак", "h1"],
        ["III чорак", "m9"],
        ["Йиллик", "year"]
      ];
      const seriesValues = quarters.map(([, period]) => {
        const r = dashboardPeriodKpi(def.id, period);
        if (n(r.growth) !== null) return n(r.growth);
        if (n(r.execution) !== null) return n(r.execution);
        if (n(r.fact) !== null) return n(r.fact);
        if (n(r.plan) !== null) return n(r.plan);
        return null;
      });
      const trendFor = (cur, prev) => {
        if (cur === null || prev === null) return null;
        const lowerBetter = ["inflation", "poverty", "unemployment"].includes(def.id);
        const delta = cur - prev;
        if (Math.abs(delta) < 0.05) return { cls: "flat" };
        const up = delta > 0;
        return { cls: lowerBetter ? (up ? "down" : "up") : (up ? "up" : "down") };
      };
      return `<article class="kpi-monitor-card ${macroGrowthKpi ? "macro-layout-card" : ""}">
        <div class="kpi-monitor-head">
          <div class="small-icon">${icon(def.icon)}</div>
          <div>
            <h3>${def.short}</h3>
            <p>${def.label}</p>
          </div>
          ${districtRoute && def.id !== "grp" ? `<button class="mini-button primary kpi-head-district" type="button" data-open-districts="${districtRoute.kpiId}">${districtRoute.label}</button>` : ""}
          <div class="head-watermark" aria-hidden="true">${icon(def.icon)}</div>
        </div>
        ${def.id === "inflation" ? "" : def.id === "budget_investment" ? renderBudgetInvestmentPanel() : macroGrowthKpi ? renderMacroGrowthPanel(def) : `<div class="quarter-matrix">
          ${quarters.map(([label, period], idx) => {
            const row = dashboardPeriodKpi(def.id, period);
            const stateInfo = periodState(def, period, row);
            const main = n(row.growth) !== null ? growthValue(row.growth) : n(row.execution) !== null ? `${fmt(row.execution, 1)}%` : primaryMetric(row);
            const fact = factValue(row);
            const plan = planValue(row);
            const measureLabel = n(row.growth) !== null ? "Ўсиш" : executionLabel(def, row, period);
            const trend = idx > 0 ? trendFor(seriesValues[idx], seriesValues[idx - 1]) : null;
            const heroValue = trend
              ? `<span class="q-hero-value q-trend ${trend.cls}">${main}</span>`
              : `<span class="q-hero-value">${main}</span>`;
            const reportFooter = row.reportStatusLabel ? `<div class="q-report"><span>Таъсир</span><b>${row.reportImpact || "KPIга қўшилмади"}</b></div>` : "";
            const chipClass = row.reportStatus ? reportStatusClass(row.reportStatus) : stateInfo.chip;
            const chipText = row.reportStatusLabel || stateInfo.label;
            const growthText = n(row.growth) !== null ? growthValue(row.growth) : "—";
            const planDisplay = growthOnly ? (stateInfo.cls === "actual" ? "—" : growthText) : plan;
            const factDisplay = growthOnly ? (stateInfo.cls === "actual" ? growthText : fact) : fact;
            const statusText = chipText || (stateInfo.cls === "actual" ? "Амалда бор" : "—");
            const sourceKind = periodSourceKind(def, period, row);
            const hidePlanRow = macroGrowthKpi || sourceKind === "target" || (def.id === "investment" && sourceKind === "expected");
            const auxRows = `<dl class="q-aux">
              ${hidePlanRow ? "" : `<div class="q-aux-row"><span>${planLabel(def, row, period)}</span><b class="num">${planDisplay}</b></div>`}
              <div class="q-aux-row"><span>${factLabel(def, row, period)}</span><b class="num">${factDisplay}</b></div>
              <div class="q-aux-row status"><span>Ҳолат</span><b><span class="chip ${chipClass || "grey"}">${statusText}</span></b></div>
            </dl>`;
            return `<div class="quarter-row ${stateInfo.cls}">
              <div class="q-head">
                <span class="q-period">${label}</span>
              </div>
              <div class="q-hero">
                ${heroValue}
                <span class="q-hero-label">${measureLabel}</span>
              </div>
              ${auxRows}
              ${reportFooter}
            </div>`;
          }).join("")}
        </div>`}
        ${def.id === "inflation" ? renderInflationDetails() : ""}
        ${def.id === "unemployment" ? renderUnemploymentDetails() : ""}
        ${def.id === "poverty" ? renderPovertyDetails() : ""}
        ${isFinanceKpi(def.id) ? `<p class="finance-source">${financeSourceText(def.id)}</p>` : ""}
      </article>`;
    }


    function renderInflationDetails() {
      const priceCaps = [
        ["Гўшт ва гўшт маҳсулотлари", "6–7%дан ошмаслик"],
        ["Тухум", "5–6%дан ошмаслик"],
        ["Сут ва сут маҳсулотлари", "6–7%дан ошмаслик"],
        ["Картошка", "4–5%дан ошмаслик"],
        ["Пиёз", "5%дан ошмаслик"],
        ["Сабзи", "5%дан ошмаслик"],
        ["Гуруч", "2025 йил даражасида"],
        ["Ун", "2025 йил даражасида"]
      ];
      const foods = (DATA.regional.food_balance || [])
        .filter(row => row.product && row.product !== "шундан:" && n(row.resource_total) !== null);
      const sensitiveFoods = [...foods]
        .filter(row => n(row.local_supply_ratio) !== null)
        .sort((a, b) => (n(a.local_supply_ratio) || 0) - (n(b.local_supply_ratio) || 0))
        .slice(0, 4);
      return `<div class="drivers">
        <div class="lagging">
          <div class="lagging-title"><strong>Инфляция чегаралари</strong></div>
          <div class="driver-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div class="driver-card"><span>II чорак</span><strong>≤2,9%</strong><small>амалдаги инфляцияга нисбатан</small></div>
            <div class="driver-card"><span>Йил якуни</span><strong>≤6,6%</strong><small>йил якуни бўйича чегара</small></div>
          </div>
          <p class="data-note">Амалдаги инфляция маълумоти киритилмаган.</p>
        </div>
        <div class="composition">
          <div class="lagging-title"><strong>Асосий озиқ-овқат нархлари</strong></div>
          <div class="composition-grid">
            ${priceCaps.map(([name, cap]) => `<button class="component-card product-card" type="button">
              <span class="product-icon" aria-hidden="true">${foodIcon(name)}</span>
              <span class="product-body">
                <span class="product-name">${name}</span>
                <strong class="product-value">${cap}</strong>
                <small class="product-note">йиллик нарх чегараси</small>
              </span>
            </button>`).join("")}
          </div>
        </div>
        <div class="composition">
          <div class="lagging-title"><strong>Озиқ-овқат балансида эътибор талаб қиладиган маҳсулотлар</strong></div>
          <div class="composition-grid">
            ${sensitiveFoods.map(row => `<button class="component-card product-card" type="button">
              <span class="product-icon" aria-hidden="true">${foodIcon(row.product)}</span>
              <span class="product-body">
                <span class="product-name">${row.product}</span>
                <strong class="product-value">${fmt((n(row.local_supply_ratio) || 0) * 100, 1)}%</strong>
                <small class="product-note">маҳаллий таъминланиш · ресурс ${fmt(row.resource_total, 1)} минг т · импорт ${fmt(row.import, 1)} минг т</small>
              </span>
            </button>`).join("")}
          </div>
        </div>
        <div class="lagging">
          <div class="lagging-title"><strong>Омборлар туманлар кесимида</strong></div>
          <div class="driver-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
            <div class="driver-card"><span>Совутгичли омборлар</span><strong>33 та</strong><small>II чорак: 4 та, 1 300 т · йил: 8 810 т</small></div>
            <div class="driver-card"><span>Захира жамғармаси</span><strong>50 млрд сўм</strong><small>йиллик режа</small></div>
          </div>
        </div>
        <p class="finance-source">Манба: 2.1-2.2-жадваллар ва кафолат хати II-бўлим.</p>
      </div>`;
    }

    function renderUnemploymentDetails() {
      const emp = DATA.regional.employment || {};
      const stats = [
        { id: "jobs",         icon: "briefcase", label: "Доимий ишга жойлаштириш",         h1: emp.jobs_h1,         year: emp.jobs_year,         unit: "минг", digits: 1 },
        { id: "legalization", icon: "users",     label: "Норасмий бандларни легаллаштириш", h1: emp.legalization_h1, year: emp.legalization_year, unit: "минг", digits: 1 }
      ];
      return `<div class="drivers poverty-section employment-driver-section">
        <div class="lagging">
          <header class="poverty-head">
            <div>
              <strong>Ишсизликни пасайтириш драйверлари</strong>
              <p>Ишсизлик KPI мақсадини бажариш учун бандлик бўйича асосий ўлчанадиган ишлар.</p>
            </div>
            <button class="mini-button primary" type="button" data-open-districts="unemployment" data-period="h1">Туманлар кесими →</button>
          </header>
          <div class="poverty-stats">
          ${stats.map(s => {
            const h1Num = n(s.h1);
            const yearNum = n(s.year);
            const pct = h1Num !== null && yearNum !== null && yearNum !== 0 ? Math.min(100, Math.max(0, (h1Num / yearNum) * 100)) : 0;
            const h1Text = h1Num !== null ? fmt(s.h1, s.digits) : "—";
            const yearText = yearNum !== null ? fmt(s.year, s.digits) : "—";
            return `<article class="poverty-stat">
              <div class="poverty-stat-icon" aria-hidden="true">${icon(s.icon)}</div>
              <div class="poverty-stat-body">
                <span class="poverty-stat-label">${s.label}</span>
                <strong class="poverty-stat-value">${yearText}<em>${s.unit}</em></strong>
                <div class="poverty-stat-meta">
                  <span>II чорак <b>${h1Text}</b></span>
                  <span class="poverty-stat-divider">·</span>
                  <span>Йиллик мақсад</span>
                </div>
                <div class="poverty-progress" role="progressbar" aria-valuenow="${pct.toFixed(0)}" aria-valuemin="0" aria-valuemax="100">
                  <i style="width:${pct.toFixed(1)}%"></i>
                </div>
                <small class="poverty-progress-label">II чорак йиллик мақсаднинг ${pct.toFixed(0)}%</small>
              </div>
            </article>`;
          }).join("")}
          </div>
        </div>
        <p class="finance-source">Манба: 6-жадвал ва кафолат хати.</p>
      </div>`;
    }

    function renderPovertyDetails() {
      const emp = DATA.regional.employment || {};
      const districts = DATA.districts || [];
      const clearTerritories = districts.filter(d => String(d.data?.employment?.poverty_year || "").includes("холи"));
      const stats = [
        { id: "jobs",          icon: "users",   label: "Доимий ишга жойлаштириш",        h1: emp.jobs_h1,          year: emp.jobs_year,          unit: "минг", digits: 1 },
        { id: "legalization",  icon: "globe",   label: "Норасмий бандларни легаллаштириш", h1: emp.legalization_h1,  year: emp.legalization_year,  unit: "минг", digits: 1 },
        { id: "mfy",           icon: "bank",    label: "Камбағалликдан холи МФЙлар",                         h1: emp.mfy_h1,           year: emp.mfy_year,           unit: "та",   digits: 0 },
        { id: "microprojects", icon: "rocket",  label: "Микролойиҳалар",                   h1: emp.microprojects_h1, year: emp.microprojects_year, unit: "та",   digits: 0 }
      ];
      return `<div class="drivers poverty-section">
        <div class="lagging">
          <header class="poverty-head">
            <div>
              <strong>Камбағалликка таъсир қилувчи драйверлар</strong>
            </div>
          </header>
          <div class="poverty-stats">
          ${stats.map(s => {
            const h1Num = n(s.h1);
            const yearNum = n(s.year);
            const pct = h1Num !== null && yearNum !== null && yearNum !== 0 ? Math.min(100, Math.max(0, (h1Num / yearNum) * 100)) : 0;
            const h1Text = h1Num !== null ? fmt(s.h1, s.digits) : "—";
            const yearText = yearNum !== null ? fmt(s.year, s.digits) : "—";
            return `<article class="poverty-stat">
              <div class="poverty-stat-icon" aria-hidden="true">${icon(s.icon)}</div>
              <div class="poverty-stat-body">
                <span class="poverty-stat-label">${s.label}</span>
                <strong class="poverty-stat-value">${yearText}<em>${s.unit}</em></strong>
                <div class="poverty-stat-meta">
                  <span>II чорак <b>${h1Text}</b></span>
                  <span class="poverty-stat-divider">·</span>
                  <span>Йиллик режа</span>
                </div>
                <div class="poverty-progress" role="progressbar" aria-valuenow="${pct.toFixed(0)}" aria-valuemin="0" aria-valuemax="100">
                  <i style="width:${pct.toFixed(1)}%"></i>
                </div>
                <small class="poverty-progress-label">II чорак йиллик режанинг ${pct.toFixed(0)}%</small>
              </div>
            </article>`;
          }).join("")}
          </div>
        </div>
        <article class="poverty-territory">
          <div class="poverty-territory-head">
            <div>
              <span class="poverty-territory-eyebrow">Камбағалликдан холи ҳудудлар режада</span>
              <strong>${fmt(clearTerritories.length, 0)}<em>та ҳудуд</em></strong>
            </div>
            <button class="mini-button primary" type="button" data-open-districts="poverty" data-period="year">Туманлар кесими →</button>
          </div>
          <p>Кафолат хатида камбағалликдан холи бўлиши кутилаётган маҳалла ва туманлар.</p>
          ${clearTerritories.length ? `<div class="poverty-territory-list">${clearTerritories.map(d => `<span class="poverty-territory-chip">${d.name}</span>`).join("")}</div>` : `<p class="poverty-territory-empty">Холи ҳудуд режасида ҳудуд белгиланмаган.</p>`}
        </article>
        <p class="finance-source">Манба: 6-жадвал ва кафолат хати.</p>
      </div>`;
    }

    function renderIndustryDrivers() {
      const districts = DATA.districts || [];
      const h1Projects = districts.reduce((s, d) => s + (n(d.data?.localization_projects_h1) || 0), 0);
      const h1Electricity = districts.reduce((s, d) => s + (n(d.data?.energy_electricity_h1) || 0), 0);
      const h1Gas = districts.reduce((s, d) => s + (n(d.data?.energy_gas_h1) || 0), 0);
      return renderIndustryDriversPanel();
    }

    function evidenceStorageKey(taskId) {
      return `andijon-v63-evidence:${taskId}`;
    }

    const REPORT_KEY = "andijon-v65-execution-reports";

    function parseReportNumber(value) {
      if (value === null || value === undefined) return null;
      const cleaned = String(value).replace(/\s+/g, "").replace(",", ".").replace(/[^\d.-]/g, "");
      if (!cleaned) return null;
      const parsed = Number(cleaned);
      return Number.isFinite(parsed) ? parsed : null;
    }

    function normalizeReportValue(value, fromUnit = "", toUnit = "") {
      const num = parseReportNumber(value);
      if (num === null) return null;
      const from = String(fromUnit).toLowerCase();
      const to = String(toUnit).toLowerCase();
      if (to.includes("минг доллар")) {
        if (from.includes("млн") || from.includes("млн $")) return num * 1000;
        if (from.includes("минг") || from.includes("минг $")) return num;
      }
      if (to.includes("млн доллар")) {
        if (from.includes("минг")) return num / 1000;
        if (from.includes("млн") || from.includes("млн $")) return num;
      }
      if (to.includes("млрд сўм")) {
        if (from.includes("трлн")) return num * 1000;
        if (from.includes("млрд")) return num;
        if (from.includes("млн")) return num / 1000;
      }
      if (to.includes("млн сўм")) {
        if (from.includes("млрд")) return num * 1000;
        if (from.includes("млн")) return num;
      }
      if (to.includes("%") && from.includes("%")) return num;
      if (!to || !from || to === from) return num;
      return null;
    }

    function approvedReportsFor(kpiId, period, districtName = null) {
      return getExecutionReports()
        .filter(report => report.status === "approved" && report.kpi === kpiId && report.period === period && (!districtName || report.district === districtName))
        .sort((a, b) => String(b.createdAt || b.date).localeCompare(String(a.createdAt || a.date)));
    }

    function latestReportFor(kpiId, period, districtName = null) {
      return approvedReportsFor(kpiId, period, districtName)[0] || null;
    }

    function latestAnyReportFor(kpiId, period, districtName = null) {
      return getExecutionReports()
        .filter(report => report.kpi === kpiId && report.period === period && (!districtName || report.district === districtName))
        .sort((a, b) => String(b.createdAt || b.date).localeCompare(String(a.createdAt || a.date)))[0] || null;
    }

    function openExecutionJournal(kpiId = null, districtName = null, period = null) {
      state.page = "execution";
      state.sector = kpiId || "all";
      state.reportDistrict = districtName || "all";
      state.reportPeriod = period || "all";
      state.reportStatus = "all";
      state.search = "";
      render();
    }

    function directionForKpi(kpiId) {
      return ["poverty", "unemployment", "inflation"].includes(kpiId) ? "lower" : "higher";
    }

    function applyApprovedReport(row, kpiId, period, districtName = null) {
      const base = { ...(row || {}) };
      const reports = approvedReportsFor(kpiId, period, districtName);
      if (!reports.length) {
        const pending = latestAnyReportFor(kpiId, period, districtName);
        return pending ? { ...base, reportStatus: pending.status, reportStatusLabel: reportStatusLabel(pending.status), reportImpact: reportImpactLabel(pending), reportDate: pending.date, reportId: pending.id } : base;
      }
      const targetUnit = base.unit || reports[0].unit || "";
      const normalized = reports
        .map(report => ({ report, value: normalizeReportValue(report.actualValue, report.unit, targetUnit) }))
        .filter(item => item.value !== null);
      if (!normalized.length) {
        const latest = reports[0];
        return {
          ...base,
          factText: `${latest.actualValue}${latest.unit ? ` ${latest.unit}` : ""}`,
          main: `${latest.actualValue}${latest.unit ? ` ${latest.unit}` : ""}`,
          reportStatus: latest.status,
          reportStatusLabel: reportStatusLabel(latest.status),
          reportImpact: reportImpactLabel(latest),
          reportDate: latest.date,
          reportId: latest.id,
          reportCount: reports.length,
          note: `тасдиқланган ҳисобот: ${latest.evidenceName || latest.date}`
        };
      }
      const useAggregate = !districtName && !["poverty", "unemployment", "inflation"].includes(kpiId);
      const fact = useAggregate
        ? normalized.reduce((sum, item) => sum + item.value, 0)
        : normalized[0].value;
      const execution = pct(fact, base.plan, directionForKpi(kpiId));
      const latest = normalized[0].report;
      const next = {
        ...base,
        fact,
        execution,
        status: statusFor(execution),
        reportStatus: latest.status,
        reportStatusLabel: reportStatusLabel(latest.status),
        reportImpact: reportImpactLabel(latest),
        reportDate: latest.date,
        reportId: latest.id,
        reportCount: reports.length,
        note: `${base.note ? `${base.note} · ` : ""}тасдиқланган ҳисобот${reports.length > 1 ? ` (${reports.length} та)` : ""}`
      };
      if (n(base.growth) === null) next.main = execution ? `${fmt(execution, 1)}%` : displayValue(fact, targetUnit);
      return next;
    }

    function reportKpiOptions() {
      return [...kpiDefs, ...macroComponentDefs, ...districtOnlyDefs]
        .filter((def, idx, arr) => def && arr.findIndex(item => item.id === def.id) === idx);
    }

    function reportEntryKpiOptions() {
      return reportKpiOptions().filter(def => tasksForKpi(def.id).length);
    }

    function defaultReportKpiId(preferred = null) {
      const options = reportEntryKpiOptions();
      const candidates = [
        preferred,
        state.sector !== "all" ? state.sector : null,
        state.kpi,
        "industry",
        ...options.map(def => def.id)
      ].filter(Boolean);
      return candidates.find(id => id !== "all" && tasksForKpi(id).length) || options[0]?.id || "industry";
    }

    function defaultReportUnit(kpiId, period = state.period) {
      const row = baseDashboardPeriodKpi(kpiId, period);
      if (row?.unit?.includes("минг доллар")) return "млн $";
      if (row?.unit?.includes("млн доллар")) return "млн $";
      if (row?.unit?.includes("млрд сўм")) return "млрд сўм";
      if (row?.unit?.includes("млн сўм")) return "млн сўм";
      if (row?.unit?.includes("%")) return "%";
      return row?.unit || "";
    }

    function kpiById(id) {
      return reportKpiOptions().find(def => def.id === id) || kpiDefs.find(def => def.id === id) || kpiDefs[0];
    }

    function getExecutionReports() {
      try {
        const raw = localStorage.getItem(REPORT_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        return [];
      }
    }

    function saveExecutionReports(items) {
      try {
        localStorage.setItem(REPORT_KEY, JSON.stringify(items));
      } catch (error) {
        return false;
      }
      return true;
    }

    function reportHistory(report) {
      const history = Array.isArray(report.history) ? report.history : [];
      if (history.length) return history;
      return [{
        status: report.status || "submitted",
        actor: report.createdBy || report.responsible || "Киритувчи",
        reason: report.comment || "",
        at: report.createdAt || report.date || ""
      }];
    }

    function reportImpactLabel(report) {
      if (report.status === "approved") return "KPIга қўшилди";
      return "KPIга қўшилмади";
    }

    function reportImpactClass(report) {
      if (report.status === "approved") return "green";
      if (report.status === "review") return "amber";
      if (report.status === "rejected") return "red";
      return "blue";
    }

    function latestStatusReason(report) {
      const latest = reportHistory(report)[0];
      return latest?.reason || report.statusReason || report.comment || "";
    }

    function updateExecutionReportStatus(id, status, meta = {}) {
      const reports = getExecutionReports();
      const index = reports.findIndex(report => report.id === id);
      if (index === -1) return;
      const now = new Date().toISOString();
      const actor = meta.actor || "Платформа администратори";
      const reason = meta.reason || "";
      const next = {
        ...reports[index],
        status,
        statusReason: reason,
        updatedAt: now,
        checkedBy: actor,
        checkedAt: now,
        history: [
          { status, actor, reason, at: now },
          ...reportHistory(reports[index])
        ]
      };
      if (status === "approved") {
        next.approvedBy = actor;
        next.approvedAt = now;
      }
      reports[index] = next;
      saveExecutionReports(reports);
      render();
    }

    function openStatusModal(id, status) {
      const report = getExecutionReports().find(item => item.id === id);
      if (!report) return;
      const requiresReason = status === "review" || status === "rejected";
      const actionText = {
        approved: "Платформага қабул қилиш ва KPIга ўтказиш",
        review: "Қайта кўришга қолдириш",
        rejected: "Қайтариш"
      }[status] || "Сақлаш";
      const effectText = status === "approved"
        ? "Бу Ҳисоб палатаси қатори KPI “амалда” қийматига қўшилади."
        : "Бу Ҳисоб палатаси қатори KPI натижасига қўшилмайди.";
      $("#reportModal").innerHTML = `
        <div class="modal-head">
          <div>
            <div class="modal-title" id="reportModalTitle">${actionText}</div>
            <div class="modal-sub">${h(report.district)} · ${h(report.kpiLabel)} · ${h(report.periodLabel || "")}. ${effectText}</div>
          </div>
          <button class="modal-close" type="button" data-close-report aria-label="Ёпиш">×</button>
        </div>
        <div class="modal-body">
          <div class="modal-grid">
            <div class="modal-field"><span>Амалдаги натижа</span><strong>${h(report.actualValue || "—")} ${h(report.unit || "")}</strong></div>
            <div class="modal-field"><span>Жорий ҳолат</span><strong><span class="chip ${reportStatusClass(report.status)}">${reportStatusLabel(report.status)}</span></strong></div>
            <div class="modal-field"><span>Янги ҳолат</span><strong><span class="chip ${reportStatusClass(status)}">${reportStatusLabel(status)}</span></strong></div>
          </div>
          <label class="modal-field"><span>Қабул қилувчи</span><input id="statusActor" type="text" value="${h(report.checkedBy || "Платформа администратори")}" placeholder="масалан: Платформа администратори"></label>
          <label class="modal-field"><span>${requiresReason ? "Сабаб / изоҳ (мажбурий)" : "Изоҳ"}</span><textarea id="statusReason" placeholder="${requiresReason ? "Қайтариш ёки қайта кўриш сабабини ёзинг" : "Қисқа изоҳ киритинг"}"></textarea><small class="field-error" id="statusReasonError">Сабаб киритилиши шарт.</small></label>
          <div class="action-row">
            <button class="mini-button primary" type="button" data-save-status="${id}:${status}">${actionText}</button>
            <button class="mini-button" type="button" data-close-report>Бекор қилиш</button>
          </div>
        </div>`;
      $("#reportModalBg").classList.add("open");
      $("#reportModalBg").setAttribute("aria-hidden", "false");
      $("#statusActor")?.focus();
    }

    function saveStatusChange(payload) {
      const [id, status] = payload.split(":");
      const reasonField = $("#statusReason");
      const actor = $("#statusActor")?.value.trim() || "Вилоят ҳокимлиги";
      const reason = reasonField?.value.trim() || "";
      const requiresReason = status === "review" || status === "rejected";
      if (requiresReason && !reason) {
        reasonField?.setAttribute("aria-invalid", "true");
        $("#statusReasonError")?.classList.add("show");
        reasonField?.focus();
        return;
      }
      updateExecutionReportStatus(id, status, { actor, reason });
      closeReportModal();
    }

    function defaultTaskForReport(kpiId) {
      return tasksForKpi(kpiId)[0]?.id || "";
    }

    function filteredExecutionReports() {
      const q = state.search.trim().toLowerCase();
      return getExecutionReports().filter(report => {
        const kpiOk = state.sector === "all" || report.kpi === state.sector;
        const statusOk = state.reportStatus === "all" || report.status === state.reportStatus;
        const periodOk = state.reportPeriod === "all" || report.period === state.reportPeriod;
        const districtOk = state.reportDistrict === "all" || report.district === state.reportDistrict;
        const qOk = !q || `${report.district} ${report.kpiLabel} ${report.taskTitle} ${report.templateRowId} ${report.executionStatus} ${report.evidenceStatus} ${report.issueType} ${report.comment} ${report.responsible} ${report.createdBy} ${report.checkedBy} ${latestStatusReason(report)}`.toLowerCase().includes(q);
        return kpiOk && statusOk && periodOk && districtOk && qOk;
      });
    }

    function getTaskEvidence(taskId) {
      try {
        const raw = localStorage.getItem(evidenceStorageKey(taskId));
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        return [];
      }
    }

    function saveTaskEvidence(taskId, items) {
      try {
        localStorage.setItem(evidenceStorageKey(taskId), JSON.stringify(items));
      } catch (error) {
        return false;
      }
      return true;
    }

    function openTaskModal(taskId) {
      const task = DATA.tasks.find(item => item.id === taskId);
      if (!task) return;
      const evidences = getTaskEvidence(taskId);
      const reports = getExecutionReports().filter(report => report.taskId === taskId);
      const kpi = kpiById(task.kpi || state.kpi);
      const districtText = Array.isArray(task.districts) && task.districts.length ? task.districts.join(", ") : "Вилоят даражаси";
      $("#taskModal").innerHTML = `
        <div class="modal-head">
          <div>
            <div class="modal-title" id="taskModalTitle">${h(task.id)} · ${h(cleanTaskTitle(task.title))}</div>
            <div class="modal-sub">${h(task.section || task.sector)} · ${h(task.deadline || task.period)} · ${h(task.owner)}</div>
          </div>
          <button class="modal-close" type="button" data-close-modal aria-label="Ёпиш">×</button>
        </div>
        <div class="modal-body">
          <div class="modal-grid">
            <div class="modal-field"><span>Ҳолат</span><strong><span class="chip ${task.status || "grey"}">${statusLabel(task.status || "grey")}</span></strong></div>
            <div class="modal-field"><span>KPI боғланиши</span><strong>${h(kpi.short)} · ${h(kpi.label)}</strong></div>
            <div class="modal-field"><span>Муддат</span><strong>${h(task.deadline || task.period || "—")}</strong></div>
          </div>
          <div class="modal-grid">
            <div class="modal-field"><span>Ҳудуд</span><strong>${h(districtText)}</strong></div>
            <div class="modal-field"><span>Гуруҳ</span><strong>${h(task.group || taskGroupLabel(task, kpi.id))}</strong></div>
            <div class="modal-field"><span>Йўналиш</span><strong>${h(task.moduleLabel || task.sector || "—")}</strong></div>
          </div>
          <div class="callout-note">Бу топшириқ KPIга эришиш учун бажариладиган амалий иш сифатида юритилади. Ҳисобот киритилса, унинг ҳолати ижро журналида кўринади.</div>
          <div>
            <div class="eyebrow">Далил / изоҳ</div>
            <textarea id="taskEvidenceText" placeholder="Қисқа изоҳ, файл номи ёки далил ҳаволасини киритинг"></textarea>
            <div class="action-row">
              <button class="mini-button primary" type="button" data-save-evidence="${task.id}">Сақлаш</button>
            </div>
          </div>
          <div>
            <div class="eyebrow">Киритилган далиллар</div>
            <div class="evidence-list">
              ${evidences.length ? evidences.map(item => `<div class="evidence-item"><span>${h(item.date)}</span><p>${h(item.text)}</p></div>`).join("") : `<p class="muted">Ҳали далил ёки изоҳ киритилмаган.</p>`}
            </div>
          </div>
          <div>
            <div class="eyebrow">Ижро ҳисоботлари</div>
            <div class="evidence-list">
              ${reports.length ? reports.map(report => `<div class="evidence-item"><span>${h(report.date)} · ${h(report.district)} · ${h(report.kpiLabel)}</span><p><b>${h(report.actualValue || "—")} ${h(report.unit || "")}</b> <span class="chip ${reportStatusClass(report.status)}">${reportStatusLabel(report.status)}</span> <span class="chip ${reportImpactClass(report)}">${reportImpactLabel(report)}</span><br>${h(report.comment || "")}</p></div>`).join("") : `<p class="muted">Бу топшириқ бўйича расмий ижро ҳисоботи киритилмаган.</p>`}
            </div>
          </div>
          <div class="action-row">
            <button class="mini-button primary" type="button" data-open-report-from-task="${task.id}">Шу топшириқ бўйича текширув киритиш</button>
          </div>
        </div>`;
      $("#taskModalBg").classList.add("open");
      $("#taskModalBg").setAttribute("aria-hidden", "false");
      $("#taskEvidenceText")?.focus();
      $$("[data-open-report-from-task]", $("#taskModal")).forEach(btn => btn.addEventListener("click", () => {
        closeTaskModal();
        const district = Array.isArray(task.districts) && task.districts.length ? task.districts[0] : state.district;
        openReportModal({ kpi: task.kpi || state.kpi, district, period: task.periodCode || state.period });
      }));
    }

    function closeTaskModal() {
      $("#taskModalBg").classList.remove("open");
      $("#taskModalBg").setAttribute("aria-hidden", "true");
    }

    function addTaskEvidence(taskId) {
      const field = $("#taskEvidenceText");
      const text = field ? field.value.trim() : "";
      if (!text) return;
      const items = getTaskEvidence(taskId);
      items.unshift({ text, date: new Date().toLocaleString("uz-Cyrl-UZ") });
      saveTaskEvidence(taskId, items);
      openTaskModal(taskId);
    }

    function tasksForReportContext(kpiId, districtName) {
      const all = tasksForKpi(kpiId);
      const specific = all.filter(task => Array.isArray(task.districts) && task.districts.includes(districtName));
      return specific.length ? specific : all;
    }

    function taskOptionsHtml(tasks, selectedTaskId = "") {
      return tasks.length
        ? tasks.map(t => `<option value="${t.id}" ${t.id === selectedTaskId ? "selected" : ""}>${h(`${t.id} · ${cleanTaskTitle(t.title)}`.slice(0, 145))}</option>`).join("")
        : `<option value="" disabled>Бу KPI бўйича текширув топшириғи йўқ</option>`;
    }

    function openReportModal(options = {}) {
      const selectedKpi = defaultReportKpiId(options.kpi || (state.sector !== "all" ? state.sector : state.kpi));
      const selectedDistrict = options.district || (state.reportDistrict !== "all" ? state.reportDistrict : state.district);
      const selectedPeriod = options.period || (state.reportPeriod !== "all" ? state.reportPeriod : state.period);
      const kpi = kpiById(selectedKpi);
      const entryKpiOptions = reportEntryKpiOptions();
      const district = (DATA.districts || []).find(item => item.name === selectedDistrict) || currentDistrict();
      const tasks = tasksForReportContext(kpi.id, district.name);
      const taskId = tasks[0]?.id || defaultTaskForReport(kpi.id);
      const unit = defaultReportUnit(kpi.id, selectedPeriod);
      $("#reportModal").innerHTML = `
        <div class="modal-head">
          <div>
            <div class="modal-title" id="reportModalTitle">Ҳисоб палатаси текширувини киритиш</div>
            <div class="modal-sub">Ҳисоб палатаси текширув натижасини киритинг. Фақат “Тасдиқланди” ҳолатидаги қатор KPI “амалда” қийматига қўшилади.</div>
          </div>
          <button class="modal-close" type="button" data-close-report aria-label="Ёпиш">×</button>
        </div>
        <div class="modal-body">
          <div class="report-context">
            <div class="context-pill"><span>KPI</span><strong id="reportContextKpi">${h(kpi.short)}</strong></div>
            <div class="context-pill"><span>Туман/шаҳар</span><strong id="reportContextDistrict">${h(district.name)}</strong></div>
            <div class="context-pill"><span>Манба</span><strong>Ҳисоб палатаси текшируви</strong></div>
          </div>
          <div class="modal-grid">
            <label class="modal-field"><span>Давр</span>
              <select id="reportPeriod">
                <option value="q1" ${selectedPeriod === "q1" ? "selected" : ""}>I чорак</option>
                ${periods.map(p => `<option value="${p.id}" ${p.id === selectedPeriod ? "selected" : ""}>${p.label}</option>`).join("")}
              </select>
            </label>
            <label class="modal-field"><span>KPI</span>
              <select id="reportKpi">
                ${entryKpiOptions.map(def => `<option value="${def.id}" ${def.id === kpi.id ? "selected" : ""}>${def.short}</option>`).join("")}
              </select>
            </label>
            <label class="modal-field"><span>Туман/шаҳар</span>
              <select id="reportDistrict">
                ${(DATA.districts || []).map(d => `<option value="${h(d.name)}" ${d.name === district.name ? "selected" : ""}>${h(d.name)}</option>`).join("")}
              </select>
            </label>
          </div>
          <div class="modal-grid">
            <label class="modal-field"><span>Template ID</span><input id="reportTemplateRow" type="text" value="${h(taskId)}" placeholder="T-001 / D-01"></label>
            <label class="modal-field"><span>Ҳисобот ҳолати</span>
              <select id="reportStatusInput">
                <option value="submitted">Киритилди</option>
                <option value="review">Кўриб чиқилмоқда</option>
                <option value="approved">Тасдиқланди</option>
                <option value="rejected">Қайтарилди / рад этилди</option>
              </select>
            </label>
            <label class="modal-field"><span>Ижро ҳолати</span>
              <select id="reportExecutionStatus">
                <option value="Бажарилди">Бажарилди</option>
                <option value="Қисман бажарилди">Қисман бажарилди</option>
                <option value="Бажарилмади">Бажарилмади</option>
                <option value="Муддати кечикди">Муддати кечикди</option>
                <option value="Маълумот йўқ">Маълумот йўқ</option>
              </select>
            </label>
          </div>
          <div class="modal-grid">
            <label class="modal-field"><span>Далил ҳолати</span>
              <select id="reportEvidenceStatus">
                <option value="Етарли">Етарли</option>
                <option value="Етарли эмас">Етарли эмас</option>
                <option value="Далил йўқ">Далил йўқ</option>
              </select>
            </label>
            <label class="modal-field"><span>Амалдаги натижа</span><input id="reportActual" type="text" required placeholder="масалан: 15,2"><small class="field-error" id="reportActualError">Рақам киритинг. Масалан: 15,2</small></label>
            <label class="modal-field"><span>Далил / файл / ҳавола</span><input id="reportEvidence" type="text" placeholder="файл номи ёки ҳавола"></label>
          </div>
          <label class="modal-field"><span>Изоҳ</span><textarea id="reportComment" placeholder="Қисқа изоҳ киритинг"></textarea></label>
          <details class="advanced-report">
            <summary>Қўшимча маълумот</summary>
            <div class="modal-grid">
              <label class="modal-field"><span>Бирлик</span><input id="reportUnit" type="text" value="${h(unit)}" placeholder="млн $, %, млрд сўм"></label>
              <label class="modal-field"><span>Муаммо тури</span>
                <select id="reportIssueType">
                  <option value="Муаммо йўқ">Муаммо йўқ</option>
                  <option value="Молиялаштириш">Молиялаштириш</option>
                  <option value="Харид жараёни">Харид жараёни</option>
                  <option value="Муддат кечикиши">Муддат кечикиши</option>
                  <option value="Далил етарсиз">Далил етарсиз</option>
                  <option value="Маълумот номувофиқ">Маълумот номувофиқ</option>
                  <option value="Ижрочи масъулияти">Ижрочи масъулияти</option>
                  <option value="Ташқи омил">Ташқи омил</option>
                  <option value="Бошқа">Бошқа</option>
                </select>
              </label>
              <label class="modal-field"><span>Тузатиш муддати</span><input id="reportCorrectionDeadline" type="date"></label>
            </div>
            <div class="modal-grid">
              <label class="modal-field"><span>Масъул</span><input id="reportResponsible" type="text" value="${h(district.owner || "Туман ҳокимлиги")}"></label>
              <label class="modal-field"><span>Текширув санаси</span><input id="reportDate" type="date" value="${new Date().toISOString().slice(0, 10)}"></label>
              <label class="modal-field"><span>Текширувчи</span><input id="reportSubmitter" type="text" value="Ҳисоб палатаси" placeholder="Ҳисоб палатаси масъул ходими"></label>
            </div>
            <div style="padding: 0 12px 12px">
              <label class="modal-field"><span>Боғланган топшириқ</span>
                <select id="reportTask">
                  ${taskOptionsHtml(tasks, taskId)}
                </select>
              </label>
            </div>
          </details>
          <div class="action-row">
            <button class="mini-button primary" type="button" data-save-report>Текширувни киритиш</button>
            <button class="mini-button" type="button" data-close-report>Бекор қилиш</button>
          </div>
        </div>`;
      $("#reportModalBg").classList.add("open");
      $("#reportModalBg").setAttribute("aria-hidden", "false");
      $("#reportActual")?.focus();
      $("#reportKpi").addEventListener("change", event => {
        const nextTasks = tasksForReportContext(event.target.value, $("#reportDistrict").value);
        const nextKpi = kpiById(event.target.value);
        $("#reportContextKpi").textContent = nextKpi.short;
        $("#reportTask").innerHTML = taskOptionsHtml(nextTasks);
        $("#reportTemplateRow").value = $("#reportTask").value || "";
        $("#reportUnit").value = defaultReportUnit(event.target.value, $("#reportPeriod").value);
      });
      $("#reportPeriod").addEventListener("change", () => {
        $("#reportUnit").value = defaultReportUnit($("#reportKpi").value, $("#reportPeriod").value);
      });
      $("#reportDistrict").addEventListener("change", event => {
        const nextDistrict = (DATA.districts || []).find(item => item.name === event.target.value);
        $("#reportContextDistrict").textContent = event.target.value;
        if (nextDistrict) $("#reportResponsible").value = nextDistrict.owner || "";
        $("#reportTask").innerHTML = taskOptionsHtml(tasksForReportContext($("#reportKpi").value, event.target.value));
        $("#reportTemplateRow").value = $("#reportTask").value || "";
      });
      $("#reportTask").addEventListener("change", event => {
        $("#reportTemplateRow").value = event.target.value || "";
      });
    }

    function closeReportModal() {
      $("#reportModalBg").classList.remove("open");
      $("#reportModalBg").setAttribute("aria-hidden", "true");
    }

    function addExecutionReport() {
      const kpi = kpiById($("#reportKpi").value);
      const actualValue = $("#reportActual").value.trim();
      if (!actualValue || parseReportNumber(actualValue) === null) {
        $("#reportActual").focus();
        $("#reportActual").setAttribute("aria-invalid", "true");
        $("#reportActualError")?.classList.add("show");
        return;
      }
      const taskId = $("#reportTask").value;
      const task = DATA.tasks.find(t => t.id === taskId);
      const templateRowId = $("#reportTemplateRow").value.trim() || taskId;
      if (!taskId || !templateRowId || !task) {
        $("#reportTask")?.focus();
        return;
      }
      const createdAt = new Date().toISOString();
      const createdBy = $("#reportSubmitter").value.trim() || "Ҳисоб палатаси";
      const reportStatus = $("#reportStatusInput").value || "submitted";
      const executionStatus = $("#reportExecutionStatus").value || "Маълумот йўқ";
      const evidenceStatus = $("#reportEvidenceStatus").value || "Далил йўқ";
      let issueType = $("#reportIssueType").value || "Муаммо йўқ";
      const correctionDeadline = $("#reportCorrectionDeadline").value || "";
      const requiresIssue = ["review", "rejected"].includes(reportStatus)
        || ["Бажарилмади", "Муддати кечикди"].includes(executionStatus)
        || evidenceStatus !== "Етарли";
      if (requiresIssue && issueType === "Муаммо йўқ") issueType = evidenceStatus !== "Етарли" ? "Далил етарсиз" : executionStatus === "Муддати кечикди" ? "Муддат кечикиши" : "Бошқа";
      const item = {
        id: `report-${Date.now()}`,
        createdAt,
        updatedAt: createdAt,
        date: $("#reportDate").value || new Date().toISOString().slice(0, 10),
        period: $("#reportPeriod").value,
        periodLabel: $("#reportPeriod").selectedOptions[0]?.textContent || "Давр",
        kpi: kpi.id,
        kpiLabel: kpi.short,
        district: $("#reportDistrict").value,
        taskId,
        templateRowId,
        reportSource: "Ҳисоб палатаси текшируви",
        executionStatus,
        evidenceStatus,
        issueType,
        correctionDeadline,
        taskTitle: cleanTaskTitle(task.title),
        actualValue,
        unit: $("#reportUnit").value.trim(),
        comment: $("#reportComment").value.trim(),
        evidenceName: $("#reportEvidence").value.trim(),
        responsible: $("#reportResponsible").value.trim(),
        createdBy,
        checkedBy: createdBy,
        checkedAt: $("#reportDate").value || createdAt,
        status: reportStatus,
        history: [{
          status: reportStatus,
          actor: createdBy,
          reason: $("#reportComment").value.trim(),
          at: createdAt
        }]
      };
      if (reportStatus === "approved") {
        item.approvedBy = createdBy;
        item.approvedAt = createdAt;
      }
      const reports = getExecutionReports();
      reports.unshift(item);
      saveExecutionReports(reports);
      closeReportModal();
      state.page = "execution";
      state.sector = kpi.id;
      state.reportDistrict = item.district;
      state.reportPeriod = item.period;
      state.reportStatus = "all";
      state.search = "";
      render();
    }

    function taskCard(t) {
      const status = t.status || "grey";
      return `<article class="task-card" data-task-id="${t.id}">
        <span class="task-code">${t.id}</span>
        <header><strong>${cleanTaskTitle(t.title)}</strong><span class="chip ${status}">${statusLabel(status)}</span></header>
        <div class="task-meta"><span>${t.sector}</span><span>${t.period}</span><span>${t.owner}</span></div>
      </article>`;
    }

    function taskSelectorDefs() {
      return [
        ...kpiDefs.filter(def => def.id !== "grp"),
        ...macroComponentDefs,
        ...districtOnlyDefs
      ].filter((def, idx, arr) => def && arr.findIndex(item => item.id === def.id) === idx);
    }

    function taskModules() {
      return Array.isArray(DATA.task_modules) ? DATA.task_modules : [];
    }

    function moduleById(id) {
      return taskModules().find(module => module.id === id) || null;
    }

    function taskKpiOptionsForModule(moduleId = state.taskModule) {
      const scopedTasks = DATA.tasks.filter(t => moduleId === "all" || t.module === moduleId);
      const ids = [...new Set(scopedTasks.map(t => t.kpi).filter(Boolean))];
      return taskSelectorDefs().filter(def => ids.includes(def.id));
    }

    function taskGroupLabel(task, kpiId) {
      return "Ижро топшириғи";
    }

    function taskGroupOrder(label) {
      return 0;
    }

    function taskActionTarget(kpiId) {
      if (districtSelectorDefs().some(def => def.id === kpiId)) return "districts";
      return "dashboard";
    }

    function compactTaskCard(t, kpiId) {
      const status = t.status || "grey";
      const evidenceCount = getTaskEvidence(t.id).length;
      const reports = getExecutionReports().filter(report => report.taskId === t.id);
      const approved = reports.filter(report => report.status === "approved").length;
      const districtText = Array.isArray(t.districts) && t.districts.length ? `${t.districts.length} туман/шаҳар` : t.scope || "вилоят";
      return `<article class="task-card compact" data-task-id="${t.id}">
        <header>
          <span class="task-code">${t.id}</span>
          <strong>${cleanTaskTitle(t.title)}</strong>
          <div class="task-meta"><span>${t.deadline || t.period}</span><span>${districtText}</span><span>${t.owner || t.moduleShort || t.sector}</span></div>
        </header>
        <div class="task-actions">
          <span class="chip ${status}">${statusLabel(status)}</span>
          ${evidenceCount ? `<span class="chip blue">${evidenceCount} далил</span>` : ""}
          ${reports.length ? `<span class="chip ${approved ? "green" : "blue"}">${approved ? "тасдиқланган ҳисобот" : `${reports.length} ҳисобот`}</span>` : ""}
        </div>
      </article>`;
    }

    function cleanTaskTitle(text) {
      return (text || "").replace(/\s+/g, " ").trim();
    }

    function renderKpiPage() {
      const selected = currentKpiDef();
      const rows = kpiDefs.map(def => {
        const r = regionalKpi(def.id);
        return { def, ...r };
      });
      const activeRow = regionalKpi(selected.id);
      const relatedDistricts = filteredDistricts().map(d => ({ d, k: districtKpi(d, selected.id) }));
      $("#kpiPage").innerHTML = `
        <div class="grid-2">
          <article class="panel">
            <div class="panel-head">
              <div><h3>KPI мониторинг</h3><p>Ҳар бир кўрсаткич бўйича фақат факт, режа ва ижро кўрсатилади.</p></div>
              <span class="chip blue">${periodLabel()}</span>
            </div>
            <div class="table-scroll">
              <table>
                <thead><tr><th style="width:25%">KPI</th><th class="num">Факт</th><th class="num">Режа</th><th class="num">Ижро</th><th style="width:16%">Ҳолат</th></tr></thead>
                <tbody>${rows.map(r => `<tr class="clickable ${r.def.id === state.kpi ? "active-row" : ""}" data-kpi="${r.def.id}">
                  <td><strong>${r.def.label}</strong><br><span class="muted">${r.def.sector}</span></td>
                  <td class="num">${displayValue(r.fact, r.unit)}</td>
                  <td class="num">${displayValue(r.plan, r.unit)}</td>
                  <td class="num">${r.execution ? `${fmt(r.execution)}%` : "—"}<div class="progress"><i style="--w:${Math.min(100, r.execution || 0)}%;--c:${colorFor(statusFor(r.execution))}"></i></div></td>
                  <td><span class="chip ${r.status || statusFor(r.execution)}">${statusLabel(r.status || statusFor(r.execution))}</span></td>
                </tr>`).join("")}</tbody>
              </table>
            </div>
          </article>
          <article class="panel">
            <div class="panel-head">
              <div><h3>${selected.short}: KPI ҳолати</h3><p>Танланган кўрсаткичнинг давр, режа, амалда ва туманлар кесими.</p></div>
              <span class="chip ${activeRow.status || "grey"}">${statusLabel(activeRow.status || "grey")}</span>
            </div>
            <div class="panel-body">
              <div class="detail-main">
                <div><div class="eyebrow">${selected.sector}</div><strong>${activeRow.main || displayValue(activeRow.fact ?? activeRow.plan, activeRow.unit)}</strong></div>
                <span class="chip blue">${periodLabel()}</span>
              </div>
              <div class="kv">
                <div><span>Факт</span><b>${displayValue(activeRow.fact, activeRow.unit)}</b></div>
                <div><span>Режа</span><b>${displayValue(activeRow.plan, activeRow.unit)}</b></div>
                <div><span>Ижро</span><b>${activeRow.execution ? `${fmt(activeRow.execution)}%` : "—"}</b></div>
              </div>
              <h3 style="font-size:15px;margin:16px 0 8px">Туманлар кесимида режа</h3>
              <div class="task-list">${relatedDistricts.slice(0, 7).map(({d, k}) => `<article class="task-card" data-district="${d.name}">
                <header><strong>${d.name}</strong><span class="chip ${d.debt.task_unfinished ? "red" : "green"}">${d.debt.task_unfinished}/${d.debt.task_total}</span></header>
                <div class="task-meta"><span>Факт: ${displayValue(k.fact, k.unit)}</span><span>Режа: ${displayValue(k.plan, k.unit)}</span><span>Ижро: ${k.execution ? `${fmt(k.execution)}%` : "—"}</span></div>
              </article>`).join("")}</div>
            </div>
          </article>
        </div>`;
      $$("tr[data-kpi]", $("#kpiPage")).forEach(row => row.addEventListener("click", () => { state.kpi = row.dataset.kpi; render(); }));
      $$("[data-district]", $("#kpiPage")).forEach(card => card.addEventListener("click", () => { state.district = card.dataset.district; state.page = "profile"; render(); }));
    }

    function renderTasksPage() {
      let kpiOptions = taskKpiOptionsForModule(state.taskModule);
      if (!kpiOptions.length) {
        state.taskModule = "all";
        kpiOptions = taskKpiOptionsForModule("all");
      }
      if (state.kpi !== "all" && !kpiOptions.some(def => def.id === state.kpi)) state.kpi = "all";
      const allKpis = state.kpi === "all";
      const kpi = currentKpiDef();
      const q = state.search.trim().toLowerCase();
      const selectedModule = moduleById(state.taskModule);
      const moduleLabel = selectedModule ? selectedModule.label : "Барча 7 йўналиш";
      const cleanModuleLabel = moduleLabel.replace(/^\d+\.\s*/, "");
      const moduleScopedTasks = DATA.tasks.filter(t => state.taskModule === "all" || t.module === state.taskModule);
      const allByKpi = tasksForKpi(state.kpi).filter(t => state.taskModule === "all" || t.module === state.taskModule);
      const allTasks = allByKpi.filter(t => {
        const qOk = !q || `${t.title} ${t.sector} ${t.moduleLabel} ${t.owner} ${t.period} ${t.deadline} ${(t.districts || []).join(" ")}`.toLowerCase().includes(q);
        const periodOk = state.taskPeriod === "all" || t.periodCode === state.taskPeriod;
        const districtOk = state.taskDistrict === "all" || (Array.isArray(t.districts) && t.districts.includes(state.taskDistrict));
        return qOk && periodOk && districtOk;
      });
      const notDone = allTasks.filter(t => (t.status || "grey") !== "green");
      const done = allTasks.filter(t => t.status === "green");
      const visibleTasks = state.taskStatus === "all" ? allTasks : state.taskStatus === "done" ? done : notDone;
      const districtReady = districtSelectorDefs().some(def => def.id === kpi.id);
      const kpiDistricts = [...new Set(allByKpi.flatMap(t => Array.isArray(t.districts) ? t.districts : []))].sort((a, b) => a.localeCompare(b, "uz-Cyrl-UZ"));
      const reportLinked = allTasks.filter(t => getExecutionReports().some(report => report.taskId === t.id)).length;
      const donePct = allTasks.length > 0 ? Math.round(done.length / allTasks.length * 100) : 0;
      const taskScopeTitle = allKpis ? `${cleanModuleLabel} топшириқлари` : `${kpi.short}га оид топшириқлар`;
      const shownScope = state.taskStatus === "done" ? "Бажарилган" : state.taskStatus === "open" ? "Бажарилмаган" : "Барчаси";
      const contextBits = [
        cleanModuleLabel,
        state.taskDistrict !== "all" ? state.taskDistrict : "",
        state.taskPeriod !== "all" ? (state.taskPeriod === "h1" ? "II чорак / I ярим йиллик" : "Йил якуни / давомида") : ""
      ].filter(Boolean);
      $("#tasksPage").innerHTML = `
        <div class="task-filter report-filter">
          <label>Йўналиш / жадвал
            <select id="taskModuleSelect">
              <option value="all" ${state.taskModule === "all" ? "selected" : ""}>Барча 7 йўналиш</option>
              ${taskModules().map(module => `<option value="${module.id}" ${state.taskModule === module.id ? "selected" : ""}>${module.label}</option>`).join("")}
            </select>
          </label>
          <label>KPI / топшириқ йўналиши
            <select id="taskKpiSelect">
              <option value="all" ${allKpis ? "selected" : ""}>Барча KPI</option>
              ${kpiOptions.map(def => `<option value="${def.id}" ${!allKpis && def.id === kpi.id ? "selected" : ""}>${def.short} — ${def.label}</option>`).join("")}
            </select>
          </label>
          <label>Ҳолат
            <select id="taskStatusSelect">
              <option value="open" ${state.taskStatus === "open" ? "selected" : ""}>Бажарилмаган</option>
              <option value="all" ${state.taskStatus === "all" ? "selected" : ""}>Барчаси</option>
              <option value="done" ${state.taskStatus === "done" ? "selected" : ""}>Бажарилган</option>
            </select>
          </label>
          <label>Қидириш
            <input id="taskSearchBox" value="${h(state.search)}" placeholder="Топшириқ, масъул ёки ҳудуд">
          </label>
        </div>
        <details class="task-advanced-filters" ${state.taskPeriod !== "all" || state.taskDistrict !== "all" ? "open" : ""}>
          <summary>Қўшимча фильтрлар</summary>
          <div class="task-advanced-grid">
            <label>Муддат
              <select id="taskPeriodSelect">
                <option value="all" ${state.taskPeriod === "all" ? "selected" : ""}>Барча муддатлар</option>
                <option value="h1" ${state.taskPeriod === "h1" ? "selected" : ""}>II чорак / I ярим йиллик</option>
                <option value="year" ${state.taskPeriod === "year" ? "selected" : ""}>Йил якуни / давомида</option>
              </select>
            </label>
            <label>Туман/шаҳар
              <select id="taskDistrictSelect">
                <option value="all" ${state.taskDistrict === "all" ? "selected" : ""}>Барча ҳудудлар</option>
                ${kpiDistricts.map(name => `<option value="${h(name)}" ${state.taskDistrict === name ? "selected" : ""}>${h(name)}</option>`).join("")}
              </select>
            </label>
          </div>
        </details>
        <div class="task-summary-strip execution-overview">
          <div class="task-summary-copy">
            <span>Ижро ҳолати</span>
            <strong>${taskScopeTitle}</strong>
            <small>${contextBits.join(" · ")} бўйича ${shownScope.toLowerCase()} топшириқлар кўрсатилмоқда.</small>
          </div>
          <div class="exec-status-grid">
            <button class="exec-status-pill ${state.taskStatus === "all" ? "active" : ""}" type="button" data-task-status-jump="all">
              <span>Жами</span>
              <strong>${allTasks.length}</strong>
            </button>
            <button class="exec-status-pill green ${state.taskStatus === "done" ? "active" : ""}" type="button" data-task-status-jump="done">
              <span>Бажарилди</span>
              <strong>${done.length}</strong>
            </button>
            <button class="exec-status-pill red ${state.taskStatus === "open" ? "active" : ""}" type="button" data-task-status-jump="open">
              <span>Бажарилмади</span>
              <strong>${notDone.length}</strong>
            </button>
            <button class="exec-status-pill blue" type="button" data-task-execution>
              <span>Ҳисобот киритилган</span>
              <strong>${reportLinked}</strong>
            </button>
          </div>
          <div class="exec-progress-box">
            <div class="exec-donut" style="--pct:${donePct}"><strong>${donePct}%</strong></div>
            <small>бажарилиш</small>
          </div>
          <div class="score-actions">
            <button class="score-action primary" type="button" data-task-page="dashboard">KPI экрани</button>
            <button class="score-action" type="button" data-task-execution>Ижро журнали</button>
          </div>
        </div>
        <div class="task-workspace">
          <div class="task-groups">
            <section class="task-group">
              <div class="task-group-head"><h3>${shownScope} топшириқлар</h3><span class="chip grey">${visibleTasks.length} та</span></div>
              <div class="task-list">${visibleTasks.map(task => compactTaskCard(task, kpi.id)).join("") || `<p class="muted">Бу KPI бўйича топшириқ топилмади.</p>`}</div>
            </section>
          </div>
          <aside class="task-focus">
            <div class="eyebrow">Топшириқлар</div>
            <h3>KPI → топшириқ → ҳисобот</h3>
            <p>Бу экран KPI карточкасида кўринган ижро ҳолатини номма-ном топшириқларга очиб беради. Карточкага босганда тўлиқ матн, далил ва Ҳисоб палатаси текширувини киритиш ойнаси очилади.</p>
            <div class="task-side-stack">
              <div class="task-side-row"><div><strong>Танланган йўналиш</strong><span>${cleanModuleLabel}</span></div><span class="chip blue">${moduleScopedTasks.length} та</span></div>
              <div class="task-side-row"><div><strong>Танланган KPI</strong><span>${allKpis ? "Барча KPI бўйича топшириқлар" : kpi.label}</span></div><span class="chip blue">${allKpis ? "ҳаммаси" : kpi.short}</span></div>
              <div class="task-side-row"><div><strong>Ҳисобот киритилган</strong><span>Киритилган ҳисоботлар ижро журналида текширилади.</span></div><span class="chip ${reportLinked ? "green" : "grey"}">${reportLinked}/${allTasks.length}</span></div>
              ${!allKpis && districtReady ? `<div class="task-side-row"><div><strong>Туманлар кесими</strong><span>Шу KPI бўйича ҳудудлар ҳолатини очиш.</span></div><button class="mini-button primary" data-open-districts="${kpi.id}" type="button">Очиш</button></div>` : ""}
            </div>
          </aside>
        </div>`;
      $("#taskModuleSelect").addEventListener("change", event => {
        state.taskModule = event.target.value;
        state.taskStatus = "open";
        state.taskDistrict = "all";
        const nextOptions = taskKpiOptionsForModule(state.taskModule);
        if (state.kpi !== "all" && !nextOptions.some(def => def.id === state.kpi)) state.kpi = "all";
        render();
      });
      $("#taskKpiSelect").addEventListener("change", event => {
        state.kpi = event.target.value;
        state.taskStatus = "open";
        state.taskDistrict = "all";
        render();
      });
      $("#taskPeriodSelect").addEventListener("change", event => {
        state.taskPeriod = event.target.value;
        render();
      });
      $("#taskDistrictSelect").addEventListener("change", event => {
        state.taskDistrict = event.target.value;
        render();
      });
      $("#taskStatusSelect").addEventListener("change", event => {
        state.taskStatus = event.target.value;
        render();
      });
      $("#taskSearchBox").addEventListener("input", event => {
        state.search = event.target.value;
        render();
      });
      $$("[data-task-status-jump]", $("#tasksPage")).forEach(btn => btn.addEventListener("click", () => {
        state.taskStatus = btn.dataset.taskStatusJump;
        render();
      }));
      $$("[data-task-execution]", $("#tasksPage")).forEach(btn => btn.addEventListener("click", () => {
        openExecutionJournal(allKpis ? "all" : kpi.id, state.taskDistrict === "all" ? null : state.taskDistrict, state.taskPeriod === "all" ? null : state.taskPeriod);
      }));
      $$("[data-task-page]", $("#tasksPage")).forEach(btn => btn.addEventListener("click", () => {
        state.page = btn.dataset.taskPage;
        render();
      }));
      $$("[data-open-districts]", $("#tasksPage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.openDistricts;
        state.page = "districts";
        render();
      }));
    }

    function districtContextDefs() {
      const all = districtSelectorDefs();
      const groups = {
        grp: ["grp", "industry", "agriculture", "services"],
        industry: ["industry", "localization", "energy_electricity", "energy_gas", "investment"],
        agriculture: ["grp", "industry", "agriculture", "services"],
        services: ["grp", "industry", "agriculture", "services"],
        localization: ["industry", "localization", "energy_electricity", "energy_gas"],
        energy_electricity: ["industry", "localization", "energy_electricity", "energy_gas"],
        energy_gas: ["industry", "localization", "energy_electricity", "energy_gas"],
        poverty: ["poverty", "unemployment", "small_business_share", "jobs", "legalization", "mfy_clear", "microprojects"],
        unemployment: ["unemployment", "poverty", "small_business_share", "jobs", "legalization", "mfy_clear", "microprojects"],
        small_business_share: ["small_business_share", "unemployment", "jobs", "legalization"],
        jobs: ["jobs", "unemployment", "poverty", "small_business_share", "legalization", "mfy_clear", "microprojects"],
        legalization: ["legalization", "jobs", "unemployment", "poverty", "small_business_share", "mfy_clear", "microprojects"],
        mfy_clear: ["mfy_clear", "poverty", "unemployment", "jobs", "microprojects"],
        microprojects: ["microprojects", "poverty", "jobs", "legalization"],
        inflation: ["inflation"],
        export: ["export"],
        investment: ["investment"],
        budget: ["budget", "budget_investment"],
        budget_investment: ["budget_investment", "budget"]
      };
      const ids = groups[state.kpi] || [state.kpi];
      const scoped = ids.map(id => all.find(def => def.id === id)).filter(Boolean);
      return scoped.some(def => def.id === state.kpi) ? scoped : [currentKpiDef(), ...scoped].filter(Boolean);
    }

    function taskScopeForKpi(kpiId) {
      const def = districtSelectorDefs().find(item => item.id === kpiId) || currentKpiDef();
      const macroTaskKpis = ["grp", "industry", "agriculture", "construction", "services", "localization", "energy_electricity", "energy_gas"];
      if (kpiId === "inflation") return "Макро иқтисодиёт";
      if (kpiId === "budget_investment") return "Бюджет";
      return macroTaskKpis.includes(kpiId) ? "Макро иқтисодиёт" : def.sector;
    }

    function tasksForKpi(kpiId) {
      if (kpiId === "all") return DATA.tasks.slice();
      if (DATA.tasks.some(t => t.kpi)) return DATA.tasks.filter(t => isSpecificTaskForKpi(t, kpiId));
      const sector = taskScopeForKpi(kpiId);
      return DATA.tasks.filter(t => (sector === "all" || t.sector === sector) && isSpecificTaskForKpi(t, kpiId));
    }

    function districtTargetsForKpi(kpiId) {
      return (DATA.kafolat_district_targets || []).filter(t => isSpecificTaskForKpi(t, kpiId));
    }

    function recordMentionsDistrict(record, d) {
      if (!record || !d) return false;
      const districtStem = d.name.replace(/\s+(шаҳри|тумани)$/i, "").toLowerCase();
      return (Array.isArray(record.districts) && record.districts.includes(d.name))
        || cleanTaskTitle(record.title || "").toLowerCase().includes(districtStem);
    }

    function districtScopedTasks(d, kpiId = null) {
      const records = kpiId ? tasksForKpi(kpiId) : (DATA.tasks || []);
      return records.filter(t => recordMentionsDistrict(t, d));
    }

    function districtScopedTargets(d, kpiId = null) {
      const records = kpiId ? districtTargetsForKpi(kpiId) : (DATA.kafolat_district_targets || []);
      return records.filter(t => recordMentionsDistrict(t, d));
    }

    function districtTargetsForDistrict(d, kpiId = state.kpi) {
      return districtScopedTargets(d, kpiId);
    }

    function districtTasksFor(d, kpiId = state.kpi) {
      return districtScopedTasks(d, kpiId);
    }

    function taskDistrictFilterFor(kpiId, districtName) {
      if (!districtName || districtName === "all") return "all";
      const district = (DATA.districts || []).find(item => item.name === districtName);
      const hasDistrictTasks = tasksForKpi(kpiId).some(t =>
        district ? recordMentionsDistrict(t, district) : (Array.isArray(t.districts) && t.districts.includes(districtName))
      );
      return hasDistrictTasks ? districtName : "all";
    }

    function openTasksForContext(kpiId = state.kpi, districtName = null, period = null, status = "open") {
      const targetKpi = kpiId || "all";
      state.page = "tasks";
      state.kpi = targetKpi;
      state.taskModule = targetKpi === "all" ? "all" : dashboardModuleForKpi(targetKpi);
      state.taskStatus = status || "open";
      state.taskDistrict = taskDistrictFilterFor(targetKpi, districtName || state.district);
      state.taskPeriod = period || "all";
      state.search = "";
      render();
    }

    function districtTaskSummary(d, kpiId = state.kpi) {
      const tasks = districtTasksFor(d, kpiId);
      const unfinished = tasks.filter(t => (t.status || "grey") !== "green").length;
      return { total: tasks.length, unfinished, tasks };
    }

    function districtMeasure(row, kpiId, period = state.period) {
      if (!row) return "—";
      if (["industry", "agriculture", "services", "export"].includes(kpiId) && n(row.growth) !== null) return growthValue(row.growth);
      if (n(row.execution) !== null) return `${fmt(row.execution, 1)}%`;
      if (period === "q1" && factValue(row) !== "—") return factValue(row);
      if (row.main && row.main !== "—") return row.main;
      return planValue(row);
    }

    function districtMeasureNote(row, kpiId) {
      if (!row) return "";
      if (["industry", "agriculture", "services", "export"].includes(kpiId) && n(row.growth) !== null) return planValue(row);
      if (row.note) return row.note;
      const fact = factValue(row);
      const plan = planValue(row);
      if (fact !== "—" && plan !== "—") return `факт ${fact} / режа ${plan}`;
      if (plan !== "—") return `режа ${plan}`;
      return "маълумот кутилмоқда";
    }

    function pathValue(obj, path) {
      return path.split(".").reduce((acc, key) => acc && acc[key] !== undefined ? acc[key] : null, obj);
    }

    function fieldColumn(label, path, unit = "", notePath = null, noteUnit = "") {
      return {
        label,
        value: d => displayValue(pathValue(d, path), unit),
        note: d => notePath ? displayValue(pathValue(d, notePath), noteUnit) : "",
        status: () => "grey"
      };
    }

    function metricColumn(label, kpiId, period, mode = "auto", note = null) {
      return {
        label,
        period,
        value: d => {
          const row = districtKpi(d, kpiId, period);
          if (mode === "growth") return n(row.growth) !== null ? growthValue(row.growth) : "—";
          if (mode === "execution") return n(row.execution) !== null ? `${fmt(row.execution, 1)}%` : "—";
          if (mode === "fact") return factValue(row);
          if (mode === "plan") return planValue(row);
          return districtMeasure(row, kpiId, period);
        },
        note: d => {
          const row = districtKpi(d, kpiId, period);
          if (note) return note(row, d);
          return districtMeasureNote(row, kpiId);
        },
        status: d => rowStatus(districtKpi(d, kpiId, period))
      };
    }

    function districtTableConfig(kpi) {
      const id = kpi.id;
      const growthCols = ["industry", "agriculture", "services"].includes(id)
        ? [
            metricColumn("I чорак амалда", id, "q1", "growth", row => `ҳажм ${factValue(row)}`),
            metricColumn("I ярим йиллик прогноз", id, "h1", "growth", row => `режа ${planValue(row)}`),
            metricColumn("9 ойлик прогноз", id, "m9", "growth", row => `режа ${planValue(row)}`),
            metricColumn("Йиллик прогноз", id, "year", "growth", row => `режа ${planValue(row)}`)
          ]
        : null;
      const configs = {
        grp: {
          title: "ЯҲМ таркиби: туманлар кесими",
          description: "ЯҲМ туман кесимида берилмаган; солиштириш саноат, қишлоқ хўжалиги ва хизматлар ўсиши орқали берилади.",
          source: "1.1-1.5-жадваллар: 1.2, 1.4, 1.5",
          primaryPeriod: state.period,
          columns: [
            metricColumn("Саноат ўсиши", "industry", state.period, "growth", row => `ҳажм ${planValue(row)}`),
            metricColumn("ҚХ ўсиши", "agriculture", state.period, "growth", row => `ҳажм ${planValue(row)}`),
            metricColumn("Хизматлар ўсиши", "services", state.period, "growth", row => `ҳажм ${planValue(row)}`),
            { label: "Изоҳ", value: () => "ЯҲМ туман кесимида йўқ", note: () => "таркибий кўрсаткичлар", status: () => "grey" }
          ]
        },
        industry: { title: "Саноат: туманлар кесими", description: "Туманлар бўйича саноат маҳсулотлари ҳажми ва ўсиш суръати.", source: "1.2-жадвал", primaryPeriod: state.period, columns: growthCols },
        agriculture: { title: "Қишлоқ хўжалиги: туманлар кесими", description: "Туманлар бўйича қишлоқ хўжалиги маҳсулотлари ҳажми ва ўсиш суръати.", source: "1.4-жадвал", primaryPeriod: state.period, columns: growthCols },
        services: { title: "Хизматлар: туманлар кесими", description: "Туманлар бўйича бозор хизматлари ҳажми ва ўсиш суръати.", source: "1.5-жадвал", primaryPeriod: state.period, columns: growthCols },
        localization: {
          title: "Маҳаллийлаштириш дастури: туманлар кесими",
          description: "Бу кўрсаткичда I чорак/9 ойлик йўқ; Excelда I ярим йиллик ва йиллик режа берилган.",
          source: "1.3-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 лойиҳа", "localization", "h1", "plan", row => row.note),
            fieldColumn("H1 қиймат", "data.localization.h1_value_mln", "млн сўм"),
            metricColumn("Йиллик лойиҳа", "localization", "year", "plan", row => row.note),
            fieldColumn("Йиллик қиймат", "data.localization.year_value_mln", "млн сўм")
          ]
        },
        energy_electricity: {
          title: "Электр энергиясини тежаш: туманлар кесими",
          description: "Энергия самарадорлиги бўйича I ярим йиллик ва йиллик мақсадли ҳажмлар.",
          source: "1.3-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 тежаш", "energy_electricity", "h1", "plan"),
            metricColumn("Йиллик тежаш", "energy_electricity", "year", "plan")
          ]
        },
        energy_gas: {
          title: "Табиий газни тежаш: туманлар кесими",
          description: "Газ тежаш бўйича I ярим йиллик ва йиллик мақсадли ҳажмлар.",
          source: "1.3-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 тежаш", "energy_gas", "h1", "plan"),
            metricColumn("Йиллик тежаш", "energy_gas", "year", "plan")
          ]
        },
        inflation: {
          title: "Озиқ-овқат захира инфратузилмаси: туманлар кесими",
          description: "Туманлар бўйича инфляция фоизи берилмаган; шу ерда нарх барқарорлигига хизмат қилувчи омборлар кўрсатилади.",
          source: "2.2-жадвал",
          primaryPeriod: "year",
          columns: [
            fieldColumn("Захира омбори", "data.warehouses.reserve_warehouses", "та", "data.warehouses.reserve_capacity_t", "тонна"),
            fieldColumn("Совутгичли омбор", "data.warehouses.cold_storage_count", "та", "data.warehouses.cold_storage_capacity_t", "тонна"),
            { label: "Янги омбор режаси", value: d => displayValue((n(d.data?.warehouses?.new_small_cold_storage_count) || 0) + (n(d.data?.warehouses?.new_large_cold_storage_count) || 0), "та"), note: () => "100 тоннагача / 100 тоннадан юқори", status: () => "grey" },
            metricColumn("Жами сиғим", "inflation", "year", "plan", row => row.note)
          ]
        },
        budget: {
          title: "Бюджет тушумлари: туманлар кесими",
          description: "II чорак, I ярим йиллик ва йиллик прогноз/кутилиш алоҳида кўрсатилади.",
          source: "3-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("II чорак ижро", "budget", "q2", "execution", row => `факт ${factValue(row)} / режа ${planValue(row)}`),
            metricColumn("I ярим йиллик ижро", "budget", "h1", "execution", row => `факт ${factValue(row)} / режа ${planValue(row)}`),
            metricColumn("Йиллик кутилиш", "budget", "year", "execution", row => `кутилиш ${factValue(row)} / режа ${planValue(row)}`)
          ]
        },
        budget_investment: {
          title: "Бюджет инвестициялари: туманлар кесими",
          description: "Объектлар, лимит ва ўзлаштириш динамикаси алоҳида кўрсатилади.",
          source: "4.1-жадвал",
          primaryPeriod: "h1",
          columns: [
            fieldColumn("Объектлар", "data.budget_investment.objects", "та", "data.budget_investment.limit", "млн сўм"),
            metricColumn("I чорак ўзлаштириш", "budget_investment", "q1", "execution", row => `амалда ${factValue(row)}`),
            metricColumn("H1 ўзлаштириш", "budget_investment", "h1", "execution", row => `амалда ${factValue(row)}`),
            metricColumn("Йиллик ўзлаштириш", "budget_investment", "year", "execution", row => `амалда ${factValue(row)}`)
          ]
        },
        investment: {
          title: "Хорижий инвестициялар: туманлар кесими",
          description: "I чорак факт/режа, I ярим йиллик кутилиш ва йиллик прогноз кесимида.",
          source: "4.2-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("I чорак ижро", "investment", "q1", "execution", row => `факт ${factValue(row)} / режа ${planValue(row)}`),
            metricColumn("H1 ижро", "investment", "h1", "execution", row => `кутилиш ${factValue(row)} / режа ${planValue(row)}`),
            metricColumn("Йиллик ижро", "investment", "year", "execution", row => `кутилиш ${factValue(row)} / прогноз ${planValue(row)}`),
            fieldColumn("H1 лойиҳа / иш ўрни", "data.foreign_investment.h1_projects", "та", "data.foreign_investment.h1_jobs", "та")
          ]
        },
        export: {
          title: "Экспорт: туманлар кесими",
          description: "Экспорт ҳажми, ўсиш суръати ва экспортчи корхоналар сони.",
          source: "5.1-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("I чорак амалда", "export", "q1", "growth", row => `ҳажм ${factValue(row)}`),
            metricColumn("H1 кутилиш", "export", "h1", "growth", row => `ҳажм ${factValue(row)}`),
            metricColumn("Йиллик кутилиш", "export", "year", "growth", row => `ҳажм ${factValue(row)}`),
            fieldColumn("Экспортчилар", "data.export.year_exporters", "та", "data.export.h1_exporters", "та")
          ]
        },
        unemployment: {
          title: "Ишсизлик даражаси: туманлар кесими",
          description: "6-жадвалда I ярим йиллик ва йиллик мақсадли даражалар берилган.",
          source: "6-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 мақсад", "unemployment", "h1", "plan"),
            metricColumn("Йиллик мақсад", "unemployment", "year", "plan"),
            metricColumn("Ишга жойлаштириш H1", "jobs", "h1", "plan"),
            metricColumn("Легаллаштириш H1", "legalization", "h1", "plan")
          ]
        },
        poverty: {
          title: "Камбағаллик даражаси: туманлар кесими",
          description: "Камбағаллик даражаси ва унга боғланган драйверлар бир жадвалда.",
          source: "6-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 камбағаллик", "poverty", "h1", "plan"),
            metricColumn("Йиллик камбағаллик", "poverty", "year", "plan"),
            metricColumn("Камбағалликдан холи МФЙлар H1", "mfy_clear", "h1", "plan"),
            metricColumn("Микролойиҳа H1", "microprojects", "h1", "plan")
          ]
        },
        jobs: {
          title: "Доимий ишга жойлаштириш: туманлар кесими",
          description: "Ишга жойлаштириш мақсадлари I ярим йиллик ва йиллик кесимда.",
          source: "6-жадвал",
          primaryPeriod: "h1",
          columns: [
            metricColumn("H1 ишга жойлаштириш", "jobs", "h1", "plan"),
            metricColumn("Йиллик ишга жойлаштириш", "jobs", "year", "plan"),
            metricColumn("H1 легаллаштириш", "legalization", "h1", "plan"),
            metricColumn("H1 микролойиҳа", "microprojects", "h1", "plan")
          ]
        },
        legalization: { title: "Норасмий бандларни легаллаштириш: туманлар кесими", description: "Легаллаштириш мақсадлари I ярим йиллик ва йиллик кесимда.", source: "6-жадвал", primaryPeriod: "h1", columns: [metricColumn("H1 мақсад", "legalization", "h1", "plan"), metricColumn("Йиллик мақсад", "legalization", "year", "plan"), metricColumn("Ишга жойлаштириш H1", "jobs", "h1", "plan")] },
        mfy_clear: { title: "Холи МФЙлар: туманлар кесими", description: "Камбағаллик ва ишсизликдан холи ҳудудга айлантириладиган МФЙлар.", source: "6-жадвал", primaryPeriod: "h1", columns: [metricColumn("H1 МФЙ", "mfy_clear", "h1", "plan"), metricColumn("Йиллик МФЙ", "mfy_clear", "year", "plan"), metricColumn("Камбағаллик H1", "poverty", "h1", "plan")] },
        microprojects: { title: "Микролойиҳалар: туманлар кесими", description: "Камбағалликни қисқартиришга боғланган микролойиҳалар.", source: "6-жадвал", primaryPeriod: "h1", columns: [metricColumn("H1 микролойиҳа", "microprojects", "h1", "plan"), metricColumn("Йиллик микролойиҳа", "microprojects", "year", "plan"), metricColumn("Ишга жойлаштириш H1", "jobs", "h1", "plan")] }
      };
      return configs[id] || configs.export;
    }

    function districtRowValue(item, kpiId) {
      const row = item.k;
      if (n(row.execution) !== null) return n(row.execution);
      if (n(row.growth) !== null) return n(row.growth);
      if (n(row.plan) !== null) return n(row.plan);
      if (n(row.fact) !== null) return n(row.fact);
      return 0;
    }

    function districtRowsForKpi(kpiId) {
      const cfg = districtTableConfig(districtSelectorDefs().find(def => def.id === kpiId) || currentKpiDef());
      const rows = filteredDistricts().map(d => ({ d, k: districtKpi(d, kpiId, cfg.primaryPeriod || state.period), task: districtTaskSummary(d, kpiId) }));
      return rows.sort((a, b) => {
        if (state.districtSort === "name") return a.d.name.localeCompare(b.d.name, "uz-Cyrl-UZ");
        if (state.districtSort === "tasks") return (b.task.unfinished || 0) - (a.task.unfinished || 0);
        if (state.districtSort === "plan") return (n(b.k.plan) || n(b.k.fact) || 0) - (n(a.k.plan) || n(a.k.fact) || 0);
        if (state.districtSort === "execution") return districtRowValue(b, kpiId) - districtRowValue(a, kpiId);
        const ast = rowStatus(a.k), bst = rowStatus(b.k);
        const order = { red: 0, amber: 1, grey: 2, green: 3 };
        if (order[ast] !== order[bst]) return order[ast] - order[bst];
        return (b.task.unfinished || 0) - (a.task.unfinished || 0);
      });
    }

    function districtCellHtml(d, column) {
      const status = column.status ? column.status(d) : "grey";
      return `<td><strong>${column.value(d)}</strong><small>${column.note ? column.note(d) : ""}</small></td>`;
    }

    function districtTableRow(item, kpi, cfg) {
      const d = item.d;
      const active = d.name === state.district ? "active-row" : "";
      const task = item.task || districtTaskSummary(d, kpi.id);
      const taskClass = task.total && task.unfinished ? "red" : task.total ? "green" : "grey";
      const targets = districtScopedTargets(d, kpi.id);
      const targetClass = targets.length ? "blue" : "grey";
      const report = latestAnyReportFor(kpi.id, cfg.primaryPeriod || state.period, d.name);
      return `<tr class="clickable ${active}" data-select-district="${d.name}">
        <td class="row-title"><strong>${d.name}</strong><span>${d.owner}</span></td>
        ${cfg.columns.map(col => districtCellHtml(d, col)).join("")}
        <td class="num"><span class="chip ${taskClass}">${task.unfinished}/${task.total}</span></td>
        <td class="num"><span class="chip ${targetClass}">${targets.length}</span></td>
        <td>${report ? `<span class="chip ${reportStatusClass(report.status)}">${reportStatusLabel(report.status)}</span><small>${h(reportImpactLabel(report))} · ${h(report.date || "")}</small>` : `<span class="chip grey">ҳисобот йўқ</span><small>амалдаги натижа киритилмаган</small>`}</td>
        <td><div class="action-row compact"><button class="mini-button profile" data-profile-district="${d.name}" title="Туман профили">Профил</button><button class="mini-button" data-open-execution data-exec-kpi="${kpi.id}" data-exec-district="${d.name}" data-exec-period="${cfg.primaryPeriod || state.period}">Журнал</button></div></td>
      </tr>`;
    }

    function renderDistrictTable(rows, kpi, cfg) {
      const head = `<tr><th>Туман/шаҳар</th>${cfg.columns.map(col => `<th>${col.label}</th>`).join("")}<th class="num">T-топшириқ</th><th class="num">D-мақсад</th><th>Ҳисобот / таъсир</th><th>Амал</th></tr>`;
      return `<div class="district-table-wrap"><table class="district-table"><thead>${head}</thead><tbody>${rows.map(row => districtTableRow(row, kpi, cfg)).join("")}</tbody></table></div>`;
    }

    function renderDistrictPreview(d, kpi, cfg) {
      const row = districtKpi(d, kpi.id, cfg.primaryPeriod || state.period);
      const status = rowStatus(row);
      const summary = districtTaskSummary(d, kpi.id);
      const taskClass = summary.total && summary.unfinished ? "red" : summary.total ? "green" : "grey";
      const report = latestAnyReportFor(kpi.id, cfg.primaryPeriod || state.period, d.name);
      const districtTargets = districtTargetsForDistrict(d, kpi.id);
      const targetClass = districtTargets.length ? "blue" : "grey";
      return `<article class="panel district-preview">
        <div class="panel-head">
          <div><h3>${d.name}</h3><p>${kpi.short} бўйича танланган ҳудуднинг қисқа KPI кўриниши.</p></div>
          <div class="district-count-split"><span class="chip ${taskClass}">T: ${summary.unfinished}/${summary.total}</span><span class="chip ${targetClass}">D: ${districtTargets.length}</span></div>
        </div>
        <div class="panel-body">
          <div class="preview-score">
            <div><strong>${districtPrimaryValue(row, kpi.id)}</strong><span>${districtPrimaryLabel(kpi.id)} · танланган KPI</span></div>
            <span class="chip ${status}">${statusLabel(status)}</span>
          </div>
          <div class="preview-metrics">
            ${cfg.columns.map(col => `<div class="preview-metric">
              <div><strong>${col.label}</strong><small>${col.note ? col.note(d) : ""}</small></div>
              <span class="chip ${col.status ? col.status(d) : "grey"}">${col.value(d)}</span>
            </div>`).join("")}
          </div>
          <div class="preview-task-summary">
            <strong>Бажарилмаган топшириқ: ${summary.unfinished}/${summary.total}</strong>
            <span>Танланган KPIга боғланган бажарилмаган топшириқлар сони.</span>
          </div>
          <div class="preview-task-summary">
            <strong>Туман мақсадлари: ${districtTargets.length}</strong>
            <span>${districtTargets.length ? districtTargets.slice(0, 2).map(t => `${t.platformId || t.sourceId}: ${cleanTaskTitle(t.title).slice(0, 80)}`).join(" · ") : "Бу KPI бўйича кафолат хатидан алоҳида туман мақсади ажратилмаган."}</span>
          </div>
          <div class="preview-task-summary">
            <strong>Ҳисобот таъсири: ${report ? reportImpactLabel(report) : "ҳисобот йўқ"}</strong>
            <span>${report ? `${reportStatusLabel(report.status)} · ${h(report.date || "")}` : "Бу KPI ва туман бўйича амалдаги натижа киритилмаган."}</span>
          </div>
          <div class="action-row">
            <button class="mini-button primary" data-profile-district="${d.name}">Туман профили</button>
            <button class="mini-button" data-open-report-modal data-report-kpi="${kpi.id}" data-report-district="${d.name}" data-report-period="${cfg.primaryPeriod || state.period}">Текширув киритиш</button>
            <button class="mini-button" data-open-execution data-exec-kpi="${kpi.id}" data-exec-district="${d.name}" data-exec-period="${cfg.primaryPeriod || state.period}">Ижро журнали</button>
            <button class="mini-button" data-page-jump="tasks">Топшириқларни кўриш</button>
          </div>
        </div>
      </article>`;
    }

    const ANDIJAN_MAP_VIEWBOX = "0 0 600 328";
    const ANDIJAN_DISTRICT_GEOMETRY = [
      { name: "Андижон тумани", short: "Андижон", cx: 331.4, cy: 129.2, path: "M381.7 83.5 L376.7 83.3 L366.1 77.5 L351.4 77.9 L335.6 78.2 L325.7 83.8 L317.5 83.7 L303.7 88.3 L296.8 93.6 L292.4 90.3 L288.7 91.7 L289.5 96.5 L284.6 99.0 L292.7 106.3 L294.5 113.0 L299.3 117.9 L302.7 117.6 L306.4 111.0 L309.0 111.5 L313.4 121.6 L317.2 124.7 L320.0 127.1 L320.6 129.7 L319.9 132.4 L319.0 140.4 L316.2 143.4 L309.0 146.6 L297.6 148.3 L296.9 152.7 L303.6 162.3 L293.4 175.6 L294.0 189.9 L301.6 185.6 L307.7 186.1 L310.9 179.9 L308.7 171.1 L314.7 168.9 L319.0 167.6 L324.5 177.6 L339.5 172.7 L338.9 166.4 L344.4 167.5 L345.4 162.0 L339.3 158.2 L340.2 155.6 L373.0 157.5 L377.5 152.6 L371.8 147.6 L372.5 143.2 L379.1 138.2 L376.9 129.9 L387.9 121.5 L385.8 115.6 L374.7 115.5 L368.8 109.3 L375.3 102.2 L377.7 95.3 L380.3 92.3 L381.7 83.5 Z" },
      { name: "Андижон шаҳри", short: "Андижон ш.", cx: 308.1, cy: 130.0, path: "M297.6 148.3 L309.0 146.6 L316.2 143.4 L319.0 140.4 L319.9 132.4 L320.6 129.7 L320.0 127.1 L317.2 124.7 L313.4 121.6 L309.0 111.5 L306.4 111.0 L302.7 117.6 L299.3 117.9 L299.0 126.4 L296.9 128.9 L293.7 134.9 L297.6 148.3 Z" },
      { name: "Асака тумани", short: "Асака", cx: 272.7, cy: 189.1, path: "M296.9 152.7 L290.3 158.1 L286.0 157.1 L283.4 153.0 L273.3 155.6 L268.5 146.3 L268.9 141.9 L254.2 139.6 L245.5 145.4 L244.4 154.8 L242.1 167.4 L251.0 168.7 L253.0 176.6 L252.1 181.7 L236.7 179.6 L231.8 183.1 L235.6 186.2 L249.4 190.1 L252.8 193.4 L245.5 199.2 L238.1 200.2 L214.7 212.3 L224.7 212.7 L232.0 209.1 L231.2 221.4 L238.2 224.8 L242.8 222.9 L247.3 232.0 L255.1 232.2 L263.2 239.9 L272.6 226.9 L269.8 221.7 L273.7 218.8 L277.6 219.4 L280.6 215.7 L287.5 217.1 L294.5 209.6 L305.4 209.1 L320.7 203.2 L315.2 199.2 L306.1 201.1 L305.2 197.0 L308.4 193.8 L310.9 187.9 L319.0 181.0 L314.7 168.9 L308.7 171.1 L310.9 179.9 L307.7 186.1 L301.6 185.6 L294.0 189.9 L293.4 175.6 L303.6 162.3 L296.9 152.7 Z" },
      { name: "Балиқчи тумани", short: "Балиқчи", cx: 182.8, cy: 104.0, path: "M228.5 90.4 L223.0 86.9 L218.3 93.6 L214.2 86.1 L206.7 84.9 L199.9 89.6 L193.2 86.0 L169.4 83.3 L160.6 75.5 L143.9 72.8 L131.9 75.3 L116.5 78.1 L110.7 84.2 L103.3 81.8 L101.3 91.7 L113.9 102.9 L117.9 103.2 L123.5 107.3 L117.7 113.9 L136.1 132.4 L143.6 132.8 L146.6 139.0 L156.9 135.1 L158.8 133.8 L174.2 123.6 L183.7 122.6 L183.3 119.6 L178.9 116.9 L179.2 113.3 L184.6 111.4 L182.3 104.1 L188.3 101.7 L187.1 95.0 L200.7 107.5 L215.3 108.8 L229.5 120.8 L235.4 120.1 L255.9 119.0 L262.8 113.7 L260.4 108.1 L254.3 110.5 L245.4 105.0 L230.4 109.6 L231.5 96.6 L228.5 90.4 Z" },
      { name: "Булоқбоши тумани", short: "Булоқбоши", cx: 354.5, cy: 205.8, path: "M339.5 172.7 L324.5 177.6 L319.0 167.6 L314.7 168.9 L319.0 181.0 L310.9 187.9 L308.4 193.8 L305.2 197.0 L306.1 201.1 L315.2 199.2 L320.7 203.2 L327.9 204.8 L337.0 210.0 L338.6 215.8 L349.3 224.7 L353.4 232.3 L360.2 241.5 L363.6 244.9 L365.2 245.2 L370.4 243.0 L374.1 244.4 L374.8 245.0 L379.5 233.7 L385.5 232.7 L396.8 236.8 L397.0 231.4 L400.7 230.2 L395.0 226.5 L394.5 224.2 L404.9 213.3 L403.2 210.4 L394.9 211.9 L395.2 200.3 L368.5 191.5 L356.1 188.6 L354.5 183.8 L361.7 177.5 L358.7 175.3 L352.7 180.1 L348.7 178.8 L345.6 182.7 L339.9 184.9 L341.7 174.9 L339.5 172.7 Z" },
      { name: "Бўстон тумани", short: "Бўстон", cx: 162.0, cy: 173.6, path: "M195.9 210.6 L196.6 202.2 L192.9 188.3 L189.1 186.7 L188.9 183.2 L198.2 180.1 L193.8 174.5 L194.8 168.4 L192.0 166.7 L183.4 170.1 L180.8 166.4 L179.4 157.6 L175.5 144.7 L168.8 138.1 L162.0 140.0 L158.8 133.8 L156.9 135.1 L146.6 139.0 L142.2 143.7 L133.6 144.7 L132.6 148.3 L134.3 151.2 L132.9 153.1 L129.1 149.3 L124.9 153.4 L128.3 165.0 L127.9 175.7 L124.9 183.1 L131.9 189.6 L136.0 196.2 L142.3 205.2 L148.4 202.2 L154.9 203.8 L163.5 208.7 L167.5 209.3 L170.8 209.2 L180.5 209.0 L195.9 210.6 Z" },
      { name: "Жалақудуқ тумани", short: "Жалақудуқ", cx: 424.5, cy: 155.9, path: "M468.9 187.0 L459.3 174.4 L447.9 182.4 L445.8 178.8 L439.4 179.1 L422.5 191.1 L411.5 178.7 L415.1 171.1 L431.2 160.5 L446.9 156.6 L441.5 151.6 L450.6 142.6 L448.1 121.3 L461.3 121.4 L461.9 117.5 L457.0 115.8 L465.0 112.0 L463.5 108.6 L454.3 113.5 L442.3 111.6 L429.6 115.1 L416.9 110.9 L405.3 104.0 L397.6 104.0 L377.7 95.3 L375.3 102.2 L368.8 109.3 L374.7 115.5 L385.8 115.6 L387.9 121.5 L376.9 129.9 L379.1 138.2 L372.5 143.2 L371.8 147.6 L377.5 152.6 L373.0 157.5 L377.3 161.1 L371.5 165.5 L377.3 171.9 L389.4 175.8 L406.0 178.1 L409.1 188.8 L416.7 193.3 L430.3 201.8 L443.1 208.7 L459.2 218.3 L458.6 210.0 L459.4 206.8 L465.0 202.2 L464.7 199.6 L461.9 196.1 L460.8 192.8 L461.2 190.4 L463.5 187.7 L468.9 187.0 Z" },
      { name: "Избоскан тумани", short: "Избоскан", cx: 281.9, cy: 73.4, path: "M303.7 88.3 L313.2 79.7 L321.0 78.1 L329.8 70.6 L339.9 62.6 L340.7 53.8 L332.2 50.0 L325.5 52.9 L316.1 60.4 L307.1 61.9 L304.2 55.7 L296.4 50.8 L281.7 44.1 L280.1 35.0 L275.6 37.0 L272.3 39.3 L269.4 40.4 L264.4 40.5 L259.8 37.7 L251.6 53.9 L242.2 53.6 L241.7 66.6 L237.1 72.2 L235.5 77.4 L228.5 90.4 L231.5 96.6 L230.4 109.6 L245.4 105.0 L254.3 110.5 L260.4 108.1 L274.2 103.4 L279.4 111.6 L292.7 106.3 L284.6 99.0 L289.5 96.5 L288.7 91.7 L292.4 90.3 L296.8 93.6 L303.7 88.3 Z" },
      { name: "Марҳамат тумани", short: "Марҳамат", cx: 314.9, cy: 258.3, path: "M338.6 215.8 L337.0 210.0 L327.9 204.8 L320.7 203.2 L305.4 209.1 L294.5 209.6 L287.5 217.1 L280.6 215.7 L277.6 219.4 L273.7 218.8 L269.8 221.7 L272.6 226.9 L263.2 239.9 L262.6 246.5 L252.3 253.5 L259.3 271.5 L261.5 271.0 L269.5 272.9 L278.3 272.2 L281.7 274.3 L282.2 276.2 L279.7 284.7 L279.7 286.0 L281.0 287.7 L283.0 288.6 L289.8 288.5 L292.0 290.1 L295.7 295.5 L298.2 297.1 L304.1 294.8 L306.3 295.5 L311.2 299.0 L320.6 308.1 L322.9 311.1 L326.3 313.2 L329.0 314.4 L333.4 314.8 L337.4 316.1 L339.6 315.6 L340.9 314.4 L341.4 309.6 L339.3 303.2 L342.8 293.6 L347.9 283.0 L346.1 277.3 L343.5 275.3 L342.4 273.4 L341.3 269.5 L340.7 262.2 L338.7 259.5 L336.7 259.3 L334.3 260.3 L330.6 260.3 L329.6 256.8 L330.8 253.0 L332.8 251.6 L338.6 251.6 L340.6 250.8 L343.2 247.8 L343.8 246.0 L343.5 243.6 L342.1 240.9 L340.5 239.4 L332.6 236.9 L331.4 235.5 L329.1 229.2 L326.7 226.9 L318.3 224.0 L317.2 221.8 L318.3 219.3 L320.2 217.7 L323.0 218.2 L328.4 217.7 L330.6 216.1 L335.8 214.5 L338.6 215.8 Z" },
      { name: "Олтинкўл тумани", short: "Олтинкўл", cx: 239.0, cy: 126.1, path: "M260.4 108.1 L262.8 113.7 L255.9 119.0 L235.4 120.1 L229.5 120.8 L215.3 108.8 L200.7 107.5 L187.1 95.0 L188.3 101.7 L182.3 104.1 L184.6 111.4 L179.2 113.3 L178.9 116.9 L183.3 119.6 L183.7 122.6 L185.7 122.8 L191.7 121.6 L198.9 126.1 L200.1 122.3 L203.1 120.5 L208.2 122.7 L205.0 129.3 L211.5 132.8 L211.6 145.3 L221.7 154.2 L235.7 143.9 L245.5 145.4 L254.2 139.6 L268.9 141.9 L268.5 146.3 L273.3 155.6 L283.4 153.0 L286.0 157.1 L290.3 158.1 L296.9 152.7 L297.6 148.3 L293.7 134.9 L296.9 128.9 L299.0 126.4 L299.3 117.9 L294.5 113.0 L292.7 106.3 L279.4 111.6 L274.2 103.4 L260.4 108.1 Z" },
      { name: "Пахтаобод тумани", short: "Пахтаобод", cx: 342.7, cy: 49.0, path: "M393.8 82.7 L393.9 79.1 L393.1 76.8 L388.8 71.6 L387.8 69.2 L382.3 61.4 L378.8 54.6 L375.7 51.7 L372.2 49.9 L370.3 50.1 L368.6 50.5 L365.2 55.9 L364.1 56.1 L362.7 54.4 L362.6 52.7 L368.8 41.3 L369.6 36.5 L368.7 30.0 L367.6 27.6 L362.6 25.3 L356.8 24.0 L350.3 24.6 L345.3 22.8 L344.3 21.7 L341.2 21.1 L337.7 21.3 L336.1 20.7 L330.4 16.2 L322.1 13.5 L320.4 13.6 L319.4 12.2 L316.9 12.0 L314.1 12.7 L311.2 15.3 L305.6 22.0 L296.5 28.9 L290.8 30.3 L285.9 32.5 L280.1 35.0 L281.7 44.1 L296.4 50.8 L304.2 55.7 L307.1 61.9 L316.1 60.4 L325.5 52.9 L332.2 50.0 L340.7 53.8 L339.9 62.6 L329.8 70.6 L321.0 78.1 L313.2 79.7 L303.7 88.3 L317.5 83.7 L325.7 83.8 L335.6 78.2 L351.4 77.9 L366.1 77.5 L376.7 83.3 L381.7 83.5 L393.8 82.7 Z" },
      { name: "Улуғнор тумани", short: "Улуғнор", cx: 84.9, cy: 147.4, path: "M146.6 139.0 L143.6 132.8 L136.1 132.4 L117.7 113.9 L123.5 107.3 L117.9 103.2 L113.9 102.9 L101.3 91.7 L103.3 81.8 L99.6 82.6 L94.9 89.4 L82.6 87.8 L78.2 90.2 L74.3 97.0 L65.2 106.4 L53.8 111.6 L42.7 119.6 L35.4 125.7 L34.1 132.5 L43.2 147.4 L37.2 152.6 L22.9 158.4 L12.0 163.4 L16.4 165.7 L21.8 165.7 L28.9 171.5 L34.3 180.8 L40.7 192.3 L44.8 199.3 L46.8 207.0 L55.0 201.7 L61.4 194.8 L68.3 188.4 L71.9 185.0 L63.4 175.4 L62.9 166.2 L64.4 159.8 L70.7 153.9 L77.0 161.4 L84.1 167.0 L91.7 169.6 L99.5 172.2 L108.8 174.0 L116.3 177.9 L124.9 183.1 L127.9 175.7 L128.3 165.0 L124.9 153.4 L129.1 149.3 L132.9 153.1 L134.3 151.2 L132.6 148.3 L133.6 144.7 L142.2 143.7 L146.6 139.0 Z" },
      { name: "Хонобод шаҳри", short: "Хонобод ш.", cx: 537.1, cy: 122.4, path: "M539.4 122.8 L537.8 120.3 L535.1 120.8 L534.4 123.1 L536.2 124.9 L539.4 122.8 Z" },
      { name: "Хўжаобод тумани", short: "Хўжаобод", cx: 395.1, cy: 209.6, path: "M373.0 157.5 L340.2 155.6 L339.3 158.2 L345.4 162.0 L344.4 167.5 L338.9 166.4 L339.5 172.7 L341.7 174.9 L339.9 184.9 L345.6 182.7 L348.7 178.8 L352.7 180.1 L358.7 175.3 L361.7 177.5 L354.5 183.8 L356.1 188.6 L368.5 191.5 L395.2 200.3 L394.9 211.9 L403.2 210.4 L404.9 213.3 L394.5 224.2 L395.0 226.5 L400.7 230.2 L397.0 231.4 L396.8 236.8 L385.5 232.7 L379.5 233.7 L374.8 245.0 L381.0 251.0 L386.2 253.8 L393.0 253.5 L397.2 254.3 L407.7 262.8 L410.8 263.1 L415.9 261.9 L420.2 253.8 L421.8 243.2 L422.9 241.4 L429.4 236.7 L429.7 230.9 L432.2 228.1 L443.5 230.7 L446.7 233.1 L450.7 238.1 L454.6 240.4 L457.7 240.0 L459.4 238.7 L459.9 230.7 L459.4 222.1 L459.2 218.3 L443.1 208.7 L430.3 201.8 L416.7 193.3 L409.1 188.8 L406.0 178.1 L389.4 175.8 L377.3 171.9 L371.5 165.5 L377.3 161.1 L373.0 157.5 Z" },
      { name: "Шаҳрихон тумани", short: "Шаҳрихон", cx: 209.5, cy: 161.0, path: "M245.5 145.4 L235.7 143.9 L221.7 154.2 L211.6 145.3 L211.5 132.8 L205.0 129.3 L208.2 122.7 L203.1 120.5 L200.1 122.3 L198.9 126.1 L191.7 121.6 L185.7 122.8 L183.7 122.6 L174.2 123.6 L158.8 133.8 L162.0 140.0 L168.8 138.1 L175.5 144.7 L179.4 157.6 L180.8 166.4 L183.4 170.1 L192.0 166.7 L194.8 168.4 L193.8 174.5 L198.2 180.1 L188.9 183.2 L189.1 186.7 L192.9 188.3 L196.6 202.2 L195.9 210.6 L214.7 212.3 L238.1 200.2 L245.5 199.2 L252.8 193.4 L249.4 190.1 L235.6 186.2 L231.8 183.1 L236.7 179.6 L252.1 181.7 L253.0 176.6 L251.0 168.7 L242.1 167.4 L244.4 154.8 L245.5 145.4 Z" },
      { name: "Қўрғонтепа тумани", short: "Қўрғонтепа", cx: 491.6, cy: 128.4, path: "M468.9 187.0 L469.4 186.9 L472.4 187.9 L477.9 188.0 L483.2 186.8 L489.5 184.3 L495.4 179.2 L497.0 177.0 L503.3 174.0 L507.1 171.4 L513.6 169.9 L514.0 168.2 L516.9 168.0 L520.1 166.9 L530.6 161.0 L539.3 154.9 L544.1 148.4 L556.5 146.6 L562.6 143.9 L565.9 143.2 L575.0 138.3 L578.1 137.5 L581.5 135.1 L584.8 130.5 L586.4 126.1 L588.0 117.5 L587.6 113.4 L586.3 110.2 L583.0 107.3 L580.1 106.5 L571.7 106.8 L566.3 110.1 L564.6 110.7 L562.5 109.9 L556.1 104.9 L551.8 104.5 L549.4 103.4 L542.6 94.3 L541.8 92.4 L538.5 92.4 L536.8 94.1 L533.6 104.8 L536.2 108.5 L540.6 111.5 L541.4 112.6 L541.7 115.5 L539.1 117.1 L534.7 117.3 L531.7 120.9 L526.9 123.4 L525.5 125.6 L520.8 126.8 L520.6 127.6 L516.8 127.9 L511.8 126.6 L510.5 125.5 L507.7 117.6 L501.2 111.2 L496.3 105.1 L493.3 102.9 L490.1 101.7 L484.7 101.5 L468.1 105.7 L463.0 104.1 L456.0 105.6 L449.0 108.6 L440.5 106.6 L435.4 106.4 L431.2 107.9 L419.4 103.5 L410.7 99.8 L409.4 98.7 L400.4 96.0 L396.8 94.0 L394.6 91.9 L393.7 86.1 L393.8 82.7 L381.7 83.5 L380.3 92.3 L377.7 95.3 L397.6 104.0 L405.3 104.0 L416.9 110.9 L429.6 115.1 L442.3 111.6 L454.3 113.5 L463.5 108.6 L465.0 112.0 L457.0 115.8 L461.9 117.5 L461.3 121.4 L448.1 121.3 L450.6 142.6 L441.5 151.6 L446.9 156.6 L431.2 160.5 L415.1 171.1 L411.5 178.7 L422.5 191.1 L439.4 179.1 L445.8 178.8 L447.9 182.4 L459.3 174.4 L468.9 187.0 Z M539.4 122.8 L536.2 124.9 L534.4 123.1 L535.1 120.8 L537.8 120.3 L539.4 122.8 Z" },
    ];

    function renderAndijanHexMap(kpi, statusByDistrict, selectedDistrict) {
      const cellShapes = ANDIJAN_DISTRICT_GEOMETRY.map(cell => {
        const info = statusByDistrict.get(cell.name) || { status: "grey", row: null };
        const selected = cell.name === selectedDistrict?.name ? "selected" : "";
        const isCity = cell.name === "Андижон шаҳри" || cell.name === "Хонобод шаҳри";
        const valLabel = info.row ? districtPrimaryValue(info.row, kpi.id) : "—";
        return `<g class="map-cell ${info.status} ${selected} ${isCity ? "is-city" : ""}" data-select-district="${cell.name}" tabindex="0">
          <title>${cell.name} · ${valLabel}</title>
          <path class="map-fill" d="${cell.path}"/>
        </g>`;
      }).join("");
      const cellLabels = ANDIJAN_DISTRICT_GEOMETRY.map(cell => {
        const isCity = cell.name === "Андижон шаҳри" || cell.name === "Хонобод шаҳри";
        const selected = cell.name === selectedDistrict?.name ? "selected" : "";
        return `<text class="map-label ${isCity ? "is-city" : ""} ${selected}" data-label-district="${cell.name}" x="${cell.cx}" y="${cell.cy + 1}" text-anchor="middle" dominant-baseline="central">${cell.short}</text>`;
      }).join("");
      return `<section class="districts-map">
        <header class="districts-map-head">
          <div>
            <strong>${kpi.short} — ${kpi.label}</strong>
            <span>Ранглар танланган KPI ҳолатини кўрсатади.</span>
          </div>
        </header>
        <div class="districts-map-canvas">
          <svg viewBox="${ANDIJAN_MAP_VIEWBOX}" class="andijan-map" role="img" aria-label="Андижон вилоятининг ҳудудлар харитаси">
            <defs>
              <linearGradient id="mapGradGreen" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#d6ecdb"/>
                <stop offset="100%" stop-color="#8fc69f"/>
              </linearGradient>
              <linearGradient id="mapGradAmber" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#fbe9b6"/>
                <stop offset="100%" stop-color="#e3b766"/>
              </linearGradient>
              <linearGradient id="mapGradRed" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#f5cfcf"/>
                <stop offset="100%" stop-color="#d68585"/>
              </linearGradient>
              <linearGradient id="mapGradGrey" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#e8e6dd"/>
                <stop offset="100%" stop-color="#bcb9ac"/>
              </linearGradient>
              <filter id="mapCellShadow" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#1b4d5a" flood-opacity="0.18"/>
              </filter>
            </defs>
            <g>${cellShapes}</g>
            <g class="map-labels">${cellLabels}</g>
          </svg>
        </div>
        <div class="districts-map-legend">
          <span class="legend-chip green">Яхши</span>
          <span class="legend-chip amber">Ўртача</span>
          <span class="legend-chip red">Эътибор</span>
          <span class="legend-chip grey">Маълумот йўқ</span>
        </div>
      </section>`;
    }

    function renderSelectedDistrictSummaryCard(d, kpi, cfg, period) {
      if (!d) return `<section class="district-summary-card empty">
        <div class="district-summary-head">
          <div>
            <span>Танланган ҳудуд</span>
            <h3>Туман танланмаган</h3>
          </div>
        </div>
        <p class="muted">Харита ёки рейтингдан туман/шаҳарни танланг.</p>
      </section>`;
      const row = districtKpi(d, kpi.id, period);
      const status = rowStatus(row);
      const taskSummary = districtTaskSummary(d, kpi.id);
      const scopedTargets = districtScopedTargets(d, kpi.id);
      const taskClass = taskSummary.total && taskSummary.unfinished ? "red" : taskSummary.total ? "green" : "grey";
      const targetClass = scopedTargets.length ? "blue" : "grey";
      const report = latestAnyReportFor(kpi.id, period, d.name);
      return `<section class="district-summary-card">
        <header class="district-summary-head">
          <div>
            <span>Танланган ҳудуд</span>
            <h3>${d.name}</h3>
          </div>
          <span class="chip ${status}">${statusLabel(status)}</span>
        </header>
        <div class="district-summary-value">
          <div>
            <strong>${districtPrimaryValue(row, kpi.id)}</strong>
            <span>${districtPrimaryLabel(kpi.id)} · ${kpi.short}</span>
          </div>
          <div class="district-count-split">
            <span class="chip ${taskClass}">T: ${taskSummary.unfinished}/${taskSummary.total}</span>
            <span class="chip ${targetClass}">D: ${scopedTargets.length}</span>
          </div>
        </div>
        <div class="district-summary-metrics">
          ${cfg.columns.slice(0, 4).map(col => `<div class="district-summary-metric">
            <span>${col.label}</span>
            <strong>${col.value(d)}</strong>
            <small>${col.note ? col.note(d) : ""}</small>
          </div>`).join("")}
        </div>
        <div class="district-summary-actions">
          <button class="mini-button primary" data-profile-district="${d.name}">Туман профили</button>
          <button class="mini-button" data-open-report-modal data-report-kpi="${kpi.id}" data-report-district="${d.name}" data-report-period="${period}">Текширув киритиш</button>
          <button class="mini-button" data-open-execution data-exec-kpi="${kpi.id}" data-exec-district="${d.name}" data-exec-period="${period}">Ижро журнали</button>
          <button class="mini-button" data-page-jump="tasks">Топшириқлар</button>
        </div>
        ${report ? `<span class="chip ${reportStatusClass(report.status)}">${reportStatusLabel(report.status)} · ${h(reportImpactLabel(report))}</span>` : `<span class="chip grey">ҳисобот йўқ</span>`}
      </section>`;
    }

    function renderDistrictLeaderboard(ranked, kpi, period, selected, maxAbs) {
      const withData = ranked.filter(item => hasPeriodValue(districtKpi(item.d, kpi.id, period)));
      return `<section class="districts-leaderboard">
        <header class="districts-lb-head">
          <strong>Туманлар</strong>
          <span>${withData.length} та туманлар · ${kpi.short}</span>
        </header>
        <ol class="districts-lb-list">
          ${withData.length ? withData.map((item, idx) => {
            const d = item.d;
            const row = districtKpi(d, kpi.id, period);
            const status = rowStatus(row);
            const value = districtPrimaryValue(row, kpi.id);
            const num = districtRowValue(item, kpi.id);
            const barPct = num !== null && maxAbs ? Math.min(100, (Math.abs(num) / maxAbs) * 100) : 0;
            const isSelected = d.name === selected?.name;
            return `<li class="lb-row ${status} ${isSelected ? "selected" : ""}" data-select-district="${d.name}" tabindex="0">
              <span class="lb-rank">${idx + 1}</span>
              <span class="lb-name">${d.name}</span>
              <span class="lb-value">${value}</span>
              <span class="lb-bar"><i style="width:${barPct.toFixed(1)}%"></i></span>
            </li>`;
          }).join("") : `<li class="lb-empty">Қидирув бўйича туман топилмади. Қидирувни тозаланг ёки бошқа KPI танланг.</li>`}
        </ol>
      </section>`;
    }

    function renderDistrictCrossKpi(d) {
      if (!d) return "";
      const tiles = [
        { id: "industry",    label: "Саноат",          period: "h1",   formatter: r => growthValue(r.growth) },
        { id: "agriculture", label: "Қишлоқ хўжалиги", period: "h1",   formatter: r => growthValue(r.growth) },
        { id: "services",    label: "Хизматлар",       period: "h1",   formatter: r => growthValue(r.growth) },
        { id: "export",      label: "Экспорт",         period: "h1",   formatter: r => growthValue(r.growth) },
        { id: "budget",      label: "Бюджет",          period: "year", formatter: r => n(r.execution) !== null ? `${fmt(r.execution, 1)}%` : displayValue(r.fact ?? r.plan, r.unit) },
        { id: "jobs",        label: "Бандлик",         period: "year", formatter: r => displayValue(r.fact ?? r.plan, r.unit || "") }
      ];
      return `<section class="dpc-cross">
        <header class="dpc-cross-head">
          <strong>Туман KPI кесими</strong>
          <span>Бошқа йўналишлар бўйича қисқача кўриниш</span>
        </header>
        <div class="dpc-cross-grid">
          ${tiles.map(tile => {
            const row = districtKpi(d, tile.id, tile.period);
            const value = row && hasPeriodValue(row) ? tile.formatter(row) : "—";
            const status = row && hasPeriodValue(row) ? rowStatus(row) : "grey";
            return `<button class="dpc-cross-tile ${status}" type="button" data-cross-kpi="${tile.id}" title="${tile.label} — ${tile.id} KPIга ўтиш">
              <span class="dpc-cross-label">${tile.label}</span>
              <strong class="dpc-cross-value">${value}</strong>
            </button>`;
          }).join("")}
        </div>
      </section>`;
    }

    function renderDistrictTasksPanels(d, kpiId = state.kpi) {
      if (!d) return "";
      const tasks = districtScopedTasks(d, kpiId);
      const targets = districtScopedTargets(d, kpiId);
      const sortByStatus = arr => [...arr].sort((a, b) => {
        const order = { red: 0, amber: 1, grey: 2, green: 3 };
        return (order[a.status || "grey"] ?? 4) - (order[b.status || "grey"] ?? 4);
      });
      const taskItems = sortByStatus(tasks).slice(0, 12).map(t => {
        const status = t.status || "grey";
        const id = t.platformId || t.id || "T-—";
        const title = cleanTaskTitle(t.title || "");
        const period = t.deadline || t.period || "";
        return `<li class="dpc-task-item ${status}">
          <span class="dpc-task-id">${id}</span>
          <span class="dpc-task-body">
            <strong>${title}</strong>
            ${period ? `<small>${period}</small>` : ""}
          </span>
          <span class="chip ${status}">${statusLabel(status)}</span>
        </li>`;
      }).join("");
      const targetItems = sortByStatus(targets).slice(0, 12).map(t => {
        const status = t.status || "grey";
        const id = t.platformId || t.sourceId || "D-—";
        const title = cleanTaskTitle(t.title || "");
        const kpiTag = t.kpi || "—";
        return `<li class="dpc-task-item ${status}">
          <span class="dpc-task-id">${id}</span>
          <span class="dpc-task-body">
            <strong>${title}</strong>
            <small>KPI: ${kpiTag}${t.deadline ? ` · ${t.deadline}` : ""}</small>
          </span>
        </li>`;
      }).join("");
      return `<section class="dpc-tasks-grid">
        <div class="dpc-task-panel">
          <header class="dpc-task-head">
            <strong>T-топшириқлар</strong>
            <span class="chip ${tasks.length ? "blue" : "grey"}">${tasks.length} та</span>
          </header>
          ${tasks.length ? `<ul class="dpc-task-list">${taskItems}</ul>${tasks.length > 12 ? `<p class="dpc-task-more">…ва яна ${tasks.length - 12} та</p>` : ""}` : `<p class="dpc-task-empty">Танланган KPI бўйича бу ҳудудга бириктирилган T-топшириқ йўқ.</p>`}
        </div>
        <div class="dpc-task-panel">
          <header class="dpc-task-head">
            <strong>D-мақсадлар</strong>
            <span class="chip ${targets.length ? "blue" : "grey"}">${targets.length} та</span>
          </header>
          ${targets.length ? `<ul class="dpc-task-list">${targetItems}</ul>${targets.length > 12 ? `<p class="dpc-task-more">…ва яна ${targets.length - 12} та</p>` : ""}` : `<p class="dpc-task-empty">Танланган KPI бўйича кафолат хатида D-мақсад ажратилмаган.</p>`}
        </div>
      </section>`;
    }

    function renderSelectedDistrictCard(d, kpi, cfg, period) {
      if (!d) return "";
      const row = districtKpi(d, kpi.id, period);
      const status = rowStatus(row);
      const scopedTasks = districtScopedTasks(d, kpi.id);
      const scopedTargets = districtScopedTargets(d, kpi.id);
      const unfinishedTasks = scopedTasks.filter(t => (t.status || "grey") !== "green").length;
      const taskClass = scopedTasks.length && unfinishedTasks ? "red" : scopedTasks.length ? "green" : "grey";
      const targetClass = scopedTargets.length ? "blue" : "grey";
      const report = latestAnyReportFor(kpi.id, period, d.name);
      return `<article class="district-profile-card">
        <header class="dpc-head">
          <div class="dpc-head-titles">
            <span class="eyebrow">Танланган ҳудуд</span>
            <h3>${d.name}</h3>
            <p>${d.owner ? `Маъсул: ${d.owner}` : ""}</p>
          </div>
          <div class="dpc-head-stat">
            <strong>${districtPrimaryValue(row, kpi.id)}</strong>
            <span>${districtPrimaryLabel(kpi.id)} · ${kpi.short}</span>
          </div>
          <div class="dpc-head-chips">
            <span class="chip ${status}">${statusLabel(status)}</span>
            <span class="chip ${taskClass}">T: ${unfinishedTasks}/${scopedTasks.length}</span>
            <span class="chip ${targetClass}">D: ${scopedTargets.length}</span>
          </div>
        </header>
        <div class="dpc-metrics">
          ${cfg.columns.map(col => `<div class="dpc-metric">
            <span class="dpc-metric-label">${col.label}</span>
            <strong class="dpc-metric-value">${col.value(d)}</strong>
            <small class="dpc-metric-note">${col.note ? col.note(d) : ""}</small>
          </div>`).join("")}
        </div>
        ${renderDistrictCrossKpi(d)}
        ${renderDistrictTasksPanels(d, kpi.id)}
        <footer class="dpc-actions">
          <button class="mini-button primary" data-profile-district="${d.name}">Туман профили →</button>
          <button class="mini-button" data-open-report-modal data-report-kpi="${kpi.id}" data-report-district="${d.name}" data-report-period="${period}">Текширув киритиш</button>
          <button class="mini-button" data-open-execution data-exec-kpi="${kpi.id}" data-exec-district="${d.name}" data-exec-period="${period}">Ижро журнали</button>
          <button class="mini-button" data-page-jump="tasks">Топшириқларни кўриш</button>
          ${report ? `<span class="chip ${reportStatusClass(report.status)}" style="margin-left:auto">${reportStatusLabel(report.status)} · ${h(report.date || "")}</span>` : ""}
        </footer>
      </article>`;
    }

    function kpiHasAnyDistrictData(kpiId) {
      const cfg = districtTableConfig(districtSelectorDefs().find(def => def.id === kpiId) || currentKpiDef());
      const period = cfg.primaryPeriod || state.period;
      return (DATA.districts || []).some(d => hasPeriodValue(districtKpi(d, kpiId, period)));
    }

    function mapColorValue(row) {
      if (n(row.execution) !== null) return n(row.execution);
      if (n(row.growth) !== null) return n(row.growth);
      if (n(row.fact) !== null) return n(row.fact);
      if (n(row.plan) !== null) return n(row.plan);
      return null;
    }

    function buildMapStatusByDistrict(kpiId, period) {
      const lowerBetter = ["inflation", "poverty", "unemployment"].includes(kpiId);
      const items = (DATA.districts || []).map(d => {
        const row = districtKpi(d, kpiId, period);
        return { name: d.name, row, value: mapColorValue(row) };
      });
      const valued = items.filter(it => it.value !== null);
      const sorted = [...valued].sort((a, b) => lowerBetter ? a.value - b.value : b.value - a.value);
      const total = sorted.length;
      const greenCut = Math.max(1, Math.ceil(total / 3));
      const amberCut = Math.max(greenCut + 1, Math.ceil(total * 2 / 3));
      const tierByName = new Map();
      sorted.forEach((it, idx) => {
        let status = "red";
        if (idx < greenCut) status = "green";
        else if (idx < amberCut) status = "amber";
        tierByName.set(it.name, status);
      });
      const out = new Map();
      items.forEach(it => {
        const status = it.value === null ? "grey" : (tierByName.get(it.name) || "grey");
        out.set(it.name, { row: it.row, status });
      });
      return out;
    }

    function districtSelectorDefsWithData() {
      return districtSelectorDefs().filter(def => kpiHasAnyDistrictData(def.id));
    }

    const districtKpiModuleMap = {
      macro: ["grp", "industry", "agriculture", "services", "localization", "energy_electricity", "energy_gas"],
      inflation: ["inflation"],
      budget: ["budget"],
      budget_investment: ["budget_investment"],
      investment: ["investment"],
      export: ["export"],
      employment: ["unemployment", "poverty", "jobs", "legalization", "mfy_clear", "microprojects"]
    };

    function districtKpisForModule(moduleId) {
      const available = districtSelectorDefsWithData();
      const ids = districtKpiModuleMap[moduleId] || districtKpiModuleMap.macro;
      return ids.map(id => available.find(def => def.id === id)).filter(Boolean);
    }

    function districtModulesWithData() {
      return dashboardModules().filter(module => districtKpisForModule(module.id).length);
    }

    function districtKpiOption(def, cfg) {
      const active = def.id === state.kpi ? "active" : "";
      return `<button class="district-kpi-option ${active}" type="button" data-district-kpi="${def.id}" aria-label="${def.label}">
        <span class="kpi-mini-icon" aria-hidden="true">${icon(def.icon)}</span>
        <span>
          <strong>${def.short}</strong>
          <small>${cfg?.source || def.label}</small>
        </span>
      </button>`;
    }

    function renderDistrictsPage() {
      const availableDefs = districtSelectorDefsWithData();
      if (!availableDefs.some(def => def.id === state.kpi)) state.kpi = availableDefs[0]?.id || "grp";
      let activeModule = dashboardModuleForKpi(state.kpi);
      let moduleDefs = districtKpisForModule(activeModule);
      if (!moduleDefs.some(def => def.id === state.kpi)) {
        state.kpi = moduleDefs[0]?.id || availableDefs[0]?.id || "grp";
        activeModule = dashboardModuleForKpi(state.kpi);
        moduleDefs = districtKpisForModule(activeModule);
      }
      const kpi = currentKpiDef();
      const cfg = districtTableConfig(kpi);
      const period = cfg.primaryPeriod || state.period;
      const selectedModule = moduleById(activeModule) || dashboardModules()[0];
      const ranked = districtRowsForKpi(kpi.id);
      const query = String(state.search || "").trim().toLowerCase();
      const filteredRanked = query
        ? ranked.filter(item => item.d.name.toLowerCase().includes(query) || String(item.d.owner || "").toLowerCase().includes(query))
        : ranked;
      const visibleRanked = filteredRanked;
      const districts = visibleRanked.map(x => x.d);
      let selectedDistrict = state.district ? districts.find(d => d.name === state.district) : null;
      if (!selectedDistrict) {
        selectedDistrict = districts[0] || null;
        if (selectedDistrict) state.district = selectedDistrict.name;
      }
      const statusByDistrict = buildMapStatusByDistrict(kpi.id, period);
      const values = visibleRanked.map(r => districtRowValue(r, kpi.id)).filter(v => v !== null);
      const maxAbs = values.length ? Math.max(...values.map(Math.abs)) : 1;
      $("#districtsPage").innerHTML = `
        <header class="districts-head">
          <div class="dashboard-module-tabs district-module-tabs">
            ${districtModulesWithData().map(module => `<button class="module-tab ${module.id === activeModule ? "active" : ""}" data-district-module="${module.id}" type="button">
              <span class="module-dot" aria-hidden="true"></span>
              <strong>${module.label.replace(/^\d+\.\s*/, "")}</strong>
            </button>`).join("")}
          </div>
          <div class="module-heading">
            <div>
              <h2>${selectedModule?.label || "Туманлар мониторинги"}</h2>
              <p>${cfg.description}</p>
            </div>
          </div>
          ${moduleDefs.length > 1 ? `<div class="district-kpi-selector">${moduleDefs.map(def => districtKpiOption(def, districtTableConfig(def))).join("")}</div>` : ""}
          ${renderDistrictDataLayers(kpi, period)}
          <div class="districts-head-actions">
            <label class="districts-control">
              <span>Саралаш</span>
              <select id="districtSortSelect">
                <option value="attention" ${state.districtSort === "attention" ? "selected" : ""}>Эътибор талаб</option>
                <option value="execution" ${state.districtSort === "execution" ? "selected" : ""}>Юқоридан</option>
                <option value="plan" ${state.districtSort === "plan" ? "selected" : ""}>Режа каттадан</option>
                <option value="tasks" ${state.districtSort === "tasks" ? "selected" : ""}>Топшириқлар</option>
                <option value="name" ${state.districtSort === "name" ? "selected" : ""}>Алифбо бўйича</option>
              </select>
            </label>
            <label class="districts-control districts-control--search">
              <span>Қидириш</span>
              <input id="districtSearchBox" value="${state.search}" placeholder="Туман қидириш">
            </label>
          </div>
        </header>
        <div class="districts-grid">
          ${renderAndijanHexMap(kpi, statusByDistrict, selectedDistrict)}
          <aside class="districts-side">
            ${renderSelectedDistrictSummaryCard(selectedDistrict, kpi, cfg, period)}
            ${renderDistrictLeaderboard(visibleRanked, kpi, period, selectedDistrict, maxAbs)}
          </aside>
        </div>
        <section class="panel district-detail-table">
          <div class="panel-head">
            <div><h3>Батафсил жадвал</h3><p>${cfg.title}. ${cfg.description}</p></div>
            <span class="chip grey">${cfg.source}</span>
          </div>
          ${renderDistrictTable(visibleRanked, kpi, cfg)}
        </section>`;
      $$("[data-district-module]", $("#districtsPage")).forEach(btn => btn.addEventListener("click", () => {
        const defs = districtKpisForModule(btn.dataset.districtModule);
        state.kpi = defs[0]?.id || state.kpi;
        state.dashboardModule = btn.dataset.districtModule;
        state.search = "";
        render();
      }));
      $$("[data-district-kpi]", $("#districtsPage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.districtKpi;
        state.dashboardModule = dashboardModuleForKpi(state.kpi);
        state.search = "";
        render();
      }));
      $("#districtSortSelect").addEventListener("change", event => {
        state.districtSort = event.target.value;
        render();
      });
      $("#districtSearchBox").addEventListener("input", event => {
        state.search = event.target.value;
        render();
      });
      $$(".map-cell", $("#districtsPage")).forEach(cell => {
        const name = cell.dataset.selectDistrict;
        const label = $(`.map-label[data-label-district="${name}"]`, $("#districtsPage"));
        if (!label) return;
        const show = () => label.classList.add("hover");
        const hide = () => label.classList.remove("hover");
        cell.addEventListener("mouseenter", show);
        cell.addEventListener("mouseleave", hide);
        cell.addEventListener("focus", show);
        cell.addEventListener("blur", hide);
      });
      $$("[data-select-district]", $("#districtsPage")).forEach(el => el.addEventListener("click", event => {
        if (event.target.closest("button")) return;
        state.district = el.dataset.selectDistrict;
        render();
      }));
      $$("[data-profile-district]", $("#districtsPage")).forEach(el => el.addEventListener("click", event => {
        event.stopPropagation();
        state.district = el.dataset.profileDistrict;
        state.page = "profile";
        render();
      }));
      $$("[data-page-jump]", $("#districtsPage")).forEach(btn => btn.addEventListener("click", () => {
        if (btn.dataset.pageJump === "tasks") {
          openTasksForContext(state.kpi, state.district, state.period, "open");
          return;
        }
        state.page = btn.dataset.pageJump;
        render();
      }));
      $$("[data-cross-kpi]", $("#districtsPage")).forEach(btn => btn.addEventListener("click", event => {
        event.stopPropagation();
        state.kpi = btn.dataset.crossKpi;
        render();
      }));
      $$("[data-open-report-modal]", $("#districtsPage")).forEach(btn => btn.addEventListener("click", () => {
        openReportModal({ kpi: btn.dataset.reportKpi || state.kpi, district: btn.dataset.reportDistrict || state.district, period: btn.dataset.reportPeriod || null });
      }));
      $$("[data-open-execution]", $("#districtsPage")).forEach(btn => btn.addEventListener("click", event => {
        event.stopPropagation();
        openExecutionJournal(btn.dataset.execKpi || state.kpi, btn.dataset.execDistrict || null, btn.dataset.execPeriod || null);
      }));
    }

    function renderProfilePage() {
      const d = currentDistrict();
      const kpi = currentKpiDef();
      const cfg = districtTableConfig(kpi);
      const metrics = districtAllMetrics(d);
      const summary = districtTaskSummary(d, kpi.id);
      const tasks = summary.tasks;
      const selected = districtKpi(d, state.kpi, cfg.primaryPeriod || state.period);
      const selectedStatus = rowStatus(selected);
      const latestReport = latestAnyReportFor(kpi.id, cfg.primaryPeriod || state.period, d.name);
      const districtReports = getExecutionReports()
        .filter(report => report.district === d.name)
        .sort((a, b) => String(b.createdAt || b.date).localeCompare(String(a.createdAt || a.date)));
      const recentReports = districtReports.slice(0, 4);
      const unresolvedTasks = tasks.filter(task => (task.status || "grey") !== "green");
      const districtTargets = districtTargetsForDistrict(d, kpi.id);
      const taskClass = summary.total && summary.unfinished ? "red" : summary.total ? "green" : "grey";
      $("#profilePage").innerHTML = `
        <div class="profile-filter">
          <label>Туман/шаҳар танлаш
            <select id="profileDistrictSelect">
              ${DATA.districts.map(item => `<option value="${item.name}" ${item.name === d.name ? "selected" : ""}>${item.name}</option>`).join("")}
            </select>
          </label>
          <label>KPI / маълумот тури
            <select id="profileKpiSelect">
              ${districtSelectorDefs().map(def => `<option value="${def.id}" ${def.id === state.kpi ? "selected" : ""}>${def.short} — ${def.label}</option>`).join("")}
            </select>
          </label>
          <div class="action-row" style="margin-top:0">
            <button class="mini-button" data-open-districts="${kpi.id}">Туманлар кесимига қайтиш</button>
            <button class="mini-button" data-open-report-modal data-report-kpi="${kpi.id}" data-report-district="${d.name}" data-report-period="${cfg.primaryPeriod || state.period}">Текширув киритиш</button>
            <button class="mini-button primary" data-page-jump="dashboard">KPI экрани</button>
          </div>
        </div>
        <div class="profile-grid">
          <article class="profile-focus">
            <div class="profile-hero">
              <div>
                <div class="eyebrow">${kpi.sector}</div>
                <h3>${d.name}: ${kpi.short}</h3>
                <p>Танланган KPI бўйича туман ҳолати: режа, амалдаги натижа, ҳисобот таъсири ва очиқ топшириқлар.</p>
                <div class="action-row">
                  <span class="chip blue">Туман профили</span>
                  <span class="chip ${selectedStatus}">${statusLabel(selectedStatus)}</span>
                  <span class="chip ${taskClass}">${summary.unfinished}/${summary.total} T-топшириқ</span>
                  <span class="chip grey">${districtTargets.length} D-мақсад</span>
                  ${latestReport ? `<span class="chip ${reportImpactClass(latestReport)}">${reportImpactLabel(latestReport)}</span>` : `<span class="chip grey">ҳисобот йўқ</span>`}
                </div>
              </div>
              <div class="profile-main-value">
                <strong>${districtPrimaryValue(selected, kpi.id)}</strong>
                <span>${districtPrimaryLabel(kpi.id)}</span>
              </div>
            </div>
            <div class="profile-metrics">
              ${cfg.columns.map(col => `<div class="profile-metric">
                <span>${col.label}</span>
                <strong>${col.value(d)}</strong>
                <small>${col.note ? col.note(d) : ""}</small>
              </div>`).join("")}
            </div>
          </article>
          <article class="panel">
            <div class="panel-head"><div><h3>Қисқа ҳолат</h3><p>Шу туман бўйича тезкор қарор учун керакли маълумот.</p></div></div>
            <div class="panel-body">
              <div class="profile-side-stat"><span>Масъул</span><strong>${d.owner}</strong></div>
              <div class="profile-side-stat"><span>Жорий маълумот</span><strong>${districtPrimaryValue(selected, kpi.id)}</strong></div>
              <div class="profile-side-stat"><span>Бажарилмаган T-топшириқ</span><strong>${summary.unfinished}/${summary.total}</strong></div>
              <div class="profile-side-stat"><span>Туман мақсадлари</span><strong>${districtTargets.length}</strong></div>
              <div class="profile-side-stat"><span>Ҳисобот таъсири</span><strong>${latestReport ? reportImpactLabel(latestReport) : "ҳисобот йўқ"}</strong></div>
              <div class="profile-actions" style="margin-top:12px">
                <button class="mini-button primary" data-open-report-modal data-report-kpi="${kpi.id}" data-report-district="${d.name}" data-report-period="${cfg.primaryPeriod || state.period}">Текширув киритиш</button>
                <button class="mini-button" data-open-districts="${kpi.id}">Туманлар жадвали</button>
                <button class="mini-button" data-open-execution data-exec-kpi="${kpi.id}" data-exec-district="${d.name}" data-exec-period="${cfg.primaryPeriod || state.period}">Ижро журнали</button>
              </div>
            </div>
          </article>
        </div>
        <article class="panel profile-secondary">
          <div class="panel-head">
            <div><h3>Шу туман бўйича KPIлар</h3><p>Кўрсаткични босинг: юқоридаги профиль шу KPIга мослашади.</p></div>
            <span class="chip blue">${d.name}</span>
          </div>
          <div class="panel-body">
            <div class="district-kpis">
              ${metrics.map(({def, row}) => `<button class="district-kpi ${def.id === state.kpi ? "active" : ""}" data-kpi="${def.id}" type="button">
                <span>${def.label}</span>
                <strong>${districtPrimaryValue(row, def.id)}</strong>
                <small>${districtMeasureNote(row, def.id)}</small>
              </button>`).join("")}
            </div>
          </div>
        </article>
        <div class="profile-bottom-grid">
          <article class="panel">
            <div class="panel-head">
              <div><h3>Ҳисоботлар</h3><p>${d.name} бўйича киритилган амалдаги натижалар ва уларнинг KPIга таъсири.</p></div>
              <span class="chip ${latestReport ? reportStatusClass(latestReport.status) : "grey"}">${latestReport ? reportStatusLabel(latestReport.status) : "йўқ"}</span>
            </div>
            <div class="panel-body">
              <div class="profile-report">
                ${recentReports.length ? recentReports.map(report => `<div class="report-item">
                  <header><strong>${h(report.kpiLabel || kpi.short)}</strong><span class="chip ${reportStatusClass(report.status)}">${reportStatusLabel(report.status)}</span></header>
                  <p>${h(report.periodLabel || "")} · амалда: <b>${h(report.actualValue || "—")} ${h(report.unit || "")}</b><br>${h(report.evidenceName || "далил киритилмаган")}</p>
                  <p><span class="chip ${reportImpactClass(report)}">${reportImpactLabel(report)}</span><br>Киритувчи: ${h(report.createdBy || report.responsible || "—")}${report.checkedBy ? `<br>Текширди: ${h(report.checkedBy)}` : ""}${latestStatusReason(report) ? `<br>${h(latestStatusReason(report))}` : ""}</p>
                </div>`).join("") : `<div class="empty"><b>Ҳисобот йўқ</b><br>Бу туман бўйича ҳали ижро ҳисоботи киритилмаган.</div>`}
              </div>
            </div>
          </article>
          <article class="panel">
            <div class="panel-head">
              <div><h3>${kpi.short} топшириқлари</h3><p>Фақат танланган KPI бўйича қисқа рўйхат.</p></div>
              <span class="chip ${taskClass}">${summary.unfinished}/${summary.total}</span>
            </div>
            <div class="panel-body">
              <div class="profile-task-list">
                ${(unresolvedTasks.length ? unresolvedTasks : tasks).slice(0, 4).map(taskCard).join("") || `<p class="muted">Бу KPI бўйича топшириқ топилмади.</p>`}
              </div>
              <div class="action-row">
                <button class="mini-button" data-page-jump="tasks">Барча топшириқлар</button>
              </div>
            </div>
          </article>
        </div>`;
      $$("[data-kpi]", $("#profilePage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.kpi;
        render();
      }));
      $("#profileDistrictSelect").addEventListener("change", event => {
        state.district = event.target.value;
        render();
      });
      $("#profileKpiSelect").addEventListener("change", event => {
        state.kpi = event.target.value;
        render();
      });
      $$("[data-open-districts]", $("#profilePage")).forEach(btn => btn.addEventListener("click", () => {
        state.kpi = btn.dataset.openDistricts;
        if (btn.dataset.period) state.period = btn.dataset.period;
        state.page = "districts";
        render();
      }));
      $$("[data-page-jump]", $("#profilePage")).forEach(btn => btn.addEventListener("click", () => {
        if (btn.dataset.pageJump === "tasks") {
          openTasksForContext(state.kpi, state.district, state.period, "open");
          return;
        }
        state.page = btn.dataset.pageJump;
        render();
      }));
      $$("[data-open-report-modal]", $("#profilePage")).forEach(btn => btn.addEventListener("click", () => {
        openReportModal({ kpi: btn.dataset.reportKpi || state.kpi, district: btn.dataset.reportDistrict || state.district, period: btn.dataset.reportPeriod || null });
      }));
      $$("[data-open-execution]", $("#profilePage")).forEach(btn => btn.addEventListener("click", () => {
        openExecutionJournal(btn.dataset.execKpi || state.kpi, btn.dataset.execDistrict || state.district, btn.dataset.execPeriod || null);
      }));
    }

    function renderExecutionPage() {
      const reports = filteredExecutionReports();
      const allReports = getExecutionReports();
      const approvedReports = allReports.filter(r => r.status === "approved");
      const submittedReports = allReports.filter(r => r.status === "submitted");
      const reviewReports = allReports.filter(r => r.status === "review");
      const rejectedReports = allReports.filter(r => r.status === "rejected");
      const queueReports = allReports.filter(r => r.status === "submitted" || r.status === "review").slice(0, 5);
      const visibleQueue = queueReports.length ? queueReports : allReports.slice(0, 5);
      const problemReports = allReports.filter(r => ["Бажарилмади", "Муддати кечикди"].includes(r.executionStatus) || ["Етарли эмас", "Далил йўқ"].includes(r.evidenceStatus) || r.status === "rejected");
      const approved = approvedReports.length;
      const submitted = submittedReports.length;
      const review = reviewReports.length;
      const rejected = rejectedReports.length;
      const approvedPct = allReports.length ? Math.round(approved / allReports.length * 100) : 0;
      const hasReports = allReports.length > 0;
      const commandTitle = hasReports ? "Ҳисоб палатаси текширувидан KPI амалда қийматигача" : "Ҳисоб палатаси маълумоти келишига тайёр ҳолат";
      const commandText = hasReports
        ? "Ҳисоб палатаси ҳар бир T/D қатор бўйича ижро ҳолати, далил ҳолати ва изоҳ беради. Фақат қабул қилинган қаторлар KPI “амалда” қийматига қўшилади."
        : "Реал текширув маълумоти ҳали киритилмаган. Template тўлдирилгандан кейин “Платформага қабул = Тайёр” қаторлари журналга тушади, тасдиқланганлари эса KPI “амалда” қийматига қўшилади.";
      const impactMap = new Map();
      approvedReports.forEach(report => {
        const key = report.kpiLabel || report.kpi || "KPI";
        const current = impactMap.get(key) || { label: key, count: 0, latest: report };
        current.count += 1;
        current.latest = report;
        impactMap.set(key, current);
      });
      const impactRows = [...impactMap.values()].slice(0, 5);
      const statusButton = (id, label, count, tone = "") => `<button class="execution-status-btn ${tone} ${state.reportStatus === id ? "active" : ""}" type="button" data-report-status-filter="${id}"><span>${label}</span><strong>${count}</strong></button>`;
      const reportCard = report => `<article class="execution-card">
        <header>
          <div>
            <strong>${h(report.district)} · ${h(report.kpiLabel)}</strong>
              <p>${h(report.taskTitle || report.comment || "Топшириқ ID кўрсатилмаган")}</p>
          </div>
          <span class="chip ${reportStatusClass(report.status)}">${reportStatusLabel(report.status)}</span>
        </header>
        <div class="execution-card-meta">
          <span>қатор: ${h(report.templateRowId || report.taskId || "—")}</span>
          <span>${h(report.periodLabel || report.period || "давр йўқ")}</span>
          <span>факт: ${h(report.actualValue || "—")} ${h(report.unit || "")}</span>
          <span>ижро: ${h(report.executionStatus || "—")}</span>
          <span>далил: ${h(report.evidenceStatus || "—")}</span>
          <span>${h(report.createdBy || report.responsible || "киритувчи йўқ")}</span>
        </div>
        <div class="action-row compact">
          ${report.status !== "approved" ? `<button class="mini-button primary" data-report-status="${report.id}:approved">Қабул қилиш</button>` : `<span class="chip green">KPIга қўшилди</span>`}
          ${report.status !== "review" ? `<button class="mini-button" data-report-status="${report.id}:review">Кўриб чиқишда</button>` : ""}
          ${report.status !== "rejected" ? `<button class="mini-button danger" data-report-status="${report.id}:rejected">Қайтариш</button>` : ""}
        </div>
      </article>`;
      $("#executionPage").innerHTML = `
        <div class="execution-command">
          <div class="execution-command-copy">
            <span>Ижро мониторинги</span>
            <strong>${commandTitle}</strong>
            <small>${commandText}</small>
          </div>
          <div class="execution-status-grid">
            ${statusButton("all", "Жами", allReports.length)}
            ${statusButton("submitted", "Киритилди", submitted, "blue")}
            ${statusButton("review", "Кўриб чиқилмоқда", review, "amber")}
            ${statusButton("approved", "Тасдиқланди", approved, "green")}
            ${statusButton("rejected", "Қайтарилди", rejected, "red")}
          </div>
          <div class="execution-actions">
            <a class="score-action primary" href="templates/hisob_palatasi_ijro_tekshiruv_template.xlsx" download>Template XLSX</a>
            <a class="score-action" href="templates/hisob_palatasi_ijro_import_contract.md">Import қоидаси</a>
            <button class="score-action" type="button" data-open-report-modal>Текширув киритиш</button>
            <button class="score-action" type="button" data-clear-report-filters>Фильтрни тозалаш</button>
          </div>
        </div>
        <div class="execution-flow">
          <div class="execution-step"><span>1</span><strong>Template тўлдирилади</strong><small>Ҳисоб палатаси T-ID ёки D-ID бўйича текширув натижасини беради.</small></div>
          <div class="execution-step"><span>2</span><strong>Platform қабул қилади</strong><small>“Платформага қабул” тайёр бўлган қаторлар журналга тушади.</small></div>
          <div class="execution-step"><span>3</span><strong>Ижро ҳолати кўринади</strong><small>Бажарилди, қисман, бажарилмади, кечикди ва далил сифати алоҳида юритилади.</small></div>
          <div class="execution-step"><span>4</span><strong>KPIга қўшилади</strong><small>Фақат тасдиқланган/қабул қилинган қатор “амалда” қийматга ўтади.</small></div>
        </div>
        <div class="execution-workspace">
          <section class="execution-lane">
            <div class="execution-lane-head">
              <h3>${queueReports.length ? "Текшириш навбати" : "Сўнгги ҳисоботлар"}</h3>
              <span class="chip ${queueReports.length ? "blue" : "grey"}">${visibleQueue.length} та</span>
            </div>
            <div class="execution-card-list">
              ${visibleQueue.length ? visibleQueue.map(reportCard).join("") : `<div class="execution-empty">Ҳали Ҳисоб палатаси текшируви киритилмаган. Биринчи текширувни “Текширув киритиш” орқали киритинг.</div>`}
            </div>
          </section>
          <aside class="task-focus">
            <div class="eyebrow">KPIга қўшилган ҳисоботлар</div>
            <h3>${approvedPct}% тасдиқланган</h3>
            <p>Ҳисоб палатаси тасдиқлаган ва platform қабул қилган қаторлар KPI мониторингида амалдаги натижа сифатида ишлатилади. Қайта кўриш ва қайтарилган қаторлар KPIга қўшилмайди.</p>
            <div class="execution-impact">
              <div class="impact-row"><div><strong>Тасдиқланган</strong><span>KPI амалда қийматига қўшилган</span></div><span class="chip green">${approved}</span></div>
              <div class="impact-row"><div><strong>Кутилаётган</strong><span>киритилган ёки кўриб чиқилмоқда</span></div><span class="chip blue">${submitted + review}</span></div>
              <div class="impact-row"><div><strong>Қайтарилган</strong><span>KPIга қўшилмайди</span></div><span class="chip red">${rejected}</span></div>
              <div class="impact-row"><div><strong>Муаммоли</strong><span>кечиккан, бажарилмаган ёки далили етарсиз</span></div><span class="chip amber">${problemReports.length}</span></div>
              ${impactRows.length ? impactRows.map(row => `<div class="impact-row"><div><strong>${h(row.label)}</strong><span>${h(row.latest.district)} · ${h(row.latest.periodLabel || "")}</span></div><span class="chip green">${row.count}</span></div>`).join("") : `<div class="execution-empty">Тасдиқланган ҳисобот йўқ.</div>`}
            </div>
          </aside>
        </div>
        <div class="task-filter report-filter execution-filter">
          <label>KPI
            <select id="reportKpiFilter">
              <option value="all" ${state.sector === "all" ? "selected" : ""}>Барча KPI</option>
              ${reportKpiOptions().map(def => `<option value="${def.id}" ${state.sector === def.id ? "selected" : ""}>${def.short}</option>`).join("")}
            </select>
          </label>
          <label>Ҳолат
            <select id="reportStatusFilter">
              <option value="all" ${state.reportStatus === "all" ? "selected" : ""}>Барча ҳолатлар</option>
              <option value="submitted" ${state.reportStatus === "submitted" ? "selected" : ""}>Киритилди</option>
              <option value="approved" ${state.reportStatus === "approved" ? "selected" : ""}>Тасдиқланди</option>
              <option value="review" ${state.reportStatus === "review" ? "selected" : ""}>Кўриб чиқилмоқда</option>
              <option value="rejected" ${state.reportStatus === "rejected" ? "selected" : ""}>Қайтарилди</option>
            </select>
          </label>
          <label>Давр
            <select id="reportPeriodFilter">
              <option value="all" ${state.reportPeriod === "all" ? "selected" : ""}>Барча даврлар</option>
              <option value="q1" ${state.reportPeriod === "q1" ? "selected" : ""}>I чорак</option>
              ${periods.map(p => `<option value="${p.id}" ${state.reportPeriod === p.id ? "selected" : ""}>${p.label}</option>`).join("")}
            </select>
          </label>
          <label>Туман/шаҳар
            <select id="reportDistrictFilter">
              <option value="all" ${state.reportDistrict === "all" ? "selected" : ""}>Барча туманлар</option>
              ${(DATA.districts || []).map(d => `<option value="${h(d.name)}" ${state.reportDistrict === d.name ? "selected" : ""}>${h(d.name)}</option>`).join("")}
            </select>
          </label>
          <label>Қидириш
            <input id="reportSearchBox" type="search" value="${h(state.search)}" placeholder="туман, KPI, изоҳ">
          </label>
          <div class="action-row" style="margin-top:0">
            <button class="mini-button" data-clear-report-filters>Фильтрни тозалаш</button>
          </div>
        </div>
        <article class="panel">
          <div class="panel-head">
            <div><h3>Батафсил журнал</h3><p>${reports.length} / ${allReports.length} та текширув кўрсатилмоқда. Бу ерда ҳар бир қаторнинг ижро ҳолати, далил ҳолати ва KPIга қўшилган-қўшилмагани кўринади.</p></div>
            <span class="chip blue">Ҳисоб палатаси журнали</span>
          </div>
          <div class="panel-body">
            ${reports.length ? `<div class="table-scroll">
              <table>
                <thead><tr><th>Қатор</th><th>Сана</th><th>Давр</th><th>Ҳудуд</th><th>KPI</th><th>Топшириқ / изоҳ</th><th class="num">Факт</th><th>Ижро / далил</th><th>Қабул ҳолати</th><th>Муаммо / тавсия</th><th>Амал</th></tr></thead>
                <tbody>${reports.map(report => `<tr>
                  <td><strong>${h(report.templateRowId || report.taskId || "—")}</strong><br><span class="muted">${h(report.reportSource || "Ҳисоб палатаси")}</span></td>
                  <td>${h(report.date)}</td>
                  <td>${h(report.periodLabel)}</td>
                  <td><strong>${h(report.district)}</strong><br><span class="muted">${h(report.responsible || "")}</span></td>
                  <td>${h(report.kpiLabel)}</td>
                  <td>${h((report.taskTitle || "").slice(0, 90))}${(report.taskTitle || "").length > 90 ? "…" : ""}<br><span class="muted">${h(report.comment || "")}</span></td>
                  <td class="num"><strong>${h(report.actualValue || "—")}</strong><br><span class="muted">${h(report.unit || "")}</span></td>
                  <td>
                    <span class="chip ${executionStatusClass(report.executionStatus)}">${h(report.executionStatus || "Маълумот йўқ")}</span>
                    <div style="margin-top:6px"><span class="chip ${evidenceStatusClass(report.evidenceStatus)}">${h(report.evidenceStatus || "Далил йўқ")}</span></div>
                    <div class="history-list"><span>Текширувчи: ${h(report.checkedBy || report.createdBy || "—")}</span></div>
                  </td>
                  <td>
                    <span class="chip ${reportStatusClass(report.status)}">${reportStatusLabel(report.status)}</span>
                    <div style="margin-top:6px"><span class="chip ${reportImpactClass(report)}">${report.status === "approved" ? "KPIга қўшилди" : "KPIга қўшилмади"}</span></div>
                    <div class="history-list">${reportHistory(report).slice(0, 3).map(item => `<span>${h(reportStatusLabel(item.status))} · ${h(item.actor || "—")} · ${h((item.at || "").slice(0, 10))}</span>`).join("")}</div>
                  </td>
                  <td><strong>${h(report.issueType || "Муаммо йўқ")}</strong><br><span class="muted">${h(report.evidenceName || "далил йўқ")}</span><br><span class="muted">${h(latestStatusReason(report) || "сабаб/изоҳ йўқ")}</span>${report.correctionDeadline ? `<br><span class="muted">тузатиш: ${h(report.correctionDeadline)}</span>` : ""}</td>
                  <td>
                    <div class="action-row compact">
                      ${report.status !== "approved" ? `<button class="mini-button" data-report-status="${report.id}:approved">Қабул қилиш</button>` : `<span class="chip green">KPIга қўшилди</span>`}
                      ${report.status !== "review" ? `<button class="mini-button" data-report-status="${report.id}:review">Кўриб чиқишда</button>` : ""}
                      ${report.status !== "rejected" ? `<button class="mini-button danger" data-report-status="${report.id}:rejected">Қайтариш</button>` : ""}
                    </div>
                  </td>
                </tr>`).join("")}</tbody>
              </table>
            </div>` : `<div class="execution-empty"><b>Текширув киритилмаган</b><br>“Текширув киритиш” тугмаси орқали биринчи Ҳисоб палатаси текширувини киритинг.</div>`}
          </div>
        </article>`;
      $("#reportKpiFilter").addEventListener("change", event => {
        state.sector = event.target.value;
        render();
      });
      $("#reportStatusFilter").addEventListener("change", event => {
        state.reportStatus = event.target.value;
        render();
      });
      $("#reportPeriodFilter").addEventListener("change", event => {
        state.reportPeriod = event.target.value;
        render();
      });
      $("#reportDistrictFilter").addEventListener("change", event => {
        state.reportDistrict = event.target.value;
        render();
      });
      $("#reportSearchBox").addEventListener("input", event => {
        state.search = event.target.value;
        render();
      });
      $$("[data-open-report-modal]", $("#executionPage")).forEach(btn => btn.addEventListener("click", openReportModal));
      $$("[data-clear-report-filters]", $("#executionPage")).forEach(btn => btn.addEventListener("click", () => {
        state.sector = "all";
        state.reportStatus = "all";
        state.reportPeriod = "all";
        state.reportDistrict = "all";
        state.search = "";
        render();
      }));
      $$("[data-report-status-filter]", $("#executionPage")).forEach(btn => btn.addEventListener("click", () => {
        state.reportStatus = btn.dataset.reportStatusFilter;
        render();
      }));
      $$("[data-report-status]", $("#executionPage")).forEach(btn => btn.addEventListener("click", () => {
        const [id, status] = btn.dataset.reportStatus.split(":");
        openStatusModal(id, status);
      }));
    }

    function setPageMeta() {
      const meta = {
        dashboard: ["KPI", "Йиллик режа, чораклар кесими ва ижро ҳолати."],
        tasks: ["Топшириқлар", "Танланган KPIга боғланган бажарилмаган ишлар."],
        districts: ["Туманлар кесими", `${currentKpiDef().short} бўйича туман/шаҳарлар кесими: режа, амалда/кутилма, ижро ва топшириқлар.`],
        profile: ["Туман ҳолати", `${state.district}: ${currentKpiDef().short} бўйича режа, амалда ва топшириқлар.`],
        execution: ["Ижро мониторинги", "Ҳисоб палатаси текширувлари, ижро ҳолати, далил сифати ва platform қабул ҳолати."]
      };
      $("#pageTitle").textContent = meta[state.page][0];
      $("#pageSubtitle").textContent = meta[state.page][1];
    }

    function render() {
      if (state.page === "kpi") state.page = "dashboard";
      const pageChanged = renderedPage !== state.page;
      renderPeriodTabs();
      renderSectorFilter();
      $("#periodTabs").closest(".segmented").classList.toggle("hidden", ["dashboard", "tasks", "execution"].includes(state.page));
      $("#sectorFilter").classList.toggle("hidden", ["dashboard", "tasks", "districts", "profile", "execution"].includes(state.page));
      $("#searchBox").classList.toggle("hidden", ["dashboard", "tasks", "districts", "profile", "execution"].includes(state.page));
      $$(".nav-btn").forEach(btn => btn.classList.toggle("active", btn.dataset.page === state.page));
      ["dashboard", "tasks", "districts", "profile", "execution"].forEach(page => {
        $(`#${page}Page`).classList.toggle("hidden", page !== state.page);
      });
      if (state.page === "dashboard") renderDashboard();
      if (state.page === "tasks") renderTasksPage();
      if (state.page === "districts") renderDistrictsPage();
      if (state.page === "profile") renderProfilePage();
      if (state.page === "execution") renderExecutionPage();
      setPageMeta();
      if (pageChanged) {
        renderedPage = state.page;
        if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
        window.scrollTo({ top: 0, left: 0, behavior: "auto" });
        requestAnimationFrame(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }));
        setTimeout(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }), 0);
        setTimeout(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }), 80);
        setTimeout(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }), 180);
        setTimeout(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }), 320);
      }
    }

    $$(".nav-btn").forEach(btn => btn.addEventListener("click", () => {
      state.page = btn.dataset.page;
      if (state.page === "tasks") {
        state.kpi = "all";
        state.taskModule = "all";
        state.taskStatus = "open";
        state.taskDistrict = "all";
        state.taskPeriod = "all";
      }
      render();
    }));

    $("#sectorFilter").addEventListener("change", event => {
      state.sector = event.target.value;
      render();
    });

    $("#searchBox").addEventListener("input", event => {
      state.search = event.target.value;
      render();
    });

    document.addEventListener("click", event => {
      const closeReport = event.target.closest("[data-close-report]");
      if (closeReport || event.target === $("#reportModalBg")) {
        closeReportModal();
        return;
      }
      if (event.target.closest("[data-save-report]")) {
        addExecutionReport();
        return;
      }
      const saveStatus = event.target.closest("[data-save-status]");
      if (saveStatus) {
        saveStatusChange(saveStatus.dataset.saveStatus);
        return;
      }
      const close = event.target.closest("[data-close-modal]");
      if (close || event.target === $("#taskModalBg")) {
        closeTaskModal();
        return;
      }
      const save = event.target.closest("[data-save-evidence]");
      if (save) {
        addTaskEvidence(save.dataset.saveEvidence);
        render();
        return;
      }
      const task = event.target.closest("[data-task-id]");
      if (task) {
        openTaskModal(task.dataset.taskId);
      }
    });

    document.addEventListener("keydown", event => {
      if (event.key === "Escape" && $("#taskModalBg").classList.contains("open")) closeTaskModal();
      if (event.key === "Escape" && $("#reportModalBg").classList.contains("open")) closeReportModal();
    });

    render();
  </script>
</body>
</html>
"""


if __name__ == "__main__":
    import sys

    if "--audit-action-plan" in sys.argv:
        audit_action_plan_cli()
    elif "--audit-kpi-compare" in sys.argv:
        audit_kpi_compare_cli()
    else:
        main()
