<nav class="navbar navbar-default navbar-static-top">
  <div class="navbar-header">
    <div class="container">
      <div class="row">
        <div class="col-xs-6 col-sm-8 col-md-12 col-lg-12">

          <!-- Branding Image -->
          <a class="navbar-brand" href="/"></a>

            <?php if (!strpos($_SERVER['REQUEST_URI'], 'login')) { ?>
              <!-- Collapsed Hamburger -->
              <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#app-navbar-collapse">
                <span class="sr-only">Toggle Navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
              </button>

              <div class="clearfix hidden-sm hidden-md hidden-lg"></div>
              <div class="collapse navbar-collapse" id="app-navbar-collapse">
                <!-- Right Side Of Navbar -->
                <ul class="nav navbar-nav navbar-right">
                  <!-- Authentication Links -->
                    <?php if (!strpos($_SERVER['REQUEST_URI'], 'login') && !strpos($_SERVER['REQUEST_URI'], 'register')) { ?>
                      <li><a class="scroll-to" data-target="about" href="#about">About</a></li>
                      <li><a class="scroll-to" data-target="products" href="#products">Products</a></li>
                      <li><a class="scroll-to" data-target="our-team" href="#team">Team</a></li>
                      <li><a href="mailto:support@landconnect.com.au">Contact</a></li>
                    <?php } ?>
                </ul>
              </div>
            <?php } ?>
        </div>
      </div>
    </div>
  </div>
</nav>