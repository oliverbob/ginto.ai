<?php 
define ('PATHSPAGE', TRUE);
include 'verse.php';
// include '../root_path.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Creation of the world, Genesis Chapter 1</title>
<meta name="description" content="Creation of the world, Genesis Chapter 1" />
<meta name="keywords" content="Holy Bible, Old Testament, scriptures,  Creation, faith, heaven, hell, God, Jesus" />
<!-- Mobile viewport optimisation -->
<!-- <link rel="shortcut icon" href="favicon.ico?v=2" type="image/x-icon" /> -->
<link href="apple-touch-icon.png" rel="apple-touch-icon" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="stylesheet" type="text/css" href="_assets/css/css.css" />
<link rel="stylesheet" type="text/css" href="_assets/css/_darkmode.css" />
<link rel="stylesheet" type="text/css" href="_assets/css/style.css" />
<link rel="stylesheet" type="text/css" href="_assets/css/bar-ui.css" />

<style>
    #textBody > 
        p:first-letter {
            float: left;
            font-size:  300%;
            <!--padding: 10px 20 10 40px;-->
            margin-right: 9px;
            color:#a23021; 
            line-height:100%; 
            padding:4px 8px 0 3px;
            font-family: Georgia;
        }
    </style>
</head>
<body>
<!-- <header class="ym-noprint">
<div id="mytop" class="ym-wrapper">
<div class="ym-wbox">
<span class="wp"><strong><a class="wplink" href="index.htm" target="_top">W</a><a class="wplink" href="index.htm" target="_top">P</a></strong></span>
</div>
</div>
</header> -->

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

<div class=" ym-grid">
<div class="ym-g62 ym-gl breadCrumbs"> <a title="Bibles" href="index.php" target="_self" data-toggle='tooltip' data-html='true' title='<?php echo $bk ?>'>Bible</a> /  <a href="index.php" target="_self">KJV</a></div>
</div>
</div>
</div>
<div id="main" class="ym-clearfix" role="main">
<div class="ym-wrapper"> 
<div class="ym-wbox">

<div class="textHeader">



