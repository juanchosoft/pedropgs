<ul class="metismenu" id="menu">
   <li><a href="./main.php" class="ai-icon" aria-expanded="false">
         <i class="fa fa-home"></i>
         <span class="nav-text">Home</span>
      </a>
   </li>
   <style>
  /* =========================
     Premium Sidebar (RED/BLACK)
     Works with MetisMenu
     ========================= */
  #menu{
    --brand:#e11d48;
    --brand2:#fb7185;
    --ink:#0b0f16;
    --muted:#9aa4b2;
    --border: rgba(255,255,255,.08);
    --card: rgba(255,255,255,.04);
    --card2: rgba(255,255,255,.06);
    --glow: 0 16px 34px rgba(225,29,72,.18);
  }

  /* Sidebar background (if your theme allows) */
  .deznav{
    background:
      radial-gradient(900px 420px at 20% -10%, rgba(225,29,72,.22), transparent 55%),
      radial-gradient(900px 420px at 120% 20%, rgba(0,0,0,.28), transparent 55%),
      linear-gradient(180deg, #0b0f16, #070a0f) !important;
    border-right: 1px solid rgba(255,255,255,.06);
  }

  /* Main list spacing */
  #menu > li{ margin: 6px 10px; }

  /* Top-level link */
  #menu > li > a{
    border-radius: 14px !important;
    padding: 12px 12px !important;
    display:flex !important;
    align-items:center !important;
    gap: 10px !important;
    color: rgba(255,255,255,.86) !important;
    font-weight: 900 !important;
    letter-spacing: -.1px;
    background: transparent;
    border: 1px solid transparent;
    transition: transform .12s ease, background .12s ease, border-color .12s ease, box-shadow .12s ease;
    position: relative;
    overflow:hidden;
  }

  /* subtle shine */
  #menu > li > a::before{
    content:"";
    position:absolute;
    inset:0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.06), transparent);
    transform: translateX(-120%);
    transition: transform .5s ease;
  }
  #menu > li > a:hover::before{ transform: translateX(120%); }

  #menu > li > a:hover{
    background: rgba(255,255,255,.05) !important;
    border-color: rgba(255,255,255,.08) !important;
    transform: translateY(-1px);
  }

  /* Icon bubble */
  #menu > li > a > i{
    width: 34px;
    height: 34px;
    display:grid;
    place-items:center;
    border-radius: 12px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.07);
    color: rgba(255,255,255,.92);
    flex: 0 0 auto;
  }

  /* Active item */
  #menu > li.mm-active > a,
  #menu > li > a.active{
    background: linear-gradient(135deg, rgba(225,29,72,.22), rgba(255,255,255,.05)) !important;
    border-color: rgba(225,29,72,.25) !important;
    box-shadow: var(--glow);
  }
  #menu > li.mm-active > a > i,
  #menu > li > a.active > i{
    background: linear-gradient(135deg, var(--brand), #111827) !important;
    border-color: rgba(225,29,72,.35) !important;
    color: #fff !important;
  }

  /* Left accent bar */
  #menu > li.mm-active > a::after,
  #menu > li > a.active::after{
    content:"";
    position:absolute;
    left:0;
    top: 10px;
    bottom: 10px;
    width: 4px;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--brand), var(--brand2));
  }

  /* Submenu container */
  #menu ul{
    margin: 8px 0 0 0 !important;
    padding: 8px 8px 8px 44px !important;
    border-radius: 14px;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.06);
  }

  /* Submenu links */
  #menu ul li a{
    padding: 10px 10px !important;
    border-radius: 12px !important;
    color: rgba(255,255,255,.78) !important;
    font-weight: 850 !important;
    transition: background .12s ease, transform .12s ease;
    position: relative;
  }

  /* Bullet */
  #menu ul li a::before{
    content:"";
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: rgba(255,255,255,.18);
    display:inline-block;
    margin-right: 10px;
    vertical-align: middle;
    transform: translateY(-1px);
  }

  #menu ul li a:hover{
    background: rgba(255,255,255,.05) !important;
    transform: translateX(2px);
  }

  /* Submenu active */
  #menu ul li.mm-active > a,
  #menu ul li a.active{
    background: rgba(225,29,72,.15) !important;
    color: rgba(255,255,255,.95) !important;
  }
  #menu ul li.mm-active > a::before,
  #menu ul li a.active::before{
    background: linear-gradient(135deg, var(--brand), var(--brand2));
  }

  /* Arrow animation for has-arrow */
  #menu a.has-arrow:after{
    right: 14px !important;
    opacity: .85;
    transform: rotate(0deg);
    transition: transform .18s ease;
  }
  #menu li.mm-active > a.has-arrow:after{ transform: rotate(90deg); }

  /* Better tap targets on mobile */
  @media (max-width: 768px){
    #menu > li{ margin: 6px 8px; }
    #menu > li > a{
      padding: 14px 12px !important;
      border-radius: 16px !important;
    }
    #menu > li > a > i{
      width: 38px;
      height: 38px;
      border-radius: 14px;
    }
    #menu ul{
      padding-left: 52px !important;
    }
    #menu ul li a{
      padding: 12px 10px !important;
      border-radius: 14px !important;
    }
  }

  /* When sidebar is collapsed (common in admin templates) */
  [data-sidebar-style="mini"] #menu > li > a{
    justify-content:center !important;
  }
  
