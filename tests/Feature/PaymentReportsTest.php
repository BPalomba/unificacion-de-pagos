<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentReport;
use App\Models\PaymentReviewGroup;
use App\Models\UnifiedPayment;
use App\Models\User;
use App\Services\Payments\PaymentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesUsersWithTwoFactor;
use Tests\TestCase;

class PaymentReportsTest extends TestCase
{
    use CreatesUsersWithTwoFactor, RefreshDatabase;

    private const ENCABEZADOS = [
        'PACIENTE', 'DNI', 'FECHA', 'HORA', 'DEPARTAMENTO', 'ESPECIALIDAD', 'MEDICO',
        'COBERTURA', 'NUMEROBENEFICIARIO', 'NUMERO', 'PRESTACION', 'CODIGO',
        'CANTIDAD', 'PRECIO', 'COPAGO', 'TOTAL', 'ESTADO', 'TIPO',
    ];

    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function fila(string $paciente, string $tipo, string $medico = 'Dr. House', string $numero = ''): array
    {
        return [
            $paciente, '30111222', '1/6/2026', '10:00', 'Clínica', 'Cardiología', $medico,
            'OSDE', '123', $numero, 'Consulta', '420101', 1, 1000, 0, 1000, 'ESTADO_CONFIRMADO', $tipo,
        ];
    }

    /**
     * Un par cobertura + Bono a Persona, una fila sin duplicado y un grupo
     * de 3 filas que queda para revisión manual.
     */
    private function filasDePrueba(): array
    {
        return [
            $this->fila('PEREZ JUAN', 'Obra Social'),
            $this->fila('PEREZ JUAN', 'Bono a Persona'),
            $this->fila('GOMEZ ANA', 'Obra Social'),
            $this->fila('LOPEZ LUIS', 'Obra Social'),
            $this->fila('LOPEZ LUIS', 'Obra Social'),
            $this->fila('LOPEZ LUIS', 'Bono a Persona'),
        ];
    }

    private function excelPath(array $filas): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([self::ENCABEZADOS, ...$filas]);

        $path = tempnam(sys_get_temp_dir(), 'informe') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->temporales[] = $path;

