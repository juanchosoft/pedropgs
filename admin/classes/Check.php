<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Check
{

    public function __construct()
    {
    }

    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();


        $q = "SELECT tbl_check.*, tbl_unidades.id as tbl_unidad_id, tbl_unidades.nombre AS hoa, tec_usuarios.nombre AS employee
            FROM " . $db->getTable('tbl_check') . " 
            INNER JOIN " . $db->getTable('tbl_unidades') . " ON tbl_check.tbl_unidad_id = tbl_unidades.id
            INNER JOIN " . $db->getTable('tec_usuarios') . "  ON tbl_check.tec_usuario_id = tec_usuarios.id ORDER BY tbl_check.tbl_unidad_id ASC LIMIT 100";

        if ($id > 0) {
            $q = "SELECT tbl_check.*, tbl_unidades.id as tbl_unidad_id, tbl_unidades.nombre AS hoa, tec_usuarios.nombre AS employee
            FROM " . $db->getTable('tbl_check') . " 
            INNER JOIN " . $db->getTable('tbl_unidades') . " ON tbl_check.tbl_unidad_id = tbl_unidades.id
            INNER JOIN " . $db->getTable('tec_usuarios') . "  ON tbl_check.tec_usuario_id = tec_usuarios.id WHERE  tbl_check.id = " . $id;
        }

        $result = $pdo->query($q);
        $arr = array();
        if ($result) {
            foreach ($result as $valor) {
                $arr[] = $valor;
            }
            $arrjson = array('output' => array('valid' => true, 'response' => $arr));
        } else {
            $arrjson = Util::error_no_result();
        }
        $db->closeConect();
        return $arrjson;
    }

    public static function save($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $tbl_unidad_id = isset($rqst['tbl_unidad_id']) ? ($rqst['tbl_unidad_id']) : '';
        $tec_usuario_id = $_SESSION['session_user']['id'];
        $overall_appearance = isset($rqst['overall_appearance']) ? ($rqst['overall_appearance']) : '';
        $condition_walls = isset($rqst['condition_walls']) ? ($rqst['condition_walls']) : '';
        $condition_paint = isset($rqst['condition_paint']) ? ($rqst['condition_paint']) : '';
        $wall_lights = isset($rqst['wall_lights']) ? ($rqst['wall_lights']) : '';
        $ceiling_lights = isset($rqst['ceiling_lights']) ? ($rqst['ceiling_lights']) : '';
        $carpet = isset($rqst['carpet']) ? ($rqst['carpet']) : '';
        $door_clean = isset($rqst['door_clean']) ? ($rqst['door_clean']) : '';
        $spot_lights = isset($rqst['spot_lights']) ? ($rqst['spot_lights']) : '';
        $lit = isset($rqst['lit']) ? ($rqst['lit']) : '';
        $extinguisher_charged = isset($rqst['extinguisher_charged']) ? ($rqst['extinguisher_charged']) : '';
        $shute_door = isset($rqst['shute_door']) ? ($rqst['shute_door']) : '';
        $free_storage = isset($rqst['free_storage']) ? ($rqst['free_storage']) : '';
        $hazardous_materials = isset($rqst['hazardous_materials']) ? ($rqst['hazardous_materials']) : '';
        $inspection_visible1 = isset($rqst['inspection_visible1']) ? ($rqst['inspection_visible1']) : '';
        $supplies_stored = isset($rqst['supplies_stored']) ? ($rqst['supplies_stored']) : '';
        $debris = isset($rqst['debris']) ? ($rqst['debris']) : '';
        $chemical_labeled = isset($rqst['chemical_labeled']) ? ($rqst['chemical_labeled']) : '';
        $paint_labeled = isset($rqst['paint_labeled']) ? ($rqst['paint_labeled']) : '';
        $fire_charged = isset($rqst['fire_charged']) ? ($rqst['fire_charged']) : '';
        $ladders_stored = isset($rqst['ladders_stored']) ? ($rqst['ladders_stored']) : '';
        $debrisj = isset($rqst['debrisj']) ? ($rqst['debrisj']) : '';
        $inventory_labeled = isset($rqst['inventory_labeled']) ? ($rqst['inventory_labeled']) : '';
        $equipment_tested = isset($rqst['equipment_tested']) ? ($rqst['equipment_tested']) : '';
        $inspectionf = isset($rqst['inspectionf']) ? ($rqst['inspectionf']) : '';
        $storagef = isset($rqst['storagef']) ? ($rqst['storagef']) : '';
        $hazardousf = isset($rqst['hazardousf']) ? ($rqst['hazardousf']) : '';
        $chargedf = isset($rqst['chargedf']) ? ($rqst['chargedf']) : '';
        $elevators_working = isset($rqst['elevators_working']) ? ($rqst['elevators_working']) : '';
        $doors_clean = isset($rqst['debris']) ? ($rqst['debris']) : '';
        $floors_clean = isset($rqst['floors_clean']) ? ($rqst['floors_clean']) : '';
        $permit_posted = isset($rqst['permit_posted']) ? ($rqst['permit_posted']) : '';
        $call_working = isset($rqst['call_working']) ? ($rqst['call_working']) : '';
        $pump_operational = isset($rqst['pump_operational']) ? ($rqst['pump_operational']) : '';
        $pump_functioning = isset($rqst['pump_functioning']) ? ($rqst['pump_functioning']) : '';
        $check_gauge = isset($rqst['check_gauge']) ? ($rqst['check_gauge']) : '';
        $visiblep = isset($rqst['visiblep']) ? ($rqst['visiblep']) : '';
        $storagep = isset($rqst['storagep']) ? ($rqst['storagep']) : '';
        $materialp = isset($rqst['materialp']) ? ($rqst['materialp']) : '';
        $extinguisherp = isset($rqst['extinguisherp']) ? ($rqst['extinguisherp']) : '';
        $tested = isset($rqst['tested']) ? ($rqst['tested']) : '';
        $fuel_level = isset($rqst['fuel_level']) ? ($rqst['fuel_level']) : '';
        $materialsd = isset($rqst['materialsd']) ? ($rqst['materialsd']) : '';
        $storaged = isset($rqst['storaged']) ? ($rqst['storaged']) : '';
        $inspectiond = isset($rqst['inspectiond']) ? ($rqst['inspectiond']) : '';
        $extinguisherd = isset($rqst['extinguisherd']) ? ($rqst['extinguisherd']) : '';
        $ac_units = isset($rqst['ac_units']) ? ($rqst['ac_units']) : '';
        $acfilters = isset($rqst['acfilters']) ? ($rqst['acfilters']) : '';
        $thermostat_properly = isset($rqst['thermostat_properly']) ? ($rqst['thermostat_properly']) : '';
        $interior_clear = isset($rqst['interior_clear']) ? ($rqst['interior_clear']) : '';
        $maintenance_schedule = isset($rqst['maintenance_schedule']) ? ($rqst['maintenance_schedule']) : '';
        $visibleac = isset($rqst['visibleac']) ? ($rqst['visibleac']) : '';
        $debrisac = isset($rqst['debrisac']) ? ($rqst['debrisac']) : '';
        $no_water = isset($rqst['no_water']) ? ($rqst['no_water']) : '';
        $compactor_functioning = isset($rqst['compactor_functioning']) ? ($rqst['compactor_functioning']) : '';
        $elevator = isset($rqst['elevator']) ? ($rqst['elevator']) : '';
        $dumpster_correctly = isset($rqst['dumpster_correctly']) ? ($rqst['dumpster_correctly']) : '';
        $inspection_visible = isset($rqst['inspection_visible']) ? ($rqst['inspection_visible']) : '';
        $materialst = isset($rqst['materialst']) ? ($rqst['materialst']) : '';
        $debrist = isset($rqst['debrist']) ? ($rqst['debrist']) : '';
        $doors_secure = isset($rqst['doors_secure']) ? ($rqst['doors_secure']) : '';
        $inspection_shingles = isset($rqst['inspection_shingles']) ? ($rqst['inspection_shingles']) : '';
        $drains_clear = isset($rqst['drains_clear']) ? ($rqst['drains_clear']) : '';
        $debrisr = isset($rqst['debrisr']) ? ($rqst['debrisr']) : '';
        $observations = isset($rqst['observations']) ? ($rqst['observations']) : '';        


        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tbl_check') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tbl_check');
                $arrfieldscomma = array(
                    'tbl_unidad_id' => $tbl_unidad_id,
                    'tec_usuario_id' => $tec_usuario_id,
                    'overall_appearance' => $overall_appearance,
                    'condition_walls' => $condition_walls,
                    'condition_paint' => $condition_paint,
                    'wall_lights' => $wall_lights,
                    'ceiling_lights' => $ceiling_lights,
                    'carpet' => $carpet,
                    'door_clean' => $door_clean,
                    'spot_lights' => $spot_lights,
                    'lit' => $lit,
                    'extinguisher_charged' => $extinguisher_charged,
                    'shute_door' => $shute_door,
                    'free_storage' => $free_storage,
                    'hazardous_materials' => $hazardous_materials,
                    'inspection_visible1' => $inspection_visible1,
                    'supplies_stored' => $supplies_stored,
                    'debris' => $debris,
                    'chemical_labeled' => $chemical_labeled,
                    'paint_labeled' => $paint_labeled,
                    'fire_charged' => $fire_charged,
                    'ladders_stored' => $ladders_stored,
                    'debrisj' => $debrisj,
                    'inventory_labeled' => $inventory_labeled,
                    'equipment_tested' => $equipment_tested,
                    'inspectionf' => $inspectionf,
                    'storagef' => $storagef,
                    'hazardousf' => $hazardousf,
                    'chargedf' => $chargedf,
                    'elevators_working' => $elevators_working,
                    'doors_clean' => $doors_clean,
                    'floors_clean' => $floors_clean,
                    'permit_posted' => $permit_posted,
                    'call_working' => $call_working,
                    'pump_operational' => $pump_operational,
                    'pump_functioning' => $pump_functioning,
                    'check_gauge' => $check_gauge,
                    'visiblep' => $visiblep,
                    'storagep' => $storagep,
                    'materialp' => $materialp,
                    'extinguisherp' => $extinguisherp,
                    'tested' => $tested,
                    'fuel_level' => $fuel_level,
                    'materialsd' => $materialsd,
                    'storaged' => $storaged,
                    'inspectiond' => $inspectiond,
                    'extinguisherd' => $extinguisherd,
                    'ac_units' => $ac_units,
                    'acfilters' => $acfilters,
                    'thermostat_properly' => $thermostat_properly,
                    'interior_clear' => $interior_clear,
                    'maintenance_schedule' => $maintenance_schedule,
                    'visibleac' => $visibleac,
                    'no_water' => $no_water,
                    'compactor_functioning' => $compactor_functioning,
                    'elevator' => $elevator,
                    'dumpster_correctly' => $dumpster_correctly,
                    'inspection_visible' => $inspection_visible,
                    'materialst' => $materialst,
                    'debrist' => $debrist,
                    'doors_secure' => $doors_secure,
                    'inspection_shingles' => $inspection_shingles,
                    'drains_clear' => $drains_clear,
                    'debrisr' => $debrisr,
                    'observations' => $observations,

                    

                );
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);
                if (!$result) {
                    $arrjson = Util::error_general('Updating unit data');
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id));
                }
            }
        } else {
            if ($tbl_unidad_id != "") {
                $q = "INSERT INTO " . $db->getTable('tbl_check') . " (dtcreate, tbl_unidad_id, tec_usuario_id, overall_appearance, condition_walls, condition_paint, wall_lights, ceiling_lights, carpet, door_clean, spot_lights, lit, extinguisher_charged, shute_door, free_storage, hazardous_materials, inspection_visible1, supplies_stored, debris, chemical_labeled, paint_labeled, fire_charged, ladders_stored, debrisj, inventory_labeled, equipment_tested, inspectionf, storagef, hazardousf, chargedf, elevators_working, doors_clean, floors_clean, permit_posted, call_working, pump_operational, pump_functioning, check_gauge, visiblep, storagep, materialp, extinguisherp, tested, fuel_level, materialsd, storaged, inspectiond, extinguisherd, ac_units, acfilters, thermostat_properly, interior_clear, maintenance_schedule, visibleac, no_water, compactor_functioning, elevator, dumpster_correctly, inspection_visible, materialst, debrist, doors_secure, inspection_shingles, drains_clear, debrisr, observations) 
                                    VALUES (" . Util::date_now_server() . ", :tbl_unidad_id, :tec_usuario_id, :overall_appearance, :condition_walls, :condition_paint, :wall_lights, :ceiling_lights, :carpet, :door_clean, :spot_lights, :lit, :extinguisher_charged, :shute_door, :free_storage, :hazardous_materials, :inspection_visible1, :supplies_stored, :debris, :chemical_labeled, :paint_labeled, :fire_charged, :ladders_stored, :debrisj, :inventory_labeled, :equipment_tested, :inspectionf, :storagef, :hazardousf, :chargedf, :elevators_working, :doors_clean, :floors_clean, :permit_posted, :call_working, :pump_operational, :pump_functioning, :check_gauge, :visiblep, :storagep, :materialp, :extinguisherp, :tested, :fuel_level, :materialsd, :storaged, :inspectiond, :extinguisherd, :ac_units, :acfilters, :thermostat_properly, :interior_clear, :maintenance_schedule, :visibleac, :no_water, :compactor_functioning, :elevator, :dumpster_correctly, :inspection_visible, :materialst, :debrist, :doors_secure, :inspection_shingles, :drains_clear, :debrisr, :observations)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    'tbl_unidad_id' => $tbl_unidad_id,
                    'tec_usuario_id' => $tec_usuario_id,
                    'overall_appearance' => $overall_appearance,
                    'condition_walls' => $condition_walls,
                    'condition_paint' => $condition_paint,
                    'wall_lights' => $wall_lights,
                    'ceiling_lights' => $ceiling_lights,
                    'carpet' => $carpet,
                    'door_clean' => $door_clean,
                    'spot_lights' => $spot_lights,
                    'lit' => $lit,
                    'extinguisher_charged' => $extinguisher_charged,
                    'shute_door' => $shute_door,
                    'free_storage' => $free_storage,
                    'hazardous_materials' => $hazardous_materials,
                    'inspection_visible1' => $inspection_visible1,
                    'supplies_stored' => $supplies_stored,
                    'debris' => $debris,
                    'chemical_labeled' => $chemical_labeled,
                    'paint_labeled' => $paint_labeled,
                    'fire_charged' => $fire_charged,
                    'ladders_stored' => $ladders_stored,
                    'debrisj' => $debrisj,
                    'inventory_labeled' => $inventory_labeled,
                    'equipment_tested' => $equipment_tested,
                    'inspectionf' => $inspectionf,
                    'storagef' => $storagef,
                    'hazardousf' => $hazardousf,
                    'chargedf' => $chargedf,
                    'elevators_working' => $elevators_working,
                    'doors_clean' => $doors_clean,
                    'floors_clean' => $floors_clean,
                    'permit_posted' => $permit_posted,
                    'call_working' => $call_working,
                    'pump_operational' => $pump_operational,
                    'pump_functioning' => $pump_functioning,
                    'check_gauge' => $check_gauge,
                    'visiblep' => $visiblep,
                    'storagep' => $storagep,
                    'materialp' => $materialp,
                    'extinguisherp' => $extinguisherp,
                    'tested' => $tested,
                    'fuel_level' => $fuel_level,
                    'materialsd' => $materialsd,
                    'storaged' => $storaged,
                    'inspectiond' => $inspectiond,
                    'extinguisherd' => $extinguisherd,
                    'ac_units' => $ac_units,
                    'acfilters' => $acfilters,
                    'thermostat_properly' => $thermostat_properly,
                    'interior_clear' => $interior_clear,
                    'maintenance_schedule' => $maintenance_schedule,
                    'visibleac' => $visibleac,
                    'no_water' => $no_water,
                    'compactor_functioning' => $compactor_functioning,
                    'elevator' => $elevator,
                    'dumpster_correctly' => $dumpster_correctly,
                    'inspection_visible' => $inspection_visible,
                    'materialst' => $materialst,
                    'debrist' => $debrist,
                    'doors_secure' => $doors_secure,
                    'inspection_shingles' => $inspection_shingles,
                    'drains_clear' => $drains_clear,
                    'debrisr' => $debrisr,
                    'observations' => $observations,
                );


                if ($result->execute($arrparam)) {
                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                } else {
                    $arrjson = Util::error_general();
                }
            } else {
                $arrjson = Util::error_missing_data();
            }
        }
        $db->closeConect();
        return $arrjson;
    }

    public static function delete($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "DELETE FROM " . $db->getTable('tbl_check') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            Util::trace_log_error($rqst, 'Unidades::delete ' . $id, $pdo->errorInfo());
            $arrjson = Util::error_generaldelete();
        }
        $db->closeConect();
        return $arrjson;
    }

    public static function enable($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        Util::trace_log($rqst, 'Unidades::enable ' . $id);
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "UPDATE " . $db->getTable('tbl_check') . " SET enable = '$enable' WHERE id = " . $id;
        $result = $pdo->query($q);
        $arr = array();
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'response' => $arr, 'error' => $pdo->errorInfo()));
        } else {
            $arrjson = Util::error_general($pdo->errorInfo());
        }
        $db->closeConect();
        return $arrjson;
    }

    public static function search($rqst)
    {
        $search = isset($rqst['search']) ? ($rqst['search']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tbl_check') . " 
        WHERE tbl_unidad_id  LIKE '%$search%'  OR
            overall_appearance  LIKE '%$search%' LIMIT 200 ";

        $result = $pdo->query($q);
        $arr = array();
        if ($result) {
            foreach ($result as $valor) {
                $arr[] = $valor;
            }
            $arrjson = array('output' => array('valid' => true, 'response' => $arr));
        } else {
            $arrjson = Util::error_no_result();
        }
        $db->closeConect();
        return $arrjson;
    }
}
