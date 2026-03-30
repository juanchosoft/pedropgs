  
  <script src="assets/js/core/jquery.min.js"></script>
  <script src="assets/js/core/popper.min.js"></script>
  <!-- <script src="assets/js/core/bootstrap-material-design.min.js"></script> -->
  <script src="assets/js/plugins/perfect-scrollbar.jquery.min.js"></script>
  <script src="assets/js/plugins/moment.min.js"></script>
  <script src="assets/js/plugins/sweetalert2.js"></script>
  <script src="assets/js/plugins/jquery.validate.min.js"></script>
  <script src="assets/js/plugins/jquery.bootstrap-wizard.js"></script>
  <!-- <script src="assets/js/plugins/bootstrap-selectpicker.js"></script> -->
  <script src="assets/js/plugins/bootstrap-datetimepicker.min.js"></script>
  <script src="assets/js/plugins/bootstrap-tagsinput.js"></script>
  <script src="assets/js/plugins/jasny-bootstrap.min.js"></script>
  <script src="assets/js/plugins/jquery-jvectormap.js"></script>
  <script src="assets/js/plugins/nouislider.min.js"></script>
  <script src="assets/js/plugins/arrive.min.js"></script>
  <script src="assets/js/plugins/chartist.min.js"></script>
  <script src="assets/js/plugins/bootstrap-notify.js"></script>
  <!-- <script src="assets/js/material-dashboard.js?v=2.1.2" type="text/javascript"></script> -->
  <script src="assets/demo/demo.js"></script>
  <script type="text/javascript" src="admin/js/lib/util.js"></script>
  <script type="text/javascript" src="admin/js/lib/axios-v0.21.0.js"></script>
  <script type="text/javascript" src="admin/js/jquery/solonumeros.js"></script>
  <script type="text/javascript" src="admin/js/jquery/numeral.min.2.0.6.js"></script>

  <script src="data/js/global/global.min.js"></script>
  <script src="data/js/bootstrap-select/dist/js/bootstrap-select.min.js"></script>
  <script src="data/js/bootstrap-datetimepicker/js/moment.js"></script>
  <script src="data/js/bootstrap-datetimepicker/js/bootstrap-datetimepicker.min.js"></script>
  <script src="data/js/custom.min.js"></script>
  <script src="data/js/deznav-init.js"></script>
  <link href="data/js/select2/select2.min.css" rel="stylesheet" />
  <script src="data/js/select2/select2.min.js"></script>
  <script type="text/javascript" src="admin/js/KBmodal.js"></script>
<script>
  (function(){
    function norm(p){ return (p || '').split('?')[0].split('#')[0].toLowerCase(); }

    const current = norm(window.location.pathname);
    const menu = document.getElementById('menu');
    if(!menu) return;

    const links = menu.querySelectorAll('a[href]');
    let best = null;

    links.forEach(a=>{
      const href = a.getAttribute('href') || '';
      if(!href || href === 'javascript:void()' || href === 'javascript:void(0)') return;

      // handle ./file.php and file.php
      const url = new URL(href, window.location.origin + window.location.pathname);
      const path = norm(url.pathname);

      // match end of path
      if(current.endsWith(path)){
        best = a;
      }
    });

    if(best){
      best.classList.add('active');

      // activate parents
      let li = best.closest('li');
      while(li){
        li.classList.add('mm-active');
        const parentUl = li.parentElement;
        if(parentUl && parentUl.classList.contains('metismenu')) break;
        li = parentUl ? parentUl.closest('li') : null;
      }

      // ensure submenu visible (MetisMenu usually handles, this is a safe fallback)
      const parentSub = best.closest('ul');
      if(parentSub && parentSub !== menu){
        parentSub.classList.add('mm-show');
        parentSub.style.display = 'block';
      }
    }
  })();
