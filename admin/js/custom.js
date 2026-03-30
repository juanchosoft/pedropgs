/** No permitir dar atras ni adelante en la pagina */
function nobackbutton() {

    window.location.hash = "no-back-button";

    window.location.hash = "Again-No-back-button" //chrome

    window.onhashchange = function() { window.location.hash = "no-back-button"; }

}


// /** No permitir la tecla F5 para no recargar la pagina */
function disableF5(e) { if ((e.which || e.keyCode) == 116) e.preventDefault(); };
$(document).on("keydown", disableF5);

// simply visual, let's you know when the correct iframe is selected
// $(window).on("focus", function(e) {
//         $("html, body").css({ background: "#FFF", color: "#000" })
//             .find("h2").html("THIS BOX NOW HAS FOCUS<br />F5 should not work.");
//     })
//     .on("blur", function(e) {
//         $("html, body").css({ background: "", color: "" })
//             .find("h2").html("CLICK HERE TO GIVE THIS BOX FOCUS BEFORE PRESSING F5");
//     });