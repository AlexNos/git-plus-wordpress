  <!DOCTYPE html>

  <head>
    <meta charset="utf-8">
    <title><?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?> | <?php echo get_the_title(); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <!-- Google Fonts loaded here -->
    <link rel="stylesheet" type="text/css" href="http://fonts.googleapis.com/css?family=Volkhov:700,700italic|Droid Serif:400,400italic,700,700italic|Droid Sans:400,400italic,700,700italic">

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
    <link href="<?php echo get_stylesheet_directory_uri();?>/custom.css" rel="stylesheet">

    <!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

    <?php wp_enqueue_script("jquery"); ?>
    <?php wp_head(); ?>
  </head>

<body <?php body_class(); ?> data-spy="scroll" data-target=".navbar">

<div class="container page">

<!--
<div class="navbar navbar-inverse navbar-fixed-top">
  <div class="navbar-inner">
    <div class="container">
      <a class="brand" href="<?php echo get_site_url(); ?>">HorseFly</a>
      <ul class="nav">
        <li><a href="<?php echo get_site_url(); ?>/our-services">Services</a></li>

        <li><a href="<?php echo get_site_url(); ?>/our-equipment">Equipment</a></li>
        <li><a href="<?php echo get_site_url(); ?>/our-facilities">Facilities</a></li>
 
        <li><a href="<?php echo get_site_url(); ?>/faq">FAQ</a></li>
        <li><a href="<?php echo get_site_url(); ?>/request-a-quote">Request a Quote</a></li>
      </ul>
      <ul class="nav pull-right">
        <li><a href="<?php echo get_site_url(); ?>/contact-horsefly-international"><i class="icon-envelope"></i> Contact HorseFly</a></li>
      </ul>
    </div>
  </div>
</div>
 
</div>
</div>
-->

<header>
  <div class="jumbotron">
    <div class="container">
      <div class="row-fluid">
        <div class="span3">
          <h1><a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
          <p><?php bloginfo( 'description' ); ?></p>
        </div>          
        <div class="span9">
          <?php wp_nav_menu( array(
                                    'theme_location'=>'main-menu',
                                    'container' => false,
                                    'menu_class' => 'nav nav-pills pull-right'
                                  )
                            ); ?>
<!--
           <ul class="nav nav-pills  pull-right">
            <li class="active">
              <a href="#">Home</a>
            </li>
            <li><a href="#">About</a></li>
            <li><a href="#">Contact</a></li>
          </ul>            
 -->
         </div>
      </div>
    </div>
  </div>
</header>

<div class="picture-bar"></div>

<div id="hf-page-title">
  <div class="container">
    <h1><?php echo get_the_title(); ?></h1>
  </div>
</div>

<div class="container-fluid content">
