<?php 
define ('PATHSPAGE', TRUE);
include 'verse.php';
include '../root_path.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>The Holy Bible in the English language  - KJV</title>
<meta name="title" content="The Holy Bible in the English language - KJV" />
<!-- <link rel="shortcut icon" href="favicon.ico?v=2" type="image/x-icon" /> -->
<link href="_assets/apple-touch-icon.png" rel="apple-touch-icon" />
<!-- Mobile viewport optimisation -->
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="stylesheet" type="text/css" href="_assets/css/css.css" />
<link rel="stylesheet" type="text/css" href="_assets/css/style.css" />
<link rel="stylesheet" type="text/css" href="_assets/css/tables_bibles.css" />
</head>
<body>
<!-- <header class="ym-noprint">
<div id="mytop" class="ym-wrapper">
<div class="ym-wbox">
<span class="wp"><strong><a class="wplink" href="#" target="_top">W</a><a class="wplink" href="../../index.htm" target="_top">P</strong></a></span>
</div>
</div>
</header> -->
<!--index nav-->
<nav id="nav">
<div class="ym-wrapper">
<div class="ym-hlist">
<ul>
<li><a title="Other languages" href="<?php echo PATH ?>" target="_self">FaceGod</a></li>
<li><a title="Other languages" href="https://www.wordproject.org/bibles/index.htm" target="_self">More Bibles</a></li>
<li><a title="Audio Bibles in different languages" href="https://www.wordproject.org/bibles/audio/index.htm" target="_top">Audio Bibles</a></li>
<!--li><a title="Search this Bible" href="search.html" target="_top">Search</a></li-->
</ul>
</div>
</div>
</nav>
<div class="ym-wrapper ym-noprint">
<div class="ym-wbox">

<!--breadcrumbs-->
<div class=" ym-grid">
<div class="ym-g62 ym-gl breadCrumbs"> <a title="Home" href="#" target="_top">Home</a> / <a title="Bibles" href="#" target="_self">Bibles</a> /  </div>
</div>

<div class="shareright"><a class="decreaseFont ym-button2">-</a><a class="resetFont ym-button2">Reset</a><a class="increaseFont ym-button2">+</a>
</div>

</div>
</div>
<div id="main" class="ym-clearfix" role="main">
<div class="ym-wrapper">
<div class="ym-wbox">
<div class="textOptions">
<div class="textHeader">
<h1>The Holy Bible </h1>
<span class="faded4">[King James Version]</span>
<p class="faded" id="0">Please, choose a book  of the Holy Bible in the English language:</p>
</div>
<div class="ym-grid linearize-level-2">
<!-- chapter list -->
<div class="alignCenter" id="1"><a title="Index without Book Numbers" href="index.php#1" class="special">[No Book Nos.]</a></span></div>
<div class="ym-g50 ym-gl">
<div class="h4center">Old Testament </div>
<ul class="nav nav-tabs nav-stacked">
<?php 
$count = 0;
foreach($Book["Old Testament"] as $b){
	$count++;
	echo "<li><a title='click to open book ' href='book.php?passage=$b 1'>[$count] $b</a></li>\n";
}
?>

</ul>
</div> <div class="ym-g50 ym-gr">
<div class="h4center">New Testament</div>
<ul class="nav nav-tabs nav-stacked">
<?php 
$count = 0;
foreach($Book["New Testament"] as $k => $b){
	$count++;
	echo "<li><a title='click to open book ' href='book.php?passage=$b 1'>[".$k."] $b</a></li>\n";
}
?>
</ul>
</div>
<!-- end chapter list -->
</div><!-- linearize end -->
</div><!-- text options end -->

</div><!-- ym-box end -->
</div><!-- ym-wrapper end -->
</div><!-- main end -->
<!--first chapter start/Top-->
<div class="ym-wrapper">
<div class="ym-wbox">
<div class="shareright ym-noprint">
<a class="ym-button" title="Page TOP" href="#mytop">&nbsp;<img src="_assets/img/arrow_up.png" class="imageatt" alt="arrowup"/>&nbsp;</a>
<a class="ym-button" title="Open First Book" href="01/1.htm">&nbsp;<img src="_assets/img/arrow_right.png" class="imageatt" alt="arrowright"/>&nbsp;</a></p>
</div>
</div>
</div>
<!--first chapter end-->

<footer>
<div class="ym-wrapper">
<div id="redborder" class="ym-wbox">
<p class="alignCenter">Courtesy of Wordproject® a registered domain of the <a href="https://www.abiblica.org/index.html" target="_top">International Biblical Association</a>, a non-profit organization registered in Macau, China.	</p>
</div>
</div>
</footer>
</body>
<script src="_assets/js/jquery-1.8.0.min.js"></script>
<!-- <script src="_assets/js/script.js"></script> -->
<script src="_assets/js/jquery.dropotron.min.js"></script>
<script src="_assets/js/skel.min.js"></script>
<script src="_assets/js/skel-viewport.min.js"></script>
<script src="_assets/js/util.js"></script>
<script src="_assets/js/main.js"></script>
</html>