</style>
<style>
/* =========================
   FIX: text over icon (MetisMenu / ai-icon templates)
   ========================= */
#menu > li > a.ai-icon{
  display:flex !important;
  align-items:center !important;
  gap: 10px !important;
}

/* Many templates set icons as absolute. Force them back into the flow */
#menu > li > a.ai-icon > i{
  position: static !important;
  left: auto !important;
  top: auto !important;
  transform: none !important;
  margin: 0 !important;
  flex: 0 0 34px;
}

/* Some templates set .nav-text absolute / padded */
#menu > li > a.ai-icon .nav-text{
  position: static !important;
  padding-left: 0 !important;
  margin-left: 0 !important;
  display:inline-block !important;
  white-space: nowrap;
  line-height: 1.1;
}

/* If your theme adds extra left padding that causes overlap, normalize it */
#menu > li > a{
  padding-left: 12px !important;
}

/* Mobile: a bit more room */
@media (max-width: 768px){
  #menu > li > a.ai-icon > i{ flex-basis: 38px; }
}
</style>
<style>
/* =========================
   FIX: icons outside the pill / box
   ========================= */

/* the clickable item */
#menu > li > a.ai-icon{
  position: relative !important;
  overflow: hidden !important;        /* evita que se salga el icono */
  border-radius: 16px !important;
}

/* force icon box */
#menu > li > a.ai-icon > i{
  width: 42px !important;
  height: 42px !important;
  min-width: 42px !important;
  min-height: 42px !important;

  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;

  border-radius: 14px !important;

  /* neutraliza offsets viejos del theme */
  position: static !important;
  left: auto !important;
  top: auto !important;
  right: auto !important;
  bottom: auto !important;
  margin: 0 !important;
  padding: 0 !important;
  transform: none !important;
  line-height: 1 !important;
}

/* keeps text aligned */
#menu > li > a.ai-icon .nav-text{
  padding-left: 0 !important;
  margin-left: 0 !important;
}

/* spacing inside pill */
#menu > li > a.ai-icon{
  padding: 10px 12px !important;
  gap: 12px !important;
}

