<?php
echo "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Permission Denied</title>

    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>

    <style>
        body{
            margin:0;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:
                radial-gradient(circle at top left, rgba(239,35,60,.22), transparent 34%),
                linear-gradient(135deg, #080b10 0%, #180612 55%, #030509 100%);
            font-family: Arial, Helvetica, sans-serif;
        }

        .swal2-container{
            z-index:999999 !important;
        }

        .pgs-alert-popup{
            width:min(430px, calc(100% - 28px)) !important;
            border-radius:28px !important;
            padding:0 !important;
            overflow:hidden !important;
            border:1px solid rgba(239,35,60,.30) !important;
            background:
                radial-gradient(420px 190px at 100% 0%, rgba(239,35,60,.18), transparent 62%),
                radial-gradient(320px 160px at 0% 100%, rgba(255,255,255,.07), transparent 62%),
                linear-gradient(180deg, #151821 0%, #090b10 100%) !important;
            box-shadow:
                0 28px 80px rgba(0,0,0,.55),
                0 0 0 1px rgba(255,255,255,.04) inset !important;
        }

        .pgs-alert-box{
            padding:30px 26px 26px !important;
            text-align:center !important;
            color:#ffffff !important;
        }

        .pgs-alert-icon{
            width:72px;
            height:72px;
            margin:0 auto 18px;
            border-radius:24px;
            display:grid;
            place-items:center;
            font-size:32px;
            background:
                radial-gradient(circle at 30% 20%, rgba(255,255,255,.35), transparent 46%),
                linear-gradient(135deg, #ef233c, #9b1025);
            box-shadow:
                0 18px 38px rgba(239,35,60,.34),
                0 0 0 8px rgba(239,35,60,.10);
        }

        .pgs-alert-title{
            margin:0 0 10px;
            color:#ffffff !important;
            font-size:24px;
            font-weight:900;
            line-height:1.08;
            letter-spacing:-.04em;
        }

        .pgs-alert-text{
            margin:0 auto;
            max-width:330px;
            color:rgba(255,255,255,.80) !important;
            font-size:14px;
            font-weight:650;
            line-height:1.55;
        }

        .pgs-alert-mini{
            margin-top:16px;
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:9px 12px;
            border-radius:999px;
            color:rgba(255,255,255,.82);
            font-size:12px;
            font-weight:800;
            background:rgba(255,255,255,.06);
            border:1px solid rgba(255,255,255,.10);
        }

        .pgs-alert-mini span{
            width:8px;
            height:8px;
            border-radius:50%;
            background:#ef233c;
            box-shadow:0 0 0 5px rgba(239,35,60,.13);
        }

        .pgs-alert-button{
            border:0 !important;
            border-radius:999px !important;
            padding:13px 24px !important;
            min-width:170px !important;
            color:#ffffff !important;
            font-size:14px !important;
            font-weight:900 !important;
            background:linear-gradient(135deg, #ef233c, #9b1025) !important;
            box-shadow:0 16px 34px rgba(239,35,60,.32) !important;
            transition:all .22s ease !important;
        }

        .pgs-alert-button:hover{
            transform:translateY(-1px) !important;
            filter:brightness(1.08) !important;
        }

        .swal2-actions{
            margin-top:22px !important;
            padding-bottom:8px !important;
        }

        .swal2-html-container{
            margin:0 !important;
            padding:0 !important;
        }

        @media (max-width:575px){
            .pgs-alert-box{
                padding:26px 20px 22px !important;
            }

            .pgs-alert-icon{
                width:62px;
                height:62px;
                border-radius:20px;
                margin-bottom:15px;
                font-size:28px;
            }

            .pgs-alert-title{
                font-size:21px;
            }

            .pgs-alert-text{
                font-size:13px;
            }

            .pgs-alert-button{
                width:100% !important;
                min-width:100% !important;
                padding:14px 20px !important;
            }
        }
    </style>
</head>

<body>

<script>
function redirectPermissionDenied(){
    window.location.href = 'main.php';
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            html: `
                <div class='pgs-alert-box'>
                    <div class='pgs-alert-icon'>🔒</div>

                    <h2 class='pgs-alert-title'>
                        Permission denied
                    </h2>

                    <p class='pgs-alert-text'>
                        You do not have permission to access this section.
                        Please contact your manager.
                    </p>

                    <div class='pgs-alert-mini'>
                        <span></span>
                        Restricted access
                    </div>
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: 'Go to dashboard',
            buttonsStyling: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            background: 'transparent',
            customClass: {
                popup: 'pgs-alert-popup',
                confirmButton: 'pgs-alert-button'
            }
        }).then(function () {
            redirectPermissionDenied();
        });
    } else {
        alert('PERMISSION DENIED, PLEASE CONTACT WITH MANAGER');
        redirectPermissionDenied();
    }
});
</script>

</body>
</html>
";
exit;
?>