</script>
<!-- 
  <script>
    $(document).ready(function() {
      $().ready(function() {
        $sidebar = $('.sidebar');

        $sidebar_img_container = $sidebar.find('.sidebar-background');

        $full_page = $('.full-page');

        $sidebar_responsive = $('body > .navbar-collapse');

        window_width = $(window).width();

        fixed_plugin_open = $('.sidebar .sidebar-wrapper .nav li.active a p').html();

        if (window_width > 767 && fixed_plugin_open == 'Dashboard') {
          if ($('.fixed-plugin .dropdown').hasClass('show-dropdown')) {
            $('.fixed-plugin .dropdown').addClass('open');
          }
        }

        $('.fixed-plugin a').click(function(event) {
          if ($(this).hasClass('switch-trigger')) {
            if (event.stopPropagation) {
              event.stopPropagation();
            } else if (window.event) {
              window.event.cancelBubble = true;
            }
          }
        });

        $('.fixed-plugin .active-color span').click(function() {
          $full_page_background = $('.full-page-background');

          $(this).siblings().removeClass('active');
          $(this).addClass('active');

          var new_color = $(this).data('color');

          if ($sidebar.length != 0) {
            $sidebar.attr('data-color', new_color);
          }

          if ($full_page.length != 0) {
            $full_page.attr('filter-color', new_color);
          }

          if ($sidebar_responsive.length != 0) {
            $sidebar_responsive.attr('data-color', new_color);
          }
        });

        $('.fixed-plugin .background-color .badge').click(function() {
          $(this).siblings().removeClass('active');
          $(this).addClass('active');

          var new_color = $(this).data('background-color');

          if ($sidebar.length != 0) {
            $sidebar.attr('data-background-color', new_color);
          }
        });

        $('.fixed-plugin .img-holder').click(function() {
          $full_page_background = $('.full-page-background');

          $(this).parent('li').siblings().removeClass('active');
          $(this).parent('li').addClass('active');


          var new_image = $(this).find("img").attr('src');

          if ($sidebar_img_container.length != 0 && $('.switch-sidebar-image input:checked').length != 0) {
            $sidebar_img_container.fadeOut('fast', function() {
              $sidebar_img_container.css('background-image', 'url("' + new_image + '")');
              $sidebar_img_container.fadeIn('fast');
            });
          }

          if ($full_page_background.length != 0 && $('.switch-sidebar-image input:checked').length != 0) {
            var new_image_full_page = $('.fixed-plugin li.active .img-holder').find('img').data('src');

            $full_page_background.fadeOut('fast', function() {
              $full_page_background.css('background-image', 'url("' + new_image_full_page + '")');
              $full_page_background.fadeIn('fast');
            });
          }

          if ($('.switch-sidebar-image input:checked').length == 0) {
            var new_image = $('.fixed-plugin li.active .img-holder').find("img").attr('src');
            var new_image_full_page = $('.fixed-plugin li.active .img-holder').find('img').data('src');

            $sidebar_img_container.css('background-image', 'url("' + new_image + '")');
            $full_page_background.css('background-image', 'url("' + new_image_full_page + '")');
          }

          if ($sidebar_responsive.length != 0) {
            $sidebar_responsive.css('background-image', 'url("' + new_image + '")');
          }
        });

        $('.switch-sidebar-image input').change(function() {
          $full_page_background = $('.full-page-background');

          $input = $(this);

          if ($input.is(':checked')) {
            if ($sidebar_img_container.length != 0) {
              $sidebar_img_container.fadeIn('fast');
              $sidebar.attr('data-image', '#');
            }

            if ($full_page_background.length != 0) {
              $full_page_background.fadeIn('fast');
              $full_page.attr('data-image', '#');
            }

            background_image = true;
          } else {
            if ($sidebar_img_container.length != 0) {
              $sidebar.removeAttr('data-image');
              $sidebar_img_container.fadeOut('fast');
            }

            if ($full_page_background.length != 0) {
              $full_page.removeAttr('data-image', '#');
              $full_page_background.fadeOut('fast');
            }

            background_image = false;
          }
        });

        $('.switch-sidebar-mini input').change(function() {
          $body = $('body');

          $input = $(this);

          if (md.misc.sidebar_mini_active == true) {
            $('body').removeClass('sidebar-mini');
            md.misc.sidebar_mini_active = false;

            $('.sidebar .sidebar-wrapper, .main-panel').perfectScrollbar();

          } else {

            $('.sidebar .sidebar-wrapper, .main-panel').perfectScrollbar('destroy');

            setTimeout(function() {
              $('body').addClass('sidebar-mini');

              md.misc.sidebar_mini_active = true;
            }, 300);
          }

          var simulateWindowResize = setInterval(function() {
            window.dispatchEvent(new Event('resize'));
          }, 180);

          setTimeout(function() {
            clearInterval(simulateWindowResize);
          }, 1000);

        });
      });
    });
  </script>
  <script>
    $(document).ready(function() {
      md.initDashboardPageCharts();
      var url = window.location.pathname;
      var activePage = url.substring(url.lastIndexOf('/') + 1);
      $('.nav li a').each(function() {
        var currentPage = this.href.substring(this.href.lastIndexOf('/') + 1);
        if (activePage == currentPage) {
          $(this).parent().addClass('active');
        }
      });
    });
  </script>
-->

<!-- Campo para la configuracion de la impresora termina -->
<input type="hidden" id="config_impresion_termica" name="config_impresion_termica" value="<?php echo SessionData::getConfigImpresionPOS();?>">