<?php 
if(isset($_GET['passage'])){
    
    //$holy_str = 'Mat. 2:1-2; 3:3-5:8; 6:7,9';
    $holy_str = $_GET['passage'];
    //$holy_str = $_GET['verse'];
    //$holy_str = "John 3:16";
    

    
    $verses = trim_citation($holy_str);
    
    //echo "<pre>".print_r($holy_str, 1)."</pres><br />";
    
    $bible = $Book["All"];
    //header('Content-type: application/json'); 
    $verse_result;
    
    if(count($verses)>1 && is_array($verses)){
    
        foreach($verses as $passage){ 
            
            /*
                Mat 3:3-5:8;
                (Might need to slice ranges)            
                Format: b c:v-c:v (sum())
                        Possible query:
                            (chapter => 3 AND verse >=3) AND (chapter <=5 AND verse <=8)
                    
            */
            
            $listcheck = preg_split('/[-]/', $passage);
            
            if(count($listcheck)>=2){ 
                
                // If ":" exists in splitted passage at right side index 1
                if(preg_match( '/[:]/', $listcheck[1])){
                    $repeat = str_replace("-", "to", $passage);
                    //echo "<pre>Listcheck: $repeat</pre><br />";
                    
                    list($bk, $ch, $frm, $t) = preg_split('/[ :-]/', $repeat); 

                    // echo '<pre>'.print_r(array($bk, $ch, $frm, $t),1).'</pre>';
                    getVerse($bk, $ch, $frm, $t);
                } else { // If If ":" is absent in splitted passage at right side index 1
                    list($bk, $ch, $frm, $t) = preg_split('/[ :-]/', $passage); 
                    getVerse($bk, $ch, $frm, $t);
                }
            } else {
                // match " ", or "-" or ":";
                $list = preg_split( '/[ :-]/', $passage ); 
                
                if(count($list)==3){
                    
                } elseif(count($list)==4) {
        
                }
                list($book, $chapter, $from, $to) = $list;

                // show_r($passage);
                
                // echo "$book is on the list;<br />";
                getVerse($book, $chapter, $from, $to);
                
                // You can run a db query here...
                
                
                //echo "The book of ". $book ."<br />";
                
                
                /*echo $book; //"Luke" */ 
                
                /*
                From format:
                $holy_str = 'Mat. 2:1-5; 3:3-5:8; 6:7,9; 8';
                becomes:
                    Mat. 2:1-5;
                    Format: b c : v-v
                    Split results into Array
                    (
                        [0] => Mat
                        [1] => 2
                        [2] => 1
                        [3] => 5
                    )
                */
                
                 /* 
    
                        Mat 6:7,9
                        Format: b c : v, v
                            becomes 
                                [0] => Mat
                                [1] => 7
                                [2] => 9
                                
                
                Lastly,
                    Mat 8
                    Format: b c
                                [0] => Mat
                                [1] => 8
                */
            }
            
            

        }
    
    } else {
        list($bk, $ch, $frm, $t) = preg_split('/[ :-]/', $verses[0]); 

        getVerse($bk, $ch, $frm, $t);

        //echo "$BookIndex[$bk], $ch, $frm, $t";
    }

    echo "<h1>$bk</h1>";

    $book_count = $db->rawQueryOne("SELECT DISTINCT(CHAPTER) AS count FROM `fgbibledb_kjv` WHERE BOOK = ? ORDER BY CHAPTER DESC LIMIT 1", Array($BookIndex[$bk]));

    if($db->count>0){
    	$bc = $book_count['count'];
    } else {
    	exit();
    }

    echo "<p class='ym-noprint' style='color:#aa2023;'>";
    for($i = 1; $i <= $bc; $i++){
    	
    	if($i == $ch){
    		echo "<span class='chapread'>$i</span>\n";
    	} else {
    		echo "<a href='?passage=$bk ".$i."' class='chap'>$i</a>\n";
    	}
    	
    }
    echo "</p>";

    if($BookIndex[$bk]<=39) {
        $testament = 'ot';
        $section = 'A';
        $source = 'ENGKJVO2DA';
        $nt = 0;
    } else {
        $testament = 'nt';
        $section = 'B';
        $source = 'ENGKJVN2DA';
        $nt = 39;
    }

    $mp3 = "http://localhost/uploads/native/".$testament."/$section".sprintf("%02d", $BookIndex[$bk]-$nt)."_".sprintf("%02d", $ch)."_".$bk."_".$source.".mp3";

   	echo '

</div>

<div id="0" class="textAudio ym-noprint">
<div class="sm2-bar-ui compact full-width flat">
<div class="bd sm2-main-controls">
<div class="sm2-inline-texture"></div>
<div class="sm2-inline-gradient"></div>
<div class="sm2-inline-element sm2-button-element">
<div class="sm2-button-bd">
<a href="#play" class="sm2-inline-button play-pause">Play / pause</a>
</div>
</div>
<div class="sm2-inline-element sm2-inline-status">
<div class="sm2-playlist">
<div class="sm2-playlist-target">
<noscript><p>JavaScript is required.</p></noscript>
</div>
</div>
<div class="sm2-progress">
<div class="sm2-row">
<div class="sm2-inline-time">0:00</div>
<div class="sm2-progress-bd">
<div class="sm2-progress-track">
<div class="sm2-progress-bar"></div>
<div class="sm2-progress-ball"><div class="icon-overlay"></div></div>
</div>
</div>
<div class="sm2-inline-duration">0:00</div>
</div>
</div>
</div>
<div class="sm2-inline-element sm2-button-element sm2-volume">
<div class="sm2-button-bd">
<span class="sm2-inline-button sm2-volume-control volume-shade"></span>
<a href="#volume" class="sm2-inline-button sm2-volume-control">volume</a>
</div>
</div>
<div class="sm2-inline-element sm2-button-element">
<div class="sm2-button-bd">
<a href="'.$mp3.'" target="_blank" title="Right Click and select Save As to Download" class="sm2-inline-button download sm2-exclude"></a>
</div>
</div>
</div>
<div class="bd sm2-playlist-drawer sm2-element">
<div class="sm2-inline-texture">
<div class="sm2-box-shadow"></div>
</div>
<div class="sm2-playlist-wrapper">
<ul class="sm2-playlist-bd">
<li><a href="'.$mp3.'">Audio of '.$bk.' - Chapter '.$ch.' </a></li>
</ul>
</div>
</div>
</div>
</div> 

<div class="leftrightdiv">
<div class="playerOne">
<div class="shareright"><a class="decreaseFont ym-button2">-</a><a class="resetFont ym-button2"><span class="f1">A</span><span class="f2">A</span><span class="f3">A</span></a><a class="increaseFont ym-button2">+</a>
</div>
</div>
<div class="playerTwo">
<div>

<button class="btn-toggle btn-toggle_black" title="Dark/Night Mode"><img src="_assets/img/night.gif" class="imageatt" alt="arrowright" /></button>
</div>
</div>
</div>

</div>

<div class="textOptions"> 
<div class="textBody" id="textBody">
<h3>Chapter '.$ch.'</h3>
<!--... the Word of God:--><span class="dimver"> 
</span>
';

    echo "<p>$verse_result \n</p>";
            
}

