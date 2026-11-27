#!/usr/bin/env python3
"""Wandelt die NiaDB-Rohdaten (data/imports/niadb/raw) in Leonidas CSV-Importformat um.

Die NiaDB-Daten liegen nicht im Repository (data/ ist von Git ausgenommen): Rohdaten von
https://meowdb.com/gta6/ selbst abrufen und als raw/weapons.csv bzw. raw/vehicles.csv ablegen.

Quelle: NiaMeowDB, https://meowdb.com/gta6/ – die Daten dürfen mit Quellenlink
weiterverwendet werden ("Data from NiaDB"). Bilder werden nicht übernommen.

Aufruf:  python3 scripts/niadb_convert.py
Ergebnis: data/imports/niadb/waffen.csv und data/imports/niadb/fahrzeuge.csv
"""

import csv
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RAW = ROOT / "data" / "imports" / "niadb" / "raw"
OUT = ROOT / "data" / "imports" / "niadb"
BASE = "https://meowdb.com/gta6/"
SOURCE = "NiaDB"

WEAPON_TYPES = {
    "Pistol": "Pistole",
    "Revolver": "Revolver",
    "Shotgun": "Schrotflinte",
    "SMG": "Maschinenpistole",
    "Launcher": "Werfer",
    "Machine gun": "Maschinengewehr",
    "Rifle": "Gewehr",
    "Melee": "Nahkampf",
    "Stun gun": "Elektroschocker",
    "Explosive": "Sprengstoff",
}

VEHICLE_CLASSES = {
    "Car": "Auto",
    "Truck": "Pickup & Lkw",
    "SUV and van": "SUV & Van",
    "Motorcycle": "Motorrad",
    "Boat": "Boot",
    "Aircraft": "Luftfahrzeug",
    "Special": "Spezialfahrzeug",
}

# Sammel- oder Platzhaltereinträge, die kein einzelnes Fahrzeug bzw. keine benannte Waffe sind.
SKIP_WEAPONS = {"Pistol marked CAPO", "Rifle marked DUKE"}
SKIP_VEHICLES = {
    "Classic Car Collection", "Police cruisers", "Police boats",
    "Sheriff airboats", "Airboats", "Floatplanes",
}

UNIQUE = re.compile(r"\bunique\b|\bgold-icon\b|\bvariant of\b", re.I)
# Basismodelle verweisen auf ihre Unique-Variante ("The X is its unique variant").
BASE_MODEL = re.compile(r"\bis (?:its|a) (?:gold )?unique variant\b", re.I)


def source_url(path: str, kind: str) -> str:
    path = path.strip()
    if not path or path in ("weapons", "vehicles", "editions"):
        return BASE + kind
    return BASE + path


def legendary(detail: str) -> str:
    if BASE_MODEL.search(detail):
        return ""
    return "ja" if UNIQUE.search(detail) else ""


def read(name: str) -> list[dict]:
    with open(RAW / name, newline="", encoding="utf-8") as f:
        return list(csv.DictReader(f))


def write(name: str, header: list[str], rows: list[dict]) -> None:
    with open(OUT / name, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=header, lineterminator="\n")
        w.writeheader()
        w.writerows(rows)


def weapons() -> int:
    header = ["name", "weapon_type", "damage", "accuracy", "handling", "range",
              "is_legendary", "notes", "source_name", "source_url"]
    rows = []
    for r in read("weapons.csv"):
        if r["name"] in SKIP_WEAPONS:
            continue
        if r["type"] not in WEAPON_TYPES:
            sys.exit(f"Unbekannter Waffentyp: {r['type']}")
        rows.append({
            "name": r["name"], "weapon_type": WEAPON_TYPES[r["type"]],
            "damage": r["damage"], "accuracy": r["accuracy"],
            "handling": r["handling"], "range": r["range"],
            "is_legendary": legendary(r["detail"]), "notes": r["detail"],
            "source_name": SOURCE, "source_url": source_url(r["url"], "weapons"),
        })
    write("waffen.csv", header, rows)
    return len(rows)


def vehicles() -> int:
    header = ["name", "manufacturer", "vehicle_class", "rating_on_road", "rating_off_road",
              "speed", "acceleration", "handling_on_road", "handling_off_road", "durability",
              "notes", "source_name", "source_url"]
    rows = []
    for r in read("vehicles.csv"):
        if r["name"] in SKIP_VEHICLES:
            continue
        if r["class"] not in VEHICLE_CLASSES:
            sys.exit(f"Unbekannte Fahrzeugklasse: {r['class']}")
        rows.append({
            "name": r["name"], "manufacturer": r["manufacturer"],
            "vehicle_class": VEHICLE_CLASSES[r["class"]],
            "rating_on_road": r["rating_road"], "rating_off_road": r["rating_offroad"],
            "speed": r["speed"], "acceleration": r["acceleration"],
            "handling_on_road": r["handling_asphalt"], "handling_off_road": r["handling_offroad"],
            "durability": r["strength"], "notes": r["detail"],
            "source_name": SOURCE, "source_url": source_url(r["url"], "vehicles"),
        })
    write("fahrzeuge.csv", header, rows)
    return len(rows)


if __name__ == "__main__":
    print(f"Waffen: {weapons()}  Fahrzeuge: {vehicles()}")
