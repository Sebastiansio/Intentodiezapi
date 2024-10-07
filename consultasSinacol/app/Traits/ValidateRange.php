<?php

namespace App\Traits;
use App\TipoParte;
use DateTime;
use DateTimeZone;

trait ValidateRange
{
    /**
     * Funcion para validar los rangos de tiempo
     * @param time $start_time1
     * @param time $end_time1
     * @param time $start_time2
     * @param time $end_time2
     * @return bool
     * @throws Exception
     */
    function rangesNotOverlapOpen($start_time1,$end_time1,$start_time2,$end_time2)
    {
      $utc = new DateTimeZone('UTC');

      $start1 = new DateTime($start_time1,$utc);
      $end1 = new DateTime($end_time1,$utc);
      if($end1 < $start1)
        throw new Exception('Range is negative.');

      $start2 = new DateTime($start_time2,$utc);
      $end2 = new DateTime($end_time2,$utc);
      if($end2 < $start2)
        throw new Exception('Range is negative.');

      return ($end1 <= $start2) || ($end2 <= $start1);
    }

}
