<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Jobs\ProcessCitadoRowJob;
use Illuminate\Support\Str;

class CitadoImport implements ToCollection, WithHeadingRow
{
    /**
    * @param Collection $rows
    */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) 
        {
            // Limpiamos las claves antes de enviarlas al Job
            $cleanedData = $this->cleanKeys($row->toArray());
            
            // Despachamos el job con los datos ya limpios
            ProcessCitadoRowJob::dispatch($cleanedData);
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