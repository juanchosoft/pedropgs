/**
 * FAB: editar último reporte no finalizado del usuario en sesión.
 */
(function ($) {
    "use strict";

    var currentReport = null;
    var pgsStream = null;

    function notify(title, text, type) {
        if (typeof swal === "function") {
            swal(title, text || "", type || "info");
        } else {
            alert((title ? title + ": " : "") + (text || ""));
        }
    }

    function stopPgsCamera() {
        if (pgsStream) {
            pgsStream.getTracks().forEach(function (t) {
                t.stop();
            });
            pgsStream = null;
        }
        var video = document.getElementById("pgs_video");
        if (video) {
            try {
                video.pause();
            } catch (e) {}
            video.srcObject = null;
            $(video).addClass("none");
        }
        $(".pgs-defaultavatar").removeClass("none");
        $("#pgs_selectcam").hide();
        $("#pgs_radiosfoto").prop("checked", true);
        $("#pgs_radiotfoto").prop("checked", false);
    }

    function startPgsCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            notify("Camera", "Camera is not supported in this browser.", "error");
            stopPgsCamera();
            return;
        }
        stopPgsCamera();
        navigator.mediaDevices
            .getUserMedia({ video: { facingMode: "environment" }, audio: false })
            .then(function (stream) {
                pgsStream = stream;
                var video = document.getElementById("pgs_video");
                video.srcObject = stream;
                $(video).removeClass("none");
                $(".pgs-defaultavatar").addClass("none");
                return navigator.mediaDevices.enumerateDevices();
            })
            .then(function (devices) {
                var $sel = $("#pgs_listaDeDispositivos");
                $sel.empty();
                var count = 0;
                devices.forEach(function (d) {
                    if (d.kind !== "videoinput") return;
                    count++;
                    $sel.append($("<option/>").val(d.deviceId).text(d.label || "Camera " + count));
                });
                if (count > 1) {
                    $("#pgs_selectcam").show();
                }
            })
            .catch(function () {
                notify(
                    "Camera required",
                    "Camera permission is required to take a picture. Allow access and try again.",
                    "error"
                );
                stopPgsCamera();
            });
    }

    function showFab(report) {
        currentReport = report;
        if (!report || !report.id) {
            $("#pgsReportFab").removeClass("is-visible");
            return;
        }
        var status = report.estado || "creado";
        var statusLabel =
            status === "creado"
                ? "Created / Pending"
                : status === "pendiente"
                ? "Pending"
                : status === "finalizado"
                ? "Finalized"
                : status;
        $("#pgsReportFabLabel").text(
            "Item #" +
                report.id +
                " · " +
                statusLabel +
                (report.actividades ? " — " + report.actividades : "")
        );
        $("#pgsReportFab").addClass("is-visible");
        // Panel cerrado por defecto; se abre solo con clic en el FAB
        $("#pgsReportFabPanel").hide();
    }

    function hideFabPanel() {
        $("#pgsReportFabPanel").hide();
    }

    function hideFabCompletely() {
        currentReport = null;
        $("#pgsReportFab").removeClass("is-visible");
        hideFabPanel();
    }

    function loadLastUnfinished(opts) {
        opts = opts || {};
        if (!window.PGS_CAN_EDIT_REPORT || typeof UTIL === "undefined") {
            return;
        }
        UTIL.callAjaxRqstPOST({ op: "report_last_unfinished" }, function (data) {
            if (!data || !data.output || !data.output.valid) {
                return;
            }
            var report = data.output.response;
            if (!report) {
                hideFabCompletely();
                return;
            }
            showFab(report);
            if (opts.notifyCreated) {
                notify("Report created", "You can finish it with the floating Edit button.", "success");
            }
        }, { silent: true });
    }

    function openEditDescription() {
        if (!currentReport) return;
        $("#pgs_report_edit_id").val(currentReport.id);
        $("#pgs_report_actividades").val(currentReport.actividades || "");
        $("#pgs_report_observaciones").val(currentReport.observaciones || "");
        $("#pgsReportEditModal").modal({ backdrop: "static", keyboard: false });
    }

    function openEditPhoto() {
        if (!currentReport) return;
        $("#pgs_report_photo_id").val(currentReport.id);
        $("#pgs_report_photo_item").text(currentReport.id);
        stopPgsCamera();
        $("#pgsReportPhotoModal").modal({ backdrop: "static", keyboard: false });
    }

    function saveDescription() {
        var id = $("#pgs_report_edit_id").val();
        var actividades = $.trim($("#pgs_report_actividades").val());
        var observaciones = $("#pgs_report_observaciones").val();
        if (!id || !actividades) {
            notify("Missing data", "Activities is required.", "warning");
            return;
        }
        UTIL.callAjaxRqstPOST(
            {
                op: "updateFields",
                id: id,
                actividades: actividades,
                observaciones: observaciones,
            },
            function (data) {
                if (data && data.output && data.output.valid) {
                    $("#pgsReportEditModal").modal("hide");
                    if (currentReport) {
                        currentReport.actividades = actividades;
                        currentReport.observaciones = observaciones;
                        currentReport.estado = "pendiente";
                        showFab(currentReport);
                    }
                    notify("Saved", "Information saved correctly", "success");
                } else if (data && data.output && data.output.response) {
                    var msg =
                        typeof data.output.response === "object"
                            ? data.output.response.content
                            : data.output.response;
                    notify("Error", msg || "Could not save", "error");
                }
            }
        );
    }

    function savePhoto(e) {
        e.preventDefault();
        var id = $("#pgs_report_photo_id").val();
        var takePicture = $("#pgs_radiotfoto").is(":checked");
        var video = document.getElementById("pgs_video");
        var canvas = document.getElementById("pgs_canvas");
        var foto = "";

        if (!takePicture) {
            notify("Picture required", "Select “Take a Picture” and capture the after photo.", "warning");
            return false;
        }
        if (!pgsStream || !video || !video.videoWidth) {
            notify("Camera required", "Allow camera access and try again.", "error");
            return false;
        }

        var ctx = canvas.getContext("2d");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        foto = canvas.toDataURL("image/jpeg", 0.5);

        $("#pgs_btn_save_photo").prop("disabled", true).text("Saving...");
        $.ajax({
            type: "POST",
            url: "admin/js/camara/update_photo_after.php",
            data: { foto: foto, id: id },
            dataType: "json",
            success: function (response) {
                $("#pgs_btn_save_photo").prop("disabled", false).text("Save");
                if (response && response.success) {
                    $("#pgsReportPhotoModal").modal("hide");
                    stopPgsCamera();
                    notify("Saved", response.messages || "Photo saved", "success");
                    loadLastUnfinished();
                } else {
                    notify("Error", (response && response.messages) || "Error saving photo", "error");
                }
            },
            error: function (xhr) {
                $("#pgs_btn_save_photo").prop("disabled", false).text("Save");
                if (typeof UTIL !== "undefined" && UTIL.handleAjaxTransportError) {
                    UTIL.handleAjaxTransportError(xhr);
                } else {
                    notify("Error", "Error saving photo", "error");
                }
            },
        });
        return false;
    }

    function finalizeReport() {
        if (!currentReport || !currentReport.id) {
            return;
        }
        Swal.fire({
            title: "Finalize report?",
            text: "The report will be marked as finalized and will no longer be editable.",
            type: "warning",
            showCancelButton: true,
            confirmButtonText: "Finalize",
            cancelButtonText: "Cancel",
        }).then(function (result) {
            if (!result.value) {
                return;
            }
            UTIL.callAjaxRqstPOST(
                { op: "report_finalize", id: currentReport.id },
                function (data) {
                    if (data && data.output && data.output.valid) {
                        hideFabCompletely();
                        notify("Report finalized", "", "success");
                    } else if (data && data.output && data.output.response) {
                        var msg =
                            typeof data.output.response === "object"
                                ? data.output.response.content
                                : data.output.response;
                        notify("Error", msg || "Could not finalize", "error");
                    }
                }
            );
        });
    }

    window.PGS_REPORT_QUICK = {
        refresh: loadLastUnfinished,
        showCreated: function (reportId) {
            if (reportId) {
                showFab({ id: reportId, actividades: "", observaciones: "", estado: "creado" });
            }
            loadLastUnfinished();
        },
    };

    $(function () {
        if (!window.PGS_CAN_EDIT_REPORT) {
            return;
        }

        $("#pgsReportFabToggle").on("click", function () {
            $("#pgsReportFabPanel").toggle();
        });
        $("#pgsReportFabHide").on("click", function () {
            hideFabPanel();
        });
        $("#pgsReportFabEditDesc").on("click", openEditDescription);
        $("#pgsReportFabEditPhoto").on("click", openEditPhoto);
        $("#pgsReportFabFinalize").on("click", finalizeReport);
        $("#pgsReportEditSave").on("click", saveDescription);
        $("#pgsReportPhotoForm").on("submit", savePhoto);

        $(document).on("change", 'input[name="pgs_radio_photo"]', function () {
            if ($("#pgs_radiotfoto").is(":checked")) {
                startPgsCamera();
            } else {
                stopPgsCamera();
            }
        });

        $("#pgsReportPhotoModal").on("hidden.bs.modal", function () {
            stopPgsCamera();
        });

        if (typeof UTIL !== "undefined") {
            UTIL.cursorNormal();
        }
        loadLastUnfinished();
    });
})(jQuery);