$next;
$chapter = FALSE;
if($ch == $bc){
    if(isset($Book["All"][$BookIndex[$bk]+1])){
        $prev = $Book["All"][$BookIndex[$bk]] . " ". ($ch-1);
        $next = $Book["All"][$BookIndex[$bk]+1] . " 1";
    } else {
        $chapter = TRUE;
        $prev = $Book["All"][$BookIndex[$bk]] . " 1";
        $next = "Genesis 1";
    }
} else {
	$next = $Book["All"][$BookIndex[$bk]]. " ". ($ch+1);
    if($ch-1 > 0)
        $prev = $Book["All"][$BookIndex[$bk]]. " ". ($ch-1);
    else {
        if($bk!='Genesis'){
            $pc = $db->rawQueryOne("SELECT DISTINCT(CHAPTER) AS count FROM `fgbibledb_kjv` WHERE BOOK = ? ORDER BY CHAPTER DESC LIMIT 1", Array($BookIndex[$bk]-1));
            $prev = $Book["All"][$BookIndex[$bk]-1]. " ". ($pc['count']);
        } else {
            $prev = $Book["All"][$BookIndex['Revelation']]. " 22";
        }
        
    }
}
?>

<div class="fadeout" style="display:none;">

<a class="bible-nav-button nav-right fixed dim br-100 ba b--black-20 pa2 pa3-m flex items-center justify-center bg-white right-1" href="?passage=<?php echo $next ?>" title="<?php echo $next ?>" data-vars-event-category="Bible Chapter" data-vars-event-action="Next" data-vars-event-label="nextChapter"><div class="flex"><svg class="reader-arrow" width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg"><path transform="rotate(0, 12, 12)" stroke="none" stroke-width="0" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" fill="#888888" d="M 9.07,17.44 L 14.4,12.1 14.4,12.1 C 14.6,11.91 14.6,11.59 14.4,11.4 L 9.09,6.08 9.09,6.08 C 8.7,5.7 8.7,5.08 9.09,4.69 L 9.09,4.69 9.09,4.69 C 9.47,4.31 10.09,4.31 10.47,4.69 L 16.85,11.07 16.85,11.07 C 17.24,11.46 17.24,12.09 16.85,12.49 L 10.49,18.85 10.49,18.85 C 10.09,19.24 9.46,19.24 9.07,18.85 L 9.07,18.85 9.07,18.85 C 8.68,18.46 8.68,17.83 9.07,17.44 Z M 9.07,17.44"></path></svg></div></a>
<a class="bible-nav-button nav-left fixed dim br-100 ba b--black-20 pa2 pa3-m flex items-center justify-center bg-white left-1" href="?passage=<?php echo $prev ?>" title="<?php echo $prev ?>" data-vars-event-category="Bible Chapter" data-vars-event-action="Previous" data-vars-event-label="previousChapter"><div class="flex"><svg class="reader-arrow h-100 ma0 pa0" width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg"><path transform="rotate(180, 12, 12)" stroke="none" stroke-width="0" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" fill="#888888" d="M 9.07,17.44 L 14.4,12.1 14.4,12.1 C 14.6,11.91 14.6,11.59 14.4,11.4 L 9.09,6.08 9.09,6.08 C 8.7,5.7 8.7,5.08 9.09,4.69 L 9.09,4.69 9.09,4.69 C 9.47,4.31 10.09,4.31 10.47,4.69 L 16.85,11.07 16.85,11.07 C 17.24,11.46 17.24,12.09 16.85,12.49 L 10.49,18.85 10.49,18.85 C 10.09,19.24 9.46,19.24 9.07,18.85 L 9.07,18.85 9.07,18.85 C 8.68,18.46 8.68,17.83 9.07,17.44 Z M 9.07,17.44"></path></svg></div></a>
</div>