/* Optional: chip background subtle (if you want) */
#menu > li > a.ai-icon > i{
  background: rgba(255,255,255,.06) !important;
  border: 1px solid rgba(255,255,255,.10) !important;
}
</style>
   <?php
   $userUnidad = SessionData::getUnidadUser(); // Obtener la unidad del usuario
   $userType = SessionData::getUserType(); // Obtener el tipo de usuario

   // Determinar si el usuario puede ver todas las opciones o solo algunas
   $esSuperAdmin = ($userType == Util::SuperAdmin());
   $esManagerOStaff = ($userType == Util::Manager() || $userType == Util::Staff());
   $esManager =  $userType == Util::Staff();

   ?>

   <li>
      <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
         <i class="fa fa-hand-pointer-o"></i>
         <span class="nav-text">Reports</span>
      </a>
      <ul aria-expanded="false">

         <?php if (($esSuperAdmin  || $esManager)  && SessionData::getPermission(7)): ?>
            <li><a href="./report.php">Enter Report</a></li>
         <?php endif; ?>

         <?php if (($esSuperAdmin  || $esManager)  && SessionData::getPermission(9)): ?>
            <li><a href="./report-list.php">Edit Report</a></li>
         <?php endif; ?>

         <?php if (($esSuperAdmin  || $esManager) && SessionData::getPermission(21)): ?>
            <li><a href="./check_list.php">Check List Report</a></li>
         <?php endif; ?>

           <?php if (($esSuperAdmin  ||  $esManagerOAdmin) && SessionData::getPermission(21)): ?>
            <li><a href="./check_list_villasol.php">Check List Report Villasol</a></li>
         <?php endif; ?>

            <?php if (($esSuperAdmin  ||  $esManagerOAdmin) && SessionData::getPermission(21)): ?>
            ) && SessionData::getPermission(22)): ?>
            <li><a href="./check_report_list_villasol.php">Show Check List Report Villasol</a></li>
            <li><a href="./calendar.php">Calendar</a></li>
         <?php endif; ?>

         <!-- Si es Manager o Staff, pero solo si tienen permisos -->
         <?php if ( ($esSuperAdmin  || $esManagerOStaff) && SessionData::getPermission(22)): ?>
            <li><a href="./check_report_list.php">Show Check List Report</a></li>
            <li><a href="./calendar.php">Calendar</a></li>
         <?php endif; ?>

         <?php if ( ($esSuperAdmin  || $esManagerOStaff ) && SessionData::getPermission(22)): ?>
            <li><a href="./report-list-group.php">Report List Group Download</a></li>
         <?php endif; ?>

      </ul>
   </li>

   <?php if (SessionData::getPermission(12)): ?>
      <li>
         <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
            <i class="fa fa-address-book-o"></i>
            <span class="nav-text">Customers</span>
         </a>
         <ul aria-expanded="false">
            <li><a href="./places_customers.php">New Customer</a></li>
         </ul>
      </li>
   <?php endif; ?>
   <?php if (SessionData::getPermission(1)): ?>
      <li>
         <a href="./usuarios.php" class="ai-icon" aria-expanded="false">
            <i class="fa fa-user-circle-o"></i>
            <span class="nav-text">User</span>
         </a>
      </li>
   <?php endif; ?>

   <?php if (SessionData::getPermission(27)): ?>
      <li>
         <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
            <i class="fa fa-address-card-o"></i>
            <span class="nav-text">Employees</span>
         </a>
         <ul aria-expanded="false">
            <li><a href="./empleados.php">View Employees</a></li>
            <li><a href="./reloj.php">Record time</a></li>
            <li><a href="./informe_salidas.php">Entry - Exit</a></li>
            <li><a href="./uniformes.php">Uniforms</a></li>
         </ul>
      </li>
   <?php endif; ?>
   <?php if (SessionData::getPermission(40)): ?>
      <li>
         <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
            <i class="fa fa-cog"></i>
            <span class="nav-text">Configuration</span>
         </a>
         <ul aria-expanded="false">
            <li><a href="./configuracion.php">Configuration</a></li>

         </ul>
      </li>
   <?php endif; ?>
   <!-- <li>
      <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">
         <i class="fa fa-list-ol"></i>
         <span class="nav-text">Request</span>
      </a>
      <ul aria-expanded="false">
         <li><a href="./new_requeriment.php">New request</a></li>
      </ul>
   </li> -->

   <!-- <li>
      <a class="has-arrow ai-icon" href="javascript:void()" aria-expanded="false">

         <i class="fa fa-barcode"></i>

         <span class="nav-text">Inventory</span>

      </a>

      <ul aria-expanded="false">

         <li><a href="./categorias.php">Category</a></li>

         <li><a href="./productos.php">New product</a></li>

         <li><a href="./inventario.php">Inventory General</a></li>

      </ul>

   </li> -->