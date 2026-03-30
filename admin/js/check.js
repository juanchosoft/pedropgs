$(document).on('ready', init);
var q;

function init() {
    q = {};
    // No permitir cerrar el modal, click afuera
    $("#myModal").modal({ backdrop: "static", keyboard: false });

}
var return_page = 'check_report_list.php';
var CHECK = {

    validateData: function() {
        var bValid = true;
        var msj = "You need to add all obligatory data";
        if (
            $("#tbl_unidad_id").val() == "select" ||
            $("#overall_appearance").val() == "select" ||
            $("#condition_walls").val() == "select" ||
            $("#condition_paint").val() == "select" ||
            $("#wall_lights").val() == "select" ||
            $("#ceiling_lights").val() == "select" ||
            $("#carpet").val() == "select" ||
            $("#door_clean").val() == "select" ||
            $("#spot_lights").val() == "select" ||
            $("#lit").val() == "select" ||
            $("#extinguisher_charged").val() == "select" ||
            $("#shute_door").val() == "select" ||
            $("#free_storage").val() == "select" ||
            $("#hazardous_materials").val() == "select" ||
            $("#debris").val() == "select" ||
            $("#inspection_visible1").val() == "select" ||
            $("#supplies_stored").val() == "select" ||
            $("#chemical_labeled").val() == "select" ||
            $("#paint_labeled").val() == "select" ||
            $("#fire_charged").val() == "select" ||
            $("#ladders_stored").val() == "select" ||
            $("#debrisj").val() == "select" ||
            $("#inventory_labeled").val() == "select" ||
            $("#equipment_tested").val() == "select" ||
            $("#inspectionf").val() == "select" ||
            $("#storagef").val() == "select" ||
            $("#hazardousf").val() == "select" ||
            $("#chargedf").val() == "select" ||
            $("#elevators_working").val() == "select" ||
            $("doors_clean").val() == "select" ||
            $("#floors_clean").val() == "select" ||
            $("#permit_posted").val() == "select" ||
            $("#call_working").val() == "select" ||
            $("#pump_operational").val() == "select" ||
            $("#pump_functioning").val() == "select" ||
            $("#check_gauge").val() == "select" ||
            $("#visiblep").val() == "select" ||
            $("#storagep").val() == "select" ||
            $("#materialp").val() == "select" ||
            $("#extinguisherp").val() == "select" ||
            $("#tested").val() == "select" ||
            $("#fuel_level").val() == "select" ||
            $("#materialsd").val() == "select" ||
            $("#storaged").val() == "select" ||
            $("#inspectiond").val() == "select" ||
            $("#extinguisherd").val() == "select" ||
            $("#ac_units").val() == "select" ||
            $("#acfilters").val() == "select" ||
            $("#thermostat_properly").val() == "select" ||
            $("#interior_clear").val() == "select" ||
            $("#maintenance_schedule").val() == "select" ||
            $("#visibleac").val() == "select" ||
            $("#debrisac").val() == "select" ||
            $("#no_water").val() == "select" ||
            $("#compactor_functioning").val() == "select" ||
            $("#elevator").val() == "select" ||
            $("#dumpster_correctly").val() == "select" ||
            $("#inspection_visible").val() == "select" ||
            $("#materialst").val() == "select" ||
            $("#debrist").val() == "select" ||
            $("#doors_secure").val() == "select" ||
            $("#inspection_shingles").val() == "select" ||
            $("#debrisr").val() == "select" ||
            $("#observations").val() == "" ||
            $("#drains_clear").val() == ""          
        ) {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }
        if (bValid) {
            CHECK.savedata();
        }
    },
   
    
    successMessage: function() {
        swal("Information saved successfully ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 1000);
    },

    savedata: function() {
        q = {};
        q.op = "pms_check_save";
        q.id = $("#id").val();
        q.tbl_unidad_id = $("#tbl_unidad_id").val();
        q.overall_appearance = $("#overall_appearance").val();
        q.condition_walls = $("#condition_walls").val();
        q.condition_paint = $("#condition_paint").val();
        q.wall_lights = $("#wall_lights").val();
        q.ceiling_lights = $("#ceiling_lights").val();
        q.carpet = $("#carpet").val();
        q.door_clean = $("#door_clean").val();
        q.spot_lights = $("#spot_lights").val();
        q.lit = $("#lit").val();
        q.extinguisher_charged = $("#extinguisher_charged").val();
        q.shute_door = $("#shute_door").val();
        q.free_storage = $("#free_storage").val();
        q.hazardous_materials = $("#hazardous_materials").val();
        q.debris = $("#debris").val();
        q.inspection_visible1 = $("#inspection_visible1").val();
        q.supplies_stored = $("#supplies_stored").val();
        q.chemical_labeled = $("#chemical_labeled").val();
        q.paint_labeled= $("#paint_labeled").val();
        q.fire_charged= $("#fire_charged").val();
        q.fire_charged = $("#fire_charged").val();
        q.ladders_stored = $("#ladders_stored").val();
        q.debrisj = $("#debrisj").val();
        q.inventory_labeled = $("#inventory_labeled").val();
        q.equipment_tested = $("#equipment_tested").val();
        q.inspectionf = $("#inspectionf").val();
        q.storagef = $("#storagef").val();
        q.hazardousf = $("#hazardousf").val();
        q.chargedf = $("#chargedf").val();
        q.elevators_working = $("#elevators_working").val();
        q.doors_clean = $("#doors_clean").val();
        q.floors_clean = $("#floors_clean").val();
        q.permit_posted = $("#permit_posted").val();
        q.call_working = $("#call_working").val();
        q.pump_operational = $("#pump_operational").val();
        q.pump_functioning = $("#pump_functioning").val();
        q.check_gauge = $("#check_gauge").val();
        q.visiblep = $("#visiblep").val();
        q.storagep = $("#storagep").val();
        q.materialp = $("#materialp").val();
        q.extinguisherp = $("#extinguisherp").val();
        q.tested = $("#tested").val();
        q.fuel_level = $("#fuel_level").val();
        q.materialsd = $("#materialsd").val();
        q.storaged = $("#storaged").val();
        q.inspectiond = $("#inspectiond").val();
        q.extinguisherd = $("#extinguisherd").val();
        q.ac_units = $("#ac_units").val();
        q.acfilters = $("#cacfilters").val();
        q.thermostat_properly = $("#thermostat_properly").val();
        q.interior_clear = $("#interior_clear").val();
        q.maintenance_schedule = $("#maintenance_schedule").val();
        q.visibleac = $("#visibleac").val();
        q.debrisac = $("#debrisac").val();
        q.no_water = $("#no_water").val();
        q.compactor_functioning = $("#compactor_functioning").val();
        q.elevator = $("#elevator").val();
        q.dumpster_correctly = $("#dumpster_correctly").val();
        q.inspection_visible = $("#inspection_visible").val();
        q.materialst = $("#materialst").val();
        q.debrist = $("#debrist").val();
        q.doors_secure = $("#doors_secure").val();
        q.inspection_shingles = $("#inspection_shingles").val();
        q.debrisr = $("#debrisr").val();
        q.observations = $("#observations").val();
        q.drains_clear = $("#drains_clear").val();
      
        UTIL.callAjaxRqstPOST(q, CHECK.savedataHandler);
    },

    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            swal("information saved successfully", "", "success");
            setTimeout(function() {
                window.location = return_page;
            }, 1000);
        } else {
            swal("Information missing", data.output.response.content, "error");
        }
    },

};