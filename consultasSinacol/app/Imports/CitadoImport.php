<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Jobs\ProcessCitadoRowJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CitadoImport implements ToCollection, WithHeadingRow
{
    protected $solicitante = [];
    protected $common = [];

    /**
     * Accept solicitante and common request data so each row can be processed
     * in the context of the common applicant data.
     */
    public function __construct(array $solicitante = [], array $common = [])
    {
        $this->solicitante = $solicitante;
        $this->common = $common;
    }
    /**
    * @param Collection $rows
    */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) 
        {
            // Limpiamos las claves antes de enviarlas al Job
            $cleanedData = $this->cleanKeys($row->toArray());

            // Sanitizar/normalizar valores individuales
            $cleanedData = $this->sanitizeRow($cleanedData);

            // Adjuntamos los datos comunes a nivel superior (para compatibilidad
            // con los servicios que esperan keys como 'fecha_conflicto' en top-level)
            // y mantenemos 'solicitante' anidado bajo su propia key.
            $payload = array_merge($this->common, $cleanedData);
            $payload['solicitante'] = $this->solicitante;

            // Despachamos el job con los datos ya limpios y el solicitante
            try {
                ProcessCitadoRowJob::dispatch($payload);
            } catch (\Exception $e) {
                Log::error('CitadoImport: fallo al despachar job', ['error' => $e->getMessage(), 'row' => $payload]);
                // rethrow for visibility
                throw $e;
            }
        }
    }

    /**
     * Sanitiza/normaliza una fila ya con claves limpias.
     * - Convierte encoding a UTF-8 cuando es necesario
     * - Quita control chars, colapsa espacios
     * - Normaliza fechas a Y-m-d
     * - Limpia números que vienen como floats ("12345.0")
     */
    private function sanitizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            // Normalizar nulls
            if ($v === null) {
                $out[$k] = null;
                continue;
            }

            // Fields that look like dates
            if (strpos($k, 'fecha') !== false || strpos($k, 'nacimiento') !== false) {
                $out[$k] = $this->parseDateValue($v);
                continue;
            }

            // Numeric-ish fields
            if (in_array($k, ['telefono','cp','num_ext','num_int','nss','salario']) || preg_match('/^(\d+)$/', (string)$v)) {
                // Remove trailing .0 from floats exported by Excel
                $val = (string)$v;
                $val = preg_replace('/\.0+$/', '', $val);
                // If it's numeric, cast accordingly
                if (is_numeric($val)) {
                    // salario puede ser decimal
                    if ($k === 'salario' || strpos($k, 'monto') !== false) {
                        $out[$k] = (float)$val;
                    } else {
                        // keep as string to preserve leading zeros in CP/telefono
                        $out[$k] = preg_replace('/[^0-9\+\-]/','', $val);
                    }
                } else {
                    $out[$k] = $this->normalizeString($val);
                }
                continue;
            }

            // Text fields: normalize encoding and whitespace
            $out[$k] = $this->normalizeString($v);
        }

        return $out;
    }

    private function normalizeString($value)
    {
        if ($value === null) return null;
        $s = (string)$value;
        // Remove control chars (including CR/LF) and replace with single space
        $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
        // Trim and collapse multiple spaces
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s);

        // Ensure UTF-8
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'WINDOWS-1252');
        }

        return $s;
    }

    private function parseDateValue($value)
    {
        if (empty($value)) return null;
        $val = trim((string)$value);
        $formats = ['d/m/Y','d-m-Y','Y-m-d','Y/m/d'];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $val);
                if ($dt) return $dt->toDateString();
            } catch (\Exception $e) {
                // continue
            }
        }

        // try generic parse
        try {
            $dt = new Carbon($val);
            return $dt->toDateString();
        } catch (\Exception $e) {
            Log::warning('CitadoImport::parseDateValue no pudo parsear fecha', ['value' => $value]);
            return null;
        }
    }

    /**
     * Limpia los encabezados del CSV.
     * Quita los asteriscos (*) y convierte a snake_case.
     * ej: "Primer Apellido*" -> "primer_apellido"
     */
    private function cleanKeys(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            // 1. Quita el asterisco y espacios
            $newKey = trim(str_replace('*', '', $key));
            
            // 2. Convierte a snake_case (ej. "Primer Apellido" -> "primer_apellido")
            $newKey = Str::snake($newKey);
            
            $cleaned[$newKey] = $value;
        }
        return $cleaned;
    }
}