  <!DOCTYPE html>

  <head>
    <meta charset="utf-8">
    <title><?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <!-- Google Fonts loaded here -->
    <link rel="stylesheet" type="text/css" href="http://fonts.googleapis.com/css?family=Questrial">

    <!-- Twitter Bootstrap & FontAwesome css -->
    <link href="<?php echo get_template_directory_uri();?>/bootstrap/css/bootstrap.css" rel="stylesheet">
    <style type="text/css">
      body {
        /* 
        padding-top: 60px;
        padding-bottom: 40px; 
        */
      }
    </style>
    <link href="<?php echo get_template_directory_uri();?>/bootstrap/css/bootstrap-responsive.css" rel="stylesheet">
    <link href="<?php echo get_template_directory_uri();?>/bootstrap/css/font-awesome.min.css" rel="stylesheet">

    <!-- WP styles.css -->
    <link href="<?php echo get_stylesheet_directory_uri();?>/style.css" rel="stylesheet">
    <!-- <link href="<?php echo get_stylesheet_directory_uri();?>/style.css" rel="stylesheet"> -->
    <link href="<?php echo get_stylesheet_directory_uri();?>/custom.css" rel="stylesheet">

    <!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

    <?php wp_enqueue_script("jquery"); ?>
    <?php wp_head(); ?>
  </head>
  <body <?php body_class(); ?>>

<!--
  <div class="navbar navbar-inverse navbar-fixed-top">
    <div class="navbar-inner">
      <div class="container">

        <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </a>
 
        <a class="brand" href="<?php echo site_url(); ?>"><?php bloginfo('name'); ?></a>
        <div class="nav-collapse collapse">
          <ul class="nav">

              <?php wp_list_pages(array('title_li' => '', 'exclude' => 4)); ?>

          </ul>
        </div> 
      </div>
    </div>
  </div>

 -->

  <header>

    <div class="container">

      <h1 class="site-title page header"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
      <h2 class="site-description"><?php bloginfo( 'description' ); ?></h2>

      <div class="navbar">
        <div class="navbar-inner">

            <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
            </a>
     
            <div class="nav-collapse collapse">
              <?php wp_nav_menu( array('menu' => 'main-menu', 'menu_class' => 'nav') ); ?>
            </div> 

        </div>
      </div>  

    </div>

  </header>

  <div class="sub-menu">&nbsp;</div>
  <div class="title-bar">&nbsp;</div>
  <div class="main-slider">&nbsp;</div>

  <div class="container">