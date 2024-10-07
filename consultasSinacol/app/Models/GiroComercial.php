<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Kalnoy\Nestedset\NodeTrait;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;


/**
 * @property mixed parent_id
 */
class GiroComercial extends Model implements AuditableContract
{
    use SoftDeletes;
    use NodeTrait;
    use Auditable;
    use \App\Traits\CambiarEventoAudit;

    protected $table = 'giro_comerciales';
    public $incrementing = false;
    protected $guarded = ['id', 'created_at', 'updated_at',];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }

    /**
     * Relación con industria
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function industria()
    {
        return $this->belongsTo(Industria::class);
    }

    /**
     * Solicitudes que pertenecen a esta industria
     */
    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class);
    }

    /**
     * Ordena y genera las dependencias de cc mediante su código de definición
     *
     * @param int $niveles Indica cuántos niveles tiene el código, cuántos pares de dígitos hay en la cadena.
     */
    public static function reordenar($niveles = 3)
    {

        Log::debug("Entra al reordenamiento");

        //Bandera para debuguear el resultado del metodo.
        $dbg = false;

        $result = self::orderBy('codigo')->get()->all();

        $mapCcostos = [];

        //Aqui almacenamos el codigo o clave de centro de costos anterior
        $ccant = "";

        $c = 0;

        if($dbg) echo "<table border=1>";

        foreach ($result as $cc) {
            $c++;

            //Nuevo código de centro de costos de esta iteración
            $ncc = $cc->codigo;

            $dn1 = null; $dn2 = null; $dn3=null; $dn4=null; $dn5=null;

            //Extraemos los pares de los nodos y los asignamos a variables de nivel n1 a n5
            // estos son los niveles del nodo actual
            if($niveles >= 1) $n1 = substr($ncc,0,2);
            if($niveles >= 2) $n2 = substr($ncc,2,2);
            if($niveles >= 3) $n3 = substr($ncc,4,2);
            if($niveles >= 4) $n4 = substr($ncc,6,2);
            if($niveles >= 5) $n5 = substr($ncc,8,2);

            // Si el código de centro de costos anterior es diferente de nada (quiere decir que no es el primero)
            if($ccant != "")
            {

                $dif = $ncc - $ccant;

                //Extraemos los pares de los nodos y los asignamos a variables de nivel a1 a a5
                // estos son los niveles del nodo anterior
                if($niveles >= 1) $a1 = substr($ccant,0,2);
                if($niveles >= 2) $a2 = substr($ccant,2,2);
                if($niveles >= 3) $a3 = substr($ccant,4,2);
                if($niveles >= 4) $a4 = substr($ccant,6,2);
                if($niveles >= 5) $a5 = substr($ccant,8,2);

                // Hacemos la diferencia entre el fragmento de nodo del nivel actual con el del nivel anterior
                // que le corresponde por posición y lo asignamos variables de la diferencia de niveles de dn1 a dn5
                if($niveles >= 1) $dn1 = $n1 - $a1;
                if($niveles >= 2) $dn2 = $n2 - $a2;
                if($niveles >= 3) $dn3 = $n3 - $a3;
                if($niveles >= 4) $dn4 = $n4 - $a4;
                if($niveles >= 5) $dn5 = $n5 - $a5;

                $nivel = 0;
                $dn = 0;
                $nact = 0;
                $npad = 0;

                if($dif > 0)
                {
                    if($niveles >= 1 && $dn1 > 0 && $dn1 < 99)
                    {
                        $nivel = 1;
                        $dn = $dn1;
                        $nact = 1;
                        $mapCcostos[1] = $cc;
                        $an = $a1;
                        if($niveles >= 2) if(isset($mapCcostos[2])) unset($mapCcostos[2]);
                        if($niveles >= 3) if(isset($mapCcostos[3])) unset($mapCcostos[3]);
                        if($niveles >= 4) if(isset($mapCcostos[4])) unset($mapCcostos[4]);
                        if($niveles >= 5) if(isset($mapCcostos[5])) unset($mapCcostos[5]);

                        if($dbg) echo "<tr><td>$c</td><td>".$cc->codigo."</td><td></td><td></td><td></td><td></td><td>".$cc->nombre."</td><td>$nivel</td>";
                    }
                    else if($niveles >= 2 && $dn2 > 0 && $dn2 < 99)
                    {
                        $nivel = 2; $dn = $dn2; $dt="n2----";
                        $nact = 2;
                        $mapCcostos[2] = $cc;
                        $an = $a2;
                        if($niveles >= 3) if(isset($mapCcostos[3])) unset($mapCcostos[3]);
                        if($niveles >= 4) if(isset($mapCcostos[4])) unset($mapCcostos[4]);
                        if($niveles >= 5) if(isset($mapCcostos[5])) unset($mapCcostos[5]);

                        if($dbg) echo "<tr><td>$c</td><td></td><td>".$cc->codigo."</td><td></td><td></td><td></td><td>".$cc->nombre."</td><td>$nivel</td>";

                    }
                    else if($niveles >= 3 && $dn3 > 0 && $dn3 < 99)
                    {
                        $nivel = 3; $dn = $dn3; $dt="n3----";
                        $nact = 3;
                        $mapCcostos[3] = $cc;
                        $an = $a3;
                        if($niveles >= 4) if(isset($mapCcostos[4])) unset($mapCcostos[4]);
                        if($niveles >= 5) if(isset($mapCcostos[5])) unset($mapCcostos[5]);

                        if($dbg) echo "<tr><td>$c</td><td></td><td></td><td>".$cc->codigo."</td><td></td><td></td><td>".$cc->nombre."</td><td>$nivel</td>";
                    }
                    else if($niveles >= 4 && $dn4 > 0 && $dn4 < 99)
                    {
                        $nivel = 4; $dn = $dn4; $dt="n4----";
                        $nact = 4;
                        $mapCcostos[4] = $cc;
                        $an = $a4;
                        if($niveles >= 5) if(isset($mapCcostos[5])) unset($mapCcostos[5]);

                        if($dbg) echo "<tr><td>$c</td><td></td><td></td><td></td><td>".$cc->codigo."</td><td></td><td>".$cc->nombre."</td><td>$nivel</td>";
                    }
                    else if($niveles >= 5 && $dn5 > 0 && $dn5 < 99)
                    {
                        $nivel = 5; $dn = $dn5; $dt="n5----";
                        $nact = 5;
                        $mapCcostos[5] = $cc;
                        $an = $a5;
                        if($dbg) echo "<tr><td>$c</td><td></td><td></td><td></td><td></td><td>".$cc->codigo."</td><td>".$cc->nombre."</td><td>$nivel</td>";
                    }

                    if($npad == 0)
                    {
                        $npad = $dn;
                    }
                    elseif($nact > $npad)
                    {
                        $npad = $nact;
                    }
                    if(($nivel -1 ) < 0)
                    {
                        $npadre = 0;
                    }
                    else
                    {
                        $npadre = $nivel -1;
                        while(!isset($mapCcostos[$npadre]))
                        {
                            $npadre = $npadre -1;
                        }

                        if($mapCcostos[$npadre] == $cc)
                        {
                            $npadre = $npadre -1;
                            while(!isset($mapCcostos[$npadre]))
                            {
                                $npadre = $npadre -1;
                            }
                        }
                    }
                    if($dbg) echo "<td>".$mapCcostos[$npadre]->nombre."</td><td>".$mapCcostos[$npadre]->ccosto."</td><td>$dn1</td><td>$dn2</td><td>$dn3</td><td>$dn4</td><td>$dn5</td></tr>";

                    $cc->parent_id = $mapCcostos[$npadre]->id;
                    //echo "El padre de". $cc->codigo. " Es ". $mapCcostos[$npadre]->codigo."\n";
                    $cc->save();
                }

                $ccant = $cc->codigo;
                $cc->save();
            }
            else
            {
                //Quiere decir que es el primer codigo que se evalúa y se asigna tambien al codigo anterior
                //como es el primero se ingresa al mapa de claves en el nivel 0 y guardamos
                $ccant = $cc->codigo;
                $mapCcostos[0] = $cc;
                $cc->save();
            }

        }
        if($dbg) echo "</table>";
    }
    /**
     * Funcion para asociar con modelo Ambitos con belongsTo
     * * Utilizando belongsTo para relacion uno a muchos
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function ambito(){
      return $this->belongsTo('App\Ambito');
    }

    public static function CambiarAmbito($id, $ambito_id){
        $giro = GiroComercial::find($id);
        $giro->ambito_id = $ambito_id;
        $giro->save();

        $arreglo []= ["id" => $giro->id , "ambito_id" => $giro->ambito_id,"nombre" => $giro->ambito->nombre];
        $arreglo = self::CambiarAmbitoChildrens($giro,$arreglo);
        $arreglo = self::CambiarAmbitoParents($giro, $arreglo);
        return $arreglo;
    }

    public static function CambiarAmbitoParents(GiroComercial $giro, $arreglo){
        #validamos si tiene padre
        if($giro->parent_id != "" && $giro->parent_id != null){
            # obtenemos el padre
            $padre = GiroComercial::find($giro->parent_id);
            #obtenemos a todos los hijos
            $hijos = GiroComercial::where("parent_id",$padre->id)->get();
            #declaramos bandera para validar a los hijos
            #Si un hijo es diferente al nuevo ambito del giro, automaticamente cambia a mixto
            #Si todas son iguales colocamos el nuevo ambito tambien al padre
            $bandera = true;

            #Recorremos los hijos
            foreach($hijos as $hijo){
                if($hijo->ambito_id != $giro->ambito_id){
                    $bandera=false;
                }
            }
            if($bandera){
                $padre->ambito_id = $giro->ambito_id;
                $padre->save();
                //dump($padre);
            }else{
                $padre->ambito_id = 3;
                $padre->save();
                //dump($padre);
            }
            #Agregamos el padre al arreglo de la respuesta
            $arreglo []= ["id" => $padre->id , "ambito_id" => $padre->ambito_id,"nombre" => $padre->ambito->nombre];
            #Llamamos a la misma función para verificar que no tenga padre el padre y si lo tiene modificarlo
            $arreglo = self::CambiarAmbitoParents($padre,$arreglo);
            return $arreglo;
        }else{
            return $arreglo;
        }
    }
    private static function CambiarAmbitoChildrens($giro,$arreglo){
        //buscamos los hijos
        $hijos = GiroComercial::where("parent_id",$giro->id)->get();
        // Recorremos los hijos
        foreach($hijos as $hijo){
            #Modificamos el ambito del hijo
            $hijo->ambito_id = $giro->ambito_id;
            $hijo->save();
            #Agregamos el hijo al arreglo de la respuesta
            $arreglo []= ["id" => $hijo->id , "ambito_id" => $hijo->ambito_id,"nombre" => $hijo->ambito->nombre];
            #Llamamos a la misma función para verificar que no tenga hijos el giro y si los tiene modificarlos
            $arreglo = self::CambiarAmbitoChildrens($hijo,$arreglo);
        }
        return $arreglo;
    }


}
