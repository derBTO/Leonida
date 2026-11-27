<?php

namespace Tests\Feature;

use App\Models\LookupValue;
use App\Models\Vehicle;
use App\Models\Weapon;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    private function import(string $kind, string $content, array $options = []): TestResponse
    {
        return $this->post("/api/admin/catalog-import/$kind", ['file' => $this->csv($content)] + $options, ['Accept' => 'application/json']);
    }

    public function test_only_admins_may_import(): void
    {
        $this->actingAs($this->editor());
        $this->import('weapons', "name\nX\n")->assertForbidden();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->actingAs($this->admin());
        $report = $this->import('weapons', "name,weapon_type,damage\nGirardi ES9,Pistole,4.2\n", ['dry_run' => 1])->assertOk()->json('report');
        $this->assertSame(1, $report['created']);
        $this->assertSame(['weapon_type' => ['Pistole']], $report['lookups_created']);
        $this->assertSame(0, Weapon::query()->count());
        $this->assertSame(0, LookupValue::query()->count());
    }

    public function test_weapons_are_created_with_lookups_stats_and_source(): void
    {
        $this->actingAs($this->admin());
        $csv = "\u{FEFF}name;weapon_type;damage;accuracy;handling;range;is_legendary;magazine_size;source_name;source_url\n"
            ."Gil's Kalinga .50;Pistole;7,0;4,3;7,9;5;ja;7;NiaDB;https://meowdb.com/gta6/weapons/gils-kalinga-50\n"
            ."Klose K17;pistole;4.0;;;;;;;\n";
        $report = $this->import('weapons', $csv)->assertOk()->json('report');
        $this->assertSame(2, $report['created']);
        $this->assertSame([], $report['errors']);

        $gil = Weapon::query()->where('name', "Gil's Kalinga .50")->first();
        $this->assertTrue($gil->is_legendary);
        $this->assertSame(7.0, $gil->damage);
        $this->assertSame(7, $gil->magazine_size);
        $this->assertSame('NiaDB', $this->getJson("/api/weapons/{$gil->id}")->json('item.source.name'));

        $k17 = Weapon::query()->where('name', 'Klose K17')->first();
        $this->assertSame($gil->weapon_type_id, $k17->weapon_type_id, 'Listenwert ohne Rücksicht auf Groß-/Kleinschreibung');
        $this->assertNull($k17->accuracy, 'Leere Zelle bleibt NULL');
        $this->assertNull($k17->is_legendary);
        $this->assertNull($k17->source_name);
    }

    public function test_existing_values_are_kept_unless_overwrite(): void
    {
        $this->actingAs($this->admin());
        $this->import('vehicles', "name,speed,rating_on_road\nPegassi Vitello,8.0,\n")->assertOk();
        $report = $this->import('vehicles', "name,speed,acceleration,rating_on_road\n pegassi  vitello ,8.2,8.4,832\n")->assertOk()->json('report');
        $this->assertSame(1, $report['updated']);
        $this->assertSame(1, $report['conflict_count']);
        $this->assertSame('speed', $report['conflicts'][0]['field']);

        $car = Vehicle::query()->sole();
        $this->assertSame(8.0, $car->speed, 'vorhandener Wert bleibt');
        $this->assertSame(8.4, $car->acceleration, 'leeres Feld wird ergänzt');
        $this->assertSame(832, $car->rating_on_road);

        $this->import('vehicles', "name,speed\nPegassi Vitello,8.2\n", ['overwrite' => 1])->assertOk();
        $this->assertSame(8.2, $car->fresh()->speed);

        $report = $this->import('vehicles', "name,speed\nPegassi Vitello,\n")->assertOk()->json('report');
        $this->assertSame(1, $report['unchanged']);
        $this->assertSame(8.2, $car->fresh()->speed, 'leere Zelle löscht nichts');
    }

    public function test_invalid_rows_are_reported_and_skipped(): void
    {
        $this->actingAs($this->admin());
        $csv = "name,speed,rating_on_road,drivetrain,registrable,source_url\n"
            ."Gut,5.5,500,rwd,nein,\n"
            ."Zu schnell,10.5,,,,\n"
            ."Rating,,1200,,,\n"
            .",1,,,,\n"
            ."Antrieb,,,4x4,,\n"
            ."Bool,,,,vielleicht,\n"
            ."Link,,,,,javascript:alert(1)\n"
            ."gut,1,,,,\n";
        $report = $this->import('vehicles', $csv)->assertOk()->json('report');
        $this->assertSame(1, $report['created']);
        $this->assertSame(7, $report['skipped']);
        $this->assertSame([3, 4, 5, 6, 7, 8, 9], array_column($report['errors'], 'line'));
        $car = Vehicle::query()->sole();
        $this->assertSame('RWD', $car->drivetrain->value);
        $this->assertFalse($car->registrable);
    }

    public function test_unknown_columns_reject_the_file(): void
    {
        $this->actingAs($this->admin());
        $this->import('weapons', "name,schaden\nX,5\n")->assertStatus(422)->assertJsonValidationErrors('file');
        $this->import('weapons', "typ\nPistole\n")->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_template_download(): void
    {
        $this->actingAs($this->admin());
        $body = $this->get('/api/admin/catalog-template/vehicles')->assertOk()->getContent();
        $this->assertStringStartsWith('name,manufacturer,vehicle_class', $body);
    }

    /** Nur lokal: die NiaDB-Daten liegen nicht im Repository (data/imports/niadb, siehe docs/KATALOG-IMPORT.md). */
    public function test_niadb_files_import_cleanly(): void
    {
        if (! is_file(base_path('../data/imports/niadb/waffen.csv'))) {
            $this->markTestSkipped('NiaDB-Daten nicht vorhanden (data/imports/niadb).');
        }
        $this->actingAs($this->admin());
        foreach (['weapons' => 'waffen.csv', 'vehicles' => 'fahrzeuge.csv'] as $kind => $file) {
            $report = $this->import($kind, file_get_contents(base_path("../data/imports/niadb/$file")), ['dry_run' => 1])->assertOk()->json('report');
            $this->assertSame([], $report['errors'], $kind);
            $this->assertSame($report['rows'], $report['created'], $kind);
        }
    }
}
