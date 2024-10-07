<?php
namespace App\Traits;


trait Jerarquizable
{


    public function jerarquiza($nodos, $niveles = 3)
    {
        $mapCcostos = [];

        //Aqui almacenamos el codigo o clave de centro de costos anterior
        $ccant = "";

        $c = 0;

        $dbg = false;
        if($dbg) echo "<table border=1>";

        foreach ($nodos as $cc) {
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

}