<!-- <audio controls autoplay>
  <source src='<?php echo $mp3; ?>' type="audio/mpeg">
  Your browser does not support the audio element.
</audio> -->

<!--... sharper than any twoedged sword... -->
</div> <!-- /textBody -->
</div><!-- /textOptions -->
</div><!-- /ym-wbox end -->
</div><!-- /ym-wrapper end -->
</div><!-- /main -->
<div class="ym-wrapper">
<div class="ym-wbox">
<div class="shareright ym-noprint">
<!--next chapter start/Top-->
<a class="ym-button" title="Page TOP" href="#m" target="_top">&nbsp;<img src="_assets/img/arrow_up.png" class="imageatt" alt="arrowup"/>&nbsp;</a>
<a class="ym-button" title="<?php echo !$chapter ? 'Next chapter' : 'Back to Genesis'?>" href="?passage=<?php echo $next ?>">&nbsp;<img src="_assets/img/arrow_right.png" class="imageatt" alt="arrowright"/>&nbsp;</a></p>
<!--next chapter end-->
</div>
</div>
</div>
<footer>
<div class="ym-wrapper">
<div id="redborder" class="ym-wbox">
<p class="alignCenter">Courtesy of Wordproject® a registered domain of <a href="#" target="_top">International Biblical Association</a>, a non-profit organization registered in Macau, China.	</p>
</div>
</footer>
</body>
<script src="_assets/js/jquery-1.8.0.min.js"></script>
<script src="_assets/js/soundmanager2.js"></script>
<script src="_assets/js/script.js"></script>
<script src="_assets/js/jquery.waypoints.js"></script>
<script src="_assets/js/sticky.js"></script>
<script src="_assets/js/bar-ui.js"></script>
<!-- <script src="_assets/js/script.js"></script> -->


<script>

var sticky = new Waypoint.Sticky({
element: $('.textAudio')[0],
});
// var sound = soundManager.setup({
//     url: '<?php echo $mp3; ?>',
//         onready: function() {

//     }

// });

function loopSound(sound) {
  window.setTimeout(function() {
    sound.play({
       onfinish:  function() {
         alert('finished');
       },
    });
  }, 500);  // window.setTimeout
}

soundManager.setup({
  onready: function() {
    var sound = soundManager.createSound({
    id: 'music',
    url: '<?php echo $mp3; ?>',
    autoLoad: true,
    autoPlay: false,
    // onload: function() {
    //     $('.play-pause').trigger('click');
    // },
    onready: function() {
        alert('test');
    }
  });
 }
});


// $('.play-pause').click(function(){
//   var sound = soundManager.getSoundById('music');
//   sound.toggleMute();
// })



</script>


<script src="_assets/js/jquery.dropotron.min.js"></script>
<script src="_assets/js/skel.min.js"></script>
<script src="_assets/js/skel-viewport.min.js"></script>
<script src="_assets/js/util.js"></script>
<script src="_assets/js/main.js"></script>
<script src="_assets/js/_darkmode.js"></script>

<script>
// (function makeDiv(){
//     var divsize = ((Math.random()*100) + 50).toFixed();
//     var color = '#'+ Math.round(0xffffff * Math.random()).toString(16);
//     $newdiv = $('<div/>').css({
//         'width':'300px',
//         'height':'100px',
//         'background-color': color
//     });
    
//     var posx = (Math.random() * ($(window).width() - divsize)).toFixed();
//     var posy = (Math.random() * ($(window).height() - divsize)).toFixed();
    
//     $newdiv.css({
//         'position':'fixed',
//         'left':posx+'px',
//         'top':posy+'px',
//         'display':'none'
//     }).appendTo( 'body' ).fadeIn(100).delay(300).fadeOut(200, function(){
//        $(this).remove();
//        makeDiv(); 
//     }); 
// })();
</script>
</html>