        return $path;
    }

    private function excel(array $filas, string $nombre = 'Reporte Junio.xlsx'): UploadedFile
    {
        return new UploadedFile($this->excelPath($filas), $nombre, null, null, true);
    }

    private function subir(User $user, array $filas, string $nombre = 'Reporte Junio.xlsx')
    {
        return $this->actingAs($user)->post('/informes', ['archivo' => $this->excel($filas, $nombre)]);
    }

    private function filasDelInforme(string $id): int
    {
        return Payment::where('import_batch_id', $id)->count()
            + UnifiedPayment::where('import_batch_id', $id)->count()
            + PaymentReviewGroup::where('import_batch_id', $id)->count();
    }

    public function test_subir_un_excel_crea_el_informe_y_lleva_a_su_pagina(): void
    {
        $this->freezeSecond();
        $user = $this->userWithTwoFactor();

        $response = $this->subir($user, $this->filasDePrueba());

        $informe = PaymentReport::sole();
        $response->assertRedirect(route('informes.show', $informe));

        $this->assertTrue($informe->user->is($user));
        $this->assertSame('Reporte Junio.xlsx', $informe->nombre_archivo);
        $this->assertSame(6, $informe->filas_leidas);
        $this->assertSame(1, $informe->unificados);
        $this->assertSame(1, $informe->standalone);
        $this->assertSame(1, $informe->revision);
        $this->assertTrue($informe->expires_at->equalTo(now()->addMinutes(120)));

        $this->actingAs($user)->get(route('informes.show', $informe))
            ->assertOk()
            ->assertSee('Reporte Junio.xlsx')
            ->assertSee('GOMEZ ANA')
            ->assertSee('5 fila(s)')
            ->assertSee('para revisión manual');

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Reporte Junio.xlsx')
            ->assertSee(route('informes.show', $informe));
    }

    public function test_la_duracion_es_configurable(): void
    {
        $this->freezeSecond();
        config(['payments.report_ttl_minutes' => 30]);
        $user = $this->userWithTwoFactor();

        $this->subir($user, $this->filasDePrueba());

        $this->assertTrue(PaymentReport::sole()->expires_at->equalTo(now()->addMinutes(30)));
        $this->actingAs($user)->get('/')->assertSee('30 minutos');
    }

    public function test_un_usuario_no_puede_ver_los_informes_de_otro(): void
    {
        $duenio = $this->userWithTwoFactor();
        $otro = $this->userWithTwoFactor();

        $this->subir($duenio, $this->filasDePrueba());
        $informe = PaymentReport::sole();

        $this->actingAs($otro)->get('/')
            ->assertOk()
            ->assertDontSee('Reporte Junio.xlsx')
            ->assertSee('No tenés informes vigentes');

        foreach ([
            $this->actingAs($otro)->get(route('informes.show', $informe)),
            $this->actingAs($otro)->get(route('informes.export', $informe)),
            $this->actingAs($otro)->delete(route('informes.destroy', $informe)),
        ] as $response) {
            $response->assertRedirect(route('copagos.index'))
                ->assertSessionHas('warning', 'El informe no existe o ya venció.');
        }

        $this->assertModelExists($informe);
    }

    public function test_el_informe_vencido_deja_de_verse_y_se_purga(): void
    {
        config(['payments.report_ttl_minutes' => 30]);
        $user = $this->userWithTwoFactor();

        $this->subir($user, $this->filasDePrueba());
        $informe = PaymentReport::sole();
        $this->assertGreaterThan(0, $this->filasDelInforme($informe->id));

        $this->travel(31)->minutes();

        $this->actingAs($user)->get(route('informes.show', $informe))
            ->assertRedirect(route('copagos.index'));

        // Entrar a la pantalla principal dispara la limpieza
        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('Reporte Junio.xlsx');

        $this->assertModelMissing($informe);
        $this->assertSame(0, $this->filasDelInforme($informe->id));
    }

    public function test_la_purga_borra_pagos_sin_informe_vigente_y_respeta_los_vigentes(): void
    {
        $user = $this->userWithTwoFactor();
        $this->subir($user, $this->filasDePrueba());
        $vigente = PaymentReport::sole();
        $filasVigentes = $this->filasDelInforme($vigente->id);

        // Datos cargados antes de existir los informes
        Payment::create(['paciente' => 'VIEJO SIN BATCH', 'import_batch_id' => null]);
        Payment::create(['paciente' => 'VIEJO CON BATCH', 'import_batch_id' => 'batch-viejo']);
        PaymentReviewGroup::create(['import_batch_id' => 'batch-viejo', 'motivo' => 'x']);

        $resultado = app(PaymentReportService::class)->purgarVencidos();

        $this->assertSame(['informes' => 0, 'pagos' => 2], $resultado);
        $this->assertSame(0, Payment::where('paciente', 'like', 'VIEJO%')->count());
        $this->assertSame(0, PaymentReviewGroup::where('import_batch_id', 'batch-viejo')->count());
        $this->assertSame($filasVigentes, $this->filasDelInforme($vigente->id));
    }

    public function test_se_puede_descargar_el_excel_del_informe(): void
    {
        $user = $this->userWithTwoFactor();
        $this->subir($user, $this->filasDePrueba());
        $informe = PaymentReport::sole();

        $response = $this->actingAs($user)->get(route('informes.export', $informe));

        $response->assertOk();
        $this->assertStringContainsString(
            'reporte-junio_unificado.xlsx',
            $response->headers->get('content-disposition'),
        );
    }

    public function test_eliminar_un_informe_borra_sus_datos(): void
    {
        $user = $this->userWithTwoFactor();
        $this->subir($user, $this->filasDePrueba());
        $informe = PaymentReport::sole();

        $this->actingAs($user)->delete(route('informes.destroy', $informe))
            ->assertRedirect(route('copagos.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($informe);
        $this->assertSame(0, $this->filasDelInforme($informe->id));
    }

    public function test_un_excel_ilegible_vuelve_con_error(): void
    {
        $user = $this->userWithTwoFactor();
        $roto = UploadedFile::fake()->createWithContent('roto.xlsx', 'esto no es un excel');

        $this->actingAs($user)->from('/')->post('/informes', ['archivo' => $roto])
            ->assertRedirect(route('copagos.index'))
            ->assertSessionHasErrors('archivo');

        $this->assertSame(0, PaymentReport::count());
    }

    public function test_el_cron_exige_el_secreto(): void
    {
        $this->get('/cron/purgar-informes')->assertNotFound();

        config(['payments.cron_secret' => 'secreto-de-prueba']);

        $this->get('/cron/purgar-informes')->assertNotFound();
        $this->withToken('otro')->get('/cron/purgar-informes')->assertNotFound();

        Payment::create(['paciente' => 'HUERFANO']);

        $this->withToken('secreto-de-prueba')->get('/cron/purgar-informes')
            ->assertOk()
            ->assertExactJson(['informes' => 0, 'pagos' => 1]);
    }

    public function test_la_grilla_pagina_y_filtra_solo_dentro_del_informe(): void
    {
        $user = $this->userWithTwoFactor();

        // 60 filas sin duplicado: 30 de cada médico
        $filas = [];
        for ($i = 1; $i <= 60; $i++) {
            $filas[] = $this->fila(sprintf('PACIENTE %02d', $i), 'Obra Social', $i % 2 ? 'Dr. Impar' : 'Dr. Par');
        }
        $this->subir($user, $filas);
        $informe = PaymentReport::sole();

        // Otro informe del mismo usuario, que no debe mezclarse
        $this->subir($user, [$this->fila('DE OTRO INFORME', 'Obra Social', 'Dr. Par')], 'otro.xlsx');

        $this->actingAs($user)->get(route('informes.show', $informe))
            ->assertOk()
            ->assertSee('60 fila(s)')
            ->assertDontSee('DE OTRO INFORME');

        $pagina2 = $this->actingAs($user)->get(route('informes.show', [$informe, 'page' => 2]))->assertOk();
        $this->assertCount(10, $pagina2->viewData('rows')->items());

        $this->actingAs($user)->get(route('informes.show', [$informe, 'medico' => 'Dr. Par']))
            ->assertOk()
            ->assertSee('30 fila(s)')
            ->assertDontSee('DE OTRO INFORME');
    }

    public function test_el_comando_crea_un_informe_del_usuario(): void
    {
        $user = User::factory()->create(['email' => 'ana@hospital.test']);
        $path = $this->excelPath($this->filasDePrueba());

        $this->artisan('pagos:unificar', ['--file' => $path])->assertFailed();
        $this->assertSame(0, PaymentReport::count());

        $this->artisan('pagos:unificar', ['--file' => $path, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, PaymentReport::count());
        $this->assertSame(0, Payment::count());

        $this->artisan('pagos:unificar', ['--file' => $path, '--user' => 'Ana@Hospital.test'])->assertSuccessful();

        $informe = PaymentReport::sole();
        $this->assertTrue($informe->user->is($user));
        $this->assertSame(1, $informe->unificados);
        $this->assertSame(basename($path), $informe->nombre_archivo);
    }

    public function test_el_comando_de_purga(): void
    {
        Payment::create(['paciente' => 'HUERFANO']);

        $this->artisan('informes:purgar')->assertSuccessful();

        $this->assertSame(0, Payment::count());
    }
}
